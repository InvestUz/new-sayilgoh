<?php

namespace App\Models;

use App\Services\PenaltyCalculatorService;
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
    // Daily rate: 0.04% = 0.0004
    const PENYA_FOIZI = 0.04; // 0.04% per day (displayed as percentage)
    const PENYA_RATE = 0.0004; // 0.04% per day (decimal for calculation)
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
     * Calculate penalty as of current date
     * Uses PenaltyCalculatorService for contract-compliant calculation
     *
     * Contract Rules:
     * - Rate: 0.04% per day
     * - Cap: 50% of overdue amount
     * - Only applies when current date > due date
     *
     * @param bool $save - Whether to persist the result
     * @return float Calculated penalty amount
     */
    public function calculatePenya(bool $save = true): float
    {
        return $this->calculatePenyaAtDate(Carbon::today(), $save);
    }

    /**
     * Calculate penalty at a specific date
     * This is the PRIMARY penalty calculation method
     *
     * Business Rules (Contract Section 8.2):
     * 1. If payment_date <= due_date → penalty = 0
     * 2. penalty = overdue_amount * 0.0004 * overdue_days
     * 3. penalty <= overdue_amount * 0.5 (cap)
     * 4. overdue_days = max(0, payment_date - due_date)
     *
     * @param Carbon $tolovSanasi - The date to calculate penalty as of
     * @param bool $save - Whether to persist the result
     * @return float Calculated penalty amount
     */
    public function calculatePenyaAtDate(Carbon $tolovSanasi, bool $save = true): float
    {
        // Rule: Fully paid schedules have no new penalty
        if ($this->holat === 'tolangan') {
            return (float) $this->penya_summasi;
        }

        // Use custom deadline if set, otherwise use original deadline
        $oxirgiMuddat = $this->custom_oxirgi_muddat
            ? Carbon::parse($this->custom_oxirgi_muddat)
            : Carbon::parse($this->oxirgi_muddat);

        // Rule 1: If payment_date <= due_date → penalty = 0
        if ($tolovSanasi->lte($oxirgiMuddat)) {
            $this->kechikish_kunlari = 0;
            $this->penya_summasi = 0;

            if ($save) {
                $this->save();
            }
            return 0;
        }

        // Rule 4: Calculate overdue days
        $kechikishKunlari = $oxirgiMuddat->diffInDays($tolovSanasi);
        $this->kechikish_kunlari = $kechikishKunlari;

        // Rule 2: penalty = overdue_amount * 0.0004 * overdue_days
        $overdueAmount = (float) $this->qoldiq_summa;
        $penya = $overdueAmount * self::PENYA_RATE * $kechikishKunlari;

        // Rule 3: penalty <= overdue_amount * 0.5 (cap at 50%)
        $maxPenya = $overdueAmount * self::MAX_PENYA_RATE;
        $penya = min($penya, $maxPenya);

        $this->penya_summasi = round($penya, 2);

        if ($save) {
            $this->save();
        }

        return $this->penya_summasi;
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
     * Apply payment to this schedule
     *
     * Payment allocation order (Contract Rule 6):
     * a) penalty (ONLY if penalty > 0)
     * b) overdue rent
     * c) current rent
     *
     * Rule 8: If penalty = 0, skip penalty allocation step entirely
     *
     * @param float $amount Amount to apply
     * @param Carbon|null $paymentDate Date of payment (for penalty calculation)
     * @return array Result with penalty_tolangan, asosiy_tolangan, qoldiq
     */
    public function applyPayment(float $amount, ?Carbon $paymentDate = null): array
    {
        $paymentDate = $paymentDate ?? Carbon::today();

        $result = [
            'penya_tolangan' => 0,
            'asosiy_tolangan' => 0,
            'qoldiq' => $amount,
            'penalty_details' => null,
        ];

        // Calculate penalty at payment date
        $this->calculatePenyaAtDate($paymentDate, false);
        $result['penalty_details'] = $this->getPenaltyDetails($paymentDate);

        // Rule 6a: Pay penalty first (ONLY if penalty > 0 - Rule 8)
        $qoldiqPenya = $this->penya_summasi - $this->tolangan_penya;
        if ($qoldiqPenya > 0 && $result['qoldiq'] > 0) {
            $penyaTolov = min($qoldiqPenya, $result['qoldiq']);
            $this->tolangan_penya += $penyaTolov;
            $result['penya_tolangan'] = $penyaTolov;
            $result['qoldiq'] -= $penyaTolov;
        }

        // Rule 6b & 6c: Pay rent (overdue and current)
        if ($this->qoldiq_summa > 0 && $result['qoldiq'] > 0) {
            $asosiyTolov = min($this->qoldiq_summa, $result['qoldiq']);
            $this->tolangan_summa += $asosiyTolov;
            $this->qoldiq_summa -= $asosiyTolov;
            $result['asosiy_tolangan'] = $asosiyTolov;
            $result['qoldiq'] -= $asosiyTolov;
        }

        // Update status
        $this->updateStatus();
        $this->save();

        return $result;
    }

    /**
     * Holatni yangilash
     */
    public function updateStatus(): void
    {
        if ($this->qoldiq_summa <= 0) {
            $this->holat = 'tolangan';
        } elseif ($this->tolangan_summa > 0) {
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
