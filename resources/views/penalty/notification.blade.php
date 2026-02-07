@extends('layouts.dark')
@section('title', "Bildirg'inoma - " . $notification->notification_number)
@section('header', "Bildirg'inoma")
@section('subheader', $notification->notification_number)
@section('header-actions')
<a href="{{ route('registry.penalty.download', $notification) }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
    Yuklab olish
</a>
<a href="{{ route('registry.contracts.penalty-calculator', $notification->contract) }}" class="btn btn-secondary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    Kalkulyatorga qaytish
</a>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Notification Preview -->
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <h2 class="card-title">Bildirg'inoma ko'rinishi</h2>
            <span class="badge {{ $notification->status_rangi }}">{{ $notification->status_nomi }}</span>
        </div>
        <div class="card-body">
            <div class="bg-white text-gray-900 rounded-lg p-8 max-w-2xl mx-auto">
                <!-- Header -->
                <div class="text-center border-b-2 border-gray-900 pb-4 mb-6">
                    <h1 class="text-xl font-bold uppercase">BILDIRG'INOMA</h1>
                    <p class="text-gray-600">Penya hisoblash to'g'risida</p>
                    <p class="mt-2 font-semibold">№ {{ $notification->notification_number }} | {{ $notification->notification_date->format('d.m.Y') }}</p>
                </div>

                <!-- Contract Info -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-300 pb-1 mb-3">Shartnoma ma'lumotlari</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <p class="text-gray-600">Shartnoma raqami:</p>
                        <p class="font-semibold">{{ $notification->contract_number }}</p>
                        <p class="text-gray-600">Ijarachi:</p>
                        <p class="font-semibold">{{ $notification->tenant_name }}</p>
                        <p class="text-gray-600">Lot raqami:</p>
                        <p class="font-semibold">{{ $notification->lot_number }}</p>
                    </div>
                </div>

                <!-- Delay Info -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-300 pb-1 mb-3">Kechikish ma'lumotlari</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <p class="text-gray-600">To'lov muddati:</p>
                        <p class="font-semibold">{{ $notification->due_date->format('d.m.Y') }}</p>
                        <p class="text-gray-600">Hisoblash sanasi:</p>
                        <p class="font-semibold">{{ $notification->payment_date->format('d.m.Y') }}</p>
                        <p class="text-gray-600">Kechikish:</p>
                        <p class="font-bold text-red-600">{{ $notification->overdue_days }} kun</p>
                        <p class="text-gray-600">Qarz summasi:</p>
                        <p class="font-semibold">{{ number_format($notification->overdue_amount, 0, ',', ' ') }} UZS</p>
                    </div>
                </div>

                <!-- Calculation -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-300 pb-1 mb-3">Penya hisoblash</h3>
                    <div class="bg-gray-100 border border-gray-300 p-4 text-center font-mono text-lg">
                        {{ $notification->formula_text }}
                    </div>
                    <div class="mt-4 text-center">
                        <p class="text-gray-600">Yakuniy penya:</p>
                        <p class="text-3xl font-bold text-red-600">{{ number_format($notification->final_penalty, 0, ',', ' ') }} UZS</p>
                    </div>
                </div>

                <!-- Legal Basis -->
                <div class="text-sm text-gray-600 border-t border-gray-300 pt-4 mt-6">
                    <p><strong>Huquqiy asos:</strong> {{ $notification->legal_basis }}</p>
                    <p class="mt-2">Penya stavkasi: kuniga 0,4% (yillik 146%)</p>
                    <p>Maksimal chegara: qarz summasining 50%</p>
                    @if($notification->cap_applied)
                    <p class="text-amber-600 mt-2">* 50% chegara qo'llandi</p>
                    @endif
                </div>

                <!-- Signatures -->
                <div class="flex justify-between mt-12 text-center">
                    <div class="w-1/3">
                        <div class="border-t border-gray-900 pt-2">Ijara beruvchi</div>
                    </div>
                    <div class="w-1/3">
                        <div class="border-t border-gray-900 pt-2">Ijarachi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Validation -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Tizim tekshiruvi</h2>
        </div>
        <div class="card-body">
            <div class="rounded-lg p-4 {{ $notification->system_match ? 'bg-green-500/10 border border-green-500/30' : 'bg-red-500/10 border border-red-500/30' }}">
                <div class="flex items-center gap-3">
                    @if($notification->system_match)
                    <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-green-400 font-semibold text-lg">Tizim bilan mos keladi</p>
                        <p class="text-gray-400 text-sm">Kalkulyator natijasi tizim ma'lumotlari bilan bir xil</p>
                    </div>
                    @else
                    <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="text-red-400 font-semibold text-lg">OGOHLANTIRISH: Nomuvofiqlik aniqlandi!</p>
                        <p class="text-gray-400 text-sm">{{ $notification->mismatch_reason }}</p>
                    </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Kalkulyator natijasi</p>
                        <p class="text-xl font-bold text-white">{{ number_format($notification->final_penalty, 0, ',', ' ') }} UZS</p>
                    </div>
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Tizimdagi qiymat</p>
                        <p class="text-xl font-bold text-white">{{ number_format($notification->system_penalty ?? 0, 0, ',', ' ') }} UZS</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Info -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Audit ma'lumotlari</h2>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-400">Yaratilgan sana</p>
                    <p class="text-white font-semibold">{{ $notification->created_at->format('d.m.Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Yaratgan</p>
                    <p class="text-white font-semibold">{{ $notification->generated_by ?? 'Tizim' }}</p>
                </div>
                <div>
                    <p class="text-gray-400">PDF yaratilgan</p>
                    <p class="text-white font-semibold">{{ $notification->pdf_generated_at ? $notification->pdf_generated_at->format('d.m.Y H:i') : '-' }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Holat</p>
                    <span class="badge {{ $notification->status_rangi }}">{{ $notification->status_nomi }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
