<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Lot;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\Carbon;

class FactPaymentSeeder extends Seeder
{
    /**
     * Statistics for reporting
     */
    private array $matched = [];
    private array $skipped = [];
    private array $unmatched = [];

    /**
     * Cache for faster lookups
     */
    private array $lotCache = [];
    private array $innCache = [];
    private array $tenantCache = [];

    public function run(): void
    {
        $this->command->info('Starting FACT payment import from CSV...');
        $this->command->info('');

        // Build caches for faster lookup
        $this->buildCaches();

        // Read CSV file
        $csvPath = public_path('dataset/POYTAXT SAYILGOH FACT.csv');
        if (!file_exists($csvPath)) {
            $this->command->error('CSV file not found: ' . $csvPath);
            return;
        }

        // Parse CSV
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->command->error('Cannot open CSV file');
            return;
        }

        // Read header row
        $header = fgetcsv($handle, 0, ';');
        $this->command->info('CSV Columns: ' . implode(' | ', array_map(fn($h, $i) => "[$i]$h", $header, array_keys($header))));
        $this->command->info('');

        $rowNumber = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $this->processRow($row, $rowNumber);

            if ($rowNumber % 100 === 0) {
                $this->command->info("Processed {$rowNumber} rows...");
            }
        }

        fclose($handle);

        // Display summary
        $this->displaySummary();
    }

    /**
     * Build lookup caches from existing database records
     */
    private function buildCaches(): void
    {
        $this->command->info('Building lookup caches...');

        // Cache all lots by lot_raqami (normalized)
        $lots = Lot::with(['contracts' => function ($q) {
            $q->where('holat', 'faol')->with('tenant');
        }])->get();

        foreach ($lots as $lot) {
            // Store by original lot number
            $this->lotCache[$lot->lot_raqami] = $lot;

            // Also store by numeric-only version (L8408626L -> 8408626)
            $numericLot = preg_replace('/[^0-9]/', '', $lot->lot_raqami);
            if ($numericLot) {
                $this->lotCache[$numericLot] = $lot;
            }
        }

        // Cache tenants by INN
        $tenants = Tenant::with(['contracts' => function ($q) {
            $q->where('holat', 'faol');
        }])->get();

        foreach ($tenants as $tenant) {
            $cleanInn = preg_replace('/[^0-9]/', '', $tenant->inn);
            if ($cleanInn) {
                $this->innCache[$cleanInn] = $tenant;
            }

            // Also cache by normalized name
            $normalizedName = $this->normalizeName($tenant->name);
            if ($normalizedName) {
                $this->tenantCache[$normalizedName] = $tenant;
            }
        }

        $this->command->info("Cached {$lots->count()} lots and {$tenants->count()} tenants");
        $this->command->info('');
    }

    /**
     * Process a single CSV row
     *
     * CSV columns (semicolon separated):
     * 0: Регион, 1: Филиал, 2: Счет, 3: Дата, 4: Счет корреспондента
     * 5: Наименование корреспондента, 6: МФО, 7: Наименование банка корреспондента
     * 8: ИНН, 9: № документа, 10: Сумма дебет, 11: Сумма кредит
     * 12: Кассовый символ, 13: Код, 14: Назначение платежа
     */
    private function processRow(array $row, int $rowNumber): void
    {
        // Parse row data
        $data = [
            'region' => $this->cleanValue($row[0] ?? ''),
            'branch' => $this->cleanValue($row[1] ?? ''),
            'account' => $this->cleanValue($row[2] ?? ''),
            'date' => $this->parseDate($row[3] ?? ''),
            'correspondent_account' => $this->cleanValue($row[4] ?? ''),
            'correspondent_name' => $this->cleanValue($row[5] ?? ''),
            'mfo' => $this->cleanValue($row[6] ?? ''),
            'bank_name' => $this->cleanValue($row[7] ?? ''),
            'inn' => $this->cleanValue($row[8] ?? ''),
            'document_number' => $this->cleanValue($row[9] ?? ''),
            'debit' => $this->parseNumber($row[10] ?? ''),
            'credit' => $this->parseNumber($row[11] ?? ''),
            'cash_symbol' => $this->cleanValue($row[12] ?? ''),
            'code' => $this->cleanValue($row[13] ?? ''),
            'payment_purpose' => $this->cleanValue($row[14] ?? ''),
            'row_number' => $rowNumber,
        ];

        // Skip if no credit amount (not an incoming payment)
        if ($data['credit'] <= 0) {
            $this->skipped[] = [
                'row' => $rowNumber,
                'reason' => 'No credit amount (debit only: ' . number_format($data['debit'], 2) . ')',
                'purpose' => mb_substr($data['payment_purpose'], 0, 80) . '...',
            ];
            return;
        }

        // Check if this is a rental/auction payment by looking at payment purpose
        if (!$this->isRentalPayment($data['payment_purpose'])) {
            $this->skipped[] = [
                'row' => $rowNumber,
                'reason' => 'Not a rental/auction payment',
                'purpose' => mb_substr($data['payment_purpose'], 0, 80) . '...',
            ];
            return;
        }

        // Extract lot number, INN/PINFL, and tenant name from payment purpose
        $extracted = $this->extractPaymentInfo($data['payment_purpose']);

        // Try to find matching contract
        $contract = $this->findContract($extracted, $data);

        if (!$contract) {
            $this->unmatched[] = [
                'row' => $rowNumber,
                'lot_number' => $extracted['lot_number'] ?? 'N/A',
                'inn_pinfl' => $extracted['inn_pinfl'] ?? 'N/A',
                'tenant_name' => $extracted['tenant_name'] ?? 'N/A',
                'amount' => $data['credit'],
                'date' => $data['date'] ? $data['date']->format('d.m.Y') : 'N/A',
                'purpose' => mb_substr($data['payment_purpose'], 0, 100),
            ];
            return;
        }

        // Create payment record
        $this->createPayment($contract, $data, $extracted);

        $this->matched[] = [
            'row' => $rowNumber,
            'lot_number' => $contract->lot->lot_raqami ?? $extracted['lot_number'],
            'tenant_name' => $contract->tenant->name ?? 'N/A',
            'amount' => $data['credit'],
            'date' => $data['date'] ? $data['date']->format('d.m.Y') : 'N/A',
            'match_by' => $extracted['matched_by'] ?? 'unknown',
        ];
    }

    /**
     * Check if payment purpose indicates a rental/auction payment
     */
    private function isRentalPayment(string $purpose): bool
    {
        $keywords = [
            'lotdan',
            'G`oliblikdan',
            'Buyurtmachi',
            'SAYILGOH',
            'auksion',
            'ijara',
            // Pattern with L{number}L
        ];

        $purposeLower = mb_strtolower($purpose);

        foreach ($keywords as $keyword) {
            if (mb_stripos($purpose, $keyword) !== false) {
                return true;
            }
        }

        // Also check for lot number pattern L{digits}L
        if (preg_match('/L\d{5,}L/i', $purpose)) {
            return true;
        }

        return false;
    }

    /**
     * Extract lot number, INN/PINFL, and tenant name from payment purpose
     *
     * Examples:
     * - "T-03. L8408626L - lotdan G`oliblikdan Buyurtmachi hisobiga INN - PINFL:303736663 G`olib:"GLOBERENT FINANCE" MCHJ"
     * - "T-03-7232598 | L11570247L - lotdan G`oliblikdan Buyurtmachi hisobiga INN - PINFL:311587054 G`olib:"GRUPO IBERIA..."
     */
    private function extractPaymentInfo(string $purpose): array
    {
        $result = [
            'lot_number' => null,
            'inn_pinfl' => null,
            'tenant_name' => null,
        ];

        // Extract lot number (pattern: L{7-8 digits}L)
        if (preg_match('/L(\d{5,10})L/i', $purpose, $matches)) {
            $result['lot_number'] = $matches[1]; // Just the numeric part
        }

        // Extract INN/PINFL (pattern: INN - PINFL:{digits} or PINFL:{digits} or INN:{digits})
        if (preg_match('/(?:INN\s*[-:]?\s*PINFL|PINFL|INN)\s*[:=]?\s*(\d{9,14})/i', $purpose, $matches)) {
            $result['inn_pinfl'] = $matches[1];
        }

        // Extract tenant name (pattern: G`olib:"NAME" or G`olib:NAME)
        if (preg_match('/G`olib\s*[:=]?\s*"?([^"]+)"?\s*(?:MCHJ|MChJ|DUK|XK|QK|АЖ|AJ|,|Buyurtmachi|$)/ui', $purpose, $matches)) {
            $result['tenant_name'] = trim($matches[1], ' "\'');
        }

        return $result;
    }

    /**
     * Find matching contract
     * Priority: 1) Lot number, 2) INN/PINFL, 3) Tenant name
     */
    private function findContract(array &$extracted, array $data): ?Contract
    {
        // Method 1: Match by lot number (most reliable)
        if (!empty($extracted['lot_number'])) {
            $lotNumber = $extracted['lot_number'];

            // Try exact match
            if (isset($this->lotCache[$lotNumber])) {
                $lot = $this->lotCache[$lotNumber];
                $contract = $lot->contracts->first();
                if ($contract) {
                    $extracted['matched_by'] = 'lot_number';
                    return $contract;
                }
            }

            // Try with L prefix/suffix
            $fullLotNumber = 'L' . $lotNumber . 'L';
            if (isset($this->lotCache[$fullLotNumber])) {
                $lot = $this->lotCache[$fullLotNumber];
                $contract = $lot->contracts->first();
                if ($contract) {
                    $extracted['matched_by'] = 'lot_number_full';
                    return $contract;
                }
            }

            // Try LIKE search in database
            $lot = Lot::where('lot_raqami', 'LIKE', '%' . $lotNumber . '%')
                ->with(['contracts' => function ($q) {
                    $q->where('holat', 'faol')->with('tenant');
                }])
                ->first();

            if ($lot && $lot->contracts->isNotEmpty()) {
                $extracted['matched_by'] = 'lot_number_like';
                return $lot->contracts->first();
            }
        }

        // Method 2: Match by INN/PINFL
        if (!empty($extracted['inn_pinfl'])) {
            $inn = $extracted['inn_pinfl'];

            if (isset($this->innCache[$inn])) {
                $tenant = $this->innCache[$inn];
                $contract = $tenant->contracts->first();
                if ($contract) {
                    $extracted['matched_by'] = 'inn_pinfl';
                    return $contract;
                }
            }

            // Try LIKE search
            $tenant = Tenant::where('inn', 'LIKE', '%' . $inn . '%')
                ->with(['contracts' => function ($q) {
                    $q->where('holat', 'faol');
                }])
                ->first();

            if ($tenant && $tenant->contracts->isNotEmpty()) {
                $extracted['matched_by'] = 'inn_pinfl_like';
                return $tenant->contracts->first();
            }
        }

        // Method 3: Match by tenant name (least reliable - one tenant may have multiple lots)
        if (!empty($extracted['tenant_name'])) {
            $normalizedName = $this->normalizeName($extracted['tenant_name']);

            if (isset($this->tenantCache[$normalizedName])) {
                $tenant = $this->tenantCache[$normalizedName];
                $contract = $tenant->contracts->first();
                if ($contract) {
                    $extracted['matched_by'] = 'tenant_name_exact';
                    return $contract;
                }
            }

            // Try fuzzy LIKE search
            $tenant = Tenant::where('name', 'LIKE', '%' . $extracted['tenant_name'] . '%')
                ->with(['contracts' => function ($q) {
                    $q->where('holat', 'faol');
                }])
                ->first();

            if ($tenant && $tenant->contracts->isNotEmpty()) {
                // Warning: one tenant may have multiple lots
                if ($tenant->contracts->count() > 1) {
                    $extracted['warning'] = 'Tenant has multiple contracts, using first one';
                }
                $extracted['matched_by'] = 'tenant_name_like';
                return $tenant->contracts->first();
            }
        }

        return null;
    }

    /**
     * Create payment record and apply it to schedules
     */
    private function createPayment(Contract $contract, array $data, array $extracted): void
    {
        $payment = Payment::create([
            'contract_id' => $contract->id,
            'tolov_raqami' => Payment::generateTolovRaqami(),
            'tolov_sanasi' => $data['date'] ?? now(),
            'summa' => $data['credit'],
            'asosiy_qarz_uchun' => 0, // Will be calculated by applyToContract
            'penya_uchun' => 0,
            'auksion_uchun' => 0,
            'avans' => 0,
            'tolov_usuli' => 'bank_otkazmasi',
            'hujjat_raqami' => $data['document_number'],
            'holat' => 'tasdiqlangan',
            'izoh' => 'Imported from FACT CSV. ' .
                      'Row: ' . $data['row_number'] . '. ' .
                      'Matched by: ' . ($extracted['matched_by'] ?? 'N/A') . '. ' .
                      mb_substr($data['payment_purpose'], 0, 200),
        ]);

        // IMPORTANT: Apply payment to schedules (FIFO - oldest debt first)
        $payment->applyToContract();
    }

    /**
     * Display summary report
     */
    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('='.str_repeat('=', 79));
        $this->command->info('                              IMPORT SUMMARY');
        $this->command->info('='.str_repeat('=', 79));
        $this->command->info('');

        // Matched payments
        $this->command->info("MATCHED PAYMENTS: " . count($this->matched));
        $this->command->info('-'.str_repeat('-', 79));
        if (count($this->matched) > 0) {
            $this->command->info(sprintf('%-6s %-15s %-30s %15s %12s %s',
                'Row', 'Lot Number', 'Tenant Name', 'Amount', 'Date', 'Match By'));
            $this->command->info('-'.str_repeat('-', 79));

            $totalMatched = 0;
            foreach (array_slice($this->matched, 0, 20) as $item) {
                $this->command->info(sprintf('%-6d %-15s %-30s %15s %12s %s',
                    $item['row'],
                    mb_substr($item['lot_number'], 0, 15),
                    mb_substr($item['tenant_name'], 0, 30),
                    number_format($item['amount'], 2),
                    $item['date'],
                    $item['match_by']
                ));
                $totalMatched += $item['amount'];
            }
            if (count($this->matched) > 20) {
                $this->command->info("... and " . (count($this->matched) - 20) . " more matched payments");
            }
            $this->command->info("TOTAL MATCHED AMOUNT: " . number_format($totalMatched, 2) . " UZS");
        }

        $this->command->info('');

        // Unmatched payments
        $this->command->warn("UNMATCHED PAYMENTS (No matching contract found): " . count($this->unmatched));
        $this->command->info('-'.str_repeat('-', 79));
        if (count($this->unmatched) > 0) {
            $this->command->info(sprintf('%-6s %-12s %-14s %15s %s',
                'Row', 'Lot Number', 'INN/PINFL', 'Amount', 'Purpose'));
            $this->command->info('-'.str_repeat('-', 79));

            $totalUnmatched = 0;
            foreach (array_slice($this->unmatched, 0, 30) as $item) {
                $this->command->warn(sprintf('%-6d %-12s %-14s %15s %s',
                    $item['row'],
                    mb_substr($item['lot_number'] ?? 'N/A', 0, 12),
                    mb_substr($item['inn_pinfl'] ?? 'N/A', 0, 14),
                    number_format($item['amount'], 2),
                    mb_substr($item['purpose'], 0, 50)
                ));
                $totalUnmatched += $item['amount'];
            }
            if (count($this->unmatched) > 30) {
                $this->command->warn("... and " . (count($this->unmatched) - 30) . " more unmatched payments");
            }
            $this->command->warn("TOTAL UNMATCHED AMOUNT: " . number_format($totalUnmatched, 2) . " UZS");
        }

        $this->command->info('');

        // Skipped rows
        $this->command->comment("SKIPPED ROWS (Not rental payments): " . count($this->skipped));
        $this->command->info('-'.str_repeat('-', 79));

        // Group skipped by reason
        $skippedByReason = [];
        foreach ($this->skipped as $item) {
            $reason = $item['reason'];
            if (!isset($skippedByReason[$reason])) {
                $skippedByReason[$reason] = 0;
            }
            $skippedByReason[$reason]++;
        }

        foreach ($skippedByReason as $reason => $count) {
            $this->command->comment("  - {$reason}: {$count} rows");
        }

        $this->command->info('');
        $this->command->info('='.str_repeat('=', 79));
        $this->command->info("SUMMARY:");
        $this->command->info("  - Total rows processed: " . (count($this->matched) + count($this->unmatched) + count($this->skipped)));
        $this->command->info("  - Matched (imported):   " . count($this->matched));
        $this->command->warn("  - Unmatched (failed):   " . count($this->unmatched));
        $this->command->comment("  - Skipped (not rental): " . count($this->skipped));
        $this->command->info('='.str_repeat('=', 79));
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Clean string value
     */
    private function cleanValue(?string $value): string
    {
        if ($value === null) return '';
        // Remove quotes and extra whitespace
        $value = trim($value, '" ');
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Parse number from string (handles "10 244 476,26" format)
     */
    private function parseNumber(?string $value): float
    {
        if (empty($value)) return 0;

        // Remove all whitespace (thousand separators)
        $value = preg_replace('/\s+/', '', $value);

        // Remove all non-numeric except comma and dot
        $value = preg_replace('/[^\d,.\-]/', '', $value);

        // Handle comma as decimal separator
        $value = str_replace(',', '.', $value);

        // If multiple dots, keep only last as decimal
        if (substr_count($value, '.') > 1) {
            $parts = explode('.', $value);
            $decimal = array_pop($parts);
            $value = implode('', $parts) . '.' . $decimal;
        }

        return (float) $value;
    }

    /**
     * Parse date from string (DD.MM.YYYY format)
     */
    private function parseDate(?string $value): ?Carbon
    {
        if (empty($value)) return null;

        $value = trim($value);

        // Try DD.MM.YYYY format
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $matches)) {
            return Carbon::createFromDate($matches[3], $matches[2], $matches[1]);
        }

        // Try YYYY-MM-DD format
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
            return Carbon::createFromDate($matches[1], $matches[2], $matches[3]);
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalize name for comparison
     */
    private function normalizeName(?string $name): string
    {
        if (empty($name)) return '';
        // Remove quotes, special chars, lowercase
        $name = mb_strtolower($name);
        $name = preg_replace('/["\'\-\.\,\(\)]+/', '', $name);
        $name = preg_replace('/\s+/', '', $name);
        return $name;
    }
}
