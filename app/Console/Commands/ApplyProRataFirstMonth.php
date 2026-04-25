<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\PaymentSchedule;
use App\Services\PaymentApplicator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mavjud shartnoma grafiklariga pro-rata birinchi oy formulasini qo'llash.
 *
 *  - Birinchi grafik (oy_raqami = 1): tolov_sanasi va oxirgi_muddat =
 *    boshlanish_sanasi, summa = oylik × (faol_kunlar / oy_kunlari).
 *  - Qolgan grafiklar: summa = (N × oylik − tejov) / (N − 1).
 *  - Hech qanday grafik o'chirilmaydi — faqat summalar va sanalar yangilanadi.
 *  - So'ngra `PaymentApplicator` yordamida barcha to'lovlar FIFO qaytadan
 *    taqsimlanadi va penya yangidan hisoblanadi.
 *
 * Foydalanish:
 *   php artisan schedules:apply-pro-rata             # barcha shartnomalar
 *   php artisan schedules:apply-pro-rata --id=1      # faqat 1-shartnoma
 *   php artisan schedules:apply-pro-rata --dry-run   # o'zgarishsiz oldindan ko'rish
 */
class ApplyProRataFirstMonth extends Command
{
    protected $signature = 'schedules:apply-pro-rata {--id= : Shartnoma ID} {--dry-run : O\'zgarishsiz preview}';

    protected $description = 'Mavjud shartnoma grafiklariga pro-rata birinchi oy formulasini qo\'llash';

    public function handle(PaymentApplicator $applicator): int
    {
        $query = Contract::query()->whereHas('paymentSchedules');
        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }
        $contracts = $query->get();

        if ($contracts->isEmpty()) {
            $this->warn('Mos keluvchi shartnoma topilmadi.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        foreach ($contracts as $contract) {
            $boshlanish = Carbon::parse($contract->boshlanish_sanasi);

            if ($boshlanish->day === 1) {
                $skipped++;
                $this->line("  #{$contract->id}: 1-chi kunidan boshlangan — pro-rata kerak emas");
                continue;
            }

            $annualRent = (float) ($contract->yillik_ijara_haqi ?? $contract->shartnoma_summasi);
            $monthlyFull = round($annualRent / 12, 2);
            $n = (int) $contract->shartnoma_muddati;

            if ($n < 2) {
                $skipped++;
                continue;
            }

            $daysInMonth = $boshlanish->daysInMonth;
            $activeDays = $daysInMonth - $boshlanish->day + 1;
            $firstAmount = round($monthlyFull * $activeDays / $daysInMonth, 2);
            $savings = round($monthlyFull - $firstAmount, 2);
            $remainingTotal = round($n * $monthlyFull - $savings, 2);
            $remainingMonthly = round($remainingTotal / ($n - 1), 2);

            $schedules = $contract->paymentSchedules()
                ->orderBy('oy_raqami')
                ->get();

            if ($schedules->count() < $n) {
                $this->warn("  #{$contract->id}: grafiklar soni ({$schedules->count()}) shartnoma muddatidan ({$n}) kam — o'tkazilmoqda");
                $skipped++;
                continue;
            }

            $this->info(sprintf(
                "  #%d (%s): boshlanish=%s, oy_kun=%d, faol=%d, birinchi=%s, qolgan_oylik=%s",
                $contract->id,
                $contract->shartnoma_raqami,
                $boshlanish->format('d.m.Y'),
                $daysInMonth,
                $activeDays,
                number_format($firstAmount, 2, ',', ' '),
                number_format($remainingMonthly, 2, ',', ' '),
            ));

            if ($dryRun) {
                $updated++;
                continue;
            }

            DB::transaction(function () use ($contract, $schedules, $firstAmount, $remainingMonthly, $boshlanish, $applicator) {
                foreach ($schedules as $idx => $sch) {
                    $oyRaqami = $idx + 1;
                    $newAmount = $oyRaqami === 1 ? $firstAmount : $remainingMonthly;
                    $tolanganSumma = (float) $sch->tolangan_summa;

                    $sch->tolov_summasi = $newAmount;
                    $sch->qoldiq_summa = max(0, $newAmount - $tolanganSumma);

                    if ($oyRaqami === 1) {
                        $sch->tolov_sanasi = $boshlanish->format('Y-m-d');
                        $sch->oxirgi_muddat = $boshlanish->format('Y-m-d');
                        $sch->custom_oxirgi_muddat = null;
                        $sch->yil = $boshlanish->year;
                        $sch->oy = $boshlanish->month;
                    }

                    // Penyani noldan qayta hisoblash uchun reset
                    $sch->penya_summasi = 0;
                    $sch->kechikish_kunlari = 0;
                    $sch->save();
                }

                // Asosiy qarz allokatsiyasini reset va FIFO qayta qo'llash
                $contract->paymentSchedules()->update([
                    'tolangan_summa' => 0,
                    'qoldiq_summa'   => DB::raw('tolov_summasi'),
                    'holat'          => 'kutilmoqda',
                ]);
                $contract->avans_balans = 0;
                $contract->save();

                $payments = $contract->payments()
                    ->where('holat', 'tasdiqlangan')
                    ->orderBy('tolov_sanasi')
                    ->orderBy('id')
                    ->get();

                foreach ($payments as $p) {
                    $p->asosiy_qarz_uchun = 0;
                    $p->penya_uchun = 0;
                    $p->avans = 0;
                    $p->save();
                    $applicator->apply($p, $contract);
                }

                // Penyani qayta hisoblash (paid bo'lsa to'lov sanasi bo'yicha)
                $today = Carbon::today();
                foreach ($contract->paymentSchedules()->get() as $schedule) {
                    if ((float) $schedule->qoldiq_summa > 0) {
                        $schedule->calculatePenyaAtDate($today, true);
                        continue;
                    }

                    $deadline = $schedule->custom_oxirgi_muddat
                        ? Carbon::parse($schedule->custom_oxirgi_muddat)
                        : Carbon::parse($schedule->oxirgi_muddat);

                    $lastPayment = $contract->payments
                        ->where('holat', 'tasdiqlangan')
                        ->filter(function ($p) use ($schedule) {
                            $d = Carbon::parse($p->tolov_sanasi);
                            return $d->month == $schedule->oy && $d->year == $schedule->yil;
                        })
                        ->sortByDesc('tolov_sanasi')
                        ->first();

                    if (!$lastPayment) {
                        continue;
                    }

                    $payDate = Carbon::parse($lastPayment->tolov_sanasi);
                    if ($payDate->lte($deadline)) {
                        continue;
                    }

                    $days = (int) $deadline->diffInDays($payDate);
                    $base = (float) $schedule->tolov_summasi;
                    $newPenya = min(
                        $base * PaymentSchedule::PENYA_RATE * $days,
                        $base * PaymentSchedule::MAX_PENYA_RATE
                    );

                    $schedule->penya_summasi = round($newPenya, 2);
                    $schedule->kechikish_kunlari = $days;
                    $schedule->save();
                }
            });

            $updated++;
        }

        $this->newLine();
        $this->info(sprintf(
            'Tugallandi: %d ta shartnoma %s, %d ta o\'tkazildi.',
            $updated,
            $dryRun ? 'preview qilindi' : 'yangilandi',
            $skipped
        ));

        return self::SUCCESS;
    }
}
