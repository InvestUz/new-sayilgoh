<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Poytaxt Sayilgohi - Ijara Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>[x-cloak]{display:none!important}.fade-in{animation:fadeIn .2s}@keyframes fadeIn{from{opacity:0}to{opacity:1}}</style>
</head>
<body class="bg-gray-100 min-h-screen" x-data="app()" x-init="init()">

<!-- Toast Notification -->
<div x-show="toast.show" x-cloak class="fixed top-4 right-4 z-[100] px-4 py-2 rounded text-sm text-white"
     :class="toast.type==='success'?'bg-gray-900':'bg-gray-700'" x-text="toast.msg"
     x-transition:enter="transition ease-out duration-300" x-transition:leave="transition ease-in duration-200"></div>

<!-- Main Layout -->
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-52 bg-white border-r fixed h-full z-40 hidden lg:block">
        <div class="p-4 border-b">
            <h1 class="text-base font-semibold text-gray-900">Ijara Tizimi</h1>
        </div>
        <nav class="p-2 space-y-1">
            <template x-for="m in menu" :key="m.id">
                <button @click="switchPage(m.id)" class="w-full flex items-center gap-2 px-3 py-2 rounded text-left text-sm"
                    :class="page===m.id?'bg-gray-900 text-white':'text-gray-600 hover:bg-gray-100'">
                    <span x-html="m.icon" class="w-4 h-4"></span>
                    <span x-text="m.name"></span>
                </button>
            </template>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 lg:ml-52">
        <!-- Header -->
        <header class="bg-white border-b px-4 py-3 flex justify-between items-center sticky top-0 z-30">
            <h2 class="text-sm font-medium text-gray-900" x-text="pageTitle"></h2>
            <button @click="refreshData()" class="p-2 hover:bg-gray-100 rounded" title="Yangilash">
                <svg class="w-4 h-4" :class="loading&&'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </header>

        <div class="p-4 lg:p-6">
            <!-- ========== DASHBOARD ========== -->
            <div x-show="page==='dashboard'" class="fade-in space-y-4">
                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-white p-4 rounded-lg border">
                        <p class="text-2xl font-semibold text-gray-900" x-text="stats.faol_shartnomalar||0"></p>
                        <p class="text-xs text-gray-400 mt-1">Faol shartnomalar</p>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <p class="text-lg font-semibold text-gray-900" x-text="fmt(stats.jami_shartnoma_summasi)"></p>
                        <p class="text-xs text-gray-400 mt-1">Jami summa</p>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <p class="text-lg font-semibold text-gray-900" x-text="fmt(stats.jami_qarzdorlik)"></p>
                        <p class="text-xs text-gray-400 mt-1">Qarzdorlik</p>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <p class="text-lg font-semibold text-gray-900" x-text="fmt(stats.jami_penya)"></p>
                        <p class="text-xs text-gray-400 mt-1">Penya</p>
                    </div>
                </div>

                <!-- Collection Rate -->
                <div class="bg-white p-4 rounded-lg border">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-600">Yig'ilish foizi</span>
                        <span class="text-lg font-semibold text-gray-900" x-text="(stats.yig_ilish_foizi||0)+'%'"></span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full">
                        <div class="h-2 bg-gray-900 rounded-full transition-all" :style="'width:'+(stats.yig_ilish_foizi||0)+'%'"></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <button @click="page='create';createTab='tenant'" class="bg-white p-3 rounded-lg border hover:border-gray-300 text-left">
                        <span class="text-sm font-medium text-gray-900">+ Ijarachi</span>
                    </button>
                    <button @click="page='create';createTab='lot'" class="bg-white p-3 rounded-lg border hover:border-gray-300 text-left">
                        <span class="text-sm font-medium text-gray-900">+ Lot</span>
                    </button>
                    <button @click="page='create';createTab='contract'" class="bg-white p-3 rounded-lg border hover:border-gray-300 text-left">
                        <span class="text-sm font-medium text-gray-900">+ Shartnoma</span>
                    </button>
                    <button @click="page='create';createTab='payment'" class="bg-white p-3 rounded-lg border hover:border-gray-300 text-left">
                        <span class="text-sm font-medium text-gray-900">+ To'lov</span>
                    </button>
                </div>

                <!-- Recent Debtors -->
                <div class="bg-white rounded-lg border" x-show="debtors.length">
                    <div class="p-3 border-b"><span class="text-sm font-medium">Qarzdorlar</span></div>
                    <div class="divide-y">
                        <template x-for="d in debtors.slice(0,5)" :key="d.id">
                            <div class="p-3 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium" x-text="d.tenant?.name || 'Noma\'lum'"></p>
                                    <p class="text-xs text-gray-400" x-text="d.shartnoma_raqami"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium" x-text="fmt(d.jami_qoldiq)"></p>
                                    <button @click="quickPay(d)" class="text-xs text-gray-500 hover:text-gray-900">To'lov</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ========== CREATE PAGE WITH TABS ========== -->
            <div x-show="page==='create'" class="fade-in">
                <div class="bg-white rounded-lg border">
                    <!-- Tabs -->
                    <div class="flex border-b">
                        <button @click="createTab='tenant'" class="px-4 py-2 text-sm" :class="createTab==='tenant'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">Ijarachi</button>
                        <button @click="createTab='lot'" class="px-4 py-2 text-sm" :class="createTab==='lot'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">Lot</button>
                        <button @click="createTab='contract'" class="px-4 py-2 text-sm" :class="createTab==='contract'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">Shartnoma</button>
                        <button @click="createTab='payment'" class="px-4 py-2 text-sm" :class="createTab==='payment'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">To'lov</button>
                    </div>

                    <!-- TENANT FORM -->
                    <div x-show="createTab==='tenant'" class="p-4">
                        <form @submit.prevent="saveTenant()" class="max-w-lg space-y-3">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Nomi *</label>
                                <input type="text" x-model="tenantForm.name" required class="w-full px-3 py-2 border rounded text-sm focus:ring-1 focus:ring-gray-400" placeholder="Korxona nomi">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Turi</label>
                                    <select x-model="tenantForm.type" class="w-full px-3 py-2 border rounded text-sm">
                                        <option value="yuridik">Yuridik shaxs</option>
                                        <option value="jismoniy">Jismoniy shaxs</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">INN *</label>
                                    <input type="text" x-model="tenantForm.inn" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Direktor</label>
                                    <input type="text" x-model="tenantForm.director_name" class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Telefon *</label>
                                    <input type="text" x-model="tenantForm.phone" required class="w-full px-3 py-2 border rounded text-sm" placeholder="+998">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Manzil</label>
                                <input type="text" x-model="tenantForm.address" class="w-full px-3 py-2 border rounded text-sm">
                            </div>
                            <div class="flex gap-2 pt-2">
                                <button type="button" @click="resetTenantForm()" class="px-4 py-2 border rounded text-sm">Tozalash</button>
                                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded text-sm" :disabled="saving">
                                    <span x-show="!saving">Saqlash</span><span x-show="saving">...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <!-- LOT FORM -->
                    <div x-show="createTab==='lot'" class="p-4">
                        <form @submit.prevent="saveLot()" class="max-w-lg space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Lot raqami *</label>
                                    <input type="text" x-model="lotForm.lot_raqami" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Obyekt nomi *</label>
                                    <input type="text" x-model="lotForm.obyekt_nomi" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Tuman</label>
                                    <input type="text" x-model="lotForm.tuman" list="districts-list" class="w-full px-3 py-2 border rounded text-sm">
                                    <datalist id="districts-list"><template x-for="d in districts" :key="d"><option :value="d"></option></template></datalist>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Maydon (m²) *</label>
                                    <input type="number" step="0.01" x-model="lotForm.maydon" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Manzil</label>
                                <input type="text" x-model="lotForm.manzil" class="w-full px-3 py-2 border rounded text-sm">
                            </div>
                            <div class="flex gap-2 pt-2">
                                <button type="button" @click="resetLotForm()" class="px-4 py-2 border rounded text-sm">Tozalash</button>
                                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded text-sm" :disabled="saving">
                                    <span x-show="!saving">Saqlash</span><span x-show="saving">...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- CONTRACT FORM -->
                    <div x-show="createTab==='contract'" class="p-4">
                        <div x-show="!tenants.length || !availableLots.length" class="p-3 mb-3 bg-gray-50 rounded text-sm text-gray-600">
                            Shartnoma yaratish uchun avval ijarachi va lot yarating.
                        </div>
                        <form @submit.prevent="saveContract()" class="max-w-lg space-y-3" x-show="tenants.length && availableLots.length">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Lot *</label>
                                    <select x-model="contractForm.lot_id" required class="w-full px-3 py-2 border rounded text-sm">
                                        <option value="">Tanlang...</option>
                                        <template x-for="l in availableLots" :key="l.id"><option :value="l.id" x-text="l.lot_raqami + ' - ' + l.obyekt_nomi"></option></template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Ijarachi *</label>
                                    <select x-model="contractForm.tenant_id" required class="w-full px-3 py-2 border rounded text-sm">
                                        <option value="">Tanlang...</option>
                                        <template x-for="t in tenants" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Shartnoma raqami *</label>
                                    <input type="text" x-model="contractForm.shartnoma_raqami" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Shartnoma sanasi *</label>
                                    <input type="date" x-model="contractForm.shartnoma_sanasi" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Boshlanish sanasi *</label>
                                    <input type="date" x-model="contractForm.boshlanish_sanasi" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Auksion sanasi *</label>
                                    <input type="date" x-model="contractForm.auksion_sanasi" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Summa (UZS) *</label>
                                    <input type="number" step="any" x-model="contractForm.shartnoma_summasi" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Muddat (oy) *</label>
                                    <input type="number" x-model="contractForm.shartnoma_muddati" required min="1" class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                            </div>
                            <div class="flex gap-2 pt-2">
                                <button type="button" @click="resetContractForm()" class="px-4 py-2 border rounded text-sm">Tozalash</button>
                                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded text-sm" :disabled="saving">
                                    <span x-show="!saving">Saqlash</span><span x-show="saving">...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- PAYMENT FORM -->
                    <div x-show="createTab==='payment'" class="p-4">
                        <div x-show="!contracts.length" class="p-3 mb-3 bg-gray-50 rounded text-sm text-gray-600">
                            To'lov qo'shish uchun avval shartnoma yarating.
                        </div>
                        <form @submit.prevent="savePayment()" class="max-w-lg space-y-3" x-show="contracts.length">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Shartnoma *</label>
                                <select x-model="paymentForm.contract_id" required class="w-full px-3 py-2 border rounded text-sm">
                                    <option value="">Tanlang...</option>
                                    <template x-for="c in contracts" :key="c.id"><option :value="c.id" x-text="c.shartnoma_raqami + ' - ' + (c.tenant?.name||'') + ' (Qarz: ' + fmt(c.jami_qoldiq||0) + ')'"></option></template>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Sana *</label>
                                    <input type="date" x-model="paymentForm.tolov_sanasi" required class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Summa (UZS) *</label>
                                    <input type="number" step="any" x-model="paymentForm.summa" required min="1" class="w-full px-3 py-2 border rounded text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">To'lov usuli</label>
                                <select x-model="paymentForm.tolov_usuli" class="w-full px-3 py-2 border rounded text-sm">
                                    <option value="bank_otkazmasi">Bank o'tkazmasi</option>
                                    <option value="naqd">Naqd</option>
                                    <option value="karta">Karta</option>
                                </select>
                            </div>
                            <div class="flex gap-2 pt-2">
                                <button type="button" @click="resetPaymentForm()" class="px-4 py-2 border rounded text-sm">Tozalash</button>
                                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded text-sm" :disabled="saving">
                                    <span x-show="!saving">Saqlash</span><span x-show="saving">...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- ========== CONTRACTS LIST ========== -->
            <div x-show="page==='contracts'" class="fade-in">
                <div class="bg-white rounded-lg border">
                    <div class="p-4 border-b flex justify-between items-center">
                        <span class="font-medium">Shartnomalar</span>
                        <div class="flex gap-2">
                            <input type="text" placeholder="Qidirish..." x-model="searchQ" class="px-3 py-1.5 border rounded text-sm w-40">
                            <button @click="page='create';createTab='contract'" class="px-3 py-1.5 bg-gray-900 text-white rounded text-sm">+ Yangi</button>
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50"><tr><th class="text-left px-4 py-2 font-medium text-gray-600">Shartnoma</th><th class="text-left px-4 py-2 font-medium text-gray-600">Ijarachi</th><th class="text-right px-4 py-2 font-medium text-gray-600">Summa</th><th class="text-right px-4 py-2 font-medium text-gray-600">Qarz</th><th class="px-4 py-2"></th></tr></thead>
                        <tbody class="divide-y"><template x-for="r in contracts" :key="r.id"><tr class="hover:bg-gray-50">
                            <td class="px-4 py-2"><p class="font-medium" x-text="r.shartnoma_raqami"></p><p class="text-xs text-gray-400" x-text="fmtDate(r.shartnoma_sanasi)"></p></td>
                            <td class="px-4 py-2 text-gray-600" x-text="r.tenant?.name"></td>
                            <td class="px-4 py-2 text-right" x-text="fmt(r.shartnoma_summasi)"></td>
                            <td class="px-4 py-2 text-right font-medium" x-text="fmt(r.jami_qoldiq||0)"></td>
                            <td class="px-4 py-2 text-right"><button @click="viewContract(r)" class="text-gray-400 hover:text-gray-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg></button></td>
                        </tr></template></tbody>
                    </table>
                    <div x-show="!contracts.length" class="p-8 text-center text-gray-400 text-sm">Shartnomalar yo'q</div>
                </div>
            </div>

            <!-- ========== DATA PAGE (Tenants, Lots, Payments, Calendar, Analytics) ========== -->
            <div x-show="page==='data'" class="fade-in" x-data="dataPage()" x-init="init()">
                <div class="bg-white rounded-lg border">
                    <!-- Tabs -->
                    <div class="flex border-b overflow-x-auto">
                        <button @click="tab='tenants'" class="px-4 py-2 text-sm whitespace-nowrap" :class="tab==='tenants'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">Ijarachilar</button>
                        <button @click="tab='lots'" class="px-4 py-2 text-sm whitespace-nowrap" :class="tab==='lots'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">Lotlar</button>
                        <button @click="tab='payments'" class="px-4 py-2 text-sm whitespace-nowrap" :class="tab==='payments'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">To'lovlar</button>
                        <button @click="tab='debtors'" class="px-4 py-2 text-sm whitespace-nowrap" :class="tab==='debtors'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">Qarzdorlar</button>
                        <button @click="tab='calendar';loadCalendar()" class="px-4 py-2 text-sm whitespace-nowrap" :class="tab==='calendar'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">Kalendar</button>
                        <button @click="tab='analytics';loadAnalytics()" class="px-4 py-2 text-sm whitespace-nowrap" :class="tab==='analytics'?'border-b-2 border-gray-900 font-medium':'text-gray-500'">Analitika</button>
                    </div>

                    <!-- Tenants -->
                    <div x-show="tab==='tenants'">
                        <div class="p-3 border-b flex justify-end"><button @click="page='create';createTab='tenant'" class="px-3 py-1.5 bg-gray-900 text-white rounded text-sm">+ Yangi</button></div>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50"><tr><th class="text-left px-4 py-2 font-medium text-gray-600">Nomi</th><th class="text-left px-4 py-2 font-medium text-gray-600">INN</th><th class="text-left px-4 py-2 font-medium text-gray-600">Telefon</th><th class="px-4 py-2"></th></tr></thead>
                            <tbody class="divide-y"><template x-for="r in tenants" :key="r.id"><tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium" x-text="r.name"></td>
                                <td class="px-4 py-2 text-gray-600" x-text="r.inn"></td>
                                <td class="px-4 py-2 text-gray-600" x-text="r.phone"></td>
                                <td class="px-4 py-2 text-right"><button @click="editTenant(r)" class="text-gray-400 hover:text-gray-600 mr-2">Tahrir</button><button @click="deleteTenant(r)" class="text-gray-400 hover:text-red-600">O'chir</button></td>
                            </tr></template></tbody>
                        </table>
                        <div x-show="!tenants.length" class="p-8 text-center text-gray-400 text-sm">Ijarachilar yo'q</div>
                    </div>

                    <!-- Lots -->
                    <div x-show="tab==='lots'">
                        <div class="p-3 border-b flex justify-end"><button @click="page='create';createTab='lot'" class="px-3 py-1.5 bg-gray-900 text-white rounded text-sm">+ Yangi</button></div>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50"><tr><th class="text-left px-4 py-2 font-medium text-gray-600">Lot</th><th class="text-left px-4 py-2 font-medium text-gray-600">Nomi</th><th class="text-left px-4 py-2 font-medium text-gray-600">Tuman</th><th class="text-right px-4 py-2 font-medium text-gray-600">Maydon</th><th class="px-4 py-2"></th></tr></thead>
                            <tbody class="divide-y"><template x-for="r in lots" :key="r.id"><tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium" x-text="r.lot_raqami"></td>
                                <td class="px-4 py-2 text-gray-600" x-text="r.obyekt_nomi"></td>
                                <td class="px-4 py-2 text-gray-600" x-text="r.tuman"></td>
                                <td class="px-4 py-2 text-right text-gray-600" x-text="r.maydon+' m²'"></td>
                                <td class="px-4 py-2 text-right"><button @click="editLot(r)" class="text-gray-400 hover:text-gray-600 mr-2">Tahrir</button><button @click="deleteLot(r)" class="text-gray-400 hover:text-red-600">O'chir</button></td>
                            </tr></template></tbody>
                        </table>
                        <div x-show="!lots.length" class="p-8 text-center text-gray-400 text-sm">Lotlar yo'q</div>
                    </div>

                    <!-- Payments -->
                    <div x-show="tab==='payments'">
                        <div class="p-3 border-b flex justify-end"><button @click="page='create';createTab='payment'" class="px-3 py-1.5 bg-gray-900 text-white rounded text-sm">+ Yangi</button></div>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50"><tr><th class="text-left px-4 py-2 font-medium text-gray-600">Raqam</th><th class="text-left px-4 py-2 font-medium text-gray-600">Sana</th><th class="text-left px-4 py-2 font-medium text-gray-600">Ijarachi</th><th class="text-right px-4 py-2 font-medium text-gray-600">Summa</th></tr></thead>
                            <tbody class="divide-y"><template x-for="r in payments" :key="r.id"><tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium" x-text="r.tolov_raqami"></td>
                                <td class="px-4 py-2 text-gray-600" x-text="fmtDate(r.tolov_sanasi)"></td>
                                <td class="px-4 py-2 text-gray-600" x-text="r.contract?.tenant?.name"></td>
                                <td class="px-4 py-2 text-right font-medium" x-text="fmt(r.summa)"></td>
                            </tr></template></tbody>
                        </table>
                        <div x-show="!payments.length" class="p-8 text-center text-gray-400 text-sm">To'lovlar yo'q</div>
                    </div>

                    <!-- Debtors -->
                    <div x-show="tab==='debtors'">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50"><tr><th class="text-left px-4 py-2 font-medium text-gray-600">Shartnoma</th><th class="text-left px-4 py-2 font-medium text-gray-600">Ijarachi</th><th class="text-right px-4 py-2 font-medium text-gray-600">Qarz</th><th class="text-right px-4 py-2 font-medium text-gray-600">Penya</th><th class="px-4 py-2"></th></tr></thead>
                            <tbody class="divide-y"><template x-for="r in debtors" :key="r.id"><tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium" x-text="r.shartnoma_raqami"></td>
                                <td class="px-4 py-2 text-gray-600" x-text="r.tenant?.name"></td>
                                <td class="px-4 py-2 text-right font-medium" x-text="fmt(r.jami_qoldiq)"></td>
                                <td class="px-4 py-2 text-right text-gray-500" x-text="fmt(r.jami_penya)"></td>
                                <td class="px-4 py-2 text-right"><button @click="quickPay(r)" class="px-2 py-1 bg-gray-900 text-white text-xs rounded">To'lov</button></td>
                            </tr></template></tbody>
                        </table>
                        <div x-show="!debtors.length" class="p-8 text-center text-gray-400 text-sm">Qarzdorlar yo'q</div>
                    </div>

                    <!-- Calendar -->
                    <div x-show="tab==='calendar'" class="p-4">
                        <div class="flex items-center justify-between mb-4">
                            <button @click="prevMonth()" class="p-2 hover:bg-gray-100 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg></button>
                            <span class="font-medium" x-text="monthNames[calMonth] + ' ' + calYear"></span>
                            <button @click="nextMonth()" class="p-2 hover:bg-gray-100 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg></button>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-500 mb-2">
                            <div>Du</div><div>Se</div><div>Ch</div><div>Pa</div><div>Ju</div><div>Sh</div><div>Ya</div>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <template x-for="(day, idx) in calDays" :key="idx">
                                <div class="min-h-[60px] p-1 border rounded text-xs" :class="day.isToday ? 'border-gray-900' : 'border-gray-100'" @click="day.payments && day.payments.length && showDayPayments(day)">
                                    <div class="font-medium" :class="day.isCurrentMonth ? 'text-gray-900' : 'text-gray-300'" x-text="day.day"></div>
                                    <template x-if="day.payments && day.payments.length > 0">
                                        <div class="mt-1">
                                            <div class="px-1 py-0.5 rounded text-[10px] truncate cursor-pointer" :class="day.hasOverdue ? 'bg-gray-900 text-white' : 'bg-gray-100'" x-text="day.payments.length + ' to\'lov'"></div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <!-- Day Details Modal -->
                        <div x-show="selectedCalDay" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="selectedCalDay=null">
                            <div class="bg-white rounded-lg w-full max-w-md">
                                <div class="p-3 border-b flex justify-between"><span class="font-medium" x-text="selectedCalDay?.date"></span><button @click="selectedCalDay=null" class="text-gray-400">&times;</button></div>
                                <div class="max-h-64 overflow-y-auto divide-y">
                                    <template x-for="(p, pIdx) in (selectedCalDay?.payments || [])" :key="pIdx">
                                        <div class="p-3 flex justify-between">
                                            <div><p class="font-medium text-sm" x-text="p.tenant"></p><p class="text-xs text-gray-400" x-text="p.contract"></p></div>
                                            <p class="font-medium text-sm" x-text="fmt(p.amount)"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics -->
                    <div x-show="tab==='analytics'" class="p-4">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                            <div class="p-3 border rounded">
                                <p class="text-lg font-semibold" x-text="analyticsData.collection_rate + '%'"></p>
                                <p class="text-xs text-gray-400">Yig'ilish %</p>
                            </div>
                            <div class="p-3 border rounded">
                                <p class="text-lg font-semibold" x-text="fmt(analyticsData.total_expected)"></p>
                                <p class="text-xs text-gray-400">Kutilgan</p>
                            </div>
                            <div class="p-3 border rounded">
                                <p class="text-lg font-semibold" x-text="fmt(analyticsData.total_collected)"></p>
                                <p class="text-xs text-gray-400">Yig'ilgan</p>
                            </div>
                            <div class="p-3 border rounded">
                                <p class="text-lg font-semibold" x-text="fmt(analyticsData.total_debt)"></p>
                                <p class="text-xs text-gray-400">Qarz</p>
                            </div>
                        </div>
                        <div class="border rounded p-3">
                            <p class="text-sm font-medium mb-3">Oylik taqqoslash</p>
                            <canvas id="analyticsChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- View Contract Modal -->
