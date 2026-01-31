@extends('layouts.dark')
@section('title', 'Shartnoma: ' . $contract->shartnoma_raqami)
@section('header', $contract->shartnoma_raqami)
@section('subheader', 'Shartnoma tafsilotlari')
@section('header-actions')
<a href="{{ route('payments.create', ['contract_id' => $contract->id]) }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    To'lov qo'shish
</a>
@endsection

@php
$months = ['', 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];
$progress = $contract->shartnoma_summasi > 0 ? round(($contract->jami_tolangan / $contract->shartnoma_summasi) * 100) : 0;

function formatSumShow($num) {
    if ($num >= 1000000000) return number_format($num / 1000000000, 2, ',', ' ') . ' <span class="text-sm font-normal text-gray-400">mlrd so\'m</span>';
    if ($num >= 1000000) return number_format($num / 1000000, 2, ',', ' ') . ' <span class="text-sm font-normal text-gray-400">mln so\'m</span>';
    return number_format($num, 0, ',', ' ');
}
@endphp

@section('content')
<div class="space-y-6">
    <!-- Progress Overview -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">TO'LOV JARAYONI</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">{!! formatSumShow($contract->jami_tolangan) !!} <span class="text-lg text-gray-400 font-normal">/ {!! formatSumShow($contract->shartnoma_summasi) !!}</span></p>
            </div>
            <div class="text-right">
                <p class="text-5xl font-bold {{ $progress == 100 ? 'text-green-600' : 'text-blue-600' }}">{{ $progress }}%</p>
                <p class="text-sm text-gray-400">bajarildi</p>
            </div>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-4">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-4 rounded-full transition-all" style="width: {{ $progress }}%"></div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">SHARTNOMA SUMMASI</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">{!! formatSumShow($contract->shartnoma_summasi) !!}</p>
                </div>
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 border-l-4 border-l-green-500 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">TO'LANGAN</p>
                    <p class="text-2xl font-bold text-green-600 mt-2">{!! formatSumShow($contract->jami_tolangan) !!}</p>
                </div>
                <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 border-l-4 border-l-red-500 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">QOLDIQ QARZ</p>
                    <p class="text-2xl font-bold {{ $contract->jami_qarzdorlik > 0 ? 'text-red-600' : 'text-gray-900' }} mt-2">{!! formatSumShow($contract->jami_qarzdorlik) !!}</p>
                </div>
                <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 border-l-4 border-l-amber-500 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">PENYA</p>
                    <p class="text-2xl font-bold {{ $contract->jami_penya > 0 ? 'text-amber-600' : 'text-gray-900' }} mt-2">{!! formatSumShow($contract->jami_penya) !!}</p>
                </div>
                <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Contract Details -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="font-semibold text-gray-900">Shartnoma ma'lumotlari</h2>
            </div>
            <div class="p-5 space-y-1 text-sm">
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">Ijarachi</span>
                    @if($contract->tenant)
                    <a href="{{ route('tenants.show', $contract->tenant) }}" class="font-semibold text-blue-600 hover:text-blue-800">{{ $contract->tenant->name }}</a>
                    @else
                    <span class="text-gray-400">—</span>
                    @endif
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Telefon</span><span class="text-gray-900">{{ $contract->tenant->phone ?? '-' }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">Lot</span>
                    @if($contract->lot)
                    <a href="{{ route('lots.show', $contract->lot) }}" class="font-semibold text-blue-600 hover:text-blue-800">{{ $contract->lot->lot_raqami }}</a>
                    @else
                    <span class="text-gray-400">—</span>
                    @endif
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Obyekt</span><span class="text-gray-900">{{ $contract->lot->obyekt_nomi ?? '-' }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Muddat</span><span class="font-semibold text-gray-900">{{ $contract->shartnoma_muddati }} oy</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Boshlanish</span><span class="text-gray-900">{{ $contract->boshlanish_sanasi->format('d.m.Y') }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Tugash</span><span class="text-gray-900">{{ $contract->tugash_sanasi->format('d.m.Y') }}</span></div>
                <div class="flex justify-between py-3 border-b border-gray-100"><span class="text-gray-500">Oylik to'lov</span><span class="font-bold text-lg text-gray-900">{{ number_format($contract->oylik_tolovi, 0, '.', ' ') }}</span></div>
                <div class="flex justify-between py-3">
                    <span class="text-gray-500">Holat</span>
                    @if($contract->holat == 'faol')
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-green-100 text-green-700 rounded">Faol</span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded">Yakunlangan</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Payment Schedule - Calendar Style -->
        <div class="lg:col-span-2 bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                <h2 class="font-semibold text-gray-900">To'lov grafigi</h2>
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded">{{ $contract->shartnoma_muddati }} oylik</span>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach($contract->paymentSchedules as $schedule)
                    @php
                        $isPaid = $schedule->holat == 'tolangan';
                        $isPartial = $schedule->holat == 'qisman_tolangan';
                        $isOverdue = $schedule->holat == 'tolanmagan';
                        $isPending = $schedule->holat == 'kutilmoqda';
                        $scheduleProgress = $schedule->tolov_summasi > 0 ? round(($schedule->tolangan_summa / $schedule->tolov_summasi) * 100) : 0;
                    @endphp
                    <div class="border rounded-lg p-4 {{ $isPaid ? 'bg-green-50 border-green-200' : ($isOverdue ? 'bg-red-50 border-red-200' : ($isPartial ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200')) }}">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-bold text-gray-900">{{ $schedule->oy_raqami }}-oy</p>
                                <p class="text-xs text-gray-500">{{ $months[$schedule->oy] }} {{ $schedule->yil }}</p>
                            </div>
                            @if($isPaid)
                            <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                            @elseif($isOverdue)
                            <div class="w-6 h-6 bg-red-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </div>
                            @endif
                        </div>
                        <p class="font-bold text-lg text-gray-900">{{ number_format($schedule->tolov_summasi, 0, '.', ' ') }}</p>
                        @if($isPartial || $isPaid)
                        <div class="mt-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-500">To'langan</span>
                                <span class="font-semibold">{{ $scheduleProgress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ $scheduleProgress }}%"></div>
                            </div>
                        </div>
                        @elseif($isOverdue)
                        <span class="inline-flex items-center mt-3 px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded">Muddati o'tgan</span>
                        @else
                        <p class="text-xs text-gray-400 mt-3">{{ $schedule->tolov_sanasi->format('d.m.Y') }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <h2 class="font-semibold text-gray-900">To'lovlar tarixi</h2>
            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded">{{ $contract->payments->count() }} ta</span>
        </div>
        @if($contract->payments->count())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="text-left px-5 py-3 font-medium">Raqam</th>
                        <th class="text-left px-5 py-3 font-medium">Sana</th>
                        <th class="text-center px-5 py-3 font-medium">Usul</th>
                        <th class="text-right px-5 py-3 font-medium">Summa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($contract->payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4 font-semibold text-gray-900">{{ $payment->tolov_raqami }}</td>
                        <td class="px-5 py-4 text-gray-600">{{ $payment->tolov_sanasi->format('d.m.Y') }}</td>
                        <td class="px-5 py-4 text-center">
                            @if($payment->tolov_usuli == 'bank_otkazmasi')
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded">Bank</span>
                            @elseif($payment->tolov_usuli == 'naqd')
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-green-100 text-green-700 rounded">Naqd</span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-purple-100 text-purple-700 rounded">Karta</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right font-bold text-green-600">+{{ number_format($payment->summa, 0, '.', ' ') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-12 text-center text-gray-400">
            <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <p class="font-medium text-gray-500">Hali to'lovlar yo'q</p>
            <a href="{{ route('payments.create', ['contract_id' => $contract->id]) }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Birinchi to'lovni qo'shing
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
