@extends('layouts.dark')
@section('title', $contract->shartnoma_raqami)
@section('breadcrumb')
<nav class="flex items-center gap-2 text-sm">
    <a href="{{ route('registry', ['tab' => 'contracts']) }}" class="text-gray-400 hover:text-gray-600">Shartnomalar</a>
    <span class="text-gray-300">/</span>
    <span class="text-gray-900">{{ $contract->shartnoma_raqami }}</span>
</nav>
@endsection
@section('header-actions')
<a href="{{ route('registry.contracts.penalty-calculator', $contract) }}" class="px-4 py-2 border border-amber-500 text-amber-600 rounded text-sm hover:bg-amber-50 flex items-center gap-2" title="Penya kalkulyatori">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
    Penya
</a>
<button onclick="window.print()" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
</button>
<a href="{{ route('registry.contracts.edit', $contract) }}" class="px-4 py-2 bg-gray-900 text-white rounded text-sm hover:bg-gray-800">Tahrirlash</a>
@endsection

@section('content')
<div x-data="contractDetail()" x-init="init()">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
        <span class="px-3 py-1 text-xs font-medium rounded {{ $contract->holat === 'faol' ? 'bg-gray-900 text-white' : 'bg-gray-200 text-gray-600' }}">
            {{ $contract->holat === 'faol' ? 'FAOL' : 'YAKUNLANGAN' }}
        </span>
        <span class="text-sm text-gray-500">
            {{ \Carbon\Carbon::parse($contract->boshlanish_sanasi)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($contract->tugash_sanasi)->format('d.m.Y') }}
        </span>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white border rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Shartnoma summasi</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($stats['jami_summa'], 0, '', ' ') }}</p>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">To'langan</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($stats['tolangan'], 0, '', ' ') }}</p>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Qoldiq</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($stats['qoldiq'], 0, '', ' ') }}</p>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Penya</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($stats['penya'], 0, '', ' ') }}</p>
        </div>
    </div>

    <!-- Progress -->
    @php $paidPercent = $stats['jami_summa'] > 0 ? round(($stats['tolangan'] / $stats['jami_summa']) * 100, 1) : 0; @endphp
    <div class="bg-white border rounded-lg p-4 mb-6">
        <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-600">To'lov jarayoni</span>
            <span class="font-medium text-gray-900">{{ $paidPercent }}%</span>
        </div>
        <div class="h-2 bg-gray-100 rounded-full">
            <div class="h-2 bg-gray-900 rounded-full" style="width: {{ $paidPercent }}%"></div>
        </div>
    </div>

    <!-- Two Column -->
    <div class="grid lg:grid-cols-3 gap-6 mb-6">
        <!-- Tenant -->
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b">
                <h3 class="text-sm font-medium text-gray-900">Ijarachi</h3>
            </div>
            <div class="p-4 space-y-3 text-sm">
                @if($contract->tenant)
                <div class="flex justify-between"><span class="text-gray-500">Nomi:</span><a href="{{ route('registry.tenants.show', $contract->tenant) }}" class="text-gray-900 hover:underline">{{ $contract->tenant->name }}</a></div>
                <div class="flex justify-between"><span class="text-gray-500">INN:</span><span class="text-gray-900">{{ $contract->tenant->inn ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Direktor:</span><span class="text-gray-900">{{ $contract->tenant->direktor_ismi ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Telefon:</span><span class="text-gray-900">{{ $contract->tenant->telefon ?? '—' }}</span></div>
                @else
                <p class="text-gray-400 text-center py-4">Biriktirilmagan</p>
                @endif
            </div>
        </div>

        <!-- Lot -->
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b">
                <h3 class="text-sm font-medium text-gray-900">Lot/Obyekt</h3>
            </div>
            <div class="p-4 space-y-3 text-sm">
                @if($contract->lot)
                <div class="flex justify-between"><span class="text-gray-500">Lot:</span><a href="{{ route('registry.lots.show', $contract->lot) }}" class="text-gray-900 hover:underline">{{ $contract->lot->lot_raqami }}</a></div>
                <div class="flex justify-between"><span class="text-gray-500">Tuman:</span><span class="text-gray-900">{{ $contract->lot->tuman ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Ko'cha:</span><span class="text-gray-900">{{ $contract->lot->kocha ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Maydon:</span><span class="text-gray-900">{{ number_format($contract->lot->maydoni ?? 0, 1) }} m²</span></div>
                @else
                <p class="text-gray-400 text-center py-4">Biriktirilmagan</p>
                @endif
            </div>
        </div>

        <!-- Chart -->
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b">
                <h3 class="text-sm font-medium text-gray-900">To'lov taqqoslash</h3>
            </div>
            <div class="p-4">
                <canvas id="paymentChart" height="160"></canvas>
                <div class="flex justify-center gap-6 mt-3 text-xs text-gray-500">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-gray-900 rounded-full"></span> Kutilgan</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 bg-gray-400 rounded-full"></span> Haqiqiy</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Schedule -->
    <div class="bg-white border rounded-lg mb-6">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <div class="flex items-center gap-4">
                <h3 class="text-sm font-medium text-gray-900">To'lov jadvali</h3>
                <span class="text-xs text-gray-400">(Har oyning 10-sanasi default)</span>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showAddScheduleModal = true" class="px-3 py-1.5 border border-gray-200 text-gray-600 rounded text-xs hover:bg-gray-50">+ Grafik</button>
                <button @click="showPaymentModal = true" class="px-3 py-1.5 bg-gray-900 text-white rounded text-xs hover:bg-gray-800">+ To'lov</button>
            </div>
        </div>

        <!-- Penya formula info -->
        @php
            // Find first overdue schedule for example
            $exampleSchedule = $contract->paymentSchedules->first(function($s) {
                return $s->qoldiq_summa > 0 && \Carbon\Carbon::parse($s->oxirgi_muddat)->isPast();
            });
            $exampleKun = 0;
            $examplePenya = 0;
            if ($exampleSchedule) {
                $exampleDetails = $exampleSchedule->getPenaltyDetails();
                $exampleKun = $exampleDetails['overdue_days'];
                $examplePenya = $exampleDetails['calculated_penalty'];
            }
        @endphp
        <div class="px-4 py-2 bg-amber-50 border-b text-xs text-amber-700">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><b>Penya formulasi:</b> Qoldiq summa × 0.4% × kechikish kunlari (max 50%)</span>
            </div>
            @if($exampleSchedule)
            <div class="mt-2 ml-6 text-amber-600 bg-amber-100/50 px-3 py-2 rounded inline-block">
                <b>Misol ({{ $exampleSchedule->oy_raqami }}-oy):</b> {{ number_format($exampleSchedule->qoldiq_summa, 0, '', ' ') }} so'm × 0.4% × {{ $exampleKun }} kun = <b>{{ number_format($examplePenya, 0, '', ' ') }} so'm</b>
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Oy</th>
                        <th class="px-4 py-3 text-left font-medium">Grafik sanasi</th>
                        <th class="px-4 py-3 text-left font-medium">Oxirgi muddat</th>
                        <th class="px-4 py-3 text-right font-medium">Summa</th>
                        <th class="px-4 py-3 text-right font-medium">To'langan</th>
                        <th class="px-4 py-3 text-right font-medium">Qoldiq</th>
                        <th class="px-4 py-3 text-center font-medium">Penya</th>
                        <th class="px-4 py-3 text-center font-medium">Holat</th>
                        <th class="px-4 py-3 text-center font-medium w-20">Amal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($contract->paymentSchedules as $schedule)
                    @php
                        // Логика отображения пени:
                        // - Если есть оплаченная пеня (tolangan_penya > 0) - показываем сохранённые данные
                        // - Если пеня не оплачена - рассчитываем динамически на текущую дату

                        $kechikish = $schedule->kechikish_kunlari;
                        $calculatedPenya = $schedule->penya_summasi;
                        $qoldiqPenya = $calculatedPenya - $schedule->tolangan_penya;

                        // Если месяц просрочен и пеня ещё не оплачена - пересчитываем на сегодня
                        if ($schedule->qoldiq_summa > 0 && \Carbon\Carbon::parse($schedule->oxirgi_muddat)->isPast()) {
                            $penaltyDetails = $schedule->getPenaltyDetails();
                            $kechikish = $penaltyDetails['overdue_days'];
                            $calculatedPenya = $penaltyDetails['calculated_penalty'];
                            $qoldiqPenya = $calculatedPenya - $schedule->tolangan_penya;
                        }

                        $penyaInfo = number_format($schedule->qoldiq_summa, 0, '', ' ') . ' × 0.4% × ' . $kechikish . ' kun';
                    @endphp
                    <tr class="hover:bg-gray-50 group" x-data="{ editing: false }">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $schedule->oy_raqami }}-oy</td>
                        <td class="px-4 py-3">
                            <template x-if="!editing">
                                <span class="text-gray-600 cursor-pointer hover:text-blue-600" @click="editing = true">{{ \Carbon\Carbon::parse($schedule->tolov_sanasi)->format('d.m.Y') }}</span>
                            </template>
                            <template x-if="editing">
                                <input type="date"
                                    value="{{ \Carbon\Carbon::parse($schedule->tolov_sanasi)->format('Y-m-d') }}"
                                    class="border rounded px-2 py-1 text-xs w-32"
                                    @change="updateSchedule({{ $schedule->id }}, 'tolov_sanasi', $event.target.value); editing = false"
                                    @blur="editing = false">
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <template x-if="!editing">
                                <span class="text-gray-500 cursor-pointer hover:text-blue-600" @click="editing = true">{{ \Carbon\Carbon::parse($schedule->oxirgi_muddat)->format('d.m.Y') }}</span>
                            </template>
                            <template x-if="editing">
                                <input type="date"
                                    value="{{ \Carbon\Carbon::parse($schedule->oxirgi_muddat)->format('Y-m-d') }}"
                                    class="border rounded px-2 py-1 text-xs w-32"
                                    @change="updateSchedule({{ $schedule->id }}, 'oxirgi_muddat', $event.target.value); editing = false"
                                    @blur="editing = false">
                            </template>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-900">{{ number_format($schedule->tolov_summasi, 0, '', ' ') }}</td>
                        <td class="px-4 py-3 text-right text-gray-900">{{ number_format($schedule->tolangan_summa, 0, '', ' ') }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $schedule->qoldiq_summa > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($schedule->qoldiq_summa, 0, '', ' ') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($kechikish > 0)
                            <div class="group/penya relative">
                                <span class="text-red-600 font-medium cursor-help">{{ number_format($calculatedPenya, 0, '', ' ') }}</span>
                                <div class="hidden group-hover/penya:block absolute z-10 bg-gray-900 text-white text-xs rounded px-3 py-2 -translate-x-1/2 left-1/2 top-full mt-1 w-48 shadow-lg">
                                    <div class="font-medium mb-1">{{ $kechikish }} kun kechikish</div>
                                    <div class="text-gray-300">{{ $penyaInfo }}</div>
                                </div>
                            </div>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($schedule->qoldiq_summa <= 0)
                            <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded">To'landi</span>
                            @elseif($kechikish > 0)
                            <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded">{{ $kechikish }} kun</span>
                            @elseif(\Carbon\Carbon::parse($schedule->tolov_sanasi)->lt(now()))
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded">Muddat</span>
                            @else
                            <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">Kutilmoqda</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($schedule->tolangan_summa == 0)
                            <button @click="deleteSchedule({{ $schedule->id }})" class="text-gray-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Ma'lumot yo'q</td></tr>
                    @endforelse
                </tbody>
                @if($contract->paymentSchedules->count() > 0)
                <tfoot class="bg-gray-50 font-medium">
                    @php
                        // Динамический расчет общего штрафа
                        $totalPenya = $contract->paymentSchedules->sum(function($schedule) {
                            return $schedule->getPenaltyDetails()['calculated_penalty'];
                        });
                    @endphp
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-gray-600">Jami:</td>
                        <td class="px-4 py-3 text-right">{{ number_format($contract->paymentSchedules->sum('tolov_summasi'), 0, '', ' ') }}</td>
                        <td class="px-4 py-3 text-right text-green-600">{{ number_format($contract->paymentSchedules->sum('tolangan_summa'), 0, '', ' ') }}</td>
                        <td class="px-4 py-3 text-right text-red-600">{{ number_format($contract->paymentSchedules->sum('qoldiq_summa'), 0, '', ' ') }}</td>
                        <td class="px-4 py-3 text-center text-red-600">{{ number_format($totalPenya, 0, '', ' ') }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- Penalty Payments Table -->
    <div class="bg-white border rounded-lg mb-6">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="text-sm font-medium text-gray-900">Penya to'lovlari</h3>
            <button @click="showPenaltyPaymentModal = true" class="px-3 py-1.5 bg-amber-600 text-white rounded text-xs hover:bg-amber-700">+ Penya to'lovi</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Sana</th>
                        <th class="px-4 py-3 text-left font-medium">Oy</th>
                        <th class="px-4 py-3 text-right font-medium">Penya Summasi</th>
                        <th class="px-4 py-3 text-right font-medium">To'langan</th>
                        <th class="px-4 py-3 text-right font-medium">Qoldiq</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $penyaPayments = $contract->paymentSchedules
                            ->filter(fn($s) => $s->penya_summasi > 0)
                            ->sortBy('oy_raqami');
                    @endphp
                    @forelse($penyaPayments as $schedule)
                    @php
                        $qoldiqPenya = $schedule->penya_summasi - $schedule->tolangan_penya;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-600">{{ \Carbon\Carbon::parse($schedule->oxirgi_muddat)->format('d.m.Y') }}</td>
                        <td class="px-4 py-3">{{ $schedule->oy_raqami }}-oy ({{ $schedule->yil }})</td>
                        <td class="px-4 py-3 text-right font-medium text-red-600">{{ number_format($schedule->penya_summasi, 0, '', ' ') }}</td>
                        <td class="px-4 py-3 text-right text-green-600">{{ number_format($schedule->tolangan_penya, 0, '', ' ') }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $qoldiqPenya > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($qoldiqPenya, 0, '', ' ') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Penya yo'q</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="bg-white border rounded-lg">
        <div class="px-4 py-3 border-b">
            <h3 class="text-sm font-medium text-gray-900">So'nggi to'lovlar</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($contract->payments->take(5) as $payment)
            <div class="px-4 py-3">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <p class="font-medium text-gray-900">{{ number_format($payment->summa, 0, '', ' ') }} so'm</p>
                        <p class="text-xs text-gray-400">{{ $payment->tolov_usuli_nomi ?? 'Naqd' }}</p>
                    </div>
                    <span class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($payment->tolov_sanasi)->format('d.m.Y') }}</span>
                </div>
                <!-- Breakdown -->
                @if($payment->penya_uchun > 0 || $payment->asosiy_qarz_uchun > 0 || $payment->avans > 0)
                <div class="mt-2 pt-2 border-t border-gray-200 space-y-1 text-xs">
                    @if($payment->penya_uchun > 0)
                    <div class="flex justify-between text-red-600">
                        <span>Penya uchun:</span>
                        <span>{{ number_format($payment->penya_uchun, 0, '', ' ') }} so'm</span>
                    </div>
                    @endif
                    @if($payment->asosiy_qarz_uchun > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Asosiy qarz uchun:</span>
                        <span>{{ number_format($payment->asosiy_qarz_uchun, 0, '', ' ') }} so'm</span>
                    </div>
                    @endif
                    @if($payment->avans > 0)
                    <div class="flex justify-between text-blue-600">
                        <span>Avans:</span>
                        <span>{{ number_format($payment->avans, 0, '', ' ') }} so'm</span>
                    </div>
                    @endif
                </div>
                @endif
            </div>
            @empty
            <div class="px-4 py-8 text-center text-gray-400">To'lovlar yo'q</div>
            @endforelse
        </div>
    </div>

    <!-- Payment Modal -->
    <div x-show="showPaymentModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showPaymentModal=false">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="font-medium text-gray-900">To'lov qilish</h3>
                <button @click="showPaymentModal=false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form @submit.prevent="submitPayment" class="p-4 space-y-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Summa</label>
                    <input type="number" step="any" x-model="paymentForm.summa" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Sana</label>
                    <input type="date" x-model="paymentForm.tolov_sanasi" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Turi</label>
                    <select x-model="paymentForm.tolov_turi" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="naqd">Naqd</option>
                        <option value="plastik">Plastik</option>
                        <option value="bank">Bank o'tkazmasi</option>
                    </select>
                </div>
                <button type="submit" class="w-full py-2 bg-gray-900 text-white rounded text-sm hover:bg-gray-800" :disabled="loading">
                    <span x-show="!loading">Saqlash</span>
                    <span x-show="loading">...</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div x-show="showAddScheduleModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showAddScheduleModal=false">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="font-medium text-gray-900">Yangi grafik qo'shish</h3>
                <button @click="showAddScheduleModal=false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form @submit.prevent="submitSchedule" class="p-4 space-y-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">To'lov sanasi</label>
                    <input type="date" x-model="scheduleForm.tolov_sanasi" class="w-full border rounded px-3 py-2 text-sm" required>
                    <p class="text-xs text-gray-400 mt-1">Default: har oyning 10-sanasi</p>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Summa</label>
                    <input type="number" step="any" x-model="scheduleForm.tolov_summasi" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Oxirgi muddat (ixtiyoriy)</label>
                    <input type="date" x-model="scheduleForm.oxirgi_muddat" class="w-full border rounded px-3 py-2 text-sm">
                    <p class="text-xs text-gray-400 mt-1">Bo'sh qoldirilsa to'lov sanasidan 10 kun keyin</p>
                </div>
                <button type="submit" class="w-full py-2 bg-gray-900 text-white rounded text-sm hover:bg-gray-800" :disabled="loading">
                    <span x-show="!loading">Qo'shish</span>
                    <span x-show="loading">...</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Penalty Payment Modal -->
    <div x-show="showPenaltyPaymentModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showPenaltyPaymentModal=false">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="font-medium text-gray-900">Penya to'lovi qilish</h3>
                <button @click="showPenaltyPaymentModal=false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form @submit.prevent="submitPenaltyPayment" class="p-4 space-y-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Oy tanlang</label>
                    <select x-model="penaltyPaymentForm.payment_schedule_id" class="w-full border rounded px-3 py-2 text-sm" required>
                        <option value="">-- Tanlang --</option>
                        @php
                            $penyaSchedules = $contract->paymentSchedules
                                ->filter(fn($s) => $s->penya_summasi > 0)
                                ->sortBy('oy_raqami');
                        @endphp
                        @foreach($penyaSchedules as $s)
                        @php
                            $qoldiqPenya = $s->penya_summasi - $s->tolangan_penya;
                        @endphp
                        @if($qoldiqPenya > 0)
                        <option value="{{ $s->id }}">{{ $s->oy_raqami }}-oy ({{ $s->yil }}) - Qoldiq: {{ number_format($qoldiqPenya, 0, '', ' ') }} so'm</option>
                        @endif
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Summa</label>
                    <input type="number" step="any" x-model="penaltyPaymentForm.summa" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Sana</label>
                    <input type="date" x-model="penaltyPaymentForm.tolov_sanasi" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Usuli</label>
                    <select x-model="penaltyPaymentForm.tolov_usuli" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="naqd">Naqd</option>
                        <option value="bank_otkazmasi">Bank o'tkazmasi</option>
                        <option value="karta">Plastik karta</option>
                    </select>
                </div>
                <button type="submit" class="w-full py-2 bg-amber-600 text-white rounded text-sm hover:bg-amber-700" :disabled="loading">
                    <span x-show="!loading">Saqlash</span>
                    <span x-show="loading">...</span>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function contractDetail() {
    return {
        showPaymentModal: false,
        showAddScheduleModal: false,
        showPenaltyPaymentModal: false,
        loading: false,
        paymentForm: { contract_id: {{ $contract->id }}, summa: '', tolov_sanasi: new Date().toISOString().split('T')[0], tolov_turi: 'naqd' },
        scheduleForm: { tolov_sanasi: this.getDefault10thDate(), tolov_summasi: '', oxirgi_muddat: '' },
        penaltyPaymentForm: { contract_id: {{ $contract->id }}, payment_schedule_id: '', summa: '', tolov_sanasi: new Date().toISOString().split('T')[0], tolov_usuli: 'naqd' },
        chartData: @json($contract->paymentSchedules->map(fn($s) => ['month' => $s->oy_raqami, 'expected' => $s->tolov_summasi, 'actual' => $s->tolangan_summa])),

        init() {
            this.renderChart();
            this.scheduleForm.tolov_sanasi = this.getDefault10thDate();
        },

        getDefault10thDate() {
            const now = new Date();
            const next = new Date(now.getFullYear(), now.getMonth() + 1, 10);
            return next.toISOString().split('T')[0];
        },

        renderChart() {
            const ctx = document.getElementById('paymentChart')?.getContext('2d');
            if (!ctx) return;
            let expCum = 0, actCum = 0;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.chartData.map(d => d.month + '-oy'),
                    datasets: [
                        { label: 'Kutilgan', data: this.chartData.map(d => { expCum += d.expected; return expCum; }), borderColor: '#111827', backgroundColor: 'transparent', tension: 0.3, borderWidth: 2 },
                        { label: 'Haqiqiy', data: this.chartData.map(d => { actCum += d.actual; return actCum; }), borderColor: '#9CA3AF', backgroundColor: 'transparent', tension: 0.3, borderWidth: 2 }
                    ]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => (v/1000000).toFixed(1) + 'M' } } } }
            });
        },

        async submitPayment() {
            this.loading = true;
            try {
                const res = await fetch('/api/payments', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(this.paymentForm) });
                if (res.ok) { this.showPaymentModal = false; window.location.reload(); } else { alert('Xatolik'); }
            } catch (e) { alert('Xatolik'); }
            this.loading = false;
        },

        async submitPenaltyPayment() {
            this.loading = true;
            try {
                const res = await fetch('/api/penalty-payments', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(this.penaltyPaymentForm)
                });
                if (res.ok) {
                    this.showPenaltyPaymentModal = false;
                    window.location.reload();
                } else {
                    const err = await res.json();
                    alert(err.message || 'Xatolik');
                }
            } catch (e) { alert('Xatolik: ' + e.message); }
            this.loading = false;
        },

        async submitSchedule() {
            this.loading = true;
            try {
                const res = await fetch('/api/payment-schedules/contract/{{ $contract->id }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(this.scheduleForm)
                });
                if (res.ok) {
                    this.showAddScheduleModal = false;
                    window.location.reload();
                } else {
                    const err = await res.json();
                    alert(err.message || 'Xatolik');
                }
            } catch (e) { alert('Xatolik'); }
            this.loading = false;
        },

        async updateSchedule(id, field, value) {
            try {
                const data = {};
                data[field] = value;
                const res = await fetch(`/api/payment-schedules/${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(data)
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const err = await res.json();
                    alert(err.message || 'Xatolik');
                }
            } catch (e) { alert('Xatolik'); }
        },

        async deleteSchedule(id) {
            if (!confirm('Ushbu grafikni o\'chirishni tasdiqlaysizmi?')) return;
            try {
                const res = await fetch(`/api/payment-schedules/${id}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const err = await res.json();
                    alert(err.message || 'To\'lov qilingan grafikni o\'chirib bo\'lmaydi');
                }
            } catch (e) { alert('Xatolik'); }
        }
    }
}
</script>
@endsection
