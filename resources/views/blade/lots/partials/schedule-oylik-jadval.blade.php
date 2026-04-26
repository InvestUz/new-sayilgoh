@php
    $oylikSchedules = $oylikSchedules ?? [];
@endphp
<table class="w-full text-xs">
    <thead class="bg-slate-700/50 text-slate-300">
        <tr>
            <th class="border border-slate-600 px-2 py-1 text-center">№</th>
            <th class="border border-slate-600 px-2 py-1 text-left">Oy</th>
            <th class="border border-slate-600 px-2 py-1 text-center">Muddat</th>
            <th class="border border-slate-600 px-2 py-1 text-right" title="Oylik reja (yillik bo'lsa 12 ga bo'linib bir xil; pro-rata: o'z summasi)">Grafik</th>
            <th class="border border-slate-600 px-2 py-1 text-right" title="Shu kalendar oyda kassaga tushim">Fakt tushgan</th>
            <th class="border border-slate-600 px-2 py-1 text-center">To'lov sanasi</th>
            <th class="border border-slate-600 px-2 py-1 text-right" title="Grafik - Fakt (shu kalendar oy)">Qoldiq</th>
            <th class="border border-slate-600 px-2 py-1 text-center">Kun</th>
            <th class="border border-slate-600 px-2 py-1 text-center">Stavka</th>
            <th class="border border-slate-600 px-2 py-1 text-right">Penya hisob</th>
            <th class="border border-slate-600 px-2 py-1 text-right">To'l. penya</th>
            <th class="border border-slate-600 px-2 py-1 text-right">Qol. penya</th>
            <th class="border border-slate-600 px-2 py-1 text-left min-w-[100px] max-w-xs">Izoh</th>
            <th class="border border-slate-600 px-2 py-1 text-center">Amal</th>
        </tr>
    </thead>
    <tbody class="text-slate-200">
        @php $rowNum = 0; @endphp
        @foreach($oylikSchedules as $scheduleData)
        @php
            $rowNum++;

            $isOverdue = $scheduleData['is_overdue'];
            $overdueDays = $scheduleData['overdue_days'];
            $daysLeft = $scheduleData['days_left'];
            $isCurrentMonth = $scheduleData['is_current_month'];
            $canDelete = $scheduleData['can_delete'];
            $hasCustomDeadline = $scheduleData['has_custom_deadline'];
            $effectiveDeadline = \Carbon\Carbon::parse($scheduleData['effective_deadline']);
            $originalDeadline = \Carbon\Carbon::parse($scheduleData['oxirgi_muddat']);
            $tolanganPenya = $scheduleData['tolangan_penya'];
            $penyaHisob = $scheduleData['penya_summasi'];
            $qoldiqPenya = $scheduleData['qoldiq_penya'];
            $lastPaymentDate = $scheduleData['payment_date'] ? \Carbon\Carbon::parse($scheduleData['payment_date']) : null;
            $highlightDebt = $scheduleData['highlight_active_debt'] ?? false;
            $kunK = $scheduleData['kun_ko_rinishi'] ?? null;
            $kunTitleCell = trim(($scheduleData['muddat_ozgarish_izoh'] ?? '') . (empty($scheduleData['kun_ko_rinishi_izoh']) ? '' : ' ' . $scheduleData['kun_ko_rinishi_izoh']));
            $kunJamiAkt = !empty($scheduleData['kun_jami_akt']);
            $qarzKo = $scheduleData['qarz_ko_rinishi'] ?? 'oddiy';
        @endphp
        <tr x-data="{
            editing: false,
            form: {
                tolov_sanasi: '{{ $scheduleData['tolov_sanasi'] }}',
                oxirgi_muddat: '{{ $originalDeadline->format('Y-m-d') }}',
                new_deadline: '{{ $effectiveDeadline->format('Y-m-d') }}',
                tolov_summasi: {{ $scheduleData['tolov_summasi'] }}
            }
        }"
            class="hover:bg-slate-700/30"
        >
            <td class="border border-slate-600 px-2 py-1 text-center">{{ $rowNum }}</td>
            <td class="border border-slate-600 px-2 py-1">
                {{ $scheduleData['month_name'] }} {{ $scheduleData['year'] }}
                @if($isCurrentMonth)<span class="text-[9px] text-slate-400">(joriy)</span>@endif
                @if($qarzKo === 'qarzdor_fakt')<span class="ml-1 inline-block text-[8px] font-semibold text-red-300 border border-red-500/40 px-1 rounded align-middle" title="Kalendar: shu oydan fakt tushim yo'q, qarz">Qarzdor</span>@endif
                @if($qarzKo === 'kutilayotgan')<span class="ml-1 inline-block text-[8px] text-slate-300 border border-slate-500/50 px-1 rounded align-middle" title="Oxirgi muddat hali kelmadi">Kutilayotgan</span>@endif
            </td>
            <td class="border border-slate-600 px-2 py-1 text-center">
                <template x-if="!editing">
                    <div>
                        <span class="{{ $hasCustomDeadline ? 'text-slate-200' : '' }}">{{ $effectiveDeadline->format('d.m.Y') }}</span>
                        @if($hasCustomDeadline)
                            <span class="text-[8px] text-slate-400 ml-0.5" title="Asl muddat: {{ $originalDeadline->format('d.m.Y') }}">*</span>
                        @endif
                    </div>
                </template>
                <template x-if="editing">
                    <input type="date" x-model="form.new_deadline" class="w-full border border-slate-500 bg-slate-700 rounded px-1 py-0.5 text-xs text-white">
                </template>
            </td>
            <td class="border border-slate-600 px-2 py-1 text-right text-slate-200"
                @if(!empty($scheduleData['pro_rata_tooltip'])) title="{{ $scheduleData['pro_rata_tooltip'] }}" @endif>
                <template x-if="!editing">
                    <span>
                        {{ number_format($scheduleData['grafik_ko_rinish'] ?? $scheduleData['tolov_summasi'], 0, ',', ' ') }}
                        @if(!empty($scheduleData['is_pro_rata']))
                            <span class="text-[8px] text-slate-400 ml-0.5" title="Qisman oy (pro-rata)">⊘</span>
                        @endif
                    </span>
                </template>
                <template x-if="editing"><input type="number" x-model="form.tolov_summasi" class="w-full border border-slate-500 bg-slate-700 rounded px-1 py-0.5 text-xs text-right text-white"></template>
            </td>
            @php $ft = (float) ($scheduleData['fakt_tushgan'] ?? 0); $faktDocs = $scheduleData['fakt_payments'] ?? []; @endphp
            <td class="border border-slate-600 px-2 py-1 text-right {{ $ft > 0 ? 'text-green-500 font-semibold' : 'text-slate-500' }}"
                @if($ft > 0 && count($faktDocs))
                    title="{{ collect($faktDocs)->map(fn($d) => $d['sana'].': +'.number_format($d['summa'],0,',',' ').($d['hujjat'] ? ' ('.$d['hujjat'].')' : ''))->implode('&#10;') }}"
                @endif
            >{{ $ft > 0 ? '+'.number_format($ft, 0, ',', ' ') : '—' }}</td>
            <td class="border border-slate-600 px-2 py-1 text-center text-slate-400">{{ $lastPaymentDate ? $lastPaymentDate->format('d.m.Y') : '—' }}</td>
            <td class="border border-slate-600 px-2 py-1 text-right align-top {{ $scheduleData['qoldiq_summa'] > 0 ? 'text-red-400' : 'text-slate-200' }}"
                @if(!empty($scheduleData['qoldiq_usti_title'])) title="{{ e($scheduleData['qoldiq_usti_title']) }}" @endif
            >
                @if(!empty($scheduleData['qoldiq_hujayra_ochilishi']))
                    @if($scheduleData['qoldiq_summa'] > 0)
                        {{ number_format($scheduleData['qoldiq_summa'], 0, ',', ' ') }}
                    @else
                        <span class="text-slate-400">0</span>
                    @endif
                @else
                    —
                @endif
            </td>
            <td class="border border-slate-600 px-2 py-1 text-center font-semibold
                @if($kunK === null) text-slate-400
                @elseif($kunJamiAkt) text-red-400
                @elseif($isOverdue) text-red-400
                @elseif($daysLeft > 0) text-slate-200
                @else text-slate-400 @endif
            " title="{{ $kunTitleCell }}">
                @if($kunK !== null)
                    {{ $kunK }}
                    @if($hasCustomDeadline)<span class="text-[8px] text-slate-400 ml-0.5">*</span>@endif
                @else
                    —
                @endif
            </td>
            <td class="border border-slate-600 px-2 py-1 text-center text-slate-400">{{ $scheduleData['penya_rate'] ?? '—' }}</td>
            <td class="border border-slate-600 px-2 py-1 text-right {{ $penyaHisob > 0 ? 'text-red-400' : 'text-slate-500' }}">{{ $penyaHisob > 0 ? number_format($penyaHisob, 0, ',', ' ') : '—' }}</td>
            <td class="border border-slate-600 px-2 py-1 text-right {{ $tolanganPenya > 0 ? 'text-slate-200' : 'text-slate-500' }}">{{ $tolanganPenya > 0 ? number_format($tolanganPenya, 0, ',', ' ') : '—' }}</td>
            <td class="border border-slate-600 px-2 py-1 text-right {{ $qoldiqPenya > 0 ? 'text-red-400' : ($tolanganPenya > 0 ? 'text-slate-200' : 'text-slate-500') }}">{{ $qoldiqPenya > 0 ? number_format($qoldiqPenya, 0, ',', ' ') : ($tolanganPenya > 0 ? '✓' : '—') }}</td>
            <td class="border border-slate-600 px-2 py-1 text-[10px] leading-snug text-slate-400 align-top max-w-xs">{{ $scheduleData['qator_izoh'] ?? '—' }}</td>
            <td class="border border-slate-600 px-1 py-1 text-center">
                <template x-if="!editing">
                    <div class="flex items-center justify-center gap-1">
                        <button @click="editing = true" class="p-1 text-slate-500 hover:text-blue-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
                        @if($canDelete)<button @click="deleteSchedule({{ $scheduleData['id'] }})" class="p-1 text-slate-500 hover:text-red-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                        @else<span class="p-1 text-slate-600"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>@endif
                    </div>
                </template>
                <template x-if="editing">
                    <div class="flex items-center justify-center gap-1">
                        <button @click="updateSchedule({{ $scheduleData['id'] }}, form); editing = false" class="p-1 bg-green-600 text-white rounded hover:bg-green-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>
                        <button @click="editing = false" class="p-1 bg-slate-600 text-white rounded hover:bg-slate-500"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                </template>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
