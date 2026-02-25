<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use App\Services\ContractPeriodService;
use App\Services\ScheduleDisplayService;
use Illuminate\View\View;

class LotPageController extends Controller
{
    public function show(Lot $lot): View
    {
        $lot->load(['contracts.tenant', 'contracts.paymentSchedules', 'contracts.payments']);

        $activeContract = $lot->contracts->where('holat', 'faol')->first();

        // Use ContractPeriodService for period calculations
        $periodService = null;
        $contractYearPeriods = [];
        $currentPeriodNum = null;
        $grandTotals = [];
        $isContractExpired = false;
        $currentMonth = null;
        $currentYear = null;
        $scheduleDisplayData = ['schedules' => [], 'is_contract_expired' => false, 'reference_date' => now()->format('Y-m-d')];

        if ($activeContract) {
            $periodService = ContractPeriodService::forContract($activeContract);
            $contractYearPeriods = $periodService->getAllPeriods();
            $currentPeriodNum = $periodService->getCurrentPeriodNum();
            $grandTotals = $periodService->getGrandTotals();
            $isContractExpired = $periodService->isContractExpired();
            $currentMonthYear = $periodService->getCurrentMonthYear();
            $currentMonth = $currentMonthYear['month'];
            $currentYear = $currentMonthYear['year'];

            // Get schedule display data from service (current period only)
            $scheduleService = new ScheduleDisplayService();
            $currentPeriod = $periodService->getCurrentPeriod();

            $periodDates = $currentPeriod ? [
                'start' => $currentPeriod['start'],
                'end' => $currentPeriod['end'],
            ] : null;

            $scheduleDisplayData = $scheduleService->getScheduleDisplayData($activeContract, $periodDates);
        }

        return view('lots.show', compact(
            'lot',
            'activeContract',
            'contractYearPeriods',
            'currentPeriodNum',
            'grandTotals',
            'isContractExpired',
            'currentMonth',
            'currentYear',
            'scheduleDisplayData'
        ));
    }
}
