@extends('layouts.app')
@section('title', 'Kalendar')
@section('header', 'To\'lov Kalendari')

@section('content')
<div x-data="calendarApp()" x-init="init()">
    <!-- Header -->
    <div class="bg-white border rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="prevMonth()" class="p-2 hover:bg-gray-100 rounded">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="text-lg font-medium text-gray-900" x-text="monthNames[currentMonth] + ' ' + currentYear"></h2>
                <button @click="nextMonth()" class="p-2 hover:bg-gray-100 rounded">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            <button @click="goToToday()" class="px-4 py-2 bg-gray-900 text-white rounded text-sm hover:bg-gray-800">Bugun</button>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="text-center py-12 text-gray-400">Yuklanmoqda...</div>

    <!-- Calendar Grid -->
    <div x-show="!loading" class="bg-white border rounded-lg overflow-hidden">
        <div class="grid grid-cols-7 bg-gray-50 border-b">
            <template x-for="day in weekDays"><div class="p-3 text-center text-xs font-medium text-gray-500 uppercase" x-text="day"></div></template>
        </div>
        <div class="grid grid-cols-7">
            <template x-for="(day, idx) in calendarDays" :key="idx">
                <div class="min-h-[90px] border-b border-r p-2 cursor-pointer hover:bg-gray-50"
                     :class="{'bg-gray-50': !day.isCurrentMonth, 'bg-gray-100': day.isToday}"
                     @click="selectDay(day)">
                    <div class="flex justify-between items-start">
                        <span class="text-sm" :class="{'text-gray-300': !day.isCurrentMonth, 'font-semibold text-gray-900': day.isToday}" x-text="day.date"></span>
                        <span x-show="day.payments.length > 0" class="text-xs px-1.5 py-0.5 rounded bg-gray-900 text-white" x-text="day.payments.length"></span>
                    </div>
                    <div class="mt-1 space-y-1">
                        <template x-for="(p, pIdx) in (day.payments || []).slice(0, 2)" :key="pIdx">
                            <div class="text-xs p-1 rounded truncate" :class="p.status === 'overdue' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600'" x-text="p.tenant"></div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Day Modal -->
    <div x-show="selectedDay" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="selectedDay=null">
        <div class="bg-white rounded-lg w-full max-w-md max-h-[70vh] overflow-hidden">
            <div class="px-4 py-3 border-b flex justify-between items-center">
                <h3 class="font-medium text-gray-900" x-text="selectedDay ? (selectedDay.date + ' ' + monthNames[currentMonth]) : ''"></h3>
                <button @click="selectedDay=null" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="overflow-y-auto max-h-[50vh] divide-y divide-gray-100">
                <template x-if="selectedDay && selectedDay.payments.length === 0">
                    <div class="p-8 text-center text-gray-400">To'lovlar yo'q</div>
                </template>
                <template x-for="(p, pIdx) in (selectedDay ? selectedDay.payments : [])" :key="'modal-' + pIdx">
                    <div class="p-4 flex justify-between items-center">
                        <div>
                            <a :href="'/contracts/' + p.contract_id" class="font-medium text-gray-900 hover:underline" x-text="p.contract"></a>
                            <p class="text-sm text-gray-500" x-text="p.tenant"></p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-gray-900" x-text="formatMoney(p.amount)"></p>
                            <span class="text-xs px-2 py-0.5 rounded" :class="p.status === 'overdue' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500'" x-text="p.status === 'overdue' ? 'Muddati o\'tgan' : 'Kutilmoqda'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mt-6">
        <div class="bg-white border rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase">Jami</p>
            <p class="text-2xl font-semibold text-gray-900" x-text="monthStats.total"></p>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase">Summa</p>
            <p class="text-xl font-semibold text-gray-900" x-text="formatMoney(monthStats.totalAmount)"></p>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase">Muddati o'tgan</p>
            <p class="text-2xl font-semibold text-gray-900" x-text="monthStats.overdue"></p>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <p class="text-xs text-gray-400 uppercase">Bugun</p>
            <p class="text-2xl font-semibold text-gray-900" x-text="monthStats.today"></p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function calendarApp() {
    return {
        loading: true, currentYear: new Date().getFullYear(), currentMonth: new Date().getMonth(),
        calendarData: {}, calendarDays: [], selectedDay: null,
        monthStats: { total: 0, totalAmount: 0, overdue: 0, today: 0 },
        monthNames: ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'],
        weekDays: ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'],
        init() { this.loadCalendar(); },
        async loadCalendar() {
            this.loading = true;
            try {
                const res = await fetch(`/api/calendar/payments?year=${this.currentYear}&month=${this.currentMonth + 1}`);
                const data = await res.json();
                this.calendarData = data.data || {};
            } catch (e) { this.calendarData = {}; }
            this.buildCalendar();
            this.calculateStats();
            this.loading = false;
        },
        buildCalendar() {
            this.calendarDays = [];
            const firstDay = new Date(this.currentYear, this.currentMonth, 1);
            const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
            const today = new Date();
            let startOffset = firstDay.getDay() - 1; if (startOffset < 0) startOffset = 6;
            const prevLast = new Date(this.currentYear, this.currentMonth, 0).getDate();
            for (let i = startOffset - 1; i >= 0; i--) this.calendarDays.push({ date: prevLast - i, isCurrentMonth: false, isToday: false, payments: [] });
            for (let d = 1; d <= lastDay.getDate(); d++) {
                const dateStr = `${this.currentYear}-${String(this.currentMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                this.calendarDays.push({ date: d, dateStr, isCurrentMonth: true, isToday: today.getFullYear() === this.currentYear && today.getMonth() === this.currentMonth && today.getDate() === d, payments: this.calendarData[dateStr] || [] });
            }
            const rem = 42 - this.calendarDays.length;
            for (let i = 1; i <= rem; i++) this.calendarDays.push({ date: i, isCurrentMonth: false, isToday: false, payments: [] });
        },
        calculateStats() {
            let total = 0, totalAmount = 0, overdue = 0, todayCount = 0;
            Object.values(this.calendarData).forEach(payments => { payments.forEach(p => { total++; totalAmount += p.amount || 0; if (p.status === 'overdue') overdue++; if (p.status === 'today') todayCount++; }); });
            this.monthStats = { total, totalAmount, overdue, today: todayCount };
        },
        prevMonth() { if (this.currentMonth === 0) { this.currentMonth = 11; this.currentYear--; } else { this.currentMonth--; } this.loadCalendar(); },
        nextMonth() { if (this.currentMonth === 11) { this.currentMonth = 0; this.currentYear++; } else { this.currentMonth++; } this.loadCalendar(); },
        goToToday() { this.currentYear = new Date().getFullYear(); this.currentMonth = new Date().getMonth(); this.loadCalendar(); },
        selectDay(day) { if (day.isCurrentMonth && day.payments.length > 0) this.selectedDay = day; },
        formatMoney(v) { return new Intl.NumberFormat('uz-UZ').format(v || 0); }
    }
}
</script>
@endsection
