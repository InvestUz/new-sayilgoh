<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Lot;
use App\Models\PaymentSchedule;
use App\Services\ContractPeriodService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * Shartnoma (Contract) Controller - CRUD operatsiyalari
 */
class ContractController extends Controller
{
    /**
     * Barcha shartnomalar ro'yxati
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Contract::query()
                ->with(['lot', 'tenant'])
                ->withSum('paymentSchedules as jami_qoldiq', 'qoldiq_summa')
                ->withSum('paymentSchedules as jami_penya', 'penya_summasi');

        // Qidiruv
        if ($search = $request->get('search')) {
            $query->where(function($q) use ($search) {
                $q->where('shartnoma_raqami', 'like', "%{$search}%")
                  ->orWhereHas('tenant', function($tq) use ($search) {
                      $tq->where('name', 'like', "%{$search}%")
                         ->orWhere('inn', 'like', "%{$search}%");
                  })
                  ->orWhereHas('lot', function($lq) use ($search) {
                      $lq->where('lot_raqami', 'like', "%{$search}%")
                         ->orWhere('obyekt_nomi', 'like', "%{$search}%");
                  });
            });
        }

        // Holat bo'yicha filter
        if ($holat = $request->get('holat')) {
            $query->where('holat', $holat);
        }

        // Qarzdorlar
        if ($request->boolean('qarzdor')) {
            $query->qarzdor();
        }

        // Sanalar oralig'i
        if ($from = $request->get('from_date')) {
            $query->where('shartnoma_sanasi', '>=', $from);
        }
        if ($to = $request->get('to_date')) {
            $query->where('shartnoma_sanasi', '<=', $to);
        }

            $perPage = $request->get('per_page', 15);
            $contracts = $query->latest('shartnoma_sanasi')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $contracts
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => ['data' => []]]);
        }
    }

    /**
     * Yangi shartnoma yaratish
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lot_id' => 'required|exists:lots,id',
                'tenant_id' => 'required|exists:tenants,id',
                'shartnoma_raqami' => 'required|string|unique:contracts,shartnoma_raqami',
                'shartnoma_sanasi' => 'required|date',
                'auksion_sanasi' => 'required|date',
                'auksion_bayonnoma_raqami' => 'nullable|string',
                'shartnoma_summasi' => 'required|numeric|min:0',
                'shartnoma_muddati' => 'required|integer|min:1',
                'boshlanish_sanasi' => 'required|date',
                'izoh' => 'nullable|string',
            ]);

            // Lot band emasligini tekshirish
            $lot = Lot::findOrFail($validated['lot_id']);
            if ($lot->holat !== 'bosh') {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu lot allaqachon band'
                ], 422);
            }

            // Tugash sanasini hisoblash
            $boshlanish = Carbon::parse($validated['boshlanish_sanasi']);
            $tugash = $boshlanish->copy()->addMonths($validated['shartnoma_muddati'])->subDay();

            // Birinchi to'lov sanasi (10 ish kuni)
            $birinchiTolov = Contract::calculate10WorkingDays(Carbon::parse($validated['shartnoma_sanasi']));

            // Oylik to'lovni hisoblash
            $oylikTolov = $validated['shartnoma_summasi'] / $validated['shartnoma_muddati'];

            // Auksion xarajati (1%)
            $auksionXarajati = $validated['shartnoma_summasi'] * 0.01;

            $contract = Contract::create([
                ...$validated,
                'tugash_sanasi' => $tugash,
                'birinchi_tolov_sanasi' => $birinchiTolov,
                'oylik_tolovi' => $oylikTolov,
                'auksion_xarajati' => $auksionXarajati,
                'holat' => 'faol',
                'dalolatnoma_holati' => 'kutilmoqda',
            ]);

            // To'lov grafigini yaratish
            $contract->generatePaymentSchedule();

            // Lotni band qilish
            $lot->update(['holat' => 'ijarada']);

            $contract->load(['lot', 'tenant', 'paymentSchedules']);

            return response()->json([
                'success' => true,
                'message' => 'Shartnoma muvaffaqiyatli yaratildi',
                'data' => $contract
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bitta shartnomani ko'rish
     */
    public function show(Contract $contract): JsonResponse
    {
        $contract->load(['lot', 'tenant', 'paymentSchedules', 'payments']);

        // Penyalarni yangilash
        foreach ($contract->paymentSchedules as $schedule) {
            $schedule->calculatePenya();
        }

        return response()->json([
            'success' => true,
            'data' => $contract,
            'statistics' => [
                'jami_tolangan' => $contract->jami_tolangan,
                'jami_qarzdorlik' => $contract->jami_qarzdorlik,
                'jami_penya' => $contract->jami_penya,
                'muddati_otgan_oylar' => $contract->muddati_otgan_oylar,
            ],
            'formatted' => [
                'holat' => $contract->holat_nomi,
                'summa' => $contract->formatted_summa,
                'dalolatnoma_holati' => $contract->dalolatnoma_holati_nomi,
            ]
        ]);
    }

