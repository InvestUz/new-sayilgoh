<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contract;
use App\Models\Lot;

echo "Checking LOT 18579291...\n";
echo "========================================\n\n";

$lot = Lot::where('lot_raqami', '18579291')->first();

if (!$lot) {
    echo "❌ LOT not found\n";
    exit(1);
}

echo "✓ LOT found: {$lot->lot_raqami}\n";
echo "  Obyekt: {$lot->obyekt_nomi}\n\n";

$contract = $lot->contracts()->first();

if (!$contract) {
    echo "❌ Contract not found for this LOT\n";
    exit(1);
}

echo "✓ Contract found: {$contract->shartnoma_raqami}\n";
echo "  Tenant: {$contract->tenant->name}\n";
echo "  INN: {$contract->tenant->inn}\n";
echo "  Start: {$contract->boshlanish_sanasi->format('d.m.Y')}\n";
echo "  End: {$contract->tugash_sanasi->format('d.m.Y')}\n";
echo "  Duration: {$contract->shartnoma_muddati} months\n\n";

echo "FINANCIAL DATA:\n";
echo "  shartnoma_summasi: " . number_format($contract->shartnoma_summasi, 2) . " UZS\n";
echo "  yillik_ijara_haqi: " . number_format($contract->yillik_ijara_haqi, 2) . " UZS\n";
echo "  oylik_tolovi: " . number_format($contract->oylik_tolovi, 2) . " UZS\n\n";

$expected_monthly = round($contract->yillik_ijara_haqi / 12, 2);
echo "  Expected monthly (annual ÷ 12): " . number_format($expected_monthly, 2) . " UZS\n";
echo "  Match: " . ($expected_monthly == $contract->oylik_tolovi ? "✓ YES" : "✗ NO") . "\n\n";

$schedules = $contract->paymentSchedules;
echo "PAYMENT SCHEDULES:\n";
echo "  Total schedules: {$schedules->count()}\n";

if ($schedules->count() > 0) {
    $first = $schedules->first();
    $last = $schedules->last();
    
    echo "  First schedule:\n";
    echo "    Month: {$first->oy}/{$first->yil}\n";
    echo "    Amount: " . number_format($first->tolov_summasi, 2) . " UZS\n";
    
    echo "  Last schedule:\n";
    echo "    Month: {$last->oy}/{$last->yil}\n";
    echo "    Amount: " . number_format($last->tolov_summasi, 2) . " UZS\n\n";
    
    echo "  All amounts match monthly payment: ";
    $all_match = $schedules->every(fn($s) => round($s->tolov_summasi, 2) == round($contract->oylik_tolovi, 2));
    echo $all_match ? "✓ YES" : "✗ NO";
    echo "\n\n";
}

echo "SUMMARY:\n";
$total_plan = $schedules->sum('tolov_summasi');
echo "  Total plan (all schedules): " . number_format($total_plan, 2) . " UZS\n";

$expected_total = $contract->yillik_ijara_haqi * ($contract->shartnoma_muddati / 12);
echo "  Expected total (annual × years): " . number_format($expected_total, 2) . " UZS\n";
echo "  Difference: " . number_format(abs($total_plan - $expected_total), 2) . " UZS\n\n";

if ($contract->shartnoma_muddati == 60) {
    echo "✓ Contract is 5 years (60 months) as expected\n";
    echo "✓ Annual rent model working correctly\n";
} else {
    echo "⚠ Duration mismatch: expected 60, got {$contract->shartnoma_muddati}\n";
}

echo "\n========================================\n";
echo "CHECK COMPLETE\n";
