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

            foreach ($payments as $payment) {
                $this->applyPaymentFIFO($payment, $contract);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Apply payment using FIFO method
     */
    private function applyPaymentFIFO(Payment $payment, Contract $contract): void
    {
        $qoldiqSumma = $payment->summa;
        $asosiyQarzUchun = 0;
        $penyaUchun = 0;
        $avans = 0;
        $tolovSanasi = Carbon::parse($payment->tolov_sanasi);

        // Get all schedules that have debt or are pending
        $schedules = $contract->paymentSchedules()
            ->whereIn('holat', ['tolanmagan', 'qisman_tolangan', 'kutilmoqda'])
            ->where('qoldiq_summa', '>', 0)
            ->orderBy('oy_raqami')
            ->get();

        foreach ($schedules as $schedule) {
            if ($qoldiqSumma <= 0) break;

            // Calculate penalty based on payment date (not today)
            $oxirgiMuddat = Carbon::parse($schedule->oxirgi_muddat);

            // Only charge penalty if deadline passed BEFORE payment date
            if ($tolovSanasi->gt($oxirgiMuddat)) {
                $kechikishKunlari = $oxirgiMuddat->diffInDays($tolovSanasi);
                $penya = $schedule->qoldiq_summa * 0.0004 * $kechikishKunlari;
                $maxPenya = $schedule->qoldiq_summa * 0.5;
                $schedule->penya_summasi = min($penya, $maxPenya);
                $schedule->kechikish_kunlari = $kechikishKunlari;
            }

            // Pay penalty first
            $qoldiqPenya = $schedule->penya_summasi - $schedule->tolangan_penya;
            if ($qoldiqPenya > 0 && $qoldiqSumma > 0) {
                $penyaTolov = min($qoldiqPenya, $qoldiqSumma);
                $schedule->tolangan_penya += $penyaTolov;
                $penyaUchun += $penyaTolov;
                $qoldiqSumma -= $penyaTolov;
            }

            // Then pay principal
            if ($schedule->qoldiq_summa > 0 && $qoldiqSumma > 0) {
                $asosiyTolov = min($schedule->qoldiq_summa, $qoldiqSumma);
                $schedule->tolangan_summa += $asosiyTolov;
                $schedule->qoldiq_summa -= $asosiyTolov;
                $asosiyQarzUchun += $asosiyTolov;
                $qoldiqSumma -= $asosiyTolov;
            }

            $schedule->updateStatus();
            $schedule->save();
        }

        // Remaining amount goes to avans
        if ($qoldiqSumma > 0) {
            $avans = $qoldiqSumma;
            $contract->avans_balans = ($contract->avans_balans ?? 0) + $avans;
            $contract->save();
        }

        // Update payment record
        $payment->asosiy_qarz_uchun = $asosiyQarzUchun;
        $payment->penya_uchun = $penyaUchun;
        $payment->avans = $avans;
        $payment->save();
    }
}
