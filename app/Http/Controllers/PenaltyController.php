<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\PaymentSchedule;
use App\Models\PenaltyNotification;
use App\Services\PenaltyNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * PenaltyController - Manual penalty verification and notification generation
 *
 * Contract clause 8.2 compliance:
 * - Penalty rate: 0.4% per day
 * - Penalty cap: 50% of overdue amount
 * - Penalty only when payment is late
 */
class PenaltyController extends Controller
{
    protected PenaltyNotificationService $notificationService;

    public function __construct(PenaltyNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // ============================================
    // PENALTY CALCULATOR (Manual Verification)
    // ============================================

    /**
     * Calculate penalty via API
     * POST /api/penalty/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'overdue_amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'payment_date' => 'required|date',
            'contract_id' => 'nullable|exists:contracts,id',
        ]);

        $overdueAmount = (float) $validated['overdue_amount'];
        $dueDate = Carbon::parse($validated['due_date']);
        $paymentDate = Carbon::parse($validated['payment_date']);

        // Calculate using service
        $calculation = $this->notificationService->calculatePenalty(
            $overdueAmount,
            $dueDate,
            $paymentDate
        );

        // If contract provided, validate against system
        $validation = null;
        if (!empty($validated['contract_id'])) {
            $contract = Contract::find($validated['contract_id']);
            if ($contract) {
                $validation = $this->notificationService->validateCalculation($contract, $validated);
            }
        }

        return response()->json([
            'success' => true,
            'calculation' => $calculation,
            'validation' => $validation,
            'display' => [
                'overdue_days' => $calculation['overdue_days'],
                'penalty_rate' => '0.4%',
                'calculated_penalty' => number_format($calculation['calculated_penalty'], 0, ',', ' ') . ' UZS',
                'cap_applied' => $calculation['cap_applied'] ? 'Ha (50%)' : "Yo'q",
                'final_penalty' => number_format($calculation['final_penalty'], 0, ',', ' ') . ' UZS',
                'formula' => $calculation['formula_text'],
            ],
        ]);
    }

    /**
     * Get calculator page for a contract
     * GET /contracts/{contract}/penalty-calculator
     */
    public function calculatorPage(Contract $contract): View
    {
        $contract->load(['tenant', 'lot', 'paymentSchedules']);

        // Get overdue schedules for quick calculation (using effective deadline)
        $overdueSchedules = $contract->paymentSchedules()
            ->where('qoldiq_summa', '>', 0)
            ->whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) < ?', [Carbon::today()])
            ->orderBy('oy_raqami')
            ->get();

        // Get all notifications for this contract
        $notifications = $this->notificationService->getContractNotifications($contract);

        return view('penalty.calculator', compact('contract', 'overdueSchedules', 'notifications'));
    }

    // ============================================
    // PENALTY NOTIFICATIONS (Bildirg'inoma)
    // ============================================

