<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PenaltyCalculatorService - Contract-compliant penalty (penya) calculation
 * 
 * Business Rules (from contract section 8.2):
 * 1. Penalty rate: 0.4% per day on overdue amount
 * 2. Penalty cap: 50% of overdue amount maximum
 * 3. Penalty is calculated ONLY when payment_date > due_date
 * 4. Each month's penalty is calculated independently
 * 5. No automatic carry-over of penalty balances
 */
class PenaltyCalculatorService
{
    // Daily penalty rate (0.4% = 0.004)
    public const PENALTY_RATE = 0.004;
    
    // Maximum penalty as percentage of overdue amount (50% = 0.5)
    public const MAX_PENALTY_RATE = 0.5;

    /**
     * Calculate penalty for a specific schedule at a given payment date
     * 
     * @param PaymentSchedule $schedule The payment schedule
     * @param Carbon $paymentDate The date when payment was made
     * @return array ['overdue_days' => int, 'penalty_rate' => float, 'calculated_penalty' => float]
     */
    public function calculatePenaltyForSchedule(PaymentSchedule $schedule, Carbon $paymentDate): array
    {
        $result = [
            'overdue_days' => 0,
            'penalty_rate' => self::PENALTY_RATE * 100, // 0.4%
            'calculated_penalty' => 0.0,
            'penalty_cap_applied' => false,
            'overdue_amount' => $schedule->qoldiq_summa,
        ];

        $dueDate = Carbon::parse($schedule->oxirgi_muddat);

        // Rule 1: If payment_date <= due_date → penalty = 0
        if ($paymentDate->lte($dueDate)) {
            return $result;
        }

        // Rule 4: Calculate overdue days = payment_date - due_date
        $overdueDays = $dueDate->diffInDays($paymentDate);
        $result['overdue_days'] = $overdueDays;

        // Rule 2: penalty = overdue_amount * 0.004 * overdue_days
        $overdueAmount = (float) $schedule->qoldiq_summa;
        $calculatedPenalty = $overdueAmount * self::PENALTY_RATE * $overdueDays;

        // Rule 3: Penalty cap - penalty <= overdue_amount * 0.5
        $maxPenalty = $overdueAmount * self::MAX_PENALTY_RATE;
        if ($calculatedPenalty > $maxPenalty) {
            $calculatedPenalty = $maxPenalty;
            $result['penalty_cap_applied'] = true;
        }

        $result['calculated_penalty'] = round($calculatedPenalty, 2);

        return $result;
    }

    /**
     * Apply payment to contract schedules following FIFO and allocation rules
     * 
     * Payment allocation order (Rule 6):
     * a) penalty (only if penalty > 0)
     * b) overdue rent
     * c) current rent
     * d) advance balance
     * 
     * @param Payment $payment The payment to apply
     * @return array Detailed allocation report
     */
    public function applyPayment(Payment $payment): array
    {
        $contract = $payment->contract;
        if (!$contract) {
            throw new \InvalidArgumentException("Payment has no associated contract");
        }

        $paymentDate = Carbon::parse($payment->tolov_sanasi);
        $remainingAmount = (float) $payment->summa;

        $allocation = [
            'payment_id' => $payment->id,
            'payment_date' => $paymentDate->format('Y-m-d'),
            'total_amount' => $payment->summa,
            'penalty_paid' => 0.0,
            'principal_paid' => 0.0,
            'advance_added' => 0.0,
            'schedules_affected' => [],
        ];

        // Get all schedules with debt, ordered by month number (FIFO)
        $schedules = PaymentSchedule::where('contract_id', $contract->id)
            ->whereIn('holat', ['tolanmagan', 'qisman_tolangan', 'kutilmoqda'])
            ->where('qoldiq_summa', '>', 0)
            ->orderBy('oy_raqami')
            ->get();

        foreach ($schedules as $schedule) {
            if ($remainingAmount <= 0) {
                break;
            }

            $scheduleAllocation = $this->applyPaymentToSchedule(
                $schedule,
                $remainingAmount,
                $paymentDate
            );

            $allocation['penalty_paid'] += $scheduleAllocation['penalty_paid'];
            $allocation['principal_paid'] += $scheduleAllocation['principal_paid'];
            $remainingAmount = $scheduleAllocation['remaining_amount'];

            $allocation['schedules_affected'][] = $scheduleAllocation;
        }

        // Rule 6d: Remaining amount goes to advance balance
        if ($remainingAmount > 0) {
            $allocation['advance_added'] = $remainingAmount;
            $contract->avans_balans = ($contract->avans_balans ?? 0) + $remainingAmount;
            $contract->save();
        }

        // Update payment record
        Payment::withoutEvents(function () use ($payment, $allocation) {
            $payment->update([
                'asosiy_qarz_uchun' => $allocation['principal_paid'],
                'penya_uchun' => $allocation['penalty_paid'],
                'avans' => $allocation['advance_added'],
            ]);
        });

        Log::info("Payment #{$payment->id} applied", [
            'principal' => $allocation['principal_paid'],
            'penalty' => $allocation['penalty_paid'],
            'advance' => $allocation['advance_added'],
        ]);

        return $allocation;
    }

