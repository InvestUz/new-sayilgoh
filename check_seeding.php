<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Database Status Check\n";
echo str_repeat("=", 50) . "\n";
echo "Lots: " . \App\Models\Lot::count() . "\n";
echo "Contracts: " . \App\Models\Contract::count() . "\n";
echo "Tenants: " . \App\Models\Tenant::count() . "\n";
echo "Payment Schedules: " . \App\Models\PaymentSchedule::count() . "\n";
echo "Payments: " . \App\Models\Payment::count() . "\n\n";

// Check CSV file
$csvPath = public_path('dataset/Дўконлар тўғрисида маълумотлар.csv');
if (file_exists($csvPath)) {
    $lines = count(file($csvPath));
    echo "CSV File: Found\n";
    echo "CSV Rows: " . ($lines - 1) . " (excluding header)\n";
} else {
    echo "CSV File: NOT FOUND\n";
}

// Check for incomplete imports
$skipped = \App\Models\Lot::where('holat', 'unknown')->count();
echo "\nPotential Issues:\n";
echo "Lots with unknown status: " . $skipped . "\n";

// Sample contract
$contract = \App\Models\Contract::first();
if ($contract) {
    echo "\nSample Contract:\n";
    echo "Contract #: " . $contract->shartnoma_raqami . "\n";
    echo "Lot: " . $contract->lot_id . "\n";
    echo "Schedules: " . $contract->paymentSchedules->count() . "\n";
}
