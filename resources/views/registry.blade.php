@extends('layouts.dark')

@section('title', 'Reestr')
@section('header', 'Reestr')
@section('subheader', 'Ijarachilar, Lotlar va To\'lovlar')

@section('content')
<div x-data="{ activeTab: '{{ $tab }}' }">
    <!-- Search -->
    <div class="mb-6">
        <form method="GET" action="{{ route('registry') }}" class="flex gap-4">
            <input type="hidden" name="tab" :value="activeTab">
            <div class="flex-1">
                <input type="text" name="search" value="{{ $search }}" placeholder="Qidirish..."
                    class="form-input">
            </div>
            <button type="submit" class="btn btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Qidirish
            </button>
            @if($search)
            <a href="{{ route('registry', ['tab' => $tab]) }}" class="btn btn-secondary">
                Tozalash
            </a>
            @endif
        </form>
    </div>

    <!-- Unified merged table (Contracts + Tenants + Lots + Payments) -->
    <div class="card mb-6">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700/50">
            <h3 class="text-base font-semibold text-[#e2e8f0]">Yagona reestr (shartnomalar bo'yicha)</h3>
            <div class="flex items-center gap-4 text-xs text-[#64748b]">
                <span>Ijarachilar: <span class="text-[#e2e8f0] font-semibold">{{ $counts['tenants'] }}</span></span>
                <span>Lotlar: <span class="text-[#e2e8f0] font-semibold">{{ $counts['lots'] }}</span></span>
                <span>To'lovlar: <span class="text-[#e2e8f0] font-semibold">{{ $counts['payments'] }}</span></span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Ijarachi</th>
                        <th>INN</th>
                        <th>Lot</th>
                        <th>Obyekt</th>
                        <th>Shartnoma</th>
                        <th class="text-right">Plan</th>
                        <th class="text-right">To'langan</th>
                        <th class="text-right">Qoldiq</th>
                        <th class="text-right">Oxirgi to'lov</th>
                        <th>Amal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $contract)
                    @php
                        $plan = $contract->paymentSchedules->sum('tolov_summasi');
                        $paid = $contract->paymentSchedules->sum('tolangan_summa');
                        $debt = $contract->paymentSchedules->sum('qoldiq_summa');
                        $lastPayment = $contract->payments->sortByDesc('tolov_sanasi')->first();
                    @endphp
                    <tr>
                        <td class="text-[#e2e8f0]">{{ $contract->tenant?->name ?? '-' }}</td>
                        <td class="text-[#94a3b8] font-mono text-xs">{{ $contract->tenant?->inn ?? '-' }}</td>
                        <td>
                            @if($contract->lot)
                            <a href="{{ route('registry.lots.show', $contract->lot) }}" class="text-cyan hover:underline font-medium">
                                {{ $contract->lot->lot_raqami }}
                            </a>
                            @else
                            -
                            @endif
                        </td>
                        <td class="text-[#94a3b8] max-w-[200px] truncate">{{ $contract->lot?->obyekt_nomi ?? '-' }}</td>
                        <td class="text-[#94a3b8]">
                            <a href="{{ route('registry.contracts.show', $contract) }}" class="text-cyan hover:underline font-medium">
                                {{ $contract->shartnoma_raqami }}
                            </a>
                        </td>
                        <td class="text-right text-[#94a3b8] whitespace-nowrap">{{ number_format($plan, 0, '', ' ') }}</td>
                        <td class="text-right text-emerald-400 whitespace-nowrap">{{ number_format($paid, 0, '', ' ') }}</td>
                        <td class="text-right whitespace-nowrap {{ $debt > 0 ? 'text-[#f97373]' : 'text-[#22c55e]' }}">{{ $debt > 0 ? number_format($debt, 0, '', ' ') : '0' }}</td>
                        <td class="text-right text-[#94a3b8]">
                            {{ $lastPayment ? \Carbon\Carbon::parse($lastPayment->tolov_sanasi)->format('d.m.Y') : '—' }}
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                @if($contract->tenant)
                                <a href="{{ route('registry.tenants.show', $contract->tenant) }}" class="text-cyan hover:underline text-xs">Ijarachi</a>
                                @endif
                                @if($contract->lot)
                                <a href="{{ route('registry.lots.show', $contract->lot) }}" class="text-cyan hover:underline text-xs">Lot</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-[#64748b] py-6">Ma'lumot topilmadi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 px-4 pb-4 pagination">
            {{ $contracts->links() }}
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-[rgba(56,189,248,0.1)] mb-6">
        <nav class="flex space-x-6">
            <button @click="activeTab = 'tenants'"
                :class="activeTab === 'tenants' ? 'border-[#38bdf8] text-[#38bdf8]' : 'border-transparent text-[#94a3b8] hover:text-[#e2e8f0] hover:border-[rgba(56,189,248,0.3)]'"
                class="py-3 px-1 border-b-2 font-medium text-sm transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Ijarachilar
                <span class="badge badge-info">{{ $counts['tenants'] }}</span>
            </button>
            <button @click="activeTab = 'lots'"
                :class="activeTab === 'lots' ? 'border-[#38bdf8] text-[#38bdf8]' : 'border-transparent text-[#94a3b8] hover:text-[#e2e8f0] hover:border-[rgba(56,189,248,0.3)]'"
                class="py-3 px-1 border-b-2 font-medium text-sm transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Lotlar
                <span class="badge badge-info">{{ $counts['lots'] }}</span>
            </button>
            <button @click="activeTab = 'payments'"
                :class="activeTab === 'payments' ? 'border-[#38bdf8] text-[#38bdf8]' : 'border-transparent text-[#94a3b8] hover:text-[#e2e8f0] hover:border-[rgba(56,189,248,0.3)]'"
                class="py-3 px-1 border-b-2 font-medium text-sm transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                To'lovlar
                <span class="badge badge-info">{{ $counts['payments'] }}</span>
            </button>
        </nav>
    </div>

    <!-- Tenants Tab -->
    <div x-show="activeTab === 'tenants'" x-cloak>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-[#e2e8f0]">Ijarachilar ro'yxati</h3>
            <a href="{{ route('registry.tenants.create') }}" class="btn btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Yangi ijarachi
            </a>
        </div>
        <div class="card">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Nomi</th>
                        <th>INN</th>
                        <th>Telefon</th>
                        <th>Shartnomalar</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                    <tr>
                        <td>
                            <a href="{{ route('registry.tenants.show', $tenant) }}" class="text-cyan hover:underline font-medium">
                                {{ $tenant->name }}
                            </a>
                        </td>
                        <td class="text-[#94a3b8]">{{ $tenant->inn }}</td>
                        <td class="text-[#94a3b8]">{{ $tenant->phone }}</td>
                        <td>
                            <span class="badge badge-info">{{ $tenant->activeContracts->count() }} faol</span>
                        </td>
                        <td>
                            <a href="{{ route('registry.tenants.show', $tenant) }}" class="text-cyan hover:underline text-sm">Ko'rish</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-[#64748b] py-8">Ma'lumot topilmadi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 pagination">{{ $tenants->links() }}</div>
    </div>

    <!-- Lots Tab -->
    <div x-show="activeTab === 'lots'" x-cloak>
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-4">
                <h3 class="text-base font-semibold text-[#e2e8f0]">Lotlar ro'yxati</h3>
                <form method="GET" action="{{ route('registry') }}" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="lots">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <select name="lot_status" onchange="this.form.submit()" class="form-input form-select text-sm py-1.5 w-auto">
                        <option value="">Barcha holatlar</option>
                        <option value="bosh" {{ request('lot_status') == 'bosh' ? 'selected' : '' }}>Bo'sh</option>
                        <option value="ijarada" {{ request('lot_status') == 'ijarada' ? 'selected' : '' }}>Ijarada</option>
                        <option value="muddati_tugagan" {{ request('lot_status') == 'muddati_tugagan' ? 'selected' : '' }}>Muddati tugagan</option>
                    </select>
                    @if(request('lot_status'))
                    <a href="{{ route('registry', ['tab' => 'lots', 'search' => $search]) }}" class="text-xs text-red-400 hover:text-red-300">Tozalash</a>
                    @endif
                </form>
            </div>
            <a href="{{ route('registry.lots.create') }}" class="btn btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Yangi lot
            </a>
        </div>
        <div class="card">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Lot raqami</th>
                        <th>Obyekt</th>
                        <th>Tuman</th>
                        <th>Maydon</th>
                        <th>Holat</th>
                        <th>Ijarachi</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lots as $lot)
                    @php
                        $contract = $lot->contracts->first();
                        $isContractExpired = $contract && $contract->is_expired;
                    @endphp
                    <tr class="{{ $isContractExpired ? 'opacity-60' : '' }}">
                        <td>
                            <a href="{{ route('registry.lots.show', $lot) }}" class="text-cyan hover:underline font-medium">
                                {{ $lot->lot_raqami }}
                            </a>
                        </td>
                        <td class="text-[#94a3b8]">{{ $lot->obyekt_nomi }}</td>
                        <td class="text-[#94a3b8]">{{ $lot->tuman ?? '-' }}</td>
                        <td class="text-[#94a3b8]">{{ number_format($lot->maydon, 2) }} m²</td>
                        <td>
                            @php
                            $holatBadges = [
                                'bosh' => 'badge-success',
                                'ijarada' => 'badge-info',
                                'band' => 'badge-warning',
                                'tamirlashda' => 'badge-danger',
                            ];
                            @endphp
                            @if($isContractExpired)
                                <span class="badge bg-gray-600 text-white">Muddati tugagan</span>
                            @else
                                <span class="badge {{ $holatBadges[$lot->holat] ?? 'badge-info' }}">
                                    {{ ucfirst($lot->holat) }}
                                </span>
                            @endif
                        </td>
                        <td class="text-[#94a3b8]">{{ $contract?->tenant?->name ?? '-' }}</td>
                        <td>
                            <a href="{{ route('registry.lots.show', $lot) }}" class="text-cyan hover:underline text-sm">Ko'rish</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-[#64748b] py-8">Ma'lumot topilmadi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 pagination">{{ $lots->links() }}</div>
    </div>

    <!-- Payments Tab -->
    <div x-show="activeTab === 'payments'" x-cloak>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-[#e2e8f0]">To'lovlar ro'yxati</h3>
            <a href="{{ route('registry.payments.create') }}" class="btn btn-success">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Yangi to'lov
            </a>
        </div>
        <div class="card">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>To'lov raqami</th>
                        <th>Sana</th>
                        <th>Ijarachi</th>
                        <th class="text-right">Summa</th>
                        <th>Usul</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr>
                        <td class="font-medium">{{ $payment->tolov_raqami }}</td>
                        <td class="text-[#94a3b8]">{{ \Carbon\Carbon::parse($payment->tolov_sanasi)->format('d.m.Y') }}</td>
                        <td class="text-[#94a3b8]">{{ $payment->contract?->tenant?->name ?? '-' }}</td>
                        <td class="text-right font-medium text-green">{{ number_format($payment->summa, 0, '', ' ') }} so'm</td>
                        <td>
                            @php
                            $usulLabels = [
                                'bank_otkazmasi' => 'Bank o\'tkazmasi',
                                'naqd' => 'Naqd',
                                'karta' => 'Karta',
                            ];
                            @endphp
                            <span class="badge badge-info">{{ $usulLabels[$payment->tolov_usuli] ?? $payment->tolov_usuli }}</span>
                        </td>
                        <td>
                            @if($payment->contract?->lot)
                            <a href="{{ route('registry.lots.show', $payment->contract->lot) }}" class="text-cyan hover:underline text-sm">Lot</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-[#64748b] py-8">Ma'lumot topilmadi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 pagination">{{ $payments->links() }}</div>
    </div>
</div>
@endsection
