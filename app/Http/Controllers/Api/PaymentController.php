<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Contract;
use App\Models\PaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * To'lov (Payment) Controller - CRUD + To'lov qo'llash mantiqiy
 */
class PaymentController extends Controller
{
    /**
     * Barcha to'lovlar ro'yxati
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Payment::query()
                ->with(['contract.tenant', 'contract.lot']);

            // Shartnoma bo'yicha filter
            if ($contractId = $request->get('contract_id')) {
                $query->where('contract_id', $contractId);
            }

            // Qidiruv
            if ($search = $request->get('search')) {
                $query->where('tolov_raqami', 'like', "%{$search}%");
            }

            $perPage = $request->get('per_page', 50);
            $payments = $query->latest('tolov_sanasi')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => ['data' => []]]);
        }
    }

    /**
     * Yangi to'lov qabul qilish
     *
     * MUHIM: To'lov FIFO tartibida eng eski qarzga qo'llanadi
     * Tartib: 1) Penya 2) Asosiy qarz (eng eski oydan boshlab)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_id' => 'required|exists:contracts,id',
                'tolov_sanasi' => 'required|date',
                'summa' => 'required|numeric|min:1',
                'tolov_usuli' => 'sometimes|in:bank_otkazmasi,naqd,karta,onlayn',
                'hujjat_raqami' => 'nullable|string',
                'izoh' => 'nullable|string',
            ]);

            $validated['tolov_usuli'] = $validated['tolov_usuli'] ?? 'bank_otkazmasi';
            $contract = Contract::with('paymentSchedules')->findOrFail($validated['contract_id']);

            // Shartnoma faol ekanligini tekshirish
            if ($contract->holat !== 'faol') {
                return response()->json([
                    'success' => false,
                    'message' => 'Faqat faol shartnomaga to\'lov qabul qilinadi'
                ], 422);
            }

            DB::beginTransaction();

            // To'lov raqamini generatsiya qilish
            $tolovRaqami = Payment::generateTolovRaqami();

            // To'lovni yaratish
            $payment = Payment::create([
                'contract_id' => $contract->id,
                'tolov_raqami' => $tolovRaqami,
                'tolov_sanasi' => $validated['tolov_sanasi'],
                'summa' => $validated['summa'],
                'tolov_usuli' => $validated['tolov_usuli'],
                'hujjat_raqami' => $validated['hujjat_raqami'] ?? null,
                'izoh' => $validated['izoh'] ?? null,
                'holat' => 'tasdiqlangan',
                'tasdiqlangan_sana' => now(),
            ]);

            // To'lovni qo'llash (FIFO tartibida)
            $applicationResult = $this->applyPaymentFIFO($payment, $contract);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'To\'lov muvaffaqiyatli qabul qilindi',
                'data' => $payment->fresh(),
                'application_details' => $applicationResult
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * FIFO tartibida to'lovni qo'llash
     *
     * MUHIM: To'lov faqat TO'LOV SANASIGA QARAB qo'llanadi:
     * - Penya: faqat oxirgi_muddat < tolov_sanasi bo'lgan oylarga
     * - Qarz: faqat muddati o'tgan oylarga
     * - Qoldiq: avans (oldindan to'lov) sifatida saqlanadi
     *
     * Qoida (Shartnomaga binoan):
     * 1. Avval chiqimlar (pochta, sud xarajatlari)
     * 2. Penya va jarima
     * 3. Muddati o'tgan ijara to'lovi
     * 4. Qoldiq = Avans (credit balance)
     */
    private function applyPaymentFIFO(Payment $payment, Contract $contract): array
    {
        $qoldiqSumma = $payment->summa;
        $tolovSanasi = \Carbon\Carbon::parse($payment->tolov_sanasi);

        $result = [
            'jami_summa' => $payment->summa,
            'penya_uchun' => 0,
            'asosiy_qarz_uchun' => 0,
            'avans' => 0,
            'qoldiq' => 0,
            'qoplangan_oylar' => [],
        ];

        // Faqat TO'LOV SANASIGA QADAR muddati o'tgan oylarni olish
        // oxirgi_muddat < tolov_sanasi (deadline already passed at payment time)
        $schedules = $contract->paymentSchedules()
            ->where('oxirgi_muddat', '<', $tolovSanasi)
            ->whereIn('holat', ['tolanmagan', 'qisman_tolangan'])
            ->orderBy('oy_raqami')
            ->get();

        foreach ($schedules as $schedule) {
            if ($qoldiqSumma <= 0) break;

            // Penyani to'lov sanasiga nisbatan hisoblash
            $schedule->calculatePenyaAtDate($tolovSanasi);

            $oyInfo = [
                'oy_raqami' => $schedule->oy_raqami,
                'davr' => $schedule->davr_nomi,
                'oldingi_qoldiq' => $schedule->qoldiq_summa,
                'oldingi_penya' => $schedule->qoldiq_penya,
            ];

            // 1. Avval penyani to'lash
            $qoldiqPenya = $schedule->penya_summasi - $schedule->tolangan_penya;
            if ($qoldiqPenya > 0 && $qoldiqSumma > 0) {
                $penyaTolov = min($qoldiqPenya, $qoldiqSumma);
                $schedule->tolangan_penya += $penyaTolov;
                $result['penya_uchun'] += $penyaTolov;
                $qoldiqSumma -= $penyaTolov;
                $oyInfo['tolangan_penya'] = $penyaTolov;
            }

            // 2. Keyin asosiy qarzni to'lash
            if ($schedule->qoldiq_summa > 0 && $qoldiqSumma > 0) {
                $asosiyTolov = min($schedule->qoldiq_summa, $qoldiqSumma);
                $schedule->tolangan_summa += $asosiyTolov;
                $schedule->qoldiq_summa -= $asosiyTolov;
                $result['asosiy_qarz_uchun'] += $asosiyTolov;
                $qoldiqSumma -= $asosiyTolov;
                $oyInfo['tolangan_asosiy'] = $asosiyTolov;
            }

            // Holatni yangilash
            $schedule->updateStatus();
            $schedule->save();

            $oyInfo['yangi_qoldiq'] = $schedule->qoldiq_summa;
            $oyInfo['holat'] = $schedule->holat_nomi;
            $result['qoplangan_oylar'][] = $oyInfo;
        }

        // Qoldiq summa = Avans (oldindan to'lov)
        $result['avans'] = $qoldiqSumma;
        $result['qoldiq'] = 0;

        // To'lov taqsimotini saqlash
        $payment->asosiy_qarz_uchun = $result['asosiy_qarz_uchun'];
        $payment->penya_uchun = $result['penya_uchun'];
        $payment->avans = $result['avans'];
        $payment->save();

        // Shartnoma avans balansini yangilash
        if ($result['avans'] > 0) {
            $contract->avans_balans = ($contract->avans_balans ?? 0) + $result['avans'];
            $contract->save();
        }

        return $result;
    }