<div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
    <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto" @click.outside="showModal=false">
        <div class="p-4 border-b flex justify-between sticky top-0 bg-white"><h3 class="text-lg font-bold">Shartnoma tafsilotlari</h3><button @click="showModal=false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button></div>
        <div class="p-4" x-show="selectedContract">
            <div class="grid grid-cols-2 gap-3 mb-6 text-sm">
                <div><span class="text-gray-500">Shartnoma:</span> <strong x-text="selectedContract?.shartnoma_raqami"></strong></div>
                <div><span class="text-gray-500">Ijarachi:</span> <strong x-text="selectedContract?.tenant?.name"></strong></div>
                <div><span class="text-gray-500">Lot:</span> <strong x-text="selectedContract?.lot?.lot_raqami"></strong></div>
                <div><span class="text-gray-500">Summa:</span> <strong x-text="fmt(selectedContract?.shartnoma_summasi)"></strong></div>
            </div>
            <h4 class="font-semibold mb-3">To'lov grafigi</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Oy</th><th class="px-3 py-2 text-right">Summa</th><th class="px-3 py-2 text-right">To'langan</th><th class="px-3 py-2 text-right">Qoldiq</th><th class="px-3 py-2 text-right">Penya</th><th class="px-3 py-2 text-center">Holat</th></tr></thead>
                    <tbody class="divide-y"><template x-for="s in schedule"><tr>
                        <td class="px-3 py-2" x-text="s.oy_raqami+'-oy'"></td>
                        <td class="px-3 py-2 text-right" x-text="fmt(s.tolov_summasi)"></td>
                        <td class="px-3 py-2 text-right text-green-600" x-text="fmt(s.tolangan_summa)"></td>
                        <td class="px-3 py-2 text-right text-red-600" x-text="fmt(s.qoldiq_summa)"></td>
                        <td class="px-3 py-2 text-right text-amber-600" x-text="fmt(s.penya_summasi)"></td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs" :class="{'bg-green-100 text-green-700':s.holat==='tolangan','bg-red-100 text-red-700':s.holat==='tolanmagan','bg-amber-100 text-amber-700':s.holat==='qisman_tolangan','bg-blue-100 text-blue-700':s.holat==='kutilmoqda'}" x-text="s.holat"></span></td>
                    </tr></template></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Tenant Modal -->
