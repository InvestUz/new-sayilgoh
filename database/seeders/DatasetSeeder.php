<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Lot;
use App\Models\Tenant;
use App\Models\Contract;
use Carbon\Carbon;

class DatasetSeeder extends Seeder
{
    /**
     * Month mapping from CSV headers to date
     */
    private array $monthMapping = [];

    public function run(): void
    {
        $this->command->info('Starting dataset import from CSV...');

        // Build month mapping
        $this->buildMonthMapping();

        // Read CSV file (Updated to new format)
        $csvPath = public_path('dataset/dataset.csv');
        if (!file_exists($csvPath)) {
            $this->command->error('CSV file not found: ' . $csvPath);
            return;
        }

        // Disable foreign key checks for faster import
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        // Clear existing data
        $this->command->info('Clearing existing data...');
        DB::table('payment_schedules')->delete();
        DB::table('payments')->delete();
        DB::table('contracts')->delete();
        DB::table('lots')->delete();
        DB::table('tenants')->delete();

        // Re-enable foreign key checks
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        // Parse CSV
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->command->error('Cannot open CSV file');
            return;
        }

        // Read header row (may contain newline inside quoted field)
        // Skip lines until we find actual data (header ends when we see numeric first column)
        $header = fgetcsv($handle, 0, ';');
        
        // Check if header was read correctly - first data row should start with number
        $testRow = fgetcsv($handle, 0, ';');
        if (!is_numeric(trim($testRow[0] ?? ''))) {
            // Header spanned multiple lines, this is still header continuation
            // Re-read to skip remaining header
            $this->command->warn('Multi-line header detected, skipping...');
            $testRow = fgetcsv($handle, 0, ';');
        }
        
        // Rewind and skip header properly, then process testRow first
        $firstDataRow = $testRow;

        $rowNumber = 0;
        $imported = 0;
        $skipped = 0;
        $tenantCache = [];

        // Process first data row
        if ($firstDataRow && !empty(array_filter($firstDataRow))) {
            $rowNumber++;
            try {
                $data = $this->parseRow($firstDataRow);
                $lot = $this->createLot($data, $rowNumber);
                
                if (empty($data['winner_name'])) {
                    $lot->update(['holat' => 'bosh']);
                    $imported++;
                } else {
                    $tenantKey = $this->normalizeName($data['winner_name']) . '_' . $this->normalizePhone($data['phone']);
                    if (!isset($tenantCache[$tenantKey])) {
                        $tenant = $this->createTenant($data);
                        $tenantCache[$tenantKey] = $tenant->id;
                    }
                    $tenantId = $tenantCache[$tenantKey];
                    $contract = $this->createContract($data, $lot->id, $tenantId);
                    $this->createPaymentSchedules($contract, $data);
                    $lot->update(['holat' => 'ijarada']);
                    $imported++;
                }
            } catch (\Exception $e) {
                $this->command->warn("Row {$rowNumber}: " . $e->getMessage());
                $skipped++;
            }
        }

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $data = $this->parseRow($row);

                // Create lot first (even without winner)
                $lot = $this->createLot($data, $rowNumber);

                // If no winner name, just create lot without contract
                if (empty($data['winner_name'])) {
                    $lot->update(['holat' => 'bosh']);
                    $imported++;
                    if ($imported % 10 === 0) {
                        $this->command->info("Imported {$imported} records...");
                    }
                    continue;
                }

                // Create or get tenant
                $tenantKey = $this->normalizeName($data['winner_name']) . '_' . $this->normalizePhone($data['phone']);
                if (!isset($tenantCache[$tenantKey])) {
                    $tenant = $this->createTenant($data);
                    $tenantCache[$tenantKey] = $tenant->id;
                }
                $tenantId = $tenantCache[$tenantKey];

                // Create contract
                $contract = $this->createContract($data, $lot->id, $tenantId);

                // Create payment schedules from monthly data
                $this->createPaymentSchedules($contract, $data);

                // Update lot status
                $lot->update(['holat' => 'ijarada']);

                $imported++;

