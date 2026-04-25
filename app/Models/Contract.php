<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shartnoma (Contract) modeli
 */
class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lot_id',
        'tenant_id',
        'shartnoma_raqami',
        'shartnoma_sanasi',
        'auksion_sanasi',
        'auksion_bayonnoma_raqami',
        'auksion_xarajati',
        'shartnoma_summasi',
        'yillik_ijara_haqi',
        'shartnoma_izohi',
        'oylik_tolovi',
        'shartnoma_muddati',
        'boshlanish_sanasi',
        'tugash_sanasi',
        'birinchi_tolov_sanasi',
        'tolov_kuni',
        'penya_muddati',
        'dalolatnoma_raqami',
        'dalolatnoma_sanasi',
        'dalolatnoma_holati',
        'holat',
        'joriy_yil',
        'avans_balans',
        'izoh',
        'qoshimcha_shartlar',
    ];

    protected $casts = [
        'shartnoma_sanasi' => 'date',
        'auksion_sanasi' => 'date',
        'boshlanish_sanasi' => 'date',
        'tugash_sanasi' => 'date',
        'birinchi_tolov_sanasi' => 'date',
        'dalolatnoma_sanasi' => 'date',
        'shartnoma_summasi' => 'decimal:2',
        'yillik_ijara_haqi' => 'decimal:2',
        'oylik_tolovi' => 'decimal:2',
        'auksion_xarajati' => 'decimal:2',
        'qoshimcha_shartlar' => 'array',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class)->orderBy('oy_raqami');
    }

    /**
     * Payment schedules for a specific year
     */
    public function paymentSchedulesForYear(int $year): HasMany
    {
        return $this->hasMany(PaymentSchedule::class)
            ->where('yil', $year)
            ->orderBy('oy_raqami');
    }

    /**
     * Get all available years for this contract
     */
    public function getAvailableYearsAttribute(): array
    {
        return $this->paymentSchedules()
            ->reorder()  // Clear default orderBy from relationship
            ->selectRaw('DISTINCT yil')
            ->orderBy('yil')
            ->pluck('yil')
            ->toArray();
    }

    /**
     * Get schedule stats for a specific year
     */
    public function getYearStats(int $year): array
    {
        $schedules = $this->paymentSchedules()->where('yil', $year)->get();

        return [
            'jami_summa' => $schedules->sum('tolov_summasi'),
            'tolangan' => $schedules->sum('tolangan_summa'),
            'qoldiq' => $schedules->sum('qoldiq_summa'),
            'penya' => $schedules->sum('penya_summasi') - $schedules->sum('tolangan_penya'),
            'oylar_soni' => $schedules->count(),
            'tolangan_oylar' => $schedules->where('holat', 'tolangan')->count(),
            'muddati_otgan' => $schedules->where('holat', 'tolanmagan')->count(),
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('tolov_sanasi', 'desc');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Jami to'langan summa
     */
    public function getJamiTolanganAttribute(): float
    {
        return $this->payments()->where('holat', 'tasdiqlangan')->sum('summa');
    }

    /**
     * Jami qarzdorlik (qolgan summa)
     */
    public function getJamiQarzdorlikAttribute(): float
    {
        $today = Carbon::today();

        // Debt (qarzdorlik) = only unpaid amounts whose payment date has passed
        return $this->paymentSchedules()
            ->where('qoldiq_summa', '>', 0)
            ->whereDate('tolov_sanasi', '<', $today)
            ->sum('qoldiq_summa');
    }

    /**
     * Jami penya
     */
    public function getJamiPenyaAttribute(): float
    {
        return $this->paymentSchedules()->sum('penya_summasi') -
               $this->paymentSchedules()->sum('tolangan_penya');
    }

    /**
     * Muddati o'tgan oylar soni
     */
    public function getMuddatiOtganOylarAttribute(): int
    {
        return $this->paymentSchedules()
            ->where('holat', 'tolanmagan')
            ->where('tolov_sanasi', '<', now())
            ->count();
    }

    /**
     * Get actual annual rent (use yillik_ijara_haqi if set, fallback to shartnoma_summasi)
     */
    public function getYillikIjaraHaqiAttribute($value): float
    {
        return $value ?? $this->attributes['shartnoma_summasi'] ?? 0;
    }

    /**
     * Get monthly payment amount based on annual rent
     */
    public function getOylikTolovAttribute($value): float
    {
        // If explicitly set, use it
        if ($value && $value > 0) {
            return $value;
        }

        // Otherwise calculate from annual rent
        $annualRent = $this->attributes['yillik_ijara_haqi'] ?? $this->attributes['shartnoma_summasi'] ?? 0;
        return $annualRent > 0 ? round($annualRent / 12, 2) : 0;
    }

    /**
     * Holat nomi (O'zbekcha)
     */
    public function getHolatNomiAttribute(): string
    {
        return match($this->holat) {
            'faol' => 'Faol',
            'tugagan' => 'Tugagan',
            'bekor_qilingan' => 'Bekor qilingan',
            'muzlatilgan' => 'Muzlatilgan',
            default => $this->holat
        };
    }

    /**
     * Check if contract has expired (tugash_sanasi < today)
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->tugash_sanasi) {
            return false;
        }
        return Carbon::parse($this->tugash_sanasi)->lt(Carbon::today());
    }

    /**
     * Check if contract is active (not expired and status is faol)
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->holat === 'faol' && !$this->is_expired;
    }

    /**
     * Dalolatnoma holati nomi (O'zbekcha)
     */
    public function getDalolatnomaHolatiNomiAttribute(): string
    {
        return match($this->dalolatnoma_holati) {
            'kutilmoqda' => 'Kutilmoqda',
            'topshirilgan' => 'Topshirilgan',
            'qaytarilgan' => 'Qaytarilgan',
            default => $this->dalolatnoma_holati
        };
    }

    /**
     * Formatlangan shartnoma summasi
     */
    public function getFormattedSummaAttribute(): string
    {
        return number_format($this->shartnoma_summasi, 0, '.', ' ') . ' UZS';
    }

    // ============================================
    // METHODS
    // ============================================

    /**
     * To'lov grafigini yaratish (ANNUAL RENT MODEL + PRO-RATA FIRST MONTH)
     *
     * Asosiy mantiq:
     *  - To'liq oylik = yillik_ijara_haqi ÷ 12
     *  - Agar `boshlanish_sanasi` oyning 1-chi kunida bo'lmasa, BIRINCHI grafik
     *    qisman oy uchun pro-rata bo'yicha hisoblanadi:
     *
     *      faol_kunlar = oy_kunlari − boshlanish_kuni + 1
     *      birinchi_summa = to'liq_oylik × faol_kunlar / oy_kunlari
     *      tejov = to'liq_oylik − birinchi_summa
     *      qolgan_jami = (N × to'liq_oylik) − tejov
     *      qolgan_oylik = qolgan_jami / (N − 1)
     *
     *    Misol (boshlanish=24.07.2025, yillik=141 536 724, N=12):
     *      to'liq_oylik = 11 794 727
     *      faol_kunlar = 31 − 24 + 1 = 8
     *      birinchi_summa = 11 794 727 × 8/31 = 3 043 800,52
     *      tejov = 8 750 926,48
     *      qolgan_jami = 132 785 797,52
     *      qolgan_oylik = 12 071 436,14   (× 11 oy)
     *
     *  - Birinchi grafikning `oxirgi_muddat` = `boshlanish_sanasi` (qisman oy
     *    uchun grace period yo'q — to'lov darhol kutiladi).
     *  - `birinchi_tolov_sanasi` ENDI grafik yaratishda foydalanilmaydi (legacy
     *    maydon sifatida saqlangan). Birinchi grafik har doim
     *    `boshlanish_sanasi` bo'yicha pro-rata bilan yaratiladi.
     */
    public function generatePaymentSchedule(): void
    {
        DB::table('payment_schedules')->where('contract_id', $this->id)->delete();

        $annualRent = $this->yillik_ijara_haqi ?? $this->shartnoma_summasi;
        $monthlyFull = round($annualRent / 12, 2);

        $boshlanishSanasi = Carbon::parse($this->boshlanish_sanasi);
        $tolovKuni = $this->tolov_kuni ?? 10;
        $penyaMuddati = $this->penya_muddati ?? 10;
        $shartnomaMuddati = (int) $this->shartnoma_muddati;

        // Pro-rata sharti: oyning 1-chi kunidan boshlanmagan VA shartnoma > 1 oy
        $isPartialFirst = $boshlanishSanasi->day > 1 && $shartnomaMuddati > 1;

        if ($isPartialFirst) {
            $daysInFirstMonth = $boshlanishSanasi->daysInMonth;
            $activeDaysInFirstMonth = $daysInFirstMonth - $boshlanishSanasi->day + 1;
            $firstAmount = round($monthlyFull * $activeDaysInFirstMonth / $daysInFirstMonth, 2);
            $savings = round($monthlyFull - $firstAmount, 2);
            // (N × monthly_full − savings) qolgan summa, qolgan (N−1) ga bo'linadi
            $remainingTotal = round($shartnomaMuddati * $monthlyFull - $savings, 2);
            $remainingMonthly = round($remainingTotal / ($shartnomaMuddati - 1), 2);
        } else {
            $firstAmount = $monthlyFull;
            $remainingMonthly = $monthlyFull;
        }

        $schedules = [];
        $firstYear = null;

        for ($i = 1; $i <= $shartnomaMuddati; $i++) {
            if ($i === 1) {
                // Birinchi grafik — har doim boshlanish sanasi
                $tolovSanasi = $boshlanishSanasi->copy();
                $oxirgiMuddat = $boshlanishSanasi->copy();
                $amount = $firstAmount;
            } else {
                $tolovSanasi = $boshlanishSanasi->copy()->addMonths($i - 1)->day($tolovKuni);
                $oxirgiMuddat = $tolovSanasi->copy()->addDays($penyaMuddati);
                $amount = $remainingMonthly;
            }

            if ($firstYear === null) {
                $firstYear = $tolovSanasi->year;
            }

            $schedules[] = [
                'contract_id' => $this->id,
                'oy_raqami' => $i,
                'yil' => $tolovSanasi->year,
                'oy' => $tolovSanasi->month,
                'tolov_sanasi' => $tolovSanasi->format('Y-m-d'),
                'oxirgi_muddat' => $oxirgiMuddat->format('Y-m-d'),
                'tolov_summasi' => $amount,
                'tolangan_summa' => 0,
                'qoldiq_summa' => $amount,
                'penya_summasi' => 0,
                'tolangan_penya' => 0,
                'kechikish_kunlari' => 0,
                'holat' => $tolovSanasi->isPast() ? 'tolanmagan' : 'kutilmoqda',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('payment_schedules')->insert($schedules);

        $this->joriy_yil = $firstYear;
        $this->save();
    }

    /**
     * 10 ish kunini hisoblash
     */
    public static function calculate10WorkingDays(Carbon $startDate): Carbon
    {
        $date = $startDate->copy();
        $workingDays = 0;

        while ($workingDays < 10) {
            $date->addDay();
            if (!$date->isWeekend()) {
                $workingDays++;
            }
        }

        return $date;
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeFaol($query)
    {
        return $query->where('holat', 'faol');
    }

    public function scopeQarzdor($query)
    {
        return $query->whereHas('paymentSchedules', function ($q) {
            $q->where('holat', 'tolanmagan')
              ->where('tolov_sanasi', '<', now());
        });
    }
}