    /**
     * Apply payment to a single schedule
     */
    private function applyPaymentToSchedule(
        PaymentSchedule $schedule,
        float $amount,
        Carbon $paymentDate
    ): array {
        $result = [
            'schedule_id' => $schedule->id,
            'month' => $schedule->oy_raqami,
            'year' => $schedule->yil,
            'due_date' => $schedule->oxirgi_muddat,
            'penalty_paid' => 0.0,
            'principal_paid' => 0.0,
            'remaining_amount' => $amount,
            'penalty_details' => null,
        ];

        // Calculate penalty at payment date
        $penaltyDetails = $this->calculatePenaltyForSchedule($schedule, $paymentDate);
        $result['penalty_details'] = $penaltyDetails;

        // Update schedule with calculated penalty
        $schedule->kechikish_kunlari = $penaltyDetails['overdue_days'];
        $schedule->penya_summasi = $penaltyDetails['calculated_penalty'];

        // Rule 6a: Pay penalty first (only if penalty > 0)
        $unpaidPenalty = $schedule->penya_summasi - $schedule->tolangan_penya;
        if ($unpaidPenalty > 0 && $result['remaining_amount'] > 0) {
            $penaltyPayment = min($unpaidPenalty, $result['remaining_amount']);
            $schedule->tolangan_penya += $penaltyPayment;
            $result['penalty_paid'] = $penaltyPayment;
            $result['remaining_amount'] -= $penaltyPayment;
        }

        // Rule 6b & 6c: Pay rent (overdue and current)
        if ($schedule->qoldiq_summa > 0 && $result['remaining_amount'] > 0) {
            $principalPayment = min($schedule->qoldiq_summa, $result['remaining_amount']);
            $schedule->tolangan_summa += $principalPayment;
            $schedule->qoldiq_summa -= $principalPayment;
            $result['principal_paid'] = $principalPayment;
            $result['remaining_amount'] -= $principalPayment;
        }

        // Update schedule status
        $this->updateScheduleStatus($schedule);
        $schedule->save();

        return $result;
    }

    /**
     * Update schedule status based on payment state
     */
    private function updateScheduleStatus(PaymentSchedule $schedule): void
    {
        if ($schedule->qoldiq_summa <= 0) {
            $schedule->holat = 'tolangan';
        } elseif ($schedule->tolangan_summa > 0) {
            $schedule->holat = 'qisman_tolangan';
        } elseif (Carbon::parse($schedule->tolov_sanasi)->isPast()) {
            $schedule->holat = 'tolanmagan';
        } else {
            $schedule->holat = 'kutilmoqda';
        }
    }

