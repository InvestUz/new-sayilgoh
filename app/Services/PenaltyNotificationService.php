<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\PenaltyNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * PenaltyNotificationService - Generate penalty notifications (Bildirg'inoma)
 *
 * Contract clause 8.2 compliance:
 * - Penalty rate: 0.4% per day on overdue amount
 * - Penalty cap: 50% of overdue amount maximum
 * - Penalty only when payment is late (payment_date > due_date)
 */
class PenaltyNotificationService
{
    // Penalty constants (from contract)
    public const PENALTY_RATE = 0.0004; // 0.04% per day
    public const MAX_PENALTY_RATE = 0.5; // 50% cap
    public const LEGAL_BASIS = "Shartnomaning 8.2-bandi asosida";

    /**
     * Calculate penalty using contract-compliant formula
     *
     * @param float $overdueAmount Amount that is overdue
     * @param Carbon $dueDate Due date (oxirgi muddat)
     * @param Carbon $paymentDate Payment date (or calculation date)
     * @return array Calculation details
     */
    public function calculatePenalty(float $overdueAmount, Carbon $dueDate, Carbon $paymentDate): array
    {
        $result = [
            'overdue_amount' => $overdueAmount,
            'due_date' => $dueDate->format('Y-m-d'),
            'payment_date' => $paymentDate->format('Y-m-d'),
            'overdue_days' => 0,
            'penalty_rate' => self::PENALTY_RATE,
            'penalty_rate_percent' => self::PENALTY_RATE * 100, // 0.4%
            'calculated_penalty' => 0.0,
            'max_penalty' => $overdueAmount * self::MAX_PENALTY_RATE,
            'cap_applied' => false,
            'final_penalty' => 0.0,
            'formula_text' => '',
            'is_late' => false,
        ];

        // Rule 1: Penalty only if payment_date > due_date
        if ($paymentDate->lte($dueDate)) {
            $result['formula_text'] = "To'lov o'z vaqtida amalga oshirilgan - penya yo'q";
            return $result;
        }

        $result['is_late'] = true;

        // Rule 4: overdue_days = payment_date - due_date
        $overdueDays = $dueDate->diffInDays($paymentDate);
        $result['overdue_days'] = $overdueDays;

        // Rule 2: penalty = overdue_amount * 0.0004 * overdue_days
        $calculatedPenalty = $overdueAmount * self::PENALTY_RATE * $overdueDays;
        $result['calculated_penalty'] = round($calculatedPenalty, 2);

        // Rule 3: penalty <= overdue_amount * 0.5 (cap)
        $maxPenalty = $overdueAmount * self::MAX_PENALTY_RATE;
        $result['max_penalty'] = round($maxPenalty, 2);

        if ($calculatedPenalty > $maxPenalty) {
            $result['cap_applied'] = true;
            $result['final_penalty'] = round($maxPenalty, 2);
        } else {
            $result['final_penalty'] = round($calculatedPenalty, 2);
        }

        // Generate formula text
        $result['formula_text'] = $this->generateFormulaText($overdueAmount, $overdueDays, $result['final_penalty'], $result['cap_applied']);

        return $result;
    }

    /**
     * Generate human-readable formula text
     */
    public function generateFormulaText(float $overdueAmount, int $overdueDays, float $penalty, bool $capApplied): string
    {
        $formattedAmount = number_format($overdueAmount, 0, ',', ' ');
        $formattedPenalty = number_format($penalty, 0, ',', ' ');

        $formula = "{$formattedAmount} × 0,4% × {$overdueDays} kun = {$formattedPenalty} UZS";

        if ($capApplied) {
            $formula .= " (50% chegara qo'llandi)";
        }

        return $formula;
    }

    /**
     * Generate notification text in Uzbek
     */
    public function generateNotificationText(array $data): string
    {
        $dueDate = Carbon::parse($data['due_date'])->format('d.m.Y');
        $paymentDate = Carbon::parse($data['payment_date'])->format('d.m.Y');
        $overdueAmount = number_format($data['overdue_amount'], 0, ',', ' ');
        $penalty = number_format($data['final_penalty'], 0, ',', ' ');
        $overdueDays = $data['overdue_days'];

        $text = <<<TEXT
{$data['legal_basis']},
{$dueDate} sanada to'lanishi kerak bo'lgan ijara haqi {$paymentDate} sanada to'langanligi sababli,
to'lov {$overdueDays} kun kechiktirilgan.

PENYA HISOBLASH:
{$overdueAmount} × 0,4% × {$overdueDays} kun = {$penalty} UZS

TEXT;

        if ($data['cap_applied']) {
            $text .= "\nUshbu penya summasi qonuniy 50% chegaradan oshmagan.";
        } else {
            $text .= "\nUshbu penya summasi qonuniy chegaradan oshmagan.";
        }

        return $text;
    }

    /**
     * Create a penalty notification record
     *
     * @param Contract $contract
     * @param PaymentSchedule|null $schedule Specific schedule (optional)
     * @param Carbon|null $asOfDate Date to calculate as of (defaults to today)
     * @return PenaltyNotification
     */
    public function createNotification(
        Contract $contract,
        ?PaymentSchedule $schedule = null,
        ?Carbon $asOfDate = null
    ): PenaltyNotification {
        $asOfDate = $asOfDate ?? Carbon::today();

        // If no specific schedule, get the oldest unpaid one
        if (!$schedule) {
            $schedule = $contract->paymentSchedules()
                ->where('qoldiq_summa', '>', 0)
                ->orderBy('oy_raqami')
                ->first();
        }

        if (!$schedule) {
            throw new \InvalidArgumentException("No unpaid schedule found for this contract");
        }

        $dueDate = Carbon::parse($schedule->oxirgi_muddat);
        $overdueAmount = (float) $schedule->qoldiq_summa;

        // Calculate penalty
        $calculation = $this->calculatePenalty($overdueAmount, $dueDate, $asOfDate);

        // Verify against system value
        $systemPenalty = (float) $schedule->penya_summasi;

        // Create notification
        $notification = PenaltyNotification::create([
            'contract_id' => $contract->id,
            'payment_schedule_id' => $schedule->id,
            'notification_number' => PenaltyNotification::generateNotificationNumber(),
            'notification_date' => $asOfDate,
            'contract_number' => $contract->shartnoma_raqami,
            'tenant_name' => $contract->tenant?->name ?? 'N/A',
            'lot_number' => $contract->lot?->lot_raqami ?? 'N/A',
            'due_date' => $dueDate,
            'payment_date' => $asOfDate,
            'overdue_amount' => $overdueAmount,
            'overdue_days' => $calculation['overdue_days'],
            'penalty_rate' => self::PENALTY_RATE,
            'calculated_penalty' => $calculation['calculated_penalty'],
            'max_penalty' => $calculation['max_penalty'],
            'cap_applied' => $calculation['cap_applied'],
            'final_penalty' => $calculation['final_penalty'],
            'formula_text' => $calculation['formula_text'],
            'legal_basis' => self::LEGAL_BASIS,
            'notification_text_uz' => $this->generateNotificationText([
                ...$calculation,
                'legal_basis' => self::LEGAL_BASIS,
            ]),
            'status' => 'generated',
            'system_match' => abs($calculation['final_penalty'] - $systemPenalty) <= 0.01,
            'system_penalty' => $systemPenalty,
        ]);

        // Log mismatch if any
        if (!$notification->system_match) {
            $notification->mismatch_reason = sprintf(
                'Kalkulyator: %s UZS, Tizim: %s UZS',
                number_format($calculation['final_penalty'], 2),
                number_format($systemPenalty, 2)
            );
            $notification->save();
        }

        return $notification;
    }

    /**
     * Generate PDF for notification
     *
     * @param PenaltyNotification $notification
     * @return string PDF file path
     */
    public function generatePdf(PenaltyNotification $notification): string
    {
        // Load contract with relations
        $contract = $notification->contract()->with(['tenant', 'lot'])->first();

        // Generate HTML content for PDF
        $html = $this->generatePdfHtml($notification, $contract);

        // Use simple HTML to PDF approach (no external libraries needed)
        $filename = sprintf(
            'bildirginoma_%s_%s.html',
            $notification->notification_number,
            now()->format('Ymd_His')
        );

        $path = 'notifications/' . $filename;
        Storage::disk('public')->put($path, $html);

        // Update notification
        $notification->update([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ]);

        return $path;
    }

    /**
     * Generate printable HTML content
     */
    public function generatePdfHtml(PenaltyNotification $notification, Contract $contract): string
    {
        $notificationDate = $notification->notification_date->format('d.m.Y');
        $dueDate = $notification->due_date->format('d.m.Y');
        $paymentDate = $notification->payment_date->format('d.m.Y');
        $overdueAmount = number_format($notification->overdue_amount, 0, ',', ' ');
        $penalty = number_format($notification->final_penalty, 0, ',', ' ');

        return <<<HTML
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Bildirg'inoma {$notification->notification_number}</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 14px;
            line-height: 1.6;
            margin: 40px;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 14px;
            color: #666;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 12px;
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }
        .info-label {
            color: #666;
        }
        .info-value {
            font-weight: bold;
        }
        .formula-box {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 20px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 16px;
            text-align: center;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .legal-text {
            font-style: italic;
            color: #666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .signature-area {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }
        @media print {
            body { margin: 20mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">BILDIRG'INOMA</div>
        <div class="subtitle">Penya hisoblash to'g'risida</div>
        <div style="margin-top: 10px;">
            <strong>№ {$notification->notification_number}</strong> | {$notificationDate}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Shartnoma ma'lumotlari</div>
        <div class="info-row">
            <span class="info-label">Shartnoma raqami:</span>
            <span class="info-value">{$notification->contract_number}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Ijarachi:</span>
            <span class="info-value">{$notification->tenant_name}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Lot raqami:</span>
            <span class="info-value">{$notification->lot_number}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Kechikish ma'lumotlari</div>
        <div class="info-row">
            <span class="info-label">To'lov muddati:</span>
            <span class="info-value">{$dueDate}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Hisoblash sanasi:</span>
            <span class="info-value">{$paymentDate}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Kechikish:</span>
            <span class="info-value" style="color: #c00;">{$notification->overdue_days} kun</span>
        </div>
        <div class="info-row">
            <span class="info-label">Qarz summasi:</span>
            <span class="info-value">{$overdueAmount} UZS</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Penya hisoblash</div>
        <div class="formula-box">
            {$notification->formula_text}
        </div>
        <div class="info-row" style="font-size: 18px;">
            <span class="info-label">Yakuniy penya:</span>
            <span class="info-value" style="color: #c00;">{$penalty} UZS</span>
        </div>
    </div>

    <div class="legal-text">
        <strong>Huquqiy asos:</strong> {$notification->legal_basis}
        <br><br>
        Penya stavkasi: kuniga 0,4% (yillik 146%)
        <br>
        Maksimal chegara: qarz summasining 50%
    </div>

    <div class="signature-area">
        <div class="signature-box">
            <div class="signature-line">Ijara beruvchi</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Ijarachi</div>
        </div>
    </div>

    <div class="no-print" style="margin-top: 30px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 16px; cursor: pointer;">
            Chop etish
        </button>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Validate calculation matches system values
     * Raises warning if mismatch detected
     */
    public function validateCalculation(Contract $contract, array $calculatorInput): array
    {
        $result = [
            'valid' => true,
            'warnings' => [],
            'calculator' => null,
            'system' => null,
        ];

        // Calculate using input
        $dueDate = Carbon::parse($calculatorInput['due_date']);
        $paymentDate = Carbon::parse($calculatorInput['payment_date']);
        $overdueAmount = (float) $calculatorInput['overdue_amount'];

        $calculatorResult = $this->calculatePenalty($overdueAmount, $dueDate, $paymentDate);
        $result['calculator'] = $calculatorResult;

        // Find matching schedule
        $schedule = $contract->paymentSchedules()
            ->where('oxirgi_muddat', $dueDate->format('Y-m-d'))
            ->first();

        if ($schedule) {
            $result['system'] = [
                'overdue_amount' => $schedule->qoldiq_summa,
                'penalty' => $schedule->penya_summasi,
                'overdue_days' => $schedule->kechikish_kunlari,
            ];

            // Check for mismatches
            $tolerance = 0.01;

            if (abs($calculatorResult['final_penalty'] - $schedule->penya_summasi) > $tolerance) {
                $result['valid'] = false;
                $result['warnings'][] = [
                    'type' => 'penalty_mismatch',
                    'message' => 'Penya summasi tizim bilan mos kelmaydi',
                    'calculator_value' => $calculatorResult['final_penalty'],
                    'system_value' => $schedule->penya_summasi,
                ];
            }

            if ($calculatorResult['overdue_days'] != $schedule->kechikish_kunlari) {
                $result['warnings'][] = [
                    'type' => 'days_mismatch',
                    'message' => 'Kechikish kunlari tizim bilan mos kelmaydi',
                    'calculator_value' => $calculatorResult['overdue_days'],
                    'system_value' => $schedule->kechikish_kunlari,
                ];
            }
        }

        return $result;
    }

    /**
     * Get all notifications for a contract
     */
    public function getContractNotifications(Contract $contract): \Illuminate\Database\Eloquent\Collection
    {
        return PenaltyNotification::where('contract_id', $contract->id)
            ->orderBy('notification_date', 'desc')
            ->get();
    }
}
