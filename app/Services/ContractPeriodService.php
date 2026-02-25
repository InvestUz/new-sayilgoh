<?php

namespace App\Services;

use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Contract Period Service
 *
 * Reusable service for calculating contract periods (years) and filtering schedules.
 * This provides "current period by default, full data on demand" pattern.
 *
 * Usage:
 *   $periodService = new ContractPeriodService($contract);
 *   $currentPeriod = $periodService->getCurrentPeriod();
 *   $allPeriods = $periodService->getAllPeriods();
 */
class ContractPeriodService
{
    protected Contract $contract;
    protected Carbon $today;
    protected Collection $allSchedules;
    protected array $periods = [];
    protected ?int $currentPeriodNum = null;

    public function __construct(Contract $contract, ?Carbon $referenceDate = null)
    {
        $this->contract = $contract;
        $this->today = $referenceDate ?? Carbon::today();
        $this->allSchedules = $contract->paymentSchedules->sortBy('tolov_sanasi');

        $this->calculatePeriods();
    }

    /**
     * Calculate all contract periods (12-month year periods)
     */
    protected function calculatePeriods(): void
    {
        if ($this->allSchedules->count() === 0) {
            return;
        }

        // Group schedules by year-month
        $schedulesByYearMonth = $this->allSchedules->groupBy(function($s) {
            return Carbon::parse($s->tolov_sanasi)->format('Y-m');
        })->sortKeys();

        // Get first and last schedule dates
        $firstScheduleDate = Carbon::parse($this->allSchedules->first()->tolov_sanasi);
        $lastScheduleDate = Carbon::parse($this->allSchedules->last()->tolov_sanasi);

        // Calculate number of 12-month periods
        $totalMonths = $firstScheduleDate->diffInMonths($lastScheduleDate) + 1;
        $numPeriods = max(1, ceil($totalMonths / 12));

        // Build periods
        $periodNum = 1;
        $periodStartDate = $firstScheduleDate->copy();

        while ($periodNum <= $numPeriods && $periodStartDate->lte($lastScheduleDate)) {
            $periodEndDate = $periodStartDate->copy()->addMonths(12)->subDay();

            // Don't extend beyond last schedule
            if ($periodEndDate->gt($lastScheduleDate)) {
                $periodEndDate = $lastScheduleDate->copy();
            }

            // Get schedules for this period
            $periodSchedules = $this->allSchedules->filter(function($s) use ($periodStartDate, $periodEndDate) {
                $scheduleDate = Carbon::parse($s->tolov_sanasi);
                return $scheduleDate->gte($periodStartDate) && $scheduleDate->lte($periodEndDate);
            })->values();

            // Calculate period stats
            $stats = $this->calculatePeriodStats($periodSchedules);

            $this->periods[] = [
                'num' => $periodNum,
                'start' => $periodStartDate->copy(),
                'end' => $periodEndDate->copy(),
                'schedules' => $periodSchedules,
                'stats' => $stats,
                'is_current' => $this->today->gte($periodStartDate) && $this->today->lte($periodEndDate),
            ];

            // Check if this is current period
            if ($this->today->gte($periodStartDate) && $this->today->lte($periodEndDate)) {
                $this->currentPeriodNum = $periodNum;
            }

            $periodStartDate->addMonths(12);
            $periodNum++;
        }
    }

