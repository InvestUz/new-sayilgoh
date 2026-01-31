@extends('layouts.dark')
@section('title', isset($contract) ? 'Shartnoma tahrirlash' : 'Yangi shartnoma')
@section('header', isset($contract) ? $contract->shartnoma_raqami : 'Yangi shartnoma')
@section('subheader', isset($contract) ? 'Shartnoma ma\'lumotlarini tahrirlash' : 'Yangi shartnoma yaratish')

@section('content')
<div class="max-w-2xl">
    @if(!isset($contract))
        @if($tenants->isEmpty())
        <div class="card mb-4">
            <div class="card-body flex items-center gap-3 text-[#f59e0b]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                <span>Shartnoma yaratish uchun avval <a href="{{ route('registry.tenants.create') }}" class="underline text-[#38bdf8]">ijarachi</a> yarating.</span>
            </div>
        </div>
        @endif

        @if($lots->isEmpty())
        <div class="card mb-4">
            <div class="card-body flex items-center gap-3 text-[#f59e0b]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                <span>Bo'sh lotlar yo'q. <a href="{{ route('registry.lots.create') }}" class="underline text-[#38bdf8]">Yangi lot yarating</a>.</span>
            </div>
        </div>
        @endif
    @endif

    <form action="{{ isset($contract) ? route('registry.contracts.update', $contract) : route('registry.contracts.store') }}" method="POST" class="card">
        @csrf
        @if(isset($contract)) @method('PUT') @endif

        <div class="card-header">
            <h3 class="card-title">{{ isset($contract) ? 'Shartnoma tahrirlash' : 'Shartnoma ma\'lumotlari' }}</h3>
        </div>
        <div class="card-body space-y-4">
            @if(isset($contract))
            <!-- Edit mode -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Lot</label>
                    <div class="form-input bg-[#0d1a2d] text-[#64748b]">{{ $contract->lot->lot_raqami }} - {{ $contract->lot->obyekt_nomi }}</div>
                </div>
                <div>
                    <label class="form-label">Ijarachi</label>
                    <div class="form-input bg-[#0d1a2d] text-[#64748b]">{{ $contract->tenant->name }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Shartnoma raqami</label>
                    <div class="form-input bg-[#0d1a2d] text-[#64748b] font-mono">{{ $contract->shartnoma_raqami }}</div>
                </div>
                <div>
                    <label class="form-label">Shartnoma sanasi <span class="text-[#ef4444]">*</span></label>
                    <input type="date" name="shartnoma_sanasi" value="{{ old('shartnoma_sanasi', $contract->shartnoma_sanasi->format('Y-m-d')) }}" required class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Boshlanish sanasi</label>
                    <div class="form-input bg-[#0d1a2d] text-[#64748b]">{{ $contract->boshlanish_sanasi->format('d.m.Y') }}</div>
                </div>
                <div>
                    <label class="form-label">Auksion sanasi <span class="text-[#ef4444]">*</span></label>
                    <input type="date" name="auksion_sanasi" value="{{ old('auksion_sanasi', $contract->auksion_sanasi->format('Y-m-d')) }}" required class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Summa</label>
                    <div class="form-input bg-[#0d1a2d] text-[#38bdf8]">{{ number_format($contract->shartnoma_summasi / 1000000, 1) }} mln UZS</div>
                </div>
                <div>
                    <label class="form-label">Muddat</label>
                    <div class="form-input bg-[#0d1a2d] text-[#64748b]">{{ $contract->shartnoma_muddati }} oy</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">To'lov kuni</label>
                    <select name="tolov_kuni" class="form-input form-select">
                        @for($i = 1; $i <= 31; $i++)
                            <option value="{{ $i }}" {{ $contract->tolov_kuni == $i ? 'selected' : '' }}>{{ $i }}-kun</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="form-label">Penya muddati (kun)</label>
                    <select name="penya_muddati" class="form-input form-select">
                        @for($i = 1; $i <= 30; $i++)
                            <option value="{{ $i }}" {{ $contract->penya_muddati == $i ? 'selected' : '' }}>{{ $i }} kun</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div>
                <label class="form-label">Izoh</label>
                <textarea name="izoh" rows="2" class="form-input">{{ old('izoh', $contract->izoh) }}</textarea>
            </div>

            @else
            <!-- Create mode -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Lot <span class="text-[#ef4444]">*</span></label>
                    <select name="lot_id" required class="form-input form-select @error('lot_id') border-[#ef4444] @enderror">
                        <option value="">Tanlang...</option>
                        @foreach($lots as $lot)
                        <option value="{{ $lot->id }}" {{ old('lot_id', request('lot_id')) == $lot->id ? 'selected' : '' }}>{{ $lot->lot_raqami }} - {{ $lot->obyekt_nomi }}</option>
                        @endforeach
                    </select>
                    @error('lot_id') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Ijarachi <span class="text-[#ef4444]">*</span></label>
                    <select name="tenant_id" required class="form-input form-select @error('tenant_id') border-[#ef4444] @enderror">
                        <option value="">Tanlang...</option>
                        @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}" {{ old('tenant_id') == $tenant->id ? 'selected' : '' }}>{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    @error('tenant_id') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Shartnoma raqami <span class="text-[#ef4444]">*</span></label>
                    <input type="text" name="shartnoma_raqami" value="{{ old('shartnoma_raqami') }}" required class="form-input @error('shartnoma_raqami') border-[#ef4444] @enderror">
                    @error('shartnoma_raqami') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Shartnoma sanasi <span class="text-[#ef4444]">*</span></label>
                    <input type="date" name="shartnoma_sanasi" value="{{ old('shartnoma_sanasi', date('Y-m-d')) }}" required class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Boshlanish sanasi <span class="text-[#ef4444]">*</span></label>
                    <input type="date" name="boshlanish_sanasi" value="{{ old('boshlanish_sanasi', date('Y-m-d')) }}" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Auksion sanasi <span class="text-[#ef4444]">*</span></label>
                    <input type="date" name="auksion_sanasi" value="{{ old('auksion_sanasi', date('Y-m-d')) }}" required class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Summa (UZS) <span class="text-[#ef4444]">*</span></label>
                    <input type="number" step="any" name="shartnoma_summasi" value="{{ old('shartnoma_summasi') }}" required class="form-input @error('shartnoma_summasi') border-[#ef4444] @enderror">
                    @error('shartnoma_summasi') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Muddat (oy) <span class="text-[#ef4444]">*</span></label>
                    <input type="number" name="shartnoma_muddati" value="{{ old('shartnoma_muddati', 60) }}" required min="1" class="form-input">
                    <p class="text-xs text-[#64748b] mt-1">Default: 60 oy (5 yil)</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">To'lov kuni</label>
                    <select name="tolov_kuni" class="form-input form-select">
                        @for($i = 1; $i <= 31; $i++)
                            <option value="{{ $i }}" {{ old('tolov_kuni', 10) == $i ? 'selected' : '' }}>{{ $i }}-kun</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="form-label">Penya muddati (kun)</label>
                    <select name="penya_muddati" class="form-input form-select">
                        @for($i = 1; $i <= 30; $i++)
                            <option value="{{ $i }}" {{ old('penya_muddati', 10) == $i ? 'selected' : '' }}>{{ $i }} kun</option>
                        @endfor
                    </select>
                </div>
            </div>
            @endif

            <div class="flex items-center justify-between pt-4 border-t border-[rgba(56,189,248,0.08)]">
                <a href="{{ isset($contract) ? route('registry.lots.show', $contract->lot) : route('registry', ['tab' => 'lots']) }}" class="text-[#64748b] hover:text-[#e2e8f0] text-sm flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Orqaga
                </a>
                <div class="flex gap-3">
                    <a href="{{ isset($contract) ? route('registry.lots.show', $contract->lot) : route('registry', ['tab' => 'lots']) }}" class="btn btn-secondary">Bekor qilish</a>
                    <button type="submit" class="btn btn-primary">{{ isset($contract) ? 'Saqlash' : 'Yaratish' }}</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