<div x-show="editModal==='tenant'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
    <div class="bg-white rounded-xl shadow-xl max-w-xl w-full" @click.outside="editModal=null">
        <div class="p-4 border-b flex justify-between"><h3 class="text-lg font-bold">Ijarachini tahrirlash</h3><button @click="editModal=null" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button></div>
        <form @submit.prevent="updateTenant()" class="p-4 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2"><label class="text-sm font-medium">Nomi *</label><input type="text" x-model="editForm.name" required class="w-full px-3 py-2 border rounded-lg"></div>
                <div><label class="text-sm font-medium">INN *</label><input type="text" x-model="editForm.inn" required class="w-full px-3 py-2 border rounded-lg"></div>
                <div><label class="text-sm font-medium">Telefon *</label><input type="text" x-model="editForm.phone" required class="w-full px-3 py-2 border rounded-lg"></div>
                <div><label class="text-sm font-medium">Direktor</label><input type="text" x-model="editForm.director_name" class="w-full px-3 py-2 border rounded-lg"></div>
                <div><label class="text-sm font-medium">Email</label><input type="email" x-model="editForm.email" class="w-full px-3 py-2 border rounded-lg"></div>
                <div class="col-span-2"><label class="text-sm font-medium">Manzil *</label><textarea x-model="editForm.address" required rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea></div>
            </div>
            <div class="flex gap-2 pt-2"><button type="button" @click="editModal=null" class="flex-1 px-4 py-2 border rounded-lg">Bekor</button><button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg">Yangilash</button></div>
        </form>
    </div>