    /**
     * Calculate statistics for a period's schedules
     */
    protected function calculatePeriodStats(Collection $schedules): array
    {
        $total = $schedules->sum('tolov_summasi');
        $paid = $schedules->sum('tolangan_summa');
        $debt = $schedules->sum('qoldiq_summa');
        $penalty = $schedules->sum(function($s) {
            return ($s->penya_summasi ?? 0) - ($s->tolangan_penya ?? 0);
        });

        // Count overdue schedules (using effective deadline)
        $overdueCount = $schedules->filter(function($s) {
            if ($s->qoldiq_summa <= 0) return false;
            $effectiveDeadline = $s->custom_oxirgi_muddat
                ? Carbon::parse($s->custom_oxirgi_muddat)
                : Carbon::parse($s->oxirgi_muddat);
            return $effectiveDeadline->lt($this->today);
        })->count();

        $paidCount = $schedules->filter(fn($s) => $s->qoldiq_summa == 0)->count();
        $percent = $total > 0 ? round(($paid / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'paid' => $paid,
            'debt' => $debt,
            'penalty' => $penalty,
            'overdue_count' => $overdueCount,
            'paid_count' => $paidCount,
            'total_count' => $schedules->count(),
            'percent' => $percent,
        ];
    }

    /**
     * Get all periods
     */
    public function getAllPeriods(): array
    {
        return $this->periods;
    }

    /**
     * Get current period (or first period if none is current)
     */
    public function getCurrentPeriod(): ?array
    {
        if (empty($this->periods)) {
            return null;
        }

        // Find current period
        foreach ($this->periods as $period) {
            if ($period['is_current']) {
                return $period;
            }
        }

        // Fallback to first period if no current
        return $this->periods[0];
    }

    /**
     * Get current period number
     */
    public function getCurrentPeriodNum(): ?int
    {
        return $this->currentPeriodNum;
    }

    /**
     * Get periods other than current
     */
    public function getOtherPeriods(): array
    {
        $current = $this->getCurrentPeriod();
        if (!$current) {
            return $this->periods;
        }

        return array_values(array_filter($this->periods, function($p) use ($current) {
            return $p['num'] !== $current['num'];
        }));
    }

    /**
     * Get grand totals across all periods
     */
    public function getGrandTotals(): array
    {
        $grandTotal = $this->allSchedules->sum('tolov_summasi');
        $grandPaid = $this->allSchedules->sum('tolangan_summa');
        $grandDebt = max(0, $grandTotal - $grandPaid);
        $grandPenya = max(0,
            $this->allSchedules->sum('penya_summasi') -
            $this->allSchedules->sum('tolangan_penya')
        );

        // Overdue amount (using effective deadline)
        $grandOverdue = $this->allSchedules->filter(function($s) {
            if ($s->qoldiq_summa <= 0) return false;
            $effectiveDeadline = $s->custom_oxirgi_muddat
                ? Carbon::parse($s->custom_oxirgi_muddat)
                : Carbon::parse($s->oxirgi_muddat);
            return $effectiveDeadline->lt($this->today);
        })->sum('qoldiq_summa');

        $grandPercent = $grandTotal > 0 ? round(($grandPaid / $grandTotal) * 100, 1) : 0;

        return [
            'total' => $grandTotal,
            'paid' => $grandPaid,
            'debt' => $grandDebt,
            'penalty' => $grandPenya,
            'overdue' => $grandOverdue,
            'percent' => $grandPercent,
        ];
    }

    /**
     * Get schedules for current period only
     */
    public function getCurrentPeriodSchedules(): Collection
    {
        $current = $this->getCurrentPeriod();
        return $current ? $current['schedules'] : collect([]);
    }

    /**
     * Check if contract is expired
     */
    public function isContractExpired(): bool
    {
        $contractEnd = Carbon::parse($this->contract->tugash_sanasi);
        return $contractEnd->lt($this->today);
    }

    /**
     * Get current month and year (for highlighting)
     */
    public function getCurrentMonthYear(): array
    {
        return [
            'month' => $this->today->month,
            'year' => $this->today->year,
        ];
    }

    /**
     * Static factory method for convenience
     */
    public static function forContract(Contract $contract, ?Carbon $referenceDate = null): self
    {
        return new self($contract, $referenceDate);
    }

    /**
     * Get summary data suitable for API/JSON responses
     */
    public function toArray(): array
    {
        return [
            'periods' => array_map(function($period) {
                return [
                    'num' => $period['num'],
                    'start' => $period['start']->format('Y-m-d'),
                    'end' => $period['end']->format('Y-m-d'),
                    'is_current' => $period['is_current'],
                    'stats' => $period['stats'],
                    'schedule_count' => $period['schedules']->count(),
                ];
            }, $this->periods),
            'current_period_num' => $this->currentPeriodNum,
            'grand_totals' => $this->getGrandTotals(),
            'is_expired' => $this->isContractExpired(),
            'current_month_year' => $this->getCurrentMonthYear(),
        ];
    }
}
