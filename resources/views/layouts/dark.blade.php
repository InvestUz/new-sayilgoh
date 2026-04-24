<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '"POYTAXT SAYILGOHI" DUK')</title>
    {{-- Default theme: LIGHT. Only users who explicitly chose "dark" stay dark. --}}
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('theme');
                if (saved !== 'dark') {
                    document.documentElement.classList.add('light');
                }
            } catch (e) {
                document.documentElement.classList.add('light');
            }
        })();
    </script>
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

        /* ======================================================================
           LIGHT MODE OVERRIDES
           Activated when <html> carries the "light" class.
           Uses high specificity + !important to defeat inline hex utility classes.
           ====================================================================== */
        html.light body { background: #f1f5f9 !important; color: #1e293b !important; }

        html.light ::-webkit-scrollbar-track { background: #e2e8f0; }
        html.light ::-webkit-scrollbar-thumb { background: #94a3b8; }
        html.light ::-webkit-scrollbar-thumb:hover { background: #0891b2; }

        /* Sidebar */
        html.light aside { background: #ffffff !important; border-right-color: #e2e8f0 !important; box-shadow: 1px 0 3px rgba(15, 23, 42, 0.04); }
        html.light aside > div:first-child { border-bottom-color: #e2e8f0 !important; }
        html.light aside h1 { color: #0e7490 !important; }
        html.light aside p { color: #64748b !important; }
        html.light .sidebar-link { color: #475569; }
        html.light .sidebar-link:hover { background: #f1f5f9; color: #0e7490; }
        html.light .sidebar-link.active { background: rgba(8, 145, 178, 0.12); color: #0e7490; border-left-color: #0891b2; }
        html.light aside nav > div { border-top-color: #e2e8f0 !important; }
        html.light aside nav p { color: #94a3b8 !important; }

        /* Header */
        html.light header { background: #ffffff !important; border-bottom-color: #e2e8f0 !important; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); }
        html.light header h2 { color: #0f172a !important; }
        html.light header p, html.light header span { color: #64748b !important; }

        /* Flash messages keep their colored backgrounds; tint backgrounds slightly for readability */
        html.light .bg-\[rgba\(34\,197\,94\,0\.1\)\] { background: #ecfdf5 !important; border-color: #a7f3d0 !important; }
        html.light .bg-\[rgba\(239\,68\,68\,0\.1\)\] { background: #fef2f2 !important; border-color: #fecaca !important; }

        /* Cards */
        html.light .card { background: #ffffff !important; border: 1px solid #e2e8f0 !important; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        html.light .card-header { border-bottom-color: #e2e8f0 !important; }
        html.light .card-title { color: #475569 !important; }

        /* Stat cards */
        html.light .stat-card { background: #ffffff !important; border: 1px solid #e2e8f0 !important; }
        html.light .stat-card:hover { background: #f8fafc !important; border-color: #0891b2 !important; }
        html.light .stat-label { color: #64748b !important; }
        html.light .stat-value { color: #0f172a !important; }
        html.light .stat-icon { color: #0891b2 !important; }

        /* Tables */
        html.light .table-dark th { background: #f8fafc !important; color: #475569 !important; border-bottom-color: #e2e8f0 !important; }
        html.light .table-dark td { color: #1e293b !important; border-bottom-color: #f1f5f9 !important; }
        html.light .table-dark tr:hover td { background: #f1f5f9 !important; }
        html.light .table-dark .text-cyan { color: #0891b2 !important; }

        /* Forms */
        html.light .form-input { background: #ffffff !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
        html.light .form-input:focus { border-color: #0891b2 !important; box-shadow: 0 0 0 2px rgba(8, 145, 178, 0.15) !important; }
        html.light .form-input::placeholder { color: #94a3b8 !important; }
        html.light .form-label { color: #475569 !important; }

        /* Buttons */
        html.light .btn-secondary { background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important; }
        html.light .btn-secondary:hover { background: #e2e8f0 !important; }

        /* Badges: keep colors, soften backgrounds */
        html.light .badge-success { background: #dcfce7; color: #15803d; }
        html.light .badge-danger { background: #fee2e2; color: #b91c1c; }
        html.light .badge-warning { background: #fef3c7; color: #b45309; }
        html.light .badge-info { background: #e0f2fe; color: #0369a1; }

        /* Pagination */
        html.light .pagination a, html.light .pagination span { background: #ffffff; border-color: #e2e8f0; color: #475569; }
        html.light .pagination a:hover { background: #f1f5f9; color: #0e7490; }
        html.light .pagination .active span { background: rgba(8, 145, 178, 0.1); color: #0e7490; border-color: #0891b2; }

        /* Generic hardcoded dark-hex utility classes used across pages */
        html.light .bg-\[\#0a1628\] { background: #f1f5f9 !important; }
        html.light .bg-\[\#0d1a2d\] { background: #ffffff !important; }
        html.light .bg-\[\#132238\] { background: #ffffff !important; }
        html.light .text-\[\#e2e8f0\] { color: #0f172a !important; }
        html.light .text-\[\#94a3b8\] { color: #475569 !important; }
        html.light .text-\[\#64748b\] { color: #64748b !important; }
        html.light .text-\[\#7dd3fc\] { color: #0e7490 !important; }
        html.light .text-\[\#38bdf8\] { color: #0891b2 !important; }
        html.light .border-\[rgba\(56\,189\,248\,0\.1\)\] { border-color: #e2e8f0 !important; }
        html.light .border-\[rgba\(56\,189\,248\,0\.08\)\] { border-color: #e2e8f0 !important; }

        /* ---------- Tailwind slate/color utility overrides for nested pages ---------- */
        /* Backgrounds: slate neutrals flip to white/off-white */
        html.light .bg-slate-900,
        html.light .bg-slate-800,
        html.light .bg-slate-800\/80,
        html.light .bg-slate-800\/60,
        html.light .bg-slate-800\/50 { background-color: #ffffff !important; }
        html.light .bg-slate-700,
        html.light .bg-slate-700\/50,
        html.light .bg-slate-700\/30 { background-color: #f1f5f9 !important; }
        html.light .bg-slate-600 { background-color: #e2e8f0 !important; }

        /* Text colors */
        html.light .text-white { color: #0f172a !important; }
        html.light .text-slate-200 { color: #1e293b !important; }
        html.light .text-slate-300 { color: #334155 !important; }
        html.light .text-slate-400 { color: #64748b !important; }
        html.light .text-slate-500 { color: #94a3b8 !important; }
        html.light .text-slate-600 { color: #cbd5e1 !important; }

        /* Borders */
        html.light .border-slate-500,
        html.light .border-slate-600,
        html.light .border-slate-700,
        html.light .border-slate-700\/50 { border-color: #e2e8f0 !important; }
        html.light .divide-slate-700\/50 > * + * { border-top-color: #e2e8f0 !important; }

        /* Accent backgrounds: soften to pastels in light mode */
        html.light .bg-blue-900\/20,
        html.light .bg-blue-900\/30 { background-color: #eff6ff !important; }
        html.light .bg-red-900\/10 { background-color: #fef2f2 !important; }
        html.light .bg-red-900\/20,
        html.light .bg-red-900\/30,
        html.light .bg-red-900\/40 { background-color: #fee2e2 !important; }
        html.light .bg-amber-900\/20 { background-color: #fffbeb !important; }
        html.light .bg-green-500\/10 { background-color: #dcfce7 !important; }
        html.light .bg-green-500\/20 { background-color: #bbf7d0 !important; }
        html.light .bg-red-500\/10 { background-color: #fee2e2 !important; }
        html.light .bg-amber-500\/10 { background-color: #fef3c7 !important; }

        /* Accent text colors tuned for readability on light bg */
        html.light .text-blue-300,
        html.light .text-blue-400 { color: #1d4ed8 !important; }
        html.light .text-green-400 { color: #15803d !important; }
        html.light .text-emerald-400 { color: #047857 !important; }
        html.light .text-red-400 { color: #b91c1c !important; }
        html.light .text-red-300 { color: #991b1b !important; }
        html.light .text-red-200,
        html.light .text-red-100 { color: #7f1d1d !important; }
        html.light .text-amber-400,
        html.light .text-amber-300 { color: #b45309 !important; }

        /* Semi-transparent accent borders (left stripes on cards) */
        html.light .border-l-green-500 { border-left-color: #22c55e !important; }
        html.light .border-l-red-500 { border-left-color: #ef4444 !important; }
        html.light .border-l-amber-500 { border-left-color: #f59e0b !important; }
        html.light .border-l-blue-500 { border-left-color: #3b82f6 !important; }

        /* Shadow polish for cards on light surfaces */
        html.light .bg-slate-800\/50,
        html.light .bg-slate-800\/80 { box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }

        /* Preserve modals/toasts that explicitly use bg-white or bg-gray-*: no change needed */

        /* Theme toggle button */
        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(56, 189, 248, 0.08);
            border: 1px solid rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, border-color 0.2s, transform 0.2s;
        }
        .theme-toggle:hover { background: rgba(56, 189, 248, 0.18); transform: rotate(12deg); }
        .theme-toggle svg { width: 18px; height: 18px; }
        html.light .theme-toggle { background: #f1f5f9; border-color: #cbd5e1; color: #b45309; }
        html.light .theme-toggle:hover { background: #e2e8f0; }
        html.light .theme-toggle .icon-sun { display: none; }
        .theme-toggle .icon-moon { display: none; }
        html.light .theme-toggle .icon-moon { display: inline; }
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
                <a href="{{ route('registry') }}" class="sidebar-link {{ request()->routeIs('registry*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    Reestr
                </a>
                <a href="{{ route('import-stats') }}" class="sidebar-link {{ request()->routeIs('import-stats') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Import Holati
                </a>

                <div class="pt-3 mt-3 border-t border-[rgba(56,189,248,0.1)]">
                    <p class="px-3 text-[10px] text-[#64748b] uppercase tracking-wider mb-2 font-semibold">Tezkor amallar</p>
                    <a href="{{ route('registry.contracts.create') }}" class="sidebar-link text-xs">
                        <svg class="w-4 h-4 text-[#38bdf8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Yangi shartnoma
                    </a>
                    <a href="{{ route('registry.payments.create') }}" class="sidebar-link text-xs">
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
                        <button
                            type="button"
                            class="theme-toggle"
                            onclick="toggleTheme()"
                            title="Mavzu almashtirish / Toggle theme"
                            aria-label="Mavzu almashtirish">
                            <svg class="icon-sun" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 3v1.5M12 19.5V21M4.22 4.22l1.06 1.06M18.72 18.72l1.06 1.06M3 12h1.5M19.5 12H21M4.22 19.78l1.06-1.06M18.72 5.28l1.06-1.06M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                            </svg>
                            <svg class="icon-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                            </svg>
                        </button>
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
    <script>
        function toggleTheme() {
            var root = document.documentElement;
            var isLight = root.classList.toggle('light');
            try {
                localStorage.setItem('theme', isLight ? 'light' : 'dark');
            } catch (e) {}
        }
    </script>
    @yield('scripts')
</body>
</html>
