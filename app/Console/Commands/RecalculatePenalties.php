<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Barcha grafiklar (qoldiqli va yopilgan) bo'yicha penya: formula + to'lov sanasi
 * (`bypassMuzlati` — DBdagi noto'g'ri yuqori penya qolishini oldini oladi).
 */
class RecalculatePenalties extends Command
{
    protected $signature = 'penalties:recalculate
                            {--contract= : Shartnoma ID (bitta shartnoma; holat filtri yo\'q)}
                            {--dry-run : DBga yozmasdan}';

    protected $description = 'Aktiv shartnomalar uchun penyalarni qayta hisoblash (barcha grafiklar)';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();
        $contractId = $this->option('contract');

        $this->info('═══════════════════════════════════════════');
        $this->info('  PENYA QAYTA HISOBLASH');
        $this->info('═══════════════════════════════════════════');
        $this->line('  Sana: ' . $today->format('d.m.Y'));
        if ($isDryRun) {
            $this->warn('  DRY RUN — DBga yozilmaydi');
        }
        $this->newLine();

        $query = Contract::query()
            ->with(['paymentSchedules', 'payments']);

        if ($contractId) {
            $query->where('id', (int) $contractId);
        } else {
            $query->where('holat', 'faol');
        }

        $contracts = $query->get();

        if ($contracts->isEmpty()) {
            $this->error($contractId ? "Shartnoma topilmadi: {$contractId}" : 'Faol shartnoma yo\'q.');

            return self::FAILURE;
        }

        $this->info('Topildi: ' . $contracts->count() . ' ta shartnoma');
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

                $schedule->setRelation('contract', $contract);

                if ($isDryRun) {
                    $newPenya = (clone $schedule)->calculatePenyaAtDate($today, false, true);
                } else {
                    $newPenya = $schedule->calculatePenyaAtDate($today, true, true);
                }

                if (abs($newPenya - $previous) > 0.01) {
                    $stats['updated']++;
                }
                $stats['new_total'] += $newPenya;
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
                ['O‘zgargan (taxminan) grafiklar', $stats['updated']],
                ['Jami avvalgi penya (DB)', number_format($stats['previous_total'], 2) . ' UZS'],
                ['Jami hisoblangan yangi', number_format($stats['new_total'], 2) . ' UZS'],
                ['Farq', number_format($stats['new_total'] - $stats['previous_total'], 2) . ' UZS'],
            ]
        );

        Log::info('Penalty recalculation completed', $stats + ['dry_run' => $isDryRun, 'contract_id' => $contractId]);

        return self::SUCCESS;
    }
}
