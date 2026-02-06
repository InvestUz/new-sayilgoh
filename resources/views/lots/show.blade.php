@extends('layouts.dark')
@section('title', $lot->lot_raqami)
@section('header', $lot->lot_raqami)
@section('subheader', 'Lot tafsilotlari')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
        <span class="px-3 py-1 text-xs font-medium rounded {{ $activeContract ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600' }}">
            {{ $activeContract ? 'BAND' : 'BO\'SH' }}
        </span>
        <span class="text-sm text-gray-500">{{ $lot->tuman }}, {{ $lot->kocha }}</span>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-4">
            <p class="text-xs text-slate-400 uppercase">Maydon</p>
            <p class="text-2xl font-semibold text-white mt-1">{{ number_format($lot->maydoni ?? 0, 1) }} <span class="text-sm font-normal text-slate-400">m²</span></p>
        </div>
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-4">
            <p class="text-xs text-slate-400 uppercase">Narxi</p>
            <p class="text-xl font-semibold text-white mt-1">{{ number_format($lot->narxi ?? 0, 0, '', ' ') }} <span class="text-sm font-normal text-slate-400">so'm/m²</span></p>
        </div>
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-4">
            <p class="text-xs text-slate-400 uppercase">Oylik</p>
            <p class="text-xl font-semibold text-white mt-1">{{ number_format(($lot->maydoni ?? 0) * ($lot->narxi ?? 0), 0, '', ' ') }}</p>
        </div>
        <div class="bg-slate-800/50 backdrop-blur rounded-xl border border-slate-700/50 p-4">
            <p class="text-xs text-gray-400 uppercase">Shartnomalar</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $lot->contracts->count() }}</p>
        </div>
    </div>

    <!-- Info -->
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b"><h3 class="text-sm font-medium text-gray-900">Ma'lumotlar</h3></div>
            <div class="p-4 space-y-3 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">Lot raqami:</span><span class="text-gray-900">{{ $lot->lot_raqami }}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">Obyekt:</span><span class="text-gray-900">{{ $lot->obyekt_nomi ?? '—' }}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">Tuman:</span><span class="text-gray-900">{{ $lot->tuman ?? '—' }}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">Ko'cha:</span><span class="text-gray-900">{{ $lot->kocha ?? '—' }}</span></div>
                <div class="flex justify-between py-2"><span class="text-gray-500">Uy raqami:</span><span class="text-gray-900">{{ $lot->uy_raqami ?? '—' }}</span></div>
            </div>
        </div>

        @if($activeContract)
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b"><h3 class="text-sm font-medium text-gray-900">Joriy shartnoma</h3></div>
            <div class="p-4">
                <a href="{{ route('registry.contracts.show', $activeContract) }}" class="text-lg font-medium text-gray-900 hover:underline">{{ $activeContract->shartnoma_raqami }}</a>
                <p class="text-sm text-gray-500 mt-1">{{ $activeContract->tenant->name ?? '—' }}</p>
                <div class="flex justify-between mt-4 text-sm">
                    <span class="text-gray-500">Summa:</span>
                    <span class="font-medium text-gray-900">{{ number_format($activeContract->shartnoma_summasi, 0, '', ' ') }} so'm</span>
                </div>
                <div class="flex justify-between mt-2 text-sm">
                    <span class="text-gray-500">Qarz:</span>
                    <span class="font-medium text-gray-900">{{ number_format($activeContract->paymentSchedules->sum('qoldiq_summa'), 0, '', ' ') }} so'm</span>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Contract History -->
    <div class="bg-white border rounded-lg">
        <div class="px-4 py-3 border-b"><h3 class="text-sm font-medium text-gray-900">Shartnomalar tarixi</h3></div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Shartnoma</th>
                    <th class="px-4 py-3 text-left font-medium">Ijarachi</th>
                    <th class="px-4 py-3 text-right font-medium">Summa</th>
                    <th class="px-4 py-3 text-center font-medium">Holat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($lot->contracts as $contract)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><a href="{{ route('registry.contracts.show', $contract) }}" class="text-gray-900 hover:underline">{{ $contract->shartnoma_raqami }}</a></td>
                    <td class="px-4 py-3 text-gray-500">{{ $contract->tenant->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-gray-900">{{ number_format($contract->shartnoma_summasi, 0, '', ' ') }}</td>
                    <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 text-xs rounded {{ $contract->holat === 'faol' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500' }}">{{ $contract->holat === 'faol' ? 'Faol' : 'Yakunlangan' }}</span></td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Shartnomalar yo'q</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
