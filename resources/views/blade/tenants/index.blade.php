@extends('layouts.dark')
@section('title', 'Ijarachilar')
@section('header', "Ijarachilar ro'yxati")
@section('subheader', 'Barcha ijarachilarni boshqarish')

@php
$totalTenants = $tenants->total();
$yuridik = \App\Models\Tenant::where('type', 'yuridik')->count();
$jismoniy = \App\Models\Tenant::where('type', 'jismoniy')->count();
@endphp

@section('header-actions')
<a href="{{ route('tenants.create') }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Yangi ijarachi
</a>
@endsection

@section('content')
<div class="space-y-5">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card">
            <div class="flex items-center gap-3">
                <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <div>
                    <p class="stat-label">Jami Ijarachilar</p>
                    <p class="stat-value">{{ $totalTenants }}</p>
                </div>
            </div>
        </div>
        <div class="stat-card border-l-2 border-l-[#38bdf8]">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#38bdf8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <div>
                    <p class="stat-label">Yuridik</p>
                    <p class="stat-value text-[#38bdf8]">{{ $yuridik }}</p>
                </div>
            </div>
        </div>
        <div class="stat-card border-l-2 border-l-[#22c55e]">
            <div class="flex items-center gap-3">
                <svg class="stat-icon text-[#22c55e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <div>
                    <p class="stat-label">Jismoniy</p>
                    <p class="stat-value text-[#22c55e]">{{ $jismoniy }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <h3 class="card-title">Ijarachilar ro'yxati</h3>
                    <p class="text-xs text-[#64748b] mt-1">{{ $tenants->total() }} ta ijarachi</p>
                </div>
                <form action="{{ route('tenants.index') }}" method="GET" class="flex items-center gap-2">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Qidirish..."
                            class="form-input pl-10 w-64">
                        <svg class="w-4 h-4 text-[#64748b] absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <button type="submit" class="btn btn-primary">Qidirish</button>
                    @if(request('search'))
                    <a href="{{ route('tenants.index') }}" class="btn btn-secondary">Tozalash</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table-dark">
                <thead>
                    <tr>
                        <th>Nomi</th>
                        <th>INN/PINFL</th>
                        <th>Telefon</th>
                        <th class="text-center">Faol lotlar</th>
                        <th class="text-center">Turi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                    <tr>
                        <td>
                            <a href="{{ route('tenants.show', $tenant) }}" class="text-cyan font-medium hover:underline">{{ $tenant->name }}</a>
                        </td>
                        <td class="font-mono text-xs">{{ $tenant->inn ?? '—' }}</td>
                        <td>{{ $tenant->phone ?? '—' }}</td>
                        <td class="text-center">
                            @php $lotCount = $tenant->activeContracts->count(); @endphp
                            @if($lotCount > 0)
                            <span class="badge badge-info">{{ $lotCount }}</span>
                            @else
                            <span class="text-[#64748b]">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($tenant->type == 'yuridik')
                            <span class="badge badge-info">Yuridik</span>
                            @else
                            <span class="badge badge-success">Jismoniy</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('tenants.show', $tenant) }}" class="text-[#38bdf8] hover:underline text-xs mr-2">Ko'rish</a>
                            <a href="{{ route('tenants.edit', $tenant) }}" class="text-[#64748b] hover:text-white text-xs mr-2">Tahrir</a>
                            <form action="{{ route('tenants.destroy', $tenant) }}" method="POST" class="inline" onsubmit="return confirm('O\'chirishni tasdiqlaysizmi?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[#64748b] hover:text-[#ef4444] text-xs">O'chir</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-10 text-[#64748b]">Ijarachilar yo'q</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tenants->hasPages())
        <div class="p-4 border-t border-[rgba(56,189,248,0.08)]">
            {{ $tenants->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
