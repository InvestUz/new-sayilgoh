<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\PaymentSchedule;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        return view('analytics');
    }

    /**
     * Get analytics data API
     */
    public function getData(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        // Monthly expected vs actual payments
        $monthlyData = $this->getMonthlyComparison($year);

        // Collection rate
        $collectionRate = $this->getCollectionRate();

        // Top debtors
        $topDebtors = $this->getTopDebtors();

        // Monthly income
        $monthlyIncome = $this->getMonthlyIncome($year);

        return response()->json([
            'success' => true,
            'data' => [
                'monthly_comparison' => $monthlyData,
                'collection_rate' => $collectionRate,
                'top_debtors' => $topDebtors,
                'monthly_income' => $monthlyIncome,
            ]
        ]);
    }

    public function getMonthlyComparison(Request $request = null): JsonResponse
    {
        $year = $request ? $request->get('year', now()->year) : now()->year;
        $months = [];
        $monthNames = ['','Yan','Fev','Mar','Apr','May','Iyn','Iyl','Avg','Sen','Okt','Noy','Dek'];
        for ($m = 1; $m <= 12; $m++) {
            $schedules = PaymentSchedule::whereYear('tolov_sanasi', $year)
                ->whereMonth('tolov_sanasi', $m)
                ->whereHas('contract', fn($q) => $q->where('holat', 'faol'))
                ->get();

            $months[] = [
                'month' => $monthNames[$m],
                'expected' => $schedules->sum('tolov_summasi'),
                'collected' => $schedules->sum('tolangan_summa'),
            ];
        }
        return response()->json(['success' => true, 'data' => $months]);
    }

    public function getCollectionRate(): JsonResponse
    {
        $total = PaymentSchedule::whereHas('contract', fn($q) => $q->where('holat', 'faol'))
            ->sum('tolov_summasi');
        $collected = PaymentSchedule::whereHas('contract', fn($q) => $q->where('holat', 'faol'))
            ->sum('tolangan_summa');
        $debt = $total - $collected;

        return response()->json([
            'success' => true,
            'data' => [
                'total_expected' => $total,
                'total_collected' => $collected,
                'total_debt' => $debt,
                'collection_rate' => $total > 0 ? round(($collected / $total) * 100, 1) : 0,
            ]
        ]);
    }

    private function getTopDebtors(): array
    {
        return Contract::where('holat', 'faol')
            ->whereHas('paymentSchedules', fn($q) => $q->where('qoldiq_summa', '>', 0))
            ->with('tenant')
            ->withSum('paymentSchedules as total_debt', 'qoldiq_summa')
            ->withSum('paymentSchedules as total_penya', 'penya_summasi')
            ->orderByDesc('total_debt')
            ->limit(10)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'contract' => $c->shartnoma_raqami,
                'tenant' => $c->tenant->name ?? 'N/A',
                'debt' => $c->total_debt,
                'penya' => $c->total_penya,
            ])
            ->toArray();
    }

    private function getMonthlyIncome(int $year): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $payments = Payment::whereYear('tolov_sanasi', $year)
                ->whereMonth('tolov_sanasi', $m)
                ->where('holat', 'tasdiqlangan')
                ->get();

            $months[] = [
                'month' => $m,
                'income' => $payments->sum('summa'),
                'count' => $payments->count(),
            ];
        }
        return $months;
    }
}
