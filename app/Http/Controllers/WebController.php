<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class WebController extends Controller
{
    // ==================== DASHBOARD ====================
    public function dashboard(Request $request)
    {
        $year = $request->get('year'); // null = all years by default
        $period = $request->get('period', 'month'); // month, quarter, year
        $search = $request->get('search', '');
        $status = $request->get('status', 'all'); // all, qarzdor, tolangan, muddati_otgan
        $minAmount = $request->get('min_amount', null);
        $maxAmount = $request->get('max_amount', null);

        $bugun = Carbon::today();

        // Base query for contracts - APPLY YEAR FILTER only if specified
        $contractsQuery = Contract::with(['paymentSchedules', 'tenant', 'lot'])
            ->where('holat', 'faol');

        // Only filter by year if explicitly set
        if ($year) {
            $contractsQuery->whereYear('boshlanish_sanasi', $year);
        }

        // Apply search filter
        if ($search) {
            $contractsQuery->where(function($q) use ($search) {
                $q->where('shartnoma_raqami', 'like', "%{$search}%")
                  ->orWhereHas('tenant', fn($tq) => $tq->where('name', 'like', "%{$search}%")->orWhere('inn', 'like', "%{$search}%"))
                  ->orWhereHas('lot', fn($lq) => $lq->where('lot_raqami', 'like', "%{$search}%")->orWhere('obyekt_nomi', 'like', "%{$search}%"));
            });
        }

        // Apply amount filter
        if ($minAmount) {
            $contractsQuery->where('shartnoma_summasi', '>=', $minAmount);
        }
        if ($maxAmount) {
            $contractsQuery->where('shartnoma_summasi', '<=', $maxAmount);
        }

        $contracts = $contractsQuery->get();

        // Apply status filter after loading (needs calculated fields)
        if ($status === 'qarzdor') {
            $contracts = $contracts->filter(fn($c) => $c->paymentSchedules->sum('qoldiq_summa') > 0);
        } elseif ($status === 'tolangan') {
            $contracts = $contracts->filter(fn($c) => $c->paymentSchedules->sum('qoldiq_summa') <= 0);
        } elseif ($status === 'muddati_otgan') {
            $contracts = $contracts->filter(function($c) use ($bugun) {
                return $c->paymentSchedules->filter(function($s) use ($bugun) {
                    $effectiveDeadline = $s->custom_oxirgi_muddat ?? $s->oxirgi_muddat;
                    return Carbon::parse($effectiveDeadline)->lt($bugun) && $s->qoldiq_summa > 0;
                })->count() > 0;
            });
        }

        /*
         * ═══════════════════════════════════════════════════════════════════════
         * QARZ HISOBLASH FORMULASI (Debt Calculation Formula)
         * ═══════════════════════════════════════════════════════════════════════
         *
         * 1. JAMI TO'LANGAN = SUM(tolangan_summa) from payment_schedules
         *    - Bu - haqiqatda to'langan pul miqdori
         *
         * 2. JAMI KUTILGAN (Plan) = SUM(tolov_summasi) from payment_schedules WHERE tolov_sanasi <= bugun
         *    - Bu - bugungi kunga qadar to'lanishi kerak bo'lgan summa
         *
         * 3. MUDDATI O'TGAN QARZ = SUM(qoldiq_summa) WHERE oxirgi_muddat < bugun
         *    - Bu - to'lov muddati o'tgan, lekin to'lanmagan summa (HAQIQIY QARZ)
         *
         * 4. MUDDATI O'TMAGAN QARZ = SUM(qoldiq_summa) WHERE oxirgi_muddat >= bugun
         *    - Bu - hali to'lov muddati kelmagan summa
         *
         * 5. PENYA = qoldiq_summa × 0.4% × kechikish_kunlari (max 50%)
         *    - Har bir kechikkan kun uchun 0.4% jarima
         *
         * 6. JAMI QOLDIQ = shartnoma_summasi - jami_tolangan
         *    - Shartnomadan qolgan umumiy summa
         * ═══════════════════════════════════════════════════════════════════════
         */

        // Calculate stats with CORRECT debt formulas
        // Only count debt where payment is actually due (oxirgi_muddat < today)
        $jamiMuddatiOtganQarz = 0;
        $jamiMuddatiOtmaganQarz = 0;
        $jamiTolangan = 0;
        $jamiPenya = 0;
        $jamiKutilgan = 0; // Plan: what should have been paid by now

        foreach ($contracts as $contract) {
            foreach ($contract->paymentSchedules as $schedule) {
                $jamiTolangan += $schedule->tolangan_summa;
                $jamiPenya += ($schedule->penya_summasi - $schedule->tolangan_penya);

                // Kutilgan: only schedules where payment date has passed
                if (Carbon::parse($schedule->tolov_sanasi)->lte($bugun)) {
                    $jamiKutilgan += $schedule->tolov_summasi;
                }

                if ($schedule->qoldiq_summa > 0) {
                    // Use effective deadline (custom if set, otherwise original)
                    $effectiveDeadline = $schedule->custom_oxirgi_muddat ?? $schedule->oxirgi_muddat;
                    if (Carbon::parse($effectiveDeadline)->lt($bugun)) {
                        $jamiMuddatiOtganQarz += $schedule->qoldiq_summa;
                    } else {
                        // Not yet due
                        $jamiMuddatiOtmaganQarz += $schedule->qoldiq_summa;
                    }
                }
            }
        }

                // Calculate counts for each filter type FROM FILTERED CONTRACTS
        $muddatiOtganCount = 0;
        $penyaCount = 0;
        $tolanganCount = 0;
        $kutilmoqdaCount = 0;
        $qarzdorCount = 0;

        // Calculate counts from filtered contracts (respects year filter)
        foreach ($contracts as $contract) {
            $lotQarz = 0;
            $lotPenya = 0;
            $lotTolangan = 0;
            $lotKutilmoqda = 0;
            $lotKechikishKunlari = 0;

            foreach ($contract->paymentSchedules as $schedule) {
                $lotTolangan += $schedule->tolangan_summa;

                if ($schedule->qoldiq_summa > 0) {
                    // Use effective deadline
                    $effectiveDeadline = $schedule->custom_oxirgi_muddat ?? $schedule->oxirgi_muddat;
                    $oxirgiMuddat = Carbon::parse($effectiveDeadline);
                    if ($oxirgiMuddat->lt($bugun)) {
                        $lotQarz += $schedule->qoldiq_summa;
                        $days = $oxirgiMuddat->diffInDays($bugun);
                        $lotKechikishKunlari = max($lotKechikishKunlari, $days);
                        $penyaCalc = $schedule->qoldiq_summa * 0.0004 * $days;
                        $maxPenya = $schedule->qoldiq_summa * 0.5;
                        $lotPenya += min($penyaCalc, $maxPenya);
                    } else {
                        $lotKutilmoqda += $schedule->qoldiq_summa;
                    }
                }
            }

            if ($lotQarz > 0 && $lotKechikishKunlari > 0) $muddatiOtganCount++;
            if ($lotPenya > 0) $penyaCount++;
            if ($lotTolangan > 0) $tolanganCount++;
            if ($lotKutilmoqda > 0) $kutilmoqdaCount++;
            if (($lotQarz + $lotKutilmoqda) > 0) $qarzdorCount++;
        }

                // Count unique lots from filtered contracts
        $filteredLotIds = $contracts->pluck('lot_id')->unique();

        // Calculate total area (umumiy maydon) from active lots
        $umumiyMaydon = Lot::whereIn('id', $filteredLotIds)->sum('maydon');

        $stats = [
            'faol_shartnomalar' => $contracts->count(),
            'jami_shartnoma_summasi' => $contracts->sum('shartnoma_summasi'),
            'jami_tolangan' => $jamiTolangan,
            'jami_kutilgan' => $jamiKutilgan, // Plan: expected payments till today
            'jami_qarzdorlik' => $jamiMuddatiOtganQarz, // ONLY past due debt
            'muddati_otmagan_qarz' => $jamiMuddatiOtmaganQarz, // Not yet due
            'jami_qoldiq' => $jamiMuddatiOtganQarz + $jamiMuddatiOtmaganQarz, // Total remaining
            'jami_penya' => max(0, $jamiPenya),
            'qarzdorlar_soni' => $contracts->filter(function($c) use ($bugun) {
                return $c->paymentSchedules->filter(function($s) use ($bugun) {
                    $effectiveDeadline = $s->custom_oxirgi_muddat ?? $s->oxirgi_muddat;
                    return Carbon::parse($effectiveDeadline)->lt($bugun) && $s->qoldiq_summa > 0;
                })->count() > 0;
            })->count(),
            'jami_lotlar' => $filteredLotIds->count(), // From filtered contracts
            'ijaradagi_lotlar' => $contracts->count(), // Contracts = active lots in this year
            'bosh_lotlar' => Lot::where('holat', 'bosh')->count(),
            'jami_ijarachilar' => Tenant::count(), // All tenants (consistent with tenants page)
            'umumiy_maydon' => $umumiyMaydon, // Total area in m²
            // Counts for card links
            'muddati_otgan_count' => $muddatiOtganCount,
            'penya_count' => $penyaCount,
            'tolangan_count' => $tolanganCount,
            'kutilmoqda_count' => $kutilmoqdaCount,
            'qarzdor_count' => $qarzdorCount,
        ];

        // Years list
        $years = Contract::selectRaw('YEAR(boshlanish_sanasi) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        if (empty($years)) {
            $years = [date('Y')];
        }

        // Chart data - now respects year and period filter
        $chartData = $this->getChartData($year, $period, $contracts);

        // Filtered contracts list for display
        $filteredContracts = $contracts->map(function ($c) use ($bugun) {
            // Only past-due debt counts as real debt (using effective deadline)
            $c->qarz = $c->paymentSchedules
                ->filter(function($s) use ($bugun) {
                    $effectiveDeadline = $s->custom_oxirgi_muddat ?? $s->oxirgi_muddat;
                    return Carbon::parse($effectiveDeadline)->lt($bugun);
                })
                ->sum('qoldiq_summa');
            $c->penya = $c->paymentSchedules->sum('penya_summasi') - $c->paymentSchedules->sum('tolangan_penya');
            $c->tolangan = $c->paymentSchedules->sum('tolangan_summa');
            $overdueSchedules = $c->paymentSchedules->filter(function($s) use ($bugun) {
                $effectiveDeadline = $s->custom_oxirgi_muddat ?? $s->oxirgi_muddat;
                return Carbon::parse($effectiveDeadline)->lt($bugun) && $s->qoldiq_summa > 0;
            });
            $c->kechikish_kunlari = $overdueSchedules->count() > 0
                ? $overdueSchedules->max(function($s) use ($bugun) {
                    $effectiveDeadline = $s->custom_oxirgi_muddat ?? $s->oxirgi_muddat;
                    return Carbon::parse($effectiveDeadline)->diffInDays($bugun);
                })
                : 0;
            return $c;
        })->sortByDesc('qarz')->take(20)->values();

        // Recent payments
        $recentPayments = Payment::with(['contract.tenant'])
            ->latest('tolov_sanasi')
            ->take(10)
            ->get();

        // Filter params to pass to view
        $filters = [
            'search' => $search,
            'status' => $status,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
        ];

        return view('home', compact(
            'stats', 'years', 'year', 'period', 'chartData',
            'filteredContracts', 'recentPayments', 'filters'
        ));
    }

    private function getChartData($year, $period, $contracts = null)
    {
        $data = [
            'labels' => [],
            'kutilgan' => [],
            'tolangan' => [],
            'qarz' => [],
            'penya' => [],
        ];

        // Use current year for charts if no year specified
        $chartYear = $year ?: date('Y');

        $bugun = Carbon::today();

        // Only filter by contract IDs if a specific year was selected
        // When showing all years, show all data for the chart year
        $contractIds = ($year && $contracts) ? $contracts->pluck('id')->toArray() : [];

        if ($period === 'month') {
            $months = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyn', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
            for ($m = 1; $m <= 12; $m++) {
                $data['labels'][] = $months[$m - 1];

                $query = \App\Models\PaymentSchedule::whereYear('tolov_sanasi', $chartYear)
                    ->whereMonth('tolov_sanasi', $m);

                if (!empty($contractIds)) {
                    $query->whereIn('contract_id', $contractIds);
                }

                $schedules = $query->get();

                $data['kutilgan'][] = $schedules->sum('tolov_summasi');
                $data['tolangan'][] = $schedules->sum('tolangan_summa');
                // Only count past-due debt
                $data['qarz'][] = $schedules->filter(fn($s) =>
                    Carbon::parse($s->oxirgi_muddat)->lt($bugun) && $s->qoldiq_summa > 0
                )->sum('qoldiq_summa');
                $data['penya'][] = $schedules->sum('penya_summasi') - $schedules->sum('tolangan_penya');
            }
        } elseif ($period === 'quarter') {
            $quarters = ['Q1 (Yan-Mar)', 'Q2 (Apr-Iyn)', 'Q3 (Iyl-Sen)', 'Q4 (Okt-Dek)'];
            $quarterMonths = [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10, 11, 12]];

            foreach ($quarters as $i => $label) {
                $data['labels'][] = $label;

                $query = \App\Models\PaymentSchedule::whereYear('tolov_sanasi', $chartYear)
                    ->whereIn(DB::raw('MONTH(tolov_sanasi)'), $quarterMonths[$i]);

                if (!empty($contractIds)) {
                    $query->whereIn('contract_id', $contractIds);
                }

                $schedules = $query->get();

                $data['kutilgan'][] = $schedules->sum('tolov_summasi');
                $data['tolangan'][] = $schedules->sum('tolangan_summa');
                $data['qarz'][] = $schedules->filter(fn($s) =>
                    Carbon::parse($s->oxirgi_muddat)->lt($bugun) && $s->qoldiq_summa > 0
                )->sum('qoldiq_summa');
                $data['penya'][] = $schedules->sum('penya_summasi') - $schedules->sum('tolangan_penya');
            }
        } else {
            // Yearly - last 5 years
            for ($y = $chartYear - 4; $y <= $chartYear; $y++) {
                $data['labels'][] = $y;

                $query = \App\Models\PaymentSchedule::whereYear('tolov_sanasi', $y);

                if (!empty($contractIds)) {
                    $query->whereIn('contract_id', $contractIds);
                }

                $schedules = $query->get();

                $data['kutilgan'][] = $schedules->sum('tolov_summasi');
                $data['tolangan'][] = $schedules->sum('tolangan_summa');
                $data['qarz'][] = $schedules->filter(fn($s) =>
                    Carbon::parse($s->oxirgi_muddat)->lt($bugun) && $s->qoldiq_summa > 0
                )->sum('qoldiq_summa');
                $data['penya'][] = $schedules->sum('penya_summasi') - $schedules->sum('tolangan_penya');
            }
        }

        return $data;
    }

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
        $activeLots = Lot::whereHas('contracts', function($q) {
            $q->where('holat', 'faol');
        })->count();
        $vacantLots = Lot::where('holat', 'bosh')->count();
        $umumiyMaydon = Lot::sum('maydon');

        // Contracts - count all active without year filter for main stats
        $activeContracts = Contract::where('holat', 'faol')->count();
        $totalContractValue = Contract::where('holat', 'faol')->sum('shartnoma_summasi');
        $expiredContracts = Contract::where('holat', '!=', 'faol')->count();

        // Tenants
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::whereHas('contracts', function($q) {
            $q->where('holat', 'faol');
        })->count();

        // Build Payment Schedules query with filters
        $schedulesQuery = \App\Models\PaymentSchedule::query();

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
        $totalPaid = $filteredSchedules->sum('tolangan_summa');
        $totalDebt = $filteredSchedules->sum('qoldiq_summa');
        $totalPenya = max(0, $filteredSchedules->sum('penya_summasi') - $filteredSchedules->sum('tolangan_penya'));

        // Payment Statistics - with year filter
        $paymentsQuery = Payment::query();
        if ($year) {
            $paymentsQuery->whereYear('tolov_sanasi', $year);
        }
        $totalPayments = $paymentsQuery->count();

        $thisMonthPayments = Payment::whereMonth('tolov_sanasi', $bugun->month)
            ->whereYear('tolov_sanasi', $bugun->year)->count();
        $thisMonthSum = Payment::whereMonth('tolov_sanasi', $bugun->month)
            ->whereYear('tolov_sanasi', $bugun->year)->sum('summa');

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

        return view('data-center', compact(
            'totalLots', 'activeLots', 'vacantLots', 'umumiyMaydon',
            'activeContracts', 'expiredContracts', 'totalContractValue',
            'totalTenants', 'activeTenants',
            'totalPayments', 'thisMonthPayments', 'thisMonthSum',
            'totalPlan', 'totalPaid', 'totalDebt', 'totalPenya',
            'overdueDebt', 'overdueCount', 'notYetDueDebt', 'notYetDueCount',
            'paidPercent', 'debtPercent', 'overduePercent',
            'monthlyData', 'statusData', 'districtData',
            'years', 'year', 'period', 'status', 'chartYear'
        ));
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
        $lotsQuery = Lot::with(['contracts' => function($q) {
            $q->where('holat', 'faol')->with(['tenant', 'paymentSchedules']);
        }]);
        if ($search) {
            $lotsQuery->where(function ($q) use ($search) {
                $q->where('lot_raqami', 'like', "%{$search}%")
                  ->orWhere('obyekt_nomi', 'like', "%{$search}%")
                  ->orWhere('tuman', 'like', "%{$search}%");
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
        $payments = $paymentsQuery->latest('tolov_sanasi')->paginate(20, ['*'], 'payments_page')->withQueryString();

        // Counts for badges
        $counts = [
            'tenants' => Tenant::count(),
            'lots' => Lot::count(),
            'payments' => Payment::count(),
        ];

        return view('registry', compact('tenants', 'lots', 'payments', 'tab', 'search', 'counts'));
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
                            $penyaCalc = $schedule->qoldiq_summa * 0.0004 * $days;
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

        // Get active contract
        $contract = $lot->contracts->where('holat', 'faol')->first();

        // Calculate penalties for active contract
        if ($contract) {
            foreach ($contract->paymentSchedules as $schedule) {
                if ($schedule->qoldiq_summa > 0) {
                    $schedule->calculatePenya();
                }
            }
            // Reload to get updated penalty values
            $contract->load('paymentSchedules');
        }

        // Calculate statistics from REAL payments (not calculated schedules)
        $stats = null;
        if ($contract) {
            // Get only approved payments (not refunds)
            $approvedPayments = $contract->payments->where('holat', 'tasdiqlangan');
            $realPaid = $approvedPayments->sum('summa');

            // Get refunds
            $refunds = $contract->payments->where('holat', 'qaytarilgan');
            $refundSum = abs($refunds->sum('summa'));

            // Net paid = real payments - refunds
            $netPaid = $realPaid - $refundSum;

            $stats = [
                'jami_summa' => $contract->shartnoma_summasi,
                'tolangan' => $netPaid, // Real payments minus refunds
                'qoldiq' => max(0, $contract->shartnoma_summasi - $netPaid),
                'penya' => $contract->paymentSchedules->sum('penya_summasi') - $contract->paymentSchedules->sum('tolangan_penya'),
                'real_payments' => $realPaid,
                'refunds' => $refundSum,
            ];
        }

        return view('blade.lots.show', compact('lot', 'contract', 'stats'));
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
        ]);

        $contract = Contract::with('paymentSchedules')->findOrFail($validated['contract_id']);

        if ($contract->holat !== 'faol') {
            return back()->with('error', 'Faqat faol shartnomaga to\'lov qabul qilinadi')->withInput();
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
                'holat' => 'tasdiqlangan',
                'tasdiqlangan_sana' => now(),
            ]);

            // Apply FIFO payment
            $this->applyPaymentFIFO($payment, $contract);

            DB::commit();
            return redirect()->route('registry.lots.show', $contract->lot)->with('success', 'To\'lov muvaffaqiyatli qabul qilindi');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Xatolik: ' . $e->getMessage())->withInput();
        }
    }

    private function applyPaymentFIFO(Payment $payment, Contract $contract): void
    {
        $qoldiqSumma = $payment->summa;
        $asosiyQarzUchun = 0;
        $penyaUchun = 0;

        $schedules = $contract->paymentSchedules()
            ->whereIn('holat', ['tolanmagan', 'qisman_tolangan', 'kutilmoqda'])
            ->orderBy('oy_raqami')
            ->get();

        foreach ($schedules as $schedule) {
            if ($qoldiqSumma <= 0) break;

            $schedule->calculatePenya();

            // Pay penalty first
            $qoldiqPenya = $schedule->penya_summasi - $schedule->tolangan_penya;
            if ($qoldiqPenya > 0 && $qoldiqSumma > 0) {
                $penyaTolov = min($qoldiqPenya, $qoldiqSumma);
                $schedule->tolangan_penya += $penyaTolov;
                $penyaUchun += $penyaTolov;
                $qoldiqSumma -= $penyaTolov;
            }

            // Then pay principal
            if ($schedule->qoldiq_summa > 0 && $qoldiqSumma > 0) {
                $asosiyTolov = min($schedule->qoldiq_summa, $qoldiqSumma);
                $schedule->tolangan_summa += $asosiyTolov;
                $schedule->qoldiq_summa -= $asosiyTolov;
                $asosiyQarzUchun += $asosiyTolov;
                $qoldiqSumma -= $asosiyTolov;
            }

            $schedule->updateStatus();
            $schedule->save();
        }

        $payment->asosiy_qarz_uchun = $asosiyQarzUchun;
        $payment->penya_uchun = $penyaUchun;
        $payment->save();
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
