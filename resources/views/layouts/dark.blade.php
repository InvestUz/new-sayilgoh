<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '"POYTAXT SAYILGOHI" DUK')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        /* Scrollbar styling */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0d1a2d; }
        ::-webkit-scrollbar-thumb { background: #1e3a5f; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #38bdf8; }

        /* Custom styles */
        .sidebar-link { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #94a3b8; transition: all 0.2s; text-decoration: none; }
        .sidebar-link:hover { background: rgba(56, 189, 248, 0.1); color: #e2e8f0; }
        .sidebar-link.active { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border-left: 3px solid #38bdf8; }
        .sidebar-link svg { width: 18px; height: 18px; flex-shrink: 0; }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; text-decoration: none; border: none; }
        .btn-primary { background: linear-gradient(180deg, #0891b2 0%, #0e7490 100%); color: #fff; }
        .btn-primary:hover { background: #0891b2; }
        .btn-secondary { background: rgba(30, 58, 95, 0.8); border: 1px solid rgba(56, 189, 248, 0.2); color: #e2e8f0; }
        .btn-secondary:hover { background: rgba(56, 189, 248, 0.2); }
        .btn-success { background: linear-gradient(180deg, #059669 0%, #047857 100%); color: #fff; }
        .btn-danger { background: linear-gradient(180deg, #dc2626 0%, #b91c1c 100%); color: #fff; }

        .card { background: linear-gradient(180deg, #132238 0%, #0d1a2d 100%); border: 1px solid rgba(56, 189, 248, 0.1); border-radius: 8px; }
        .card-header { padding: 14px 16px; border-bottom: 1px solid rgba(56, 189, 248, 0.08); }
        .card-title { font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-body { padding: 16px; }

        .table-dark { width: 100%; border-collapse: collapse; }
        .table-dark th { padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(56, 189, 248, 0.1); background: rgba(10, 22, 40, 0.5); }
        .table-dark td { padding: 12px; font-size: 13px; color: #e2e8f0; border-bottom: 1px solid rgba(56, 189, 248, 0.05); }
        .table-dark tr:hover td { background: rgba(56, 189, 248, 0.05); }
        .table-dark .text-cyan { color: #38bdf8; }
        .table-dark .text-green { color: #22c55e; }
        .table-dark .text-red { color: #ef4444; }
        .table-dark .text-amber { color: #f59e0b; }

        .form-input { width: 100%; background: rgba(10, 22, 40, 0.8); border: 1px solid rgba(56, 189, 248, 0.2); color: #e2e8f0; padding: 10px 12px; border-radius: 6px; font-size: 13px; }
        .form-input:focus { outline: none; border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.1); }
        .form-input::placeholder { color: #64748b; }
        .form-label { display: block; font-size: 12px; font-weight: 500; color: #94a3b8; margin-bottom: 6px; }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 10px center; background-repeat: no-repeat; background-size: 16px; padding-right: 36px; }

        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-success { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .badge-info { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }

        .stat-card { background: rgba(10, 22, 40, 0.5); border: 1px solid rgba(56, 189, 248, 0.08); border-radius: 8px; padding: 16px; transition: all 0.2s; }
        .stat-card:hover { border-color: rgba(56, 189, 248, 0.3); background: rgba(56, 189, 248, 0.05); }
        .stat-icon { width: 40px; height: 40px; color: #38bdf8; }
        .stat-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 24px; font-weight: 700; color: #fff; line-height: 1.2; margin-top: 4px; }

        .pagination { display: flex; gap: 4px; }
        .pagination a, .pagination span { padding: 6px 12px; border-radius: 4px; font-size: 12px; color: #94a3b8; background: rgba(10, 22, 40, 0.5); border: 1px solid rgba(56, 189, 248, 0.1); text-decoration: none; }
        .pagination a:hover { background: rgba(56, 189, 248, 0.1); color: #38bdf8; }
        .pagination .active span { background: rgba(56, 189, 248, 0.2); color: #38bdf8; border-color: #38bdf8; }

        /* Print Styles */
        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .card { background: white !important; border: 1px solid #e5e7eb !important; }
            .table-dark th, .table-dark td { color: #111 !important; border-color: #e5e7eb !important; }
        }
    </style>
    @yield('styles')
</head>
<body class="bg-[#0a1628] text-[#e2e8f0] min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-56 bg-[#0d1a2d] border-r border-[rgba(56,189,248,0.1)] fixed h-full no-print">
            <div class="p-4 border-b border-[rgba(56,189,248,0.1)]">
                <h1 class="text-base font-bold text-[#7dd3fc]">"POYTAXT SAYILGOHI"</h1>
                <p class="text-[10px] text-[#64748b] mt-1">Davlat unitar korxonasi</p>
            </div>
            <nav class="p-3 space-y-1">
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Monitoring
                </a>
                <a href="{{ route('lots.index') }}" class="sidebar-link {{ request()->routeIs('lots.*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Lotlar
                </a>
                <a href="{{ route('tenants.index') }}" class="sidebar-link {{ request()->routeIs('tenants.*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Ijarachilar
                </a>
                <a href="{{ route('contracts.index') }}" class="sidebar-link {{ request()->routeIs('contracts.*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Shartnomalar
                </a>
                <a href="{{ route('payments.index') }}" class="sidebar-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    To'lovlar
                </a>
                <a href="{{ route('data-center') }}" class="sidebar-link {{ request()->routeIs('data-center') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Data Center
                </a>

                <div class="pt-3 mt-3 border-t border-[rgba(56,189,248,0.1)]">
                    <p class="px-3 text-[10px] text-[#64748b] uppercase tracking-wider mb-2 font-semibold">Tezkor amallar</p>
                    <a href="{{ route('contracts.create') }}" class="sidebar-link text-xs">
                        <svg class="w-4 h-4 text-[#38bdf8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Yangi shartnoma
                    </a>
                    <a href="{{ route('payments.create') }}" class="sidebar-link text-xs">
                        <svg class="w-4 h-4 text-[#22c55e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Yangi to'lov
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-56">
            <!-- Header -->
            <header class="bg-[#0d1a2d] border-b border-[rgba(56,189,248,0.1)] px-5 py-3 sticky top-0 z-10 no-print">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-[#e2e8f0]">@yield('header', 'Monitoring')</h2>
                        @hasSection('subheader')
                        <p class="text-xs text-[#64748b] mt-0.5">@yield('subheader')</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-[#64748b]">{{ date('d.m.Y H:i') }}</span>
                        @yield('header-actions')
                    </div>
                </div>
            </header>

            <!-- Flash Messages -->
            @if(session('success'))
            <div class="mx-5 mt-4">
                <div class="bg-[rgba(34,197,94,0.1)] border border-[rgba(34,197,94,0.3)] text-[#22c55e] px-4 py-3 rounded-lg text-sm flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
            </div>
            @endif
            @if(session('error'))
            <div class="mx-5 mt-4">
                <div class="bg-[rgba(239,68,68,0.1)] border border-[rgba(239,68,68,0.3)] text-[#ef4444] px-4 py-3 rounded-lg text-sm flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('error') }}
                </div>
            </div>
            @endif

            <!-- Content -->
            <div class="p-5">
                @yield('content')
            </div>
        </main>
    </div>
    @yield('scripts')
</body>
</html>
