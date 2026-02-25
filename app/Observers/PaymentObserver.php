<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Contract;
use App\Models\PaymentSchedule;
use App\Services\PenaltyCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Payment Observer - Automatically applies payments to schedules
 *
 * Uses PenaltyCalculatorService for contract-compliant penalty calculation.
 *
 * Business Rules Applied:
 * 1. Penalty only if payment_date > due_date
 * 2. Formula: penalty = overdue_amount * 0.0004 * overdue_days
 * 3. Cap: penalty <= overdue_amount * 0.5
 * 4. Each month calculated independently
 * 5. Allocation order: penalty -> overdue rent -> current rent -> advance
 */
class PaymentObserver
{
    protected PenaltyCalculatorService $penaltyService;

    public function __construct(PenaltyCalculatorService $penaltyService)
    {
        $this->penaltyService = $penaltyService;
    }

    /**
     * Handle the Payment "created" event.
     * Automatically apply payment to schedules when created with 'tasdiqlangan' status
     */
    public function created(Payment $payment): void
    {
        if ($payment->holat === 'tasdiqlangan') {
            $this->applyPaymentToSchedules($payment);
        }
    }

    /**
     * Handle the Payment "updated" event.
     * Apply payment if status changed to 'tasdiqlangan'
     */
    public function updated(Payment $payment): void
    {
        // Check if status was changed to 'tasdiqlangan'
        if ($payment->isDirty('holat') && $payment->holat === 'tasdiqlangan') {
            $this->applyPaymentToSchedules($payment);
        }
    }

    /**
     * Apply payment to schedules using FIFO method with contract-compliant penalty calculation
     *
     * Contract Rules:
     * - Rule 1: Penalty only when payment_date > due_date
     * - Rule 2: penalty = overdue_amount * 0.0004 * overdue_days
     * - Rule 3: penalty <= overdue_amount * 0.5
     * - Rule 5: Each month calculated independently
     * - Rule 6: Allocation order: penalty -> rent -> advance
     * - Rule 8: If penalty = 0, skip penalty allocation
     * - Priority: Apply to payment month first, then FIFO for remainder
     */
    private function applyPaymentToSchedules(Payment $payment): void
    {
        // Skip if already applied
        if ($payment->asosiy_qarz_uchun > 0 || $payment->penya_uchun > 0 || $payment->avans > 0) {
            return;
        }

        $contract = $payment->contract;
        if (!$contract) {
            Log::warning("Payment #{$payment->id} has no associated contract");
            return;
        }

        $paymentDate = Carbon::parse($payment->tolov_sanasi);
        $remainingAmount = (float) $payment->summa;
        $totalPrincipalPaid = 0;
        $totalPenaltyPaid = 0;

        // STRICT RULE: Apply payment ONLY to schedule matching payment month/year
        // NO FIFO fallback - each payment stays with its intended month
        $targetSchedule = PaymentSchedule::where('contract_id', $contract->id)
            ->where('oy', $paymentDate->month)
            ->where('yil', $paymentDate->year)
            ->first();

        if ($targetSchedule && $remainingAmount > 0) {
            $result = $this->applyToSchedule($targetSchedule, $remainingAmount, $paymentDate);
            $totalPenaltyPaid += $result['penalty_paid'];
            $totalPrincipalPaid += $result['principal_paid'];
            $remainingAmount = $result['remaining'];
        }

        // Rule 6d: Remaining amount goes to advance balance
        $advanceAdded = 0;
        if ($remainingAmount > 0) {
            $advanceAdded = $remainingAmount;
            $contract->avans_balans = ($contract->avans_balans ?? 0) + $advanceAdded;
            $contract->save();
        }

        // Update payment record (without triggering observer again)
        Payment::withoutEvents(function () use ($payment, $totalPrincipalPaid, $totalPenaltyPaid, $advanceAdded) {
            $payment->update([
                'asosiy_qarz_uchun' => $totalPrincipalPaid,
                'penya_uchun' => $totalPenaltyPaid,
                'avans' => $advanceAdded,
            ]);
        });

        Log::info("Payment #{$payment->id} applied", [
            'payment_date' => $paymentDate->format('Y-m-d'),
            'total_amount' => $payment->summa,
            'principal' => $totalPrincipalPaid,
            'penalty' => $totalPenaltyPaid,
            'advance' => $advanceAdded,
        ]);
    }

    /**
     * Apply payment to a single schedule with contract-compliant penalty calculation
     */
    private function applyToSchedule(
        PaymentSchedule $schedule,
        float $amount,
        Carbon $paymentDate
    ): array {
        $result = [
            'penalty_paid' => 0,
            'principal_paid' => 0,
            'remaining' => $amount,
        ];

        $dueDate = Carbon::parse($schedule->oxirgi_muddat);

        // Rule 1: Calculate penalty ONLY if payment_date > due_date
        if ($paymentDate->gt($dueDate)) {
            // Rule 4: overdue_days = payment_date - due_date
            $overdueDays = $dueDate->diffInDays($paymentDate);

            // Rule 2: penalty = overdue_amount * 0.0004 * overdue_days
            $overdueAmount = (float) $schedule->qoldiq_summa;
            $calculatedPenalty = $overdueAmount * PaymentSchedule::PENYA_RATE * $overdueDays;

            // Rule 3: penalty <= overdue_amount * 0.5
            $maxPenalty = $overdueAmount * PaymentSchedule::MAX_PENYA_RATE;
            $calculatedPenalty = min($calculatedPenalty, $maxPenalty);

            // Update schedule with calculated penalty
            $schedule->penya_summasi = round($calculatedPenalty, 2);
            $schedule->kechikish_kunlari = $overdueDays;
        } else {
            // Rule 1: On-time or early payment - NO penalty
            $schedule->penya_summasi = 0;
            $schedule->kechikish_kunlari = 0;
        }

        // Rule 6a: Pay penalty first (Rule 8: only if penalty > 0)
        $unpaidPenalty = $schedule->penya_summasi - $schedule->tolangan_penya;
        if ($unpaidPenalty > 0 && $result['remaining'] > 0) {
            $penaltyPayment = min($unpaidPenalty, $result['remaining']);
            $schedule->tolangan_penya += $penaltyPayment;
            $result['penalty_paid'] = $penaltyPayment;
            $result['remaining'] -= $penaltyPayment;
        }

        // Rule 6b & 6c: Pay principal (rent)
        if ($schedule->qoldiq_summa > 0 && $result['remaining'] > 0) {
            $principalPayment = min($schedule->qoldiq_summa, $result['remaining']);
            $schedule->tolangan_summa += $principalPayment;
            $schedule->qoldiq_summa -= $principalPayment;
            $result['principal_paid'] = $principalPayment;
            $result['remaining'] -= $principalPayment;
        }

        // Update schedule status
        if ($schedule->qoldiq_summa <= 0) {
            $schedule->holat = 'tolangan';
        } elseif ($schedule->tolangan_summa > 0) {
            $schedule->holat = 'qisman_tolangan';
        }

        $schedule->save();

        return $result;
    }
}
