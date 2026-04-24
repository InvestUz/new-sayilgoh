@extends('layouts.dark')
@section('title', "Yangi to'lov")
@section('header', "Yangi to'lov")
@section('subheader', "To'lov qo'shish")

@section('content')
<div class="max-w-2xl">
    @if($contracts->isEmpty())
    <div class="card">
        <div class="card-body">
            <div class="flex items-center gap-3 text-[#f59e0b]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>To'lov qo'shish uchun avval <a href="{{ route('registry.contracts.create') }}" class="underline text-[#38bdf8]">shartnoma</a> yarating.</span>
            </div>
        </div>
    </div>
    @else
    <form action="{{ route('registry.payments.store') }}" method="POST" class="card">
        @csrf
        <div class="card-header">
            <h3 class="card-title">To'lov ma'lumotlari</h3>
        </div>
        <div class="card-body space-y-4">
            <div>
                <label class="form-label">Shartnoma <span class="text-[#ef4444]">*</span></label>
                <select name="contract_id" required class="form-input form-select @error('contract_id') border-[#ef4444] @enderror">
                    <option value="">Tanlang...</option>
                    @foreach($contracts as $contract)
                    <option value="{{ $contract->id }}" {{ old('contract_id', $selectedContract) == $contract->id ? 'selected' : '' }}>
                        {{ $contract->shartnoma_raqami }} - {{ $contract->tenant->name ?? '' }} (Qarz: {{ number_format($contract->jami_qarzdorlik / 1000000, 1) }} mln)
                    </option>
                    @endforeach
                </select>
                @error('contract_id') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Sana <span class="text-[#ef4444]">*</span></label>
                    <input type="date" name="tolov_sanasi" value="{{ old('tolov_sanasi', date('Y-m-d')) }}" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Summa (UZS) <span class="text-[#ef4444]">*</span></label>
                    <input type="number" step="any" name="summa" value="{{ old('summa') }}" required min="1"
                        class="form-input @error('summa') border-[#ef4444] @enderror" placeholder="1000000">
                    @error('summa') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="form-label">To'lov usuli</label>
                <select name="tolov_usuli" class="form-input form-select">
                    <option value="bank_otkazmasi" {{ old('tolov_usuli') == 'bank_otkazmasi' ? 'selected' : '' }}>Bank o'tkazmasi</option>
                    <option value="naqd" {{ old('tolov_usuli') == 'naqd' ? 'selected' : '' }}>Naqd</option>
                    <option value="karta" {{ old('tolov_usuli') == 'karta' ? 'selected' : '' }}>Karta</option>
                    <option value="onlayn" {{ old('tolov_usuli') == 'onlayn' ? 'selected' : '' }}>Onlayn</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Hujjat raqami <span class="text-[#64748b] text-xs">(takror-tagi himoya)</span></label>
                    <input type="text" name="hujjat_raqami" maxlength="100" value="{{ old('hujjat_raqami') }}" class="form-input" placeholder="T-03-7501986">
                </div>
                <div>
                    <label class="form-label">Izoh <span class="text-[#64748b] text-xs">(ixtiyoriy)</span></label>
                    <input type="text" name="izoh" maxlength="255" value="{{ old('izoh') }}" class="form-input">
                </div>
            </div>

            @error('*')
                <div class="text-[#ef4444] text-xs mt-1">{{ $message }}</div>
            @enderror

            <label class="flex items-center gap-2 text-sm text-[#cbd5f5] pt-2 select-none">
                <input type="checkbox" name="force" value="1" class="accent-[#f59e0b]">
                <span>Dublicate ogohlantirishini bekor qilish (<b>juda ehtiyotkorlik bilan</b>)</span>
            </label>

            <div class="flex items-center justify-between pt-4 border-t border-[rgba(56,189,248,0.08)]">
                <a href="{{ route('registry', ['tab' => 'payments']) }}" class="text-[#64748b] hover:text-[#e2e8f0] text-sm flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Orqaga
                </a>
                <div class="flex gap-3">
                    <a href="{{ route('registry', ['tab' => 'payments']) }}" class="btn btn-secondary">Bekor qilish</a>
                    <button type="submit" class="btn btn-success"
                        onclick="return confirm('Ushbu to\'lovni saqlashni tasdiqlaysizmi?\n\nDiqqat: fakt to\'lov to\'liq ASOSIY QARZGA yo\'naltiriladi, penya undan yechilmaydi.');">
                        ✓ Saqlash
                    </button>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection
