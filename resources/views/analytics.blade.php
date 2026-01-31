@extends('layouts.app')
@section('title', 'Analitika')
@section('header', 'Analitika')

@section('content')
<div x-data="analyticsApp()" x-init="init()">
    <div x-show="loading" class="text-center py-12 text-gray-400">Yuklanmoqda...</div>

    <div x-show="!loading" x-cloak>
        <!-- Stats Row -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-white border rounded-lg p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Yig'ish darajasi</p>
                <p class="text-3xl font-semibold text-gray-900 mt-1" x-text="data.collection_rate?.rate + '%'"></p>
            </div>
            <div class="bg-white border rounded-lg p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Kutilgan</p>
                <p class="text-xl font-semibold text-gray-900 mt-1" x-text="formatShort(data.collection_rate?.total)"></p>
            </div>
            <div class="bg-white border rounded-lg p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Yig'ilgan</p>
                <p class="text-xl font-semibold text-gray-900 mt-1" x-text="formatShort(data.collection_rate?.collected)"></p>
            </div>
            <div class="bg-white border rounded-lg p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Qarzdorlar</p>
                <p class="text-3xl font-semibold text-gray-900 mt-1" x-text="data.top_debtors?.length || 0"></p>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white border rounded-lg p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Kutilgan va Haqiqiy To'lovlar</h3>
                <canvas id="comparisonChart" height="200"></canvas>
            </div>
            <div class="bg-white border rounded-lg p-5">
                <h3 class="text-sm font-medium text-gray-900 mb-4">Oylik Daromad</h3>
                <canvas id="incomeChart" height="200"></canvas>
            </div>
        </div>

        <!-- Top Debtors -->
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b">
                <h3 class="text-sm font-medium text-gray-900">Top 10 Qarzdorlar</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">#</th>
                        <th class="px-4 py-3 text-left font-medium">Shartnoma</th>
                        <th class="px-4 py-3 text-left font-medium">Ijarachi</th>
                        <th class="px-4 py-3 text-right font-medium">Qarz</th>
                        <th class="px-4 py-3 text-right font-medium">Penya</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(d, idx) in (data.top_debtors || [])" :key="d?.id || idx">
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-400" x-text="idx + 1"></td>
                            <td class="px-4 py-3"><a :href="'/contracts/' + d.id" class="text-gray-900 hover:underline" x-text="d.contract"></a></td>
                            <td class="px-4 py-3 text-gray-500" x-text="d.tenant"></td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900" x-text="formatMoney(d.debt)"></td>
                            <td class="px-4 py-3 text-right text-gray-500" x-text="formatMoney(d.penya)"></td>
                            <td class="px-4 py-3 text-right"><a :href="'/contracts/' + d.id" class="text-gray-400 hover:text-gray-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg></a></td>
                        </tr>
                    </template>
                    <template x-if="!data.top_debtors || data.top_debtors.length === 0">
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Qarzdorlar yo'q</td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function analyticsApp() {
    return {
        loading: true, data: {}, charts: {},
        init() { this.loadData(); },
        async loadData() {
            this.loading = true;
            try {
                const res = await fetch('/api/analytics?year=' + new Date().getFullYear());
                const json = await res.json();
                this.data = json.data || {};
                this.$nextTick(() => this.renderCharts());
            } catch (e) { console.error(e); }
            this.loading = false;
        },
        renderCharts() {
            const months = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyn', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
            const comparison = this.data.monthly_comparison || [];
            const income = this.data.monthly_income || [];

            // Comparison Chart
            const ctx1 = document.getElementById('comparisonChart')?.getContext('2d');
            if (ctx1) {
                if (this.charts.comparison) this.charts.comparison.destroy();
                this.charts.comparison = new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: months,
                        datasets: [
                            { label: 'Kutilgan', data: comparison.map(m => m.expected || 0), borderColor: '#111827', backgroundColor: 'transparent', tension: 0.3, borderWidth: 2 },
                            { label: 'Haqiqiy', data: comparison.map(m => m.actual || 0), borderColor: '#9CA3AF', backgroundColor: 'transparent', tension: 0.3, borderWidth: 2 }
                        ]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 6 } } }, scales: { y: { beginAtZero: true, ticks: { callback: v => this.formatShort(v) } } } }
                });
            }

            // Income Chart
            const ctx2 = document.getElementById('incomeChart')?.getContext('2d');
            if (ctx2) {
                if (this.charts.income) this.charts.income.destroy();
                this.charts.income = new Chart(ctx2, {
                    type: 'bar',
                    data: { labels: months, datasets: [{ label: 'Daromad', data: income.map(m => m.income || 0), backgroundColor: '#111827', borderRadius: 2 }] },
                    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => this.formatShort(v) } } } }
                });
            }
        },
        formatMoney(v) { return new Intl.NumberFormat('uz-UZ').format(v || 0); },
        formatShort(v) { if (!v) return '0'; if (v >= 1e9) return (v / 1e9).toFixed(1) + ' mlrd'; if (v >= 1e6) return (v / 1e6).toFixed(1) + ' mln'; if (v >= 1e3) return (v / 1e3).toFixed(0) + ' ming'; return v.toString(); }
    }
}
</script>
@endsection
