<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Lot;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use Carbon\Carbon;

/**
 * SheraliFactSeeder - Production-grade seeder for sayilgoh_fakt_cv.csv
 *
 * CSV Format (semicolon separated):
 * [0] Date/time: "27.03.2024 9:31"
 * [1] Account info: "20210000300792302016/305004780/..."
 * [2] Document number: "7084837"
 * [3] Code 1: "4"
 * [4] Code 2: "00401"
 * [5] Amount: "16 855 693,80" (space as thousand separator, comma as decimal)
 * [6] Purpose: payment description
 * [7] Lot/Contract reference: "8408626" or "21/21" - KEY COLUMN FOR MATCHING
 */
class SheraliFactSeeder extends Seeder
{
    private array $matched = [];
    private array $skipped = [];
    private array $unmatched = [];
    private array $duplicates = [];

    private array $lotCache = [];
    private array $contractCache = [];
    private array $processedPayments = [];

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('╔════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info('║           SAYILGOH FAKT CSV IMPORT - Production Mode                       ║');
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        $csvPath = public_path('dataset/sayilgoh_fakt_cv.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        // Clear existing imported payments from this source
        $this->command->info('Clearing previous imports...');
        $deleted = Payment::where('izoh', 'LIKE', '%sayilgoh_fakt_cv%')->forceDelete();
        $this->command->info("Deleted {$deleted} previous imports");

        // Reset ALL payment_schedules to initial state (important!)
        $this->command->info('Resetting payment schedules to initial state...');
        $resetCount = DB::table('payment_schedules')->update([
            'tolangan_summa' => 0,
            'qoldiq_summa' => DB::raw('tolov_summasi'),
            'tolangan_penya' => 0,
            'penya_summasi' => 0,
            'kechikish_kunlari' => 0,
            'holat' => 'kutilmoqda',
        ]);
        $this->command->info("Reset {$resetCount} payment schedules");

        // Reset contract advance balances
        DB::table('contracts')->update(['avans_balans' => 0]);
        $this->command->info('Reset contract advance balances');
        $this->command->info('');

        // Build lookup caches
        $this->buildCaches();

        // Process CSV
        $this->processCSV($csvPath);

        // Display results
        $this->displaySummary();
    }

    private function buildCaches(): void
    {
        $this->command->info('Building lookup caches...');

        // Cache lots by various formats
        $lots = Lot::with(['contracts' => function ($q) {
            $q->where('holat', 'faol')->with('tenant');
        }])->get();

        foreach ($lots as $lot) {
            if (empty($lot->lot_raqami)) continue;

            // Original format
            $this->lotCache[$lot->lot_raqami] = $lot;

            // Numeric only (L12345678L -> 12345678)
            $numericOnly = preg_replace('/[^0-9]/', '', $lot->lot_raqami);
            if ($numericOnly) {
                $this->lotCache[$numericOnly] = $lot;
            }

            // Uppercase
            $this->lotCache[strtoupper($lot->lot_raqami)] = $lot;
        }

        // Cache contracts by shartnoma_raqami
        $contracts = Contract::where('holat', 'faol')
            ->with(['lot', 'tenant'])
            ->get();

        foreach ($contracts as $contract) {
            if (empty($contract->shartnoma_raqami)) continue;

            $this->contractCache[$contract->shartnoma_raqami] = $contract;

            // Extract numeric part (SH-21 -> 21)
            if (preg_match('/(\d+)/', $contract->shartnoma_raqami, $matches)) {
                $num = $matches[1];
                $this->contractCache[$num . '/' . $num] = $contract;
                $this->contractCache[$num] = $contract;
                $this->contractCache['SH-' . $num] = $contract;
            }
        }

        $this->command->info("  Lots cached: " . count($lots));
        $this->command->info("  Contracts cached: " . count($contracts));
        $this->command->info('');
    }

    private function processCSV(string $csvPath): void
    {
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->command->error('Cannot open CSV file');
            return;
        }

        $rowNumber = 0;
        $this->command->info('Processing CSV rows...');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;

            // Skip empty/header rows
            if ($rowNumber === 1 || empty(array_filter($row))) {
                continue;
            }

            // Validate minimum columns
            if (count($row) < 7) {
                $this->skipped[] = [
                    'row' => $rowNumber,
                    'reason' => 'Insufficient columns: ' . count($row),
                ];
                continue;
            }

            $this->processRow($row, $rowNumber);

            if ($rowNumber % 100 === 0) {
                $this->command->info("  Processed {$rowNumber} rows...");
            }
        }