</div>

<!-- Edit Lot Modal -->
<div x-show="editModal==='lot'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
    <div class="bg-white rounded-xl shadow-xl max-w-xl w-full" @click.outside="editModal=null">
        <div class="p-4 border-b flex justify-between"><h3 class="text-lg font-bold">Lotni tahrirlash</h3><button @click="editModal=null" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button></div>
        <form @submit.prevent="updateLot()" class="p-4 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-sm font-medium">Lot raqami *</label><input type="text" x-model="editForm.lot_raqami" required class="w-full px-3 py-2 border rounded-lg"></div>
                <div><label class="text-sm font-medium">Obyekt nomi *</label><input type="text" x-model="editForm.obyekt_nomi" required class="w-full px-3 py-2 border rounded-lg"></div>
                <div><label class="text-sm font-medium">Tuman *</label><input type="text" x-model="editForm.tuman" required list="edit-districts" class="w-full px-3 py-2 border rounded-lg"><datalist id="edit-districts"><template x-for="d in districts"><option :value="d"></option></template></datalist></div>
                <div><label class="text-sm font-medium">Maydon *</label><input type="number" step="0.01" x-model="editForm.maydon" required class="w-full px-3 py-2 border rounded-lg"></div>
                <div class="col-span-2"><label class="text-sm font-medium">Manzil *</label><textarea x-model="editForm.manzil" required rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea></div>
            </div>
            <div class="flex gap-2 pt-2"><button type="button" @click="editModal=null" class="flex-1 px-4 py-2 border rounded-lg">Bekor</button><button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg">Yangilash</button></div>
        </form>
    </div>
