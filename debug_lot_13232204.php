<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Lot;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentSchedule;

echo "Investigating LOT: 13232204\n";
echo str_repeat("=", 60) . "\n\n";

// Check if lot exists
$lot = Lot::where('lot_raqami', '13232204')->first();

if (!$lot) {
    echo "❌ LOT 13232204 NOT FOUND in database\n";
    echo "\nLet's check what lots exist in DB:\n";
    $lots = Lot::orderBy('id')->limit(10)->get(['id', 'lot_raqami', 'obyekt_nomi']);
    foreach ($lots as $l) {
        echo "  ID: {$l->id} | LOT: {$l->lot_raqami} | Name: {$l->obyekt_nomi}\n";
    }
    exit;
}

echo "✓ LOT FOUND:\n";
echo "  ID: {$lot->id}\n";
echo "  LOT Number: {$lot->lot_raqami}\n";
echo "  Name: {$lot->obyekt_nomi}\n";
echo "  Address: {$lot->manzil}\n\n";

// Check contract
$contract = $lot->contract;

if (!$contract) {
    echo "❌ NO CONTRACT for this lot\n";
    exit;
}

echo "✓ CONTRACT FOUND:\n";
echo "  ID: {$contract->id}\n";
echo "  Number: {$contract->shartnoma_raqami}\n";
echo "  Start: {$contract->boshlanish_sanasi->format('Y-m-d')}\n";
echo "  Duration: {$contract->shartnoma_muddati} months\n";
echo "  Monthly: " . number_format($contract->oylik_tolovi, 2) . " UZS\n";
echo "  Status: {$contract->holat}\n\n";

// Check schedules
$schedules = $contract->paymentSchedules()->orderBy('oy_raqami')->get();

echo "PAYMENT SCHEDULES: " . $schedules->count() . " schedules\n";
if ($schedules->count() > 0) {
    echo "  First 5 schedules:\n";
    foreach ($schedules->take(5) as $s) {
        echo "    {$s->oy_raqami}. {$s->yil}-" . str_pad($s->oy, 2, '0', STR_PAD_LEFT) .
             " | Payment: " . number_format($s->tolov_summasi, 2) .
             " | Paid: " . number_format($s->tolangan_summa, 2) .
             " | Status: {$s->holat}\n";
    }
} else {
    echo "  ❌ NO SCHEDULES!\n";
}
echo "\n";

// Check payments
$payments = Payment::where('contract_id', $contract->id)->orderBy('tolov_sanasi')->get();

echo "PAYMENTS: " . $payments->count() . " payments\n";
if ($payments->count() > 0) {
    echo "  Payments:\n";
    foreach ($payments as $p) {
        echo "    " . $p->tolov_sanasi->format('Y-m-d') .
             " | Amount: " . number_format($p->summa, 2) . " UZS\n";
    }
} else {
    echo "  ❌ NO PAYMENTS!\n";
}