        fclose($handle);
        $this->command->info("  Total rows scanned: {$rowNumber}");
        $this->command->info('');
    }

    private function processRow(array $row, int $rowNumber): void
    {
        // Parse row data based on sayilgoh_fakt_cv.csv format
        $dateStr = trim($row[0] ?? '');
        $accountInfo = trim($row[1] ?? '');
        $docNumber = trim($row[2] ?? '');
        $amountStr = trim($row[5] ?? '');  // Column 5 = Amount
        $purpose = trim($row[6] ?? '');    // Column 6 = Purpose
        $lotRef = trim($row[7] ?? '');     // Column 7 = Lot/Contract reference (KEY!)

        // Parse amount
        $amount = $this->parseAmount($amountStr);
        if ($amount <= 0) {
            $this->skipped[] = [
                'row' => $rowNumber,
                'reason' => 'Zero or invalid amount',
                'raw_amount' => $amountStr,
            ];
            return;
        }

        // Parse date
        $date = $this->parseDate($dateStr);
        if (!$date) {
            $this->skipped[] = [
                'row' => $rowNumber,
                'reason' => 'Invalid date format',
                'raw_date' => $dateStr,
            ];
            return;
        }

        // Check if this is a REFUND transaction (will be recorded with special status)
        $refundInfo = $this->isRefund($purpose, $accountInfo);

        // Generate unique key for duplicate detection
        $uniqueKey = $date->format('Y-m-d') . '_' . $docNumber . '_' . $amount;
        if (isset($this->processedPayments[$uniqueKey])) {
            $this->duplicates[] = [
                'row' => $rowNumber,
                'key' => $uniqueKey,
                'amount' => $amount,
            ];
            return;
        }
        $this->processedPayments[$uniqueKey] = true;

        // Find matching contract using LAST COLUMN (lotRef)
        $contract = $this->findContract($lotRef, $purpose);
        $matchedBy = 'lot_ref';

        if (!$contract) {
            $this->unmatched[] = [
                'row' => $rowNumber,
                'date' => $date->format('d.m.Y'),
                'lot_number' => $lotRef,
                'tenant_name' => '-',
                'amount' => $amount,
                'purpose' => mb_substr($purpose, 0, 80),
                'is_refund' => $refundInfo !== null,
            ];
            return;
        }

        // Create payment (with refund info if applicable)
        $this->createPayment($contract, $date, $amount, $docNumber, $purpose, $lotRef, $matchedBy, $rowNumber, $refundInfo);

        $matchType = $refundInfo ? 'refund' : $matchedBy;
        $this->matched[] = [
            'row' => $rowNumber,
            'date' => $date->format('d.m.Y'),
            'lot_number' => $contract->lot->lot_raqami ?? $lotRef,
            'tenant_name' => $contract->tenant->name ?? '-',
            'amount' => $amount,
            'match_by' => $matchType,
            'is_refund' => $refundInfo !== null,
            'refund_reason' => $refundInfo['reason'] ?? null,
        ];
    }

    /**
     * Check if transaction is a REFUND/RETURN (not to be skipped, but recorded differently)
     * Returns array with refund info if it's a refund, null otherwise
     */
    private function isRefund(string $purpose, string $accountInfo): ?array
    {
        // Patterns that indicate REFUND/RETURN transactions
        $refundPatterns = [
            'Возвратить по предприятию' => 'Korxonaga qaytarish',
            'Возвратить' => 'Qaytarish',
            '$BUDJET$' => 'Byudjet qaytarishi',
            'возврат' => 'Qaytarish',
            'qaytarish' => 'Qaytarish',
            'согласно заключения ГНИ' => 'Soliq inspeksiyasi qarori',
        ];

        // Check for refund patterns
        foreach ($refundPatterns as $pattern => $reason) {
            if (mb_stripos($purpose, $pattern) !== false) {
                return [
                    'is_refund' => true,
                    'reason' => $reason,
                    'source' => $this->extractRefundSource($accountInfo, $purpose),
                    'reference' => $this->extractRefundReference($purpose),
                ];
            }
        }

        // Check if from treasury account
        $treasuryPatterns = [
            'Иктисодиёт ва молия вазирлиги',
            'газна хисобвараги',
            '23402000300100001010',
        ];

        foreach ($treasuryPatterns as $pattern) {
            if (mb_stripos($accountInfo, $pattern) !== false) {
                if (mb_stripos($purpose, 'Возвратить') !== false ||
                    mb_stripos($purpose, 'возврат') !== false ||
                    mb_stripos($purpose, '$BUDJET$') !== false) {
                    return [
                        'is_refund' => true,
                        'reason' => 'Byudjetdan qaytarish',
                        'source' => 'Moliya vazirligi / Yagona gazna',
                        'reference' => $this->extractRefundReference($purpose),
                    ];
                }
            }
        }

        return null;
    }

    private function extractRefundSource(string $accountInfo, string $purpose): string
    {
        $parts = explode('/', $accountInfo);
        if (count($parts) >= 3) {
            return trim($parts[2]);
        }
        if (preg_match('/ИНН[:\s]*(\d+)/i', $purpose, $matches)) {
            return 'INN: ' . $matches[1];
        }
        return 'Noma\'lum manba';
    }

    private function extractRefundReference(string $purpose): ?string
    {
        if (preg_match('/ГНИ\s*№?\s*(\d+)/i', $purpose, $matches)) {
            return 'GNI №' . $matches[1];
        }
        if (preg_match('/Ч\/О\s*№?\s*(\d+)/i', $purpose, $matches)) {
            return 'CH/O №' . $matches[1];
        }
        return null;
    }

    private function findContract(string $lotRef, string $purpose): ?Contract
    {
        if (empty($lotRef)) {
            return null;
        }

        // Handle multiple refs (e.g., "12072339, 12072343")
        $refs = preg_split('/[,\s]+/', $lotRef);
        $primaryRef = trim($refs[0]);

        // Method 1: Contract format "21/21"
        if (preg_match('/^(\d+)\/\d+$/', $primaryRef, $matches)) {
            $num = $matches[1];
            if (isset($this->contractCache[$primaryRef])) {
                return $this->contractCache[$primaryRef];
            }
            if (isset($this->contractCache['SH-' . $num])) {
                return $this->contractCache['SH-' . $num];
            }
        }

        // Method 2: Lot number (numeric)
        $numericRef = preg_replace('/[^0-9]/', '', $primaryRef);
        if ($numericRef && isset($this->lotCache[$numericRef])) {
            $lot = $this->lotCache[$numericRef];
            if ($lot->contracts->isNotEmpty()) {
                return $lot->contracts->first();
            }
        }

        // Method 3: Database search for lot
        if ($numericRef) {
            $lot = Lot::where('lot_raqami', 'LIKE', '%' . $numericRef . '%')
                ->with(['contracts' => fn($q) => $q->where('holat', 'faol')->with('tenant')])
                ->first();

            if ($lot && $lot->contracts->isNotEmpty()) {
                return $lot->contracts->first();
            }
        }

        // Method 4: Extract from purpose L{digits}L
        if (preg_match('/L(\d{6,10})L/i', $purpose, $matches)) {
            $lotNum = $matches[1];
            if (isset($this->lotCache[$lotNum])) {
                $lot = $this->lotCache[$lotNum];
                if ($lot->contracts->isNotEmpty()) {
                    return $lot->contracts->first();
                }
            }
        }

        // Method 5: Format "10.окт" etc.
        if (preg_match('/^(\d+)\./', $primaryRef, $matches)) {
            $num = $matches[1];
            if (isset($this->contractCache['SH-' . $num])) {
                return $this->contractCache['SH-' . $num];
            }
        }

        return null;
    }

    private function createPayment(
        Contract $contract,
        Carbon $date,
        float $amount,
        string $docNumber,
        string $purpose,
        string $lotRef,
        string $matchedBy,
        int $rowNumber,
        ?array $refundInfo = null
    ): void {
        try {
            DB::beginTransaction();

            // Determine if this is a refund
            $isRefund = $refundInfo !== null;

            // Build detailed izoh for refunds
            $izohParts = ['sayilgoh_fakt_cv Row:' . $rowNumber, 'Ref:' . $lotRef];
            if ($isRefund) {
                $izohParts[] = 'QAYTARISH: ' . ($refundInfo['reason'] ?? 'Noma\'lum');
                if (!empty($refundInfo['source'])) {
                    $izohParts[] = 'Manba: ' . $refundInfo['source'];
                }
                if (!empty($refundInfo['reference'])) {
                    $izohParts[] = 'Hujjat: ' . $refundInfo['reference'];
                }
            }

            $payment = Payment::create([
                'contract_id' => $contract->id,
                'tolov_raqami' => Payment::generateTolovRaqami(),
                'tolov_sanasi' => $date,
                'summa' => $isRefund ? -abs($amount) : $amount, // Negative amount for refunds
                'asosiy_qarz_uchun' => 0,
                'penya_uchun' => 0,
                'auksion_uchun' => 0,
                'avans' => 0,
                'tolov_usuli' => 'bank_otkazmasi', // Use bank_otkazmasi for all (refunds identified by holat)
                'hujjat_raqami' => $docNumber,
                'holat' => $isRefund ? 'qaytarilgan' : 'tasdiqlangan',
                'izoh' => implode(' | ', $izohParts),
            ]);

            // Note: For regular payments with 'tasdiqlangan' status,
            // PaymentObserver automatically applies the payment to schedules.
            // For refunds ('qaytarilgan'), we do NOT apply to schedules.

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SheraliFactSeeder payment creation failed', [
                'row' => $rowNumber,
                'error' => $e->getMessage(),
                'contract_id' => $contract->id,
                'is_refund' => $isRefund ?? false,
            ]);

            $this->skipped[] = [
                'row' => $rowNumber,
                'reason' => 'DB Error: ' . $e->getMessage(),
            ];
        }
    }

    private function getPaymentMethod(string $purpose): string
    {
        if (mb_stripos($purpose, 'нақд') !== false || mb_stripos($purpose, 'накд') !== false) {
            return 'naqd';
        }
        if (mb_stripos($purpose, 'terminal') !== false) {
            return 'karta';
        }
        if (mb_stripos($purpose, 'PAYME') !== false || mb_stripos($purpose, 'Joyda') !== false) {
            return 'onlayn';
        }
        return 'bank_otkazmasi';
    }

    private function parseAmount(string $value): float
    {
        if (empty($value)) return 0;

        // Remove all whitespace (thousand separators)
        $value = preg_replace('/\s+/', '', $value);

        // Remove non-numeric except comma/dot/minus
        $value = preg_replace('/[^\d,.\-]/', '', $value);

        // Replace comma with dot for decimal
        $value = str_replace(',', '.', $value);

        // Handle multiple dots
        if (substr_count($value, '.') > 1) {
            $parts = explode('.', $value);
            $decimal = array_pop($parts);
            $value = implode('', $parts) . '.' . $decimal;
        }

        return (float) $value;
    }

    private function parseDate(string $value): ?Carbon
    {
        if (empty($value)) return null;

        $value = trim($value);

        // Format: "27.03.2024 9:31" or "27.03.2024"
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})/', $value, $matches)) {
            try {
                return Carbon::createFromDate((int)$matches[3], (int)$matches[2], (int)$matches[1]);
            } catch (\Exception $e) {
                return null;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('╔════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info('║                           IMPORT SUMMARY                                   ║');
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        // Separate refunds from regular payments
        $refunds = array_filter($this->matched, fn($m) => $m['is_refund'] ?? false);
        $regularPayments = array_filter($this->matched, fn($m) => !($m['is_refund'] ?? false));

        // Regular Matched Payments
        $matchedCount = count($regularPayments);
        $matchedSum = array_sum(array_column($regularPayments, 'amount'));
        $this->command->info("✓ MATCHED PAYMENTS: {$matchedCount}");
        $this->command->info("  Total amount: " . number_format($matchedSum, 2) . " UZS");
        $this->command->info('');

        // Refunds
        $refundCount = count($refunds);
        $refundSum = array_sum(array_column($refunds, 'amount'));
        if ($refundCount > 0) {
            $this->command->comment("↩ REFUNDS RECORDED: {$refundCount}");
            $this->command->comment("  Total refund amount: " . number_format($refundSum, 2) . " UZS");
            foreach ($refunds as $r) {
                $this->command->comment(sprintf('    Row %d: %s | -%s | %s',
                    $r['row'], $r['lot_number'], number_format($r['amount'], 2), $r['refund_reason'] ?? ''
                ));
            }
            $this->command->info('');
        }

        // Unmatched
        $unmatchedCount = count($this->unmatched);
        $unmatchedSum = array_sum(array_column($this->unmatched, 'amount'));
        $this->command->warn("✗ UNMATCHED PAYMENTS: {$unmatchedCount}");
        $this->command->warn("  Total amount: " . number_format($unmatchedSum, 2) . " UZS");

        if ($unmatchedCount > 0 && $unmatchedCount <= 30) {
            $this->command->info('');
            foreach ($this->unmatched as $u) {
                $refundMark = ($u['is_refund'] ?? false) ? ' [REFUND]' : '';
                $this->command->warn(sprintf('  Row %d: %s | %s%s',
                    $u['row'], $u['lot_number'], number_format($u['amount'], 2), $refundMark
                ));
            }
        } elseif ($unmatchedCount > 30) {
            $this->command->warn("  (Showing first 30 of {$unmatchedCount})");
            foreach (array_slice($this->unmatched, 0, 30) as $u) {
                $refundMark = ($u['is_refund'] ?? false) ? ' [REFUND]' : '';
                $this->command->warn(sprintf('  Row %d: %s | %s%s',
                    $u['row'], $u['lot_number'], number_format($u['amount'], 2), $refundMark
                ));
            }
        }
        $this->command->info('');

        // Skipped
        $skippedCount = count($this->skipped);
        $this->command->comment("○ SKIPPED ROWS: {$skippedCount}");

        // Group by reason
        $byReason = [];
        foreach ($this->skipped as $s) {
            $reason = $s['reason'] ?? 'unknown';
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
        }
        foreach ($byReason as $reason => $count) {
            $this->command->comment("    - {$reason}: {$count}");
        }
        $this->command->info('');

        // Duplicates
        if (count($this->duplicates) > 0) {
            $this->command->comment("○ DUPLICATES SKIPPED: " . count($this->duplicates));
        }

        // Final summary
        $this->command->info('╔════════════════════════════════════════════════════════════════════════════╗');
        $total = count($this->matched) + $unmatchedCount + $skippedCount + count($this->duplicates);
        $this->command->info("║  Total processed: {$total}");
        $this->command->info("║  ✓ Imported:      {$matchedCount} (" . number_format($matchedSum, 0) . " UZS)");
        if ($refundCount > 0) {
            $this->command->comment("║  ↩ Refunds:       {$refundCount} (" . number_format($refundSum, 0) . " UZS)");
        }
        $this->command->warn("║  ✗ Failed:        {$unmatchedCount} (" . number_format($unmatchedSum, 0) . " UZS)");
        $this->command->comment("║  ○ Skipped:       {$skippedCount}");
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
    }
}
