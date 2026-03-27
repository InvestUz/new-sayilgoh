<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\Carbon;

class SayilgohFaktSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('IMPORTING ACTUAL PAYMENT RECEIPTS');

        $csvPath = public_path('dataset/sayilgoh_fakt_cv.csv');

        if (!file_exists($csvPath)) {
            $this->command->error('CSV file not found');
            return;
        }

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle, 0, ';'); // Skip header

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (empty(array_filter($row))) continue;

            try {
                $paymentDate = trim($row[0] ?? '');
                $amount = $this->parseAmount($row[3] ?? '0');
                $lotNumber = trim($row[5] ?? '');

                if (empty($paymentDate) || $amount <= 0 || empty($lotNumber)) {
                    $skipped++;
                    continue;
                }

                $date = Carbon::createFromFormat('d.m.Y H:i', $paymentDate);

                $lot = \App\Models\Lot::where('lot_raqami', $lotNumber)->first();
                if (!$lot) {
                    $skipped++;
                    continue;
                }

                $contract = $lot->contracts()->first();
                if (!$contract) {
                    $skipped++;
                    continue;
                }

                $exists = Payment::where('contract_id', $contract->id)
                    ->where('to_lov_summasi', $amount)
                    ->where('to_lov_sanasi', $date)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Payment::create([
                    'contract_id' => $contract->id,
                    'to_lov_summasi' => $amount,
                    'to_lov_sanasi' => $date,
                    'holat' => 'tasdiqlangan',
                    'izoh' => 'Imported from receipts',
                ]);

                $imported++;

            } catch (\Exception $e) {
                $skipped++;
            }
        }

        fclose($handle);

        $this->command->info("✓ Imported: " . $imported);
        $this->command->info("✗ Skipped: " . $skipped);
    }

    private function parseAmount($amount): float
    {
        $amount = str_replace(' ', '', trim($amount));
        $amount = str_replace(',', '.', $amount);
        return (float) $amount;
    }
}
