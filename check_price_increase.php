<?php
define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking 14% price increase application...\n\n";

$schedules = \App\Models\PaymentSchedule::where('price_increased_14_percent', true)->take(10)->get();

echo "Schedules with 14% increase (first 10):\n";
echo str_repeat("=", 100) . "\n";
foreach ($schedules as $s) {
    echo sprintf("Date: %-12s | Original: %15s | New: %15s | Note: %s\n",
        $s->tolov_sanasi,
        number_format($s->original_tolov_summasi, 2, '.', ' '),
        number_format($s->tolov_summasi, 2, '.', ' '),
        substr($s->muddat_ozgarish_izoh ?? '', 0, 60)
    );
}

echo "\n" . str_repeat("=", 100) . "\n";
echo sprintf("Total schedules with 14%% increase: %d\n", \App\Models\PaymentSchedule::where('price_increased_14_percent', true)->count());
echo sprintf("Total schedules without increase: %d\n", \App\Models\PaymentSchedule::where('price_increased_14_percent', false)->count());

// Check a contract's schedules
echo "\n" . str_repeat("=", 100) . "\n";
$contract = \App\Models\Contract::first();
if ($contract) {
    echo "Contract: {$contract->shartnoma_raqami}\n";
    echo "Start date: {$contract->boshlanish_sanasi}\n";
    echo "Schedules:\n";
    foreach ($contract->paymentSchedules->take(15) as $s) {
        $indicator = $s->price_increased_14_percent ? " [+14%]" : "";
        echo sprintf("  %s - %s%s\n", $s->tolov_sanasi, number_format($s->tolov_summasi, 0), $indicator);
    }
}
