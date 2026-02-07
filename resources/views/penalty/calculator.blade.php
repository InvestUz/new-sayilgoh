@extends('layouts.dark')
@section('title', 'Penya Kalkulyatori - ' . $contract->shartnoma_raqami)
@section('header', 'Penya Kalkulyatori')
@section('subheader', 'Shartnoma: ' . $contract->shartnoma_raqami)
@section('header-actions')
<a href="{{ route('registry.contracts.show', $contract) }}" class="btn btn-secondary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    Shartnomaga qaytish
</a>
@endsection

@php
$months = ['', 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];
@endphp

@section('content')
<div x-data="penaltyCalculator()" x-init="init()" class="space-y-6">
    <!-- Contract Info -->
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <h2 class="card-title">Shartnoma ma'lumotlari</h2>
            <span class="badge {{ $contract->holat == 'faol' ? 'badge-success' : 'badge-warning' }}">
                {{ $contract->holat == 'faol' ? 'Faol' : 'Yakunlangan' }}
            </span>
        </div>
        <div class="card-body grid md:grid-cols-4 gap-4">
            <div>
                <p class="form-label">Ijarachi</p>
                <p class="text-white font-semibold">{{ $contract->tenant?->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="form-label">Lot</p>
                <p class="text-white font-semibold">{{ $contract->lot?->lot_raqami ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="form-label">Oylik to'lov</p>
                <p class="text-cyan-400 font-bold">{{ number_format($contract->oylik_tolovi, 0, ',', ' ') }} UZS</p>
            </div>
            <div>
                <p class="form-label">Jami qarz</p>
                <p class="text-red-400 font-bold">{{ number_format($contract->jami_qarzdorlik, 0, ',', ' ') }} UZS</p>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Calculator Panel -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Penya hisoblash
                </h2>
            </div>
            <div class="card-body space-y-4">
                <!-- Quick Select from Overdue Schedules -->
                @if($overdueSchedules->count() > 0)
                <div>
                    <label class="form-label">Tezkor tanlash (muddati o'tganlar)</label>
                    <select x-model="selectedScheduleId" @change="loadScheduleDetails()" class="form-input form-select">
                        <option value="">Qo'lda kiritish...</option>
                        @foreach($overdueSchedules as $schedule)
                        <option value="{{ $schedule->id }}">
                            {{ $schedule->oy_raqami }}-oy ({{ $months[$schedule->oy] }} {{ $schedule->yil }}) - {{ number_format($schedule->qoldiq_summa, 0, ',', ' ') }} UZS
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <!-- Inputs -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Qarz summasi (UZS)</label>
                        <input type="text" x-model="overdueAmountFormatted" @input="formatAmount()" class="form-input" placeholder="1 000 000">
                    </div>
                    <div>
                        <label class="form-label">Penya stavkasi</label>
                        <input type="text" value="0.4% (kunlik)" class="form-input bg-gray-700 cursor-not-allowed" disabled>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">To'lov muddati</label>
                        <input type="date" x-model="dueDate" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Hisoblash sanasi</label>
                        <input type="date" x-model="paymentDate" class="form-input">
                    </div>
                </div>

                <!-- Calculate Button -->
                <button @click="calculate()" class="btn btn-primary w-full" :disabled="calculating">
                    <span x-show="!calculating">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Hisoblash
                    </span>
                    <span x-show="calculating">Hisoblanmoqda...</span>
                </button>

                <!-- Legal Basis -->
                <div class="bg-gray-800/50 rounded-lg p-4 text-sm">
                    <p class="text-amber-400 font-semibold mb-2">Huquqiy asos:</p>
                    <p class="text-gray-400">Shartnomaning 8.2-bandi asosida:</p>
                    <ul class="text-gray-400 list-disc list-inside mt-1 space-y-1">
                        <li>Penya stavkasi: kuniga <span class="text-cyan-400">0.4%</span></li>
                        <li>Maksimal chegara: qarz summasining <span class="text-cyan-400">50%</span></li>
                        <li>Penya faqat muddat o'tganda hisoblanadi</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Result Panel -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Natija
                </h2>
            </div>
            <div class="card-body">
                <template x-if="!result">
                    <div class="text-center py-12 text-gray-500">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <p>Ma'lumotlarni kiriting va "Hisoblash" tugmasini bosing</p>
                    </div>
                </template>

                <template x-if="result">
                    <div class="space-y-4">
                        <!-- Display Example -->
                        <div class="bg-gray-800 rounded-lg p-4 font-mono text-sm">
                            <p class="text-gray-400">To'lov muddati: <span class="text-white" x-text="formatDate(dueDate)"></span></p>
                            <p class="text-gray-400">Hisoblash sanasi: <span class="text-white" x-text="formatDate(paymentDate)"></span></p>
                            <p class="text-gray-400 mt-2">Kechikish: <span class="text-red-400 font-bold" x-text="result.calculation.overdue_days + ' kun'"></span></p>
                            <div class="mt-3 pt-3 border-t border-gray-700">
                                <p class="text-cyan-400" x-text="result.calculation.formula_text"></p>
                            </div>
                        </div>

                        <!-- Result Details -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Kechikish kunlari</p>
                                <p class="text-2xl font-bold" :class="result.calculation.overdue_days > 0 ? 'text-red-400' : 'text-green-400'" x-text="result.calculation.overdue_days"></p>
                            </div>
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Penya stavkasi</p>
                                <p class="text-2xl font-bold text-cyan-400">0.4%</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Hisoblangan penya</p>
                                <p class="text-xl font-bold text-white" x-text="result.display.calculated_penalty"></p>
                            </div>
                            <div class="bg-gray-800/50 rounded-lg p-4">
                                <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">50% chegara</p>
                                <p class="text-xl font-bold" :class="result.calculation.cap_applied ? 'text-amber-400' : 'text-gray-400'" x-text="result.display.cap_applied"></p>
                            </div>
                        </div>

                        <!-- Final Penalty -->
                        <div class="bg-gradient-to-r from-red-500/20 to-orange-500/20 rounded-lg p-6 text-center border border-red-500/30">
                            <p class="text-gray-400 text-sm uppercase tracking-wide mb-2">Yakuniy penya summasi</p>
                            <p class="text-4xl font-bold text-red-400" x-text="result.display.final_penalty"></p>
                        </div>

                        <!-- System Validation -->
                        <template x-if="result.validation">
                            <div class="rounded-lg p-4" :class="result.validation.valid ? 'bg-green-500/10 border border-green-500/30' : 'bg-red-500/10 border border-red-500/30'">
                                <div class="flex items-center gap-2 mb-2">
                                    <template x-if="result.validation.valid">
                                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </template>
                                    <template x-if="!result.validation.valid">
                                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    </template>
                                    <p class="font-semibold" :class="result.validation.valid ? 'text-green-400' : 'text-red-400'" x-text="result.validation.valid ? 'Tizim bilan mos keladi' : 'OGOHLANTIRISH: Tizim bilan mos kelmaydi!'"></p>
                                </div>
                                <template x-if="result.validation.warnings && result.validation.warnings.length > 0">
                                    <ul class="text-sm text-gray-400 list-disc list-inside">
                                        <template x-for="warning in result.validation.warnings">
                                            <li x-text="warning.message"></li>
                                        </template>
                                    </ul>
                                </template>
                            </div>
                        </template>

                        <!-- Generate Notification Button -->
                        <div class="flex gap-3">
                            <button @click="generateNotification()" class="btn btn-success flex-1" :disabled="generatingNotification || result.calculation.overdue_days === 0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span x-text="generatingNotification ? 'Yaratilmoqda...' : 'Bildirg\'inoma yaratish'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Notifications History -->
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <h2 class="card-title flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Bildirg'inomalar tarixi
            </h2>
            <span class="badge badge-success">{{ $notifications->count() }} ta</span>
        </div>
        <div class="card-body">
            @if($notifications->count() > 0)
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Raqam</th>
                        <th>Sana</th>
                        <th>Kechikish</th>
                        <th>Penya</th>
                        <th>Holat</th>
                        <th>Tizim</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($notifications as $notification)
                    <tr>
                        <td class="text-cyan">{{ $notification->notification_number }}</td>
                        <td>{{ $notification->notification_date->format('d.m.Y') }}</td>
                        <td class="text-red">{{ $notification->overdue_days }} kun</td>
                        <td class="text-amber font-bold">{{ number_format($notification->final_penalty, 0, ',', ' ') }} UZS</td>
                        <td><span class="badge {{ $notification->status_rangi }}">{{ $notification->status_nomi }}</span></td>
                        <td>
                            @if($notification->system_match)
                            <span class="text-green">✓ Mos</span>
                            @else
                            <span class="text-red">✗ Nomuvofiq</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('registry.penalty.download', $notification) }}" class="btn btn-secondary btn-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="text-center py-8 text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p>Hali bildirg'inomalar yaratilmagan</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function penaltyCalculator() {
    return {
        contractId: {{ $contract->id }},
        selectedScheduleId: '',
        overdueAmount: {{ $contract->oylik_tolovi }},
        overdueAmountFormatted: '{{ number_format($contract->oylik_tolovi, 0, " ", " ") }}',
        dueDate: '{{ now()->format("Y-m-d") }}',
        paymentDate: '{{ now()->format("Y-m-d") }}',
        calculating: false,
        generatingNotification: false,
        result: null,

        init() {
            // Set default dates if there are overdue schedules
            @if($overdueSchedules->count() > 0)
            this.selectedScheduleId = '{{ $overdueSchedules->first()->id }}';
            this.loadScheduleDetails();
            @endif
        },

        formatAmount() {
            let value = this.overdueAmountFormatted.replace(/\D/g, '');
            this.overdueAmount = parseInt(value) || 0;
            this.overdueAmountFormatted = this.overdueAmount.toLocaleString('ru-RU');
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },

        async loadScheduleDetails() {
            if (!this.selectedScheduleId) return;

            try {
                const res = await fetch(`/api/schedule/${this.selectedScheduleId}/penalty-details`);
                const data = await res.json();

                if (data.success) {
                    this.overdueAmount = data.schedule.overdue_amount;
                    this.overdueAmountFormatted = parseFloat(data.schedule.overdue_amount).toLocaleString('ru-RU');
                    this.dueDate = data.schedule.due_date;
                    this.paymentDate = '{{ now()->format("Y-m-d") }}';
                }
            } catch (e) {
                console.error('Error loading schedule:', e);
            }
        },

        async calculate() {
            if (!this.overdueAmount || !this.dueDate || !this.paymentDate) {
                alert('Iltimos, barcha maydonlarni to\'ldiring');
                return;
            }

            this.calculating = true;
            this.result = null;

            try {
                const res = await fetch('/api/penalty/calculate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        overdue_amount: this.overdueAmount,
                        due_date: this.dueDate,
                        payment_date: this.paymentDate,
                        contract_id: this.contractId
                    })
                });

                const data = await res.json();
                if (data.success) {
                    this.result = data;
                } else {
                    alert('Xatolik: ' + (data.message || 'Noma\'lum xatolik'));
                }
            } catch (e) {
                console.error('Error calculating:', e);
                alert('Xatolik yuz berdi');
            }

            this.calculating = false;
        },

        async generateNotification() {
            if (!this.result || this.result.calculation.overdue_days === 0) {
                alert('Avval penya hisoblang (kechikish bo\'lishi kerak)');
                return;
            }

            this.generatingNotification = true;

            try {
                const res = await fetch('/api/penalty/notification/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        contract_id: this.contractId,
                        schedule_id: this.selectedScheduleId || null,
                        as_of_date: this.paymentDate
                    })
                });

                const data = await res.json();
                if (data.success) {
                    alert('Bildirg\'inoma yaratildi: ' + data.notification.number);
                    window.location.reload();
                } else {
                    alert('Xatolik: ' + (data.message || 'Noma\'lum xatolik'));
                }
            } catch (e) {
                console.error('Error generating notification:', e);
                alert('Xatolik yuz berdi');
            }

            this.generatingNotification = false;
        }
    }
}
</script>
@endsection
