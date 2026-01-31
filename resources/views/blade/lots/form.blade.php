@extends('layouts.dark')
@section('title', isset($lot) ? 'Lotni tahrirlash' : 'Yangi lot')
@section('header', isset($lot) ? 'Lotni tahrirlash' : 'Yangi lot')
@section('subheader', isset($lot) ? 'Lot ma\'lumotlarini tahrirlash' : 'Yangi lot qo\'shish')

@section('content')
<div class="max-w-4xl">
    <form action="{{ isset($lot) ? route('registry.lots.update', $lot) : route('registry.lots.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @if(isset($lot)) @method('PUT') @endif

        <!-- Basic Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Asosiy ma'lumotlar</h3>
            </div>
            <div class="card-body">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Lot raqami <span class="text-[#ef4444]">*</span></label>
                        <input type="text" name="lot_raqami" value="{{ old('lot_raqami', $lot->lot_raqami ?? '') }}" required class="form-input @error('lot_raqami') border-[#ef4444] @enderror">
                        @error('lot_raqami') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Obyekt nomi <span class="text-[#ef4444]">*</span></label>
                        <input type="text" name="obyekt_nomi" value="{{ old('obyekt_nomi', $lot->obyekt_nomi ?? '') }}" required class="form-input @error('obyekt_nomi') border-[#ef4444] @enderror">
                        @error('obyekt_nomi') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Obyekt turi</label>
                        <select name="obyekt_turi" class="form-input form-select">
                            <option value="savdo" {{ old('obyekt_turi', $lot->obyekt_turi ?? '') == 'savdo' ? 'selected' : '' }}>Savdo obyekti</option>
                            <option value="xizmat" {{ old('obyekt_turi', $lot->obyekt_turi ?? '') == 'xizmat' ? 'selected' : '' }}>Xizmat ko'rsatish</option>
                            <option value="ishlab_chiqarish" {{ old('obyekt_turi', $lot->obyekt_turi ?? '') == 'ishlab_chiqarish' ? 'selected' : '' }}>Ishlab chiqarish</option>
                            <option value="ombor" {{ old('obyekt_turi', $lot->obyekt_turi ?? '') == 'ombor' ? 'selected' : '' }}>Ombor</option>
                            <option value="ofis" {{ old('obyekt_turi', $lot->obyekt_turi ?? '') == 'ofis' ? 'selected' : '' }}>Ofis</option>
                            <option value="boshqa" {{ old('obyekt_turi', $lot->obyekt_turi ?? '') == 'boshqa' ? 'selected' : '' }}>Boshqa</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Holat</label>
                        <select name="holat" class="form-input form-select">
                            <option value="bosh" {{ old('holat', $lot->holat ?? 'bosh') == 'bosh' ? 'selected' : '' }}>Bo'sh</option>
                            <option value="ijarada" {{ old('holat', $lot->holat ?? '') == 'ijarada' ? 'selected' : '' }}>Ijarada</option>
                            <option value="band" {{ old('holat', $lot->holat ?? '') == 'band' ? 'selected' : '' }}>Band</option>
                            <option value="tamirlashda" {{ old('holat', $lot->holat ?? '') == 'tamirlashda' ? 'selected' : '' }}>Ta'mirlashda</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Size and Details -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">O'lcham va tafsilotlar</h3>
            </div>
            <div class="card-body">
                <div class="grid md:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label">Maydon (m²) <span class="text-[#ef4444]">*</span></label>
                        <input type="number" step="0.01" name="maydon" value="{{ old('maydon', $lot->maydon ?? '') }}" required class="form-input @error('maydon') border-[#ef4444] @enderror">
                        @error('maydon') <p class="text-[#ef4444] text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Xonalar soni</label>
                        <input type="number" name="xonalar_soni" value="{{ old('xonalar_soni', $lot->xonalar_soni ?? '') }}" min="0" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Qavat</label>
                        <input type="number" name="qavat" value="{{ old('qavat', $lot->qavat ?? '') }}" min="0" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Qavatlar soni</label>
                        <input type="number" name="qavatlar_soni" value="{{ old('qavatlar_soni', $lot->qavatlar_soni ?? '') }}" min="1" class="form-input">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Kadastr raqami</label>
                        <input type="text" name="kadastr_raqami" value="{{ old('kadastr_raqami', $lot->kadastr_raqami ?? '') }}" class="form-input font-mono">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Boshlang'ich narx (UZS)</label>
                        <input type="number" name="boshlangich_narx" value="{{ old('boshlangich_narx', $lot->boshlangich_narx ?? '') }}" class="form-input">
                    </div>
                </div>
            </div>
        </div>

        <!-- Address -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Manzil</h3>
            </div>
            <div class="card-body">
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Tuman</label>
                        <input type="text" name="tuman" value="{{ old('tuman', $lot->tuman ?? '') }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Ko'cha</label>
                        <input type="text" name="kocha" value="{{ old('kocha', $lot->kocha ?? '') }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Uy raqami</label>
                        <input type="text" name="uy_raqami" value="{{ old('uy_raqami', $lot->uy_raqami ?? '') }}" class="form-input">
                    </div>
                    <div class="md:col-span-3">
                        <label class="form-label">To'liq manzil</label>
                        <input type="text" name="manzil" value="{{ old('manzil', $lot->manzil ?? '') }}" class="form-input">
                    </div>
                </div>
            </div>
        </div>

        <!-- Location -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Joylashuv (Xarita)</h3>
            </div>
            <div class="card-body">
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Latitude (Kenglik)</label>
                        <input type="text" name="latitude" value="{{ old('latitude', $lot->latitude ?? '') }}" placeholder="41.311081" class="form-input font-mono">
                        <p class="text-xs text-[#64748b] mt-1">Masalan: 41.311081</p>
                    </div>
                    <div>
                        <label class="form-label">Longitude (Uzunlik)</label>
                        <input type="text" name="longitude" value="{{ old('longitude', $lot->longitude ?? '') }}" placeholder="69.240562" class="form-input font-mono">
                        <p class="text-xs text-[#64748b] mt-1">Masalan: 69.240562</p>
                    </div>
                    <div>
                        <label class="form-label">Xarita URL</label>
                        <input type="url" name="map_url" value="{{ old('map_url', $lot->map_url ?? '') }}" placeholder="https://..." class="form-input">
                        <p class="text-xs text-[#64748b] mt-1">Google/Yandex Maps URL</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Utilities -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Kommunikatsiyalar</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach([
                        'has_elektr' => 'Elektr',
                        'has_gaz' => 'Gaz',
                        'has_suv' => 'Suv',
                        'has_kanalizatsiya' => 'Kanalizatsiya',
                        'has_internet' => 'Internet',
                        'has_isitish' => 'Isitish',
                        'has_konditsioner' => 'Konditsioner'
                    ] as $field => $label)
                    <label class="flex items-center gap-3 p-3 border border-[rgba(56,189,248,0.1)] rounded-lg cursor-pointer hover:bg-[rgba(56,189,248,0.05)] transition">
                        <input type="checkbox" name="{{ $field }}" value="1" {{ old($field, $lot->$field ?? false) ? 'checked' : '' }} class="w-4 h-4 rounded border-[#64748b] bg-[#0d1a2d] checked:bg-[#38bdf8]">
                        <span class="text-sm">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Images -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Rasmlar</h3>
            </div>
            <div class="card-body">
                @php
                $existingImages = isset($lot) ? ($lot->rasmlar ?? []) : [];
                $mainImageIndex = isset($lot) ? ($lot->main_image_index ?? 0) : 0;
                @endphp

                @if(count($existingImages) > 0)
                <div class="mb-4">
                    <p class="text-sm text-[#94a3b8] mb-3">Mavjud rasmlar</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach($existingImages as $index => $rasm)
                        <div class="relative group">
                            <div class="aspect-square bg-[#0d1a2d] rounded-lg overflow-hidden border-2 {{ $index == $mainImageIndex ? 'border-[#22c55e]' : 'border-transparent' }}">
                                <img src="{{ asset('storage/' . $rasm) }}" class="w-full h-full object-cover">
                            </div>
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition rounded-lg flex flex-col justify-between p-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="main_image_index" value="{{ $index }}" {{ $index == $mainImageIndex ? 'checked' : '' }} class="w-3 h-3">
                                    <span class="text-white text-xs">Asosiy</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="delete_images[]" value="{{ $index }}" class="w-3 h-3">
                                    <span class="text-[#ef4444] text-xs">O'chirish</span>
                                </label>
                            </div>
                            @if($index == $mainImageIndex)
                            <span class="absolute top-2 left-2 px-2 py-0.5 bg-[#22c55e] text-white text-xs rounded">Asosiy</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div>
                    <label class="form-label">{{ count($existingImages) > 0 ? 'Qo\'shimcha rasmlar' : 'Rasmlar yuklash' }}</label>
                    <input type="file" name="rasmlar[]" multiple accept="image/*" class="form-input">
                    <p class="text-xs text-[#64748b] mt-1">JPG, PNG formatida</p>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tavsif</h3>
            </div>
            <div class="card-body">
                <textarea name="tavsif" rows="3" class="form-input" placeholder="Obyekt haqida qo'shimcha ma'lumotlar...">{{ old('tavsif', $lot->tavsif ?? '') }}</textarea>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('registry', ['tab' => 'lots']) }}" class="text-[#64748b] hover:text-[#e2e8f0] text-sm flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Orqaga
            </a>
            <div class="flex gap-3">
                <a href="{{ route('registry', ['tab' => 'lots']) }}" class="btn btn-secondary">Bekor qilish</a>
                <button type="submit" class="btn btn-primary">Saqlash</button>
            </div>
        </div>
    </form>
</div>
@endsection
