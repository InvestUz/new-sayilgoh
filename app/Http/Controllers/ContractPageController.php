<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\ContractPeriodService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractPageController extends Controller
{
    public function index(): View
    {
        return view('contracts.index');
    }

    public function show(Contract $contract): View
    {
        $contract->load(['lot', 'tenant', 'paymentSchedules', 'payments']);

        // Penyalarni qayta hisoblash (to'lanmagan oylar uchun)
        foreach ($contract->paymentSchedules as $schedule) {
            if ($schedule->qoldiq_summa > 0) {
                $schedule->calculatePenya();
            }
        }

        // Reload to get updated penalty values
        $contract->load('paymentSchedules');

        // Use ContractPeriodService for period calculations
        $periodService = ContractPeriodService::forContract($contract);
        $contractYearPeriods = $periodService->getAllPeriods();
        $currentPeriodNum = $periodService->getCurrentPeriodNum();
        $grandTotals = $periodService->getGrandTotals();
        $isContractExpired = $periodService->isContractExpired();
        $currentMonthYear = $periodService->getCurrentMonthYear();
        $currentMonth = $currentMonthYear['month'];
        $currentYear = $currentMonthYear['year'];

        // Calculate statistics with dynamic penalty calculation
        $totalPenya = $contract->paymentSchedules->sum(function($schedule) {
            return $schedule->getPenaltyDetails()['calculated_penalty'];
        });

        $stats = [
            'jami_summa' => $contract->shartnoma_summasi,
            'tolangan' => $contract->paymentSchedules->sum('tolangan_summa'),
            'qoldiq' => $contract->paymentSchedules->sum('qoldiq_summa'),
            'penya' => $totalPenya,
        ];

        return view('contracts.show', compact(
            'contract',
            'stats',
            'contractYearPeriods',
            'currentPeriodNum',
            'grandTotals',
            'isContractExpired',
            'currentMonth',
            'currentYear'
        ));
    }

    public function create(): View
    {
        return view('contracts.form');
    }

    public function edit(Contract $contract): View
    {
        $contract->load(['lot', 'tenant']);
        return view('contracts.form', compact('contract'));
    }
}
