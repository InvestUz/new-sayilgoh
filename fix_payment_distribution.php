<?php
/**
 * Скрипт для исправления распределения платежей по договору п. 6.6
 *
 * Порядок списания:
 * 1. Пеня и штрафы
 * 2. Просроченная аренда
 * 3. Текущая аренда
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Контракт SH-62
$contract = Contract::with(['paymentSchedules', 'payments'])
    ->where('shartnoma_raqami', 'SH-62')
    ->first();

if (!$contract) {
    echo "Contract not found\n";
    exit;
}

echo "=== ИСПРАВЛЕНИЕ РАСПРЕДЕЛЕНИЯ ПЛАТЕЖЕЙ ===\n";
echo "Контракт: {$contract->shartnoma_raqami}\n\n";

// Сбросить все платежи и пересчитать
DB::beginTransaction();

try {
    // 1. Сбросить все schedules
    foreach ($contract->paymentSchedules as $schedule) {
        $schedule->tolangan_summa = 0;
        $schedule->qoldiq_summa = $schedule->tolov_summasi;
        $schedule->tolangan_penya = 0;
        $schedule->penya_summasi = 0;
        $schedule->kechikish_kunlari = 0;
        $schedule->holat = 'kutilmoqda';
        $schedule->save();
    }

    echo "✓ Schedules сброшены\n";

    // 2. Перераспределить каждый платёж
    foreach ($contract->payments()->orderBy('tolov_sanasi')->get() as $payment) {
        echo "\n--- Платёж #{$payment->id} от {$payment->tolov_sanasi} ---\n";
        echo "Сумма: " . number_format($payment->summa, 0) . "\n";

        $tolovSanasi = Carbon::parse($payment->tolov_sanasi);
        $qoldiqSumma = (float) $payment->summa;
        $penyaUchun = 0;
        $asosiyQarz = 0;

        // Получить просроченные месяцы на дату платежа (using effective deadline)
        $schedules = $contract->paymentSchedules()
            ->whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) < ?', [$tolovSanasi])
            ->orderBy('oy_raqami')
            ->get();

        echo "Просроченных месяцев: " . $schedules->count() . "\n";

        foreach ($schedules as $schedule) {
            if ($qoldiqSumma <= 0) break;

            // Use effective deadline
            $effectiveDeadline = $schedule->custom_oxirgi_muddat ?? $schedule->oxirgi_muddat;
            echo "\n  Oy {$schedule->oy_raqami} (muddat: {$effectiveDeadline}):\n";

            // Рассчитать пеню на дату платежа using effective deadline
            $muddat = Carbon::parse($effectiveDeadline);
            $kunlar = $muddat->diffInDays($tolovSanasi);
            $qoldiqQarz = (float) $schedule->qoldiq_summa;

            // Пеня = qoldiq × 0.04% × дни (макс 50%)
            $penya = $qoldiqQarz * 0.0004 * $kunlar;
            $maxPenya = $qoldiqQarz * 0.5;
            $penya = min($penya, $maxPenya);

            $schedule->penya_summasi = $penya;
            $schedule->kechikish_kunlari = $kunlar;

            echo "    Qoldiq: " . number_format($qoldiqQarz, 0) . "\n";
            echo "    Kunlar: {$kunlar}\n";
            echo "    Penya: " . number_format($penya, 0) . "\n";

            // 1. Сначала гасим пеню (п. 6.6)
            $qoldiqPenya = $penya - $schedule->tolangan_penya;
            if ($qoldiqPenya > 0 && $qoldiqSumma > 0) {
                $penyaTolov = min($qoldiqPenya, $qoldiqSumma);
                $schedule->tolangan_penya += $penyaTolov;
                $penyaUchun += $penyaTolov;
                $qoldiqSumma -= $penyaTolov;
                echo "    → На пеню: " . number_format($penyaTolov, 0) . "\n";
            }

            // 2. Затем гасим долг
            if ($schedule->qoldiq_summa > 0 && $qoldiqSumma > 0) {
                $asosiyTolov = min($schedule->qoldiq_summa, $qoldiqSumma);
                $schedule->tolangan_summa += $asosiyTolov;
                $schedule->qoldiq_summa -= $asosiyTolov;
                $asosiyQarz += $asosiyTolov;
                $qoldiqSumma -= $asosiyTolov;
                echo "    → На долг: " . number_format($asosiyTolov, 0) . "\n";
            }

            // Обновить статус
            if ($schedule->qoldiq_summa <= 0) {
                $schedule->holat = 'tolangan';
            } elseif ($schedule->tolangan_summa > 0) {
                $schedule->holat = 'qisman_tolangan';
            } else {
                $schedule->holat = 'tolanmagan';
            }

            $schedule->save();
        }

        // Обновить платёж
        $payment->penya_uchun = $penyaUchun;
        $payment->asosiy_qarz_uchun = $asosiyQarz;
        $payment->avans = $qoldiqSumma; // остаток = аванс
        $payment->save();

        echo "\nИТОГО платёж:\n";
        echo "  На пеню: " . number_format($penyaUchun, 0) . "\n";
        echo "  На долг: " . number_format($asosiyQarz, 0) . "\n";
        echo "  Аванс: " . number_format($qoldiqSumma, 0) . "\n";
    }

    // 3. Пересчитать пеню для текущей даты
    $today = Carbon::today();
    foreach ($contract->paymentSchedules()->where('qoldiq_summa', '>', 0)->get() as $schedule) {
        $schedule->calculatePenyaAtDate($today, true);
    }

    DB::commit();
    echo "\n✓ ИСПРАВЛЕНИЕ ЗАВЕРШЕНО\n";

    // Показать результат
    echo "\n=== РЕЗУЛЬТАТ ===\n";
    $contract->refresh();
    foreach ($contract->paymentSchedules->sortBy('oy_raqami') as $s) {
        echo "Oy {$s->oy_raqami}: ";
        echo "Grafik=" . number_format($s->tolov_summasi, 0);
        echo ", Tolangan=" . number_format($s->tolangan_summa, 0);
        echo ", Qoldiq=" . number_format($s->qoldiq_summa, 0);
        echo ", Penya=" . number_format($s->penya_summasi, 0);
        echo ", Tol_penya=" . number_format($s->tolangan_penya, 0);
        echo "\n";
    }

} catch (\Exception $e) {
    DB::rollBack();
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

