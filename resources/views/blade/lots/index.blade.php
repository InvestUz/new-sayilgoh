@extends('layouts.dark')
@section('title', 'Lotlar')
@section('header', "Lotlar ro'yxati")
@section('subheader', 'Barcha lotlarni boshqarish va ko\'rish')

@php
$totalLots = $lots->total();
$ijarada = \App\Models\Lot::where('holat', 'ijarada')->count();
$bosh = \App\Models\Lot::where('holat', 'bosh')->count();
$hasFilters = (isset($filter) && $filter) || (isset($year) && $year);
@endphp

@section('header-actions')
<a href="{{ route('lots.create') }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Yangi lot
</a>
@endsection

@section('content')
<div class="space-y-5">
    <!-- Active Filters Bar -->
    @if($hasFilters)
    <div class="card">
        <div class="p-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-sm font-medium text-[#94a3b8]">Faol filterlar:</span>
                @if(isset($year) && $year)
                <span class="badge badge-info">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $year }}-yil
                </span>
                @endif
                @if(isset($filter) && $filter)
                <span class="badge {{ $filter == 'muddati_otgan' ? 'badge-danger' : ($filter == 'tolangan' ? 'badge-success' : 'badge-info') }}">
                    @switch($filter)
                        @case('muddati_otgan') Muddati o'tgan @break
                        @case('penya') Penyali @break
                        @case('tolangan') To'langan @break
                        @case('kutilmoqda') Kutilmoqda @break
                        @case('qarzdor') Qoldiqli @break
                    @endswitch
                </span>
                @endif
            </div>
            <a href="{{ route('lots.index') }}" class="btn btn-danger text-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Tozalash
            </a>
        </div>
    </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <div>
                    <p class="stat-label">Jami Lotlar</p>
                    <p class="stat-value">{{ $totalLots }}</p>
                </div>
            </div>
        </div>
        <div class="stat-card border-l-2 border-l-[#38bdf8]">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#38bdf8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="stat-label">Ijaradagi</p>
                    <p class="stat-value text-[#38bdf8]">{{ $ijarada }}</p>
                </div>
            </div>
        </div>
        <div class="stat-card border-l-2 border-l-[#22c55e]">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#22c55e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <div>
                    <p class="stat-label">Bo'sh</p>
                    <p class="stat-value text-[#22c55e]">{{ $bosh }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="card-title">Lotlar ro'yxati</h3>
            <span class="text-xs text-[#64748b]">{{ $lots->total() }} ta lot</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Lot</th>
                        <th>Obyekt</th>
                        <th>Ijarachi</th>
                        <th class="text-right">Maydon</th>
                        <th class="text-right">Shartnoma</th>
                        <th class="text-right">Qarz</th>
                        <th class="text-center">Holat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lots as $lot)
                    @php
                        $contract = $lot->contracts->first();
                        $qarz = $contract?->paymentSchedules?->sum('qoldiq_summa') ?? 0;
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('lots.show', $lot) }}" class="text-cyan font-medium hover:underline">{{ $lot->lot_raqami }}</a>
                        </td>
                        <td>
                            <div>{{ $lot->obyekt_nomi }}</div>
                            <div class="text-xs text-[#64748b]">{{ $lot->tuman ?? '-' }}</div>
                        </td>
                        <td>
                            @if($contract && $contract->tenant)
                            <a href="{{ route('tenants.show', $contract->tenant) }}" class="hover:text-[#38bdf8]">{{ $contract->tenant->name }}</a>
                            @else
                            <span class="text-[#64748b]">—</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($lot->maydon, 1) }} m²</td>
                        <td class="text-right">
                            @if($contract)
                            <span class="font-medium">{{ number_format($contract->shartnoma_summasi / 1000000, 1) }} mln</span>
                            @else
                            <span class="text-[#64748b]">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($contract && $qarz > 0)
                            <span class="text-red font-semibold">{{ number_format($qarz / 1000000, 1) }}</span>
                            @elseif($contract)
                            <span class="text-green">0</span>
                            @else
                            <span class="text-[#64748b]">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($lot->holat == 'bosh')
                            <span class="badge badge-success">Bo'sh</span>
                            @elseif($lot->holat == 'ijarada')
                            <span class="badge badge-info">Ijarada</span>
                            @else
                            <span class="badge">{{ $lot->holat_nomi }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('lots.show', $lot) }}" class="text-[#38bdf8] hover:underline text-xs mr-2">Ko'rish</a>
                            <a href="{{ route('lots.edit', $lot) }}" class="text-[#64748b] hover:text-white text-xs">Tahrir</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-10 text-[#64748b]">Lotlar yo'q</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lots->hasPages())
        <div class="p-4 border-t border-[rgba(56,189,248,0.08)]">
            {{ $lots->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
