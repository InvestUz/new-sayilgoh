<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use App\Models\Contract;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReapplyPayments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payments:reapply
                            {--contract= : Specific contract ID to reapply}
                            {--payment= : Specific payment ID to reapply}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Re-apply payments to payment schedules using FIFO method';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $contractId = $this->option('contract');
        $paymentId = $this->option('payment');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        if ($paymentId) {
            // Reapply single payment
            return $this->reapplySinglePayment($paymentId, $dryRun);
        }

        if ($contractId) {
            // Reapply all payments for a specific contract
            return $this->reapplyContractPayments($contractId, $dryRun);
        }

        // Reapply all payments
        return $this->reapplyAllPayments($dryRun);
    }

    /**
     * Reapply a single payment
     */
    private function reapplySinglePayment(int $paymentId, bool $dryRun): int
    {
        $payment = Payment::with(['contract.paymentSchedules'])->find($paymentId);

        if (!$payment) {
            $this->error("Payment #{$paymentId} not found");
            return 1;
        }

        $this->info("Reapplying Payment #{$paymentId}");
        $this->info("  Amount: " . number_format($payment->summa, 2));
        $this->info("  Date: " . $payment->tolov_sanasi->format('d.m.Y'));
        $this->info("  Contract: " . $payment->contract->shartnoma_raqami);

        if (!$dryRun) {
            $this->resetAndReapplyContractPayments($payment->contract);
            $this->info("Payment reapplied successfully!");
        }

        return 0;
    }

    /**
     * Reapply all payments for a contract
     */
    private function reapplyContractPayments(int $contractId, bool $dryRun): int
    {
        $contract = Contract::with(['paymentSchedules', 'payments' => function ($q) {
            $q->where('holat', 'tasdiqlangan')->orderBy('tolov_sanasi');
        }])->find($contractId);

        if (!$contract) {
            $this->error("Contract #{$contractId} not found");
            return 1;
        }

        $this->info("Contract: " . $contract->shartnoma_raqami);
        $this->info("Payments to reapply: " . $contract->payments->count());

        if (!$dryRun) {
            $this->resetAndReapplyContractPayments($contract);
            $this->info("All payments reapplied successfully!");
        }

        return 0;
    }

    /**
     * Reapply all payments in the system
     */
    private function reapplyAllPayments(bool $dryRun): int
    {
        $contracts = Contract::whereHas('payments', function ($q) {
            $q->where('holat', 'tasdiqlangan');
        })->with(['paymentSchedules', 'payments' => function ($q) {
            $q->where('holat', 'tasdiqlangan')->orderBy('tolov_sanasi');
        }])->get();

        $this->info("Found {$contracts->count()} contracts with payments");

        $bar = $this->output->createProgressBar($contracts->count());

        foreach ($contracts as $contract) {
            if (!$dryRun) {
                $this->resetAndReapplyContractPayments($contract);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done! All payments reapplied.");

        return 0;
    }

    /**
     * Reset schedule paid amounts and reapply all payments
     */
    private function resetAndReapplyContractPayments(Contract $contract): void
    {
        DB::beginTransaction();

        try {
            // Reset all schedules
            foreach ($contract->paymentSchedules as $schedule) {
                $schedule->tolangan_summa = 0;
                $schedule->qoldiq_summa = $schedule->tolov_summasi;
                $schedule->tolangan_penya = 0;
                $schedule->updateStatus();
                $schedule->save();
            }

            // Reset avans
            $contract->avans_balans = 0;
            $contract->save();

            // Reload schedules
            $contract->load('paymentSchedules');

            // Reapply all payments in order
            $payments = $contract->payments()
                ->where('holat', 'tasdiqlangan')
                ->orderBy('tolov_sanasi')
                ->get();

            // Canonical applier — hozirgi siyosat: principal-only, penya
            // avtomatik yechilmaydi.
            $applicator = app(\App\Services\PaymentApplicator::class);
            foreach ($payments as $payment) {
                // reapply uchun oldingi taqsimotni nolga tushiramiz
                $payment->asosiy_qarz_uchun = 0;
                $payment->penya_uchun = 0;
                $payment->avans = 0;
                $payment->save();

                $applicator->apply($payment, $contract);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: " . $e->getMessage());
            throw $e;
        }
    }

}
