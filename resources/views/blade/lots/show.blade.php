@extends('layouts.dark')
@section('title', 'Lot: ' . $lot->lot_raqami)
@section('header', 'Lot ' . $lot->lot_raqami)
@section('subheader', $lot->obyekt_nomi)
@section('header-actions')
<a href="{{ route('lots.edit', $lot) }}" class="btn btn-secondary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
    Tahrirlash
</a>
@if(!$contract)
<a href="{{ route('contracts.create', ['lot_id' => $lot->id]) }}" class="btn btn-primary">
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
            Shartnoma: <a href="{{ route('contracts.show', $contract) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $contract->shartnoma_raqami }}</a> • {{ \Carbon\Carbon::parse($contract->boshlanish_sanasi)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($contract->tugash_sanasi)->format('d.m.Y') }}
        </span>
        @endif
    </div>

    @if($contract && $stats)
    <!-- Contract Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Shartnoma summasi -->
        <div class="bg-white rounded-lg border border-gray-200 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">SHARTNOMA SUMMASI</p>
            <p class="text-4xl font-bold text-gray-900 mt-3">{!! formatLotSum($stats['jami_summa']) !!}</p>
            <p class="text-xs text-gray-400 mt-4">Shartnoma bo'yicha jami</p>
        </div>

        <!-- To'langan -->
        <div class="bg-white rounded-lg border border-gray-200 border-l-4 border-l-green-500 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">TO'LANGAN</p>
            <p class="text-4xl font-bold text-green-600 mt-3">{!! formatLotSum($stats['tolangan']) !!}</p>
            <p class="text-xs text-gray-400 mt-4">Fakt tushum</p>
        </div>

        <!-- Qoldiq -->
        <div class="bg-white rounded-lg border border-gray-200 border-l-4 border-l-red-500 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">QOLDIQ</p>
            <p class="text-4xl font-bold {{ $stats['qoldiq'] > 0 ? 'text-red-600' : 'text-gray-900' }} mt-3">{!! formatLotSum($stats['qoldiq']) !!}</p>
            <p class="text-xs text-gray-400 mt-4">To'lanmagan summa</p>
        </div>

        <!-- Penya -->
        <div class="bg-white rounded-lg border border-gray-200 border-l-4 border-l-amber-500 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">PENYA</p>
            <p class="text-4xl font-bold {{ $stats['penya'] > 0 ? 'text-amber-600' : 'text-gray-900' }} mt-3">{!! formatLotSum($stats['penya']) !!}</p>
            <p class="text-xs text-gray-400 mt-4">Kechikish uchun jarima</p>
        </div>
    </div>

    <!-- Progress -->
    @php $paidPercent = $stats['jami_summa'] > 0 ? round(($stats['tolangan'] / $stats['jami_summa']) * 100, 1) : 0; @endphp
    <div class="bg-white rounded-lg border border-gray-200 p-5 mb-6">
        <div class="flex justify-between text-sm mb-3">
            <span class="font-medium text-gray-700">To'lov jarayoni</span>
            <span class="font-bold text-gray-900">{{ $paidPercent }}%</span>
        </div>
        <div class="h-3 bg-gray-100 rounded-full">
            <div class="h-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full transition-all" style="width: {{ $paidPercent }}%"></div>
        </div>
    </div>
    @endif

    <!-- Main Grid: Lot Info + Contract/Tenant Info -->
    <div class="grid lg:grid-cols-3 gap-6 mb-6">
        <!-- Lot Image & Details -->
        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="grid md:grid-cols-2 gap-0">
                <!-- Image -->
                <div class="bg-gray-100">
                    <div class="aspect-square relative">
                        @if($mainImage)
                        <img id="mainImage" src="{{ asset('storage/' . $mainImage) }}" alt="{{ $lot->obyekt_nomi }}" class="w-full h-full object-cover">
                        @else
                        <div class="w-full h-full flex items-center justify-center bg-gray-200">
                            <svg class="w-24 h-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        @endif
                    </div>
                    @if(count($images) > 1)
                    <div class="flex gap-1 p-2 overflow-x-auto bg-white border-t">
                        @foreach($images as $index => $img)
                        <button onclick="document.getElementById('mainImage').src='{{ asset('storage/' . $img) }}'" class="flex-shrink-0 w-14 h-14 rounded border overflow-hidden hover:border-gray-400 transition {{ $index === $mainIndex ? 'border-gray-900' : 'border-transparent' }}">
                            <img src="{{ asset('storage/' . $img) }}" alt="" class="w-full h-full object-cover">
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Lot Details -->
                <div class="p-5 space-y-1 text-sm">
                    <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Lot raqami</span><span class="font-bold text-gray-900">{{ $lot->lot_raqami }}</span></div>
                    <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Obyekt turi</span><span class="text-gray-900">{{ $lot->obyekt_turi_nomi }}</span></div>
                    <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Maydon</span><span class="font-bold text-gray-900">{{ number_format($lot->maydon, 2) }} m²</span></div>
                    @if($lot->xonalar_soni)<div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Xonalar</span><span class="text-gray-900">{{ $lot->xonalar_soni }}</span></div>@endif
                    @if($lot->qavat)<div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Qavat</span><span class="text-gray-900">{{ $lot->qavat }}{{ $lot->qavatlar_soni ? '/' . $lot->qavatlar_soni : '' }}</span></div>@endif
                    @if($lot->kadastr_raqami)<div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Kadastr</span><span class="font-mono text-xs text-gray-900">{{ $lot->kadastr_raqami }}</span></div>@endif
                    <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Tuman</span><span class="text-gray-900">{{ $lot->tuman ?? '—' }}</span></div>
                    <div class="flex justify-between py-3"><span class="text-gray-500">Manzil</span><span class="text-right max-w-[200px] text-gray-900">{{ $lot->toliq_manzil }}</span></div>
                </div>
            </div>
        </div>

        <!-- Tenant Info (if contract exists) -->
        <div class="space-y-4">
            @if($contract && $contract->tenant)
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <h3 class="font-semibold text-gray-900">Ijarachi</h3>
                    <a href="{{ route('tenants.show', $contract->tenant) }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Batafsil</a>
                </div>
                <div class="p-5 space-y-1 text-sm">
                    <div class="flex justify-between py-2"><span class="text-gray-500">Nomi:</span><span class="text-gray-900 font-semibold">{{ $contract->tenant->name }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-gray-500">INN:</span><span class="text-gray-900 font-mono font-medium">{{ $contract->tenant->inn ?? '—' }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-gray-500">Direktor:</span><span class="text-gray-900">{{ $contract->tenant->director_name ?? '—' }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-gray-500">Telefon:</span><span class="text-gray-900 font-medium">{{ $contract->tenant->phone ?? '—' }}</span></div>
                </div>
            </div>
            @endif

            @if($contract)
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="font-semibold text-gray-900">Shartnoma</h3>
                </div>
                <div class="p-5 space-y-1 text-sm">
                    <div class="flex justify-between py-2"><span class="text-gray-500">Raqam:</span><span class="text-gray-900 font-semibold">{{ $contract->shartnoma_raqami }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-gray-500">Muddat:</span><span class="text-gray-900">{{ $contract->shartnoma_muddati }} oy</span></div>
                    <div class="flex justify-between py-2"><span class="text-gray-500">Boshlanish:</span><span class="text-gray-900">{{ \Carbon\Carbon::parse($contract->boshlanish_sanasi)->format('d.m.Y') }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-gray-500">Tugash:</span><span class="text-gray-900">{{ \Carbon\Carbon::parse($contract->tugash_sanasi)->format('d.m.Y') }}</span></div>
                    <div class="flex justify-between py-2"><span class="text-gray-500">Oylik:</span><span class="text-gray-900 font-bold">{{ number_format($contract->oylik_tolovi, 0, '', ' ') }}</span></div>
                </div>
            </div>
            @else
            <div class="bg-white border border-gray-200 rounded-lg p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-gray-500 mb-4">Faol shartnoma yo'q</p>
                <a href="{{ route('contracts.create', ['lot_id' => $lot->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
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
                    $periodPenya = $periodSchedules->sum('penya_summasi') - $periodSchedules->sum('tolangan_penya');
                    $periodOverdue = $periodSchedules->filter(fn($s) => $s->qoldiq_summa > 0 && \Carbon\Carbon::parse($s->oxirgi_muddat)->lt($bugun))->sum('qoldiq_summa');
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
                        'penya' => max(0, $periodPenya),
                        'percent' => $periodPercent,
                    ];
                    $periodNum++;
                }

                $periodStart = $periodStart->copy()->addMonths(12);
            }
        }

        // Grand totals
        $grandTotal = $allSchedules->sum('tolov_summasi');
        $grandPaid = $allSchedules->sum('tolangan_summa');
        $grandDebt = $allSchedules->sum('qoldiq_summa');
        $grandPenya = max(0, $allSchedules->sum('penya_summasi') - $allSchedules->sum('tolangan_penya'));
        $grandOverdue = $allSchedules->filter(fn($s) => $s->qoldiq_summa > 0 && \Carbon\Carbon::parse($s->oxirgi_muddat)->lt($bugun))->sum('qoldiq_summa');
        $grandPercent = $grandTotal > 0 ? round(($grandPaid / $grandTotal) * 100, 1) : 0;

        // Find current period
        $currentPeriodNum = null;
        foreach ($contractYearPeriods as $idx => $p) {
            if ($bugun->gte($p['start']) && $bugun->lte($p['end'])) {
                $currentPeriodNum = $p['num'];
                break;
            }
        }
    @endphp

    <!-- Professional Government Dashboard Table -->
    <div class="bg-white border border-gray-300 rounded mb-6 overflow-hidden">
        <!-- Header -->
        <div class="px-4 py-3 bg-gray-100 border-b border-gray-300 flex justify-between items-center">
            <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">To'lov jadvali</h3>
            <div class="flex items-center gap-2">
                <button @click="showAddScheduleModal = true" class="px-3 py-1.5 border border-gray-400 text-gray-700 text-xs hover:bg-gray-200">+ Grafik</button>
                <button @click="showPaymentModal = true" class="px-3 py-1.5 bg-blue-600 text-white text-xs hover:bg-blue-700">+ To'lov</button>
            </div>
        </div>

        @if(count($contractYearPeriods) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th rowspan="2" class="border border-gray-300 px-2 py-2 text-left font-semibold text-gray-700 w-8">№</th>
                        <th rowspan="2" class="border border-gray-300 px-2 py-2 text-left font-semibold text-gray-700 min-w-[140px]">Shartnoma davri</th>
                        <th rowspan="2" class="border border-gray-300 px-2 py-2 text-center font-semibold text-gray-700">Oylar</th>
                        <th colspan="2" class="border border-gray-300 px-2 py-1 text-center font-semibold text-gray-700 bg-gray-100">Reja</th>
                        <th colspan="2" class="border border-gray-300 px-2 py-1 text-center font-semibold text-gray-700 bg-blue-50">Fakt</th>
                        <th colspan="2" class="border border-gray-300 px-2 py-1 text-center font-semibold text-gray-700 bg-red-50">Qoldiq</th>
                        <th rowspan="2" class="border border-gray-300 px-2 py-2 text-center font-semibold text-gray-700">%</th>
                        <th rowspan="2" class="border border-gray-300 px-2 py-2 text-center font-semibold text-gray-700 bg-amber-50">Penya</th>
                        <th rowspan="2" class="border border-gray-300 px-2 py-2 text-center font-semibold text-gray-700 w-20">Amal</th>
                    </tr>
                    <tr class="bg-gray-50 text-[10px]">
                        <th class="border border-gray-300 px-2 py-1 text-right text-gray-600">summa</th>
                        <th class="border border-gray-300 px-2 py-1 text-right text-gray-600">oylik</th>
                        <th class="border border-gray-300 px-2 py-1 text-right text-gray-600 bg-blue-50">tushgan</th>
                        <th class="border border-gray-300 px-2 py-1 text-right text-gray-600 bg-blue-50">oylik o'rt</th>
                        <th class="border border-gray-300 px-2 py-1 text-right text-gray-600 bg-red-50">jami</th>
                        <th class="border border-gray-300 px-2 py-1 text-right text-gray-600 bg-red-50">muddati o'tgan</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- JAMI Row -->
                    <tr class="bg-blue-50 font-bold">
                        <td class="border border-gray-300 px-2 py-2 text-center"></td>
                        <td class="border border-gray-300 px-2 py-2 text-gray-900">JAMI:</td>
                        <td class="border border-gray-300 px-2 py-2 text-center text-blue-700">{{ $allSchedules->count() }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right">{{ number_format($grandTotal, 0, ',', ' ') }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right text-gray-600">{{ $allSchedules->count() > 0 ? number_format($grandTotal / $allSchedules->count(), 0, ',', ' ') : 0 }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right text-blue-700">{{ number_format($grandPaid, 0, ',', ' ') }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right text-blue-600">{{ $allSchedules->where('tolangan_summa', '>', 0)->count() > 0 ? number_format($grandPaid / $allSchedules->where('tolangan_summa', '>', 0)->count(), 0, ',', ' ') : 0 }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right text-red-600">{{ number_format($grandDebt, 0, ',', ' ') }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right text-red-700 font-bold">{{ number_format($grandOverdue, 0, ',', ' ') }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-center {{ $grandPercent >= 100 ? 'text-green-700' : ($grandPercent >= 50 ? 'text-blue-700' : 'text-red-700') }}">{{ $grandPercent }}%</td>
                        <td class="border border-gray-300 px-2 py-2 text-right text-amber-700">{{ number_format($grandPenya, 0, ',', ' ') }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-center"></td>
                    </tr>

                    @foreach($contractYearPeriods as $period)
                    @php
                        $isCurrentPeriod = $period['num'] === $currentPeriodNum;
                        $periodScheduleIds = $period['schedules']->pluck('id')->toArray();
                        $canDeletePeriod = $period['paid'] <= 0; // Can only delete if nothing paid
                    @endphp
                    <tr class="hover:bg-gray-50 {{ $isCurrentPeriod ? 'bg-blue-50 border-l-4 border-l-blue-500' : '' }}">
                        <td class="border border-gray-300 px-2 py-2 text-center {{ $isCurrentPeriod ? 'font-bold text-blue-700' : 'text-gray-600' }}">{{ $period['num'] }}</td>
                        <td class="border border-gray-300 px-2 py-2">
                            <div class="flex items-center gap-2">
                                @if($isCurrentPeriod)
                                <span class="px-1.5 py-0.5 bg-blue-600 text-white text-[9px] font-bold rounded">JORIY</span>
                                @endif
                                <span class="font-medium text-gray-900">{{ $period['start']->format('d.m.Y') }}</span>
                                <span class="text-gray-400 mx-1">—</span>
                                <span class="font-medium text-gray-900">{{ $period['end']->format('d.m.Y') }}</span>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-2 py-2 text-center font-medium">{{ $period['months'] }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right">{{ number_format($period['total'], 0, ',', ' ') }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right text-gray-600">{{ $period['months'] > 0 ? number_format($period['total'] / $period['months'], 0, ',', ' ') : 0 }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right {{ $period['paid'] > 0 ? 'text-blue-700 font-medium' : 'text-gray-400' }}">{{ number_format($period['paid'], 0, ',', ' ') }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right text-gray-600">{{ $period['schedules']->where('tolangan_summa', '>', 0)->count() > 0 ? number_format($period['paid'] / $period['schedules']->where('tolangan_summa', '>', 0)->count(), 0, ',', ' ') : '—' }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right {{ $period['debt'] > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($period['debt'], 0, ',', ' ') }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-right {{ $period['overdue'] > 0 ? 'text-red-700 font-bold' : 'text-gray-400' }}">{{ $period['overdue'] > 0 ? number_format($period['overdue'], 0, ',', ' ') : '—' }}</td>
                        <td class="border border-gray-300 px-2 py-2 text-center font-medium {{ $period['percent'] >= 100 ? 'text-green-700' : ($period['percent'] >= 50 ? 'text-blue-700' : 'text-red-700') }}">{{ $period['percent'] }}%</td>
                        <td class="border border-gray-300 px-2 py-2 text-right {{ $period['penya'] > 0 ? 'text-amber-700' : 'text-gray-400' }}">{{ $period['penya'] > 0 ? number_format($period['penya'], 0, ',', ' ') : '—' }}</td>
                        <!-- Period Actions -->
                        <td class="border border-gray-300 px-2 py-2 text-center">
                            @if($canDeletePeriod)
                            <button @click="deletePeriodSchedules([{{ implode(',', $periodScheduleIds) }}])"
                                    class="p-1 text-gray-400 hover:text-red-600" title="Davrni o'chirish ({{ $period['months'] }} oy)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            @else
                            <span class="p-1 text-gray-300" title="To'langan davr o'chirilmaydi">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Monthly Details (Expandable) -->
        <div x-data="{ showDetails: false }" class="border-t border-gray-300">
            <button @click="showDetails = !showDetails" class="w-full px-4 py-2 text-left text-xs text-gray-600 hover:bg-gray-50 flex items-center justify-between">
                <span class="font-medium">Oylik tafsilotlar</span>
                <svg :class="showDetails ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="showDetails" x-collapse>
                <table class="w-full text-xs border-collapse">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 px-2 py-1.5 text-center font-medium text-gray-700">№</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-left font-medium text-gray-700">Oy</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-center font-medium text-gray-700">Muddat</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-right font-medium text-gray-700">Grafik</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-right font-medium text-gray-700">To'langan</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-center font-medium text-gray-700">To'lov sanasi</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-right font-medium text-gray-700">Qoldiq</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-center font-medium text-gray-700">Kun</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-center font-medium text-gray-700">Stavka</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-right font-medium text-gray-700">Penya hisob</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-right font-medium text-gray-700">To'l. penya</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-right font-medium text-gray-700">Qol. penya</th>
                            <th class="border border-gray-300 px-2 py-1.5 text-center font-medium text-gray-700 w-16">Amal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowNum = 0; @endphp
                        @foreach($contractYearPeriods as $period)
                            @foreach($period['schedules'] as $idx => $schedule)
                            @php
                                $rowNum++;
                                $muddatDate = \Carbon\Carbon::parse($schedule->oxirgi_muddat);
                                $tolanganPenya = $schedule->tolangan_penya ?? 0;

                                // Find the actual payment date for this schedule
                                $lastPaymentDate = null;
                                $schedulePayments = $contract->payments
                                    ->where('created_at', '>=', $muddatDate)
                                    ->sortBy('tolov_sanasi');

                                // Check if this schedule has been paid (partially or fully)
                                if ($schedule->tolangan_summa > 0) {
                                    // Find payment that likely paid this schedule
                                    foreach ($contract->payments->sortBy('tolov_sanasi') as $pmt) {
                                        $pmtDate = \Carbon\Carbon::parse($pmt->tolov_sanasi);
                                        if ($pmtDate->gte($muddatDate->copy()->subDays(30))) {
                                            $lastPaymentDate = $pmtDate;
                                            break;
                                        }
                                    }
                                }

                                // Calculate days overdue (penalty starts from day AFTER deadline)
                                $kechikish = 0;
                                $penyaBase = 0; // Amount used for penalty calculation
                                $penyaStartDate = $muddatDate->copy()->addDay(); // Penalty starts from day 11 (after deadline)

                                if ($schedule->tolangan_summa > 0 && $lastPaymentDate && $lastPaymentDate->gt($muddatDate)) {
                                    // If paid late, calculate days from (deadline+1) to payment date
                                    $kechikish = max(0, $penyaStartDate->diffInDays($lastPaymentDate, false));
                                    $penyaBase = $schedule->tolov_summasi; // Original amount for penalty at payment time
                                } elseif ($schedule->qoldiq_summa > 0 && $bugun->gt($muddatDate)) {
                                    // If still has debt and overdue, calculate from (deadline+1) to today
                                    $kechikish = max(0, $penyaStartDate->diffInDays($bugun, false));
                                    $penyaBase = $schedule->qoldiq_summa; // Remaining debt for ongoing penalty
                                }

                                // Penya calculation: debt × 0.4% × days (0.4% = 0.004)
                                $penyaRate = 0.004; // 0.4% per day
                                $rawPenya = $penyaBase * $penyaRate * $kechikish;
                                $maxPenya = $penyaBase * 0.5; // 50% cap
                                $penyaHisob = min($rawPenya, $maxPenya);

                                $qoldiqPenya = max(0, $penyaHisob - $tolanganPenya);

                                $monthNames = ['', 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avg', 'Sent', 'Okt', 'Noy', 'Dek'];
                                $isCurrentMonth = ($schedule->oy == $currentMonth && $schedule->yil == $currentYear);
                                $canDelete = $schedule->tolangan_summa <= 0;
                                $isOverdue = $kechikish > 0;
                            @endphp
                            <tr x-data="{ editing: false, form: { tolov_sanasi: '{{ \Carbon\Carbon::parse($schedule->tolov_sanasi)->format('Y-m-d') }}', oxirgi_muddat: '{{ $muddatDate->format('Y-m-d') }}', tolov_summasi: {{ $schedule->tolov_summasi }} } }"
                                class="{{ $isOverdue && $schedule->qoldiq_summa > 0 ? 'bg-gray-50' : '' }} hover:bg-gray-100">
                                <td class="border border-gray-300 px-2 py-2 text-center">{{ $rowNum }}</td>
                                <td class="border border-gray-300 px-2 py-2">
                                    {{ $monthNames[$schedule->oy] ?? $schedule->oy }} {{ $schedule->yil }}
                                    @if($isCurrentMonth)<span class="text-[9px] font-bold">(joriy)</span>@endif
                                </td>
                                <td class="border border-gray-300 px-2 py-2 text-center">
                                    <template x-if="!editing">
                                        <span>{{ $muddatDate->format('d.m.Y') }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <input type="date" x-model="form.oxirgi_muddat" class="w-full border rounded px-1 py-0.5 text-xs">
                                    </template>
                                </td>
                                <td class="border border-gray-300 px-2 py-2 text-right">
                                    <template x-if="!editing">
                                        <span>{{ number_format($schedule->tolov_summasi, 0, ',', ' ') }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <input type="number" x-model="form.tolov_summasi" class="w-full border rounded px-1 py-0.5 text-xs text-right">
                                    </template>
                                </td>
                                <td class="border border-gray-300 px-2 py-2 text-right">{{ $schedule->tolangan_summa > 0 ? number_format($schedule->tolangan_summa, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-gray-300 px-2 py-2 text-center">{{ $lastPaymentDate ? $lastPaymentDate->format('d.m.Y') : '—' }}</td>
                                <td class="border border-gray-300 px-2 py-2 text-right font-medium">{{ $schedule->qoldiq_summa > 0 ? number_format($schedule->qoldiq_summa, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-gray-300 px-2 py-2 text-center">{{ $kechikish > 0 ? $kechikish : '—' }}</td>
                                <td class="border border-gray-300 px-2 py-2 text-center">{{ $kechikish > 0 ? '0,4%' : '—' }}</td>
                                <td class="border border-gray-300 px-2 py-2 text-right">{{ $penyaHisob > 0 ? number_format($penyaHisob, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-gray-300 px-2 py-2 text-right">{{ $tolanganPenya > 0 ? number_format($tolanganPenya, 0, ',', ' ') : '—' }}</td>
                                <td class="border border-gray-300 px-2 py-2 text-right font-medium">{{ $qoldiqPenya > 0 ? number_format($qoldiqPenya, 0, ',', ' ') : ($tolanganPenya > 0 ? '✓' : '—') }}</td>
                                <!-- Actions -->
                                <td class="border border-gray-300 px-1 py-1 text-center">
                                    <template x-if="!editing">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="editing = true" class="p-1 text-gray-400 hover:text-blue-600" title="Tahrirlash">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                            @if($canDelete)
                                            <button @click="deleteSchedule({{ $schedule->id }})" class="p-1 text-gray-400 hover:text-red-600" title="O'chirish">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                            @else
                                            <span class="p-1 text-gray-300" title="To'langan grafikni o'chirib bo'lmaydi">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            </span>
                                            @endif
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="updateSchedule({{ $schedule->id }}, form); editing = false" class="p-1 bg-green-500 text-white rounded hover:bg-green-600" title="Saqlash">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                            <button @click="editing = false" class="p-1 bg-gray-400 text-white rounded hover:bg-gray-500" title="Bekor qilish">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
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
        @else
        <div class="px-4 py-8 text-center text-gray-500 text-sm">To'lov grafigi yo'q</div>
        @endif
    </div>

    <!-- Recent Payments -->
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-emerald-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900">To'lovlar tarixi</h3>
                    <p class="text-xs text-gray-500">Barcha kiritilgan to'lovlar</p>
                </div>
            </div>
            <span class="inline-flex items-center px-3 py-1 text-sm font-bold bg-emerald-100 text-emerald-700 rounded-full">{{ $contract->payments->count() }}</span>
        </div>

        @if($contract->payments->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Sana</th>
                        <th class="px-4 py-3 text-right font-medium">Summa</th>
                        <th class="px-4 py-3 text-right font-medium">Qarz uchun</th>
                        <th class="px-4 py-3 text-right font-medium">Penya</th>
                        <th class="px-4 py-3 text-right font-medium">Avans</th>
                        <th class="px-4 py-3 text-center font-medium">Turi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($contract->payments->sortByDesc('tolov_sanasi')->take(15) as $payment)
                    @php
                        $paymentDate = \Carbon\Carbon::parse($payment->tolov_sanasi);
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-bold text-gray-900">{{ $paymentDate->format('d.m.Y') }}</div>
                            <div class="text-xs text-gray-400">({{ $paymentDate->diffForHumans() }})</div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="text-lg font-bold text-emerald-600">+{{ number_format($payment->summa, 0, '', ' ') }}</span>
                        </td>
                        <td class="px-4 py-3 text-right {{ $payment->asosiy_qarz_uchun > 0 ? 'text-blue-600 font-medium' : 'text-gray-400' }}">
                            {{ $payment->asosiy_qarz_uchun > 0 ? number_format($payment->asosiy_qarz_uchun, 0, '', ' ') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right {{ $payment->penya_uchun > 0 ? 'text-amber-600 font-medium' : 'text-gray-400' }}">
                            {{ $payment->penya_uchun > 0 ? number_format($payment->penya_uchun, 0, '', ' ') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right {{ $payment->avans > 0 ? 'text-purple-600 font-medium' : 'text-gray-400' }}">
                            {{ $payment->avans > 0 ? number_format($payment->avans, 0, '', ' ') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $typeColors = [
                                    'naqd' => 'bg-green-100 text-green-700',
                                    'plastik' => 'bg-blue-100 text-blue-700',
                                    'bank' => 'bg-purple-100 text-purple-700',
                                ];
                                $typeNames = ['naqd' => 'Naqd', 'plastik' => 'Karta', 'bank' => 'Bank'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium {{ $typeColors[$payment->tolov_turi] ?? 'bg-gray-100 text-gray-700' }} rounded-full">
                                {{ $typeNames[$payment->tolov_turi] ?? $payment->tolov_turi ?? 'Naqd' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="px-5 py-12 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full mx-auto mb-3 flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <p class="text-gray-500 text-sm">Hali to'lov yo'q</p>
            <p class="text-gray-400 text-xs mt-1">"+To'lov" tugmasini bosing</p>
        </div>
        @endif

        @if($contract->avans_balans > 0)
        <div class="px-5 py-4 bg-gradient-to-r from-purple-50 to-white border-t border-purple-100">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-purple-700">Avans balans (kredit)</div>
                        <div class="text-xs text-purple-500">Keyingi to'lovlar uchun ishlatiladi</div>
                    </div>
                </div>
                <span class="text-2xl font-bold text-purple-700">{{ number_format($contract->avans_balans, 0, '', ' ') }}</span>
            </div>
        </div>
        @endif
    </div>

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
    <div class="bg-white rounded-lg border border-gray-200 p-6 mt-6">
        <h2 class="font-semibold text-gray-900 mb-4">Kommunikatsiyalar</h2>
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
            <div class="flex flex-col items-center gap-2 p-3 rounded-lg {{ $lot->{$util['key']} ? 'bg-green-50' : 'bg-gray-50' }}">
                <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $lot->{$util['key']} ? 'bg-green-100' : 'bg-gray-200' }}">
                    <svg class="w-5 h-5 {{ $lot->{$util['key']} ? 'text-green-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $util['icon'] }}"/></svg>
                </div>
                <span class="text-xs text-center {{ $lot->{$util['key']} ? 'text-green-700 font-medium' : 'text-gray-400' }}">{{ $util['name'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    @if($lot->tavsif)
    <div class="bg-white rounded-lg border border-gray-200 p-6 mt-6">
        <h2 class="font-semibold text-gray-900 mb-3">Muhim ma'lumotlar:</h2>
        <p class="text-gray-600 whitespace-pre-wrap">{{ $lot->tavsif }}</p>
    </div>
    @endif

    <!-- Contract History -->
    @if($lot->contracts->count() > 1)
    <div class="bg-white rounded-lg border border-gray-200 mt-6 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50"><h2 class="font-semibold text-gray-900">Shartnomalar tarixi</h2></div>
        <div class="divide-y">
            @foreach($lot->contracts->where('holat', '!=', 'faol') as $oldContract)
            <div class="flex items-center justify-between p-4">
                <div>
                    <p class="font-medium">{{ $oldContract->shartnoma_raqami }}</p>
                    <p class="text-sm text-gray-400">{{ $oldContract->tenant->name ?? '-' }}</p>
                </div>
                <div class="text-right">
                    <p class="font-medium">{{ number_format($oldContract->shartnoma_summasi, 0, '.', ' ') }}</p>
                    <span class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-600">{{ $oldContract->holat_nomi }}</span>
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
</script>
@endsection
