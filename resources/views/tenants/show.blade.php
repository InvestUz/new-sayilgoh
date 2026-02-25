@extends('layouts.dark')
@section('title', $tenant->name)
@section('header', $tenant->name)
@section('subheader', 'Ijarachi tafsilotlari')
@section('header-actions')
<a href="{{ route('registry.tenants.edit', $tenant) }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
    Tahrirlash
</a>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">FAOL LOTLAR</p>
                    <p class="text-4xl font-bold text-white mt-2">{{ $stats['faol_lotlar'] }} <span class="text-lg font-normal text-slate-400">ta</span></p>
                </div>
                <div class="w-10 h-10 bg-slate-700/50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 border-l-blue-500 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">JAMI SUMMA</p>
                    @php
                    $js = $stats['jami_summa'];
                    if ($js >= 1000000000) { $fs = number_format($js / 1000000000, 2, ',', ' '); $sx = 'mlrd'; }
                    elseif ($js >= 1000000) { $fs = number_format($js / 1000000, 2, ',', ' '); $sx = 'mln'; }
                    else { $fs = number_format($js, 0, ',', ' '); $sx = ''; }
                    @endphp
                    <p class="text-3xl font-bold text-blue-600 mt-2">{{ $fs }} <span class="text-sm font-normal text-gray-400">{{ $sx }} so'm</span></p>
                </div>
                <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 border-l-red-500 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">QARZ</p>
                    @php
                    $jq = $stats['jami_qarz'];
                    if ($jq >= 1000000000) { $fq = number_format($jq / 1000000000, 2, ',', ' '); $sq = 'mlrd'; }
                    elseif ($jq >= 1000000) { $fq = number_format($jq / 1000000, 2, ',', ' '); $sq = 'mln'; }
                    else { $fq = number_format($jq, 0, ',', ' '); $sq = ''; }
                    @endphp
                    <p class="text-3xl font-bold {{ $stats['jami_qarz'] > 0 ? 'text-red-400' : 'text-slate-100' }} mt-2">{{ $fq }} <span class="text-sm font-normal text-slate-500">{{ $sq }} so'm</span></p>
                </div>
                <div class="w-10 h-10 bg-red-500/10 border border-red-500/40 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 border-l-4 border-l-emerald-500 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">TO'LANGAN</p>
                    @php
                    $jt = $stats['jami_summa'] - $stats['jami_qarz'];
                    if ($jt >= 1000000000) { $ft = number_format($jt / 1000000000, 2, ',', ' '); $st = 'mlrd'; }
                    elseif ($jt >= 1000000) { $ft = number_format($jt / 1000000, 2, ',', ' '); $st = 'mln'; }
                    else { $ft = number_format($jt, 0, ',', ' '); $st = ''; }
                    @endphp
                    <p class="text-3xl font-bold text-emerald-400 mt-2">{{ $ft }} <span class="text-sm font-normal text-slate-500">{{ $st }} so'm</span></p>
                </div>
                <div class="w-10 h-10 bg-emerald-500/10 border border-emerald-500/40 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Two column: Info + Bank -->
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-slate-900/60 border border-slate-700/70 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/70 flex items-center justify-between bg-slate-800/80">
                <h3 class="font-semibold text-slate-100">Asosiy ma'lumotlar</h3>
                <a href="{{ route('registry.tenants.edit', $tenant) }}" class="text-xs text-cyan-400 hover:text-cyan-300 font-medium">Tahrirlash</a>
            </div>
            <div class="p-5 space-y-1 text-sm">
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Nomi:</span><span class="text-gray-900 font-semibold">{{ $tenant->name }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Turi:</span>
                    @if($tenant->type == 'yuridik')
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded">Yuridik shaxs</span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-green-100 text-green-700 rounded">Jismoniy shaxs</span>
                    @endif
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">INN:</span><span class="text-gray-900 font-mono font-medium">{{ $tenant->inn ?? '—' }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">OKED:</span><span class="text-gray-900 font-mono">{{ $tenant->oked ?? '—' }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Direktor:</span><span class="text-gray-900">{{ $tenant->director_name ?? '—' }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Pasport:</span><span class="text-gray-900 font-mono">{{ $tenant->passport_serial ?? '—' }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Telefon:</span><span class="text-gray-900 font-medium">{{ $tenant->phone ?? '—' }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Email:</span><span class="text-gray-900">{{ $tenant->email ?? '—' }}</span></div>
                <div class="flex justify-between py-3"><span class="text-gray-500">Manzil:</span><span class="text-gray-900 text-right max-w-xs">{{ $tenant->address ?? '—' }}</span></div>
            </div>
        </div>
        <div class="bg-slate-900/60 border border-slate-700/70 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700/70 flex items-center justify-between bg-slate-800/80">
                <h3 class="font-semibold text-slate-100">Bank rekvizitlari</h3>
                <a href="{{ route('registry.tenants.edit', $tenant) }}" class="text-xs text-cyan-400 hover:text-cyan-300 font-medium">Tahrirlash</a>
            </div>
            <div class="p-5 space-y-1 text-sm">
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Bank:</span><span class="text-gray-900 font-medium">{{ $tenant->bank_name ?? '—' }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Hisob raqami:</span><span class="text-gray-900 font-mono font-medium">{{ $tenant->bank_account ?? '—' }}</span></div>
                <div class="flex justify-between py-3"><span class="text-gray-500">MFO:</span><span class="text-gray-900 font-mono font-medium">{{ $tenant->bank_mfo ?? '—' }}</span></div>
            </div>
        </div>
    </div>

    <!-- Lotlar ro'yxati -->
    <div class="bg-slate-900/60 border border-slate-700/70 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/70 flex items-center justify-between bg-slate-800/80">
            <h3 class="font-semibold text-slate-100">Ijaradagi lotlar</h3>
            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-cyan-500/10 text-cyan-300 rounded">{{ $lots->count() }} ta</span>
        </div>
        @if($lots->count() > 0)
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5">
            @foreach($lots as $lot)
            <a href="{{ route('registry.lots.show', $lot) }}" class="block border border-slate-700/70 bg-slate-800/60 rounded-xl p-5 hover:border-cyan-400 hover:bg-slate-800/80 hover:shadow-lg transition group">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-lg font-bold text-slate-100 group-hover:text-cyan-400">Lot {{ $lot->lot_raqami }}</span>
                        <p class="text-sm text-slate-400 mt-1">{{ $lot->obyekt_nomi }}</p>
                    </div>
                    @if($lot->holat === 'ijarada')
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-emerald-500/10 text-emerald-300 rounded">Faol</span>
                    @else
                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-slate-700/80 text-slate-300 rounded">{{ $lot->holat }}</span>
                    @endif
                </div>
                <div class="mt-4 pt-4 border-t border-slate-700/70 text-sm text-slate-400 space-y-2">
                    <div class="flex justify-between"><span>Maydon:</span><span class="text-slate-100 font-medium">{{ number_format($lot->maydon, 1) }} m²</span></div>
                    <div class="flex justify-between"><span>Manzil:</span><span class="text-slate-100">{{ $lot->tuman ?? $lot->manzil }}</span></div>
                </div>
            </a>
            @endforeach
        </div>
        @else
        <div class="px-5 py-12 text-center text-slate-500">
            Bu ijarachiga biriktirilgan lot yo'q
        </div>
        @endif
    </div>

    <!-- Shartnomalar -->
    <div class="bg-slate-900/60 border border-slate-700/70 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/70 bg-slate-800/80">
            <h3 class="font-semibold text-slate-100">Shartnomalar</h3>
            <p class="text-xs text-slate-400">Barcha shartnomalar tarixi</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/80 text-xs text-slate-400 uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Shartnoma</th>
                        <th class="px-5 py-3 text-left font-medium">Lot</th>
                        <th class="px-5 py-3 text-right font-medium">Summa</th>
                        <th class="px-5 py-3 text-right font-medium">Qarz</th>
                        <th class="px-5 py-3 text-center font-medium">Holat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($tenant->contracts as $contract)
                    @php $qarz = $contract->paymentSchedules->sum('qoldiq_summa'); @endphp
                    <tr class="hover:bg-slate-800/60">
                        <td class="px-5 py-4">
                            <a href="{{ route('registry.contracts.show', $contract) }}" class="text-slate-100 hover:text-cyan-400 font-semibold">{{ $contract->shartnoma_raqami }}</a>
                        </td>
                        <td class="px-5 py-4">
                            @if($contract->lot)
                            <a href="{{ route('registry.lots.show', $contract->lot) }}" class="text-cyan-400 hover:text-cyan-300">Lot {{ $contract->lot->lot_raqami }}</a>
                            @else
                            <span class="text-slate-500">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right font-medium text-slate-100">{{ number_format($contract->shartnoma_summasi, 0, '', ' ') }}</td>
                        <td class="px-5 py-4 text-right {{ $qarz > 0 ? 'text-red-400 font-bold' : 'text-slate-100' }}">{{ number_format($qarz, 0, '', ' ') }}</td>
                        <td class="px-5 py-4 text-center">
                            @if($contract->holat === 'faol')
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-emerald-500/10 text-emerald-300 rounded">Faol</span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-slate-700/80 text-slate-300 rounded">Yakunlangan</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Shartnomalar yo'q</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