    /**
     * Recalculate penalties for all schedules of a contract
     * Returns an audit report
     * 
     * @param Contract $contract
     * @param Carbon|null $asOfDate Calculate as of this date (defaults to today)
     * @return array Audit report
     */
    public function recalculateContractPenalties(Contract $contract, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::today();

        $report = [
            'contract_id' => $contract->id,
            'contract_number' => $contract->shartnoma_raqami,
            'recalculated_at' => now()->format('Y-m-d H:i:s'),
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'schedules' => [],
            'totals' => [
                'previous_penalty' => 0.0,
                'recalculated_penalty' => 0.0,
                'difference' => 0.0,
                'invalid_penalties_removed' => 0,
            ],
        ];

        $schedules = $contract->paymentSchedules()->get();

        foreach ($schedules as $schedule) {
            $previousPenalty = (float) $schedule->penya_summasi;
            $report['totals']['previous_penalty'] += $previousPenalty;

            // For paid schedules, recalculate based on actual payment date
            if ($schedule->holat === 'tolangan') {
                // Find the last payment that affected this schedule
                $lastPayment = Payment::where('contract_id', $contract->id)
                    ->where('holat', 'tasdiqlangan')
                    ->where('tolov_sanasi', '>=', $schedule->tolov_sanasi)
                    ->orderBy('tolov_sanasi')
                    ->first();

                $paymentDate = $lastPayment 
                    ? Carbon::parse($lastPayment->tolov_sanasi) 
                    : $asOfDate;
            } else {
                $paymentDate = $asOfDate;
            }

            // Recalculate penalty
            $penaltyDetails = $this->calculatePenaltyForSchedule($schedule, $paymentDate);

            $scheduleReport = [
                'schedule_id' => $schedule->id,
                'month' => $schedule->oy_raqami,
                'year' => $schedule->yil,
                'month_name' => $schedule->davr_nomi,
                'due_date' => $schedule->oxirgi_muddat,
                'status' => $schedule->holat,
                'overdue_amount' => $schedule->qoldiq_summa,
                'previous_penalty' => $previousPenalty,
                'previous_overdue_days' => $schedule->kechikish_kunlari,
                'recalculated_penalty' => $penaltyDetails['calculated_penalty'],
                'recalculated_overdue_days' => $penaltyDetails['overdue_days'],
                'penalty_rate' => $penaltyDetails['penalty_rate'],
                'penalty_cap_applied' => $penaltyDetails['penalty_cap_applied'],
                'difference' => $penaltyDetails['calculated_penalty'] - $previousPenalty,
                'invalid_penalty' => false,
            ];

            // Check if previous penalty was invalid (penalty without overdue days)
            if ($previousPenalty > 0 && $penaltyDetails['overdue_days'] === 0) {
                $scheduleReport['invalid_penalty'] = true;
                $report['totals']['invalid_penalties_removed']++;
            }

            $report['totals']['recalculated_penalty'] += $penaltyDetails['calculated_penalty'];
            $report['schedules'][] = $scheduleReport;

            // Update schedule with recalculated values
            $schedule->penya_summasi = $penaltyDetails['calculated_penalty'];
            $schedule->kechikish_kunlari = $penaltyDetails['overdue_days'];
            $schedule->save();
        }

        $report['totals']['difference'] = $report['totals']['recalculated_penalty'] - 
                                          $report['totals']['previous_penalty'];

        return $report;
    }

    /**
     * Recalculate penalties for multiple contracts within a date range
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array Batch audit report
     */
    public function recalculateContractsInRange(Carbon $startDate, Carbon $endDate): array
    {
        $batchReport = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'executed_at' => now()->format('Y-m-d H:i:s'),
            'contracts_processed' => 0,
            'total_previous_penalty' => 0.0,
            'total_recalculated_penalty' => 0.0,
            'total_difference' => 0.0,
            'total_invalid_removed' => 0,
            'contract_reports' => [],
        ];

