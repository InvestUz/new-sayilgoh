<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Services\PenaltyCalculatorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recalculate penalties for all contracts based on contract rules
 *
 * Business Rules Applied:
 * 1. Penalty only if payment_date > due_date
 * 2. Formula: penalty = overdue_amount * 0.004 * overdue_days
 * 3. Cap: penalty <= overdue_amount * 0.5
 * 4. Each month calculated independently
 * 5. Remove any penalties not supported by overdue_days
 */
class RecalculatePenalties extends Command
{
    protected $signature = 'penalties:recalculate
                            {--start= : Start date (Y-m-d) for recalculation range}
                            {--end= : End date (Y-m-d) for recalculation range}
                            {--contract= : Specific contract ID to recalculate}
                            {--dry-run : Preview changes without saving}
                            {--verbose : Show detailed output}';

    protected $description = 'Recalculate penalties for all contracts following contract rules (Section 8.2)';

    protected PenaltyCalculatorService $penaltyService;
    protected array $auditReport = [];

    public function __construct(PenaltyCalculatorService $penaltyService)
    {
        parent::__construct();
        $this->penaltyService = $penaltyService;
    }

    public function handle(): int
    {
        $this->info('===========================================');
        $this->info('PENALTY RECALCULATION - Contract Compliant');
        $this->info('===========================================');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $isVerbose = $this->option('verbose');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        // Parse date range
        $startDate = $this->option('start')
            ? Carbon::parse($this->option('start'))
            : Carbon::now()->subYears(5);
        $endDate = $this->option('end')
            ? Carbon::parse($this->option('end'))
            : Carbon::today();

        $this->info("Date Range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        $this->newLine();

        // Get contracts to process
        $contractsQuery = Contract::where('holat', 'faol');

        if ($this->option('contract')) {
            $contractsQuery->where('id', $this->option('contract'));
        }

        $contracts = $contractsQuery->get();
        $this->info("Found {$contracts->count()} contracts to process");
        $this->newLine();

        // Initialize audit report
        $this->auditReport = [
            'executed_at' => now()->format('Y-m-d H:i:s'),
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'dry_run' => $isDryRun,
            'summary' => [
                'contracts_processed' => 0,
                'schedules_processed' => 0,
                'invalid_penalties_found' => 0,
                'penalties_corrected' => 0,
                'total_previous_penalty' => 0,
                'total_recalculated_penalty' => 0,
                'total_difference' => 0,
            ],
            'contracts' => [],
        ];

        $progressBar = $this->output->createProgressBar($contracts->count());
        $progressBar->start();

        foreach ($contracts as $contract) {
            $contractReport = $this->recalculateContract($contract, $endDate, $isDryRun);
            $this->auditReport['contracts'][] = $contractReport;

            // Update summary
            $this->auditReport['summary']['contracts_processed']++;
            $this->auditReport['summary']['schedules_processed'] += $contractReport['schedules_count'];
            $this->auditReport['summary']['invalid_penalties_found'] += $contractReport['invalid_penalties'];
            $this->auditReport['summary']['penalties_corrected'] += $contractReport['corrected'];
            $this->auditReport['summary']['total_previous_penalty'] += $contractReport['previous_total'];
            $this->auditReport['summary']['total_recalculated_penalty'] += $contractReport['new_total'];

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->displaySummary();

        // Display detailed contract reports if verbose
        if ($isVerbose) {
            $this->displayDetailedReport();
        }

        // Save audit report
        $this->saveAuditReport();

        $this->auditReport['summary']['total_difference'] =
            $this->auditReport['summary']['total_recalculated_penalty'] -
            $this->auditReport['summary']['total_previous_penalty'];

        // Log the recalculation
        Log::info('Penalty recalculation completed', $this->auditReport['summary']);

        return Command::SUCCESS;
    }

    /**
     * Recalculate penalties for a single contract
     */
    private function recalculateContract(Contract $contract, Carbon $asOfDate, bool $isDryRun): array
    {
        $report = [
            'contract_id' => $contract->id,
            'contract_number' => $contract->shartnoma_raqami,
            'tenant' => $contract->tenant?->nomi ?? 'N/A',
            'schedules_count' => 0,
            'invalid_penalties' => 0,
            'corrected' => 0,
            'previous_total' => 0,
            'new_total' => 0,
            'schedules' => [],
        ];

        $schedules = $contract->paymentSchedules()->get();
        $report['schedules_count'] = $schedules->count();

        foreach ($schedules as $schedule) {
            $scheduleReport = $this->recalculateSchedule($schedule, $asOfDate, $isDryRun);
            $report['schedules'][] = $scheduleReport;

            $report['previous_total'] += $scheduleReport['previous_penalty'];
            $report['new_total'] += $scheduleReport['new_penalty'];

            if ($scheduleReport['is_invalid']) {
                $report['invalid_penalties']++;
            }

            if ($scheduleReport['was_corrected']) {
                $report['corrected']++;
            }
        }

        return $report;
    }

    /**
     * Recalculate penalty for a single schedule
     */
    private function recalculateSchedule(PaymentSchedule $schedule, Carbon $asOfDate, bool $isDryRun): array
    {
        $dueDate = Carbon::parse($schedule->oxirgi_muddat);
        $previousPenalty = (float) $schedule->penya_summasi;
        $previousOverdueDays = (int) $schedule->kechikish_kunlari;

        // For paid schedules, find the actual payment date
        $effectiveDate = $asOfDate;
        if ($schedule->holat === 'tolangan') {
            $payment = Payment::where('contract_id', $schedule->contract_id)
                ->where('holat', 'tasdiqlangan')
                ->where('tolov_sanasi', '>=', $schedule->tolov_sanasi)
                ->orderBy('tolov_sanasi')
                ->first();

            if ($payment) {
                $effectiveDate = Carbon::parse($payment->tolov_sanasi);
            }
        }

        // Calculate correct penalty using contract rules
        $newOverdueDays = 0;
        $newPenalty = 0;

        if ($schedule->holat !== 'tolangan' && $effectiveDate->gt($dueDate)) {
            $newOverdueDays = $dueDate->diffInDays($effectiveDate);
            $overdueAmount = (float) $schedule->qoldiq_summa;
            $newPenalty = $overdueAmount * PaymentSchedule::PENYA_RATE * $newOverdueDays;
            $maxPenalty = $overdueAmount * PaymentSchedule::MAX_PENYA_RATE;
            $newPenalty = min($newPenalty, $maxPenalty);
        } elseif ($schedule->holat === 'tolangan' && $effectiveDate->gt($dueDate)) {
            // For paid schedules, use the stored overdue amount
            $newOverdueDays = $dueDate->diffInDays($effectiveDate);
            // Use tolov_summasi as the base since qoldiq_summa is 0 for paid
            $baseAmount = (float) $schedule->tolov_summasi;
            $newPenalty = $baseAmount * PaymentSchedule::PENYA_RATE * $newOverdueDays;
            $maxPenalty = $baseAmount * PaymentSchedule::MAX_PENYA_RATE;
            $newPenalty = min($newPenalty, $maxPenalty);
        }

        $newPenalty = round($newPenalty, 2);

        // Check if previous penalty was invalid
        $isInvalid = false;
        $wasOnTime = $effectiveDate->lte($dueDate);

        if ($previousPenalty > 0 && $wasOnTime) {
            // Penalty charged but payment was on time - INVALID
            $isInvalid = true;
        } elseif ($previousPenalty > 0 && $previousOverdueDays === 0) {
            // Penalty without overdue days - INVALID
            $isInvalid = true;
        }

        $wasCorrected = abs($previousPenalty - $newPenalty) > 0.01;

        $report = [
            'schedule_id' => $schedule->id,
            'month' => $schedule->oy_raqami,
            'year' => $schedule->yil,
            'month_name' => $schedule->davr_nomi,
            'status' => $schedule->holat,
            'due_date' => $dueDate->format('Y-m-d'),
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'previous_penalty' => $previousPenalty,
            'previous_overdue_days' => $previousOverdueDays,
            'new_penalty' => $newPenalty,
            'new_overdue_days' => $newOverdueDays,
            'difference' => $newPenalty - $previousPenalty,
            'is_invalid' => $isInvalid,
            'was_corrected' => $wasCorrected,
            'reason' => $this->getCorrectionReason($isInvalid, $wasOnTime, $previousPenalty, $newPenalty),
        ];

        // Apply changes if not dry run
        if (!$isDryRun && $wasCorrected) {
            $schedule->penya_summasi = $newPenalty;
            $schedule->kechikish_kunlari = $newOverdueDays;
            $schedule->save();
        }

        return $report;
    }

    /**
     * Get a human-readable reason for the correction
     */
    private function getCorrectionReason(bool $isInvalid, bool $wasOnTime, float $previousPenalty, float $newPenalty): string
    {
        if ($isInvalid && $wasOnTime && $previousPenalty > 0) {
            return 'INVALID: Penalty charged but payment was on time';
        }

        if ($isInvalid && $previousPenalty > 0) {
            return 'INVALID: Penalty without overdue days';
        }

        if ($previousPenalty > $newPenalty) {
            return 'OVERSTATED: Previous penalty was too high';
        }

        if ($previousPenalty < $newPenalty) {
            return 'UNDERSTATED: Previous penalty was too low';
        }

        return 'OK: Penalty is correct';
    }

    /**
     * Display summary of recalculation
     */
    private function displaySummary(): void
    {
        $summary = $this->auditReport['summary'];

        $this->info('===========================================');
        $this->info('SUMMARY');
        $this->info('===========================================');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Contracts Processed', $summary['contracts_processed']],
                ['Schedules Processed', $summary['schedules_processed']],
                ['Invalid Penalties Found', $summary['invalid_penalties_found']],
                ['Penalties Corrected', $summary['penalties_corrected']],
                ['Previous Total Penalty', number_format($summary['total_previous_penalty'], 2) . ' UZS'],
                ['Recalculated Total Penalty', number_format($summary['total_recalculated_penalty'], 2) . ' UZS'],
                ['Total Difference', number_format($summary['total_recalculated_penalty'] - $summary['total_previous_penalty'], 2) . ' UZS'],
            ]
        );
    }