</div>

<script>
function app(){
    const today = new Date().toISOString().split('T')[0];
    return {
        page:'dashboard', createTab:'tenant', loading:false, saving:false, searchQ:'',
        showModal:false, editModal:null, selectedContract:null, schedule:[],
        toast:{show:false,msg:'',type:'success'},
        stats:{}, contracts:[], tenants:[], lots:[], payments:[], debtors:[], availableLots:[],
        districts:[], streets:[],
        tenantForm:{name:'',type:'yuridik',inn:'',director_name:'',phone:'',email:'',address:'',bank_name:''},
        lotForm:{lot_raqami:'',obyekt_nomi:'',tuman:'',maydon:'',manzil:''},
        contractForm:{lot_id:'',tenant_id:'',shartnoma_raqami:'',shartnoma_sanasi:today,auksion_sanasi:today,boshlanish_sanasi:today,shartnoma_summasi:'',shartnoma_muddati:12},
        paymentForm:{contract_id:'',tolov_sanasi:today,summa:'',tolov_usuli:'bank_otkazmasi'},
        editForm:{},
        menu:[
            {id:'dashboard',name:'Bosh sahifa',icon:'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'},
            {id:'create',name:'Yaratish',icon:'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>'},
            {id:'contracts',name:'Shartnomalar',icon:'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'},
            {id:'data',name:'Ma\'lumotlar',icon:'<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>'},
        ],
        get pageTitle(){return this.menu.find(m=>m.id===this.page)?.name||'Dashboard'},

        async init(){await this.loadAll()},
        async loadAll(){this.loading=true;await Promise.all([this.loadStats(),this.loadContracts(),this.loadTenants(),this.loadLots(),this.loadPayments(),this.loadDebtors(),this.loadAvailableLots(),this.loadDistricts()]);this.loading=false},
        async refreshData(){await this.loadAll()},
        switchPage(p){this.page=p;if(p==='create')this.loadAvailableLots()},

        async loadStats(){try{const r=await fetch('/api/contracts/statistics');const d=await r.json();if(d.success)this.stats=d.data}catch(e){console.error(e)}},
        async loadContracts(){try{const r=await fetch('/api/contracts?per_page=100');const d=await r.json();if(d.success)this.contracts=d.data.data||d.data||[]}catch(e){this.contracts=[]}},
        async loadTenants(){try{const r=await fetch('/api/tenants?per_page=100');const d=await r.json();if(d.success)this.tenants=d.data.data||d.data||[]}catch(e){this.tenants=[]}},
        async loadLots(){try{const r=await fetch('/api/lots?per_page=100');const d=await r.json();if(d.success)this.lots=d.data.data||d.data||[]}catch(e){this.lots=[]}},
        async loadPayments(){try{const r=await fetch('/api/payments?per_page=100');const d=await r.json();if(d.success)this.payments=d.data.data||d.data||[]}catch(e){this.payments=[]}},
        async loadDebtors(){try{const r=await fetch('/api/contracts/debtors');const d=await r.json();if(d.success)this.debtors=d.data||[]}catch(e){this.debtors=[]}},
        async loadAvailableLots(){try{const r=await fetch('/api/lots/available');const d=await r.json();if(d.success)this.availableLots=d.data||[]}catch(e){this.availableLots=[]}},
        async loadDistricts(){try{const r=await fetch('/api/lots/districts');const d=await r.json();if(d.success)this.districts=d.data||[]}catch(e){this.districts=[]}
            this.streets=[...new Set(this.lots.map(l=>l.kocha).filter(Boolean))]},
        searchData(type){},

        async saveTenant(){this.saving=true;try{const r=await fetch('/api/tenants',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(this.tenantForm)});const d=await r.json();if(d.success){this.showToast('Ijarachi muvaffaqiyatli yaratildi','success');this.resetTenantForm();this.loadTenants()}else this.showToast(typeof d.message==='object'?Object.values(d.message).flat().join(', '):d.message||'Xatolik','error')}catch(e){this.showToast('Xatolik: '+e.message,'error')}this.saving=false},
        async saveLot(){this.saving=true;try{const r=await fetch('/api/lots',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(this.lotForm)});const d=await r.json();if(d.success){this.showToast('Lot muvaffaqiyatli yaratildi','success');this.resetLotForm();this.loadLots();this.loadAvailableLots();this.loadDistricts()}else this.showToast(typeof d.message==='object'?Object.values(d.message).flat().join(', '):d.message||'Xatolik','error')}catch(e){this.showToast('Xatolik: '+e.message,'error')}this.saving=false},
        async saveContract(){this.saving=true;try{const r=await fetch('/api/contracts',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(this.contractForm)});const d=await r.json();if(d.success){this.showToast('Shartnoma muvaffaqiyatli yaratildi','success');this.resetContractForm();this.loadContracts();this.loadAvailableLots();this.loadStats()}else this.showToast(typeof d.message==='object'?Object.values(d.message).flat().join(', '):d.message||'Xatolik','error')}catch(e){this.showToast('Xatolik: '+e.message,'error')}this.saving=false},
        async savePayment(){this.saving=true;try{const r=await fetch('/api/payments',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(this.paymentForm)});const d=await r.json();if(d.success){this.showToast('To\'lov muvaffaqiyatli qo\'shildi','success');this.resetPaymentForm();this.loadPayments();this.loadContracts();this.loadDebtors();this.loadStats()}else this.showToast(typeof d.message==='object'?Object.values(d.message).flat().join(', '):d.message||'Xatolik','error')}catch(e){this.showToast('Xatolik: '+e.message,'error')}this.saving=false},

        resetTenantForm(){this.tenantForm={name:'',type:'yuridik',inn:'',director_name:'',phone:'',email:'',address:'',bank_name:''}},
        resetLotForm(){this.lotForm={lot_raqami:'',obyekt_nomi:'',tuman:'',maydon:'',manzil:''}},
        resetContractForm(){const t=new Date().toISOString().split('T')[0];this.contractForm={lot_id:'',tenant_id:'',shartnoma_raqami:'',shartnoma_sanasi:t,auksion_sanasi:t,boshlanish_sanasi:t,shartnoma_summasi:'',shartnoma_muddati:12}},
        resetPaymentForm(){this.paymentForm={contract_id:'',tolov_sanasi:new Date().toISOString().split('T')[0],summa:'',tolov_usuli:'bank_otkazmasi'}},

        editTenant(r){this.editForm={...r};this.editModal='tenant'},
        editLot(r){this.editForm={...r};this.editModal='lot'},
        async updateTenant(){try{const r=await fetch(`/api/tenants/${this.editForm.id}`,{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(this.editForm)});const d=await r.json();if(d.success){this.showToast('Yangilandi','success');this.editModal=null;this.loadTenants()}else this.showToast(typeof d.message==='object'?Object.values(d.message).flat().join(', '):d.message||'Xatolik','error')}catch(e){this.showToast('Xatolik','error')}},
        async updateLot(){try{const r=await fetch(`/api/lots/${this.editForm.id}`,{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(this.editForm)});const d=await r.json();if(d.success){this.showToast('Yangilandi','success');this.editModal=null;this.loadLots();this.loadDistricts()}else this.showToast(typeof d.message==='object'?Object.values(d.message).flat().join(', '):d.message||'Xatolik','error')}catch(e){this.showToast('Xatolik','error')}},
        async deleteTenant(r){if(!confirm('O\'chirishni tasdiqlaysizmi?'))return;try{const res=await fetch(`/api/tenants/${r.id}`,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});const d=await res.json();if(d.success){this.showToast('O\'chirildi','success');this.loadTenants()}else this.showToast(d.message||'Xatolik','error')}catch(e){this.showToast('Xatolik','error')}},
        async deleteLot(r){if(!confirm('O\'chirishni tasdiqlaysizmi?'))return;try{const res=await fetch(`/api/lots/${r.id}`,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});const d=await res.json();if(d.success){this.showToast('O\'chirildi','success');this.loadLots();this.loadAvailableLots()}else this.showToast(d.message||'Xatolik','error')}catch(e){this.showToast('Xatolik','error')}},

        async viewContract(c){this.selectedContract=c;try{const r=await fetch(`/api/contracts/${c.id}/payment-schedule`);const d=await r.json();if(d.success)this.schedule=d.data||[]}catch(e){this.schedule=[]}this.showModal=true},
        quickPay(d){this.paymentForm.contract_id=d.id;this.page='create';this.createTab='payment'},

        showToast(msg,type){this.toast={show:true,msg,type};setTimeout(()=>this.toast.show=false,3000)},
        fmt(n){return n?new Intl.NumberFormat('uz-UZ').format(Math.round(n))+' so\'m':'0 so\'m'},
        fmtDate(d){return d?new Date(d).toLocaleDateString('uz-UZ'):''}
    }
}

