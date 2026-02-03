@extends('layouts.dark')

@section('title', 'Import Statistikasi')

@section('content')
<div class="space-y-6" x-data="{
    showMatchedModal: false,
    showUnmatchedModal: false,
    showContractsModal: false,
    showLotsModal: false
}">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Ma'lumotlar Import Statistikasi</h1>
            <p class="text-[#64748b] mt-1">CSV fayllardan import qilingan ma'lumotlar holati</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Orqaga
        </a>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="card p-4">
            <div class="text-[#64748b] text-xs uppercase tracking-wider">Lotlar</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($stats['lots_count']) }}</div>
            <div class="text-[#22c55e] text-xs mt-1">dataset.csv dan</div>
        </div>
        <div class="card p-4">
            <div class="text-[#64748b] text-xs uppercase tracking-wider">Ijarachilar</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($stats['tenants_count']) }}</div>
            <div class="text-[#22c55e] text-xs mt-1">noyob mijozlar</div>
        </div>
        <div class="card p-4">
            <div class="text-[#64748b] text-xs uppercase tracking-wider">Shartnomalar</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($stats['contracts_count']) }}</div>
            <div class="text-[#38bdf8] text-xs mt-1">{{ $stats['active_contracts'] }} faol</div>
        </div>
        <div class="card p-4">
            <div class="text-[#64748b] text-xs uppercase tracking-wider">To'lov Grafiklar</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($stats['schedules_count']) }}</div>
            <div class="text-[#64748b] text-xs mt-1">oylik rejalar</div>
        </div>
        <div class="card p-4 cursor-pointer hover:border-[#22c55e] transition-colors" @click="showMatchedModal = true">
            <div class="text-[#64748b] text-xs uppercase tracking-wider">To'lovlar</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($stats['payments_count']) }}</div>
            <div class="text-[#22c55e] text-xs mt-1 underline">ro'yxatni ko'rish</div>
        </div>
        <div class="card p-4 cursor-pointer hover:border-[#22c55e] transition-colors" @click="showMatchedModal = true">
            <div class="text-[#64748b] text-xs uppercase tracking-wider">Mos kelgan</div>
            <div class="text-2xl font-bold text-[#22c55e] mt-1">{{ number_format($importedPayments) }}</div>
            <div class="text-[#64748b] text-xs mt-1 underline">ro'yxatni ko'rish</div>
        </div>
    </div>

    <!-- Matching Statistics -->
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-semibold text-white">
                <svg class="w-5 h-5 inline mr-2 text-[#38bdf8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                To'lovlarni Moslashtirish Natijalari <span class="text-[#64748b] text-sm font-normal">(bosing - ro'yxatni ko'rish)</span>
            </h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-[rgba(34,197,94,0.1)] border border-[rgba(34,197,94,0.2)] rounded-lg p-4 cursor-pointer hover:border-[#22c55e] transition-colors" @click="showMatchedModal = true">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[rgba(34,197,94,0.2)] flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#22c55e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <div class="text-[#22c55e] text-2xl font-bold">{{ $matchedByLot }}</div>
                            <div class="text-[#64748b] text-sm">Lot raqami bo'yicha</div>
                        </div>
                    </div>
                    <p class="text-xs text-[#22c55e] mt-2 underline">Ro'yxatni ko'rish uchun bosing</p>
                </div>

                <div class="bg-[rgba(56,189,248,0.1)] border border-[rgba(56,189,248,0.2)] rounded-lg p-4 cursor-pointer hover:border-[#38bdf8] transition-colors" @click="showMatchedModal = true">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[rgba(56,189,248,0.2)] flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#38bdf8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                        </div>
                        <div>
                            <div class="text-[#38bdf8] text-2xl font-bold">{{ $matchedByInn }}</div>
                            <div class="text-[#64748b] text-sm">INN/STIR bo'yicha</div>
                        </div>
                    </div>
                    <p class="text-xs text-[#38bdf8] mt-2 underline">Ro'yxatni ko'rish uchun bosing</p>
                </div>

                <div class="bg-[rgba(245,158,11,0.1)] border border-[rgba(245,158,11,0.2)] rounded-lg p-4 cursor-pointer hover:border-[#f59e0b] transition-colors" @click="showMatchedModal = true">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[rgba(245,158,11,0.2)] flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#f59e0b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div>
                            <div class="text-[#f59e0b] text-2xl font-bold">{{ $matchedByName }}</div>
                            <div class="text-[#64748b] text-sm">Nomi bo'yicha</div>
                        </div>
                    </div>
                    <p class="text-xs text-[#f59e0b] mt-2 underline">Ro'yxatni ko'rish uchun bosing</p>
                </div>

                <div class="bg-[rgba(239,68,68,0.1)] border border-[rgba(239,68,68,0.2)] rounded-lg p-4 cursor-pointer hover:border-[#ef4444] transition-colors" @click="showUnmatchedModal = true">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[rgba(239,68,68,0.2)] flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#ef4444]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div>
                            <div class="text-[#ef4444] text-2xl font-bold">{{ $unmatchedPayments->count() }}</div>
                            <div class="text-[#64748b] text-sm">Mos kelmagan</div>
                        </div>
                    </div>
                    <p class="text-xs text-[#ef4444] mt-2 underline">Ro'yxatni ko'rish uchun bosing</p>
                </div>
            </div>

            <div class="mt-6 p-4 bg-[rgba(245,158,11,0.1)] border border-[rgba(245,158,11,0.2)] rounded-lg">
                <h4 class="text-[#f59e0b] font-medium flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Mos kelmaganligi sababi
                </h4>
                <p class="text-[#64748b] text-sm mt-2">
                    FACT.csv fayli <strong class="text-white">2024-yil mart</strong> oyidan boshlab to'lovlarni o'z ichiga oladi,
                    ammo bu to'lovlar <strong class="text-white">eski auksion tizimidagi</strong> lot raqamlari bilan (masalan: L8408626L).
                    Hozirgi dataset.csv <strong class="text-white">yangi auksion davri</strong> uchun boshqa lot raqamlarini o'z ichiga oladi.
                    Bu <strong class="text-[#f59e0b]">ma'lumotlar manbasi nomutanosibligi</strong> - kod xatosi emas.
                </p>
            </div>
        </div>
    </div>

    <!-- Payment Schedule Status -->
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-semibold text-white">To'lov Grafiklar Holati</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 rounded-lg bg-[rgba(34,197,94,0.1)]">
                    <div class="text-3xl font-bold text-[#22c55e]">{{ number_format($scheduleStats['tolangan']) }}</div>
                    <div class="text-[#64748b] text-sm mt-1">To'langan</div>
                </div>
                <div class="text-center p-4 rounded-lg bg-[rgba(56,189,248,0.1)]">
                    <div class="text-3xl font-bold text-[#38bdf8]">{{ number_format($scheduleStats['qisman_tolangan']) }}</div>
                    <div class="text-[#64748b] text-sm mt-1">Qisman to'langan</div>
                </div>
                <div class="text-center p-4 rounded-lg bg-[rgba(239,68,68,0.1)]">
                    <div class="text-3xl font-bold text-[#ef4444]">{{ number_format($scheduleStats['tolanmagan']) }}</div>
                    <div class="text-[#64748b] text-sm mt-1">To'lanmagan</div>
                </div>
                <div class="text-center p-4 rounded-lg bg-[rgba(100,116,139,0.1)]">
                    <div class="text-3xl font-bold text-[#64748b]">{{ number_format($scheduleStats['kutilmoqda']) }}</div>
                    <div class="text-[#64748b] text-sm mt-1">Kutilmoqda</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Issues Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card cursor-pointer hover:border-[#f59e0b] transition-colors" @click="showContractsModal = true">
            <div class="card-header">
                <h2 class="text-lg font-semibold text-white">To'lovsiz Shartnomalar <span class="badge badge-warning ml-2">{{ $contractsWithoutPayments->count() }}</span></h2>
                <span class="text-[#f59e0b] text-xs underline">To'liq ro'yxat uchun bosing</span>
            </div>
            <div class="p-4 max-h-48 overflow-y-auto">
                @forelse($contractsWithoutPayments->take(5) as $contract)
                <div class="py-2 border-b border-[rgba(56,189,248,0.08)] last:border-0">
                    <div class="text-white text-sm">{{ $contract->tenant->name ?? 'Nomalum' }}</div>
                    <div class="text-[#64748b] text-xs">Lot: {{ $contract->lot->lot_raqami ?? '-' }}</div>
                </div>
                @empty
                <div class="text-center text-[#22c55e] py-4">Barcha shartnomalar to'lovga ega</div>
                @endforelse
            </div>
        </div>

        <div class="card cursor-pointer hover:border-[#ef4444] transition-colors" @click="showLotsModal = true">
            <div class="card-header">
                <h2 class="text-lg font-semibold text-white">Muammoli Lotlar <span class="badge badge-danger ml-2">{{ $lotsWithIssues->count() }}</span></h2>
                <span class="text-[#ef4444] text-xs underline">To'liq ro'yxat uchun bosing</span>
            </div>
            <div class="p-4 max-h-48 overflow-y-auto">
                @forelse($lotsWithIssues->take(5) as $lot)
                <div class="py-2 border-b border-[rgba(56,189,248,0.08)] last:border-0">
                    <div class="text-white text-sm">{{ $lot->obyekt_nomi }}</div>
                    <div class="text-[#ef4444] text-xs">{{ $lot->lot_raqami ?: 'Lot raqami yoq' }}</div>
                </div>
                @empty
                <div class="text-center text-[#22c55e] py-4">Muammoli lotlar yo'q</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Matched Payments Modal -->
    <div x-show="showMatchedModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showMatchedModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/70" @click="showMatchedModal = false"></div>
            <div class="relative bg-[#0f172a] border border-[rgba(56,189,248,0.15)] rounded-xl max-w-6xl w-full max-h-[85vh] overflow-hidden">
                <div class="sticky top-0 bg-[#0f172a] border-b border-[rgba(56,189,248,0.1)] p-4 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white">Mos Kelgan To'lovlar ({{ $allMatchedPayments->count() }} ta)</h3>
                    <button @click="showMatchedModal = false" class="text-[#64748b] hover:text-white text-2xl">&times;</button>
                </div>
                <div class="overflow-y-auto max-h-[70vh] p-4">
                    <table class="w-full text-sm">
                        <thead class="text-[#64748b] text-xs uppercase sticky top-0 bg-[#0f172a]">
                            <tr><th class="text-left p-2">Sana</th><th class="text-left p-2">Lot</th><th class="text-left p-2">Ijarachi</th><th class="text-right p-2">Summa</th><th class="text-left p-2">Usul</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[rgba(56,189,248,0.08)]">
                            @foreach($allMatchedPayments as $payment)
                            <tr class="hover:bg-[rgba(56,189,248,0.05)]">
                                <td class="p-2 text-white">{{ $payment->tolov_sanasi ? \Carbon\Carbon::parse($payment->tolov_sanasi)->format('d.m.Y') : '-' }}</td>
                                <td class="p-2 text-[#38bdf8]">{{ $payment->contract->lot->lot_raqami ?? '-' }}</td>
                                <td class="p-2 text-white">{{ Str::limit($payment->contract->tenant->name ?? '-', 30) }}</td>
                                <td class="p-2 text-right text-[#22c55e] font-medium">{{ number_format($payment->summa, 0, '.', ' ') }}</td>
                                <td class="p-2">
                                    @if(str_contains($payment->izoh ?? '', 'lot_number'))<span class="text-[#22c55e] text-xs">lot</span>
                                    @elseif(str_contains($payment->izoh ?? '', 'inn'))<span class="text-[#38bdf8] text-xs">INN</span>
                                    @elseif(str_contains($payment->izoh ?? '', 'tenant_name'))<span class="text-[#f59e0b] text-xs">nomi</span>
                                    @else<span class="text-[#64748b] text-xs">-</span>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Unmatched Payments Modal -->
    <div x-show="showUnmatchedModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showUnmatchedModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/70" @click="showUnmatchedModal = false"></div>
            <div class="relative bg-[#0f172a] border border-[rgba(239,68,68,0.15)] rounded-xl max-w-6xl w-full max-h-[85vh] overflow-hidden">
                <div class="sticky top-0 bg-[#0f172a] border-b border-[rgba(239,68,68,0.1)] p-4 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-[#ef4444]">Mos Kelmagan To'lovlar ({{ $unmatchedPayments->count() }} ta)</h3>
                    <button @click="showUnmatchedModal = false" class="text-[#64748b] hover:text-white text-2xl">&times;</button>
                </div>
                <div class="p-4 bg-[rgba(239,68,68,0.1)] border-b border-[rgba(239,68,68,0.2)]">
                    <p class="text-[#64748b] text-sm">Bu to'lovlar FACT.csv dan olingan, lekin dataset.csv dagi lot/ijarachi bilan moslashtirib bo'lmadi.</p>
                </div>
                <div class="overflow-y-auto max-h-[60vh] p-4">
                    <table class="w-full text-sm">
                        <thead class="text-[#64748b] text-xs uppercase sticky top-0 bg-[#0f172a]">
                            <tr><th class="text-left p-2">Sana</th><th class="text-left p-2">Lot</th><th class="text-left p-2">INN</th><th class="text-left p-2">Ijarachi</th><th class="text-right p-2">Summa</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[rgba(56,189,248,0.08)]">
                            @foreach($unmatchedPayments as $p)
                            <tr class="hover:bg-[rgba(239,68,68,0.05)]">
                                <td class="p-2 text-white">{{ $p['date'] }}</td>
                                <td class="p-2 text-[#ef4444] font-mono">{{ $p['lot_number'] ?: '-' }}</td>
                                <td class="p-2 text-[#64748b] font-mono">{{ $p['inn'] ?: '-' }}</td>
                                <td class="p-2 text-white">{{ Str::limit($p['tenant_name'], 30) }}</td>
                                <td class="p-2 text-right text-[#ef4444] font-medium">{{ number_format($p['amount'], 0, '.', ' ') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Contracts Modal -->
    <div x-show="showContractsModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showContractsModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/70" @click="showContractsModal = false"></div>
            <div class="relative bg-[#0f172a] border border-[rgba(245,158,11,0.15)] rounded-xl max-w-4xl w-full max-h-[85vh] overflow-hidden">
                <div class="sticky top-0 bg-[#0f172a] border-b border-[rgba(245,158,11,0.1)] p-4 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-[#f59e0b]">To'lovsiz Shartnomalar ({{ $contractsWithoutPayments->count() }} ta)</h3>
                    <button @click="showContractsModal = false" class="text-[#64748b] hover:text-white text-2xl">&times;</button>
                </div>
                <div class="overflow-y-auto max-h-[70vh] p-4">
                    <table class="w-full text-sm">
                        <thead class="text-[#64748b] text-xs uppercase sticky top-0 bg-[#0f172a]">
                            <tr><th class="text-left p-2">Lot</th><th class="text-left p-2">Ijarachi</th><th class="text-left p-2">Shartnoma</th><th class="text-right p-2">Summa</th><th class="p-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-[rgba(56,189,248,0.08)]">
                            @foreach($contractsWithoutPayments as $c)
                            <tr class="hover:bg-[rgba(245,158,11,0.05)]">
                                <td class="p-2 text-[#38bdf8]">{{ $c->lot->lot_raqami ?? '-' }}</td>
                                <td class="p-2 text-white">{{ $c->tenant->name ?? '-' }}</td>
                                <td class="p-2 text-[#64748b]">{{ $c->shartnoma_raqami }}</td>
                                <td class="p-2 text-right text-[#f59e0b]">{{ number_format($c->shartnoma_summasi ?? 0, 0, '.', ' ') }}</td>
                                <td class="p-2"><a href="{{ route('registry.contracts.show', $c) }}" class="text-[#38bdf8] hover:underline text-xs">Ko'rish</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Lots Modal -->
    <div x-show="showLotsModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showLotsModal = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/70" @click="showLotsModal = false"></div>
            <div class="relative bg-[#0f172a] border border-[rgba(239,68,68,0.15)] rounded-xl max-w-4xl w-full max-h-[85vh] overflow-hidden">
                <div class="sticky top-0 bg-[#0f172a] border-b border-[rgba(239,68,68,0.1)] p-4 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-[#ef4444]">Muammoli Lotlar ({{ $lotsWithIssues->count() }} ta)</h3>
                    <button @click="showLotsModal = false" class="text-[#64748b] hover:text-white text-2xl">&times;</button>
                </div>
                <div class="overflow-y-auto max-h-[70vh] p-4">
                    <table class="w-full text-sm">
                        <thead class="text-[#64748b] text-xs uppercase sticky top-0 bg-[#0f172a]">
                            <tr><th class="text-left p-2">Lot</th><th class="text-left p-2">Obyekt</th><th class="text-left p-2">Muammo</th><th class="p-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-[rgba(56,189,248,0.08)]">
                            @foreach($lotsWithIssues as $l)
                            <tr class="hover:bg-[rgba(239,68,68,0.05)]">
                                <td class="p-2 text-[#ef4444] font-mono">{{ $l->lot_raqami ?: '(bosh)' }}</td>
                                <td class="p-2 text-white">{{ $l->obyekt_nomi }}</td>
                                <td class="p-2 text-[#ef4444] text-xs">@if(empty($l->lot_raqami))Raqam yoq @elseif(str_contains($l->lot_raqami, '-'))Takroriy @endif</td>
                                <td class="p-2"><a href="{{ route('registry.lots.show', $l) }}" class="text-[#38bdf8] hover:underline text-xs">Ko'rish</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
