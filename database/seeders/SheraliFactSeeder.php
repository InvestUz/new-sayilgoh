<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Lot;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\Carbon;

/**
 * SheraliFactSeeder - Production-grade seeder for sherali_fact.csv
 *
 * CSV Format (semicolon separated):
 * [0] Date/time: "27.03.2024 9:31"
 * [1] Account info: "20210000300792302016/305004780/..."
 * [2] Document number: "7084837"
 * [3] Code 1: "4"
 * [4] Code 2: "00401" or "00083" etc.
 * [5] Empty
 * [6] Amount: "16 855 693,80" (space as thousand separator, comma as decimal)
 * [7] Purpose: Contains lot number L{digits}L, INN/PINFL, tenant name
 */
class SheraliFactSeeder extends Seeder
{
    private array $matched = [];
    private array $skipped = [];
    private array $unmatched = [];
    private array $duplicates = [];

    private array $lotCache = [];
    private array $innCache = [];
    private array $tenantCache = [];
    private array $processedPayments = [];

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('╔════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info('║           SHERALI FACT CSV IMPORT - Production Mode                        ║');
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        $csvPath = public_path('dataset/sherali_fact.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        // Clear existing imported payments from this source
        $this->command->info('Clearing previous sherali_fact imports...');
        $deleted = Payment::where('izoh', 'LIKE', '%sherali_fact.csv%')->delete();
        $this->command->info("Deleted {$deleted} previous imports");
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

        // Cache tenants by INN with multiple formats
        $tenants = Tenant::with(['contracts' => function ($q) {
            $q->where('holat', 'faol');
        }])->get();

        foreach ($tenants as $tenant) {
            if (empty($tenant->inn)) continue;

            $cleanInn = preg_replace('/[^0-9]/', '', $tenant->inn);
            if (empty($cleanInn)) continue;

            $this->innCache[$cleanInn] = $tenant;

            // PINFL (14 digits) - also cache by last 9 digits
            if (strlen($cleanInn) == 14) {
                $last9 = substr($cleanInn, -9);
                if (!isset($this->innCache[$last9])) {
                    $this->innCache[$last9] = $tenant;
                }
            }

            // Cache by normalized name
            $normalizedName = $this->normalizeName($tenant->name);
            if ($normalizedName && !isset($this->tenantCache[$normalizedName])) {
                $this->tenantCache[$normalizedName] = $tenant;
            }
        }

        $this->command->info("  Lots cached: " . count($lots));
        $this->command->info("  Tenants cached: " . count($tenants));
        $this->command->info("  INN entries: " . count($this->innCache));
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
        // Parse row data based on sherali_fact.csv format
        $dateStr = trim($row[0] ?? '');
        $accountInfo = trim($row[1] ?? '');
        $docNumber = trim($row[2] ?? '');
        $amountStr = trim($row[6] ?? '');
        $purpose = trim($row[7] ?? '');

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

        // Check for rental/auction payment
        if (!$this->isRentalPayment($purpose, $accountInfo)) {
            $this->skipped[] = [
                'row' => $rowNumber,
                'reason' => 'Not a rental payment',
                'purpose' => mb_substr($purpose, 0, 60),
            ];
            return;
        }

        // Extract payment info
        $extracted = $this->extractPaymentInfo($purpose, $accountInfo);

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

        // Find matching contract
        $contract = $this->findContract($extracted);

        if (!$contract) {
            $this->unmatched[] = [
                'row' => $rowNumber,
                'date' => $date->format('d.m.Y'),
                'lot_number' => $extracted['lot_number'] ?? '-',
                'inn' => $extracted['inn'] ?? '-',
                'tenant_name' => $extracted['tenant_name'] ?? '-',
                'amount' => $amount,
                'purpose' => mb_substr($purpose, 0, 80),
            ];
            return;
        }

        // Create payment
        $this->createPayment($contract, $date, $amount, $docNumber, $purpose, $extracted, $rowNumber);

        $this->matched[] = [
            'row' => $rowNumber,
            'date' => $date->format('d.m.Y'),
            'lot_number' => $contract->lot->lot_raqami ?? $extracted['lot_number'],
            'tenant_name' => $contract->tenant->name ?? '-',
            'amount' => $amount,
            'match_by' => $extracted['matched_by'] ?? 'unknown',
        ];
    }

    private function isRentalPayment(string $purpose, string $accountInfo): bool
    {
        $keywords = [
            'lotdan',
            'G`oliblikdan',
            'Buyurtmachi',
            'SAYILGOH',
            'auksion',
            'ijara',
            'аренд',
            'Аренд',
        ];

        foreach ($keywords as $keyword) {
            if (mb_stripos($purpose, $keyword) !== false) {
                return true;
            }
        }

        // Check for lot number pattern
        if (preg_match('/L\d{5,}L/i', $purpose)) {
            return true;
        }

        // Check account info for rental indicators
        if (preg_match('/SAYILGOH/i', $accountInfo)) {
            return true;
        }

        return false;
    }

    private function extractPaymentInfo(string $purpose, string $accountInfo): array
    {
        $result = [
            'lot_number' => null,
            'inn' => null,
            'tenant_name' => null,
            'matched_by' => null,
        ];

        // Extract lot number (L{digits}L pattern)
        if (preg_match('/L(\d{6,10})L/i', $purpose, $matches)) {
            $result['lot_number'] = $matches[1];
        }

        // Extract INN/PINFL from purpose
        if (preg_match('/(?:INN\s*[-:]?\s*PINFL|PINFL|INN)\s*[:=]?\s*(\d{9,14})/i', $purpose, $matches)) {
            $result['inn'] = $matches[1];
        }

        // Extract INN from account info (format: account/INN/name)
        if (empty($result['inn']) && preg_match('/\/(\d{9})\//', $accountInfo, $matches)) {
            $result['inn'] = $matches[1];
        }

        // Extract tenant name
        if (preg_match('/G`olib\s*[:=]?\s*"?([^"]+)"?\s*(?:MCHJ|MChJ|DUK|XK|QK|xususiy|YaTT|,|Buyurtmachi|$)/ui', $purpose, $matches)) {
            $result['tenant_name'] = trim($matches[1], ' "\'');
        }

        return $result;
    }

    private function findContract(array &$extracted): ?Contract
    {
        // Method 1: By lot number (most reliable)
        if (!empty($extracted['lot_number'])) {
            $lotNum = $extracted['lot_number'];

            if (isset($this->lotCache[$lotNum])) {
                $lot = $this->lotCache[$lotNum];
                if ($lot->contracts->isNotEmpty()) {
                    $extracted['matched_by'] = 'lot_number';
                    return $lot->contracts->first();
                }
            }

            // Try with L prefix/suffix
            $fullLot = 'L' . $lotNum . 'L';
            if (isset($this->lotCache[$fullLot])) {
                $lot = $this->lotCache[$fullLot];
                if ($lot->contracts->isNotEmpty()) {
                    $extracted['matched_by'] = 'lot_number_full';
                    return $lot->contracts->first();
                }
            }

            // Database LIKE search
            $lot = Lot::where('lot_raqami', 'LIKE', '%' . $lotNum . '%')
                ->with(['contracts' => fn($q) => $q->where('holat', 'faol')->with('tenant')])
                ->first();

            if ($lot && $lot->contracts->isNotEmpty()) {
                $extracted['matched_by'] = 'lot_number_like';
                return $lot->contracts->first();
            }
        }

        // Method 2: By INN
        if (!empty($extracted['inn'])) {
            $inn = $extracted['inn'];

            if (isset($this->innCache[$inn])) {
                $tenant = $this->innCache[$inn];
                if ($tenant->contracts->isNotEmpty()) {
                    $extracted['matched_by'] = 'inn';
                    return $tenant->contracts->first();
                }
            }

            // Try last 9 digits for PINFL
            if (strlen($inn) > 9) {
                $last9 = substr($inn, -9);
                if (isset($this->innCache[$last9])) {
                    $tenant = $this->innCache[$last9];
                    if ($tenant->contracts->isNotEmpty()) {
                        $extracted['matched_by'] = 'inn_partial';
                        return $tenant->contracts->first();
                    }
                }
            }

            // Database LIKE search
            $tenant = Tenant::where('inn', 'LIKE', '%' . $inn . '%')
                ->with(['contracts' => fn($q) => $q->where('holat', 'faol')])
                ->first();

            if ($tenant && $tenant->contracts->isNotEmpty()) {
                $extracted['matched_by'] = 'inn_like';
                return $tenant->contracts->first();
            }
        }

        // Method 3: By tenant name
        if (!empty($extracted['tenant_name'])) {
            $normalizedName = $this->normalizeName($extracted['tenant_name']);

            if (isset($this->tenantCache[$normalizedName])) {
                $tenant = $this->tenantCache[$normalizedName];
                if ($tenant->contracts->isNotEmpty()) {
                    $extracted['matched_by'] = 'tenant_name';
                    return $tenant->contracts->first();
                }
            }

            // Database LIKE search
            $tenant = Tenant::where('name', 'LIKE', '%' . $extracted['tenant_name'] . '%')
                ->with(['contracts' => fn($q) => $q->where('holat', 'faol')])
                ->first();

            if ($tenant && $tenant->contracts->isNotEmpty()) {
                $extracted['matched_by'] = 'tenant_name_like';
                return $tenant->contracts->first();
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
        array $extracted,
        int $rowNumber
    ): void {
        try {
            DB::beginTransaction();

            $payment = Payment::create([
                'contract_id' => $contract->id,
                'tolov_raqami' => Payment::generateTolovRaqami(),
                'tolov_sanasi' => $date,
                'summa' => $amount,
                'asosiy_qarz_uchun' => 0,
                'penya_uchun' => 0,
                'auksion_uchun' => 0,
                'avans' => 0,
                'tolov_usuli' => 'bank_otkazmasi',
                'hujjat_raqami' => $docNumber,
                'holat' => 'tasdiqlangan',
                'izoh' => 'Imported from sherali_fact.csv. ' .
                          'Row: ' . $rowNumber . '. ' .
                          'Matched by: ' . ($extracted['matched_by'] ?? 'N/A') . '. ' .
                          mb_substr($purpose, 0, 150),
            ]);

            // Apply payment to schedules (FIFO)
            $payment->applyToContract();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SheraliFactSeeder payment creation failed', [
                'row' => $rowNumber,
                'error' => $e->getMessage(),
                'contract_id' => $contract->id,
            ]);

            $this->skipped[] = [
                'row' => $rowNumber,
                'reason' => 'DB Error: ' . $e->getMessage(),
            ];
        }
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

    private function normalizeName(?string $name): string
    {
        if (empty($name)) return '';
        $name = mb_strtolower($name);
        $name = preg_replace('/["\'\-\.\,\(\)]+/', '', $name);
        $name = preg_replace('/\s+/', '', $name);
        return $name;
    }

    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('╔════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info('║                           IMPORT SUMMARY                                   ║');
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        // Matched
        $matchedCount = count($this->matched);
        $matchedSum = array_sum(array_column($this->matched, 'amount'));
        $this->command->info("✓ MATCHED PAYMENTS: {$matchedCount}");
        $this->command->info("  Total amount: " . number_format($matchedSum, 2) . " UZS");

        if ($matchedCount > 0) {
            // Group by match method
            $byMethod = [];
            foreach ($this->matched as $m) {
                $method = $m['match_by'] ?? 'unknown';
                $byMethod[$method] = ($byMethod[$method] ?? 0) + 1;
            }
            foreach ($byMethod as $method => $count) {
                $this->command->info("    - {$method}: {$count}");
            }
        }
        $this->command->info('');

        // Unmatched
        $unmatchedCount = count($this->unmatched);
        $unmatchedSum = array_sum(array_column($this->unmatched, 'amount'));
        $this->command->warn("✗ UNMATCHED PAYMENTS: {$unmatchedCount}");
        $this->command->warn("  Total amount: " . number_format($unmatchedSum, 2) . " UZS");

        if ($unmatchedCount > 0 && $unmatchedCount <= 30) {
            $this->command->info('');
            $this->command->info(sprintf('  %-6s %-12s %-14s %-30s %15s',
                'Row', 'Date', 'Lot', 'Tenant', 'Amount'));
            $this->command->info('  ' . str_repeat('-', 80));
            foreach ($this->unmatched as $u) {
                $this->command->warn(sprintf('  %-6d %-12s %-14s %-30s %15s',
                    $u['row'],
                    $u['date'],
                    mb_substr($u['lot_number'], 0, 14),
                    mb_substr($u['tenant_name'], 0, 30),
                    number_format($u['amount'], 2)
                ));
            }
        } elseif ($unmatchedCount > 30) {
            $this->command->warn("  (Showing first 30 of {$unmatchedCount})");
            foreach (array_slice($this->unmatched, 0, 30) as $u) {
                $this->command->warn(sprintf('  Row %d: %s | %s | %s',
                    $u['row'], $u['lot_number'], $u['tenant_name'], number_format($u['amount'], 2)
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
        $total = $matchedCount + $unmatchedCount + $skippedCount + count($this->duplicates);
        $this->command->info("║  Total processed: {$total}");
        $this->command->info("║  ✓ Imported:      {$matchedCount} (" . number_format($matchedSum, 0) . " UZS)");
        $this->command->warn("║  ✗ Failed:        {$unmatchedCount} (" . number_format($unmatchedSum, 0) . " UZS)");
        $this->command->comment("║  ○ Skipped:       {$skippedCount}");
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
    }
}
