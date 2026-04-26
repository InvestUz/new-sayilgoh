<?php

namespace App\Services;

use App\Models\Contract;
use Carbon\Carbon;

/**
 * Lot "To'lov jadvali (Joriy davr)" 12-oylik davrlar jadvali (backend, Blade faqat ko'rsatadi).
 */
class ContractYearPeriodsService
{
    public function __construct(
        private readonly ScheduleDisplayService $scheduleDisplay
    ) {}

    /**
     * @return array{
     *   allSchedules: \Illuminate\Support\Collection,
     *   isContractExpired: bool,
     *   contractYearPeriods: list<array>,
     *   grandTotal: float,
     *   grandPaid: float,
     *   grandDebt: float,
     *   grandOverdue: float,
     *   grandPenya: float,
     *   grandPercent: float,
     *   grandOylikOrtacha: float,
     *   currentPeriodNum: int|null,
     *   currentPeriod: ?array,
     *   otherPeriods: list<array>,
     *   currentPeriodData: ?array
     * }
     */
    public function buildForContract(
        Contract $contract,
        Carbon $bugun,
        array $allDisplayScheduleRows,
        array $displayTotals
    ): array {
        $contractEnd = Carbon::parse($contract->tugash_sanasi);
        $isContractExpired = $contractEnd->lt($bugun);

        $allSchedules = $contract->paymentSchedules->sortBy('tolov_sanasi');

        $contractYearPeriods = [];
        if ($allSchedules->count() > 0) {
            $firstScheduleDate = Carbon::parse($allSchedules->first()->tolov_sanasi);
            $lastScheduleDate = Carbon::parse($allSchedules->last()->tolov_sanasi);
            $periodStart = Carbon::create($firstScheduleDate->year, $firstScheduleDate->month, 1);
            $periodNum = 1;

            while ($periodStart->lte($lastScheduleDate)) {
                $periodEnd = $periodStart->copy()->addMonths(12)->subDay();
                $periodSchedules = $allSchedules->filter(function ($s) use ($periodStart, $periodEnd) {
                    $scheduleDate = Carbon::parse($s->tolov_sanasi);

                    return $scheduleDate->gte($periodStart) && $scheduleDate->lte($periodEnd);
                })->sortBy('tolov_sanasi');

                if ($periodSchedules->count() > 0) {
                    $periodTotal = (float) $periodSchedules->sum('tolov_summasi');
                    $periodPaid = (float) $periodSchedules->sum('tolangan_summa');
                    $periodDebt = (float) $periodSchedules->sum('qoldiq_summa');

                    $actualPeriodEnd = Carbon::parse($periodSchedules->last()->tolov_sanasi)->endOfMonth();
                    $periodPenya = $this->scheduleDisplay->sumQoldiqPenyaInDateRange(
                        $allDisplayScheduleRows,
                        $periodStart,
                        $actualPeriodEnd
                    );
                    if ($periodPenya < 0) {
                        $periodPenya = 0.0;
                    }

                    $periodOverdue = (float) $periodSchedules->filter(function ($s) use ($bugun) {
                        if ((float) $s->qoldiq_summa <= 0) {
                            return false;
                        }
                        $paymentDate = Carbon::parse($s->tolov_sanasi);

                        return $paymentDate->lt($bugun);
                    })->sum('qoldiq_summa');

                    $periodPercent = $periodTotal > 0
                        ? round(($periodPaid / $periodTotal) * 100, 1) : 0.0;

                    $contractYearPeriods[] = [
                        'num' => $periodNum,
                        'start' => $periodStart->copy(),
                        'end' => $actualPeriodEnd,
                        'schedules' => $periodSchedules,
                        'months' => $periodSchedules->count(),
                        'total' => $periodTotal,
                        'paid' => $periodPaid,
                        'debt' => $periodDebt,
                        'overdue' => $periodOverdue,
                        'penya' => $periodPenya,
                        'percent' => $periodPercent,
                    ];
                    $periodNum++;
                }

                $periodStart = $periodStart->copy()->addMonths(12);
            }
        }

        $grandTotal = (float) $allSchedules->sum('tolov_summasi');
        $approvedPayments = $contract->payments->where('holat', 'tasdiqlangan');
        $refundPayments = $contract->payments->where('holat', 'qaytarilgan');
        $grandPaid = (float) $approvedPayments->sum('summa') - (float) abs($refundPayments->sum('summa'));
        $grandDebt = max(0, $grandTotal - $grandPaid);
        $grandOverdue = (float) $allSchedules->filter(function ($s) use ($bugun) {
            if ((float) $s->qoldiq_summa <= 0) {
                return false;
            }
            $paymentDate = Carbon::parse($s->tolov_sanasi);

            return $paymentDate->lt($bugun);
        })->sum('qoldiq_summa');

        $grandPenya = max(0.0, (float) ($displayTotals['jami_qoldiq_penya'] ?? 0));
        $grandPercent = $grandTotal > 0 ? round(($grandPaid / $grandTotal) * 100, 1) : 0.0;
        $nSched = $allSchedules->count();
        $grandOylikOrtacha = $nSched > 0 ? (float) round($grandTotal / $nSched) : 0.0;

        $currentPeriodNum = null;
        $currentPeriodData = null;
        foreach ($contractYearPeriods as $p) {
            if ($bugun->gte($p['start']) && $bugun->lte($p['end'])) {
                $currentPeriodNum = $p['num'];
                $currentPeriodData = $p;
                break;
            }
        }
        if (! $currentPeriodData && count($contractYearPeriods) > 0) {
            $currentPeriodData = $contractYearPeriods[0];
        }
        if (! $currentPeriodNum && $currentPeriodData) {
            $currentPeriodNum = (int) $currentPeriodData['num'];
        }

        $currentPeriod = null;
        $otherPeriods = [];
        foreach ($contractYearPeriods as $period) {
            if ($currentPeriodNum !== null && (int) $period['num'] === (int) $currentPeriodNum) {
                $currentPeriod = $period;
            } else {
                $otherPeriods[] = $period;
            }
        }
        if (! $currentPeriod && count($contractYearPeriods) > 0) {
            $currentPeriod = $contractYearPeriods[0];
            $otherPeriods = array_slice($contractYearPeriods, 1);
        }

        return [
            'allSchedules' => $allSchedules,
            'isContractExpired' => $isContractExpired,
            'contractYearPeriods' => $contractYearPeriods,
            'grandTotal' => $grandTotal,
            'grandPaid' => $grandPaid,
            'grandDebt' => $grandDebt,
            'grandOverdue' => $grandOverdue,
            'grandPenya' => $grandPenya,
            'grandPercent' => $grandPercent,
            'grandOylikOrtacha' => $grandOylikOrtacha,
            'currentPeriodNum' => $currentPeriodNum,
            'currentPeriod' => $currentPeriod,
            'otherPeriods' => $otherPeriods,
            'currentPeriodData' => $currentPeriodData,
        ];
    }
}