    /**
     * Shartnomani yangilash
     */
    public function update(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'dalolatnoma_raqami' => 'nullable|string',
            'dalolatnoma_sanasi' => 'nullable|date',
            'dalolatnoma_holati' => 'sometimes|in:kutilmoqda,topshirilgan,qaytarilgan',
            'holat' => 'sometimes|in:faol,tugagan,bekor_qilingan,muzlatilgan',
            'izoh' => 'nullable|string',
        ]);

        // Agar shartnoma bekor qilinsa, lotni bo'shatish
        if (isset($validated['holat']) && $validated['holat'] !== 'faol') {
            $contract->lot->update(['holat' => 'bosh']);
        }

        $contract->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shartnoma muvaffaqiyatli yangilandi',
            'data' => $contract
        ]);
    }

    /**
     * To'lov grafigini ko'rish
     */
    public function paymentSchedule(Contract $contract): JsonResponse
    {
        $schedules = $contract->paymentSchedules()
            ->orderBy('oy_raqami')
            ->get();

        // Penyalarni yangilash
        foreach ($schedules as $schedule) {
            $schedule->calculatePenya();
        }

        $summary = [
            'jami_summa' => $schedules->sum('tolov_summasi'),
            'jami_tolangan' => $schedules->sum('tolangan_summa'),
            'jami_qoldiq' => $schedules->sum('qoldiq_summa'),
            'jami_penya' => $schedules->sum('penya_summasi'),
            'tolangan_penya' => $schedules->sum('tolangan_penya'),
            'tolangan_oylar' => $schedules->where('holat', 'tolangan')->count(),
            'qarzador_oylar' => $schedules->whereIn('holat', ['tolanmagan', 'qisman_tolangan'])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $schedules,
            'summary' => $summary
        ]);
    }

    /**
     * Qarzdor shartnomalar (Dashboard uchun)
     */
    public function debtors(): JsonResponse
    {
        try {
            $contracts = Contract::where('holat', 'faol')
                ->whereHas('paymentSchedules', fn($q) => $q->where('qoldiq_summa', '>', 0))
                ->with(['lot', 'tenant'])
                ->withSum('paymentSchedules as jami_qoldiq', 'qoldiq_summa')
                ->withSum('paymentSchedules as jami_penya', 'penya_summasi')
                ->orderByDesc('jami_qoldiq')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $contracts
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    /**
     * Dashboard statistikasi
     */
    public function statistics(): JsonResponse
    {
        try {
            $faolShartnomalar = Contract::where('holat', 'faol')->count();
            $jamiSumma = Contract::where('holat', 'faol')->sum('shartnoma_summasi') ?? 0;
            $jamiQoldiq = PaymentSchedule::whereHas('contract', fn($q) => $q->where('holat', 'faol'))
                ->sum('qoldiq_summa') ?? 0;
            $jamiPenya = PaymentSchedule::whereHas('contract', fn($q) => $q->where('holat', 'faol'))
                ->sum('penya_summasi') ?? 0;
            $qarzdorlar = Contract::where('holat', 'faol')
                ->whereHas('paymentSchedules', fn($q) => $q->where('qoldiq_summa', '>', 0))
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'faol_shartnomalar' => $faolShartnomalar,
                    'jami_shartnoma_summasi' => $jamiSumma,
                    'jami_qarzdorlik' => $jamiQoldiq,
                    'jami_penya' => $jamiPenya,
                    'qarzdor_shartnomalar' => $qarzdorlar,
                    'yig_ilish_foizi' => $jamiSumma > 0
                        ? round((($jamiSumma - $jamiQoldiq) / $jamiSumma) * 100, 2)
                        : 0,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'faol_shartnomalar' => 0,
                    'jami_shartnoma_summasi' => 0,
                    'jami_qarzdorlik' => 0,
                    'jami_penya' => 0,
                    'qarzdor_shartnomalar' => 0,
                    'yig_ilish_foizi' => 0,
                ]
            ]);
        }
    }

    /**
     * Get contract periods (current period by default, all on demand)
     *
     * Use cases:
     * - Dashboard: Show only current period summary
     * - Analytics: Filter by specific period
     * - Monitoring: Default view with option to expand
     *
     * Query params:
     * - period: 'current' (default) | 'all' | period_number (1,2,3...)
     * - format: 'summary' (default) | 'detailed'
     */
    public function getPeriods(Request $request, Contract $contract): JsonResponse
    {
        try {
            $periodType = $request->get('period', 'current');
            $format = $request->get('format', 'summary');

            $periodService = ContractPeriodService::forContract($contract);

            // Get data based on period type
            $data = match($periodType) {
                'all' => [
                    'periods' => $periodService->getAllPeriods(),
                    'grand_totals' => $periodService->getGrandTotals(),
                ],
                'current' => [
                    'current_period' => $periodService->getCurrentPeriod(),
                    'current_period_num' => $periodService->getCurrentPeriodNum(),
                ],
                default => [
                    'period' => collect($periodService->getAllPeriods())
                        ->firstWhere('num', (int)$periodType),
                ],
            };

            // Add common metadata
            $data['meta'] = [
                'contract_id' => $contract->id,
                'contract_number' => $contract->shartnoma_raqami,
                'is_expired' => $periodService->isContractExpired(),
                'current_month_year' => $periodService->getCurrentMonthYear(),
            ];

            // For detailed format, include full service data
            if ($format === 'detailed') {
                $data['full_data'] = $periodService->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
