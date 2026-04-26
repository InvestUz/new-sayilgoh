<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Payment;
use Carbon\Carbon;

/**
 * PaymentApplicator - FAKT to'lovlarni jadvallarga qo'llashning YAGONA markazi.
 *
 * Siyosat (2026-04-24 dan boshlab):
 * ─────────────────────────────────────────────────────────────────────────
 *   1. To'lov TO'LIQ `tolangan_summa`ga (principal) yo'naltiriladi.
 *   2. Penya bu yerda AVTOMATIK yechilmaydi. Uning uchun `/api/penalty-payments`
 *      endpointi (yoki UI'dagi "Penya to'lash" tugmasi) ishlatiladi.
 *   3. `penya_summasi` informatsion ravishda (save=false) hisoblab qo'yiladi,
 *      toki ro'yxatlarda "hisoblangan penya" to'g'ri ko'rinib tursin.
 *   4. FIFO tartibi: eng kichik `oy_raqami` (eng eski) birinchi.
 *   5. Qolgan summa shartnomaning `avans_balans` maydoniga qo'shiladi.
 *
 * Bu klas `AppServiceProvider`da observer sifatida ro'yxatdan o'tkazilmaydi —
 * uni faqat aniq chaqiruvchi joylar (controller'lar va seeder) chaqirishlari
 * kerak. Shu bilan "observer + explicit call" ikki marta qo'llanish muammosi
 * butunlay yo'qoladi.
 */
class PaymentApplicator
{
    /**
     * @param  Payment $payment   — to'lov yozuvi (create()dan keyin)
     * @param  Contract|null $contract — tejashga eager-loaded shartnoma
     * @return array{asosiy_qarz_uchun: float, penya_uchun: float, avans: float, qoplangan_oylar: array<int,array>}
     */
    public function apply(Payment $payment, ?Contract $contract = null): array
    {
        $contract = $contract ?? $payment->contract()->with('paymentSchedules')->first();
        if (!$contract) {
            return [
                'asosiy_qarz_uchun' => 0.0,
                'penya_uchun' => 0.0,
                'avans' => (float) $payment->summa,
                'qoplangan_oylar' => [],
            ];
        }

        // Refund/qaytarilgan to'lovlarni bu yerda qo'llamaymiz — ular
        // kassada bo'lmagan bo'lib, schedule'larga ta'sir qilmaydi.
        if ($payment->holat === 'qaytarilgan' || ((float) $payment->summa) <= 0) {
            return [
                'asosiy_qarz_uchun' => 0.0,
                'penya_uchun' => 0.0,
                'avans' => 0.0,
                'qoplangan_oylar' => [],
            ];
        }

        // Bir to'lov qayta-qayta qo'llanib ketmasligi uchun guard:
        // agar `asosiy_qarz_uchun` yoki `avans` allaqachon belgilangan bo'lsa,
        // demak bu to'lov ilgari qo'llangan — chegirma qilmaymiz.
        if (((float) $payment->asosiy_qarz_uchun) > 0 || ((float) $payment->avans) > 0) {
            return [
                'asosiy_qarz_uchun' => (float) $payment->asosiy_qarz_uchun,
                'penya_uchun' => (float) $payment->penya_uchun,
                'avans' => (float) $payment->avans,
                'qoplangan_oylar' => [],
            ];
        }

        $qoldiqSumma = (float) $payment->summa;
        $tolovSanasi = Carbon::parse($payment->tolov_sanasi);
        $asosiyQarzUchun = 0.0;
        $qoplanganOylar = [];

        $schedules = $contract->paymentSchedules()
            ->where('qoldiq_summa', '>', 0)
            ->orderBy('oy_raqami')
            ->get();

        foreach ($schedules as $schedule) {
            if ($qoldiqSumma <= 0) {
                break;
            }

            $oldQoldiq = (float) $schedule->qoldiq_summa;
            $asosiyTolov = min($oldQoldiq, $qoldiqSumma);

            $schedule->tolangan_summa += $asosiyTolov;
            $schedule->qoldiq_summa -= $asosiyTolov;
            $asosiyQarzUchun += $asosiyTolov;
            $qoldiqSumma -= $asosiyTolov;

            $schedule->updateStatus();
            $schedule->save();

            // Penya faqat fakt tushgandan keyin (tolangan) hisoblanadi
            $schedule->calculatePenyaAtDate($tolovSanasi, false);
            $schedule->save();

            $qoplanganOylar[] = [
                'oy_raqami' => $schedule->oy_raqami,
                'davr' => $schedule->davr_nomi,
                'oldingi_qoldiq' => $oldQoldiq,
                'tolangan_asosiy' => $asosiyTolov,
                'yangi_qoldiq' => (float) $schedule->qoldiq_summa,
                'holat' => $schedule->holat_nomi,
                'hisoblangan_penya' => (float) $schedule->penya_summasi,
            ];
        }

        $avans = $qoldiqSumma;

        // To'lovning taqsimotini saqlash
        $payment->asosiy_qarz_uchun = $asosiyQarzUchun;
        $payment->penya_uchun = 0.0;
        $payment->avans = $avans;
        $payment->save();

        if ($avans > 0) {
            $contract->avans_balans = ((float) ($contract->avans_balans ?? 0)) + $avans;
            $contract->save();
        }

        return [
            'asosiy_qarz_uchun' => $asosiyQarzUchun,
            'penya_uchun' => 0.0,
            'avans' => $avans,
            'qoplangan_oylar' => $qoplanganOylar,
        ];
    }
}