function dataPage() {
    return {
        tab: 'tenants',
        calYear: new Date().getFullYear(),
        calMonth: new Date().getMonth(),
        calDays: [],
        calPayments: {},
        selectedCalDay: null,
        analyticsData: { collection_rate: 0, total_expected: 0, total_collected: 0, total_debt: 0 },
        analyticsChart: null,
        monthNames: ['Yanvar','Fevral','Mart','Aprel','May','Iyun','Iyul','Avgust','Sentyabr','Oktyabr','Noyabr','Dekabr'],
        init() {},
        async loadCalendar() {
            try {
                const res = await fetch(`/api/calendar?year=${this.calYear}&month=${this.calMonth + 1}`);
                const data = await res.json();
                this.calPayments = data.data || {};
                this.buildCalendar();
            } catch (e) { this.buildCalendar(); }
        },
        buildCalendar() {
            const firstDay = new Date(this.calYear, this.calMonth, 1);
            const lastDay = new Date(this.calYear, this.calMonth + 1, 0);
            const startDay = (firstDay.getDay() + 6) % 7;
            const today = new Date().toISOString().split('T')[0];
            this.calDays = [];
            // Previous month days
            const prevMonth = new Date(this.calYear, this.calMonth, 0);
            for (let i = startDay - 1; i >= 0; i--) {
                const d = prevMonth.getDate() - i;
                this.calDays.push({ day: d, isCurrentMonth: false, isToday: false, payments: [], hasOverdue: false });
            }
            // Current month days
            for (let d = 1; d <= lastDay.getDate(); d++) {
                const date = `${this.calYear}-${String(this.calMonth + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                const payments = this.calPayments[date] || [];
                const hasOverdue = payments.some(p => p.status === 'overdue');
                this.calDays.push({ day: d, date, isCurrentMonth: true, isToday: date === today, payments, hasOverdue });
            }
            // Next month days
            const remaining = 42 - this.calDays.length;
            for (let d = 1; d <= remaining; d++) {
                this.calDays.push({ day: d, isCurrentMonth: false, isToday: false, payments: [], hasOverdue: false });
            }
        },
        prevMonth() { this.calMonth--; if (this.calMonth < 0) { this.calMonth = 11; this.calYear--; } this.loadCalendar(); },
        nextMonth() { this.calMonth++; if (this.calMonth > 11) { this.calMonth = 0; this.calYear++; } this.loadCalendar(); },
        showDayPayments(day) { this.selectedCalDay = day; },
        async loadAnalytics() {
            try {
                const [rateRes, monthlyRes] = await Promise.all([
                    fetch('/api/analytics/collection-rate'),
                    fetch('/api/analytics/monthly-comparison')
                ]);
                const rateData = await rateRes.json();
                const monthlyData = await monthlyRes.json();
                if (rateData.data) {
                    this.analyticsData = {
                        collection_rate: rateData.data.collection_rate || 0,
                        total_expected: rateData.data.total_expected || 0,
                        total_collected: rateData.data.total_collected || 0,
                        total_debt: rateData.data.total_debt || 0
                    };
                }
                this.renderChart(monthlyData.data || []);
            } catch (e) { console.error(e); }
        },
        renderChart(data) {
            const ctx = document.getElementById('analyticsChart');
            if (!ctx) return;
            if (this.analyticsChart) this.analyticsChart.destroy();
            this.analyticsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.month),
                    datasets: [
                        { label: 'Kutilgan', data: data.map(d => d.expected), borderColor: '#111827', backgroundColor: 'transparent', tension: 0.3 },
                        { label: 'Yig\'ilgan', data: data.map(d => d.collected), borderColor: '#9ca3af', backgroundColor: 'transparent', tension: 0.3 }
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
            });
        },
        fmt(n) { return n ? new Intl.NumberFormat('uz-UZ').format(Math.round(n)) : '0'; }
    };
}
</script>
</body>
</html>
