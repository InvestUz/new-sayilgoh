<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Lot;
use App\Models\Tenant;
use App\Models\Contract;
use Carbon\Carbon;

class DokonlarSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('DOKONLAR CSV IMPORT - Production Mode');
        $this->command->info('');

        $csvPath = public_path('dataset/Дўконлар тўғрисида маълумотлар.csv');
        if (!file_exists($csvPath)) {
            $originalPath = public_path('dataset/Дўконлар тўғрисида маълумотлар.csv');
            if (file_exists($originalPath)) {
                copy($originalPath, $csvPath);
                $this->command->info('Copied original file to Дўконлар тўғрисида маълумотлар.csv');
            }
        }

        if (!file_exists($csvPath)) {
            $this->command->error('CSV file not found!');
            return;
        }

        $bugun = Carbon::today();
        $this->command->info("Today: " . $bugun->format('d.m.Y'));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->command->info('Clearing existing data...');
        DB::table('payments')->delete();
        DB::table('payment_schedules')->delete();
        DB::table('contracts')->delete();
        DB::table('lots')->delete();
        DB::table('tenants')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->command->error('Cannot open CSV file');
            return;
        }

        fgetcsv($handle, 0, ';');

        $rowNumber = 0;
        $imported = 0;
        $skipped = 0;
        $tenantCache = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;

            if (empty(array_filter($row))) continue;

            try {
                $data = $this->parseRow($row);

                if (empty($data['lot_number'])) {
                    $skipped++;
                    continue;
                }

                if (empty($data['winner_name'])) {
                    $skipped++;
                    continue;
                }

                $lot = $this->createLot($data, $rowNumber);

                $tenantKey = $this->normalizeName($data['winner_name']) . '_' . $data['inn'];
                if (!isset($tenantCache[$tenantKey])) {
                    $tenant = $this->createTenant($data);
                    $tenantCache[$tenantKey] = $tenant->id;
                }
                $tenantId = $tenantCache[$tenantKey];

                $contract = $this->createContract($data, $lot->id, $tenantId);
                $this->createPaymentSchedules($contract, $bugun);

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

        $this->command->info('');
        $this->command->info("Imported: {$imported} contracts");
        $this->command->info("Skipped: {$skipped} rows");
        $this->command->info('');
        $this->command->info('Now run: php artisan db:seed --class=SheraliFactSeeder --force');
    }

    private function parseRow(array $row): array
    {
        $lotNumber = trim($row[4] ?? '');
        $lotNumber = preg_replace('/[^\d]/', '', $lotNumber);

        // CSV columns:
        // 0: № (row number)
        // 1: Дўкон номерлари (shop number)
        // 2: Ауксион ғолиби (winner name)
        // 3: ИНН (INN)
        // 4: ЛОТ рақами (lot number)
        // 5: Шартнома тузилган сана (contract date)
        // 6: Шартноманинг амал қилиш муддати (contract END date)
        // 7: Шартнома номери № (contract number)
        // 8: Шартнома суммаси (contract amount)
        // 9: Телефон номерлари (phone)

        return [
            'row_number' => trim($row[0] ?? ''),
            'shop_number' => trim($row[1] ?? ''),
            'winner_name' => trim($row[2] ?? ''),
            'inn' => $this->parseInn($row[3] ?? ''),
            'lot_number' => $lotNumber,
            'contract_date' => $this->parseDate($row[5] ?? ''),
            'contract_end_date' => $this->parseDate($row[6] ?? ''),
            'contract_number' => trim($row[7] ?? ''),
            'contract_amount' => $this->parseNumber($row[8] ?? ''),
            'phone' => $this->normalizePhone($row[9] ?? ''),
        ];
    }

    private function parseInn(?string $value): string
    {
        if (empty($value)) return '';

        $value = trim($value);

        // Handle scientific notation (e.g., "5.30070E+13")
        if (stripos($value, 'E+') !== false || stripos($value, 'E-') !== false) {
            $value = sprintf('%.0f', (float)$value);
        }

        // Replace comma with dot for decimal
        $value = str_replace(',', '.', $value);

        // If it has decimal point, convert to integer
        if (strpos($value, '.') !== false) {
            $value = number_format((float)$value, 0, '', '');
        }

        // Keep only digits
        $inn = preg_replace('/[^0-9]/', '', $value);

        return $inn;
    }

    private function createTenant(array $data): Tenant
    {
        $name = trim($data['winner_name']);

        // If name is empty, skip (should not create fake data)
        if (empty($name)) {
            throw new \Exception('Tenant name is required');
        }

        $type = 'yuridik';
        $lowerName = mb_strtolower($name);
        if (preg_match('/\b(o[\'`]?g[\'`]?li|qizi|ovich|ovna|evna|evich)\b/ui', $lowerName)) {
            $type = 'jismoniy';
        }

        $inn = $data['inn'];

        // If INN is empty or too short, use a placeholder but log a warning
        if (empty($inn) || strlen($inn) < 9) {
            // Generate a unique placeholder INN
            $inn = '999' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $this->command->warn("Missing/invalid INN for: {$name}, using placeholder: {$inn}");
        }

        // Ensure uniqueness
        $originalInn = $inn;
        $counter = 1;
        while (Tenant::where('inn', $inn)->exists()) {
            $inn = $originalInn . '_' . $counter;
            $counter++;
        }

        return Tenant::create([
            'name' => $name,
            'type' => $type,
            'inn' => $inn,
            'director_name' => $type === 'jismoniy' ? $name : null,
            'phone' => $data['phone'],
            'address' => 'Toshkent shahri',
            'is_active' => true,
        ]);
    }

    private function createLot(array $data, int $rowNumber): Lot
    {
        $lotNumber = $data['lot_number'];

        if (empty($lotNumber)) {
            throw new \Exception("Lot number is required for row {$rowNumber}");
        }

        // Ensure uniqueness
        $originalLot = $lotNumber;
        $counter = 1;
        while (Lot::where('lot_raqami', $lotNumber)->exists()) {
            $lotNumber = $originalLot . '-' . $counter;
            $counter++;
        }

        $shopNumber = $data['shop_number'] ?: $rowNumber;

        return Lot::create([
            'lot_raqami' => $lotNumber,
            'obyekt_nomi' => "Охангарон Шох кучаси 40/{$shopNumber}",
            'manzil' => "Яшнобод тумани, Охангарон Шох кучаси, №40/{$shopNumber}",
            'tuman' => 'Яшнобод тумани',
            'kocha' => 'Охангарон Шох кучаси',
            'uy_raqami' => "40/{$shopNumber}",
            'maydon' => 157.5,
            'obyekt_turi' => 'savdo',
            'boshlangich_narx' => $data['contract_amount'] > 0 ? $data['contract_amount'] : 100000000,
            'holat' => 'bosh',
            'is_active' => true,
        ]);
    }

    private function createContract(array $data, int $lotId, int $tenantId): Contract
    {
        $contractDate = $data['contract_date'] ?: Carbon::now();
        $contractAmount = $data['contract_amount'];

        if ($contractAmount <= 0) {
            throw new \Exception("Contract amount is required and must be greater than 0");
        }

        // Use actual end date from CSV if available, otherwise calculate
        $startDate = Carbon::parse($contractDate);
        $endDate = $data['contract_end_date'] ?: $startDate->copy()->addMonths(12);

        // Calculate duration in months from actual dates
        $duration = $startDate->diffInMonths($endDate);
        if ($duration < 1) $duration = 12; // Minimum 12 months

        // ANNUAL RENT MODEL: CSV amount is annual rent, monthly = annual ÷ 12
        $annualRent = $contractAmount;
        $monthlyPayment = round($annualRent / 12, 2);

        $contractNumber = $data['contract_number'];
        if (empty($contractNumber) || !is_numeric($contractNumber)) {
            $contractNumber = Contract::count() + 1;
        }
        $contractNumber = 'SH-' . $contractNumber;

        $original = $contractNumber;
        $counter = 1;
        while (Contract::where('shartnoma_raqami', $contractNumber)->exists()) {
            $contractNumber = $original . '-' . $counter;
            $counter++;
        }

        // Check if contract is expired (end date < today)
        $bugun = Carbon::today();
        $isExpired = Carbon::parse($endDate)->lt($bugun);
        $status = $isExpired ? 'tugagan' : 'faol';

        if ($isExpired) {
            $this->command->warn("Contract {$contractNumber} is EXPIRED (end date: " . Carbon::parse($endDate)->format('d.m.Y') . ")");
        }

        return Contract::create([
            'lot_id' => $lotId,
            'tenant_id' => $tenantId,
            'shartnoma_raqami' => $contractNumber,
            'shartnoma_sanasi' => $contractDate,
            'auksion_sanasi' => $contractDate,
            'shartnoma_summasi' => $annualRent,
            'yillik_ijara_haqi' => $annualRent,
            'oylik_tolovi' => $monthlyPayment,
            'shartnoma_muddati' => $duration,
            'boshlanish_sanasi' => $startDate,
            'tugash_sanasi' => $endDate,
            'birinchi_tolov_sanasi' => $startDate->copy()->addDays(10),
            'holat' => $status,
            'dalolatnoma_holati' => 'topshirilgan',
        ]);
    }

    private function createPaymentSchedules(Contract $contract, Carbon $bugun): void
    {
        $monthlyPayment = $contract->oylik_tolovi;
        $startDate = Carbon::parse($contract->boshlanish_sanasi);
        $duration = $contract->shartnoma_muddati;

        // Check if contract is expired
        $isContractExpired = $contract->holat === 'tugagan';

        $schedules = [];

        for ($i = 0; $i < $duration; $i++) {
            $paymentDate = $startDate->copy()->addMonths($i);

            $tolovSanasi = Carbon::create(
                $paymentDate->year,
                $paymentDate->month,
                min(10, $paymentDate->daysInMonth)
            );

            // Deadline is same as payment date (10th of month)
            $oxirgiMuddat = $tolovSanasi->copy();

            // EXPIRED CONTRACT RULE: Don't calculate penalty for expired contracts
            if ($isContractExpired) {
                // All schedules for expired contracts are marked as 'tolanmagan' with NO penalty
                $holat = 'tolanmagan';
                $kechikishKunlari = 0;
                $penya = 0;
            } elseif ($oxirgiMuddat->lt($bugun)) {
                $holat = 'tolanmagan';
                $kechikishKunlari = $oxirgiMuddat->diffInDays($bugun);
                $penya = $monthlyPayment * 0.0004 * $kechikishKunlari;
                $maxPenya = $monthlyPayment * 0.5;
                $penya = min($penya, $maxPenya);
            } else {
                $holat = 'kutilmoqda';
                $kechikishKunlari = 0;
                $penya = 0;
            }

            $schedules[] = [
                'contract_id' => $contract->id,
                'oy_raqami' => $i + 1,
                'oy' => $tolovSanasi->month,
                'yil' => $tolovSanasi->year,
                'tolov_sanasi' => $tolovSanasi->format('Y-m-d'),
                'oxirgi_muddat' => $oxirgiMuddat->format('Y-m-d'),
                'tolov_summasi' => $monthlyPayment,
                'tolangan_summa' => 0,
                'qoldiq_summa' => $monthlyPayment,
                'penya_summasi' => $penya,
                'tolangan_penya' => 0,
                'kechikish_kunlari' => $kechikishKunlari,
                'holat' => $holat,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('payment_schedules')->insert($schedules);
    }

    private function parseNumber(?string $value): float
    {
        if (empty($value)) return 0;
        $value = preg_replace('/\s+/', '', $value);
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (empty($value)) return null;
        $value = trim($value);
        $value = str_replace(',', '.', $value);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $matches)) {
            return Carbon::createFromDate($matches[3], $matches[2], $matches[1]);
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            // Return empty string instead of fake number
            $this->command->warn("Missing phone number - using empty value");
            return '';
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        }

        if (strlen($digits) < 12) {
            $this->command->warn("Invalid phone number: {$phone}");
            return '';
        }

        return '+' . substr($digits, 0, 12);
    }

    private function normalizeName(?string $name): string
    {
        return mb_strtolower(preg_replace('/[^а-яА-Яa-zA-Z0-9]/u', '', $name ?? ''));
    }
}
