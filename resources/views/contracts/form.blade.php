@extends('layouts.app')
@section('title', isset($contract) ? 'Tahrirlash' : 'Yangi shartnoma')
@section('breadcrumb')
<nav class="flex items-center gap-2 text-sm">
    <a href="{{ route('registry', ['tab' => 'contracts']) }}" class="text-gray-400 hover:text-gray-600">Shartnomalar</a>
    <span class="text-gray-300">/</span>
    <span class="text-gray-900">{{ isset($contract) ? 'Tahrirlash' : 'Yangi' }}</span>
</nav>
@endsection

@section('content')
<div x-data="contractForm()" x-init="init()" class="max-w-3xl">
    <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Basic -->
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b"><h3 class="text-sm font-medium text-gray-900">Asosiy ma'lumotlar</h3></div>
            <div class="p-4 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Shartnoma raqami</label>
                    <input type="text" x-model="form.shartnoma_raqami" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Holat</label>
                    <select x-model="form.holat" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="faol">Faol</option>
                        <option value="yakunlangan">Yakunlangan</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tenant -->
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b"><h3 class="text-sm font-medium text-gray-900">Ijarachi</h3></div>
            <div class="p-4">
                <select x-model="form.tenant_id" class="w-full border rounded px-3 py-2 text-sm" required>
                    <option value="">Tanlang...</option>
                    <template x-for="t in tenants" :key="t.id">
                        <option :value="t.id" x-text="t.name"></option>
                    </template>
                </select>
            </div>
        </div>

        <!-- Lot -->
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b"><h3 class="text-sm font-medium text-gray-900">Lot</h3></div>
            <div class="p-4">
                <select x-model="form.lot_id" @change="updateLotInfo()" class="w-full border rounded px-3 py-2 text-sm" required>
                    <option value="">Tanlang...</option>
                    <template x-for="l in lots" :key="l.id">
                        <option :value="l.id" x-text="l.lot_raqami + ' — ' + (l.tuman || '') + ' (' + l.maydon + ' m²)'"></option>
                    </template>
                </select>
                <div x-show="selectedLot" class="mt-3 p-3 bg-gray-50 rounded text-sm text-gray-600">
                    Oylik: <span class="font-medium text-gray-900" x-text="formatMoney((selectedLot?.maydon || 0) * (selectedLot?.boshlangich_narx || 0))"></span>
                </div>
            </div>
        </div>

        <!-- Period -->
        <div class="bg-white border rounded-lg">
            <div class="px-4 py-3 border-b"><h3 class="text-sm font-medium text-gray-900">Muddat va summa</h3></div>
            <div class="p-4 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Boshlanish</label>
                    <input type="date" x-model="form.boshlanish_sanasi" @change="calculateAmount()" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Tugash</label>
                    <input type="date" x-model="form.tugash_sanasi" @change="calculateAmount()" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Oylik summa</label>
                    <input type="number" step="any" x-model="form.oylik_ijara_summasi" @input="calculateAmount()" class="w-full border rounded px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Jami summa</label>
                    <input type="number" step="any" x-model="form.shartnoma_summasi" class="w-full border rounded px-3 py-2 text-sm bg-gray-50" readonly>
                </div>
            </div>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-3">
            <a href="{{ route('registry', ['tab' => 'contracts']) }}" class="px-4 py-2 border rounded text-sm text-gray-600 hover:bg-gray-50">Bekor qilish</a>
            <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded text-sm hover:bg-gray-800" :disabled="loading">
                <span x-text="loading ? '...' : '{{ isset($contract) ? 'Saqlash' : 'Yaratish' }}'"></span>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
function contractForm() {
    return {
        loading: false, tenants: [], lots: [], selectedLot: null,
        form: {
            shartnoma_raqami: '{{ $contract->shartnoma_raqami ?? '' }}',
            tenant_id: '{{ $contract->tenant_id ?? '' }}',
            lot_id: '{{ $contract->lot_id ?? '' }}',
            boshlanish_sanasi: '{{ isset($contract) ? $contract->boshlanish_sanasi : '' }}',
            tugash_sanasi: '{{ isset($contract) ? $contract->tugash_sanasi : '' }}',
            oylik_ijara_summasi: '{{ $contract->oylik_ijara_summasi ?? '' }}',
            shartnoma_summasi: '{{ $contract->shartnoma_summasi ?? '' }}',
            holat: '{{ $contract->holat ?? 'faol' }}'
        },
        async init() {
            await Promise.all([this.loadTenants(), this.loadLots()]);
            if (this.form.lot_id) this.updateLotInfo();
        },
        async loadTenants() { try { const r = await fetch('/api/tenants'); const d = await r.json(); this.tenants = d.data || d || []; } catch (e) {} },
        async loadLots() {
            try { const r = await fetch('/api/lots/available'); const d = await r.json(); this.lots = d.data || d || [];
            @if(isset($contract))
            const curr = @json($contract->lot ?? null); if (curr && !this.lots.find(l => l.id == curr.id)) this.lots.unshift(curr);
            @endif
            } catch (e) {}
        },
        updateLotInfo() {
            this.selectedLot = this.lots.find(l => l.id == this.form.lot_id) || null;
            if (this.selectedLot) { this.form.oylik_ijara_summasi = (this.selectedLot.maydon || 0) * (this.selectedLot.boshlangich_narx || 0); this.calculateAmount(); }
        },
        calculateAmount() {
            if (!this.form.boshlanish_sanasi || !this.form.tugash_sanasi || !this.form.oylik_ijara_summasi) return;
            const months = Math.max(1, Math.round((new Date(this.form.tugash_sanasi) - new Date(this.form.boshlanish_sanasi)) / (30.44 * 24 * 60 * 60 * 1000)));
            this.form.shartnoma_summasi = months * parseFloat(this.form.oylik_ijara_summasi || 0);
        },
        async submitForm() {
            this.loading = true;
            const isEdit = {{ isset($contract) ? 'true' : 'false' }};
            const url = isEdit ? '/api/contracts/{{ $contract->id ?? '' }}' : '/api/contracts';
            try {
                const r = await fetch(url, { method: isEdit ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(this.form) });
                const d = await r.json();
                if (r.ok) window.location.href = '/contracts/' + (d.data?.id || d.id || {{ $contract->id ?? 0 }});
                else alert(d.message || 'Xatolik');
            } catch (e) { alert('Xatolik'); }
            this.loading = false;
        },
        formatMoney(v) { return new Intl.NumberFormat('uz-UZ').format(v || 0) + ' so\'m'; }
    }
}
</script>
@endsection
