<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Recalculate penalties for all active contracts using the persistence-safe
 * `PaymentSchedule::calculatePenyaAtDate` method.
 *
 * QOIDALAR (Shartnoma 8.2-bandi):
 *   1) Penya faqat muddati o'tgan grafiklar uchun yig'iladi.
 *   2) Formulа: penya = qoldiq * 0.004 * kechikish_kunlari, max = qoldiq * 0.5.
 *   3) `penya_summasi` MONOTON: yangi qiymat eskidan past bo'lsa, eski saqlanadi
 *      — penya hech qachon yo'qolmaydi.
 *   4) To'liq to'langan grafiklar (qoldiq <= 0) qayta hisoblanmaydi —
 *      avval saqlangan "muzlatilgan" qiymat saqlanib qoladi.
 */
class RecalculatePenalties extends Command
{
    protected $signature = 'penalties:recalculate
                            {--contract= : Specific contract ID to recalculate}
                            {--dry-run : Preview changes without saving}';

    protected $description = 'Aktiv shartnomalar uchun penyalarni qayta hisoblash (monoton, yo\'qolmas)';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();

        $this->info('═══════════════════════════════════════════');
        $this->info('  PENYA QAYTA HISOBLASH');
        $this->info('═══════════════════════════════════════════');
        $this->line('  Sana: ' . $today->format('d.m.Y'));
        if ($isDryRun) {
            $this->warn('  DRY RUN — DBga yozilmaydi');
        }
        $this->newLine();

        $contractsQuery = Contract::where('holat', 'faol');
        if ($id = $this->option('contract')) {
            $contractsQuery->where('id', $id);
        }
        $contracts = $contractsQuery->get();

        $this->info("Topildi: {$contracts->count()} ta shartnoma");
        $this->newLine();

        $stats = [
            'contracts' => 0,
            'schedules' => 0,
            'updated' => 0,
            'previous_total' => 0.0,
            'new_total' => 0.0,
        ];

        $bar = $this->output->createProgressBar($contracts->count());
        $bar->start();

        foreach ($contracts as $contract) {
            $stats['contracts']++;

            foreach ($contract->paymentSchedules as $schedule) {
                $stats['schedules']++;
                $previous = (float) $schedule->penya_summasi;
                $stats['previous_total'] += $previous;

                if ((float) $schedule->qoldiq_summa > 0) {
                    if ($isDryRun) {
                        $clone = clone $schedule;
                        $newPenya = $clone->calculatePenyaAtDate($today, false);
                    } else {
                        $newPenya = $schedule->calculatePenyaAtDate($today, true);
                    }

                    if (abs($newPenya - $previous) > 0.01) {
                        $stats['updated']++;
                    }
                    $stats['new_total'] += $newPenya;
                } else {
                    $stats['new_total'] += $previous;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Ko\'rsatkich', 'Qiymat'],
            [
                ['Shartnomalar', $stats['contracts']],
                ['Grafiklar', $stats['schedules']],
                ['Yangilangan grafiklar', $stats['updated']],
                ['Jami avvalgi penya', number_format($stats['previous_total'], 2) . ' UZS'],
                ['Jami yangi penya', number_format($stats['new_total'], 2) . ' UZS'],
                ['Farq', number_format($stats['new_total'] - $stats['previous_total'], 2) . ' UZS'],
            ]
        );

        Log::info('Penalty recalculation completed', $stats + ['dry_run' => $isDryRun]);

        return self::SUCCESS;
    }
}