    /**
     * Generate notification for a schedule
     * POST /api/penalty/notification/generate
     */
    public function generateNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'schedule_id' => 'nullable|exists:payment_schedules,id',
            'as_of_date' => 'nullable|date',
        ]);

        $contract = Contract::with(['tenant', 'lot'])->find($validated['contract_id']);
        $schedule = null;

        if (!empty($validated['schedule_id'])) {
            $schedule = PaymentSchedule::find($validated['schedule_id']);
        }

        $asOfDate = !empty($validated['as_of_date'])
            ? Carbon::parse($validated['as_of_date'])
            : Carbon::today();

        try {
            $notification = $this->notificationService->createNotification(
                $contract,
                $schedule,
                $asOfDate
            );

            return response()->json([
                'success' => true,
                'notification' => [
                    'id' => $notification->id,
                    'number' => $notification->notification_number,
                    'date' => $notification->notification_date->format('d.m.Y'),
                    'penalty' => $notification->formatted_penalty,
                    'overdue_days' => $notification->overdue_days,
                    'formula' => $notification->formula_text,
                    'text' => $notification->notification_text_uz,
                    'system_match' => $notification->system_match,
                    'mismatch_reason' => $notification->mismatch_reason,
                ],
                'message' => "Bildirg'inoma muvaffaqiyatli yaratildi",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generate PDF for notification
     * POST /api/penalty/notification/{notification}/pdf
     */
    public function generatePdf(PenaltyNotification $notification): JsonResponse
    {
        try {
            $path = $this->notificationService->generatePdf($notification);

            return response()->json([
                'success' => true,
                'pdf_url' => Storage::disk('public')->url($path),
                'pdf_path' => $path,
                'message' => 'PDF muvaffaqiyatli yaratildi',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download PDF for notification
     * GET /penalty/notification/{notification}/download
     */
    public function downloadPdf(PenaltyNotification $notification)
    {
        // Generate PDF if not exists
        if (!$notification->pdf_path || !Storage::disk('public')->exists($notification->pdf_path)) {
            $this->notificationService->generatePdf($notification);
            $notification->refresh();
        }

        $path = Storage::disk('public')->path($notification->pdf_path);
        $filename = "bildirginoma_{$notification->notification_number}.html";

        return response()->download($path, $filename, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * View notification details
     * GET /penalty/notification/{notification}
     */
    public function showNotification(PenaltyNotification $notification): View
    {
        $notification->load(['contract.tenant', 'contract.lot', 'paymentSchedule']);

        return view('penalty.notification', compact('notification'));
    }

    /**
     * Get notifications for a contract (API)
     * GET /api/contracts/{contract}/notifications
     */
    public function contractNotifications(Contract $contract): JsonResponse
    {
        $notifications = $this->notificationService->getContractNotifications($contract);

        return response()->json([
            'success' => true,
            'notifications' => $notifications->map(function ($n) {
                return [
                    'id' => $n->id,
                    'number' => $n->notification_number,
                    'date' => $n->notification_date->format('d.m.Y'),
                    'penalty' => $n->formatted_penalty,
                    'overdue_days' => $n->overdue_days,
                    'status' => $n->status,
                    'status_name' => $n->status_nomi,
                    'system_match' => $n->system_match,
                    'has_pdf' => !empty($n->pdf_path),
                ];
            }),
        ]);
    }

    /**
     * Get schedule penalty details (for calculator auto-fill)
     * GET /api/schedule/{schedule}/penalty-details
     */
    public function scheduleDetails(PaymentSchedule $schedule): JsonResponse
    {
        $schedule->load('contract');
        $today = Carbon::today();

        // Use effective deadline (custom if set, otherwise original)
        $effectiveDeadline = $schedule->custom_oxirgi_muddat
            ? Carbon::parse($schedule->custom_oxirgi_muddat)
            : Carbon::parse($schedule->oxirgi_muddat);

        // Calculate current penalty
        $calculation = $this->notificationService->calculatePenalty(
            (float) $schedule->qoldiq_summa,
            $effectiveDeadline,
            $today
        );

        return response()->json([
            'success' => true,
            'schedule' => [
                'id' => $schedule->id,
                'month' => $schedule->oy_raqami,
                'year' => $schedule->yil,
                'month_name' => $schedule->davr_nomi,
                'due_date' => $effectiveDeadline->format('Y-m-d'),
                'due_date_formatted' => $effectiveDeadline->format('d.m.Y'),
                'original_due_date' => Carbon::parse($schedule->oxirgi_muddat)->format('d.m.Y'),
                'has_custom_deadline' => !empty($schedule->custom_oxirgi_muddat),
                'overdue_amount' => $schedule->qoldiq_summa,
                'overdue_amount_formatted' => number_format($schedule->qoldiq_summa, 0, ',', ' ') . ' UZS',
                'system_penalty' => $schedule->penya_summasi,
                'system_overdue_days' => $schedule->kechikish_kunlari,
            ],
            'calculation' => $calculation,
            'contract' => [
                'id' => $schedule->contract->id,
                'number' => $schedule->contract->shartnoma_raqami,
                'monthly_payment' => $schedule->contract->oylik_tolovi,
            ],
        ]);
    }

    // ============================================
    // AUDIT & VERIFICATION
    // ============================================

    /**
     * Get mismatched notifications (for admin review)
     * GET /api/penalty/mismatches
     */
    public function getMismatches(): JsonResponse
    {
        $mismatches = PenaltyNotification::mismatched()
            ->with(['contract.tenant'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $mismatches->count(),
            'mismatches' => $mismatches->map(function ($n) {
                return [
                    'id' => $n->id,
                    'number' => $n->notification_number,
                    'contract' => $n->contract_number,
                    'tenant' => $n->tenant_name,
                    'calculator_penalty' => $n->final_penalty,
                    'system_penalty' => $n->system_penalty,
                    'reason' => $n->mismatch_reason,
                    'date' => $n->notification_date->format('d.m.Y'),
                ];
            }),
        ]);
    }
}
