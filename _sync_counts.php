<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\PaymentSchedule;
use Carbon\Carbon;

echo "=== PAYMENT BREAKDOWN ===\n";
foreach (Payment::selectRaw('holat, COUNT(*) c, SUM(summa) s')->groupBy('holat')->get() as $r) {
    echo sprintf("  %-14s : %4d rows | sum=%s\n", $r->holat, $r->c, number_format((float)$r->s));
}

$tests = Payment::where('hujjat_raqami', 'TEST-DUP-001')->orWhere('izoh', 'like', '%TEST-DUP%')->get();
echo "\nTest payments found: " . $tests->count() . "\n";
foreach ($tests as $t) {
    echo sprintf("  #%d | %s | %s | summa=%s | holat=%s | hujjat=%s\n",
        $t->id, $t->tolov_sanasi->format('Y-m-d'), $t->tolov_raqami,
        number_format($t->summa), $t->holat, $t->hujjat_raqami ?? '-');
}

echo "\n=== DELETING TEST PAYMENTS (hard delete) ===\n";
$deleted = 0;
foreach ($tests as $t) {
    $t->delete();
    $deleted++;
}
echo "Hard-deleted: $deleted\n";

echo "\n=== POST-DELETE COUNTS ===\n";
$bugun = Carbon::now();
$totalPayments = Payment::count();
$overdue = PaymentSchedule::where('qoldiq_summa', '>', 0)
    ->where('tolov_sanasi', '<', $bugun)
    ->count();
echo "  To'lovlar (barchasi) : $totalPayments\n";
echo "  Kechikkan (schedule) : $overdue\n";
