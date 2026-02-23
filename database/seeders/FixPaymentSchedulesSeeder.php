<?php
/**
 * Fix Payment Schedules - Recalculate all schedules based on contract amounts
 * This ensures schedules have correct tolov_summasi = contract.shartnoma_summasi / 12
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Contract;
use App\Models\PaymentSchedule;
use Carbon\Carbon;

class FixPaymentSchedulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('╔════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info('║           FIX PAYMENT SCHEDULES - Recalculate All                          ║');
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        $contracts = Contract::where('holat', 'faol')->with('lot')->get();
        $fixed = 0;
        $errors = 0;

        foreach ($contracts as $contract) {
            $lotNumber = $contract->lot ? $contract->lot->lot_raqami : 'N/A';
            $this->command->info("Processing: {$contract->shartnoma_raqami} (Lot: {$lotNumber})");

            try {
                // Delete existing schedules
                PaymentSchedule::where('contract_id', $contract->id)->delete();

                // Calculate correct monthly amount
                $contractSum = $contract->shartnoma_summasi;
                $months = 12; // Standard 12 months
                $monthlyAmount = round($contractSum / $months, 2);

                // Get contract start date
                $startDate = Carbon::parse($contract->boshlanish_sanasi);

                // Create 12 monthly schedules
                for ($i = 0; $i < $months; $i++) {
                    $scheduleDate = $startDate->copy()->addMonths($i);
                    $paymentDay = 20; // Default payment day

                    // Set payment date to 20th of each month
                    $tolovSanasi = Carbon::create(
                        $scheduleDate->year,
                        $scheduleDate->month,
                        min($paymentDay, $scheduleDate->daysInMonth)
                    );

                    // Due date is 10 days after payment date
                    $oxirgiMuddat = $tolovSanasi->copy()->addDays(10);
                    DB::table('payment_schedules')->insert([
                        'contract_id' => $contract->id,
                        'oy_raqami' => $i + 1,
                        'oy' => $tolovSanasi->month,
                        'yil' => $tolovSanasi->year,
                        'tolov_sanasi' => $tolovSanasi,
                        'oxirgi_muddat' => $oxirgiMuddat,
                        'tolov_summasi' => $monthlyAmount,
                        'tolangan_summa' => 0,
                        'qoldiq_summa' => $monthlyAmount,
                        'penya_summasi' => 0,
                        'tolangan_penya' => 0,
                        'kechikish_kunlari' => 0,
                        'holat' => 'kutilmoqda',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->command->info("  ✓ Created 12 schedules, monthly: " . number_format($monthlyAmount, 0) . " UZS");
                $fixed++;

            } catch (\Exception $e) {
                $this->command->error("  ✗ Error: " . $e->getMessage());
                $errors++;
            }
        }

        // Reset contract advance balances
        DB::table('contracts')->update(['avans_balans' => 0]);

        $this->command->info('');
        $this->command->info('╔════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info("║  Fixed: {$fixed} contracts");
        $this->command->info("║  Errors: {$errors}");
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
        $this->command->info('Now run: php artisan db:seed --class=SheraliFactSeeder --force');
    }
}

