<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Contract;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Payment Observer - automatically applies payments to schedules
 */
class PaymentObserver
{
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
     * Apply payment to schedules using FIFO method
     */
    private function applyPaymentToSchedules(Payment $payment): void
    {
        // Skip if already applied (asosiy_qarz_uchun > 0 or penya_uchun > 0)
        if ($payment->asosiy_qarz_uchun > 0 || $payment->penya_uchun > 0 || $payment->avans > 0) {
            return;
        }

        $contract = $payment->contract;
        if (!$contract) {
            return;
        }

        $qoldiqSumma = $payment->summa;
        $asosiyQarzUchun = 0;
        $penyaUchun = 0;
        $avans = 0;
        $tolovSanasi = Carbon::parse($payment->tolov_sanasi);

        // Get all schedules with debt, ordered by month
        $schedules = PaymentSchedule::where('contract_id', $contract->id)
            ->whereIn('holat', ['tolanmagan', 'qisman_tolangan', 'kutilmoqda'])
            ->where('qoldiq_summa', '>', 0)
            ->orderBy('oy_raqami')
            ->get();

        foreach ($schedules as $schedule) {
            if ($qoldiqSumma <= 0) break;

            // Calculate penalty based on payment date
            $oxirgiMuddat = Carbon::parse($schedule->oxirgi_muddat);

            // Only charge penalty if deadline passed BEFORE payment date
            if ($tolovSanasi->gt($oxirgiMuddat)) {
                $kechikishKunlari = $oxirgiMuddat->diffInDays($tolovSanasi);
                $penya = $schedule->qoldiq_summa * 0.004 * $kechikishKunlari;
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

            // Update status
            if ($schedule->qoldiq_summa <= 0) {
                $schedule->holat = 'tolangan';
            } elseif ($schedule->tolangan_summa > 0) {
                $schedule->holat = 'qisman_tolangan';
            }

            $schedule->save();
        }

        // Remaining amount goes to avans
        if ($qoldiqSumma > 0) {
            $avans = $qoldiqSumma;
            $contract->avans_balans = ($contract->avans_balans ?? 0) + $avans;
            $contract->save();
        }

        // Update payment record (without triggering observer again)
        Payment::withoutEvents(function () use ($payment, $asosiyQarzUchun, $penyaUchun, $avans) {
            $payment->update([
                'asosiy_qarz_uchun' => $asosiyQarzUchun,
                'penya_uchun' => $penyaUchun,
                'avans' => $avans,
            ]);
        });

        Log::info("Payment #{$payment->id} applied: Principal={$asosiyQarzUchun}, Penalty={$penyaUchun}, Advance={$avans}");
    }
}