    /**
     * Bitta to'lovni ko'rish
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['contract.lot', 'contract.tenant', 'paymentSchedule']);

        return response()->json([
            'success' => true,
            'data' => $payment,
            'formatted' => [
                'summa' => $payment->formatted_summa,
                'tolov_usuli' => $payment->tolov_usuli_nomi,
                'holat' => $payment->holat_nomi,
            ]
        ]);
    }

    /**
     * To'lovni bekor qilish
     */
    public function cancel(Payment $payment): JsonResponse
    {
        if ($payment->holat !== 'tasdiqlangan') {
            return response()->json([
                'success' => false,
                'message' => 'Faqat tasdiqlangan to\'lovni bekor qilish mumkin'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // To'lovni qaytarish (schedulelarni yangilash)
            $this->reversePayment($payment);

            $payment->update(['holat' => 'qaytarilgan']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'To\'lov bekor qilindi'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * To'lovni qaytarish (schedulelarni tiklash)
     */
    private function reversePayment(Payment $payment): void
    {
        $contract = $payment->contract;

        // Eng so'nggi to'langan oylardan qaytarish
        $schedules = $contract->paymentSchedules()
            ->where('tolangan_summa', '>', 0)
            ->orderByDesc('oy_raqami')
            ->get();

        $qaytarishSumma = $payment->asosiy_qarz_uchun;
        $qaytarishPenya = $payment->penya_uchun;

        foreach ($schedules as $schedule) {
            // Penyani qaytarish
            if ($qaytarishPenya > 0 && $schedule->tolangan_penya > 0) {
                $penya = min($qaytarishPenya, $schedule->tolangan_penya);
                $schedule->tolangan_penya -= $penya;
                $qaytarishPenya -= $penya;
            }

            // Asosiy summani qaytarish
            if ($qaytarishSumma > 0 && $schedule->tolangan_summa > 0) {
                $summa = min($qaytarishSumma, $schedule->tolangan_summa);
                $schedule->tolangan_summa -= $summa;
                $schedule->qoldiq_summa += $summa;
                $qaytarishSumma -= $summa;
            }

            $schedule->updateStatus();
            $schedule->save();

            if ($qaytarishSumma <= 0 && $qaytarishPenya <= 0) break;
        }
    }

    /**
     * Shartnoma bo'yicha to'lovlar tarixi
     */
    public function byContract(Contract $contract): JsonResponse
    {
        $payments = $contract->payments()
            ->orderByDesc('tolov_sanasi')
            ->get();

        $summary = [
            'jami_tolangan' => $payments->where('holat', 'tasdiqlangan')->sum('summa'),
            'asosiy_qarzga' => $payments->where('holat', 'tasdiqlangan')->sum('asosiy_qarz_uchun'),
            'penyaga' => $payments->where('holat', 'tasdiqlangan')->sum('penya_uchun'),
            'tolovlar_soni' => $payments->where('holat', 'tasdiqlangan')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $payments,
            'summary' => $summary
        ]);
    }

    /**
     * Bugungi to'lovlar (Dashboard uchun)
     */
    public function today(): JsonResponse
    {
        $payments = Payment::whereDate('tolov_sanasi', today())
            ->with(['contract.tenant'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
            'summary' => [
                'jami_summa' => $payments->where('holat', 'tasdiqlangan')->sum('summa'),
                'tolovlar_soni' => $payments->where('holat', 'tasdiqlangan')->count(),
            ]
        ]);
    }

    // ========================================================
    // GRAFIK (SCHEDULE) CRUD METODLARI
    // ========================================================

    /**
     * Grafikni yangilash (sana, summa)
     */
    public function updateSchedule(Request $request, PaymentSchedule $schedule): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tolov_sanasi' => 'sometimes|date',
                'oxirgi_muddat' => 'sometimes|date',
                'tolov_summasi' => 'sometimes|numeric|min:0',
            ]);

            // Agar summa o'zgarsa, qoldiqni ham yangilash
            if (isset($validated['tolov_summasi'])) {
                $farq = $validated['tolov_summasi'] - $schedule->tolov_summasi;
                $schedule->tolov_summasi = $validated['tolov_summasi'];
                $schedule->qoldiq_summa = max(0, $schedule->qoldiq_summa + $farq);
            }

            if (isset($validated['tolov_sanasi'])) {
                $schedule->tolov_sanasi = $validated['tolov_sanasi'];
                $schedule->yil = Carbon::parse($validated['tolov_sanasi'])->year;
                $schedule->oy = Carbon::parse($validated['tolov_sanasi'])->month;
            }

            if (isset($validated['oxirgi_muddat'])) {
                $schedule->oxirgi_muddat = $validated['oxirgi_muddat'];
            }

            // Holatni qayta hisoblash
            $schedule->updateStatus();

            // Penyani qayta hisoblash
            $schedule->calculatePenya();

            $schedule->save();

            return response()->json([
                'success' => true,
                'message' => 'Grafik yangilandi',
                'data' => $schedule->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Grafikni o'chirish
     */
    public function deleteSchedule(PaymentSchedule $schedule): JsonResponse
    {
        try {
            // To'lov qilingan bo'lsa o'chirishga ruxsat yo'q
            if ($schedule->tolangan_summa > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'To\'lov qilingan grafikni o\'chirib bo\'lmaydi'
                ], 422);
            }

            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Grafik o\'chirildi'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Yangi grafik qo'shish
     */
    public function addSchedule(Request $request, Contract $contract): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tolov_sanasi' => 'required|date',
                'tolov_summasi' => 'required|numeric|min:1',
                'oxirgi_muddat' => 'nullable|date',
            ]);

            // Oxirgi oy raqamini olish
            $lastMonth = $contract->paymentSchedules()->max('oy_raqami') ?? 0;
            $tolovSanasi = Carbon::parse($validated['tolov_sanasi']);

            $schedule = PaymentSchedule::create([
                'contract_id' => $contract->id,
                'oy_raqami' => $lastMonth + 1,
                'yil' => $tolovSanasi->year,
                'oy' => $tolovSanasi->month,
                'tolov_sanasi' => $validated['tolov_sanasi'],
                'oxirgi_muddat' => $validated['oxirgi_muddat'] ?? $tolovSanasi->copy()->addDays(10)->format('Y-m-d'),
                'tolov_summasi' => $validated['tolov_summasi'],
                'tolangan_summa' => 0,
                'qoldiq_summa' => $validated['tolov_summasi'],
                'penya_summasi' => 0,
                'tolangan_penya' => 0,
                'kechikish_kunlari' => 0,
                'holat' => $tolovSanasi->isPast() ? 'tolanmagan' : 'kutilmoqda',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grafik qo\'shildi',
                'data' => $schedule
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk schedule creation - davr bo'yicha avtomatik grafik yaratish
     *
     * Input:
     * - period_start: davr boshlanish sanasi (masalan: 2024-08-01)
     * - period_end: davr tugash sanasi (masalan: 2025-07-31)
     * - year_amount: yillik/davr uchun jami summa
     * - payment_day: har oyning qaysi kuni (default: 10)
     */
    public function bulkAddSchedule(Request $request, Contract $contract): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_start' => 'required|date',
                'period_end' => 'required|date|after:period_start',
                'year_amount' => 'required|numeric|min:1',
                'payment_day' => 'sometimes|integer|min:1|max:28',
            ]);

            $periodStart = Carbon::parse($validated['period_start']);
            $periodEnd = Carbon::parse($validated['period_end']);
            $yearAmount = (float) $validated['year_amount'];
            $paymentDay = (int) ($validated['payment_day'] ?? 10);

            // Calculate number of months between start and end
            $monthCount = $periodStart->diffInMonths($periodEnd) + 1;
            if ($monthCount < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Davr kamida 1 oy bo\'lishi kerak'
                ], 422);
            }

