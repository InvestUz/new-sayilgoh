@extends('layouts.dark')
@section('title', 'Shartnomalar')
@section('header', "Shartnomalar ro'yxati")
@section('subheader', 'Barcha shartnomalarni boshqarish')

@php
$totalContracts = $contracts->total();
$faol = \App\Models\Contract::where('holat', 'faol')->count();
$jamiSumma = \App\Models\Contract::sum('shartnoma_summasi');
@endphp

@section('header-actions')
<a href="{{ route('contracts.create') }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Yangi shartnoma
</a>
@endsection

@section('content')
<div class="space-y-5">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <div>
                    <p class="stat-label">Jami Shartnomalar</p>
                    <p class="stat-value">{{ $totalContracts }}</p>
                </div>
            </div>
        </div>
        <div class="stat-card border-l-2 border-l-[#22c55e]">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#22c55e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="stat-label">Faol</p>
                    <p class="stat-value text-[#22c55e]">{{ $faol }}</p>
                </div>
            </div>
        </div>
        <div class="stat-card border-l-2 border-l-[#38bdf8]">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#38bdf8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="stat-label">Jami Summa</p>
                    <p class="stat-value text-[#38bdf8]">{{ number_format($jamiSumma / 1000000000, 2) }} <span class="text-sm text-[#64748b]">mlrd</span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <div>
                <h3 class="card-title">Shartnomalar ro'yxati</h3>
                <p class="text-xs text-[#64748b] mt-1">{{ $contracts->total() }} ta shartnoma</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Shartnoma</th>
                        <th>Ijarachi</th>
                        <th>Lot</th>
                        <th class="text-right">Summa</th>
                        <th class="text-right">Qarz</th>
                        <th class="text-center">Holat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $contract)
                    <tr>
                        <td>
                            <a href="{{ route('contracts.show', $contract) }}" class="text-cyan font-medium hover:underline">{{ $contract->shartnoma_raqami }}</a>
                            <p class="text-xs text-[#64748b]">{{ $contract->shartnoma_sanasi->format('d.m.Y') }}</p>
                        </td>
                        <td>
                            @if($contract->tenant)
                            <a href="{{ route('registry.tenants.show', $contract->tenant) }}" class="hover:text-[#38bdf8]">{{ $contract->tenant->name }}</a>
                            @else
                            <span class="text-[#64748b]">—</span>
                            @endif
                        </td>
                        <td>
                            @if($contract->lot)
                            <a href="{{ route('registry.lots.show', $contract->lot) }}" class="text-cyan hover:underline">{{ $contract->lot->lot_raqami }}</a>
                            @else
                            <span class="text-[#64748b]">—</span>
                            @endif
                        </td>
                        <td class="text-right font-medium">{{ number_format($contract->shartnoma_summasi / 1000000, 1) }} mln</td>
                        <td class="text-right">
                            @if($contract->jami_qarzdorlik > 0)
                            <span class="font-bold text-red">{{ number_format($contract->jami_qarzdorlik / 1000000, 1) }}</span>
                            @else
                            <span class="text-green">0</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($contract->holat == 'faol')
                            <span class="badge badge-success">Faol</span>
                            @else
                            <span class="badge">Yakunlangan</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('contracts.show', $contract) }}" class="text-[#38bdf8] hover:underline text-xs mr-2">Ko'rish</a>
                            <a href="{{ route('payments.create', ['contract_id' => $contract->id]) }}" class="text-[#22c55e] hover:underline text-xs">To'lov</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-10 text-[#64748b]">Shartnomalar yo'q</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($contracts->hasPages())
        <div class="p-4 border-t border-[rgba(56,189,248,0.08)]">
            {{ $contracts->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
