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
 * Penalty calculation (Section 8.2):
 * - penya = fakt (tolangan_summa) × kechikish_kunlari × 0.004 (grafik/qoldiq emas)
 * - Fakt bo‘lmasa → ushbu formula bo‘yicha 0
 * - Chegara: faktning 50%
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
        $effectiveDeadline = $this->resolveEffectiveDeadline();

        return $effectiveDeadline->isPast() && $this->holat !== 'tolangan';
    }

    /**
     * Effective deadline used by penalty/overdue logic.
     *
     * Rule: for first schedule row (oy_raqami=1), if custom deadline is not set,
     * use contract start date to match table/display logic.
     */
    protected function resolveEffectiveDeadline(): Carbon
    {
        if (!empty($this->custom_oxirgi_muddat)) {
            return Carbon::parse($this->custom_oxirgi_muddat);
        }

        $isFirstRow = (int) ($this->oy_raqami ?? 0) === 1;
        if ($isFirstRow && $this->contract && !empty($this->contract->boshlanish_sanasi)) {
            return Carbon::parse($this->contract->boshlanish_sanasi);
        }

        return Carbon::parse($this->oxirgi_muddat);
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
     * Fakt tushum × stavka × (mavjud) kechikish; chegara: faktning 50%.
     *
     * @param bool $save DB ga saqlansinmi
     * @return float Joriy `penya_summasi`
     */
    public function calculatePenya(bool $save = true): float
    {
        return $this->calculatePenyaAtDate(Carbon::today(), $save, false);
    }

    /**
     * Calculate penalty at a specific date
     * This is the PRIMARY penalty calculation method.
     *
     * PERSISTENCE: to'g'ri formula = `penya_summasi` (odatdagi yuk, sahifa).
     * To'liq to'langan qator: `bypassMuzlati` bo'lmasa, mavjud penya
     * qayta hisoblanmaydi (muzlatish). `penalties:recalculate` bypass bilan tuzatadi.
     *
     * Hisob qoidalari (8.2):
     * 1. tolovSanasi <= oxirgi_muddat  → yangi penya hisoblanmaydi (sana logikasi o‘zgarmaydi)
     * 2. tolangan_summa <= 0 → yangi penya = 0 (fakt yo‘q)
     * 3. Fakt bo‘lsa: kechikish odatda shu oydagi oxirgi to‘lov sanasigacha
     *    (jadvaldagi 4 kungacha), "bugun"gacha o‘smasin.
     * 4. Aks holda: penya = fakt * 0.004 * kechikish, max = fakt * 0.5
     *
     * @param Carbon $tolovSanasi   To'lov yoki hisob sanasi
     * @param bool   $save          DB ga saqlansinmi
     * @param bool   $bypassMuzlati `true`: "to'liq to'langan, eski penya" muzlatishini
     *                          o'tkazib yuborib, formuladan qayta yozish (`penalties:recalculate` uchun)
     * @return float Joriy `penya_summasi` qiymati
     */
    public function calculatePenyaAtDate(Carbon $tolovSanasi, bool $save = true, bool $bypassMuzlati = false): float
    {
        $existingPenya = (float) $this->penya_summasi;

        // To'liq to'langanda: odatda penya o'zmaydi; lekin noto'g'ri saqlangan qiymatni
        // `penalties:recalculate` bilan tuzatish uchun bypass.
        if (! $bypassMuzlati && (float) $this->qoldiq_summa <= 0 && $existingPenya > 0) {
            return $existingPenya;
        }

        $contract = $this->contract;
        if (! $bypassMuzlati && $contract && $contract->is_expired) {
            return $existingPenya;
        }

        $oxirgiMuddat = $this->resolveEffectiveDeadline();

        // Hali muddat o'tmagan — tarixiy penya bor bo'lsa saqlaymiz
        if ($tolovSanasi->lte($oxirgiMuddat)) {
            return $existingPenya;
        }

        $fakt = (float) $this->tolangan_summa;
        $penyaTugashKuni = $this->penyaAccrualEndDate($tolovSanasi, $oxirgiMuddat);

        if ($fakt > 0 && $penyaTugashKuni->lte($oxirgiMuddat)) {
            $kechikishKunlari = 0;
            $newPenya = 0.0;
        } else {
            $kechikishKunlari = (int) $oxirgiMuddat->diffInDays($penyaTugashKuni);
            if ($fakt <= 0) {
                $newPenya = 0.0;
            } else {
                $maxPenya = $fakt * self::MAX_PENYA_RATE;
                $newPenya = min($fakt * self::PENYA_RATE * $kechikishKunlari, $maxPenya);
            }
        }

        $finalPenya = round($newPenya, 2);

        $this->kechikish_kunlari = max((int) $this->kechikish_kunlari, $kechikishKunlari);
        $this->penya_summasi = $finalPenya;

        if ($save) {
            $this->save();
        }

        return (float) $this->penya_summasi;
    }

    /**
     * Fakt tushganda: penya muddati oxirgi muddatdan shu kalendar oydagi
     * eng oxirgi to'lov sanasigacha; "as_of" sanaga o'smaydi.
     * Fakt yo'q: hisob sanasi (odatda bugun) bo'yicha.
     */
    protected function penyaAccrualEndDate(Carbon $tolovSanasi, Carbon $oxirgiMuddat): Carbon
    {
        $asOf = $tolovSanasi->copy()->startOfDay();
        $dead = $oxirgiMuddat->copy()->startOfDay();
        if ((float) $this->tolangan_summa <= 0) {
            return $asOf;
        }
        $c = $this->contract;
        if (! $c) {
            return $asOf;
        }
        $c->loadMissing('payments');
        $last = $c->payments
            ->where('holat', 'tasdiqlangan')
            ->filter(function ($p) {
                $d = Carbon::parse($p->tolov_sanasi);

                return (int) $d->month === (int) $this->oy && (int) $d->year === (int) $this->yil;
            })
            ->sortByDesc('tolov_sanasi')
            ->first();
        if (! $last) {
            return $asOf;
        }
        $payD = Carbon::parse($last->tolov_sanasi)->startOfDay();
        if ($payD->lte($dead)) {
            return $dead;
        }

        return $payD->min($asOf);
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

        $oxirgiMuddat = $this->resolveEffectiveDeadline();

        // Calculate overdue days
        $overdueDays = 0;
        if ($asOfDate->gt($oxirgiMuddat) && $this->holat !== 'tolangan') {
            $overdueDays = $oxirgiMuddat->diffInDays($asOfDate);
        }

        $fakt = (float) $this->tolangan_summa;
        $calculatedPenalty = 0.0;
        $maxPenalty = 0.0;
        $penaltyCapApplied = false;
        if ($fakt > 0 && $overdueDays > 0) {
            $raw = $fakt * self::PENYA_RATE * $overdueDays;
            $maxPenalty = $fakt * self::MAX_PENYA_RATE;
            $penaltyCapApplied = $raw > $maxPenalty;
            $calculatedPenalty = min($raw, $maxPenalty);
        }

        return [
            'overdue_days' => $overdueDays,              // integer, 0 allowed
            'penalty_rate' => self::PENYA_FOIZI,        // always 0.4%
            'calculated_penalty' => round($calculatedPenalty, 2), // numeric, 0 allowed
            'penalty_cap_applied' => $penaltyCapApplied,
            'overdue_amount' => $fakt,                  // penya asosi: fakt tushum
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
