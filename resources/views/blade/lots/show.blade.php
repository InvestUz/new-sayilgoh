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
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">TO'LANGAN (FAKT)</p>
            <p class="text-4xl font-bold text-green-400 mt-3">{!! formatLotSum($stats['tolangan']) !!}</p>
            <p class="text-xs text-slate-500 mt-4">Kassaga tushgan sof summa<br><span class="text-slate-600">(penyadan yechilmagan)</span></p>
        </div>

        <!-- Qoldiq -->
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 border-l-red-500 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-red-500/10 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">QOLDIQ</p>
            <p class="text-4xl font-bold {{ $stats['qoldiq'] > 0 ? 'text-red-400' : 'text-slate-200' }} mt-3">{!! formatLotSum($stats['qoldiq']) !!}</p>
            <p class="text-xs text-slate-500 mt-4">{{ $stats['qoldiq_izoh'] }}</p>
        </div>

        <!-- Penya -->
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 border-l-red-500 p-5 relative overflow-hidden">
            <div class="absolute top-4 right-4 w-10 h-10 bg-red-500/10 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">PENYA (Qol. jadval)</p>
            <p class="text-4xl font-bold {{ $stats['penya'] > 0 ? 'text-red-400' : 'text-slate-200' }} mt-3">{!! formatLotSum($stats['penya']) !!}</p>
            <p class="text-xs text-slate-500 mt-4">{{ $stats['penya_izoh'] }}</p>
        </div>
    </div>

    <!-- Progress -->
    <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-5 mb-6">
        <div class="flex justify-between text-sm mb-3">
            <span class="font-medium text-slate-300">To'lov jarayoni</span>
            <span class="font-bold text-white">{{ $stats['tolangan_foiz'] }}%</span>
        </div>
        <div class="h-3 bg-slate-700 rounded-full">
            <div class="h-3 bg-gradient-to-r from-green-600 to-green-500 rounded-full transition-all" style="width: {{ $stats['tolangan_foiz'] }}%"></div>
        </div>
    </div>

    {{-- ─── JORIY OY (Current calendar month summary) ─── --}}
    @isset($currentMonth)
    @php
        $cm = $currentMonth;
        $cmDebtClass = $cm['debt'] > 0 ? 'text-red-400' : 'text-slate-200';
        $cmBorderClass = $cm['debt'] > 0 ? 'border-l-red-500' : 'border-l-slate-600';
        $cmStatusLabel = !$cm['has_schedule']
            ? 'Grafik yo\'q'
            : ($cm['debt'] <= 0
                ? 'To\'liq to\'langan'
                : ($cm['is_overdue'] ? 'Muddati o\'tgan' : 'Kutilmoqda'));
    @endphp
    <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 {{ $cmBorderClass }} p-5 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400 font-medium">Joriy oy</p>
                <h3 class="text-xl font-bold text-white mt-1">{{ $cm['label'] }}</h3>
                <p class="text-sm text-slate-400 mt-1">{{ $cm['sahifa_xulosa'] }}</p>
            </div>
            <span class="self-start md:self-center inline-flex items-center px-3 py-1 text-xs font-medium rounded
                {{ $cm['debt'] <= 0 ? 'bg-slate-600/30 text-slate-200'
                   : ($cm['is_overdue'] ? 'bg-red-500/15 text-red-300' : 'bg-slate-600/30 text-slate-200') }}">
                {{ $cmStatusLabel }}
            </span>
        </div>

        @if($cm['has_schedule'])
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5">
            <div>
                <p class="text-[11px] uppercase text-slate-500">Reja</p>
                <p class="text-lg font-semibold text-white mt-1">{!! formatLotSum($cm['plan']) !!}</p>
            </div>
            <div>
                <p class="text-[11px] uppercase text-slate-500">Fakt tushim (shu oy, kassa)</p>
                <p class="text-lg font-semibold text-green-500 mt-1">{!! formatLotSum($cm['fakt_tushgan'] ?? 0) !!}</p>
                <p class="text-[10px] text-slate-500 mt-1" title="Shu oy grafigi qatoriga FIFO bo'yicha yozilgan asosiy to'lov">Grafik qator (FIFO): {!! formatLotSum($cm['paid']) !!}</p>
            </div>
            <div>
                <p class="text-[11px] uppercase text-slate-500">Qarz (shu oy)</p>
                <p class="text-lg font-semibold {{ $cmDebtClass }} mt-1">{!! formatLotSum($cm['debt']) !!}</p>
            </div>
            <div>
                <p class="text-[11px] uppercase text-slate-500">Penya (qoldiq)</p>
                <p class="text-lg font-semibold {{ $cm['penalty'] > 0 ? 'text-red-400' : 'text-slate-200' }} mt-1">
                    {!! formatLotSum($cm['penalty']) !!}
                </p>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4 text-xs text-slate-400">
            <div>
                <span class="text-slate-500">To'lov sanasi:</span>
                <span class="text-slate-200">{{ $cm['tolov_sanasi'] ? \Carbon\Carbon::parse($cm['tolov_sanasi'])->format('d.m.Y') : '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500">Oxirgi muddat:</span>
                <span class="text-slate-200">{{ $cm['effective_deadline'] ? \Carbon\Carbon::parse($cm['effective_deadline'])->format('d.m.Y') : '—' }}</span>
            </div>
            <div>
                @if($cm['debt'] <= 0)
                    <span class="text-slate-200">Yopilgan</span>
                @elseif($cm['is_overdue'])
                    <span class="text-red-400">{{ $cm['overdue_days'] }} kun kechikish</span>
                @else
                    <span class="text-slate-200">{{ $cm['days_left'] }} kun qoldi</span>
                @endif
            </div>
        </div>
        @endif
    </div>
    @endisset
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
                    <a href="{{ route('registry.contracts.penalty-calculator', $contract) }}" class="flex items-center justify-center gap-2 w-full py-2 bg-red-700 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition">
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
    {{-- Jadval/davrlar: WebController + ContractYearPeriodsService --}}

    <!-- Professional Government Dashboard Table -->
    <div class="bg-slate-800/50 backdrop-blur border border-slate-700/50 rounded-xl mb-6 overflow-hidden">
        <!-- Header -->
        <div class="px-4 py-3 bg-slate-800/80 border-b border-slate-700/50 flex justify-between items-center">
            <h3 class="font-bold text-white text-sm uppercase tracking-wide">To'lov jadvali</h3>
            <div class="flex items-center gap-2">
                <button @click="showAddScheduleModal = true" class="px-3 py-1.5 border border-slate-600 text-slate-300 text-xs hover:bg-slate-700 rounded">+ Grafik</button>
                <button @click="showPaymentModal = true" class="px-3 py-1.5 bg-blue-600 text-white text-xs hover:bg-blue-700 rounded">+ To'lov</button>
            </div>
        </div>

        @if(count($contractYearPeriods) > 0)

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
                        <th colspan="2" class="border border-slate-600 px-2 py-1 text-center text-green-500">Fakt</th>
                        <th colspan="2" class="border border-slate-600 px-2 py-1 text-center text-red-400">Qoldiq</th>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">%</th>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center text-red-400">Penya</th>
                        <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">Amal</th>
                    </tr>
                    <tr class="text-[10px] text-slate-400">
                        <th class="border border-slate-600 px-2 py-1 text-right">summa</th>
                        <th class="border border-slate-600 px-2 py-1 text-right">oylik</th>
                        <th class="border border-slate-600 px-2 py-1 text-right text-green-500">tushgan</th>
                        <th class="border border-slate-600 px-2 py-1 text-right text-green-500">oylik</th>
                        <th class="border border-slate-600 px-2 py-1 text-right text-red-400">jami</th>
                        <th class="border border-slate-600 px-2 py-1 text-right text-red-400">o'tgan</th>
                    </tr>
                </thead>
                <tbody class="text-slate-200">
                    @php
                        $periodScheduleIds = $currentPeriod['schedules']->pluck('id')->toArray();
                        $canDeletePeriod = $currentPeriod['paid'] <= 0;
                    @endphp
                    <tr class="hover:bg-slate-700/30">
                        <td class="border border-slate-600 px-2 py-1 text-center text-slate-200 font-bold">{{ $currentPeriod['num'] }}</td>
                        <td class="border border-slate-600 px-2 py-1">
                            <span class="px-1 bg-slate-600 text-slate-100 text-[9px] rounded mr-1">JORIY</span>
                            <span class="text-white">{{ $currentPeriod['start']->format('d.m.Y') }}</span>
                            <span class="text-slate-500">—</span>
                            <span class="text-white">{{ $currentPeriod['end']->format('d.m.Y') }}</span>
                        </td>
                        <td class="border border-slate-600 px-2 py-1 text-center">{{ $currentPeriod['months'] }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right text-white">{{ number_format($currentPeriod['total'], 0, ',', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $currentPeriod['months'] > 0 ? number_format($currentPeriod['total'] / $currentPeriod['months'], 0, ',', ' ') : 0 }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right {{ $currentPeriod['paid'] > 0 ? 'text-green-500' : 'text-slate-500' }}">{{ number_format($currentPeriod['paid'], 0, ',', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $currentPeriod['schedules']->where('tolangan_summa', '>', 0)->count() > 0 ? number_format($currentPeriod['paid'] / $currentPeriod['schedules']->where('tolangan_summa', '>', 0)->count(), 0, ',', ' ') : '—' }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right {{ $currentPeriod['debt'] > 0 ? 'text-red-400' : 'text-slate-200' }}">{{ number_format($currentPeriod['debt'], 0, ',', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-right {{ $currentPeriod['overdue'] > 0 ? 'text-red-400' : 'text-slate-500' }}">{{ $currentPeriod['overdue'] > 0 ? number_format($currentPeriod['overdue'], 0, ',', ' ') : '—' }}</td>
                        <td class="border border-slate-600 px-2 py-1 text-center {{ $currentPeriod['percent'] >= 100 ? 'text-green-500' : 'text-slate-200' }}">{{ $currentPeriod['percent'] }}%</td>
                        <td class="border border-slate-600 px-2 py-1 text-right {{ $currentPeriod['penya'] > 0 ? 'text-red-400' : 'text-slate-500' }}">{{ $currentPeriod['penya'] > 0 ? number_format($currentPeriod['penya'], 0, ',', ' ') : '—' }}</td>
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
                                <th colspan="2" class="border border-slate-600 px-2 py-1 text-center text-green-500">Fakt</th>
                                <th colspan="2" class="border border-slate-600 px-2 py-1 text-center text-red-400">Qoldiq</th>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">%</th>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center text-red-400">Penya</th>
                                <th rowspan="2" class="border border-slate-600 px-2 py-1 text-center">Amal</th>
                            </tr>
                            <tr class="text-[10px] text-slate-400">
                                <th class="border border-slate-600 px-2 py-1 text-right">summa</th>
                                <th class="border border-slate-600 px-2 py-1 text-right">oylik</th>
                                <th class="border border-slate-600 px-2 py-1 text-right text-green-500">tushgan</th>
                                <th class="border border-slate-600 px-2 py-1 text-right text-green-500">oylik</th>
                                <th class="border border-slate-600 px-2 py-1 text-right text-red-400">jami</th>
                                <th class="border border-slate-600 px-2 py-1 text-right text-red-400">o'tgan</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-200">
                            <!-- JAMI Row -->
                            <tr class="bg-slate-700/30 font-bold">
                                <td class="border border-slate-600 px-2 py-1 text-center"></td>
                                <td class="border border-slate-600 px-2 py-1 text-white">JAMI:</td>
                                <td class="border border-slate-600 px-2 py-1 text-center text-slate-200">{{ $allSchedules->count() }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-white">{{ number_format($grandTotal, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $allSchedules->count() > 0 ? number_format($grandTotal / $allSchedules->count(), 0, ',', ' ') : 0 }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-green-500">{{ number_format($grandPaid, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-green-500">{{ $allSchedules->where('tolangan_summa', '>', 0)->count() > 0 ? number_format($grandPaid / $allSchedules->where('tolangan_summa', '>', 0)->count(), 0, ',', ' ') : 0 }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-red-400">{{ number_format($grandDebt, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-red-400">{{ number_format($grandOverdue, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-center {{ $grandPercent >= 100 ? 'text-green-500' : 'text-slate-200' }}">{{ $grandPercent }}%</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-red-400">{{ number_format($grandPenya, 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1"></td>
                            </tr>
                            @foreach($contractYearPeriods as $period)
                            @php
                                $isCurrentPeriod = $period['num'] === $currentPeriodNum;
                                $periodScheduleIds = $period['schedules']->pluck('id')->toArray();
                                $canDeletePeriod = $period['paid'] <= 0;
                            @endphp
                            <tr class="hover:bg-slate-700/30">
                                <td class="border border-slate-600 px-2 py-1 text-center {{ $isCurrentPeriod ? 'text-slate-200 font-bold' : '' }}">{{ $period['num'] }}</td>
                                <td class="border border-slate-600 px-2 py-1">
                                    @if($isCurrentPeriod)<span class="px-1 bg-slate-600 text-slate-100 text-[9px] rounded mr-1">JORIY</span>@endif
                                    <span class="text-white">{{ $period['start']->format('d.m.Y') }}</span>
                                    <span class="text-slate-500">—</span>
                                    <span class="text-white">{{ $period['end']->format('d.m.Y') }}</span>
                                </td>
                                <td class="border border-slate-600 px-2 py-1 text-center">{{ $period['months'] }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-white">{{ number_format($period['total'], 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $period['months'] > 0 ? number_format($period['total'] / $period['months'], 0, ',', ' ') : 0 }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $period['paid'] > 0 ? 'text-green-500' : 'text-slate-500' }}">{{ number_format($period['paid'], 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right text-slate-400">{{ $period['schedules']->where('tolangan_summa', '>', 0)->count() > 0 ? number_format($period['paid'] / $period['schedules']->where('tolangan_summa', '>', 0)->count(), 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $period['debt'] > 0 ? 'text-red-400' : 'text-slate-200' }}">{{ number_format($period['debt'], 0, ',', ' ') }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $period['overdue'] > 0 ? 'text-red-400' : 'text-slate-500' }}">{{ $period['overdue'] > 0 ? number_format($period['overdue'], 0, ',', ' ') : '—' }}</td>
                                <td class="border border-slate-600 px-2 py-1 text-center {{ $period['percent'] >= 100 ? 'text-green-500' : 'text-slate-200' }}">{{ $period['percent'] }}%</td>
                                <td class="border border-slate-600 px-2 py-1 text-right {{ $period['penya'] > 0 ? 'text-red-400' : 'text-slate-500' }}">{{ $period['penya'] > 0 ? number_format($period['penya'], 0, ',', ' ') : '—' }}</td>
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

        @endif
        {{-- Oylik: joriy 12-oylik davr (ContractPeriodService + ScheduleDisplay filtri); kartochkalar = allSchedulesData totals --}}

        <!-- Monthly Details (Expandable) -->
        <div x-data="{ showDetails: true, showBarchaOylar: false }" class="border-t border-slate-600">
            <button @click="showDetails = !showDetails" class="w-full px-4 py-2 text-left text-xs text-slate-400 hover:bg-slate-700/30 flex items-center justify-between">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="font-medium">Oylik tafsilotlar</span>
                    @if($scheduleDisplayUsesPeriod && $lotCurrentPeriod)
                    <span class="text-[10px] text-slate-500">joriy 12-oylik davr</span>
                    @endif
                    @if(!empty($scheduleDisplayData['schedules']))
                    <span class="text-[10px] px-2 py-0.5 bg-slate-700/50 text-slate-300 rounded">{{ count($scheduleDisplayData['schedules']) }} oy</span>
                    @endif
                </div>
                <svg :class="showDetails ? 'rotate-180' : ''" class="w-4 h-4 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="showDetails" x-collapse>
                <p class="px-2 py-2 text-[10px] text-slate-400 leading-relaxed border-b border-slate-600/50">
                    <span class="text-slate-300">Grafik</span> — oylik reja (shartnoma <span class="text-slate-300">yillik ijarasi</span> saqlangan bo‘lsa, barcha to‘liq oylar <span class="text-slate-300">bitta oylik</span> = yillik ÷ 12; 1-oy pro-ratada o‘z summasi; yillik bo‘lmasa jadvaldagi oylik).
                    <span class="text-slate-300">Fakt</span> = shu kalendar oyda kassaga tushim.
                    <span class="text-slate-300">Qoldiq</span> = <span class="text-slate-200">Grafik − Fakt (shu oy)</span> (0 dan past emas). Kassa tizimidagi FIFO/ avans alohida.
                </p>
                @if($scheduleDisplayUsesPeriod && $lotCurrentPeriod)
                <p class="px-2 py-1.5 text-[10px] text-slate-500 border-b border-slate-600/40">
                    Davr {{ $lotCurrentPeriod['num'] }}:
                    {{ $lotCurrentPeriod['start']->format('d.m.Y') }} — {{ $lotCurrentPeriod['end']->format('d.m.Y') }}.
                    Yuqoridagi <span class="text-slate-300">QOLDIQ / PENYA</span> kartochkalari butun shartnoma (barcha oylar) bo‘yicha.
                </p>
                @endif
                @include('blade.lots.partials.schedule-oylik-jadval', ['oylikSchedules' => $scheduleDisplayData['schedules'] ?? []])

                @if($hasSeparateAllMonthsView)
                <div class="mt-2 border-t border-slate-600/80 pt-1">
                    <button type="button" @click="showBarchaOylar = !showBarchaOylar" class="w-full px-2 py-2 text-left text-[11px] text-slate-400 hover:bg-slate-700/20 flex items-center justify-between">
                        <span>Barcha oylar (yig‘indi kartochkalar bilan mos) — <span class="text-slate-500">{{ count($allSchedulesData['schedules'] ?? []) }} oy</span></span>
                        <svg :class="showBarchaOylar ? 'rotate-180' : ''" class="w-3.5 h-3.5 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="showBarchaOylar" x-collapse>
                        @include('blade.lots.partials.schedule-oylik-jadval', ['oylikSchedules' => $allSchedulesData['schedules'] ?? []])
                    </div>
                </div>
                @endif
            </div>
        </div>
        @if(isset($allSchedulesData['schedules']) && count($allSchedulesData['schedules']) === 0)
        <div class="px-4 py-8 text-center text-slate-500 text-sm border-t border-slate-600">To'lov grafigi (grafik oylar) hali yaratilmagan yoki yuklanmadi.</div>
        @endif

        <!-- Deadline Change Log -->
        @php
            $schedulesWithChanges = collect($contractYearPeriods)->flatMap(fn($p) => $p['schedules'])->filter(fn($s) => !empty($s->muddat_ozgarish_izoh));
            $priceIncreaseChanges = $schedulesWithChanges->filter(fn($s) => strpos($s->muddat_ozgarish_izoh, '+14%') !== false);
            $otherChanges = $schedulesWithChanges->filter(fn($s) => strpos($s->muddat_ozgarish_izoh, '+14%') === false);
        @endphp
        @if($priceIncreaseChanges->count() > 0 || $otherChanges->count() > 0)
        @php
            $priceIncreaseList = $priceIncreaseChanges->values();
            $otherChangesList  = $otherChanges->values();
            $previewLimit      = 3;
        @endphp
        <div class="mt-4 border-t border-slate-600 pt-3 space-y-3">
            @if($priceIncreaseList->count() > 0)
            <div x-data="{ open: false }" class="bg-amber-900/20 border border-amber-500/30 rounded-lg overflow-hidden">
                <button type="button" @click="open = !open"
                    class="w-full px-4 py-2.5 flex items-center justify-between text-left hover:bg-amber-900/30 transition">
                    <h4 class="text-xs font-bold text-amber-300 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Narx oshishi (01.01.2026 dan)
                        <span class="text-[10px] px-2 py-0.5 bg-amber-500/20 text-amber-200 rounded-full font-semibold">{{ $priceIncreaseList->count() }} ta oy</span>
                    </h4>
                    <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-amber-300 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Collapsed preview: first {{ $previewLimit }} months on a single compact line --}}
                <div x-show="!open" class="px-4 pb-3">
                    <div class="flex flex-wrap gap-1.5 text-[10px] text-amber-100">
                        @foreach($priceIncreaseList->take($previewLimit) as $schedule)
                            <span class="inline-flex items-center gap-1 bg-slate-800/50 rounded px-2 py-0.5 border-l-2 border-amber-400">
                                <span class="font-medium text-amber-300">{{ $schedule->oy_nomi }} {{ $schedule->yil }}</span>
                            </span>
                        @endforeach
                        @if($priceIncreaseList->count() > $previewLimit)
                            <span class="text-amber-200/70">+{{ $priceIncreaseList->count() - $previewLimit }} ta ko'proq…</span>
                        @endif
                    </div>
                </div>

                {{-- Expanded full list --}}
                <div x-show="open" x-collapse>
                    <div class="px-4 pb-3 space-y-1.5 text-[10px] text-amber-100 max-h-80 overflow-y-auto">
                        @foreach($priceIncreaseList as $schedule)
                            <div class="bg-slate-800/50 rounded px-2 py-1.5 border-l-2 border-amber-400">
                                <span class="font-medium text-amber-300">{{ $schedule->oy_nomi }} {{ $schedule->yil }}:</span>
                                <div class="ml-2 mt-0.5 text-slate-300 whitespace-pre-line font-mono text-[9px]">{{ $schedule->muddat_ozgarish_izoh }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            @if($otherChangesList->count() > 0)
            <div x-data="{ open: false }" class="bg-blue-900/20 border border-blue-500/30 rounded-lg overflow-hidden">
                <button type="button" @click="open = !open"
                    class="w-full px-4 py-2.5 flex items-center justify-between text-left hover:bg-blue-900/30 transition">
                    <h4 class="text-xs font-bold text-blue-300 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Muddat o'zgarishlari tarixi
                        <span class="text-[10px] px-2 py-0.5 bg-blue-500/20 text-blue-200 rounded-full font-semibold">{{ $otherChangesList->count() }} ta yozuv</span>
                    </h4>
                    <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-blue-300 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Collapsed preview --}}
                <div x-show="!open" class="px-4 pb-3">
                    <div class="flex flex-wrap gap-1.5 text-[10px] text-blue-100">
                        @foreach($otherChangesList->take($previewLimit) as $schedule)
                            <span class="inline-flex items-center gap-1 bg-slate-800/50 rounded px-2 py-0.5 border-l-2 border-blue-400">
                                <span class="font-medium text-blue-300">{{ $schedule->oy_nomi }} {{ $schedule->yil }}</span>
                            </span>
                        @endforeach
                        @if($otherChangesList->count() > $previewLimit)
                            <span class="text-blue-200/70">+{{ $otherChangesList->count() - $previewLimit }} ta ko'proq…</span>
                        @endif
                    </div>
                </div>

                {{-- Expanded full list --}}
                <div x-show="open" x-collapse>
                    <div class="px-4 pb-3 space-y-1.5 text-[10px] text-blue-200 max-h-80 overflow-y-auto">
                        @foreach($otherChangesList as $schedule)
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
            <span class="text-xs text-slate-300">{{ $approvedPayments->count() }} ta</span>
        </div>

        @if($approvedPayments->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-700/50 text-slate-300">
                    <tr>
                        <th class="border border-slate-600 px-2 py-1.5 text-left">#</th>
                        <th class="border border-slate-600 px-2 py-1.5 text-left">Sana</th>
                        <th class="border border-slate-600 px-2 py-1.5 text-right">Summa (fakt)</th>
                        <th class="border border-slate-600 px-2 py-1.5 text-right">Asosiyga</th>
                        <th class="border border-slate-600 px-2 py-1.5 text-right">Avansga</th>
                        <th class="border border-slate-600 px-2 py-1.5 text-center">Turi</th>
                        <th class="border border-slate-600 px-2 py-1.5 text-left">Hujjat</th>
                        <th class="border border-slate-600 px-2 py-1.5 text-center">Amal</th>
                    </tr>
                </thead>
                <tbody class="text-slate-200">
                    @foreach($approvedPayments->sortByDesc('tolov_sanasi')->take(50) as $payment)
                    @php $paymentDate = \Carbon\Carbon::parse($payment->tolov_sanasi); @endphp
                    <tr class="hover:bg-slate-700/30">
                        <td class="border border-slate-600 px-2 py-1.5 text-slate-400 font-mono">{{ $payment->tolov_raqami }}</td>
                        <td class="border border-slate-600 px-2 py-1.5 text-white font-medium whitespace-nowrap">{{ $paymentDate->format('d.m.Y') }}</td>
                        <td class="border border-slate-600 px-2 py-1.5 text-right text-green-500 font-bold">+{{ number_format($payment->summa, 0, '', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1.5 text-right text-slate-300">{{ number_format((float) $payment->asosiy_qarz_uchun, 0, '', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1.5 text-right text-slate-300">{{ number_format((float) $payment->avans, 0, '', ' ') }}</td>
                        <td class="border border-slate-600 px-2 py-1.5 text-center text-slate-400">{{ ['naqd' => 'Naqd', 'plastik' => 'Karta', 'bank' => 'Bank', 'bank_otkazmasi' => 'Bank', 'karta' => 'Karta', 'onlayn' => 'Onlayn'][$payment->tolov_usuli] ?? 'Bank' }}</td>
                        <td class="border border-slate-600 px-2 py-1.5 text-slate-400 text-[11px]" title="{{ $payment->izoh }}">{{ \Illuminate\Support\Str::limit($payment->hujjat_raqami ?? '-', 20) }}</td>
                        <td class="border border-slate-600 px-2 py-1.5 text-center">
                            <button type="button"
                                @click="cancelPayment({{ $payment->id }}, '{{ $payment->tolov_raqami }}', {{ $payment->summa }})"
                                class="px-2 py-0.5 text-[10px] rounded bg-red-500/20 text-red-300 hover:bg-red-500/40 border border-red-500/50">
                                Bekor qilish
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Jami to'langan -->
        <div class="px-4 py-2 border-t border-slate-700/50 flex items-center justify-between bg-slate-800/60">
            <span class="text-sm text-slate-300 font-medium">Jami to'langan:</span>
            <span class="text-lg font-bold text-green-500">{{ number_format($approvedPayments->sum('summa'), 0, '', ' ') }} UZS</span>
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

    <!-- Payment Modal (2-step: Form → Confirmation) -->
    <div x-show="showPaymentModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showPaymentModal=false; paymentStep='form'; duplicateWarning=null;">
        <div class="bg-white rounded-lg w-full max-w-md shadow-2xl">
            <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-900">
                    <span x-show="paymentStep==='form'">To'lov qilish</span>
                    <span x-show="paymentStep==='confirm'">Tasdiqlang</span>
                </h3>
                <button @click="showPaymentModal=false; paymentStep='form'; duplicateWarning=null;" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <!-- Current Debt Summary -->
            <div class="px-4 py-3 bg-red-50 border-b border-red-100">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-red-700">Joriy qarz (asosiy):</span>
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
                    <span class="text-xs text-red-600">Penya (hisoblangan, informatsion):</span>
                    <span class="text-sm font-medium text-red-600">{{ number_format($grandPenya, 0, ',', ' ') }}</span>
                </div>
                @endif
                <div class="mt-2 pt-2 border-t border-red-200 text-[11px] text-gray-500 leading-tight">
                    Diqqat: kiritilgan fakt to'lov TO'LIQ asosiy qarzga yo'naltiriladi.
                    Penya undan yechilmaydi — u alohida hisoblanadi va alohida to'lanadi.
                </div>
            </div>

            <!-- STEP 1: FORM -->
            <form x-show="paymentStep==='form'" @submit.prevent="prepareConfirmation" class="p-4 space-y-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-2">Tezkor summa:</label>
                    <div class="grid grid-cols-3 gap-2">
                        @if($grandOverdue > 0)
                        <button type="button" @click="paymentForm.summa = {{ $grandOverdue }}" class="px-2 py-2 text-xs border border-red-300 text-red-700 rounded hover:bg-red-50">Muddati o'tgan<br><b>{{ number_format($grandOverdue, 0, ',', ' ') }}</b></button>
                        @endif
                        <button type="button" @click="paymentForm.summa = {{ $grandDebt }}" class="px-2 py-2 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50">To'liq qarz<br><b>{{ number_format($grandDebt, 0, ',', ' ') }}</b></button>
                        <button type="button" @click="paymentForm.summa = {{ $grandOylikOrtacha }}" class="px-2 py-2 text-xs border border-gray-300 text-gray-700 rounded hover:bg-gray-50">1 oylik<br><b>{{ number_format($grandOylikOrtacha, 0, ',', ' ') }}</b></button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Summa (so'm)</label>
                    <input type="number" step="any" min="1" x-model="paymentForm.summa" class="w-full border border-gray-300 rounded px-3 py-2 text-lg font-bold" required placeholder="0">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Sana</label>
                        <input type="date" x-model="paymentForm.tolov_sanasi" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Turi</label>
                        <select x-model="paymentForm.tolov_usuli" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                            <option value="bank_otkazmasi">Bank o'tkazmasi</option>
                            <option value="naqd">Naqd</option>
                            <option value="karta">Plastik karta</option>
                            <option value="onlayn">Onlayn</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Hujjat raqami <span class="text-gray-400">(ixtiyoriy, dublicate himoyasi uchun)</span></label>
                    <input type="text" x-model="paymentForm.hujjat_raqami" maxlength="100" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="masalan: T-03-7501986">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Izoh <span class="text-gray-400">(ixtiyoriy)</span></label>
                    <textarea x-model="paymentForm.izoh" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                </div>
                <button type="submit" class="w-full py-3 bg-blue-600 text-white rounded font-bold text-sm hover:bg-blue-700">
                    Davom etish →
                </button>
            </form>

            <!-- STEP 2: CONFIRMATION -->
            <div x-show="paymentStep==='confirm'" class="p-4 space-y-3">
                <div class="bg-blue-50 border border-blue-200 rounded p-3 space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">Summa:</span><b class="text-blue-800" x-text="formatMoney(paymentForm.summa) + ' so\'m'"></b></div>
                    <div class="flex justify-between"><span class="text-gray-600">Sana:</span><b x-text="paymentForm.tolov_sanasi"></b></div>
                    <div class="flex justify-between"><span class="text-gray-600">Turi:</span><b x-text="paymentUsuliLabel(paymentForm.tolov_usuli)"></b></div>
                    <div class="flex justify-between" x-show="paymentForm.hujjat_raqami"><span class="text-gray-600">Hujjat raqami:</span><b x-text="paymentForm.hujjat_raqami"></b></div>
                </div>

                <!-- Duplicate warning (shown only after 409 response) -->
                <div x-show="duplicateWarning" class="bg-amber-50 border-2 border-amber-400 rounded p-3 text-sm">
                    <div class="font-bold text-amber-900 mb-1">⚠ Shubhali dublikat topildi</div>
                    <div class="text-amber-800 text-[13px] leading-tight" x-text="duplicateWarning?.message"></div>
                    <div class="mt-2 text-[11px] text-amber-700">Agar bu rostdan ham yangi to'lov ekaniga ishonchingiz komil bo'lsa, "Baribir saqlash" tugmasini bosing.</div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="paymentStep='form'; duplicateWarning=null;" class="py-3 border border-gray-300 text-gray-700 rounded font-medium text-sm hover:bg-gray-50">← Orqaga</button>
                    <button type="button" @click="submitPayment(duplicateWarning !== null)" :disabled="loading" class="py-3 text-white rounded font-bold text-sm disabled:opacity-60"
                        :class="duplicateWarning ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-600 hover:bg-green-700'">
                        <span x-show="!loading && !duplicateWarning">✓ Tasdiqlash va saqlash</span>
                        <span x-show="!loading && duplicateWarning">Baribir saqlash</span>
                        <span x-show="loading">Yuklanmoqda...</span>
                    </button>
                </div>
            </div>
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
        paymentStep: 'form',           // 'form' | 'confirm'
        duplicateWarning: null,        // null | { message, existing }
        @if($contract)
        paymentForm: {
            contract_id: {{ $contract->id }},
            summa: '',
            tolov_sanasi: new Date().toISOString().split('T')[0],
            tolov_usuli: 'bank_otkazmasi',
            hujjat_raqami: '',
            izoh: '',
        },
        @else
        paymentForm: {},
        @endif

        init() {},

        @if($contract)
        formatMoney(v) {
            const n = parseFloat(v || 0);
            if (!isFinite(n)) return '0';
            return n.toLocaleString('ru-RU').replace(/,/g, ' ');
        },

        paymentUsuliLabel(code) {
            const map = {
                bank_otkazmasi: "Bank o'tkazmasi",
                naqd: 'Naqd',
                karta: 'Plastik karta',
                onlayn: 'Onlayn',
            };
            return map[code] || code || '-';
        },

        prepareConfirmation() {
            // Client-side sanity: summa > 0
            const summa = parseFloat(this.paymentForm.summa || 0);
            if (!(summa > 0)) {
                alert("Iltimos, noldan katta summa kiriting.");
                return;
            }
            if (!this.paymentForm.tolov_sanasi) {
                alert("Iltimos, to'lov sanasini tanlang.");
                return;
            }
            this.duplicateWarning = null;
            this.paymentStep = 'confirm';
        },

        async cancelPayment(id, raqami, summa) {
            const formatted = (parseFloat(summa) || 0).toLocaleString('ru-RU').replace(/,/g, ' ');
            const msg = `№${raqami} (${formatted} so'm) to'lovini BEKOR QILASIZMI?\n\n`
                + "Bekor qilingandan so'ng to'lov \"qaytarilgan\" holatiga o'tkaziladi va\n"
                + "jadvaldagi taqsimoti teskarisiga qaytariladi. Davom etamizmi?";
            if (!confirm(msg)) return;

            try {
                const res = await fetch(`/api/payments/${id}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const err = await res.json().catch(() => ({}));
                    alert(err.message || "Bekor qilishda xatolik yuz berdi");
                }
            } catch (e) {
                alert('Tarmoq xatoligi: ' + (e?.message || e));
            }
        },

        async submitPayment(force = false) {
            this.loading = true;
            try {
                const payload = { ...this.paymentForm };
                if (force) payload.force = true;

                const res = await fetch('/api/payments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });

                if (res.status === 409) {
                    // Duplicate suspected — show warning and stay on confirm step
                    const data = await res.json().catch(() => ({}));
                    this.duplicateWarning = {
                        message: data.message || "Shubhali dublikat to'lov topildi.",
                        existing: data.existing || null,
                    };
                    this.loading = false;
                    return;
                }

                if (res.ok) {
                    this.showPaymentModal = false;
                    this.paymentStep = 'form';
                    this.duplicateWarning = null;
                    window.location.reload();
                } else {
                    const err = await res.json().catch(() => ({}));
                    alert((err && err.message) ? (typeof err.message === 'string' ? err.message : JSON.stringify(err.message)) : 'Xatolik yuz berdi');
                }
            } catch (e) {
                alert('Tarmoq xatoligi: ' + (e?.message || e));
            }
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