                if ($imported % 10 === 0) {
                    $this->command->info("Imported {$imported} records...");
                }

            } catch (\Exception $e) {
                $this->command->warn("Row {$rowNumber}: " . $e->getMessage());
                $skipped++;
            }
        }

        fclose($handle);

        $this->command->info("Import completed!");
        $this->command->info("Imported: {$imported} contracts");
        $this->command->info("Skipped: {$skipped} rows");
    }

    /**
     * Build month mapping from Uzbek/Russian month names to dates
     */
    private function buildMonthMapping(): void
    {
        $monthNames = [
            'янв' => 1, 'фев' => 2, 'март' => 3, 'апр' => 4,
            'май' => 5, 'июнь' => 6, 'июль' => 7, 'авг' => 8,
            'сен' => 9, 'окт' => 10, 'ноя' => 11, 'дек' => 12
        ];

        // Generate mapping for columns 18-62+ (2024 дек through 2029 дек)
        // Format in CSV: "2024 дек", "2025 янв", etc.
        for ($year = 2024; $year <= 2029; $year++) {
            foreach ($monthNames as $monthName => $monthNum) {
                $key = "{$year} {$monthName}";
                $this->monthMapping[$key] = [
                    'year' => $year,
                    'month' => $monthNum
                ];
            }
        }
    }

    /**
     * Parse a row from CSV (Updated for Bazaga_poytaxt_ijara_ro`yxat_80_upg.csv)
     *
     * Column mapping (0-based index):
     * 0: Т/р, 1: Лотрақами, 2: Obyekt Xolati, 3: Туман, 4: Geo Lokatsiya
     * 5: Ер манзили, 6: Maydon, 7: Faoliyat turi, 8: Boshlang'ich narx
     * 9: Auksion sanasi, 10: Sotilgan narx, 11: (empty), 12: subyekt turi
     * 13: Bank, 14: Hisob raqami, 15: MFO, 16: Passport, 17: INN/PINFL
     * 18: G'olib nomi, 19: G'olib manzili, 20: Telefon, 21: Shartnoma holati
     * 22: Sana, 23: Shartnoma raqami, 24: G'olib to'lovi, 25: Auksion xarajati
     * 26: Shartnoma summasi, 27+: Oylik to'lovlar
     */
    private function parseRow(array $row): array
    {
        return [
            'row_number' => $this->cleanValue($row[0] ?? ''),
            'lot_number' => $this->cleanValue($row[1] ?? ''),
            'obyekt_holati' => $this->cleanValue($row[2] ?? ''),
            'district' => $this->cleanValue($row[3] ?? ''),
            'geo_location' => $this->cleanValue($row[4] ?? ''),
            'address' => $this->cleanValue($row[5] ?? ''),
            'area' => $this->parseNumber($row[6] ?? ''),
            'faoliyat_turi' => $this->cleanValue($row[7] ?? ''),
            'starting_price' => $this->parseNumber($row[8] ?? ''),
            'auction_date' => $this->parseDate($row[9] ?? ''),
            'sold_price' => $this->parseNumber($row[10] ?? ''),
            'subject_type' => $this->cleanValue($row[12] ?? ''),
            'bank' => $this->cleanValue($row[13] ?? ''),
            'hisob_raqami' => $this->cleanValue($row[14] ?? ''),
            'mfo' => $this->cleanValue($row[15] ?? ''),
            'passport' => $this->cleanValue($row[16] ?? ''),
            'inn_pinfl' => $this->cleanValue($row[17] ?? ''),
            'winner_name' => $this->cleanValue($row[18] ?? ''),
            'winner_address' => $this->cleanValue($row[19] ?? ''),
            'phone' => $this->cleanValue($row[20] ?? ''),
            'contract_status' => $this->cleanValue($row[21] ?? ''),
            'contract_date' => $this->parseDate($row[22] ?? ''),
            'contract_number' => $this->cleanValue($row[23] ?? ''),
            'winner_payment' => $this->parseNumber($row[24] ?? ''),
            'auction_fee' => $this->parseNumber($row[25] ?? ''),
            'contract_amount' => $this->parseNumber($row[26] ?? ''),
            'monthly_payments' => $this->parseMonthlyPayments($row, 27),
        ];
    }

    /**
     * Parse monthly payment columns
     * @param array $row CSV row data
     * @param int $startColumn Column index where monthly payments start (default 27)
     */
    private function parseMonthlyPayments(array $row, int $startColumn = 27): array
    {
        $payments = [];
        $columnIndex = $startColumn;

        // Define month order as they appear in CSV
        $monthOrder = [
            ['year' => 2024, 'month' => 12], // 2024 дек
        ];

        // Add 2025 months
        for ($m = 1; $m <= 12; $m++) {
            $monthOrder[] = ['year' => 2025, 'month' => $m];
        }
        // Add 2026 months
        for ($m = 1; $m <= 12; $m++) {
            $monthOrder[] = ['year' => 2026, 'month' => $m];
        }
        // Add 2027 months
        for ($m = 1; $m <= 12; $m++) {
            $monthOrder[] = ['year' => 2027, 'month' => $m];
        }
        // Add 2028 months
        for ($m = 1; $m <= 12; $m++) {
            $monthOrder[] = ['year' => 2028, 'month' => $m];
        }
        // Add 2029 months
        for ($m = 1; $m <= 12; $m++) {
            $monthOrder[] = ['year' => 2029, 'month' => $m];
        }

        foreach ($monthOrder as $index => $period) {
            $colIdx = $columnIndex + $index;
            if (isset($row[$colIdx])) {
                $amount = $this->parseNumber($row[$colIdx]);
                if ($amount > 0) {
                    $payments[] = [
                        'year' => $period['year'],
                        'month' => $period['month'],
                        'amount' => $amount,
                    ];
                }
            }
        }

        return $payments;
    }

    /**
     * Create tenant from data
     */
    private function createTenant(array $data): Tenant
    {
        $type = 'yuridik';
        $subjectType = mb_strtolower($data['subject_type']);
        if (strpos($subjectType, 'жисмоний') !== false || strpos($subjectType, 'jismoniy') !== false) {
            $type = 'jismoniy';
        }

        $name = $this->cleanName($data['winner_name']);
        $phone = $this->normalizePhone($data['phone']);

        // Use INN/PINFL from CSV (real data)
        $inn = $this->cleanValue($data['inn_pinfl'] ?? '');

        // Clean INN - handle scientific notation with comma decimal (5,3007E+13 -> 53007...)
        // and regular numbers with comma decimal (41801850060069,00 -> 41801850060069)
        // First replace comma with dot for proper float conversion
        $inn = str_replace(',', '.', $inn);
        if (strpos($inn, 'E+') !== false || strpos($inn, 'e+') !== false) {
            // Scientific notation: convert to integer string
            $inn = number_format((float)$inn, 0, '', '');
        } elseif (strpos($inn, '.') !== false) {
            // Regular decimal number: convert to integer (truncate decimal part)
            $inn = number_format((float)$inn, 0, '', '');
        }
        $inn = preg_replace('/[^0-9]/', '', $inn);

        // If no INN provided, generate from phone
        if (empty($inn)) {
            $innBase = preg_replace('/[^0-9]/', '', $phone);
            $inn = str_pad(substr($innBase, 0, 9), 9, '0', STR_PAD_LEFT);
        }

        // Check if INN exists, make it unique
        $existingCount = Tenant::where('inn', $inn)->count();
        if ($existingCount > 0) {
            $inn = $inn . '_' . ($existingCount + 1);
        }

        // Use winner_address if available, otherwise use lot address
        $tenantAddress = !empty($data['winner_address']) ? $data['winner_address'] : $data['address'];

        // Convert bank_account from scientific notation (2,0208E+19 -> 20208000000000000000)
        $bankAccount = $this->cleanValue($data['hisob_raqami'] ?? '');
        $bankAccount = str_replace(',', '.', $bankAccount);
        if (strpos($bankAccount, 'E+') !== false || strpos($bankAccount, 'e+') !== false) {
            $bankAccount = number_format((float)$bankAccount, 0, '', '');
        }

        return Tenant::create([
            'name' => $name,
            'type' => $type,
            'inn' => $inn,
            'director_name' => $type === 'jismoniy' ? $name : null,
            'phone' => $phone,
            'address' => $tenantAddress ?: '-',
            'bank_name' => $this->cleanValue($data['bank'] ?? ''),
            'bank_account' => $bankAccount ?: null,
            'bank_mfo' => $this->cleanValue($data['mfo'] ?? ''),
            'is_active' => true,
        ]);
    }

    /**
     * Create lot from data
     */
    private function createLot(array $data, int $rowNumber): Lot
    {
        // Use lot number from CSV, or extract house number from address, or use row number
        $lotNumber = $data['lot_number'];
        
        // Clean lot number - remove commas and extra characters
        $lotNumber = str_replace(',', '', $lotNumber);
        $lotNumber = preg_replace('/[^\d\-\/]/', '', $lotNumber);

        if (empty($lotNumber)) {
            // Try to extract house number from address (e.g., "40/19" from "Охангарон Шох кучаси 40/19")
            $houseNumber = $this->extractHouseNumber($data['address']);
            if ($houseNumber) {
                $lotNumber = $houseNumber;
            } else {
                // Last resort: use row number
                $lotNumber = (string) $rowNumber;
            }
        }

        // Ensure unique lot number
        $originalLotNumber = $lotNumber;
        $counter = 1;
        while (Lot::where('lot_raqami', $lotNumber)->exists()) {
            $lotNumber = $originalLotNumber . '-' . $counter;
            $counter++;
        }

        // Parse address
        $address = $data['address'] ?: '-';
        $district = $data['district'] ?: 'Yashnaobod tumani';

        return Lot::create([
            'lot_raqami' => $lotNumber,
            'obyekt_nomi' => $address,
            'manzil' => $address,
            'tuman' => $district,
            'kocha' => $this->extractStreet($address),
            'uy_raqami' => $this->extractHouseNumber($address),
            'maydon' => $data['area'] ?: 100,
            'tavsif' => $data['faoliyat_turi'] ?? null,
            'obyekt_turi' => 'savdo',
            'map_url' => $data['geo_location'] ?? null,
            'boshlangich_narx' => $data['starting_price'] ?: 0,
            'holat' => !empty($data['obyekt_holati']) && stripos($data['obyekt_holati'], 'ijara') !== false ? 'ijarada' : 'bosh',
            'is_active' => true,
        ]);
    }

    /**
     * Create contract from data
     */
    private function createContract(array $data, int $lotId, int $tenantId): Contract
    {
        // Parse dates
        $auctionDate = $data['auction_date'] ?: now();
        $contractDate = $data['contract_date'] ?: $auctionDate;

        // Determine contract duration (12 months by default)
        $monthlyPayments = $data['monthly_payments'];
        $contractDuration = count($monthlyPayments) > 0 ? count($monthlyPayments) : 12;

        // Calculate total contract amount
        $contractAmount = $data['sold_price'] ?: $data['contract_amount'];
        if (!$contractAmount && count($monthlyPayments) > 0) {
            $contractAmount = array_sum(array_column($monthlyPayments, 'amount'));
        }
        $contractAmount = $contractAmount ?: ($data['starting_price'] ?: 100000000);

        // Monthly payment
        $monthlyPayment = $contractDuration > 0 ? $contractAmount / $contractDuration : $contractAmount;

        // Calculate dates
        $startDate = Carbon::parse($contractDate);
        $endDate = $startDate->copy()->addMonths($contractDuration);

        // First payment date (10 working days after contract)
        $firstPaymentDate = $this->calculate10WorkingDays($startDate);

        // Contract number
        $contractNumber = $data['contract_number'];
        if (!$contractNumber || !is_numeric(str_replace([' ', '-'], '', $contractNumber))) {
            $contractNumber = 'SH-' . date('Y') . '-' . str_pad(Contract::count() + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $contractNumber = 'SH-' . $contractNumber;
        }

        // Ensure unique contract number
        $originalNumber = $contractNumber;
        $counter = 1;
        while (Contract::where('shartnoma_raqami', $contractNumber)->exists()) {
            $contractNumber = $originalNumber . '-' . $counter;
            $counter++;
        }

        return Contract::create([
            'lot_id' => $lotId,
            'tenant_id' => $tenantId,
            'shartnoma_raqami' => $contractNumber,
            'shartnoma_sanasi' => $contractDate,
            'auksion_sanasi' => $auctionDate,
            'auksion_xarajati' => $data['auction_fee'] ?: ($contractAmount * 0.01),
            'shartnoma_summasi' => $contractAmount,
            'oylik_tolovi' => $monthlyPayment,
            'shartnoma_muddati' => $contractDuration,
            'boshlanish_sanasi' => $startDate,
            'tugash_sanasi' => $endDate,
            'birinchi_tolov_sanasi' => $firstPaymentDate,
            'holat' => $data['contract_status'] === 'Шартнома тузилмаган' ? 'bekor_qilingan' : 'faol',
            'dalolatnoma_holati' => 'topshirilgan',
        ]);
    }

    /**
     * Create payment schedules from monthly data
     *
     * QARZ HISOBLASH:
     * - MUDDATI O'TGAN: oxirgi_muddat < bugun (past-due debt)
     * - MUDDATI O'TMAGAN: oxirgi_muddat >= bugun (not yet due)
     * - PENYA: 0.4% per day, max 50%
     */
    private function createPaymentSchedules(Contract $contract, array $data): void
    {
        $monthlyPayments = $data['monthly_payments'];
        $bugun = Carbon::today();

        if (empty($monthlyPayments)) {
            // Generate default schedule
            $contract->generatePaymentSchedule();
            return;
        }

        $schedules = [];
        $oyRaqami = 1;

        foreach ($monthlyPayments as $payment) {
            $paymentDate = Carbon::createFromDate($payment['year'], $payment['month'], 10);
            $oxirgiMuddat = $paymentDate->copy()->addDays(10);

            // Determine correct status based on deadline date
            if ($oxirgiMuddat->lt($bugun)) {
                // Past due - this is REAL debt
                $holat = 'tolanmagan';
                $kechikishKunlari = $oxirgiMuddat->diffInDays($bugun);
                // Calculate penalty: 0.4% per day, max 50%
                $penya = $payment['amount'] * 0.004 * $kechikishKunlari;
                $maxPenya = $payment['amount'] * 0.5;
                $penya = min($penya, $maxPenya);
            } else {
                // Not yet due
                $holat = 'kutilmoqda';
                $kechikishKunlari = 0;
                $penya = 0;
            }

            $schedules[] = [
                'contract_id' => $contract->id,
                'oy_raqami' => $oyRaqami,
                'yil' => $payment['year'],
                'oy' => $payment['month'],
                'tolov_sanasi' => $paymentDate->format('Y-m-d'),
                'oxirgi_muddat' => $oxirgiMuddat->format('Y-m-d'),
                'tolov_summasi' => $payment['amount'],
                'tolangan_summa' => 0,
                'qoldiq_summa' => $payment['amount'],
                'penya_summasi' => $penya,
                'tolangan_penya' => 0,
                'kechikish_kunlari' => $kechikishKunlari,
                'holat' => $holat,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $oyRaqami++;
        }

        // Use chunk insert for performance
        foreach (array_chunk($schedules, 100) as $chunk) {
            DB::table('payment_schedules')->insert($chunk);
        }
    }

    /**
     * Helper: Clean value
     */
    private function cleanValue(?string $value): string
    {
        if ($value === null) return '';
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Helper: Parse number from string
     * Handles: "10 244 476,26" (space as thousand separator, comma as decimal)
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
     * Helper: Parse date
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
     * Helper: Normalize phone number
     */
    private function normalizePhone(?string $phone): string
    {
        if (empty($phone)) return '+998900000000';

        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Ensure 12 digits for Uzbekistan
        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        } elseif (strlen($digits) < 12) {
            $digits = str_pad($digits, 12, '0', STR_PAD_LEFT);
        }

        return '+' . substr($digits, 0, 12);
    }

    /**
     * Helper: Normalize name for caching
     */
    private function normalizeName(?string $name): string
    {
        return mb_strtolower(preg_replace('/[^а-яА-Яa-zA-Z0-9]/u', '', $name ?? ''));
    }

    /**
     * Helper: Clean company/person name
     */
    private function cleanName(?string $name): string
    {
        if (empty($name)) return 'Nomalum';

        // Remove extra quotes and clean up
        $name = trim($name, '" ');
        $name = str_replace(['""', '«', '»'], ['"', '"', '"'], $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return $name;
    }

    /**
     * Helper: Extract street from address
     */
    private function extractStreet(string $address): ?string
    {
        // Pattern: "Ko'cha nomi" before number
        if (preg_match('/^(.+?)\s+\d+/', $address, $matches)) {
            return trim($matches[1]);
        }
        return $address;
    }

    /**
     * Helper: Extract house number from address
     */
    private function extractHouseNumber(string $address): ?string
    {
        // Pattern: digits and optional /letter at end
        if (preg_match('/(\d+(?:\/\d+)?)(?:\s|$)/', $address, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Calculate 10 working days from date
     */
    private function calculate10WorkingDays(Carbon $startDate): Carbon
    {
        $date = $startDate->copy();
        $workingDays = 0;

        while ($workingDays < 10) {
            $date->addDay();
            if (!$date->isWeekend()) {
                $workingDays++;
            }
        }

        return $date;
    }
}