    /**
     * Display detailed report for each contract
     */
    private function displayDetailedReport(): void
    {
        $this->newLine();
        $this->info('===========================================');
        $this->info('DETAILED REPORT BY CONTRACT');
        $this->info('===========================================');

        foreach ($this->auditReport['contracts'] as $contract) {
            $this->newLine();
            $this->info("Contract #{$contract['contract_id']} - {$contract['contract_number']}");
            $this->info("Tenant: {$contract['tenant']}");

            $rows = [];
            foreach ($contract['schedules'] as $schedule) {
                if ($schedule['was_corrected'] || $schedule['is_invalid']) {
                    $rows[] = [
                        $schedule['month_name'],
                        $schedule['status'],
                        $schedule['due_date'],
                        number_format($schedule['previous_penalty'], 2),
                        $schedule['previous_overdue_days'],
                        number_format($schedule['new_penalty'], 2),
                        $schedule['new_overdue_days'],
                        $schedule['reason'],
                    ];
                }
            }

            if (!empty($rows)) {
                $this->table(
                    ['Month', 'Status', 'Due Date', 'Prev Penalty', 'Prev Days', 'New Penalty', 'New Days', 'Reason'],
                    $rows
                );
            } else {
                $this->comment('  No corrections needed');
            }
        }
    }

    /**
     * Save audit report to storage
     */
    private function saveAuditReport(): void
    {
        $filename = 'penalty_recalculation_' . now()->format('Y-m-d_His') . '.json';
        $path = storage_path('logs/' . $filename);

        file_put_contents($path, json_encode($this->auditReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info("Audit report saved to: {$path}");
    }
}
