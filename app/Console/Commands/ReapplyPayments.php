<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Payment;
use App\Services\PaymentApplicator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tasdiqlangan to'lovlarni grafiklarga qaytadan qo'llash.
 *
 * Bu komanda quyidagi hollarda foydali:
 *   - Penya/qoldiq qiymatlari noto'g'ri yuklangan bo'lsa.
 *   - Eski to'lovlar tartibi o'zgartirilgan bo'lsa.
 *   - Migratsiya yoki ko'p sonli o'zgartirishdan keyin sanity check.
 *
 * QOIDA: penya tarixiy ma'lumot — `penya_summasi`, `kechikish_kunlari` va
 * `tolangan_penya` HECH QACHON nolga tushirilmaydi. Yakunda penya bugungi
 * sana bo'yicha monoton ravishda yangilanadi.
 */
class ReapplyPayments extends Command
{
    protected $signature = 'payments:reapply
                            {--contract= : Specific contract ID}
                            {--payment= : Specific payment ID (re-runs full contract)}
                            {--dry-run : Show summary without writing to DB}';

    protected $description = 'Tasdiqlangan to\'lovlarni FIFO tartibida qaytadan qo\'llash';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $contractId = $this->option('contract');
        $paymentId = $this->option('payment');

        if ($dryRun) {
            $this->warn('DRY RUN — DBga yozilmaydi');
            $this->newLine();
        }

        if ($paymentId) {
            return $this->reapplySinglePayment((int) $paymentId, $dryRun);
        }

        if ($contractId) {
            return $this->reapplyContractPayments((int) $contractId, $dryRun);
        }

        return $this->reapplyAllPayments($dryRun);
    }

    private function reapplySinglePayment(int $paymentId, bool $dryRun): int
    {
        $payment = Payment::with('contract.paymentSchedules')->find($paymentId);
        if (!$payment) {
            $this->error("Payment #{$paymentId} topilmadi");
            return self::FAILURE;
        }

        $this->info("Payment #{$paymentId} ({$payment->summa} so'm) — shartnoma {$payment->contract->shartnoma_raqami}");

        if (!$dryRun) {
            $this->resetAndReapplyContractPayments($payment->contract);
            $this->info('Bajarildi');
        }

        return self::SUCCESS;
    }

    private function reapplyContractPayments(int $contractId, bool $dryRun): int
    {
        $contract = Contract::with('paymentSchedules')->find($contractId);
        if (!$contract) {
            $this->error("Contract #{$contractId} topilmadi");
            return self::FAILURE;
        }

        $count = $contract->payments()->where('holat', 'tasdiqlangan')->count();
        $this->info("Shartnoma: {$contract->shartnoma_raqami}, qaytadan qo'llanadi: {$count} ta to'lov");

        if (!$dryRun) {
            $this->resetAndReapplyContractPayments($contract);
            $this->info('Bajarildi');
        }

        return self::SUCCESS;
    }

    private function reapplyAllPayments(bool $dryRun): int
    {
        $contracts = Contract::whereHas('payments', fn ($q) => $q->where('holat', 'tasdiqlangan'))
            ->with('paymentSchedules')
            ->get();

        $this->info("Topildi: {$contracts->count()} ta shartnoma");

        $bar = $this->output->createProgressBar($contracts->count());
        $bar->start();

        foreach ($contracts as $contract) {
            if (!$dryRun) {
                $this->resetAndReapplyContractPayments($contract);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Bajarildi');

        return self::SUCCESS;
    }

    private function resetAndReapplyContractPayments(Contract $contract): void
    {
        DB::beginTransaction();

        try {
            $contract->paymentSchedules()->update([
                'tolangan_summa' => 0,
                'qoldiq_summa'   => DB::raw('tolov_summasi'),
                'holat'          => 'kutilmoqda',
            ]);

            $contract->avans_balans = 0;
            $contract->save();

            $contract->load('paymentSchedules');

            $payments = $contract->payments()
                ->where('holat', 'tasdiqlangan')
                ->orderBy('tolov_sanasi')
                ->orderBy('id')
                ->get();

            $applicator = app(PaymentApplicator::class);
            foreach ($payments as $payment) {
                $payment->asosiy_qarz_uchun = 0;
                $payment->penya_uchun = 0;
                $payment->avans = 0;
                $payment->save();

                $applicator->apply($payment, $contract);
            }

            $today = Carbon::today();
            foreach ($contract->paymentSchedules()->get() as $schedule) {
                $schedule->calculatePenyaAtDate($today, true);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Xatolik: ' . $e->getMessage());
            throw $e;
        }
    }
}
