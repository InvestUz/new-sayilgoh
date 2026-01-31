<?php

namespace App\Http\Controllers;

use App\Models\PaymentSchedule;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class CalendarController extends Controller
{
    public function index(): View
    {
        return view('calendar');
    }

    /**
     * Get calendar data API
     */
    public function getPayments(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        // Get all upcoming payment schedules
        $schedules = PaymentSchedule::with(['contract.tenant', 'contract.lot'])
            ->whereHas('contract', fn($q) => $q->where('holat', 'faol'))
            ->whereYear('tolov_sanasi', $year)
            ->whereMonth('tolov_sanasi', $month)
            ->where('qoldiq_summa', '>', 0)
            ->orderBy('tolov_sanasi')
            ->get();

        // Group by date
        $calendarData = [];
        foreach ($schedules as $schedule) {
            $date = $schedule->tolov_sanasi->format('Y-m-d');
            if (!isset($calendarData[$date])) {
                $calendarData[$date] = [];
            }

            $calendarData[$date][] = [
                'contract_id' => $schedule->contract_id,
                'contract' => $schedule->contract->shartnoma_raqami,
                'tenant' => $schedule->contract->tenant->name ?? 'N/A',
                'lot' => $schedule->contract->lot->lot_raqami ?? 'N/A',
                'amount' => $schedule->qoldiq_summa,
                'penya' => $schedule->penya_summasi,
                'month' => $schedule->oy_raqami,
                'status' => $this->getStatus($schedule),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $calendarData,
            'year' => $year,
            'month' => $month,
        ]);
    }

    private function getStatus($schedule): string
    {
        $dueDate = Carbon::parse($schedule->tolov_sanasi);
        $today = now()->startOfDay();

        if ($schedule->qoldiq_summa <= 0) {
            return 'paid';
        } elseif ($dueDate->lt($today)) {
            return 'overdue';
        } elseif ($dueDate->eq($today)) {
            return 'today';
        } else {
            return 'upcoming';
        }
    }
}
