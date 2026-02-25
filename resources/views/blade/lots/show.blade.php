@extends('layouts.dark')
@section('title', 'Lot: ' . $lot->lot_raqami)
@section('header', 'Lot ' . $lot->lot_raqami)
@section('subheader', $lot->obyekt_nomi)
@section('header-actions')
<a href="{{ route('registry.lots.edit', $lot) }}" class="btn btn-secondary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
    Tahrirlash
</a>
@if(!$contract)
<a href="{{ route('registry.contracts.create', ['lot_id' => $lot->id]) }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Shartnoma yaratish
</a>
@endif
@endsection

@php
$images = $lot->rasmlar ?? [];
$mainIndex = $lot->main_image_index ?? 0;
$mainImage = $images[$mainIndex] ?? ($images[0] ?? null);
@endphp

@php
function formatLotSum($num) {
    if ($num >= 1000000000) return number_format($num / 1000000000, 2, ',', ' ') . ' <span class="text-sm font-normal text-gray-400">mlrd</span>';
    if ($num >= 1000000) return number_format($num / 1000000, 2, ',', ' ') . ' <span class="text-sm font-normal text-gray-400">mln</span>';
    return number_format($num, 0, ',', ' ');
}
@endphp

@section('content')
<div x-data="lotDetail()" x-init="init()">
    <!-- Status Badge -->
    <div class="flex items-center gap-3 mb-6">
        @if($lot->holat === 'ijarada')
        <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-blue-100 text-blue-700 rounded">Ijarada</span>
        @elseif($lot->holat === 'bosh')
        <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-green-100 text-green-700 rounded">Bo'sh</span>
        @else
        <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-600 rounded">{{ $lot->holat_nomi }}</span>
        @endif
        @if($contract)
        <span class="text-sm text-gray-500">
            Shartnoma: <a href="{{ route('registry.contracts.show', $contract) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $contract->shartnoma_raqami }}</a> • {{ \Carbon\Carbon::parse($contract->boshlanish_sanasi)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($contract->tugash_sanasi)->format('d.m.Y') }}
            @if($contract->is_expired)
                <span class="ml-2 px-2 py-0.5 text-xs bg-gray-600 text-white rounded">MUDDATI TUGAGAN</span>
            @endif
        </span>
        @endif
    </div>

    @if($contract && $stats)
    <!-- Contract Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Shartnoma summasi -->
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-slate-700/50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">SHARTNOMA SUMMASI</p>
            <p class="text-4xl font-bold text-white mt-3">{!! formatLotSum($stats['jami_summa']) !!}</p>
            <p class="text-xs text-slate-500 mt-4">Shartnoma bo'yicha jami</p>
        </div>

        <!-- To'langan -->
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 border-l-green-500 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-green-500/10 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">TO'LANGAN</p>
            <p class="text-4xl font-bold text-green-400 mt-3">{!! formatLotSum($stats['tolangan']) !!}</p>
            <p class="text-xs text-slate-500 mt-4">Fakt tushum</p>
        </div>

        <!-- Qoldiq -->
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 border-l-red-500 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-red-500/10 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">QOLDIQ</p>
            <p class="text-4xl font-bold {{ $stats['qoldiq'] > 0 ? 'text-red-400' : 'text-white' }} mt-3">{!! formatLotSum($stats['qoldiq']) !!}</p>
            <p class="text-xs text-slate-500 mt-4">To'lanmagan summa</p>
        </div>

        <!-- Penya -->
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 border-l-amber-500 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-amber-500/10 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">PENYA</p>
            <p class="text-4xl font-bold {{ $stats['penya'] > 0 ? 'text-amber-400' : 'text-white' }} mt-3">{!! formatLotSum($stats['penya']) !!}</p>
            <p class="text-xs text-slate-500 mt-4">Kechikish uchun jarima</p>
        </div>
    </div>

    <!-- Progress -->
    @php $paidPercent = $stats['jami_summa'] > 0 ? round(($stats['tolangan'] / $stats['jami_summa']) * 100, 1) : 0; @endphp
    <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-5 mb-6">
        <div class="flex justify-between text-sm mb-3">
            <span class="font-medium text-slate-300">To'lov jarayoni</span>
            <span class="font-bold text-white">{{ $paidPercent }}%</span>
        </div>
        <div class="h-3 bg-slate-700 rounded-full">
            <div class="h-3 bg-gradient-to-r from-blue-500 to-blue-400 rounded-full transition-all" style="width: {{ $paidPercent }}%"></div>
        </div>
    </div>
    @endif

    <!-- Main Grid: Lot Info + Contract/Tenant Info -->
    <div class="grid lg:grid-cols-3 gap-6 mb-6">
        <!-- Lot Image & Details -->
        <div class="lg:col-span-2 bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl overflow-hidden">
            <div class="grid md:grid-cols-2 gap-0">
                <!-- Image -->
                <div class="bg-slate-900">
                    <div class="aspect-square relative">
                        @if($mainImage)
                        <img id="mainImage" src="{{ asset('storage/' . $mainImage) }}" alt="{{ $lot->obyekt_nomi }}" class="w-full h-full object-cover">
                        @else
                        <div class="w-full h-full flex items-center justify-center bg-slate-800">
                            <svg class="w-24 h-24 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        @endif
                    </div>
                    @if(count($images) > 1)
                    <div class="flex gap-1 p-2 overflow-x-auto bg-slate-800 border-t border-slate-700">
                        @foreach($images as $index => $img)
                        <button onclick="document.getElementById('mainImage').src='{{ asset('storage/' . $img) }}'" class="flex-shrink-0 w-14 h-14 rounded border overflow-hidden hover:border-blue-400 transition {{ $index === $mainIndex ? 'border-blue-500' : 'border-slate-600' }}">
                            <img src="{{ asset('storage/' . $img) }}" alt="" class="w-full h-full object-cover">
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Lot Details -->
                <div class="p-5 space-y-1 text-sm">
                    <div class="flex justify-between py-3 border-b border-slate-700/50"><span class="text-slate-400">Lot raqami</span><span class="font-bold text-white">{{ $lot->lot_raqami }}</span></div>
                    <div class="flex justify-between py-3 border-b border-slate-700/50"><span class="text-slate-400">Obyekt turi</span><span class="text-slate-200">{{ $lot->obyekt_turi_nomi }}</span></div>
                    <div class="flex justify-between py-3 border-b border-slate-700/50"><span class="text-slate-400">Maydon</span><span class="font-bold text-white">{{ number_format($lot->maydon, 2) }} m²</span></div>
                    @if($lot->xonalar_soni)<div class="flex justify-between py-3 border-b border-slate-700/50"><span class="text-slate-400">Xonalar</span><span class="text-slate-200">{{ $lot->xonalar_soni }}</span></div>@endif
                    @if($lot->qavat)<div class="flex justify-between py-3 border-b border-slate-700/50"><span class="text-slate-400">Qavat</span><span class="text-slate-200">{{ $lot->qavat }}{{ $lot->qavatlar_soni ? '/' . $lot->qavatlar_soni : '' }}</span></div>@endif
                    @if($lot->kadastr_raqami)<div class="flex justify-between py-3 border-b border-slate-700/50"><span class="text-slate-400">Kadastr</span><span class="font-mono text-xs text-slate-200">{{ $lot->kadastr_raqami }}</span></div>@endif
                    <div class="flex justify-between py-3 border-b border-slate-700/50"><span class="text-slate-400">Tuman</span><span class="text-slate-200">{{ $lot->tuman ?? '—' }}</span></div>
                    <div class="flex justify-between py-3"><span class="text-slate-400">Manzil</span><span class="text-right max-w-[200px] text-slate-200">{{ $lot->toliq_manzil }}</span></div>
                </div>
            </div>
        </div>

        <!-- Tenant Info (if contract exists) -->
        <div class="space-y-4">
            @if($contract && $contract->tenant)
            <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between bg-slate-800/80">
                    <h3 class="font-semibold text-white">Ijarachi</h3>
                    <a href="{{ route('registry.tenants.show', $contract->tenant) }}" class="text-xs text-blue-400 hover:text-blue-300 font-medium">Batafsil</a>
                </div>
                <div class="p-5 space-y-1 text-sm">
                    <div class="flex justify-between py-2"><span class="text-slate-400">Nomi:</span><span class="text-white font-semibold">{{ $contract->tenant->name }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-slate-400">INN:</span><span class="text-slate-200 font-mono font-medium">{{ $contract->tenant->inn ?? '—' }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-slate-400">Direktor:</span><span class="text-slate-200">{{ $contract->tenant->director_name ?? '—' }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-slate-400">Telefon:</span><span class="text-slate-200 font-medium">{{ $contract->tenant->phone ?? '—' }}</span></div>
                </div>
            </div>
            @endif

            @if($contract)
            <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-700/50 bg-slate-800/80 flex items-center justify-between">
                    <h3 class="font-semibold text-white">Shartnoma</h3>
                    <a href="{{ route('registry.contracts.show', $contract) }}" class="text-xs text-blue-400 hover:text-blue-300 font-medium">Batafsil</a>
                </div>
                <div class="p-5 space-y-1 text-sm">
                    <div class="flex justify-between py-2"><span class="text-slate-400">Raqam:</span><span class="text-white font-semibold">{{ $contract->shartnoma_raqami }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-slate-400">Muddat:</span><span class="text-slate-200">{{ $contract->shartnoma_muddati }} oy</span></div>
                    <div class="flex justify-between py-2"><span class="text-slate-400">Boshlanish:</span><span class="text-slate-200">{{ \Carbon\Carbon::parse($contract->boshlanish_sanasi)->format('d.m.Y') }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-slate-400">Tugash:</span><span class="text-slate-200">{{ \Carbon\Carbon::parse($contract->tugash_sanasi)->format('d.m.Y') }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-slate-400">Oylik:</span><span class="text-white font-bold">{{ number_format($contract->oylik_tolovi, 0, '', ' ') }}</span></div>
                </div>
                <!-- Penya kalkulyatori button -->
                <div class="px-5 py-3 border-t border-slate-700/50">
                    <a href="{{ route('registry.contracts.penalty-calculator', $contract) }}" class="flex items-center justify-center gap-2 w-full py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Penya kalkulyatori
                    </a>
                </div>
            </div>
            @else
            <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl p-8 text-center">
                <div class="w-16 h-16 bg-slate-700 rounded-full mx-auto mb-4 flex items-center justify-center">
                    <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-slate-400 mb-4">Faol shartnoma yo'q</p>
                <a href="{{ route('registry.contracts.create', ['lot_id' => $lot->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Shartnoma yaratish
                </a>
            </div>
            @endif
        </div>
    </div>

    @if($contract)
    @php
        $bugun = \Carbon\Carbon::today();
        $currentMonth = $bugun->month;
        $currentYear = $bugun->year;
        $currentQuarter = ceil($currentMonth / 3);
        $contractStart = \Carbon\Carbon::parse($contract->boshlanish_sanasi);
        $contractEnd = \Carbon\Carbon::parse($contract->tugash_sanasi);

        // Check if contract is expired (tugash_sanasi < today)
        $isContractExpired = $contractEnd->lt($bugun);

        // Get all schedules sorted by date
        $allSchedules = $contract->paymentSchedules->sortBy('tolov_sanasi');

        // Build periods based on ACTUAL schedule years (calendar year approach)
        // Group schedules by their actual year-month, then create 12-month periods
        $contractYearPeriods = [];

        if ($allSchedules->count() > 0) {
            // Group schedules by year-month and find distinct starting points
            $schedulesByYearMonth = $allSchedules->groupBy(function($s) {
                return \Carbon\Carbon::parse($s->tolov_sanasi)->format('Y-m');
            })->sortKeys();

            // Get the first schedule's month as the anchor for periods
            $firstScheduleDate = \Carbon\Carbon::parse($allSchedules->first()->tolov_sanasi);
            $lastScheduleDate = \Carbon\Carbon::parse($allSchedules->last()->tolov_sanasi);

            // Start period from the first schedule's year-month (day 1)
            $periodStart = \Carbon\Carbon::create($firstScheduleDate->year, $firstScheduleDate->month, 1);
            $periodNum = 1;

            // Create 12-month periods until all schedules are covered
            while ($periodStart->lte($lastScheduleDate)) {
                $periodEnd = $periodStart->copy()->addMonths(12)->subDay();

                // Get schedules for this period
                $periodSchedules = $allSchedules->filter(function($s) use ($periodStart, $periodEnd) {
                    $scheduleDate = \Carbon\Carbon::parse($s->tolov_sanasi);
                    return $scheduleDate->gte($periodStart) && $scheduleDate->lte($periodEnd);
                })->sortBy('tolov_sanasi');

                // Only add period if it has schedules
                if ($periodSchedules->count() > 0) {
                    $periodTotal = $periodSchedules->sum('tolov_summasi');
                    $periodPaid = $periodSchedules->sum('tolangan_summa');
                    $periodDebt = $periodSchedules->sum('qoldiq_summa');

                    // IMPORTANT: Use effective deadline (custom or original) for overdue/penalty
                    $periodPenalty = 0;
                    if (!$isContractExpired) {
                        $periodPenalty = $periodSchedules->sum(function($s) {
                            return ($s->penya_summasi ?? 0) - ($s->tolangan_penya ?? 0);
                        });
                    }
                    $periodPenya = max(0, $periodPenalty);

                    $periodOverdue = $periodSchedules->filter(function($s) use ($bugun) {
                        if ($s->qoldiq_summa <= 0) return false;
                        $paymentDate = \Carbon\Carbon::parse($s->tolov_sanasi);
                        return $paymentDate->lt($bugun);
                    })->sum('qoldiq_summa');

                    $periodPercent = $periodTotal > 0 ? round(($periodPaid / $periodTotal) * 100, 1) : 0;

                    // Adjust period end to actual last schedule in this period
                    $actualPeriodEnd = \Carbon\Carbon::parse($periodSchedules->last()->tolov_sanasi)->endOfMonth();

                    $contractYearPeriods[] = [
                        'num' => $periodNum,
                        'start' => $periodStart->copy(),
                        'end' => $actualPeriodEnd,
                        'schedules' => $periodSchedules,
                        'months' => $periodSchedules->count(),
                        'total' => $periodTotal,
                        'paid' => $periodPaid,
                        'debt' => $periodDebt,
                        'overdue' => $periodOverdue,
                        'penya' => $periodPenya,
                        'percent' => $periodPercent,
                    ];
                    $periodNum++;
                }

                $periodStart = $periodStart->copy()->addMonths(12);
            }
        }

        // Grand totals
        $grandTotal = $allSchedules->sum('tolov_summasi');

        // Use REAL payments instead of schedules for grandPaid
        $approvedPayments = $contract->payments->where('holat', 'tasdiqlangan');
        $refundPayments = $contract->payments->where('holat', 'qaytarilgan');
        $grandPaid = $approvedPayments->sum('summa') - abs($refundPayments->sum('summa')); // Real paid minus refunds

        $grandDebt = max(0, $grandTotal - $grandPaid);

        // Grand penalty: Don't accrue penalty after contract expiry
        $grandPenaltyRaw = 0;
        if (!$isContractExpired) {
            $grandPenaltyRaw = $allSchedules->sum('penya_summasi') - $allSchedules->sum('tolangan_penya');
        }
        $grandPenya = max(0, $grandPenaltyRaw);

        $grandOverdue = $allSchedules->filter(function($s) use ($bugun) {
            if ($s->qoldiq_summa <= 0) return false;
            $paymentDate = \Carbon\Carbon::parse($s->tolov_sanasi);
            return $paymentDate->lt($bugun);
        })->sum('qoldiq_summa');

        $grandPercent = $grandTotal > 0 ? round(($grandPaid / $grandTotal) * 100, 1) : 0;

        // Find current period
        $currentPeriodNum = null;
        $currentPeriodData = null;
        foreach ($contractYearPeriods as $idx => $p) {
            if ($bugun->gte($p['start']) && $bugun->lte($p['end'])) {
                $currentPeriodNum = $p['num'];
                $currentPeriodData = $p;
                break;
            }
        }

        // If no current period found, use first period
        if (!$currentPeriodData && count($contractYearPeriods) > 0) {
            $currentPeriodData = $contractYearPeriods[0];
        }

        // STATS: Use current period data for top cards
        $stats = $currentPeriodData ? [
            'jami_summa' => $currentPeriodData['total'],
            'tolangan' => $currentPeriodData['paid'],
            'qoldiq' => $currentPeriodData['overdue'],
            'penya' => $currentPeriodData['penya'],
        ] : [
            'jami_summa' => 0,
            'tolangan' => 0,
            'qoldiq' => 0,
            'penya' => 0,
        ];
    @endphp

    <!-- Professional Government Dashboard Table -->
    <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl mb-6 overflow-hidden">
        <!-- Header -->
        <div class="px-4 py-3 bg-slate-800/80 border-b border-slate-700/50 flex justify-between items-center">
            <h3 class="font-bold text-white text-sm uppercase tracking-wide">To'lov jadvali (Joriy davr)</h3>
            <div class="flex items-center gap-2">
                <button @click="showAddScheduleModal = true" class="px-3 py-1.5 border border-slate-600 text-slate-300 text-xs hover:bg-slate-700 rounded">+ Grafik</button>
                <button @click="showPaymentModal = true" class="px-3 py-1.5 bg-blue-600 text-white text-xs hover:bg-blue-700 rounded">+ To'lov</button>
            </div>
        </div>

        @if(count($contractYearPeriods) > 0)
        @php
            // Find current period for default display
            $currentPeriod = null;
            $otherPeriods = [];
            foreach ($contractYearPeriods as $period) {
                if ($period['num'] === $currentPeriodNum) {
                    $currentPeriod = $period;
                } else {
                    $otherPeriods[] = $period;
                }
            }
            // If no current period (e.g., contract not started yet), use first period
            if (!$currentPeriod && count($contractYearPeriods) > 0) {
                $currentPeriod = $contractYearPeriods[0];
                $otherPeriods = array_slice($contractYearPeriods, 1);
            }
        @endphp

        @if($currentPeriod)
        <!-- Current Period Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-700/50 text-slate-300">
                    <tr>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-left">№</th>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-left">Shartnoma davri</th>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">Oylar</th>
                        <th colspan="2" class="border border-slate-600 px-2 py-1 text-center">Reja</th>
                        <th colspan="2" class="border border-slate-600 px-2 py-1 text-center text-blue-400">Fakt</th>
                        <th colspan="2" class="border border-slate-600 px-2 py-1 text-center text-red-400">Qoldiq</th>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">%</th>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center text-amber-400">Penya</th>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">Amal</th>
                    </tr>
                    <tr class="text-[10px] text-slate-400">
                        <th class="border border-slate-600 px-2 py-1 text-right">summa</th>
                        <th class="border border-slate-600 px-2 py-1 text-right">oylik</th>
                        <th class="border border-slate-600 px-2 py-1 text-right text-blue-400">tushgan</th>
                        <th class="border border-slate-600 px-2 py-1 text-right text-blue-400">oylik</th>
                        <th class="border border-slate-600 px-2 py-1 text-right text-red-400">jami</th>
                        <th class="border border-slate-600 px-2 py-1 text-right text-red-400">o'tgan</th>
                    </tr>
                </thead>
                <tbody class="text-slate-200">
                    @php
                        $periodScheduleIds = $currentPeriod['schedules']->pluck('id')->toArray();
                        $canDeletePeriod = $currentPeriod['paid'] <= 0;
                    @endphp
                    <tr class="hover:bg-slate-700/30 bg-blue-900/20">
                        <td class="border border-slate-600 px-2 py-1 text-center text-blue-400 font-bold">{{ $currentPeriod['num'] }}</td>
                        <td class="border border-slate-600 px-2 py-1">
                            <span class="px-1 bg-blue-600 text-white text-[9px] rounded mr-1">JORIY</span>
                            <span class="text-white">{{ $currentPeriod['start']->format('d.m.Y') }}</span>
                            <span class="text-slate-500">—</span>
                            <span class="text-white">{{ $currentPeriod['end']->format('d.m.Y') }}</span>
                        </td>
                        <td class="border border-slate-600 px-2 py-1 text-center">{{ $currentPeriod['months'] }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right text-white">{{ number_format($currentPeriod['total'], 0, ',', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $currentPeriod['months'] > 0 ? number_format($currentPeriod['total'] / $currentPeriod['months'], 0, ',', ' ') : 0 }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right {{ $currentPeriod['paid'] > 0 ? 'text-blue-400' : 'text-slate-500' }}">{{ number_format($currentPeriod['paid'], 0, ',', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $currentPeriod['schedules']->where('tolangan_summa', '>', 0)->count() > 0 ? number_format($currentPeriod['paid'] / $currentPeriod['schedules']->where('tolangan_summa', '>', 0)->count(), 0, ',', ' ') : '—' }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right {{ $currentPeriod['debt'] > 0 ? 'text-red-400' : 'text-green-400' }}">{{ number_format($currentPeriod['debt'], 0, ',', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right {{ $currentPeriod['overdue'] > 0 ? 'text-red-400' : 'text-slate-500' }}">{{ $currentPeriod['overdue'] > 0 ? number_format($currentPeriod['overdue'], 0, ',', ' ') : '—' }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-center {{ $currentPeriod['percent'] >= 100 ? 'text-green-400' : ($currentPeriod['percent'] >= 50 ? 'text-blue-400' : 'text-red-400') }}">{{ $currentPeriod['percent'] }}%</td>
                        <td class="border border-slate-600 px-2 py-1 text-right {{ $currentPeriod['penya'] > 0 ? 'text-amber-400' : 'text-slate-500' }}">{{ $currentPeriod['penya'] > 0 ? number_format($currentPeriod['penya'], 0, ',', ' ') : '—' }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-center">
                            @if($canDeletePeriod)
                            <button @click="deletePeriodSchedules([{{ implode(',', $periodScheduleIds) }}])" class="text-slate-500 hover:text-red-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            @else
                            <span class="text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif

        <!-- Other Periods (Collapsible) -->
        @if(count($otherPeriods) > 0 || $grandTotal > 0)
        <div x-data="{ showAllPeriods: false }" class="border-t border-slate-600">
            <button @click="showAllPeriods = !showAllPeriods" class="w-full px-4 py-2 text-left text-xs text-slate-400 hover:bg-slate-700/30 flex items-center justify-between">
                <span class="font-medium">Barcha davrlar va umumiy statistika</span>
                <svg :class="showAllPeriods ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="showAllPeriods" x-collapse>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-700/50 text-slate-300">
                            <tr>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-left">№</th>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-left">Shartnoma davri</th>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">Oylar</th>
                                <th colspan="2" class="border border-slate-600 px-2 py-1 text-center">Reja</th>
                                <th colspan="2" class="border border-slate-600 px-2 py-1 text-center text-blue-400">Fakt</th>
                                <th colspan="2" class="border border-slate-600 px-2 py-1 text-center text-red-400">Qoldiq</th>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">%</th>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center text-amber-400">Penya</th>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">Amal</th>
                            </tr>
                            <tr class="text-[10px] text-slate-400">
                                <th class="border border-slate-600 px-2 py-1 text-right">summa</th>
                                <th class="border border-slate-600 px-2 py-1 text-right">oylik</th>
                                <th class="border border-slate-600 px-2 py-1 text-right text-blue-400">tushgan</th>
                                <th class="border border-slate-600 px-2 py-1 text-right text-blue-400">oylik</th>
                                <th class="border border-slate-600 px-2 py-1 text-right text-red-400">jami</th>
                                <th class="border border-slate-600 px-2 py-1 text-right text-red-400">o'tgan</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-200">
                            <!-- JAMI Row -->
                            <tr class="bg-slate-700/30 font-bold">
                                <td class="border border-slate-600 px-2 py-1 text-center"></td>
                                <td class="border border-slate-600 px-2 py-1 text-white">JAMI:</td>
                                <td class="border border-slate-600 px-2 py-1 text-center text-blue-400">{{ $allSchedules->count() }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-white">{{ number_format($grandTotal, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $allSchedules->count() > 0 ? number_format($grandTotal / $allSchedules->count(), 0, ',', ' ') : 0 }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-blue-400">{{ number_format($grandPaid, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-blue-400">{{ $allSchedules->where('tolangan_summa', '>', 0)->count() > 0 ? number_format($grandPaid / $allSchedules->where('tolangan_summa', '>', 0)->count(), 0, ',', ' ') : 0 }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-red-400">{{ number_format($grandDebt, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-red-400">{{ number_format($grandOverdue, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-center {{ $grandPercent >= 100 ? 'text-green-400' : ($grandPercent >= 50 ? 'text-blue-400' : 'text-red-400') }}">{{ $grandPercent }}%</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-amber-400">{{ number_format($grandPenya, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1"></td>
                            </tr>
                            @foreach($contractYearPeriods as $period)
                            @php
                                $isCurrentPeriod = $period['num'] === $currentPeriodNum;
                                $periodScheduleIds = $period['schedules']->pluck('id')->toArray();
                                $canDeletePeriod = $period['paid'] <= 0;
                            @endphp
                            <tr class="hover:bg-slate-700/30 {{ $isCurrentPeriod ? 'bg-blue-900/20' : '' }}">
                                <td class="border border-slate-600 px-2 py-1 text-center {{ $isCurrentPeriod ? 'text-blue-400 font-bold' : '' }}">{{ $period['num'] }}</td>
                                <td class="border border-slate-600 px-2 py-1">
                                    @if($isCurrentPeriod)<span class="px-1 bg-blue-600 text-white text-[9px] rounded mr-1">JORIY</span>@endif
                                    <span class="text-white">{{ $period['start']->format('d.m.Y') }}</span>
                                    <span class="text-slate-500">—</span>
                                    <span class="text-white">{{ $period['end']->format('d.m.Y') }}</span>
                                </td>
                                <td class="border border-slate-600 px-2 py-1 text-center">{{ $period['months'] }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-white">{{ number_format($period['total'], 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $period['months'] > 0 ? number_format($period['total'] / $period['months'], 0, ',', ' ') : 0 }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $period['paid'] > 0 ? 'text-blue-400' : 'text-slate-500' }}">{{ number_format($period['paid'], 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $period['schedules']->where('tolangan_summa', '>', 0)->count() > 0 ? number_format($period['paid'] / $period['schedules']->where('tolangan_summa', '>', 0)->count(), 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $period['debt'] > 0 ? 'text-red-400' : 'text-green-400' }}">{{ number_format($period['debt'], 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $period['overdue'] > 0 ? 'text-red-400' : 'text-slate-500' }}">{{ $period['overdue'] > 0 ? number_format($period['overdue'], 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-center {{ $period['percent'] >= 100 ? 'text-green-400' : ($period['percent'] >= 50 ? 'text-blue-400' : 'text-red-400') }}">{{ $period['percent'] }}%</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $period['penya'] > 0 ? 'text-amber-400' : 'text-slate-500' }}">{{ $period['penya'] > 0 ? number_format($period['penya'], 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-center">
                                    @if($canDeletePeriod)
                                    <button @click="deletePeriodSchedules([{{ implode(',', $periodScheduleIds) }}])" class="text-slate-500 hover:text-red-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                    @else
                                    <span class="text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Monthly Details (Expandable) -->
        <div x-data="{ showDetails: false }" class="border-t border-slate-600">
            <button @click="showDetails = !showDetails" class="w-full px-4 py-2 text-left text-xs text-slate-400 hover:bg-slate-700/30 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="font-medium">Oylik tafsilotlar (Joriy davr)</span>
                    @if($currentPeriod)
                    <span class="text-[10px] px-2 py-0.5 bg-blue-900/30 text-blue-300 rounded">{{ $currentPeriod['schedules']->count() }} oy</span>
                    @endif
                </div>
                <svg :class="showDetails ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="showDetails" x-collapse>
                <table class="w-full text-xs">
                    <thead class="bg-slate-700/50 text-slate-300">
                        <tr>
                            <th class="border border-slate-600 px-2 py-1 text-center">№</th>
                            <th class="border border-slate-600 px-2 py-1 text-left">Oy</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">Muddat</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">Grafik</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">To'langan</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">To'lov sanasi</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">Qoldiq</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">Kun</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">Stavka</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">Penya hisob</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">To'l. penya</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">Qol. penya</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">Amal</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-200">
                        @php $rowNum = 0; @endphp
                        @if($currentPeriod)
                            @foreach($currentPeriod['schedules'] as $idx => $schedule)
                            @php
                                $rowNum++;
                                $originalDeadline = \Carbon\Carbon::parse($schedule->oxirgi_muddat);
                                $bugun = \Carbon\Carbon::today();

                                // CUSTOM DEADLINE: Use custom if set, otherwise original
                                $effectiveDeadline = $schedule->custom_oxirgi_muddat
                                    ? \Carbon\Carbon::parse($schedule->custom_oxirgi_muddat)
                                    : $originalDeadline;

                                // Calculate days: positive = future, negative = overdue
                                $daysFromToday = $bugun->diffInDays($effectiveDeadline, false);
                                $isOverdue = $daysFromToday <= 0;
                                $overdueDays = $isOverdue ? abs($daysFromToday) : 0;
                                $daysLeft = $isOverdue ? 0 : $daysFromToday;

                                $tolanganPenya = $schedule->tolangan_penya ?? 0;
                                $lastPaymentDate = null;

                                if ($schedule->tolangan_summa > 0) {
                                    foreach ($contract->payments->sortBy('tolov_sanasi') as $pmt) {
                                        $pmtDate = \Carbon\Carbon::parse($pmt->tolov_sanasi);
                                        if ($pmtDate->gte($originalDeadline->copy()->subDays(30))) {
                                            $lastPaymentDate = $pmtDate;
                                            break;
                                        }
                                    }
                                }

                                // Check if 10th of month has passed (debtor status based on 10th)
                                $paymentDue10th = \Carbon\Carbon::create($schedule->yil, $schedule->oy, 10);

                                // Calculate overdue days based on payment timing
                                if ($schedule->tolangan_summa > 0 && $lastPaymentDate) {
                                    // Paid (fully or partially) - check if deadline has passed (not current month)
                                    $isCurrentMonthSchedule = ($schedule->oy == $currentMonth && $schedule->yil == $currentYear);

                                    // For partially paid schedules, still show overdue if qoldiq > 0
                                    if ($schedule->qoldiq_summa > 0 && $bugun->gt($paymentDue10th)) {
                                        // Partially paid and overdue
                                        $overdueDays = $paymentDue10th->diffInDays($bugun);
                                        $isOverdue = true;
                                    } elseif (!$isCurrentMonthSchedule && $lastPaymentDate->gt($paymentDue10th) && $schedule->qoldiq_summa <= 0) {
                                        // Fully paid past month that was late - show days between 10th and payment date
                                        $overdueDays = $paymentDue10th->diffInDays($lastPaymentDate);
                                        $isOverdue = true;
                                    } else {
                                        // Current month or on-time payment
                                        $isOverdue = false;
                                        $overdueDays = 0;
                                    }
                                } elseif ($schedule->qoldiq_summa > 0 && $bugun->gt($paymentDue10th)) {
                                    // Unpaid and overdue - ONLY if payment date has passed
                                    $overdueDays = $paymentDue10th->diffInDays($bugun);
                                    $isOverdue = true;
                                } else {
                                    // Future month or not yet due
                                    $isOverdue = false;
                                    $overdueDays = 0;
                                }

                                // PENALTY CALCULATION based on effective deadline
                                // EXPIRED CONTRACT RULE: Don't calculate penalty for expired contracts
                                $kechikish = 0;
                                $penyaHisob = 0;

                                if ($isContractExpired) {
                                    // Contract expired - no penalty calculation
                                    $kechikish = 0;
                                    $penyaHisob = 0;
                                } elseif ($isOverdue) {
                                    // Payment date passed - calculate penalty
                                    $kechikish = $overdueDays;
                                    $penyaRate = 0.0004;
                                    $baseAmount = $schedule->qoldiq_summa > 0 ? $schedule->qoldiq_summa : $schedule->tolov_summasi;
                                    $rawPenya = $baseAmount * $penyaRate * $kechikish;
                                    $maxPenya = $baseAmount * 0.5;
                                    $penyaHisob = min($rawPenya, $maxPenya);
                                }

                                $qoldiqPenya = max(0, $penyaHisob - $tolanganPenya);
                                $monthNames = ['', 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avg', 'Sent', 'Okt', 'Noy', 'Dek'];
                                $isCurrentMonth = ($schedule->oy == $currentMonth && $schedule->yil == $currentYear);
                                $canDelete = $schedule->tolangan_summa <= 0;
                                $hasCustomDeadline = !empty($schedule->custom_oxirgi_muddat);
                            @endphp
                            <tr x-data="{
                                editing: false,
                                form: {
                                    tolov_sanasi: '{{ \Carbon\Carbon::parse($schedule->tolov_sanasi)->format('Y-m-d') }}',
                                    oxirgi_muddat: '{{ $originalDeadline->format('Y-m-d') }}',
                                    new_deadline: '{{ $effectiveDeadline->format('Y-m-d') }}',
                                    tolov_summasi: {{ $schedule->tolov_summasi }}
                                }
                            }"
                                class="{{ $isOverdue && $schedule->qoldiq_summa > 0 ? 'bg-red-900/10' : '' }} hover:bg-slate-700/30">
                                <td class="border border-slate-600 px-2 py-1 text-center">{{ $rowNum }}</td>
                                <td class="border border-slate-600 px-2 py-1">
                                    {{ $monthNames[$schedule->oy] ?? $schedule->oy }} {{ $schedule->yil }}
                                    @if($isCurrentMonth)<span class="text-[9px] text-blue-400">(joriy)</span>@endif
                                </td>
                                {{-- MUDDAT COLUMN: Shows effective deadline with edit option --}}
                                <td class="border border-slate-600 px-2 py-1 text-center">
                                    <template x-if="!editing">
                                        <div>
                                            <span class="{{ $hasCustomDeadline ? 'text-blue-300' : '' }}">{{ $effectiveDeadline->format('d.m.Y') }}</span>
                                            @if($hasCustomDeadline)
                                                <span class="text-[8px] text-blue-400 ml-0.5" title="Asl muddat: {{ $originalDeadline->format('d.m.Y') }}">*</span>
                                            @endif
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <input type="date" x-model="form.new_deadline" class="w-full border border-slate-500 bg-slate-700 rounded px-1 py-0.5 text-xs text-white">
                                    </template>
                                </td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-white">
                                    <template x-if="!editing"><span>{{ number_format($schedule->tolov_summasi, 0, ',', ' ') }}</span></template>
                                    <template x-if="editing"><input type="number" x-model="form.tolov_summasi" class="w-full border border-slate-500 bg-slate-700 rounded px-1 py-0.5 text-xs text-right text-white"></template>
                                </td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $schedule->tolangan_summa > 0 ? 'text-blue-400' : 'text-slate-500' }}">{{ $schedule->tolangan_summa > 0 ? number_format($schedule->tolangan_summa, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-center text-slate-400">{{ $lastPaymentDate ? $lastPaymentDate->format('d.m.Y') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $schedule->qoldiq_summa > 0 ? 'text-red-400' : 'text-green-400' }}">{{ $schedule->qoldiq_summa > 0 ? number_format($schedule->qoldiq_summa, 0, ',', ' ') : '—' }}</td>
                                {{-- KUN COLUMN: Shows days overdue (red) or days left (green), not editable --}}
                                <td class="border border-slate-600 px-2 py-1 text-center {{ $isOverdue ? 'text-red-400 font-semibold' : ($daysLeft > 0 ? 'text-green-400' : 'text-slate-400') }}"
                                    title="{{ $schedule->muddat_ozgarish_izoh ? $schedule->muddat_ozgarish_izoh : '' }}">
                                    @if($isOverdue)
                                        {{ $overdueDays }}
                                        @if($hasCustomDeadline)<span class="text-[8px] text-blue-400 ml-0.5">*</span>@endif
                                    @elseif($daysLeft > 0)
                                        {{ $daysLeft }}
                                        @if($hasCustomDeadline)<span class="text-[8px] text-blue-400 ml-0.5">*</span>@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="border border-slate-600 px-2 py-1 text-center text-slate-400">{{ $isOverdue ? '0,04%' : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $penyaHisob > 0 ? 'text-amber-400' : 'text-slate-500' }}">{{ $penyaHisob > 0 ? number_format($penyaHisob, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $tolanganPenya > 0 ? 'text-green-400' : 'text-slate-500' }}">{{ $tolanganPenya > 0 ? number_format($tolanganPenya, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $qoldiqPenya > 0 ? 'text-amber-400' : ($tolanganPenya > 0 ? 'text-green-400' : 'text-slate-500') }}">{{ $qoldiqPenya > 0 ? number_format($qoldiqPenya, 0, ',', ' ') : ($tolanganPenya > 0 ? '✓' : '—') }}</td>
                                <td class="border border-slate-600 px-1 py-1 text-center">
                                    <template x-if="!editing">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="editing = true" class="p-1 text-slate-500 hover:text-blue-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
                                            @if($canDelete)<button @click="deleteSchedule({{ $schedule->id }})" class="p-1 text-slate-500 hover:text-red-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                            @else<span class="p-1 text-slate-600"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>@endif
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="updateSchedule({{ $schedule->id }}, form); editing = false" class="p-1 bg-green-600 text-white rounded hover:bg-green-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>
                                            <button @click="editing = false" class="p-1 bg-slate-600 text-white rounded hover:bg-slate-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- All Monthly Details (Collapsible) -->
        @if(count($contractYearPeriods) > 1)
        <div x-data="{ showAllDetails: false }" class="border-t border-slate-600">
            <button @click="showAllDetails = !showAllDetails" class="w-full px-4 py-2 text-left text-xs text-slate-400 hover:bg-slate-700/30 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="font-medium">Barcha oylik tafsilotlar</span>
                    @php
                        $totalSchedules = collect($contractYearPeriods)->sum(fn($p) => $p['schedules']->count());
                    @endphp
                    <span class="text-[10px] px-2 py-0.5 bg-slate-700/50 text-slate-400 rounded">{{ $totalSchedules }} oy ({{ count($contractYearPeriods) }} davr)</span>
                </div>
                <svg :class="showAllDetails ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="showAllDetails" x-collapse>
                <table class="w-full text-xs">
                    <thead class="bg-slate-700/50 text-slate-300">
                        <tr>
                            <th class="border border-slate-600 px-2 py-1 text-center">№</th>
                            <th class="border border-slate-600 px-2 py-1 text-left">Oy</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">Muddat</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">Grafik</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">To'langan</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">To'lov sanasi</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">Qoldiq</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">Kun</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">Stavka</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">Penya hisob</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">To'l. penya</th>
                            <th class="border border-slate-600 px-2 py-1 text-right">Qol. penya</th>
                            <th class="border border-slate-600 px-2 py-1 text-center">Amal</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-200">
                        @php $rowNum = 0; @endphp
                        @foreach($contractYearPeriods as $period)
                            @foreach($period['schedules'] as $idx => $schedule)
                            @php
                                $rowNum++;
                                $originalDeadline = \Carbon\Carbon::parse($schedule->oxirgi_muddat);
                                $bugun = \Carbon\Carbon::today();

                                // CUSTOM DEADLINE: Use custom if set, otherwise original
                                $effectiveDeadline = $schedule->custom_oxirgi_muddat
                                    ? \Carbon\Carbon::parse($schedule->custom_oxirgi_muddat)
                                    : $originalDeadline;

                                // Calculate days: positive = future, negative = overdue
                                $daysFromToday = $bugun->diffInDays($effectiveDeadline, false);
                                $isOverdue = $daysFromToday <= 0;
                                $overdueDays = $isOverdue ? abs($daysFromToday) : 0;
                                $daysLeft = $isOverdue ? 0 : $daysFromToday;

                                $tolanganPenya = $schedule->tolangan_penya ?? 0;
                                $lastPaymentDate = null;

                                if ($schedule->tolangan_summa > 0) {
                                    foreach ($contract->payments->sortBy('tolov_sanasi') as $pmt) {
                                        $pmtDate = \Carbon\Carbon::parse($pmt->tolov_sanasi);
                                        if ($pmtDate->gte($originalDeadline->copy()->subDays(30))) {
                                            $lastPaymentDate = $pmtDate;
                                            break;
                                        }
                                    }
                                }

                                // Check if 10th of month has passed (debtor status based on 10th)
                                $paymentDue10th = \Carbon\Carbon::create($schedule->yil, $schedule->oy, 10);

                                // Calculate overdue days based on payment timing
                                if ($schedule->tolangan_summa > 0 && $lastPaymentDate) {
                                    // Paid (fully or partially) - check if deadline has passed (not current month)
                                    $isCurrentMonthSchedule = ($schedule->oy == $currentMonth && $schedule->yil == $currentYear);

                                    // For partially paid schedules, still show overdue if qoldiq > 0
                                    if ($schedule->qoldiq_summa > 0 && $bugun->gt($paymentDue10th)) {
                                        // Partially paid and overdue
                                        $overdueDays = $paymentDue10th->diffInDays($bugun);
                                        $isOverdue = true;
                                    } elseif (!$isCurrentMonthSchedule && $lastPaymentDate->gt($paymentDue10th) && $schedule->qoldiq_summa <= 0) {
                                        // Fully paid past month that was late - show days between 10th and payment date
                                        $overdueDays = $paymentDue10th->diffInDays($lastPaymentDate);
                                        $isOverdue = true;
                                    } else {
                                        // Current month or on-time payment
                                        $isOverdue = false;
                                        $overdueDays = 0;
                                    }
                                } elseif ($schedule->qoldiq_summa > 0 && $bugun->gt($paymentDue10th)) {
                                    // Unpaid and overdue - ONLY if payment date has passed
                                    $overdueDays = $paymentDue10th->diffInDays($bugun);
                                    $isOverdue = true;
                                } else {
                                    // Future month or not yet due
                                    $isOverdue = false;
                                    $overdueDays = 0;
                                }

                                // PENALTY CALCULATION based on effective deadline
                                // EXPIRED CONTRACT RULE: Don't calculate penalty for expired contracts
                                $kechikish = 0;
                                $penyaHisob = 0;

                                if ($isContractExpired) {
                                    // Contract expired - no penalty calculation
                                    $kechikish = 0;
                                    $penyaHisob = 0;
                                } elseif ($schedule->qoldiq_summa > 0 && $isOverdue) {
                                    $kechikish = $overdueDays;
                                    $penyaRate = 0.0004;
                                    $rawPenya = $schedule->qoldiq_summa * $penyaRate * $kechikish;
                                    $maxPenya = $schedule->qoldiq_summa * 0.5;
                                    $penyaHisob = min($rawPenya, $maxPenya);
                                } elseif ($schedule->tolangan_summa > 0) {
                                    // Paid - NO penalty for on-time payment
                                    $kechikish = 0;
                                    $penyaHisob = 0;
                                }

                                $qoldiqPenya = max(0, $penyaHisob - $tolanganPenya);
                                $monthNames = ['', 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avg', 'Sent', 'Okt', 'Noy', 'Dek'];
                                $isCurrentMonth = ($schedule->oy == $currentMonth && $schedule->yil == $currentYear);
                                $canDelete = $schedule->tolangan_summa <= 0;
                                $hasCustomDeadline = !empty($schedule->custom_oxirgi_muddat);
                            @endphp
                            <tr x-data="{
                                editing: false,
                                form: {
                                    tolov_sanasi: '{{ \Carbon\Carbon::parse($schedule->tolov_sanasi)->format('Y-m-d') }}',
                                    oxirgi_muddat: '{{ $originalDeadline->format('Y-m-d') }}',
                                    new_deadline: '{{ $effectiveDeadline->format('Y-m-d') }}',
                                    tolov_summasi: {{ $schedule->tolov_summasi }}
                                }
                            }"
                                class="{{ $isOverdue && $schedule->qoldiq_summa > 0 ? 'bg-red-900/10' : '' }} hover:bg-slate-700/30">
                                <td class="border border-slate-600 px-2 py-1 text-center">{{ $rowNum }}</td>
                                <td class="border border-slate-600 px-2 py-1">
                                    {{ $monthNames[$schedule->oy] ?? $schedule->oy }} {{ $schedule->yil }}
                                    @if($isCurrentMonth)<span class="text-[9px] text-blue-400">(joriy)</span>@endif
                                </td>
                                {{-- MUDDAT COLUMN: Shows effective deadline with edit option --}}
                                <td class="border border-slate-600 px-2 py-1 text-center">
                                    <template x-if="!editing">
                                        <div>
                                            <span class="{{ $hasCustomDeadline ? 'text-blue-300' : '' }}">{{ $effectiveDeadline->format('d.m.Y') }}</span>
                                            @if($hasCustomDeadline)
                                                <span class="text-[8px] text-blue-400 ml-0.5" title="Asl muddat: {{ $originalDeadline->format('d.m.Y') }}">*</span>
                                            @endif
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <input type="date" x-model="form.new_deadline" class="w-full border border-slate-500 bg-slate-700 rounded px-1 py-0.5 text-xs text-white">
                                    </template>
                                </td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-white">
                                    <template x-if="!editing"><span>{{ number_format($schedule->tolov_summasi, 0, ',', ' ') }}</span></template>
                                    <template x-if="editing"><input type="number" x-model="form.tolov_summasi" class="w-full border border-slate-500 bg-slate-700 rounded px-1 py-0.5 text-xs text-right text-white"></template>
                                </td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $schedule->tolangan_summa > 0 ? 'text-blue-400' : 'text-slate-500' }}">{{ $schedule->tolangan_summa > 0 ? number_format($schedule->tolangan_summa, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-center text-slate-400">{{ $lastPaymentDate ? $lastPaymentDate->format('d.m.Y') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $schedule->qoldiq_summa > 0 ? 'text-red-400' : 'text-green-400' }}">{{ $schedule->qoldiq_summa > 0 ? number_format($schedule->qoldiq_summa, 0, ',', ' ') : '—' }}</td>
                                {{-- KUN COLUMN: Shows days overdue (red) or days left (green), not editable --}}
                                <td class="border border-slate-600 px-2 py-1 text-center {{ $isOverdue ? 'text-red-400 font-semibold' : ($daysLeft > 0 ? 'text-green-400' : 'text-slate-400') }}"
                                    title="{{ $schedule->muddat_ozgarish_izoh ? $schedule->muddat_ozgarish_izoh : '' }}">
                                    @if($isOverdue)
                                        {{ $overdueDays }}
                                        @if($hasCustomDeadline)<span class="text-[8px] text-blue-400 ml-0.5">*</span>@endif
                                    @elseif($daysLeft > 0)
                                        {{ $daysLeft }}
                                        @if($hasCustomDeadline)<span class="text-[8px] text-blue-400 ml-0.5">*</span>@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="border border-slate-600 px-2 py-1 text-center text-slate-400">{{ $isOverdue ? '0,04%' : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $penyaHisob > 0 ? 'text-amber-400' : 'text-slate-500' }}">{{ $penyaHisob > 0 ? number_format($penyaHisob, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $tolanganPenya > 0 ? 'text-green-400' : 'text-slate-500' }}">{{ $tolanganPenya > 0 ? number_format($tolanganPenya, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $qoldiqPenya > 0 ? 'text-amber-400' : ($tolanganPenya > 0 ? 'text-green-400' : 'text-slate-500') }}">{{ $qoldiqPenya > 0 ? number_format($qoldiqPenya, 0, ',', ' ') : ($tolanganPenya > 0 ? '✓' : '—') }}</td>
                                <td class="border border-slate-600 px-1 py-1 text-center">
                                    <template x-if="!editing">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="editing = true" class="p-1 text-slate-500 hover:text-blue-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
                                            @if($canDelete)<button @click="deleteSchedule({{ $schedule->id }})" class="p-1 text-slate-500 hover:text-red-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                            @else<span class="p-1 text-slate-600"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>@endif
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="updateSchedule({{ $schedule->id }}, form); editing = false" class="p-1 bg-green-600 text-white rounded hover:bg-green-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>
                                            <button @click="editing = false" class="p-1 bg-slate-600 text-white rounded hover:bg-slate-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @else
        <div class="px-4 py-8 text-center text-slate-500 text-sm">To'lov grafigi yo'q</div>
        @endif

        <!-- Deadline Change Log -->
        @php
            $schedulesWithChanges = collect($contractYearPeriods)->flatMap(fn($p) => $p['schedules'])->filter(fn($s) => !empty($s->muddat_ozgarish_izoh));
        @endphp
        @if($schedulesWithChanges->count() > 0)
        <div class="mt-4 border-t border-slate-600 pt-3">
            <div class="px-4 py-2 bg-blue-900/20 border border-blue-500/30 rounded-lg">
                <h4 class="text-xs font-bold text-blue-300 mb-2 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Muddat o'zgarishlari tarixi
                </h4>
                <div class="space-y-1.5 text-[10px] text-blue-200">
                    @foreach($schedulesWithChanges as $schedule)
                        <div class="bg-slate-800/50 rounded px-2 py-1.5 border-l-2 border-blue-400">
                            <span class="font-medium text-blue-300">{{ $schedule->oy_nomi }} {{ $schedule->yil }}:</span>
                            <div class="ml-2 mt-0.5 text-slate-300 whitespace-pre-line">{{ $schedule->muddat_ozgarish_izoh }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Recent Payments (только tasdiqlangan) -->
    @php
        $approvedPayments = $contract->payments->where('holat', 'tasdiqlangan');
        $refundPayments = $contract->payments->where('holat', 'qaytarilgan');
    @endphp
    <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-4 py-2 border-b border-slate-700/50 flex items-center justify-between bg-slate-800/80">
            <h3 class="font-bold text-white text-sm">To'lovlar tarixi</h3>
            <span class="text-xs text-emerald-400">{{ $approvedPayments->count() }} ta</span>
        </div>

        @if($approvedPayments->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-700/50 text-slate-300">
                    <tr>
                        <th class="border border-slate-600 px-3 py-1.5 text-left">Sana</th>
                        <th class="border border-slate-600 px-3 py-1.5 text-right">Summa</th>
                        <th class="border border-slate-600 px-3 py-1.5 text-center">Turi</th>
                    </tr>
                </thead>
                <tbody class="text-slate-200">
                    @foreach($approvedPayments->sortByDesc('tolov_sanasi')->take(20) as $payment)
                    @php $paymentDate = \Carbon\Carbon::parse($payment->tolov_sanasi); @endphp
                    <tr class="hover:bg-slate-700/30">
                        <td class="border border-slate-600 px-3 py-1.5 text-white font-medium">{{ $paymentDate->format('d.m.Y') }}</td>
                        <td class="border border-slate-600 px-3 py-1.5 text-right text-emerald-400 font-bold">+{{ number_format($payment->summa, 0, '', ' ') }}</td>
                        <td class="border border-slate-600 px-3 py-1.5 text-center text-slate-400">{{ ['naqd' => 'Naqd', 'plastik' => 'Karta', 'bank' => 'Bank', 'bank_otkazmasi' => 'Bank', 'onlayn' => 'Onlayn'][$payment->tolov_usuli] ?? 'Bank' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Jami to'langan -->
        <div class="px-4 py-2 border-t border-slate-700/50 flex items-center justify-between bg-slate-800/60">
            <span class="text-sm text-emerald-400 font-medium">Jami to'langan:</span>
            <span class="text-lg font-bold text-emerald-400">{{ number_format($approvedPayments->sum('summa'), 0, '', ' ') }} UZS</span>
        </div>
        @else
        <div class="px-4 py-6 text-center text-slate-500 text-sm">To'lovlar yo'q</div>
        @endif
    </div>

    <!-- Qaytarishlar tarixi (Refunds) - КРАСНАЯ СЕКЦИЯ -->
    @if($refundPayments->count() > 0)
    <div class="bg-red-900/30 backdrop-blur border-2 border-red-500/60 rounded-xl overflow-hidden mt-4">
        <div class="px-4 py-2 border-b border-red-500/50 flex items-center justify-between bg-red-900/40">
            <h3 class="font-bold text-red-300 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>
                Qaytarishlar tarixi
            </h3>
            <span class="text-xs text-red-300 font-bold bg-red-500/30 px-2 py-0.5 rounded">{{ $refundPayments->count() }} ta</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-red-900/40 text-red-200">
                    <tr>
                        <th class="border border-red-500/40 px-2 py-1.5 text-left">Sana</th>
                        <th class="border border-red-500/40 px-2 py-1.5 text-right">Summa</th>
                        <th class="border border-red-500/40 px-2 py-1.5 text-left">Sabab</th>
                        <th class="border border-red-500/40 px-2 py-1.5 text-left">Manba</th>
                        <th class="border border-red-500/40 px-2 py-1.5 text-left">Hujjat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($refundPayments->sortByDesc('tolov_sanasi') as $refund)
                    @php
                        $refundDate = \Carbon\Carbon::parse($refund->tolov_sanasi);
                        $izohParts = explode(' | ', $refund->izoh ?? '');
                        $sabab = '';
                        $manba = '';
                        $hujjat = '';
                        foreach($izohParts as $part) {
                            if (str_starts_with($part, 'QAYTARISH:')) {
                                $sabab = trim(str_replace('QAYTARISH:', '', $part));
                            } elseif (str_starts_with($part, 'Manba:')) {
                                $manba = trim(str_replace('Manba:', '', $part));
                            } elseif (str_starts_with($part, 'Hujjat:')) {
                                $hujjat = trim(str_replace('Hujjat:', '', $part));
                            }
                        }
                    @endphp
                    <tr class="bg-red-900/20 hover:bg-red-900/30">
                        <td class="border border-red-500/40 px-2 py-1.5 text-red-100 font-medium">{{ $refundDate->format('d.m.Y') }}</td>
                        <td class="border border-red-500/40 px-2 py-1.5 text-right text-red-400 font-bold text-sm">{{ number_format($refund->summa, 0, '', ' ') }}</td>
                        <td class="border border-red-500/40 px-2 py-1.5 text-red-200">{{ $sabab ?: '—' }}</td>
                        <td class="border border-red-500/40 px-2 py-1.5 text-red-300 text-[10px]" title="{{ $manba }}">{{ Str::limit($manba, 20) ?: '—' }}</td>
                        <td class="border border-red-500/40 px-2 py-1.5 text-red-300">{{ $hujjat ?: ($refund->hujjat_raqami ?? '—') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-2 border-t border-red-500/50 flex items-center justify-between bg-red-900/40">
            <span class="text-sm text-red-300 font-medium">Jami qaytarilgan:</span>
            <span class="text-lg font-bold text-red-400">{{ number_format(abs($refundPayments->sum('summa')), 0, '', ' ') }} UZS</span>
        </div>
    </div>
    @endif

    <!-- Payment Modal (Simplified) -->
    <div x-show="showPaymentModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showPaymentModal=false">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-900">To'lov qilish</h3>
                <button @click="showPaymentModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <!-- Current Debt Summary -->
            <div class="px-4 py-3 bg-red-50 border-b border-red-100">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-red-700">Joriy qarz:</span>
                    <span class="text-lg font-bold text-red-700">{{ number_format($grandDebt, 0, ',', ' ') }}</span>
                </div>
                @if($grandOverdue > 0)
                <div class="flex justify-between items-center mt-1">
                    <span class="text-xs text-red-600">Muddati o'tgan:</span>
                    <span class="text-sm font-medium text-red-600">{{ number_format($grandOverdue, 0, ',', ' ') }}</span>
                </div>
                @endif
                @if($grandPenya > 0)
                <div class="flex justify-between items-center mt-1">
                    <span class="text-xs text-amber-600">Penya:</span>
                    <span class="text-sm font-medium text-amber-600">{{ number_format($grandPenya, 0, ',', ' ') }}</span>
                </div>
                @endif
            </div>

            <form @submit.prevent="submitPayment" class="p-4 space-y-4">
                <!-- Quick Amount Buttons -->
                <div>
                    <label class="block text-xs text-gray-500 mb-2">Tezkor summa:</label>
                    <div class="grid grid-cols-3 gap-2">
                        @if($grandOverdue > 0)
                        <button type="button" @click="paymentForm.summa = {{ $grandOverdue }}" class="px-2 py-2 text-xs border border-red-300 text-red-700 rounded hover:bg-red-50">Muddati o'tgan<br><b>{{ number_format($grandOverdue, 0, ',', ' ') }}</b></button>
                        @endif
                        <button type="button" @click="paymentForm.summa = {{ $grandDebt }}" class="px-2 py-2 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50">To'liq qarz<br><b>{{ number_format($grandDebt, 0, ',', ' ') }}</b></button>
                        @php
                            $oylikOrtacha = $contract->paymentSchedules->count() > 0 ? round($grandTotal / $contract->paymentSchedules->count()) : 0;
                        @endphp
                        <button type="button" @click="paymentForm.summa = {{ $oylikOrtacha }}" class="px-2 py-2 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50">1 oylik<br><b>{{ number_format($oylikOrtacha, 0, ',', ' ') }}</b></button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Summa</label>
                    <input type="number" step="any" x-model="paymentForm.summa" class="w-full border border-gray-300 rounded px-3 py-2 text-lg font-bold" required placeholder="0">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Sana</label>
                        <input type="date" x-model="paymentForm.tolov_sanasi" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Turi</label>
                        <select x-model="paymentForm.tolov_turi" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                            <option value="bank">Bank o'tkazmasi</option>
                            <option value="naqd">Naqd</option>
                            <option value="plastik">Plastik</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full py-3 bg-blue-600 text-white rounded font-bold text-sm hover:bg-blue-700" :disabled="loading">
                    <span x-show="!loading">To'lovni saqlash</span>
                    <span x-show="loading">Yuklanmoqda...</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Add Schedule Modal (Year Period Based) -->
    <div x-show="showAddScheduleModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showAddScheduleModal=false">
        <div class="bg-white rounded-lg w-full max-w-lg" x-data="scheduleGenerator()">
            <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-900">Yangi to'lov grafigi</h3>
                <button @click="showAddScheduleModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <!-- Mode Tabs -->
            <div class="flex border-b border-gray-200">
                <button @click="mode='auto'" :class="mode==='auto' ? 'border-b-2 border-blue-600 text-blue-600 bg-blue-50' : 'text-gray-500'" class="flex-1 px-4 py-2 text-sm font-medium">Avtomatik (davr)</button>
                <button @click="mode='single'" :class="mode==='single' ? 'border-b-2 border-blue-600 text-blue-600 bg-blue-50' : 'text-gray-500'" class="flex-1 px-4 py-2 text-sm font-medium">Bitta oy</button>
            </div>

            <div class="p-4">
                <!-- Auto Mode: Period-based -->
                <div x-show="mode==='auto'" class="space-y-4">
                    <div class="p-3 bg-blue-50 rounded text-xs text-blue-700 border border-blue-200">
                        <b>Qanday ishlaydi:</b> Davr boshidan oxirigacha har oy uchun grafik avtomatik yaratiladi. Yillik summa oylarga teng bo'linadi.
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Davr boshi</label>
                            <input type="date" x-model="periodStart" @change="calculateMonths()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Davr oxiri</label>
                            <input type="date" x-model="periodEnd" @change="calculateMonths()" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Yillik summa (davr uchun jami)</label>
                        <input type="number" step="any" x-model="yearAmount" @input="calculateMonthly()" class="w-full border border-gray-300 rounded px-3 py-2 text-lg font-bold" placeholder="0" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Har oyning qaysi kuni?</label>
                        <select x-model="paymentDay" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                            <option value="10">10-kuni</option>
                            <option value="1">1-kuni</option>
                            <option value="5">5-kuni</option>
                            <option value="15">15-kuni</option>
                            <option value="20">20-kuni</option>
                            <option value="25">25-kuni</option>
                        </select>
                    </div>

                    <!-- Preview -->
                    <div x-show="monthCount > 0" class="p-3 bg-gray-50 rounded border border-gray-200">
                        <div class="text-sm font-medium text-gray-700 mb-2">Natija:</div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div>
                                <div class="text-2xl font-bold text-blue-600" x-text="monthCount"></div>
                                <div class="text-xs text-gray-500">oy</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-800" x-text="formatNumber(monthlyAmount)"></div>
                                <div class="text-xs text-gray-500">oylik summa</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-green-600" x-text="formatNumber(yearAmount)"></div>
                                <div class="text-xs text-gray-500">jami</div>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="submitAutoSchedule()" class="w-full py-3 bg-blue-600 text-white rounded font-bold text-sm hover:bg-blue-700" :disabled="loading || monthCount < 1">
                        <span x-show="!loading"><span x-text="monthCount"></span> ta grafik yaratish</span>
                        <span x-show="loading">Yuklanmoqda...</span>
                    </button>
                </div>

                <!-- Single Mode: One month -->
                <div x-show="mode==='single'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To'lov sanasi</label>
                        <input type="date" x-model="singleDate" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Summa</label>
                        <input type="number" step="any" x-model="singleAmount" class="w-full border border-gray-300 rounded px-3 py-2 text-lg font-bold" placeholder="0" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Oxirgi muddat (ixtiyoriy)</label>
                        <input type="date" x-model="singleDeadline" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <p class="text-xs text-gray-400 mt-1">Bo'sh = to'lov sanasidan 10 kun keyin</p>
                    </div>
                    <button type="button" @click="submitSingleSchedule()" class="w-full py-3 bg-gray-800 text-white rounded font-bold text-sm hover:bg-gray-900" :disabled="loading">
                        <span x-show="!loading">Grafik qo'shish</span>
                        <span x-show="loading">Yuklanmoqda...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Utilities -->
    <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-6 mt-6">
        <h2 class="font-semibold text-white mb-4">Kommunikatsiyalar</h2>
        <div class="grid grid-cols-4 md:grid-cols-7 gap-3">
            @php
            $utilities = [
                ['key' => 'has_elektr', 'name' => 'Elektr', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['key' => 'has_gaz', 'name' => 'Gaz', 'icon' => 'M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z'],
                ['key' => 'has_suv', 'name' => 'Suv', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                ['key' => 'has_kanalizatsiya', 'name' => 'Kanal.', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                ['key' => 'has_internet', 'name' => 'Internet', 'icon' => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0'],
                ['key' => 'has_isitish', 'name' => 'Isitish', 'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707'],
                ['key' => 'has_konditsioner', 'name' => 'Kond.', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ];
            @endphp
            @foreach($utilities as $util)
            <div class="flex flex-col items-center gap-2 p-3 rounded-lg {{ $lot->{$util['key']} ? 'bg-green-500/10' : 'bg-slate-700/50' }}">
                <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $lot->{$util['key']} ? 'bg-green-500/20' : 'bg-slate-600' }}">
                    <svg class="w-5 h-5 {{ $lot->{$util['key']} ? 'text-green-400' : 'text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $util['icon'] }}"/></svg>
                </div>
                <span class="text-xs text-center {{ $lot->{$util['key']} ? 'text-green-400 font-medium' : 'text-slate-500' }}">{{ $util['name'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    @if($lot->tavsif)
    <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-6 mt-6">
        <h2 class="font-semibold text-white mb-3">Muhim ma'lumotlar:</h2>
        <p class="text-slate-300 whitespace-pre-wrap">{{ $lot->tavsif }}</p>
    </div>
    @endif

    <!-- Contract History -->
    @if($lot->contracts->count() > 1)
    <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 mt-6 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 bg-slate-800/80"><h2 class="font-semibold text-white">Shartnomalar tarixi</h2></div>
        <div class="divide-y divide-slate-700/50">
            @foreach($lot->contracts->where('holat', '!=', 'faol') as $oldContract)
            <div class="flex items-center justify-between p-4">
                <div>
                    <p class="font-medium text-white">{{ $oldContract->shartnoma_raqami }}</p>
                    <p class="text-sm text-slate-400">{{ $oldContract->tenant->name ?? '-' }}</p>
                </div>
                <div class="text-right">
                    <p class="font-medium text-white">{{ number_format($oldContract->shartnoma_summasi, 0, '.', ' ') }}</p>
                    <span class="text-xs px-2 py-1 rounded bg-slate-700 text-slate-300">{{ $oldContract->holat_nomi }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
function lotDetail() {
    return {
        showPaymentModal: false,
        showAddScheduleModal: false,
        loading: false,
        @if($contract)
        paymentForm: { contract_id: {{ $contract->id }}, summa: '', tolov_sanasi: new Date().toISOString().split('T')[0], tolov_turi: 'bank' },
        @else
        paymentForm: {},
        @endif

        init() {},

        @if($contract)
        async submitPayment() {
            this.loading = true;
            try {
                const res = await fetch('/api/payments', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(this.paymentForm) });
                if (res.ok) { this.showPaymentModal = false; window.location.reload(); } else { const err = await res.json(); alert(err.message || 'Xatolik'); }
            } catch (e) { alert('Xatolik'); }
            this.loading = false;
        },

        async updateSchedule(id, form) {
            try {
                const res = await fetch(`/api/payment-schedules/${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({
                        tolov_sanasi: form.tolov_sanasi,
                        oxirgi_muddat: form.oxirgi_muddat,
                        custom_oxirgi_muddat: form.new_deadline,
                        tolov_summasi: parseFloat(form.tolov_summasi)
                    })
                });
                if (res.ok) { window.location.reload(); }
                else { const err = await res.json(); alert(err.message || 'Xatolik'); }
            } catch (e) { alert('Xatolik: ' + e.message); }
        },

        async deleteSchedule(id) {
            if (!confirm('Ushbu grafikni o\'chirishni tasdiqlaysizmi?')) return;
            try {
                const res = await fetch(`/api/payment-schedules/${id}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (res.ok) { window.location.reload(); }
                else { const err = await res.json(); alert(err.message || 'To\'lov qilingan grafikni o\'chirib bo\'lmaydi'); }
            } catch (e) { alert('Xatolik: ' + e.message); }
        },

        async deletePeriodSchedules(ids) {
            if (!confirm(`${ids.length} ta grafikni o'chirishni tasdiqlaysizmi?`)) return;

            let deleted = 0;
            let failed = 0;

            for (const id of ids) {
                try {
                    const res = await fetch(`/api/payment-schedules/${id}`, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    if (res.ok) { deleted++; }
                    else { failed++; }
                } catch (e) { failed++; }
            }

            alert(`${deleted} ta grafik o'chirildi` + (failed > 0 ? `, ${failed} ta o'chirilmadi` : ''));
            window.location.reload();
        }
        @endif
    }
}

// Schedule Generator Component
@if($contract)
function scheduleGenerator() {
    // Default: next year period from contract start date
    const contractStart = '{{ $contract->boshlanish_sanasi ?? date("Y-m-d") }}';
    const startDate = new Date(contractStart);
    const nextPeriodStart = new Date(startDate);
    nextPeriodStart.setFullYear(nextPeriodStart.getFullYear() + Math.ceil((new Date() - startDate) / (365.25 * 24 * 60 * 60 * 1000)));
    const nextPeriodEnd = new Date(nextPeriodStart);
    nextPeriodEnd.setFullYear(nextPeriodEnd.getFullYear() + 1);
    nextPeriodEnd.setDate(nextPeriodEnd.getDate() - 1);

    const defaultSingle = new Date();
    defaultSingle.setMonth(defaultSingle.getMonth() + 1);
    defaultSingle.setDate(10);

    return {
        mode: 'auto',
        loading: false,

        // Auto mode
        periodStart: nextPeriodStart.toISOString().split('T')[0],
        periodEnd: nextPeriodEnd.toISOString().split('T')[0],
        yearAmount: '',
        paymentDay: '10',
        monthCount: 12,
        monthlyAmount: 0,

        // Single mode
        singleDate: defaultSingle.toISOString().split('T')[0],
        singleAmount: '',
        singleDeadline: '',

        calculateMonths() {
            if (!this.periodStart || !this.periodEnd) { this.monthCount = 0; return; }
            const start = new Date(this.periodStart);
            const end = new Date(this.periodEnd);
            const months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth()) + 1;
            this.monthCount = Math.max(0, months);
            this.calculateMonthly();
        },

        calculateMonthly() {
            if (this.monthCount > 0 && this.yearAmount > 0) {
                this.monthlyAmount = Math.round(this.yearAmount / this.monthCount);
            } else {
                this.monthlyAmount = 0;
            }
        },

        formatNumber(num) {
            return new Intl.NumberFormat('uz-UZ').format(num || 0);
        },

        async submitAutoSchedule() {
            if (this.monthCount < 1 || !this.yearAmount) { alert('Ma\'lumotlarni to\'ldiring'); return; }
            this.loading = true;
            try {
                const res = await fetch('/api/payment-schedules/contract/{{ $contract->id }}/bulk', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({
                        period_start: this.periodStart,
                        period_end: this.periodEnd,
                        year_amount: parseFloat(this.yearAmount),
                        payment_day: parseInt(this.paymentDay)
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    alert(data.message || 'Muvaffaqiyatli!');
                    window.location.reload();
                } else {
                    // Handle validation errors
                    let errorMsg = data.message;
                    if (typeof errorMsg === 'object') {
                        errorMsg = Object.values(errorMsg).flat().join('\n');
                    }
                    alert('Xatolik: ' + errorMsg);
                }
            } catch (e) { alert('Xatolik: ' + e.message); }
            this.loading = false;
        },

        async submitSingleSchedule() {
            if (!this.singleDate || !this.singleAmount) { alert('Ma\'lumotlarni to\'ldiring'); return; }
            this.loading = true;
            try {
                const res = await fetch('/api/payment-schedules/contract/{{ $contract->id }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({
                        tolov_sanasi: this.singleDate,
                        tolov_summasi: this.singleAmount,
                        oxirgi_muddat: this.singleDeadline || null
                    })
                });
                if (res.ok) { window.location.reload(); }
                else { const err = await res.json(); alert(err.message || 'Xatolik'); }
            } catch (e) { alert('Xatolik: ' + e.message); }
            this.loading = false;
        }
    }
}
@endif</script>
@endsection
