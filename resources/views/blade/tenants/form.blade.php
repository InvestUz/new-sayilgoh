@extends('layouts.dark')
@section('title', isset($tenant) ? 'Ijarachini tahrirlash' : 'Yangi ijarachi')
@section('header', isset($tenant) ? $tenant->name : 'Yangi ijarachi')
@section('subheader', isset($tenant) ? 'Ijarachi ma\'lumotlarini tahrirlash' : 'Yangi ijarachi qo\'shish')

@section('content')
<div class="max-w-4xl">
    <form action="{{ isset($tenant) ? route('registry.tenants.update', $tenant) : route('registry.tenants.store') }}" method="POST" class="space-y-5">
        @csrf
        @if(isset($tenant)) @method('PUT') @endif

        <!-- Asosiy ma'lumotlar -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Asosiy ma'lumotlar</h3>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="form-label">Korxona/Shaxs nomi <span class="text-[#ef4444]">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $tenant->name ?? '') }}" required
                        class="form-input @error('name') border-[#ef4444] @enderror"
                        placeholder="ASIA TRADE GROUP MCHJ">
                    @error('name') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Shaxs turi</label>
                        <select name="type" class="form-input form-select">
                            <option value="yuridik" {{ old('type', $tenant->type ?? 'yuridik') == 'yuridik' ? 'selected' : '' }}>Yuridik shaxs</option>
                            <option value="jismoniy" {{ old('type', $tenant->type ?? '') == 'jismoniy' ? 'selected' : '' }}>Jismoniy shaxs</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">INN/STIR <span class="text-[#ef4444]">*</span></label>
                        <input type="text" name="inn" value="{{ old('inn', $tenant->inn ?? '') }}" required
                            class="form-input font-mono @error('inn') border-[#ef4444] @enderror"
                            placeholder="123456789">
                        @error('inn') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">OKED</label>
                        <input type="text" name="oked" value="{{ old('oked', $tenant->oked ?? '') }}"
                            class="form-input font-mono" placeholder="47190">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Direktor F.I.O</label>
                        <input type="text" name="director_name" value="{{ old('director_name', $tenant->director_name ?? '') }}"
                            class="form-input" placeholder="Karimov Jasur Abdullayevich">
                    </div>
                    <div>
                        <label class="form-label">Pasport seriya va raqami</label>
                        <input type="text" name="passport_serial" value="{{ old('passport_serial', $tenant->passport_serial ?? '') }}"
                            class="form-input font-mono" placeholder="AB 1234567">
                    </div>
                </div>
            </div>
        </div>

        <!-- Aloqa ma'lumotlari -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Aloqa ma'lumotlari</h3>
            </div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Telefon raqami <span class="text-[#ef4444]">*</span></label>
                        <input type="text" name="phone" value="{{ old('phone', $tenant->phone ?? '') }}" required
                            class="form-input @error('phone') border-[#ef4444] @enderror"
                            placeholder="+998 90 123 45 67">
                        @error('phone') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Elektron pochta</label>
                        <input type="email" name="email" value="{{ old('email', $tenant->email ?? '') }}"
                            class="form-input" placeholder="info@company.uz">
                        @error('email') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="form-label">Yuridik manzil <span class="text-[#ef4444]">*</span></label>
                    <textarea name="address" rows="2" required
                        class="form-input @error('address') border-[#ef4444] @enderror"
                        placeholder="Toshkent shahar, Mirzo Ulug'bek tumani...">{{ old('address', $tenant->address ?? '') }}</textarea>
                    @error('address') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Bank rekvizitlari -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Bank rekvizitlari</h3>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="form-label">Bank nomi</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $tenant->bank_name ?? '') }}"
                        class="form-input" placeholder="KAPITALBANK ATB">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Hisob raqami</label>
                        <input type="text" name="bank_account" value="{{ old('bank_account', $tenant->bank_account ?? '') }}"
                            class="form-input font-mono" placeholder="20208000100123456001">
                    </div>
                    <div>
                        <label class="form-label">MFO</label>
                        <input type="text" name="bank_mfo" value="{{ old('bank_mfo', $tenant->bank_mfo ?? '') }}"
                            class="form-input font-mono" placeholder="01058">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tugmalar -->
        <div class="flex items-center justify-between pt-2">
            <a href="{{ isset($tenant) ? route('registry.tenants.show', $tenant) : route('registry', ['tab' => 'tenants']) }}" class="text-[#64748b] hover:text-[#e2e8f0] text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Orqaga
            </a>
            <div class="flex gap-3">
                <a href="{{ isset($tenant) ? route('registry.tenants.show', $tenant) : route('registry', ['tab' => 'tenants']) }}" class="btn btn-secondary">Bekor qilish</a>
                <button type="submit" class="btn btn-primary">
                    {{ isset($tenant) ? 'Saqlash' : 'Yaratish' }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
