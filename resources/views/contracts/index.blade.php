@extends('layouts.app')
@section('title', 'Shartnomalar')
@section('header', 'Shartnomalar')
@section('header-actions')
<a href="{{ route('contracts.create') }}" class="px-4 py-2 bg-gray-900 text-white rounded text-sm hover:bg-gray-800">+ Yangi</a>
@endsection

@section('content')
<div x-data="contractsList()" x-init="init()">
    <!-- Filters -->
    <div class="bg-white border rounded-lg p-4 mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <input type="text" x-model="filters.search" @input.debounce.500ms="loadContracts()" placeholder="Qidirish..." class="flex-1 min-w-[200px] border rounded px-3 py-2 text-sm">
            <select x-model="filters.status" @change="loadContracts()" class="border rounded px-3 py-2 text-sm">
                <option value="">Barcha</option>
                <option value="faol">Faol</option>
                <option value="yakunlangan">Yakunlangan</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" x-model="filters.debtorOnly" @change="loadContracts()" class="rounded">
                Qarzdorlar
            </label>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="text-center py-12 text-gray-400">Yuklanmoqda...</div>

    <!-- List -->
    <div x-show="!loading" class="space-y-3">
        <template x-for="(contract, idx) in contracts" :key="contract?.id || idx">
            <div class="bg-white border rounded-lg p-4 hover:border-gray-300 transition-colors" x-show="contract && contract.id">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <a :href="'/contracts/' + contract.id" class="font-medium text-gray-900 hover:underline" x-text="contract.shartnoma_raqami || '—'"></a>
                            <span class="px-2 py-0.5 text-xs rounded" :class="contract.holat === 'faol' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500'" x-text="contract.holat === 'faol' ? 'Faol' : 'Yakunlangan'"></span>
                        </div>
                        <p class="text-sm text-gray-500" x-text="contract.tenant?.name || '—'"></p>
                    </div>
                    <div class="flex items-center gap-8 text-sm">
                        <div class="text-right">
                            <p class="text-gray-400 text-xs">Summa</p>
                            <p class="font-medium text-gray-900" x-text="formatMoney(contract.shartnoma_summasi || 0)"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-400 text-xs">Qarz</p>
                            <p class="font-medium text-gray-900" x-text="formatMoney(contract.jami_qoldiq || 0)"></p>
                        </div>
                        <a :href="'/contracts/' + contract.id" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
                <!-- Progress -->
                <div class="mt-3">
                    <div class="h-1 bg-gray-100 rounded-full">
                        <div class="h-1 bg-gray-900 rounded-full" :style="'width:' + getPaymentPercent(contract) + '%'"></div>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="contracts.length === 0" class="bg-white border rounded-lg p-12 text-center text-gray-400">
            Shartnomalar topilmadi
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function contractsList() {
    return {
        contracts: [], loading: true,
        filters: { search: '', status: '', debtorOnly: false },
        init() { this.loadContracts(); },
        async loadContracts() {
            this.loading = true;
            const params = new URLSearchParams();
            if (this.filters.search) params.append('search', this.filters.search);
            if (this.filters.status) params.append('holat', this.filters.status);
            if (this.filters.debtorOnly) params.append('debtor', '1');
            try {
                const res = await fetch('/api/contracts?' + params.toString());
                const json = await res.json();
                // Handle both paginated and non-paginated responses
                let data = json.data || json || [];
                if (data.data && Array.isArray(data.data)) {
                    data = data.data;
                }
                // Filter out any null/undefined entries and ensure unique IDs
                const seen = new Set();
                this.contracts = (Array.isArray(data) ? data : []).filter(c => {
                    if (!c || !c.id || seen.has(c.id)) return false;
                    seen.add(c.id);
                    return true;
                });
            } catch (e) {
                console.error(e);
                this.contracts = [];
            }
            this.loading = false;
        },
        getPaymentPercent(c) {
            if (!c || !c.shartnoma_summasi) return 0;
            return Math.round(((c.tolangan || 0) / c.shartnoma_summasi) * 100);
        },
        formatMoney(v) { return new Intl.NumberFormat('uz-UZ').format(v || 0); }
    }
}
</script>
@endsection
