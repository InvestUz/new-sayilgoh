<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Contract;
use App\Models\PaymentSchedule;
use App\Services\PaymentApplicator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * To'lov (Payment) Controller - CRUD + To'lov qo'llash mantiqiy
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentApplicator $applicator)
    {
    }

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
     * MUHIM: To'lov FIFO tartibida eng eski QARZGA qo'llanadi.
     *
     * QOIDA (2026-04-24 dan beri):
     *   - Fakt to'lov to'liq ASOSIY qarzga (principal) yo'naltiriladi.
     *   - Penya ushbu endpoint orqali AVTOMATIK yechilmaydi — u
     *     `/api/penalty-payments` orqali alohida kiritiladi.
     *   - Natijada `tolangan_summa` = qabul qilingan haqiqiy summa, penya
     *     esa faqat hisoblangan informatsion qiymat sifatida ko'rsatiladi.
     *
     * Dublicate himoyasi:
     *   - Agar xuddi shu (contract_id, tolov_sanasi, summa) bilan tasdiqlangan
     *     to'lov mavjud bo'lsa yoki `hujjat_raqami` takrorlansa, 409 qaytariladi.
     *     Foydalanuvchi bilib turib takror saqlamoqchi bo'lsa, so'rovga
     *     `force: true` bayrog'ini qo'shadi.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_id' => 'required|exists:contracts,id',
                'tolov_sanasi' => 'required|date',
                'summa' => 'required|numeric|min:1',
                'tolov_usuli' => 'sometimes|in:bank_otkazmasi,naqd,karta,onlayn',
                'hujjat_raqami' => 'nullable|string|max:100',
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

            // ---------- DUBLICATE GUARD ----------
            $force = $request->boolean('force');
            if (!$force) {
                $duplicate = $this->findPotentialDuplicate(
                    $contract->id,
                    $validated['tolov_sanasi'],
                    (float) $validated['summa'],
                    $validated['hujjat_raqami'] ?? null
                );
                if ($duplicate) {
                    return response()->json([
                        'success' => false,
                        'duplicate' => true,
                        'code' => 'DUPLICATE_SUSPECTED',
                        'existing' => [
                            'id' => $duplicate->id,
                            'tolov_raqami' => $duplicate->tolov_raqami,
                            'tolov_sanasi' => $duplicate->tolov_sanasi,
                            'summa' => $duplicate->summa,
                            'hujjat_raqami' => $duplicate->hujjat_raqami,
                        ],
                        'message' => sprintf(
                            "Shu shartnoma uchun %s sanasida %s so'm to'lov (№ %s) allaqachon mavjud. Rostdan takror kiritmoqchimisiz?",
                            \Carbon\Carbon::parse($duplicate->tolov_sanasi)->format('d.m.Y'),
                            number_format((float) $duplicate->summa, 0, '.', ' '),
                            $duplicate->tolov_raqami
                        ),
                    ], 409);
                }
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

            // To'lovni qo'llash (FIFO tartibida, principal-only) — yagona markaz
            $applicationResult = $this->applicator->apply($payment, $contract);

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
     * Shubhali dublikatni qidirish.
     *
     * Qoidalar:
     *   1) Xuddi shu shartnoma + sana + summa + tasdiqlangan holat mavjudmi.
     *   2) Agar hujjat_raqami yuborilgan bo'lsa, shu shartnoma uchun aynan
     *      shunday raqam bilan tasdiqlangan to'lov bormi.
     */
    private function findPotentialDuplicate(int $contractId, string $tolovSanasi, float $summa, ?string $hujjatRaqami): ?Payment
    {
        $bySameDayAmount = Payment::where('contract_id', $contractId)
            ->whereDate('tolov_sanasi', $tolovSanasi)
            ->where('summa', $summa)
            ->where('holat', 'tasdiqlangan')
            ->latest('id')
            ->first();

        if ($bySameDayAmount) {
            return $bySameDayAmount;
        }

        if ($hujjatRaqami !== null && $hujjatRaqami !== '') {
            return Payment::where('contract_id', $contractId)
                ->where('hujjat_raqami', $hujjatRaqami)
                ->where('holat', 'tasdiqlangan')
                ->latest('id')
                ->first();
        }

        return null;
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
            $payment->update(['holat' => 'qaytarilgan']);
            $this->rebuildContractAllocations($payment->contract);

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
     * Shartnoma bo'yicha to'lov taqsimlashini toza holatga qaytarish.
     *
     * Ikki rejim:
     *
     *  STANDART (`$force = false`) — to'lovni bekor qilish kabi avtomatik
     *  oqimlar uchun:
     *   1. Barcha grafiklarning ASOSIY qarz maydonlari (`tolangan_summa`,
     *      `qoldiq_summa`, `holat`) reset qilinadi.
     *   2. PENYA tarixi (`penya_summasi`, `kechikish_kunlari`,
     *      `tolangan_penya`) SAQLANADI — yo'qotmaslik kafolati.
     *   3. FAKT to'lovlar FIFO orqali qaytadan tarqaladi.
     *   4. Penya bugungi sana bo'yicha MONOTON yangilanadi
     *      (eski qiymatdan past tushmaydi).
     *
     *  KO'CHIRILGAN (`$force = true`) — qo'lda grafikni o'zgartirgan kabi
     *  ataylab qilingan operatsiyalar uchun:
     *   1. Asosiy qarz maydonlari reset qilinadi.
     *   2. Penya maydonlari (`penya_summasi`, `kechikish_kunlari`) ham
     *      reset qilinadi (`tolangan_penya` saqlanadi — bu real to'lov).
     *   3. FAKT to'lovlar FIFO orqali qaytadan tarqaladi.
     *   4. Har bir grafik penyasi NOLDAN qayta hisoblanadi:
     *        - To'lanmagan grafiklar: bugungi sana bo'yicha
     *        - To'liq to'langan grafiklar: oxirgi to'lov sanasi bo'yicha
     */
    private function rebuildContractAllocations(Contract $contract, bool $force = false): void
    {
        $resetData = [
            'tolangan_summa' => 0,
            'qoldiq_summa'   => DB::raw('tolov_summasi'),
            'holat'          => 'kutilmoqda',
        ];

        if ($force) {
            $resetData['penya_summasi'] = 0;
            $resetData['kechikish_kunlari'] = 0;
        }

        $contract->paymentSchedules()->update($resetData);

        $contract->avans_balans = 0;
        $contract->save();

        $payments = $contract->payments()
            ->where('holat', 'tasdiqlangan')
            ->orderBy('tolov_sanasi')
            ->orderBy('id')
            ->get();

        foreach ($payments as $p) {
            $p->asosiy_qarz_uchun = 0;
            $p->penya_uchun = 0;
            $p->avans = 0;
            $p->save();
            $this->applicator->apply($p, $contract);
        }

        // Penyani qayta hisoblash
        $today = Carbon::today();
        foreach ($contract->paymentSchedules()->get() as $schedule) {
            if ($force) {
                $this->recalculateSchedulePenya($schedule, $contract, $today);
            } else {
                $schedule->calculatePenyaAtDate($today, true);
            }
        }
    }

    /**
     * Bir grafikning penyasini noldan hisoblash.
     *
     *  - To'lanmagan grafik (qoldiq > 0): bugungi sana bo'yicha
     *    `calculatePenyaAtDate` chaqiriladi (force resetdan keyin
     *    "monoton" qoidasi 0'dan boshlangani uchun toza qiymat beradi).
     *  - To'liq to'langan grafik (qoldiq = 0): shu oydagi eng oxirgi
     *    FAKT to'lov sanasi olinadi va deadline'dan kechikish kunlari
     *    bo'yicha penya hisoblanadi (50% cap bilan).
     */
    private function recalculateSchedulePenya(
        PaymentSchedule $schedule,
        Contract $contract,
        Carbon $today
    ): void {
        if ((float) $schedule->qoldiq_summa > 0) {
            $schedule->calculatePenyaAtDate($today, true);
            return;
        }

        $deadline = $schedule->custom_oxirgi_muddat
            ? Carbon::parse($schedule->custom_oxirgi_muddat)
            : Carbon::parse($schedule->oxirgi_muddat);

        $lastPaymentInMonth = $contract->payments
            ->where('holat', 'tasdiqlangan')
            ->filter(function ($p) use ($schedule) {
                $d = Carbon::parse($p->tolov_sanasi);
                return $d->month == $schedule->oy && $d->year == $schedule->yil;
            })
            ->sortByDesc('tolov_sanasi')
            ->first();

        if (!$lastPaymentInMonth) {
            return;
        }

        $payDate = Carbon::parse($lastPaymentInMonth->tolov_sanasi);
        if ($payDate->lte($deadline)) {
            return;
        }

        $days = (int) $deadline->diffInDays($payDate);
        $fakt = (float) $schedule->tolangan_summa;
        $newPenya = $fakt <= 0
            ? 0.0
            : min(
                $fakt * PaymentSchedule::PENYA_RATE * $days,
                $fakt * PaymentSchedule::MAX_PENYA_RATE
            );

        $schedule->penya_summasi = round($newPenya, 2);
        $schedule->kechikish_kunlari = $days;
        $schedule->save();
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
                'custom_oxirgi_muddat' => 'nullable|date',
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

            // CUSTOM DEADLINE HANDLING
            if ($request->has('custom_oxirgi_muddat')) {
                $newDeadline = $validated['custom_oxirgi_muddat'];
                $originalDeadline = $schedule->oxirgi_muddat;
                $currentCustom = $schedule->custom_oxirgi_muddat;

                // Determine the "old" date for changelog
                $oldDate = $currentCustom ? Carbon::parse($currentCustom) : Carbon::parse($originalDeadline);

                // Only log if deadline actually changed
                if ($newDeadline && $oldDate->format('Y-m-d') !== Carbon::parse($newDeadline)->format('Y-m-d')) {
                    $schedule->custom_oxirgi_muddat = $newDeadline;

                    // Build change log
                    $changeLog = sprintf(
                        "Changed on %s: Old date was %s, changed to %s",
                        Carbon::now()->format('d.m.Y H:i'),
                        $oldDate->format('d.m.Y'),
                        Carbon::parse($newDeadline)->format('d.m.Y')
                    );

                    // Append to existing log
                    if ($schedule->muddat_ozgarish_izoh) {
                        $schedule->muddat_ozgarish_izoh .= "\n" . $changeLog;
                    } else {
                        $schedule->muddat_ozgarish_izoh = $changeLog;
                    }
                } elseif (!$newDeadline && $currentCustom) {
                    // Custom deadline removed - reset to original
                    $schedule->custom_oxirgi_muddat = null;

                    $changeLog = sprintf(
                        "Reset on %s: Custom date %s removed, reverted to original %s",
                        Carbon::now()->format('d.m.Y H:i'),
                        Carbon::parse($currentCustom)->format('d.m.Y'),
                        Carbon::parse($originalDeadline)->format('d.m.Y')
                    );

                    if ($schedule->muddat_ozgarish_izoh) {
                        $schedule->muddat_ozgarish_izoh .= "\n" . $changeLog;
                    } else {
                        $schedule->muddat_ozgarish_izoh = $changeLog;
                    }
                }
            }

            $schedule->updateStatus();
            $schedule->save();

            // Qo'lda o'zgartirish ataylab — to'liq qayta hisoblash:
            //   FAKT to'lovlar FIFO bo'yicha qaytadan tarqaladi va har bir
            //   grafik penyasi yangi deadline'ga ko'ra noldan hisoblanadi.
            DB::transaction(function () use ($schedule) {
                $this->rebuildContractAllocations($schedule->contract, true);
            });

            return response()->json([
                'success' => true,
                'message' => 'Grafik yangilandi',
                'data' => $schedule->fresh(),
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