        // Get contracts that were active during the date range
        $contracts = Contract::where('holat', 'faol')
            ->where('boshlanish_sanasi', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->where('tugash_sanasi', '>=', $startDate)
                  ->orWhereNull('tugash_sanasi');
            })
            ->get();

        foreach ($contracts as $contract) {
            $contractReport = $this->recalculateContractPenalties($contract, $endDate);

            $batchReport['contracts_processed']++;
            $batchReport['total_previous_penalty'] += $contractReport['totals']['previous_penalty'];
            $batchReport['total_recalculated_penalty'] += $contractReport['totals']['recalculated_penalty'];
            $batchReport['total_difference'] += $contractReport['totals']['difference'];
            $batchReport['total_invalid_removed'] += $contractReport['totals']['invalid_penalties_removed'];

            $batchReport['contract_reports'][] = [
                'contract_id' => $contract->id,
                'contract_number' => $contract->shartnoma_raqami,
                'previous_penalty' => $contractReport['totals']['previous_penalty'],
                'recalculated_penalty' => $contractReport['totals']['recalculated_penalty'],
                'difference' => $contractReport['totals']['difference'],
                'invalid_removed' => $contractReport['totals']['invalid_penalties_removed'],
            ];
        }

        return $batchReport;
    }

    /**
     * Get penalty calculation details for display (monthly details table)
     * Rule 7: Monthly details MUST always show overdue_days, penalty_rate, calculated_penalty
     * 
     * @param PaymentSchedule $schedule
     * @param Carbon|null $asOfDate
     * @return array
     */
    public function getMonthlyDetails(PaymentSchedule $schedule, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::today();
        $penaltyDetails = $this->calculatePenaltyForSchedule($schedule, $asOfDate);

        return [
            'schedule_id' => $schedule->id,
            'contract_id' => $schedule->contract_id,
            'month_number' => $schedule->oy_raqami,
            'year' => $schedule->yil,
            'month' => $schedule->oy,
            'month_name' => $schedule->davr_nomi,
            'payment_due_date' => $schedule->tolov_sanasi,
            'penalty_deadline' => $schedule->oxirgi_muddat,
            'amount_due' => $schedule->tolov_summasi,
            'amount_paid' => $schedule->tolangan_summa,
            'amount_remaining' => $schedule->qoldiq_summa,
            'status' => $schedule->holat,
            'status_name' => $schedule->holat_nomi,
            // Rule 7 required fields - NEVER NULL
            'overdue_days' => $penaltyDetails['overdue_days'], // integer, 0 allowed
            'penalty_rate' => $penaltyDetails['penalty_rate'], // always 0.4%
            'calculated_penalty' => $penaltyDetails['calculated_penalty'], // numeric, 0 allowed
            'penalty_paid' => $schedule->tolangan_penya,
            'penalty_remaining' => max(0, $penaltyDetails['calculated_penalty'] - $schedule->tolangan_penya),
            'penalty_cap_applied' => $penaltyDetails['penalty_cap_applied'],
        ];
    }

    /**
     * Validate that a payment allocation is contract-compliant
     * 
     * @param Payment $payment
     * @return array Validation result with any issues found
     */
    public function validatePaymentAllocation(Payment $payment): array
    {
        $issues = [];
        $paymentDate = Carbon::parse($payment->tolov_sanasi);

        // Get the schedule this payment was allocated to
        $schedule = $payment->paymentSchedule;
        if (!$schedule) {
            return ['valid' => true, 'issues' => []];
        }

        $dueDate = Carbon::parse($schedule->oxirgi_muddat);

        // Issue: Penalty charged but payment was not late
        if ($payment->penya_uchun > 0 && $paymentDate->lte($dueDate)) {
            $issues[] = [
                'type' => 'invalid_penalty',
                'message' => "Penalty of {$payment->penya_uchun} charged but payment was on time",
                'payment_date' => $paymentDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
            ];
        }

        // Issue: Penalty calculation doesn't match formula
        if ($paymentDate->gt($dueDate)) {
            $expectedPenalty = $this->calculatePenaltyForSchedule($schedule, $paymentDate);
            $tolerance = 0.01; // Allow 1 cent tolerance for rounding
            
            if (abs($payment->penya_uchun - $expectedPenalty['calculated_penalty']) > $tolerance) {
                $issues[] = [
                    'type' => 'penalty_mismatch',
                    'message' => "Penalty amount doesn't match calculation",
                    'charged' => $payment->penya_uchun,
                    'expected' => $expectedPenalty['calculated_penalty'],
                    'overdue_days' => $expectedPenalty['overdue_days'],
                ];
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }
}
