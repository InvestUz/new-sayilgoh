@extends('layouts.dark')
@section('title', 'Monitoring')
@section('header', "To'lovlar monitoringi")
@section('subheader', "Shartnomalar bo'yicha to'lovlarni kuzatish va tahlil qilish")

@php
$total = $stats['jami_kutilgan'];
$paidPercent = $total > 0 ? round(($stats['jami_tolangan'] / $total) * 100, 1) : 0;
$debtPercent = $total > 0 ? round(($stats['jami_qarzdorlik'] / $total) * 100, 1) : 0;
@endphp

@section('header-actions')
<button onclick="window.print()" class="btn btn-secondary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
    Chop etish
</button>
@endsection

@section('content')
<div x-data="monitoring()" class="space-y-5">
    <!-- Search & Filter Bar -->
    <div class="card no-print">
        <div class="p-4 flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[280px]">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="searchQuery" @keyup.enter="applyFilters()"
                        placeholder="Qidirish (lot, ijara, shartnoma)..."
                        class="form-input pl-10">
                </div>
            </div>
            <select x-model="statusFilter" @change="applyFilters()" class="form-input form-select w-auto">
                <option value="all">Barcha</option>
                <option value="qarzdor">Qarzdorlar</option>
                <option value="tolangan">To'langan</option>
                <option value="muddati_otgan">Muddati o'tgan</option>
            </select>
            <select x-model="periodFilter" @change="applyFilters()" class="form-input form-select w-auto">
                <option value="month">Oylik</option>
                <option value="quarter">Choraklik</option>
                <option value="year">Yillik</option>
            </select>
            <select x-model="yearFilter" @change="applyFilters()" class="form-input form-select w-auto">
                <option value="">Barcha yillar</option>
                @foreach($years as $y)
                <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
            <button @click="applyFilters()" class="btn btn-primary">Qidirish</button>
            <a href="{{ route('dashboard') }}" class="btn btn-danger">Tozalash</a>
        </div>
    </div>

    <!-- Top Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <a href="{{ route('registry', ['tab' => 'lots']) }}" class="stat-card block">
            <div class="flex items-center gap-3">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <div>
                    <p class="stat-label">Jami Lotlar</p>
                    <p class="stat-value">{{ $stats['jami_lotlar'] }}</p>
                </div>
            </div>
        </a>
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="1" stroke-width="1.5"/><path d="M4 12h16M12 4v16" stroke-width="1.5"/></svg>
                <div>
                    <p class="stat-label">Umumiy Maydon</p>
                    <p class="stat-value">{{ number_format($stats['umumiy_maydon'], 0) }} <span class="text-sm text-[#64748b]">m²</span></p>
                </div>
            </div>
        </div>
        <a href="{{ route('registry') }}" class="stat-card block">
            <div class="flex items-center gap-3">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <div>
                    <p class="stat-label">Shartnomalar</p>
                    <p class="stat-value">{{ $stats['faol_shartnomalar'] }}</p>
                </div>
            </div>
        </a>
        <a href="{{ route('registry', ['tab' => 'tenants']) }}" class="stat-card block">
            <div class="flex items-center gap-3">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <div>
                    <p class="stat-label">Ijarachilar</p>
                    <p class="stat-value">{{ $stats['jami_ijarachilar'] }}</p>
                </div>
            </div>
        </a>
        <a href="{{ route('registry', ['tab' => 'payments']) }}" class="stat-card block">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#22c55e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <div>
                    <p class="stat-label">To'lovlar</p>
                    <p class="stat-value text-[#22c55e]">{{ $stats['tolovlar_soni'] ?? 0 }}</p>
                </div>
            </div>
        </a>
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#ef4444]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="stat-label">Kechikkanlar</p>
                    <p class="stat-value text-[#ef4444]">{{ $stats['muddati_otgan_soni'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card">
            <div class="card-body">
                <p class="text-xs text-[#64748b] uppercase tracking-wide">Jami Shartnoma Summasi</p>
                <p class="text-2xl font-bold text-white mt-2">{{ number_format($stats['jami_shartnoma_summasi'] / 1000000000, 2) }} <span class="text-sm font-normal text-[#64748b]">mlrd</span></p>
            </div>
        </div>
        <div class="card border-l-4 border-l-[#22c55e]">
            <div class="card-body">
                <p class="text-xs text-[#64748b] uppercase tracking-wide">Jami To'langan</p>
                <p class="text-2xl font-bold text-[#22c55e] mt-2">{{ number_format($stats['jami_tolangan'] / 1000000000, 2) }} <span class="text-sm font-normal text-[#64748b]">mlrd</span></p>
                <p class="text-xs text-[#64748b] mt-1">{{ $paidPercent }}% bajarildi</p>
            </div>
        </div>
        <div class="card border-l-4 border-l-[#ef4444]">
            <div class="card-body">
                <p class="text-xs text-[#64748b] uppercase tracking-wide">Muddati O'tgan Qarz</p>
                <p class="text-2xl font-bold text-[#ef4444] mt-2">{{ number_format($stats['jami_qarzdorlik'] / 1000000000, 2) }} <span class="text-sm font-normal text-[#64748b]">mlrd</span></p>
                <p class="text-xs text-[#64748b] mt-1">{{ $stats['muddati_otgan_soni'] ?? 0 }} ta grafik</p>
            </div>
        </div>
        <div class="card border-l-4 border-l-[#f59e0b]">
            <div class="card-body">
                <p class="text-xs text-[#64748b] uppercase tracking-wide">Jami Penya</p>
                <p class="text-2xl font-bold text-[#f59e0b] mt-2">{{ number_format($stats['jami_penya'] / 1000000, 1) }} <span class="text-sm font-normal text-[#64748b]">mln</span></p>
                <p class="text-xs text-[#64748b] mt-1">Kechikish jarimasi</p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Oylik to'lovlar (Reja vs Fakt)</h3>
            </div>
            <div class="card-body">
                <div style="height: 250px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">To'lov holati</h3>
            </div>
            <div class="card-body">
                <div class="flex items-center gap-6">
                    <div style="width: 200px; height: 200px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-[#22c55e]"></span>
                            <span class="text-sm">To'langan: {{ number_format($stats['jami_tolangan'] / 1000000, 1) }} mln</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-[#f59e0b]"></span>
                            <span class="text-sm">Kutilmoqda: {{ number_format($stats['muddati_otmagan_qarz'] / 1000000, 1) }} mln</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-[#ef4444]"></span>
                            <span class="text-sm">Kechikkan: {{ number_format($stats['jami_qarzdorlik'] / 1000000, 1) }} mln</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lots Table -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="card-title">Lotlar ro'yxati</h3>
            <span class="text-xs text-[#64748b]">Jami: {{ $lots->total() }} ta</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Lot</th>
                        <th>Ijara</th>
                        <th>Shartnoma</th>
                        <th class="text-right">Maydon</th>
                        <th class="text-right">Plan</th>
                        <th class="text-right">To'langan</th>
                        <th class="text-right">Qarz</th>
                        <th class="text-right">Penya</th>
                        <th>Holat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lots as $lot)
                    @php
                        $contract = $lot->activeContract;
                        $schedules = $contract ? $contract->paymentSchedules : collect();
                        $plan = $schedules->sum('tolov_summasi');
                        $paid = $schedules->sum('tolangan_summa');
                        $debt = $schedules->sum('qoldiq_summa');
                        $penya = max(0, $schedules->sum('penya_summasi') - $schedules->sum('tolangan_penya'));
                        // Use effective deadline (custom if set, otherwise original)
                        $overdue = $schedules->filter(function($s) {
                            $effectiveDeadline = $s->custom_oxirgi_muddat ?? $s->oxirgi_muddat;
                            return \Carbon\Carbon::parse($effectiveDeadline)->lt(now()) && $s->qoldiq_summa > 0;
                        })->count();
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('registry.lots.show', $lot) }}" class="text-cyan font-medium hover:underline">{{ $lot->lot_raqami }}</a>
                        </td>
                        <td>{{ $contract?->tenant?->korxona_nomi ?? '-' }}</td>
                        <td>{{ $contract?->shartnoma_raqami ?? '-' }}</td>
                        <td class="text-right">{{ number_format($lot->maydon, 1) }} m²</td>
                        <td class="text-right">{{ number_format($plan / 1000000, 1) }}</td>
                        <td class="text-right text-green">{{ number_format($paid / 1000000, 1) }}</td>
                        <td class="text-right {{ $debt > 0 ? 'text-red' : '' }}">{{ number_format($debt / 1000000, 1) }}</td>
                        <td class="text-right {{ $penya > 0 ? 'text-amber' : '' }}">{{ number_format($penya / 1000000, 1) }}</td>
                        <td>
                            @if($overdue > 0)
                            <span class="badge badge-danger">Kechikkan</span>
                            @elseif($debt > 0)
                            <span class="badge badge-warning">Kutilmoqda</span>
                            @elseif($plan > 0)
                            <span class="badge badge-success">To'langan</span>
                            @else
                            <span class="badge badge-info">Bo'sh</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center py-8 text-[#64748b]">Ma'lumot topilmadi</td></tr>
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

@section('scripts')
<script>
function monitoring() {
    return {
        searchQuery: '{{ request('search', '') }}',
        statusFilter: '{{ request('status', 'all') }}',
        periodFilter: '{{ request('period', 'month') }}',
        yearFilter: '{{ request('year', '') }}',
        applyFilters() {
            const params = new URLSearchParams();
            if (this.searchQuery) params.set('search', this.searchQuery);
            if (this.statusFilter !== 'all') params.set('status', this.statusFilter);
            if (this.periodFilter !== 'month') params.set('period', this.periodFilter);
            if (this.yearFilter) params.set('year', this.yearFilter);
            window.location.href = '{{ route('dashboard') }}' + (params.toString() ? '?' + params.toString() : '');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Monthly Chart
    const monthlyData = @json($monthlyData ?? []);
    if (document.getElementById('monthlyChart') && monthlyData.length) {
        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.label),
                datasets: [
                    {
                        label: 'Reja',
                        data: monthlyData.map(d => d.plan),
                        borderColor: '#64748b',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        borderDash: [5, 5],
                        pointRadius: 3
                    },
                    {
                        label: 'Fakt',
                        data: monthlyData.map(d => d.paid),
                        borderColor: '#38bdf8',
                        backgroundColor: 'rgba(56, 189, 248, 0.1)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(56, 189, 248, 0.06)' }, ticks: { color: '#64748b' } },
                    y: { grid: { color: 'rgba(56, 189, 248, 0.06)' }, ticks: { color: '#64748b', callback: v => v >= 1e9 ? (v/1e9).toFixed(1)+'B' : v >= 1e6 ? (v/1e6).toFixed(0)+'M' : v } }
                }
            }
        });
    }

    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ["To'langan", 'Kutilmoqda', 'Kechikkan'],
                datasets: [{
                    data: [{{ $stats['jami_tolangan'] }}, {{ $stats['muddati_otmagan_qarz'] }}, {{ $stats['jami_qarzdorlik'] }}],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '65%' }
        });
    }
});
</script>
@endsection
