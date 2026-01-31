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
                </select>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-[rgba(56,189,248,0.08)]">
                <a href="{{ route('registry', ['tab' => 'payments']) }}" class="text-[#64748b] hover:text-[#e2e8f0] text-sm flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Orqaga
                </a>
                <div class="flex gap-3">
                    <a href="{{ route('registry', ['tab' => 'payments']) }}" class="btn btn-secondary">Bekor qilish</a>
                    <button type="submit" class="btn btn-success">Saqlash</button>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection
