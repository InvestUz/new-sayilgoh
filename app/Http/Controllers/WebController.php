<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\PaymentApplicator;
use App\Services\ScheduleDisplayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class WebController extends Controller
{
    // ==================== DATA CENTER ====================
    public function dataCenter(Request $request)
    {
        $bugun = Carbon::today();
        $year = $request->get('year');
        $period = $request->get('period', 'month');
        $status = $request->get('status', 'all');

        // Current year for chart if no year filter
        $chartYear = $year ?: date('Y');

        // Physical Device Summary (using Lots as "devices")
        $totalLots = Lot::count();

        // Lots with active contracts in selected year (if year filter is set)
        $activeLots = Lot::whereHas('contracts', function($q) use ($year) {
            $q->where('holat', 'faol');
            if ($year) {
                $q->whereYear('boshlanish_sanasi', $year);
            }
        })->count();

        $vacantLots = Lot::where('holat', 'bosh')->count();
        $umumiyMaydon = Lot::sum('maydon');

        // Contracts - main stats (respect year filter when provided)
        $contractsBaseQuery = Contract::query();
        if ($year) {
            $contractsBaseQuery->whereYear('boshlanish_sanasi', $year);
        }

        $activeContracts = (clone $contractsBaseQuery)->where('holat', 'faol')->count();
        $totalContractValue = (clone $contractsBaseQuery)->where('holat', 'faol')->sum('shartnoma_summasi');
        $expiredContracts = (clone $contractsBaseQuery)->where('holat', '!=', 'faol')->count();

        // Tenants - count based on contracts in selected year when year filter is set
        if ($year) {
            $totalTenants = Tenant::whereHas('contracts', function($q) use ($year) {
                $q->whereYear('boshlanish_sanasi', $year);
            })->count();

            $activeTenants = Tenant::whereHas('contracts', function($q) use ($year) {
                $q->where('holat', 'faol')
                  ->whereYear('boshlanish_sanasi', $year);
            })->count();
        } else {
            $totalTenants = Tenant::count();
            $activeTenants = Tenant::whereHas('contracts', function($q) {
                $q->where('holat', 'faol');
            })->count();
        }

        // Build Payment Schedules query with filters (eager-load contract for live penya calc)
        $schedulesQuery = \App\Models\PaymentSchedule::query()->with('contract');

        // Apply year filter to payment schedules
        if ($year) {
            $schedulesQuery->whereYear('tolov_sanasi', $year);
        }

        // Apply status filter to payment schedules (using effective deadline)
        if ($status === 'muddati_otgan') {
            $schedulesQuery->whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) < ?', [$bugun])->where('qoldiq_summa', '>', 0);
        } elseif ($status === 'kutilmoqda') {
            $schedulesQuery->whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) >= ?', [$bugun])->where('qoldiq_summa', '>', 0);
        } elseif ($status === 'tolangan') {
            $schedulesQuery->where('qoldiq_summa', '<=', 0);
        }

        $filteredSchedules = $schedulesQuery->get();

        // Calculate totals from filtered schedules
        $totalPlan = $filteredSchedules->sum('tolov_summasi');
        $totalDebt = $filteredSchedules->sum('qoldiq_summa');

        // "Jami To'langan" = haqiqiy tasdiqlangan to'lovlar yig'indisi (Payment.summa),
        // bu FAKT qabul qilingan pulni aks ettiradi — schedule'dagi taqsimotga
        // emas. Shu sababli qaytarilgan ('qaytarilgan') to'lovlar ayiriladi.
        $paidQuery = Payment::where('holat', 'tasdiqlangan');
        $refundQuery = Payment::where('holat', 'qaytarilgan');
        if ($year) {
            $paidQuery->whereYear('tolov_sanasi', $year);
            $refundQuery->whereYear('tolov_sanasi', $year);
        }
        $totalPaid = max(0, (float) $paidQuery->sum('summa') - (float) abs($refundQuery->sum('summa')));

        // "Jami Penya" — har bir qoldiqli grafik uchun bugungi sanaga
        // penya_summasi'ni MONOTON ravishda yangilaymiz va DBga saqlaymiz.
        // To'liq to'langan grafiklarda esa avval saqlangan (muzlatilgan)
        // qiymat o'qiladi — penya hech qachon yo'qolmaydi.
        $totalPenya = 0.0;
        foreach ($filteredSchedules as $s) {
            if ((float) $s->qoldiq_summa > 0) {
                $s->calculatePenyaAtDate($bugun, true);
            }
            $unpaid = (float) $s->penya_summasi - (float) $s->tolangan_penya;
            if ($unpaid > 0) {
                $totalPenya += $unpaid;
            }
        }
        $totalPenya = max(0.0, $totalPenya);

        // Payment Statistics - with year filter
        $paymentsQuery = Payment::query();
        if ($year) {
            $paymentsQuery->whereYear('tolov_sanasi', $year);
        }
        $totalPayments = $paymentsQuery->count();

        $thisMonthYear = $year ?: $bugun->year;
        $thisMonthPayments = Payment::whereMonth('tolov_sanasi', $bugun->month)
            ->whereYear('tolov_sanasi', $thisMonthYear)->count();
        $thisMonthSum = Payment::whereMonth('tolov_sanasi', $bugun->month)
            ->whereYear('tolov_sanasi', $thisMonthYear)->sum('summa');

        // Overdue calculation (past due with remaining balance) - with year filter (using effective deadline)
        $overdueQuery = \App\Models\PaymentSchedule::whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) < ?', [$bugun])
            ->where('qoldiq_summa', '>', 0);
        if ($year) {
            $overdueQuery->whereYear('tolov_sanasi', $year);
        }
        $overdueSchedules = $overdueQuery->get();
        $overdueDebt = $overdueSchedules->sum('qoldiq_summa');
        $overdueCount = $overdueSchedules->count();

        // Not yet due calculation - with year filter (using effective deadline)
        $notYetDueQuery = \App\Models\PaymentSchedule::whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) >= ?', [$bugun])
            ->where('qoldiq_summa', '>', 0);
        if ($year) {
            $notYetDueQuery->whereYear('tolov_sanasi', $year);
        }
        $notYetDueSchedules = $notYetDueQuery->get();
        $notYetDueDebt = $notYetDueSchedules->sum('qoldiq_summa');
        $notYetDueCount = $notYetDueSchedules->count();

        // Allocation percentages
        $paidPercent = $totalPlan > 0 ? round(($totalPaid / $totalPlan) * 100, 1) : 0;
        $debtPercent = $totalPlan > 0 ? round(($totalDebt / $totalPlan) * 100, 1) : 0;
        $overduePercent = $totalDebt > 0 ? round(($overdueDebt / $totalDebt) * 100, 1) : 0;

        // Status distribution for chart - with year filter
        $tolanganQuery = \App\Models\PaymentSchedule::where('qoldiq_summa', '<=', 0);
        if ($year) {
            $tolanganQuery->whereYear('tolov_sanasi', $year);
        }

        $statusData = [
            'tolangan' => $tolanganQuery->count(),
            'kutilmoqda' => $notYetDueCount,
            'muddati_otgan' => $overdueCount,
        ];

        // Monthly trend data based on period filter
        $monthlyData = $this->getDataCenterChartData($chartYear, $period);

        // District distribution
        $districtData = Lot::select('tuman', DB::raw('count(*) as count'))
            ->whereNotNull('tuman')
            ->where('tuman', '!=', '')
            ->groupBy('tuman')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Years list for filter - include range from earliest data to current year
        $dbYears = \App\Models\PaymentSchedule::selectRaw('YEAR(tolov_sanasi) as year')
            ->distinct()
            ->whereNotNull('tolov_sanasi')
            ->pluck('year')
            ->filter()
            ->toArray();

        $contractYears = Contract::selectRaw('YEAR(boshlanish_sanasi) as year')
            ->distinct()
            ->pluck('year')
            ->filter()
            ->toArray();

        $allYears = array_unique(array_merge($dbYears, $contractYears));
        $minYear = !empty($allYears) ? min(min($allYears), 2024) : 2024;
        $maxYear = max(date('Y'), !empty($allYears) ? max($allYears) : date('Y'));
        $years = range($maxYear, $minYear); // descending order

        // ────────────────────────────────────────────────────────────────
        //  JORIY OY (Current Month) — alohida hisoblash
        // ────────────────────────────────────────────────────────────────
        // Faqat shu kalendar oydagi grafiklar bo'yicha plan/qoldiq/penya
        // yig'iladi. Bu — foydalanuvchiga "shu oyda nechta pul kerak" degan
        // savolga aniq javob beradi.
        $currentMonth = $this->buildCurrentMonthSummary($bugun);

        return view('data-center', compact(
            'totalLots', 'activeLots', 'vacantLots', 'umumiyMaydon',
            'activeContracts', 'expiredContracts', 'totalContractValue',
            'totalTenants', 'activeTenants',
            'totalPayments', 'thisMonthPayments', 'thisMonthSum',
            'totalPlan', 'totalPaid', 'totalDebt', 'totalPenya',
            'overdueDebt', 'overdueCount', 'notYetDueDebt', 'notYetDueCount',
            'paidPercent', 'debtPercent', 'overduePercent',
            'monthlyData', 'statusData', 'districtData',
            'years', 'year', 'period', 'status', 'chartYear',
            'currentMonth'
        ));
    }

    /**
     * Joriy kalendar oy bo'yicha xulosa.
     *
     * @return array{
     *   year:int, month:int, label:string,
     *   plan:float, paid:float, debt:float, penalty:float,
     *   overdue_count:int, paid_count:int, total_count:int,
     *   collection_percent:float
     * }
     */
    private function buildCurrentMonthSummary(Carbon $today): array
    {
        $monthSchedules = \App\Models\PaymentSchedule::with('contract')
            ->where('yil', $today->year)
            ->where('oy', $today->month)
            ->get();

        $plan = (float) $monthSchedules->sum('tolov_summasi');
        $debt = (float) $monthSchedules->sum('qoldiq_summa');
        $paid = (float) $monthSchedules->sum('tolangan_summa');
        $totalCount = $monthSchedules->count();
        $paidCount = $monthSchedules->where('qoldiq_summa', '<=', 0)->count();

        $overdueCount = 0;
        $penalty = 0.0;
        foreach ($monthSchedules as $s) {
            $effDeadline = $s->custom_oxirgi_muddat ?? $s->oxirgi_muddat;
            if ((float) $s->qoldiq_summa > 0 && Carbon::parse($effDeadline)->lt($today)) {
                $overdueCount++;
                $s->calculatePenyaAtDate($today, true);
            }
            $unpaidPenya = (float) $s->penya_summasi - (float) $s->tolangan_penya;
            if ($unpaidPenya > 0) {
                $penalty += $unpaidPenya;
            }
        }

        $collectionPercent = $plan > 0 ? round(($paid / $plan) * 100, 1) : 0.0;

        $oyNomi = ['Yanvar','Fevral','Mart','Aprel','May','Iyun',
                   'Iyul','Avgust','Sentabr','Oktabr','Noyabr','Dekabr'];

        return [
            'year' => $today->year,
            'month' => $today->month,
            'label' => $oyNomi[$today->month - 1] . ' ' . $today->year,
            'plan' => $plan,
            'paid' => $paid,
            'debt' => $debt,
            'penalty' => max(0.0, $penalty),
            'overdue_count' => $overdueCount,
            'paid_count' => $paidCount,
            'total_count' => $totalCount,
            'collection_percent' => $collectionPercent,
        ];
    }

    private function getDataCenterChartData($chartYear, $period)
    {
        $bugun = Carbon::today();
        $data = [];

        if ($period === 'month') {
            $months = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyn', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
            for ($m = 1; $m <= 12; $m++) {
                $schedules = \App\Models\PaymentSchedule::whereYear('tolov_sanasi', $chartYear)
                    ->whereMonth('tolov_sanasi', $m)->get();
                $payments = Payment::whereYear('tolov_sanasi', $chartYear)
                    ->whereMonth('tolov_sanasi', $m)->sum('summa');

                $data[] = [
                    'label' => $months[$m - 1] . ' ' . $chartYear,
                    'plan' => $schedules->sum('tolov_summasi'),
                    'paid' => $payments,
                ];
            }
        } elseif ($period === 'quarter') {
            $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
            $quarterMonths = [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10, 11, 12]];

            foreach ($quarters as $i => $label) {
                $schedules = \App\Models\PaymentSchedule::whereYear('tolov_sanasi', $chartYear)
                    ->whereIn(DB::raw('MONTH(tolov_sanasi)'), $quarterMonths[$i])->get();
                $payments = Payment::whereYear('tolov_sanasi', $chartYear)
                    ->whereIn(DB::raw('MONTH(tolov_sanasi)'), $quarterMonths[$i])->sum('summa');

                $data[] = [
                    'label' => $label . ' ' . $chartYear,
                    'plan' => $schedules->sum('tolov_summasi'),
                    'paid' => $payments,
                ];
            }
        } else {
            // Yearly - last 5 years
            for ($y = $chartYear - 4; $y <= $chartYear; $y++) {
                $schedules = \App\Models\PaymentSchedule::whereYear('tolov_sanasi', $y)->get();
                $payments = Payment::whereYear('tolov_sanasi', $y)->sum('summa');

                $data[] = [
                    'label' => (string)$y,
                    'plan' => $schedules->sum('tolov_summasi'),
                    'paid' => $payments,
                ];
            }
        }

        return $data;
    }

    // ==================== UNIFIED REGISTRY ====================
    public function registryIndex(Request $request)
    {
        $tab = $request->get('tab', 'tenants');
        $search = $request->get('search', '');

        // Tenants
        $tenantsQuery = Tenant::with('activeContracts');
        if ($search) {
            $tenantsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('inn', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        $tenants = $tenantsQuery->latest()->paginate(20, ['*'], 'tenants_page')->withQueryString();

        // Lots
        $lotStatus = $request->get('lot_status', '');
        $lotsQuery = Lot::with(['contracts' => function($q) {
            $q->with(['tenant', 'paymentSchedules']);
        }]);
        if ($search) {
            $lotsQuery->where(function ($q) use ($search) {
                $q->where('lot_raqami', 'like', "%{$search}%")
                  ->orWhere('obyekt_nomi', 'like', "%{$search}%")
                  ->orWhere('tuman', 'like', "%{$search}%");
            });
        }

        // Apply lot status filter
        if ($lotStatus === 'muddati_tugagan') {
            // Filter lots with expired contracts
            $lotsQuery->whereHas('contracts', function($q) {
                $q->where('tugash_sanasi', '<', Carbon::today());
            });
        } elseif ($lotStatus === 'bosh') {
            $lotsQuery->where('holat', 'bosh');
        } elseif ($lotStatus === 'ijarada') {
            $lotsQuery->where('holat', 'ijarada')
                      ->whereHas('contracts', function($q) {
                          $q->where('tugash_sanasi', '>=', Carbon::today());
                      });
        }

        $lots = $lotsQuery->latest()->paginate(20, ['*'], 'lots_page')->withQueryString();

        // Payments
        $paymentsQuery = Payment::with(['contract.tenant']);
        if ($search) {
            $paymentsQuery->where(function ($q) use ($search) {
                $q->where('tolov_raqami', 'like', "%{$search}%")
                  ->orWhereHas('contract.tenant', fn($tq) => $tq->where('name', 'like', "%{$search}%"));
            });
        }
        $payments = $paymentsQuery->latest('tolov_sanasi')->paginate(20, ['*'], 'payments_page');

        // Unified contracts dataset for merged registry table
        $contractsQuery = Contract::with(['tenant', 'lot', 'paymentSchedules', 'payments']);
        if ($search) {
            $contractsQuery->where(function ($q) use ($search) {
                $q->where('shartnoma_raqami', 'like', "%{$search}%")
                  ->orWhereHas('tenant', function ($tq) use ($search) {
                      $tq->where('name', 'like', "%{$search}%")
                         ->orWhere('inn', 'like', "%{$search}%");
                  })
                  ->orWhereHas('lot', function ($lq) use ($search) {
                      $lq->where('lot_raqami', 'like', "%{$search}%")
                         ->orWhere('obyekt_nomi', 'like', "%{$search}%");
                  });
            });
        }
        $contracts = $contractsQuery->latest('shartnoma_sanasi')->paginate(50, ['*'], 'contracts_page');

        // Counts for badges
        $counts = [
            'tenants' => Tenant::count(),
            'lots' => Lot::count(),
            'payments' => Payment::count(),
        ];

        return view('registry', compact('tenants', 'lots', 'payments', 'contracts', 'tab', 'search', 'counts'));
    }

    // ==================== TENANTS ====================
    public function tenantsIndex(Request $request)
    {
        $query = Tenant::with('activeContracts');

        // Search across all columns using LIKE operator
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('inn', 'like', "%{$search}%")
                  ->orWhere('passport_serial', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('director_name', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('bank_account', 'like', "%{$search}%")
                  ->orWhere('bank_mfo', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $tenants = $query->latest()->paginate(20)->withQueryString();
        return view('blade.tenants.index', compact('tenants'));
    }

    public function tenantsShow(Tenant $tenant)
    {
        $tenant->load(['contracts.lot', 'contracts.paymentSchedules']);

        // Ijarachining barcha lotlarini olish (faol shartnomalar orqali)
        $lots = Lot::whereHas('contracts', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id)
              ->where('holat', 'faol');
        })->get();

        $stats = [
            'faol_lotlar' => $lots->count(),
            'jami_summa' => $tenant->contracts->sum('shartnoma_summasi'),
            'jami_qarz' => $tenant->contracts->sum(fn($c) => $c->paymentSchedules->sum('qoldiq_summa')),
        ];

        return view('tenants.show', compact('tenant', 'stats', 'lots'));
    }

    public function tenantsCreate()
    {
        return view('blade.tenants.form');
    }

    public function tenantsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:yuridik,jismoniy',
            'inn' => 'required|string|unique:tenants,inn',
            'director_name' => 'nullable|string|max:255',
            'passport_serial' => 'nullable|string|max:50',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:50',
            'bank_mfo' => 'nullable|string|max:20',
            'oked' => 'nullable|string|max:20',
        ]);

        $validated['type'] = $validated['type'] ?? 'yuridik';
        Tenant::create($validated);

        return redirect()->route('registry', ['tab' => 'tenants'])->with('success', 'Ijarachi muvaffaqiyatli yaratildi');
    }

    public function tenantsEdit(Tenant $tenant)
    {
        return view('blade.tenants.form', compact('tenant'));
    }

    public function tenantsUpdate(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:yuridik,jismoniy',
            'inn' => 'required|string|unique:tenants,inn,' . $tenant->id,
            'director_name' => 'nullable|string|max:255',
            'passport_serial' => 'nullable|string|max:50',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:50',
            'bank_mfo' => 'nullable|string|max:20',
            'oked' => 'nullable|string|max:20',
        ]);

        $tenant->update($validated);

        return redirect()->route('registry.tenants.show', $tenant)->with('success', 'Ijarachi yangilandi');
    }

    public function tenantsDestroy(Tenant $tenant)
    {
        if ($tenant->contracts()->where('holat', 'faol')->exists()) {
            return back()->with('error', 'Faol shartnomasi bor ijarachini o\'chirib bo\'lmaydi');
        }
        $tenant->delete();
        return redirect()->route('registry', ['tab' => 'tenants'])->with('success', 'Ijarachi o\'chirildi');
    }

    // ==================== LOTS ====================
    public function lotsIndex(Request $request)
    {
        $filter = $request->get('filter');
        $year = $request->get('year');
        $bugun = Carbon::today();

        // Base query with active contracts
        $query = Lot::with(['contracts' => function($q) use ($year) {
            $q->where('holat', 'faol');
            if ($year) {
                $q->whereYear('boshlanish_sanasi', $year);
            }
            $q->with(['tenant', 'paymentSchedules']);
        }]);

        // Get all lots first to calculate filtered list
        $allLots = $query->get();

        // Filter out lots without contracts (when year filter is applied)
        if ($year) {
            $allLots = $allLots->filter(function($lot) {
                return $lot->contracts->count() > 0;
            });
        }

        // Apply filter
        if ($filter) {
            $filteredIds = $allLots->filter(function($lot) use ($filter, $bugun) {
                $contract = $lot->contracts->first();
                if (!$contract) return false;

                $qarz = 0;
                $penya = 0;
                $tolangan = 0;
                $kutilmoqda = 0;
                $kechikishKunlari = 0;

                foreach ($contract->paymentSchedules as $schedule) {
                    $tolangan += $schedule->tolangan_summa;

                    if ($schedule->qoldiq_summa > 0) {
                        $oxirgiMuddat = Carbon::parse($schedule->oxirgi_muddat);
                        if ($oxirgiMuddat->lt($bugun)) {
                            // Past due
                            $qarz += $schedule->qoldiq_summa;
                            $days = $oxirgiMuddat->diffInDays($bugun);
                            $kechikishKunlari = max($kechikishKunlari, $days);
                            $penyaCalc = $schedule->qoldiq_summa * 0.004 * $days;
                            $maxPenya = $schedule->qoldiq_summa * 0.5;
                            $penya += min($penyaCalc, $maxPenya);
                        } else {
                            // Not yet due
                            $kutilmoqda += $schedule->qoldiq_summa;
                        }
                    }
                }

                switch ($filter) {
                    case 'muddati_otgan':
                        return $qarz > 0 && $kechikishKunlari > 0;
                    case 'penya':
                        return $penya > 0;
                    case 'tolangan':
                        return $tolangan > 0;
                    case 'kutilmoqda':
                        return $kutilmoqda > 0;
                    case 'qarzdor':
                        return ($qarz + $kutilmoqda) > 0;
                    default:
                        return true;
                }
            })->pluck('id');

            // Re-query with filtered IDs
            $lots = Lot::with(['contracts' => function($q) use ($year) {
                $q->where('holat', 'faol');
                if ($year) {
                    $q->whereYear('boshlanish_sanasi', $year);
                }
                $q->with(['tenant', 'paymentSchedules']);
            }])->whereIn('id', $filteredIds)->latest()->paginate(20)->withQueryString();
        } elseif ($year) {
            // Year filter only - get lots with contracts in that year
            $lotIds = $allLots->pluck('id');
            $lots = Lot::with(['contracts' => function($q) use ($year) {
                $q->where('holat', 'faol')->whereYear('boshlanish_sanasi', $year)->with(['tenant', 'paymentSchedules']);
            }])->whereIn('id', $lotIds)->latest()->paginate(20)->withQueryString();
        } else {
            $lots = Lot::with(['contracts' => function($q) {
                $q->where('holat', 'faol')->with(['tenant', 'paymentSchedules']);
            }])->latest()->paginate(20)->withQueryString();
        }

        // Filter title for display
        $filterTitles = [
            'muddati_otgan' => "Muddati o'tgan qarzdorlar",
            'penya' => 'Penyali shartnomalar',
            'tolangan' => "To'lov qilingan shartnomalar",
            'kutilmoqda' => "Muddati o'tmagan (kutilmoqda)",
            'qarzdor' => 'Jami qoldiqli shartnomalar',
        ];
        $filterTitle = $filterTitles[$filter] ?? null;

        return view('blade.lots.index', compact('lots', 'filter', 'filterTitle', 'year'));
    }

    public function lotsCreate()
    {
        return view('blade.lots.form');
    }

    public function lotsShow(Lot $lot)
    {
        $lot->load(['contracts.tenant', 'contracts.paymentSchedules', 'contracts.payments']);

        $today = Carbon::today();

        // Eng so'nggi shartnoma (faol yoki tugagan) — tarixiy ma'lumotni
        // ko'rsatish uchun ham
        $contract = $lot->contracts->sortByDesc('id')->first();

        // Penyani bugungi sanaga MONOTON ravishda saqlab qo'yish
        if ($contract) {
            foreach ($contract->paymentSchedules as $schedule) {
                if ((float) $schedule->qoldiq_summa > 0) {
                    $schedule->calculatePenyaAtDate($today, true);
                }
            }
            $contract->load('paymentSchedules');
        }

        $stats = null;
        $currentMonth = null;
        if ($contract) {
            $approvedPayments = $contract->payments->where('holat', 'tasdiqlangan');
            $realPaid = (float) $approvedPayments->sum('summa');

            $refunds = $contract->payments->where('holat', 'qaytarilgan');
            $refundSum = (float) abs($refunds->sum('summa'));

            $netPaid = $realPaid - $refundSum;

            $overdueDebt = $contract->paymentSchedules->filter(function ($schedule) use ($today) {
                if ($schedule->qoldiq_summa <= 0) {
                    return false;
                }
                $paymentDate = $schedule->tolov_sanasi;
                return $paymentDate && Carbon::parse($paymentDate)->lt($today);
            })->sum('qoldiq_summa');

            $stats = [
                'jami_summa' => (float) $contract->shartnoma_summasi,
                'tolangan' => $netPaid,
                'qoldiq' => (float) $overdueDebt,
                'penya' => max(0.0,
                    (float) $contract->paymentSchedules->sum('penya_summasi')
                    - (float) $contract->paymentSchedules->sum('tolangan_penya')
                ),
                'real_payments' => $realPaid,
                'refunds' => $refundSum,
            ];

            $currentMonth = $this->buildContractCurrentMonth($contract, $today);
        }

        $scheduleService = new ScheduleDisplayService();
        if ($contract) {
            $periodService = \App\Services\ContractPeriodService::forContract($contract);
            $currentPeriod = $periodService->getCurrentPeriod();

            $periodDates = $currentPeriod ? [
                'start' => $currentPeriod['start'],
                'end' => $currentPeriod['end'],
            ] : null;

            $scheduleDisplayData = $scheduleService->getScheduleDisplayData($contract, $periodDates);
            $allSchedulesData = $scheduleService->getScheduleDisplayData($contract, null);
        } else {
            $empty = ['schedules' => [], 'is_contract_expired' => false, 'reference_date' => $today->format('Y-m-d')];
            $scheduleDisplayData = $empty;
            $allSchedulesData = $empty;
        }

        return view('blade.lots.show', compact(
            'lot', 'contract', 'stats',
            'scheduleDisplayData', 'allSchedulesData', 'currentMonth'
        ));
    }

    /**
     * Shartnomaning JORIY kalendar oyi bo'yicha qisqacha xulosasini qaytaradi.
     *
     * @return array{
     *   year:int, month:int, label:string,
     *   has_schedule:bool, schedule_id:?int,
     *   plan:float, paid:float, fakt_tushgan:float, debt:float, penalty:float,
     *   tolov_sanasi:?string, oxirgi_muddat:?string, effective_deadline:?string,
     *   is_overdue:bool, overdue_days:int, days_left:int, status:?string,
     *   sahifa_xulosa:string
     * }
     */
    private function buildContractCurrentMonth(Contract $contract, Carbon $today): array
    {
        $oyNomi = ['Yanvar','Fevral','Mart','Aprel','May','Iyun',
                   'Iyul','Avgust','Sentabr','Oktabr','Noyabr','Dekabr'];

        $schedule = $contract->paymentSchedules
            ->first(fn ($s) => (int) $s->yil === $today->year && (int) $s->oy === $today->month);

        if (!$schedule) {
            return [
                'year' => $today->year,
                'month' => $today->month,
                'label' => $oyNomi[$today->month - 1] . ' ' . $today->year,
                'has_schedule' => false,
                'schedule_id' => null,
                'plan' => 0.0,
                'paid' => 0.0,
                'fakt_tushgan' => 0.0,
                'debt' => 0.0,
                'penalty' => 0.0,
                'tolov_sanasi' => null,
                'oxirgi_muddat' => null,
                'effective_deadline' => null,
                'is_overdue' => false,
                'overdue_days' => 0,
                'days_left' => 0,
                'status' => null,
                'sahifa_xulosa' => 'Bu oyda to\'lov grafigi yo\'q.',
            ];
        }

        $effDeadline = $schedule->custom_oxirgi_muddat ?? $schedule->oxirgi_muddat;
        $deadlineCarbon = Carbon::parse($effDeadline);
        if (empty($schedule->custom_oxirgi_muddat) && (int) $schedule->oy_raqami === 1) {
            $deadlineCarbon = Carbon::parse($contract->boshlanish_sanasi)->startOfDay();
        }
        $todayStart = $today->copy()->startOfDay();
        $isOverdue = (float) $schedule->qoldiq_summa > 0 && $deadlineCarbon->lt($todayStart);
        $overdueDays = $isOverdue ? (int) $deadlineCarbon->diffInDays($todayStart) : 0;
        $daysLeft = $deadlineCarbon->isFuture() ? (int) $todayStart->diffInDays($deadlineCarbon->copy()->startOfDay()) : 0;

        $penalty = max(0.0, (float) $schedule->penya_summasi - (float) $schedule->tolangan_penya);

        $faktTushgan = (float) $contract->payments
            ->filter(function ($p) use ($schedule) {
                if ($p->holat !== 'tasdiqlangan') {
                    return false;
                }
                $d = Carbon::parse($p->tolov_sanasi);

                return (int) $d->month === (int) $schedule->oy && (int) $d->year === (int) $schedule->yil;
            })
            ->sum('summa');

        $debt = (float) $schedule->qoldiq_summa;

        if ($debt <= 0) {
            $xulosa = "Joriy oy to'liq to'langan.";
        } elseif ($isOverdue) {
            $xulosa = sprintf(
                "Bu oyda %s so'm to'lash kerak (muddati %d kun oldin o'tgan).",
                number_format($debt, 0, '.', ' '),
                $overdueDays
            );
        } else {
            $xulosa = sprintf(
                "Bu oy uchun %s so'm kutilmoqda (muddat %s, %d kun qoldi).",
                number_format($debt, 0, '.', ' '),
                $deadlineCarbon->format('d.m.Y'),
                $daysLeft
            );
        }

        return [
            'year' => (int) $schedule->yil,
            'month' => (int) $schedule->oy,
            'label' => $oyNomi[(int) $schedule->oy - 1] . ' ' . (int) $schedule->yil,
            'has_schedule' => true,
            'schedule_id' => $schedule->id,
            'plan' => (float) $schedule->tolov_summasi,
            'paid' => (float) $schedule->tolangan_summa,
            'fakt_tushgan' => $faktTushgan,
            'debt' => $debt,
            'penalty' => $penalty,
            'tolov_sanasi' => optional($schedule->tolov_sanasi)->format('Y-m-d'),
            'oxirgi_muddat' => optional($schedule->oxirgi_muddat)->format('Y-m-d'),
            'effective_deadline' => $deadlineCarbon->format('Y-m-d'),
            'is_overdue' => $isOverdue,
            'overdue_days' => $overdueDays,
            'days_left' => $daysLeft,
            'status' => $schedule->holat,
            'sahifa_xulosa' => $xulosa,
        ];
    }

    public function lotsStore(Request $request)
    {
        $validated = $request->validate([
            'lot_raqami' => 'required|string|unique:lots,lot_raqami',
            'obyekt_nomi' => 'required|string|max:255',
            'obyekt_turi' => 'nullable|in:savdo,xizmat,ishlab_chiqarish,ombor,ofis,boshqa',
            'holat' => 'nullable|in:bosh,ijarada,band,tamirlashda',
            'tuman' => 'nullable|string|max:100',
            'kocha' => 'nullable|string|max:255',
            'uy_raqami' => 'nullable|string|max:50',
            'manzil' => 'nullable|string',
            'maydon' => 'required|numeric|min:0',
            'xonalar_soni' => 'nullable|integer|min:0',
            'qavat' => 'nullable|integer|min:0',
            'qavatlar_soni' => 'nullable|integer|min:1',
            'kadastr_raqami' => 'nullable|string|max:100',
            'boshlangich_narx' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'map_url' => 'nullable|url',
            'tavsif' => 'nullable|string',
            'rasmlar.*' => 'nullable|image|max:5120',
        ]);

        // Handle utilities checkboxes
        $validated['has_elektr'] = $request->has('has_elektr');
        $validated['has_gaz'] = $request->has('has_gaz');
        $validated['has_suv'] = $request->has('has_suv');
        $validated['has_kanalizatsiya'] = $request->has('has_kanalizatsiya');
        $validated['has_internet'] = $request->has('has_internet');
        $validated['has_isitish'] = $request->has('has_isitish');
        $validated['has_konditsioner'] = $request->has('has_konditsioner');

        $validated['obyekt_turi'] = $validated['obyekt_turi'] ?? 'savdo';
        $validated['holat'] = $validated['holat'] ?? 'bosh';

        // Handle image uploads
        if ($request->hasFile('rasmlar')) {
            $rasmlar = [];
            foreach ($request->file('rasmlar') as $file) {
                $path = $file->store('lots', 'public');
                $rasmlar[] = $path;
            }
            $validated['rasmlar'] = $rasmlar;
        }

        Lot::create($validated);

        return redirect()->route('registry', ['tab' => 'lots'])->with('success', 'Lot muvaffaqiyatli yaratildi');
    }

    public function lotsEdit(Lot $lot)
    {
        return view('blade.lots.form', compact('lot'));
    }

    public function lotsUpdate(Request $request, Lot $lot)
    {
        $validated = $request->validate([
            'lot_raqami' => 'required|string|unique:lots,lot_raqami,' . $lot->id,
            'obyekt_nomi' => 'required|string|max:255',
            'obyekt_turi' => 'nullable|in:savdo,xizmat,ishlab_chiqarish,ombor,ofis,boshqa',
            'holat' => 'nullable|in:bosh,ijarada,band,tamirlashda',
            'tuman' => 'nullable|string|max:100',
            'kocha' => 'nullable|string|max:255',
            'uy_raqami' => 'nullable|string|max:50',
            'manzil' => 'nullable|string',
            'maydon' => 'required|numeric|min:0',
            'xonalar_soni' => 'nullable|integer|min:0',
            'qavat' => 'nullable|integer|min:0',
            'qavatlar_soni' => 'nullable|integer|min:1',
            'kadastr_raqami' => 'nullable|string|max:100',
            'boshlangich_narx' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'map_url' => 'nullable|url',
            'tavsif' => 'nullable|string',
            'rasmlar.*' => 'nullable|image|max:5120',
            'main_image_index' => 'nullable|integer|min:0',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer',
        ]);

        // Handle utilities checkboxes
        $validated['has_elektr'] = $request->has('has_elektr');
        $validated['has_gaz'] = $request->has('has_gaz');
        $validated['has_suv'] = $request->has('has_suv');
        $validated['has_kanalizatsiya'] = $request->has('has_kanalizatsiya');
        $validated['has_internet'] = $request->has('has_internet');
        $validated['has_isitish'] = $request->has('has_isitish');
        $validated['has_konditsioner'] = $request->has('has_konditsioner');

        // Get existing images
        $rasmlar = $lot->rasmlar ?? [];

        // Handle image deletion
        if ($request->has('delete_images') && !empty($request->delete_images)) {
            $deleteIndices = array_map('intval', $request->delete_images);
            rsort($deleteIndices); // Delete from end to preserve indices

            foreach ($deleteIndices as $index) {
                if (isset($rasmlar[$index])) {
                    // Delete file from storage
                    Storage::disk('public')->delete($rasmlar[$index]);
                    unset($rasmlar[$index]);
                }
            }
            // Re-index array
            $rasmlar = array_values($rasmlar);
        }

        // Handle new image uploads
        if ($request->hasFile('rasmlar')) {
            foreach ($request->file('rasmlar') as $file) {
                $path = $file->store('lots', 'public');
                $rasmlar[] = $path;
            }
        }

        $validated['rasmlar'] = $rasmlar;

        // Handle main image index
        $mainIndex = $request->input('main_image_index', 0);
        // Adjust main image index if images were deleted
        if ($mainIndex >= count($rasmlar)) {
            $mainIndex = max(0, count($rasmlar) - 1);
        }
        $validated['main_image_index'] = $mainIndex;

        // Remove delete_images from validated as it's not a model field
        unset($validated['delete_images']);

        $lot->update($validated);

        return redirect()->route('registry.lots.show', $lot)->with('success', 'Lot yangilandi');
    }

    public function lotsDestroy(Lot $lot)
    {
        if ($lot->holat === 'ijarada') {
            return back()->with('error', 'Ijarada bo\'lgan lotni o\'chirib bo\'lmaydi');
        }
        $lot->delete();
        return redirect()->route('registry', ['tab' => 'lots'])->with('success', 'Lot o\'chirildi');
    }

    // ==================== CONTRACTS ====================
    public function contractsIndex()
    {
        $contracts = Contract::with(['tenant', 'lot'])->latest('shartnoma_sanasi')->paginate(20);
        return view('blade.contracts.index', compact('contracts'));
    }

    public function contractsShow(Contract $contract)
    {
        $contract->load(['tenant', 'lot', 'payments', 'paymentSchedules']);
        return view('blade.contracts.show', compact('contract'));
    }

    public function contractsCreate()
    {
        $tenants = Tenant::orderBy('name')->get();
        $lots = Lot::where('holat', 'bosh')
            ->where('is_active', true)
            ->orderBy('lot_raqami')
            ->get();
        return view('blade.contracts.form', compact('tenants', 'lots'));
    }

    public function contractsStore(Request $request)
    {
        $validated = $request->validate([
            'lot_id' => 'required|exists:lots,id',
            'tenant_id' => 'required|exists:tenants,id',
            'shartnoma_raqami' => 'required|string|unique:contracts,shartnoma_raqami',
            'shartnoma_sanasi' => 'required|date',
            'auksion_sanasi' => 'required|date',
            'shartnoma_summasi' => 'required|numeric|min:0',
            'shartnoma_muddati' => 'required|integer|min:1',
            'boshlanish_sanasi' => 'required|date',
            'tolov_kuni' => 'nullable|integer|min:1|max:31',
            'penya_muddati' => 'nullable|integer|min:1|max:30',
        ]);

        $lot = Lot::findOrFail($validated['lot_id']);
        if ($lot->holat !== 'bosh') {
            return back()->with('error', 'Bu lot allaqachon band')->withInput();
        }

        $boshlanish = Carbon::parse($validated['boshlanish_sanasi']);
        $tugash = $boshlanish->copy()->addMonths($validated['shartnoma_muddati'])->subDay();
        $birinchiTolov = Contract::calculate10WorkingDays(Carbon::parse($validated['shartnoma_sanasi']));
        $oylikTolov = $validated['shartnoma_summasi'] / $validated['shartnoma_muddati'];
        $auksionXarajati = $validated['shartnoma_summasi'] * 0.01;

        $contract = Contract::create([
            ...$validated,
            'tugash_sanasi' => $tugash,
            'birinchi_tolov_sanasi' => $birinchiTolov,
            'oylik_tolovi' => $oylikTolov,
            'auksion_xarajati' => $auksionXarajati,
            'holat' => 'faol',
            'dalolatnoma_holati' => 'kutilmoqda',
            'tolov_kuni' => $validated['tolov_kuni'] ?? 10,
            'penya_muddati' => $validated['penya_muddati'] ?? 10,
        ]);

        $contract->generatePaymentSchedule();
        $lot->update(['holat' => 'ijarada']);

        return redirect()->route('registry.lots.show', $lot)->with('success', 'Shartnoma muvaffaqiyatli yaratildi');
    }

    public function contractsEdit(Contract $contract)
    {
        $contract->load(['lot', 'tenant']);
        $tenants = Tenant::orderBy('name')->get();
        return view('blade.contracts.form', compact('contract', 'tenants'));
    }

    public function contractsUpdate(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'shartnoma_sanasi' => 'required|date',
            'auksion_sanasi' => 'required|date',
            'izoh' => 'nullable|string',
            'tolov_kuni' => 'nullable|integer|min:1|max:31',
            'penya_muddati' => 'nullable|integer|min:1|max:30',
        ]);

        $contract->update($validated);

        return redirect()->route('registry.lots.show', $contract->lot)->with('success', 'Shartnoma yangilandi');
    }

    // ==================== PAYMENTS ====================
    public function paymentsIndex()
    {
        $payments = Payment::with(['contract.tenant'])->latest('tolov_sanasi')->paginate(20);
        return view('blade.payments.index', compact('payments'));
    }

    public function paymentsCreate(Request $request)
    {
        $contracts = Contract::with('tenant')->where('holat', 'faol')->get();
        $selectedContract = $request->get('contract_id');
        return view('blade.payments.form', compact('contracts', 'selectedContract'));
    }

    public function paymentsStore(Request $request)
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'tolov_sanasi' => 'required|date',
            'summa' => 'required|numeric|min:1',
            'tolov_usuli' => 'nullable|in:bank_otkazmasi,naqd,karta',
            'hujjat_raqami' => 'nullable|string|max:100',
            'izoh' => 'nullable|string',
        ]);

        $contract = Contract::with('paymentSchedules')->findOrFail($validated['contract_id']);

        if ($contract->holat !== 'faol') {
            return back()->with('error', 'Faqat faol shartnomaga to\'lov qabul qilinadi')->withInput();
        }

        // Dublicate himoyasi (server tomondan)
        if (!$request->boolean('force')) {
            $duplicate = Payment::where('contract_id', $contract->id)
                ->whereDate('tolov_sanasi', $validated['tolov_sanasi'])
                ->where('summa', $validated['summa'])
                ->where('holat', 'tasdiqlangan')
                ->first();
            if ($duplicate) {
                return back()
                    ->with('error', sprintf(
                        "Ushbu shartnoma uchun %s sanasida %s so'm to'lov (№ %s) allaqachon mavjud. Takror kiritish uchun 'Takror saqlash' tugmasini bosing.",
                        \Carbon\Carbon::parse($duplicate->tolov_sanasi)->format('d.m.Y'),
                        number_format((float) $duplicate->summa, 0, '.', ' '),
                        $duplicate->tolov_raqami
                    ))
                    ->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $tolovRaqami = Payment::generateTolovRaqami();

            $payment = Payment::create([
                'contract_id' => $contract->id,
                'tolov_raqami' => $tolovRaqami,
                'tolov_sanasi' => $validated['tolov_sanasi'],
                'summa' => $validated['summa'],
                'tolov_usuli' => $validated['tolov_usuli'] ?? 'bank_otkazmasi',
                'hujjat_raqami' => $validated['hujjat_raqami'] ?? null,
                'izoh' => $validated['izoh'] ?? null,
                'holat' => 'tasdiqlangan',
                'tasdiqlangan_sana' => now(),
            ]);

            app(PaymentApplicator::class)->apply($payment, $contract);

            DB::commit();
            return redirect()->route('registry.lots.show', $contract->lot)->with('success', 'To\'lov muvaffaqiyatli qabul qilindi');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Xatolik: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Tasdiqlangan to'lovni bekor qilish (qaytarilgan holatiga o'tkazish).
     *
     * `Api\PaymentController::cancel` bilan bir xil ichki logikani chaqiramiz,
     * lekin web marshruti orqali redirect qaytaramiz.
     */
    public function paymentsCancel(Request $request, Payment $payment)
    {
        if ($payment->holat !== 'tasdiqlangan') {
            return back()->with('error', 'Faqat tasdiqlangan to\'lovni bekor qilish mumkin');
        }

        try {
            app(\App\Http\Controllers\Api\PaymentController::class, ['applicator' => app(PaymentApplicator::class)])
                ->cancel($payment);
            return back()->with('success', "To'lov №{$payment->tolov_raqami} bekor qilindi");
        } catch (\Throwable $e) {
            return back()->with('error', 'Xatolik: ' . $e->getMessage());
        }
    }

    // ==================== IMPORT STATISTICS ====================
    public function importStats()
    {
        $bugun = Carbon::today();

        // Basic counts
        $stats = [
            'lots_count' => Lot::count(),
            'tenants_count' => Tenant::count(),
            'contracts_count' => Contract::count(),
            'active_contracts' => Contract::where('holat', 'faol')->count(),
            'schedules_count' => \App\Models\PaymentSchedule::count(),
            'payments_count' => Payment::count(),
        ];

        // Payment schedule statistics
        $scheduleStats = [
            'tolangan' => \App\Models\PaymentSchedule::where('holat', 'tolangan')->count(),
            'qisman_tolangan' => \App\Models\PaymentSchedule::where('holat', 'qisman_tolangan')->count(),
            'tolanmagan' => \App\Models\PaymentSchedule::where('holat', 'tolanmagan')->count(),
            'kutilmoqda' => \App\Models\PaymentSchedule::where('holat', 'kutilmoqda')->count(),
        ];

        // Financial summary
        $totalPlan = \App\Models\PaymentSchedule::sum('tolov_summasi');
        $totalPaid = \App\Models\PaymentSchedule::sum('tolangan_summa');
        $totalDebt = \App\Models\PaymentSchedule::sum('qoldiq_summa');
        $totalPenya = \App\Models\PaymentSchedule::sum('penya_summasi');

        // Overdue calculations (using effective deadline)
        $overdueSchedules = \App\Models\PaymentSchedule::whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) < ?', [$bugun])
            ->where('qoldiq_summa', '>', 0)
            ->get();
        $overdueDebt = $overdueSchedules->sum('qoldiq_summa');
        $overdueCount = $overdueSchedules->count();

        // Not yet due (using effective deadline)
        $notYetDue = \App\Models\PaymentSchedule::whereRaw('COALESCE(custom_oxirgi_muddat, oxirgi_muddat) >= ?', [$bugun])
            ->where('qoldiq_summa', '>', 0)
            ->get();
        $notYetDueDebt = $notYetDue->sum('qoldiq_summa');
        $notYetDueCount = $notYetDue->count();

        // Recent payments (last 30 days)
        $recentPayments = Payment::with(['contract.tenant', 'contract.lot'])
            ->where('tolov_sanasi', '>=', $bugun->copy()->subDays(30))
            ->orderBy('tolov_sanasi', 'desc')
            ->limit(20)
            ->get();

        // Contracts without payments
        $contractsWithoutPayments = Contract::where('holat', 'faol')
            ->whereDoesntHave('payments')
            ->with(['tenant', 'lot'])
            ->get();

        // Tenants with multiple contracts
        $tenantsMultipleContracts = Tenant::withCount('contracts')
            ->having('contracts_count', '>', 1)
            ->get();

        // Lots with issues (empty lot numbers or missing data)
        $lotsWithIssues = Lot::where(function($q) {
            $q->whereNull('lot_raqami')
              ->orWhere('lot_raqami', '')
              ->orWhere('lot_raqami', 'LIKE', '%-%'); // Has suffix meaning duplicate was found
        })->get();

        // Payment matching summary (estimated from imported payments)
        $importedPayments = Payment::where('izoh', 'LIKE', '%Imported from FACT CSV%')->count();
        $matchedByLot = Payment::where('izoh', 'LIKE', '%Matched by: lot_number%')->count();
        $matchedByInn = Payment::where('izoh', 'LIKE', '%Matched by: inn%')->count();
        $matchedByName = Payment::where('izoh', 'LIKE', '%Matched by: tenant_name%')->count();

        // Get all matched payments with details
        $allMatchedPayments = Payment::with(['contract.tenant', 'contract.lot'])
            ->where('izoh', 'LIKE', '%Imported from FACT CSV%')
            ->orderBy('tolov_sanasi', 'desc')
            ->get();

        // Get unmatched payments from CSV
        $unmatchedPayments = $this->getUnmatchedFromCsv();

        return view('import-stats', compact(
            'stats', 'scheduleStats',
            'totalPlan', 'totalPaid', 'totalDebt', 'totalPenya',
            'overdueDebt', 'overdueCount', 'notYetDueDebt', 'notYetDueCount',
            'recentPayments', 'contractsWithoutPayments', 'tenantsMultipleContracts',
            'lotsWithIssues', 'importedPayments', 'matchedByLot', 'matchedByInn', 'matchedByName',
            'allMatchedPayments', 'unmatchedPayments'
        ));
    }

    /**
     * Parse sherali_fact.csv and return payments that couldn't be matched
     * Format: [0]Date [1]Account [2]DocNum [6]Amount [7]Purpose
     */
    private function getUnmatchedFromCsv()
    {
        $result = collect();
        $csvPath = public_path('dataset/sherali_fact.csv');
        if (!file_exists($csvPath)) return $result;

        $existingLots = Lot::pluck('id', 'lot_raqami')->toArray();
        $existingInns = Tenant::pluck('id', 'inn')->toArray();
        $existingNames = Tenant::pluck('id', 'name')->mapWithKeys(function($id, $name) {
            return [mb_strtolower(trim($name)) => $id];
        })->toArray();

        $normalizedLots = [];
        foreach ($existingLots as $lot => $id) {
            $normalized = preg_replace('/[^0-9]/', '', $lot);
            if ($normalized) $normalizedLots[$normalized] = $id;
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) return $result;

        fgetcsv($handle, 0, ';'); // Skip header
        $rowNum = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNum++;
            if (count($row) < 7) continue;

            $date = trim($row[0] ?? '');
            $docNumber = trim($row[2] ?? '');
            $amountStr = trim($row[6] ?? '');
            $purpose = trim($row[7] ?? '');

            // Parse amount ("16 855 693,80" format)
            $amount = (float) str_replace([' ', ','], ['', '.'], $amountStr);
            if ($amount <= 0) continue;

            // Check if rental payment
            $isRental = preg_match('/lotdan|SAYILGOH|auksion|ijara|L\d{5,}L/i', $purpose);
            if (!$isRental) continue;

            // Extract lot number from purpose (L{digits}L)
            $lotNumber = '';
            if (preg_match('/L(\d{6,10})L/i', $purpose, $m)) {
                $lotNumber = $m[1];
            }

            // Extract INN/PINFL from purpose
            $inn = '';
            if (preg_match('/(?:INN|PINFL)\s*[:=]?\s*(\d{9,14})/i', $purpose, $m)) {
                $inn = $m[1];
            }

            // Extract tenant name
            $tenantName = '';
            if (preg_match('/G`olib\s*[:=]?\s*"?([^"]+)"?\s*(?:MCHJ|XK|DUK|YaTT|xususiy|,|Buyurtmachi|$)/ui', $purpose, $m)) {
                $tenantName = trim($m[1], ' "\'');
            }

            // Check if matched
            $matched = false;

            if (!empty($lotNumber) && isset($normalizedLots[$lotNumber])) {
                $matched = true;
            }

            if (!$matched && !empty($inn)) {
                if (isset($existingInns[$inn])) {
                    $matched = true;
                } elseif (strlen($inn) >= 9 && isset($existingInns[substr($inn, -9)])) {
                    $matched = true;
                }
            }

            if (!$matched && !empty($tenantName)) {
                if (isset($existingNames[mb_strtolower(trim($tenantName))])) $matched = true;
            }

            if (!$matched) {
                $result->push([
                    'date' => $date,
                    'lot_number' => $lotNumber ?: '-',
                    'inn' => $inn ?: '-',
                    'tenant_name' => $tenantName ?: '-',
                    'amount' => $amount,
                    'doc_number' => $docNumber,
                    'purpose' => mb_substr($purpose, 0, 60),
                ]);
            }
        }
        fclose($handle);

        return $result;
    }
}
