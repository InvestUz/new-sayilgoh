@extends('layouts.dark')
@section('title', "To'lovlar")
@section('header', "To'lovlar ro'yxati")
@section('subheader', "Barcha to'lovlarni ko'rish va boshqarish")

@php
$totalPayments = $payments->total();
$jamiSumma = \App\Models\Payment::sum('summa');
$buOy = \App\Models\Payment::whereYear('tolov_sanasi', date('Y'))->whereMonth('tolov_sanasi', date('m'))->sum('summa');
@endphp

@section('header-actions')
<a href="{{ route('payments.create') }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Yangi to'lov
</a>
@endsection

@section('content')
<div class="space-y-5">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <div>
                    <p class="stat-label">Jami To'lovlar</p>
                    <p class="stat-value">{{ $totalPayments }}</p>
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
        <div class="stat-card border-l-2 border-l-[#22c55e]">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#22c55e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <div>
                    <p class="stat-label">Bu Oy</p>
                    <p class="stat-value text-[#22c55e]">{{ number_format($buOy / 1000000, 1) }} <span class="text-sm text-[#64748b]">mln</span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <div>
                <h3 class="card-title">To'lovlar ro'yxati</h3>
                <p class="text-xs text-[#64748b] mt-1">{{ $payments->total() }} ta to'lov</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Raqam</th>
                        <th>Sana</th>
                        <th>Shartnoma</th>
                        <th>Ijarachi</th>
                        <th class="text-right">Summa</th>
                        <th class="text-center">Usul</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr>
                        <td>
                            <span class="font-medium text-cyan">{{ $payment->tolov_raqami }}</span>
                        </td>
                        <td>{{ $payment->tolov_sanasi->format('d.m.Y') }}</td>
                        <td>
                            <a href="{{ route('contracts.show', $payment->contract) }}" class="text-cyan hover:underline">{{ $payment->contract->shartnoma_raqami ?? '-' }}</a>
                        </td>
                        <td>{{ $payment->contract->tenant->name ?? '-' }}</td>
                        <td class="text-right">
                            <span class="font-bold text-green">{{ number_format($payment->summa / 1000000, 2) }} mln</span>
                        </td>
                        <td class="text-center">
                            @if($payment->tolov_usuli == 'bank_otkazmasi')
                            <span class="badge badge-info">Bank</span>
                            @elseif($payment->tolov_usuli == 'naqd')
                            <span class="badge badge-success">Naqd</span>
                            @else
                            <span class="badge badge-warning">Karta</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-10 text-[#64748b]">To'lovlar yo'q</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div class="p-4 border-t border-[rgba(56,189,248,0.08)]">
            {{ $payments->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
