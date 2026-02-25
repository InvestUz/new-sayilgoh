<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use App\Services\ContractPeriodService;
use Illuminate\View\View;

class LotPageController extends Controller
{
    public function show(Lot $lot): View
    {
        $lot->load(['contracts.tenant', 'contracts.paymentSchedules']);

        $activeContract = $lot->contracts->where('holat', 'faol')->first();

        // Use ContractPeriodService for period calculations
        $periodService = null;
        $contractYearPeriods = [];
        $currentPeriodNum = null;
        $grandTotals = [];
        $isContractExpired = false;
        $currentMonth = null;
        $currentYear = null;

        if ($activeContract) {
            $periodService = ContractPeriodService::forContract($activeContract);
            $contractYearPeriods = $periodService->getAllPeriods();
            $currentPeriodNum = $periodService->getCurrentPeriodNum();
            $grandTotals = $periodService->getGrandTotals();
            $isContractExpired = $periodService->isContractExpired();
            $currentMonthYear = $periodService->getCurrentMonthYear();
            $currentMonth = $currentMonthYear['month'];
            $currentYear = $currentMonthYear['year'];
        }

        return view('lots.show', compact(
            'lot',
            'activeContract',
            'contractYearPeriods',
            'currentPeriodNum',
            'grandTotals',
            'isContractExpired',
            'currentMonth',
            'currentYear'
        ));
    }
}
