<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * To'lov Grafigi (Payment Schedule) modeli
 *
 * Penalty calculation follows contract rules (Section 8.2):
 * - Rate: 0.4% per day on overdue amount
 * - Cap: 50% maximum of overdue amount
 * - Only applies when payment_date > due_date
 */
class PaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'oy_raqami',
        'yil',
        'oy',
        'tolov_sanasi',
        'oxirgi_muddat',
        'custom_oxirgi_muddat',
        'muddat_ozgarish_izoh',
        'tolov_summasi',
        'tolangan_summa',
        'qoldiq_summa',
        'penya_summasi',
        'tolangan_penya',
        'kechikish_kunlari',
        'holat',
    ];

    protected $casts = [
        'tolov_sanasi' => 'date',
        'oxirgi_muddat' => 'date',
        'custom_oxirgi_muddat' => 'date',
        'tolov_summasi' => 'decimal:2',
        'tolangan_summa' => 'decimal:2',
        'qoldiq_summa' => 'decimal:2',
        'penya_summasi' => 'decimal:2',
        'tolangan_penya' => 'decimal:2',
    ];

    // Penalty constants (from contract section 8.2)
    // Daily rate: 0.4% = 0.004
    const PENYA_FOIZI = 0.4; // 0.4% per day (displayed as percentage)
    const PENYA_RATE = 0.004; // 0.4% per day (decimal for calculation)
    const MAX_PENYA_FOIZI = 50; // Maximum 50% of debt
    const MAX_PENYA_RATE = 0.5; // Maximum 50% (decimal for calculation)

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Oy nomi (O'zbekcha)
     */
    public function getOyNomiAttribute(): string
    {
        $oylar = [
            1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart',
            4 => 'Aprel', 5 => 'May', 6 => 'Iyun',
            7 => 'Iyul', 8 => 'Avgust', 9 => 'Sentabr',
            10 => 'Oktabr', 11 => 'Noyabr', 12 => 'Dekabr'
        ];
        return $oylar[$this->oy] ?? '';
    }

    /**
     * To'liq davr nomi
     */
    public function getDavrNomiAttribute(): string
    {
        return "{$this->oy_nomi} {$this->yil}";
    }

    /**
     * Holat nomi (O'zbekcha)
     */
    public function getHolatNomiAttribute(): string
    {
        return match($this->holat) {
            'kutilmoqda' => 'Kutilmoqda',
            'tolanmagan' => 'To\'lanmagan',
            'qisman_tolangan' => 'Qisman to\'langan',
            'tolangan' => 'To\'langan',
            default => $this->holat
        };
    }

    /**
     * Holat rangi (CSS class)
     */
    public function getHolatRangiAttribute(): string
    {
        return match($this->holat) {
            'kutilmoqda' => 'bg-blue-100 text-blue-700',
            'tolanmagan' => 'bg-red-100 text-red-700',
            'qisman_tolangan' => 'bg-amber-100 text-amber-700',
            'tolangan' => 'bg-green-100 text-green-700',
            default => 'bg-gray-100 text-gray-700'
        };
    }

    /**
     * Muddati o'tganmi?
     */
    public function getMuddatiOtganAttribute(): bool
    {
        // Use custom deadline if set, otherwise use original deadline
        $effectiveDeadline = $this->custom_oxirgi_muddat
            ? Carbon::parse($this->custom_oxirgi_muddat)
            : Carbon::parse($this->oxirgi_muddat);

        return $effectiveDeadline->isPast() && $this->holat !== 'tolangan';
    }

    /**
     * Qolgan penya (to'lanmagan)
     */
    public function getQoldiqPenyaAttribute(): float
    {
        return $this->penya_summasi - $this->tolangan_penya;
    }

    // ============================================
    // METHODS
    // ============================================

    /**
     * Calculate penalty as of current date.
     *
     * Contract qoidalari:
     * - Stavka: kuniga 0.4%
     * - Chegara: qarzning 50%
     * - Faqat muddat o'tgan bo'lsa qo'llaniladi
     *
     * @param bool $save DB ga saqlansinmi
     * @return float Joriy `penya_summasi`
     */
    public function calculatePenya(bool $save = true): float
    {
        return $this->calculatePenyaAtDate(Carbon::today(), $save);
    }

    /**
     * Calculate penalty at a specific date
     * This is the PRIMARY penalty calculation method.
     *
     * PERSISTENCE QOIDASI (penya yo'qolmasligi shart):
     * ──────────────────────────────────────────────────────────────────────
     *  - `penya_summasi` MONOTON ravishda o'sadi: yangi hisoblangan qiymat
     *    avvalgi `penya_summasi`'dan KICHIK bo'lsa, eski (yuqoriroq) qiymat
     *    saqlab qolinadi. Bu — qisman to'lovdan keyin yoki shartnoma
     *    to'liq yopilgandan keyin tarixda jamlangan penya o'chmasligi
     *    uchun kafolat.
     *  - Grafik to'liq to'langan bo'lsa (qoldiq_summa <= 0), `penya_summasi`
     *    "muzlatilgan" deb hisoblanadi va hech qachon avtomatik tarzda
     *    qayta nolga tushirilmaydi. Faqat `/api/penalty-payments` orqali
     *    ko'paytirilgan `tolangan_penya` uni qoplashi mumkin.
     *
     * Hisob qoidalari (Shartnoma 8.2-bandi):
     * 1. tolovSanasi <= oxirgi_muddat  → yangi penya hisoblanmaydi
     * 2. penya = qoldiq_summa * 0.004 * kechikish_kunlari
     * 3. penya <= qoldiq_summa * 0.5 (chegara 50%)
     *
     * @param Carbon $tolovSanasi To'lov yoki hisob sanasi
     * @param bool   $save        DB ga saqlansinmi
     * @return float Joriy `penya_summasi` qiymati
     */
    public function calculatePenyaAtDate(Carbon $tolovSanasi, bool $save = true): float
    {
        $existingPenya = (float) $this->penya_summasi;

        // To'liq to'langan grafiklar — penya muzlatilgan, qayta hisoblamaymiz
        if ((float) $this->qoldiq_summa <= 0) {
            return $existingPenya;
        }

        // Tugagan shartnoma — yangi penya yig'ilmaydi, mavjud qiymat saqlanadi
        $contract = $this->contract;
        if ($contract && $contract->is_expired) {
            return $existingPenya;
        }

        $oxirgiMuddat = $this->custom_oxirgi_muddat
            ? Carbon::parse($this->custom_oxirgi_muddat)
            : Carbon::parse($this->oxirgi_muddat);

        // Hali muddat o'tmagan — tarixiy penya bor bo'lsa saqlaymiz
        if ($tolovSanasi->lte($oxirgiMuddat)) {
            return $existingPenya;
        }

        $kechikishKunlari = (int) $oxirgiMuddat->diffInDays($tolovSanasi);
        $overdueAmount = (float) $this->qoldiq_summa;
        $maxPenya = $overdueAmount * self::MAX_PENYA_RATE;
        $newPenya = min($overdueAmount * self::PENYA_RATE * $kechikishKunlari, $maxPenya);

        // MONOTON: yangi qiymat eskidan past bo'lmasligi kerak
        $finalPenya = round(max($existingPenya, $newPenya), 2);

        $this->kechikish_kunlari = max((int) $this->kechikish_kunlari, $kechikishKunlari);
        $this->penya_summasi = $finalPenya;

        if ($save) {
            $this->save();
        }

        return (float) $this->penya_summasi;
    }

    /**
     * Get penalty details for display in monthly table
     * Rule 7: MUST always return overdue_days, penalty_rate, calculated_penalty
     * No NULL or empty values allowed
     *
     * @param Carbon|null $asOfDate
     * @return array
     */
    public function getPenaltyDetails(?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::today();

        // Use custom deadline if set, otherwise use original deadline
        $oxirgiMuddat = $this->custom_oxirgi_muddat
            ? Carbon::parse($this->custom_oxirgi_muddat)
            : Carbon::parse($this->oxirgi_muddat);

        // Calculate overdue days
        $overdueDays = 0;
        if ($asOfDate->gt($oxirgiMuddat) && $this->holat !== 'tolangan') {
            $overdueDays = $oxirgiMuddat->diffInDays($asOfDate);
        }

        // Calculate penalty using the formula
        $overdueAmount = (float) $this->qoldiq_summa;
        $calculatedPenalty = $overdueAmount * self::PENYA_RATE * $overdueDays;
        $maxPenalty = $overdueAmount * self::MAX_PENYA_RATE;
        $penaltyCapApplied = $calculatedPenalty > $maxPenalty;
        $calculatedPenalty = min($calculatedPenalty, $maxPenalty);

        return [
            'overdue_days' => $overdueDays,              // integer, 0 allowed
            'penalty_rate' => self::PENYA_FOIZI,        // always 0.4%
            'calculated_penalty' => round($calculatedPenalty, 2), // numeric, 0 allowed
            'penalty_cap_applied' => $penaltyCapApplied,
            'overdue_amount' => $overdueAmount,
            'max_penalty' => round($maxPenalty, 2),
        ];
    }

    /**
     * Holatni yangilash.
     *
     * Diqqat: bu metod `penya_summasi` yoki `kechikish_kunlari`'ni HECH QACHON
     * nolga keltirmaydi. Penya tarixiy ma'lumot bo'lib, faqat
     * `/api/penalty-payments` orqali qoplanishi mumkin.
     */
    public function updateStatus(): void
    {
        if ((float) $this->qoldiq_summa <= 0) {
            $this->holat = 'tolangan';
        } elseif ((float) $this->tolangan_summa > 0) {
            $this->holat = 'qisman_tolangan';
        } elseif (Carbon::parse($this->tolov_sanasi)->isPast()) {
            $this->holat = 'tolanmagan';
        } else {
            $this->holat = 'kutilmoqda';
        }
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeTolanmagan($query)
    {
        return $query->whereIn('holat', ['tolanmagan', 'qisman_tolangan']);
    }

    public function scopeMuddatiOtgan($query)
    {
        return $query->whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) < ?', [now()])
                     ->whereIn('holat', ['tolanmagan', 'qisman_tolangan']);
    }

    public function scopeKutilmoqda($query)
    {
        return $query->where('holat', 'kutilmoqda');
    }
}