            // Calculate monthly amount
            $monthlyAmount = round($yearAmount / $monthCount);

            // Get last oy_raqami
            $lastMonth = $contract->paymentSchedules()->max('oy_raqami') ?? 0;

            DB::beginTransaction();

            $schedules = [];
            $totalCreated = 0;

            // Start from the FIRST DAY of start month to avoid date confusion
            $currentMonth = Carbon::create($periodStart->year, $periodStart->month, 1);
            $endMonth = Carbon::create($periodEnd->year, $periodEnd->month, 1);

            while ($currentMonth->lte($endMonth)) {
                $lastMonth++;

                // Set payment date to specified day of current month
                $paymentDate = $currentMonth->copy();

                // Handle months with fewer days (e.g., February)
                $daysInMonth = $paymentDate->daysInMonth;
                if ($paymentDay > $daysInMonth) {
                    $paymentDate->day($daysInMonth);
                } else {
                    $paymentDate->day($paymentDay);
                }

                // Deadline = payment date + 10 days
                $deadline = $paymentDate->copy()->addDays(10);

                $schedule = PaymentSchedule::create([
                    'contract_id' => $contract->id,
                    'oy_raqami' => $lastMonth,
                    'yil' => $paymentDate->year,
                    'oy' => $paymentDate->month,
                    'tolov_sanasi' => $paymentDate->format('Y-m-d'),
                    'oxirgi_muddat' => $deadline->format('Y-m-d'),
                    'tolov_summasi' => $monthlyAmount,
                    'tolangan_summa' => 0,
                    'qoldiq_summa' => $monthlyAmount,
                    'penya_summasi' => 0,
                    'tolangan_penya' => 0,
                    'kechikish_kunlari' => 0,
                    'holat' => $paymentDate->isPast() ? 'tolanmagan' : 'kutilmoqda',
                ]);

                $schedules[] = $schedule;
                $totalCreated++;

                // Move to next month
                $currentMonth->addMonth();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$totalCreated} ta grafik yaratildi",
                'data' => [
                    'created_count' => $totalCreated,
                    'monthly_amount' => $monthlyAmount,
                    'total_amount' => $monthlyAmount * $totalCreated,
                    'schedules' => $schedules
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Grafiklarni qayta yaratish (10-sanada default)
     */
    public function regenerateSchedules(Request $request, Contract $contract): JsonResponse
    {
        try {
            $validated = $request->validate([
                'default_day' => 'sometimes|integer|min:1|max:28',
            ]);

            $defaultDay = $validated['default_day'] ?? 10;

            // Faqat to'lov qilinmagan oylarni o'chirish
            $contract->paymentSchedules()
                ->where('tolangan_summa', 0)
                ->delete();

            // Yangi grafik yaratish
            $contract->generatePaymentSchedule();

            return response()->json([
                'success' => true,
                'message' => 'Grafik qayta yaratildi',
                'data' => $contract->paymentSchedules()->get()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Penya (jarima) uchun alohida to'lov qabul qilish
     *
     * Bu endpoint faqat penya uchun alohida to'lov qabul qiladi
     */
    public function storePenaltyPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_id' => 'required|exists:contracts,id',
                'payment_schedule_id' => 'required|exists:payment_schedules,id',
                'summa' => 'required|numeric|min:1',
                'tolov_sanasi' => 'required|date',
                'tolov_usuli' => 'sometimes|in:bank_otkazmasi,naqd,karta,onlayn',
            ]);

            $validated['tolov_usuli'] = $validated['tolov_usuli'] ?? 'bank_otkazmasi';
            $contract = Contract::findOrFail($validated['contract_id']);
            $schedule = PaymentSchedule::findOrFail($validated['payment_schedule_id']);

            // Tekshirish
            if ($schedule->contract_id !== $contract->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noto\'g\'ri grafik'
                ], 422);
            }

            $qoldiqPenya = $schedule->penya_summasi - $schedule->tolangan_penya;
            if ($qoldiqPenya <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu oy uchun penya yo\'q yoki to\'lagan'
                ], 422);
            }

            if ($validated['summa'] > $qoldiqPenya) {
                return response()->json([
                    'success' => false,
                    'message' => 'Summa juda katta. Qoldiq: ' . number_format($qoldiqPenya, 0)
                ], 422);
            }

            DB::beginTransaction();

            // To'lov raqamini generatsiya qilish
            $tolovRaqami = Payment::generateTolovRaqami();

            // To'lovni yaratish (faqat penya uchun)
            $payment = Payment::create([
                'contract_id' => $contract->id,
                'payment_schedule_id' => $schedule->id,
                'tolov_raqami' => $tolovRaqami,
                'tolov_sanasi' => $validated['tolov_sanasi'],
                'summa' => $validated['summa'],
                'penya_uchun' => $validated['summa'],
                'asosiy_qarz_uchun' => 0,
                'avans' => 0,
                'tolov_usuli' => $validated['tolov_usuli'],
                'holat' => 'tasdiqlangan',
                'tasdiqlangan_sana' => now(),
            ]);

            // Grafikni yangilash
            $schedule->tolangan_penya += $validated['summa'];
            $schedule->updateStatus();
            $schedule->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Penya to\'lovi saqlandi',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }
}
