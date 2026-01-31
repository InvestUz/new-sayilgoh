<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>"POYTAXT SAYILGOHI" DUK</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        html, body { height: 100%; overflow: hidden; }
        body { background: #0a1628; color: #e2e8f0; }

        .dashboard {
            height: 100vh;
            display: grid;
            grid-template-rows: auto auto 1fr;
            padding: 10px 14px;
            gap: 10px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 6px;
            border-bottom: 1px solid rgba(56, 189, 248, 0.15);
        }
        .header h1 { font-size: 20px; font-weight: 600; color: #7dd3fc; }
        .header-right { display: flex; align-items: center; gap: 12px; }
        .header-date { font-size: 11px; color: #64748b; }
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: 600;
            background: rgba(34, 197, 94, 0.2); color: #22c55e;
        }
        .status-badge svg { width: 8px; height: 8px; }

        /* Filters Bar */
        .filters-bar {
            display: flex; align-items: center; gap: 10px;
            padding: 6px 10px;
            background: rgba(19, 34, 56, 0.6);
            border: 1px solid rgba(56, 189, 248, 0.1);
            border-radius: 4px;
        }
        .filter-group { display: flex; align-items: center; gap: 4px; }
        .filter-label { font-size: 10px; color: #64748b; text-transform: uppercase; }
        .filter-select {
            background: rgba(10, 22, 40, 0.8);
            border: 1px solid rgba(56, 189, 248, 0.2);
            color: #e2e8f0; padding: 4px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;
        }
        .filter-select:focus { outline: none; border-color: #38bdf8; }
        .filter-select option { background: #0a1628; }
        .filter-btn {
            background: linear-gradient(180deg, #0891b2 0%, #0e7490 100%);
            border: none; color: #fff; padding: 4px 12px; border-radius: 3px;
            font-size: 11px; font-weight: 500; cursor: pointer;
        }
        .filter-btn:hover { background: #0891b2; }
        .filter-btn.secondary { background: rgba(30, 58, 95, 0.8); border: 1px solid rgba(56, 189, 248, 0.2); }
        .quick-links { margin-left: auto; display: flex; gap: 6px; }
        .quick-link {
            display: flex; align-items: center; gap: 3px;
            padding: 4px 8px; background: rgba(10, 22, 40, 0.6);
            border: 1px solid rgba(56, 189, 248, 0.15); border-radius: 3px;
            color: #94a3b8; font-size: 10px; text-decoration: none;
        }
        .quick-link:hover { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
        .quick-link svg { width: 12px; height: 12px; }

        /* Main Grid - 3 columns */
        .main-grid {
            display: grid;
            grid-template-columns: 240px 1fr 260px;
            gap: 10px;
            min-height: 0;
        }

        .column { display: flex; flex-direction: column; gap: 8px; min-height: 0; }

        /* Panel */
        .panel {
            background: linear-gradient(180deg, #132238 0%, #0d1a2d 100%);
            border: 1px solid rgba(56, 189, 248, 0.1);
            border-radius: 5px;
            padding: 8px 10px;
        }
        .panel-title {
            font-size: 10px; font-weight: 600; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 6px; padding-bottom: 4px;
            border-bottom: 1px solid rgba(56, 189, 248, 0.08);
        }

        /* Top Stats Row */
        .stats-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .stat-card {
            background: rgba(10, 22, 40, 0.5);
            border: 1px solid rgba(56, 189, 248, 0.08);
            border-radius: 4px; padding: 8px;
            display: flex; align-items: center; gap: 8px;
        }
        .stat-card:hover { border-color: rgba(56, 189, 248, 0.3); }
        .stat-card a { text-decoration: none; color: inherit; display: flex; align-items: center; gap: 8px; width: 100%; }
        .stat-icon { width: 28px; height: 28px; color: #38bdf8; flex-shrink: 0; }
        .stat-info h4 { font-size: 9px; color: #64748b; font-weight: 500; text-transform: uppercase; }
        .stat-info .val { font-size: 18px; font-weight: 700; color: #fff; line-height: 1; }
        .stat-info .val.cyan { color: #38bdf8; }
        .stat-info .val.red { color: #ef4444; }

        /* List Stats */
        .list-stat {
            display: flex; justify-content: space-between; align-items: center;
            padding: 5px 0; border-bottom: 1px solid rgba(56, 189, 248, 0.05);
        }
        .list-stat:last-child { border-bottom: none; }
        .list-stat-left { display: flex; align-items: center; gap: 5px; }
        .list-stat-icon { width: 14px; height: 14px; color: #38bdf8; }
        .list-stat-name { font-size: 11px; color: #94a3b8; }
        .list-stat-val { font-size: 13px; font-weight: 700; color: #38bdf8; }
        .list-stat-val.white { color: #fff; }
        .list-stat-val.green { color: #22c55e; }
        .list-stat-val.red { color: #ef4444; }

        /* Map */
        .map-panel { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        .map-container {
            flex: 1; position: relative;
            background: rgba(10, 22, 40, 0.3); border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            min-height: 0; overflow: hidden;
            padding: 10px;
        }
        .uzbekistan-map { width: 85%; height: 85%; object-fit: contain; }
        .map-marker { position: absolute; top: 28%; left: 58%; transform: translate(-50%, -50%); z-index: 10; }
        .marker-label {
            background: rgba(19, 34, 56, 0.95);
            border: 1px solid rgba(56, 189, 248, 0.4);
            padding: 4px 8px; border-radius: 3px;
            font-size: 9px; font-weight: 600; color: #e2e8f0;
        }
        .marker-dot {
            width: 8px; height: 8px; background: #3b82f6; border-radius: 50%;
            margin: 4px auto 0; position: relative;
        }
        .marker-dot::before {
            content: ''; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 16px; height: 16px;
            background: rgba(59, 130, 246, 0.25); border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            50% { transform: translate(-50%, -50%) scale(1.5); opacity: 0; }
        }

        /* Bottom Row - Chart + Finance side by side */
        .bottom-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .chart-panel { display: flex; flex-direction: column; }
        .chart-legend { display: flex; gap: 10px; margin-top: 4px; font-size: 9px; }
        .chart-legend span { display: flex; align-items: center; gap: 3px; }
        .legend-dot { width: 8px; height: 8px; border-radius: 50%; }

        /* Finance Grid - 2x2 */
        .finance-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; height: 100%; }
        .finance-card {
            background: rgba(10, 22, 40, 0.4); border-radius: 4px;
            padding: 10px; border-left: 3px solid #38bdf8;
            display: flex; flex-direction: column; justify-content: center;
        }
        .finance-card.green { border-left-color: #22c55e; }
        .finance-card.red { border-left-color: #ef4444; }
        .finance-card.amber { border-left-color: #f59e0b; }
        .finance-card.orange { border-left-color: #f97316; }
        .finance-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
        .finance-val { font-size: 20px; font-weight: 700; color: #fff; margin-top: 4px; line-height: 1; }
        .finance-val span { font-size: 12px; font-weight: 400; color: #64748b; }
        .finance-val.green { color: #22c55e; }
        .finance-val.red { color: #ef4444; }
        .finance-val.amber { color: #f59e0b; }
        .finance-val.orange { color: #f97316; }
        .finance-sub { font-size: 9px; color: #64748b; margin-top: 4px; }

        /* Progress */
        .progress-row { margin-bottom: 6px; }
        .progress-row:last-child { margin-bottom: 0; }
        .progress-top { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .progress-name { font-size: 10px; color: #94a3b8; }
        .progress-vals { font-size: 10px; font-weight: 600; color: #38bdf8; }
        .progress-vals.red { color: #ef4444; }
        .progress-vals.amber { color: #f59e0b; }
        .progress-track { height: 4px; background: rgba(30, 58, 95, 0.8); border-radius: 2px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%); border-radius: 2px; }
        .progress-fill.red { background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%); }
        .progress-fill.amber { background: linear-gradient(90deg, #d97706 0%, #f59e0b 100%); }

        /* Donut small */
        .donut-container { height: 100px; position: relative; display: block; }
        .donut-container canvas { width: 100% !important; height: 100% !important; }
        .donut-legend { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; font-size: 9px; }
        .donut-legend span { display: flex; align-items: center; gap: 3px; }

        /* Chart container fix */
        .chart-container { height: 140px; position: relative; display: block; }
        .chart-container canvas { width: 100% !important; height: 100% !important; }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <div class="header">
            <h1>"POYTAXT SAYILGOHI" DUK</h1>
            <div class="header-right">
                <span class="status-badge">
                    <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>
                    LIVE
                </span>
                <div class="header-date">{{ date('d.m.Y H:i') }} | Toshkent</div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <div class="filter-group">
                <span class="filter-label">Yil:</span>
                <select class="filter-select" id="yearFilter">
                    <option value="">Barchasi</option>
                    @foreach($years as $y)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Davr:</span>
                <select class="filter-select" id="periodFilter">
                    <option value="month" {{ $period == 'month' ? 'selected' : '' }}>Oylik</option>
                    <option value="quarter" {{ $period == 'quarter' ? 'selected' : '' }}>Choraklik</option>
                    <option value="year" {{ $period == 'year' ? 'selected' : '' }}>Yillik</option>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Holat:</span>
                <select class="filter-select" id="statusFilter">
                    <option value="all" {{ $status == 'all' ? 'selected' : '' }}>Barchasi</option>
                    <option value="muddati_otgan" {{ $status == 'muddati_otgan' ? 'selected' : '' }}>Muddati o'tgan</option>
                    <option value="kutilmoqda" {{ $status == 'kutilmoqda' ? 'selected' : '' }}>Kutilmoqda</option>
                    <option value="tolangan" {{ $status == 'tolangan' ? 'selected' : '' }}>To'langan</option>
                </select>
            </div>
            <button class="filter-btn" onclick="applyFilters()">Qo'llash</button>
            <button class="filter-btn secondary" onclick="window.location.href='/data-center'">Tozalash</button>

            <div class="quick-links">
                <a href="{{ route('dashboard') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Monitoring
                </a>
                <a href="{{ route('registry', ['tab' => 'lots']) }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Lotlar
                </a>
                <a href="{{ route('registry', ['tab' => 'tenants']) }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    Ijarachilar
                </a>
                <a href="{{ route('registry') }}" class="quick-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Shartnomalar
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-grid">
            <!-- Left Column -->
            <div class="column">
                <div class="panel">
                    <div class="panel-title">Jismoniy ko'rsatkichlar</div>
                    <div class="list-stat">
                        <div class="list-stat-left">
                            <svg class="list-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="16" height="12" rx="1"/><line x1="12" y1="15" x2="12" y2="19"/><line x1="8" y1="19" x2="16" y2="19"/></svg>
                            <span class="list-stat-name">Jami Lotlar</span>
                        </div>
                        <span class="list-stat-val white">{{ number_format($totalLots) }}</span>
                    </div>
                    <div class="list-stat">
                        <div class="list-stat-left">
                            <svg class="list-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="8" fill="rgba(34, 197, 94, 0.2)"/><path d="M9 12l2 2 4-4"/></svg>
                            <span class="list-stat-name">Band Lotlar</span>
                        </div>
                        <span class="list-stat-val green">{{ number_format($activeLots) }}</span>
                    </div>
                    <div class="list-stat">
                        <div class="list-stat-left">
                            <svg class="list-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="8"/></svg>
                            <span class="list-stat-name">Bo'sh Lotlar</span>
                        </div>
                        <span class="list-stat-val">{{ number_format($vacantLots) }}</span>
                    </div>
                    <div class="list-stat">
                        <div class="list-stat-left">
                            <svg class="list-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="4" width="16" height="16" rx="1"/><path d="M4 12h16"/><path d="M12 4v16"/></svg>
                            <span class="list-stat-name">Umumiy Maydon</span>
                        </div>
                        <span class="list-stat-val white">{{ number_format($umumiyMaydon, 0) }} m²</span>
                    </div>
                    <div class="list-stat">
                        <div class="list-stat-left">
                            <svg class="list-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="list-stat-name">Faol Shartnomalar</span>
                        </div>
                        <span class="list-stat-val">{{ number_format($activeContracts) }}</span>
                    </div>
                    <div class="list-stat">
                        <div class="list-stat-left">
                            <svg class="list-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <span class="list-stat-name">Ijarachilar</span>
                        </div>
                        <span class="list-stat-val">{{ number_format($totalTenants) }}</span>
                    </div>
                </div>

                <!-- Donut Chart -->
                <div class="panel">
                    <div class="panel-title">Grafiklar holati</div>
                    <div class="donut-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="donut-legend">
                        <span><span class="legend-dot" style="background: #3b82f6;"></span> To'l: {{ number_format($statusData['tolangan']) }}</span>
                        <span><span class="legend-dot" style="background: #0891b2;"></span> Kut: {{ number_format($statusData['kutilmoqda']) }}</span>
                        <span><span class="legend-dot" style="background: #ef4444;"></span> Kech: {{ number_format($statusData['muddati_otgan']) }}</span>
                    </div>
                </div>

                <!-- Contract Values -->
                <div class="panel">
                    <div class="panel-title">Shartnoma qiymatlari</div>
                    <div class="list-stat">
                        <span class="list-stat-name">Shartnoma qiymati</span>
                        <span class="list-stat-val white">{{ number_format($totalContractValue / 1000000000, 2) }} mlrd</span>
                    </div>
                    <div class="list-stat">
                        <span class="list-stat-name">Plan tushum</span>
                        <span class="list-stat-val white">{{ number_format($totalPlan / 1000000000, 2) }} mlrd</span>
                    </div>
                    <div class="list-stat">
                        <span class="list-stat-name">Fakt tushum</span>
                        <span class="list-stat-val green">{{ number_format($totalPaid / 1000000000, 2) }} mlrd</span>
                    </div>
                    <div class="list-stat">
                        <span class="list-stat-name">Jami qoldiq</span>
                        <span class="list-stat-val" style="color: #94a3b8;">{{ number_format($totalDebt / 1000000000, 2) }} mlrd</span>
                    </div>
                </div>
            </div>

            <!-- Center Column -->
            <div class="column">
                <!-- Top Stats -->
                <div class="panel">
                    <div class="stats-row">
                        <div class="stat-card">
                            <a href="{{ route('registry', ['tab' => 'lots']) }}">
                                <svg class="stat-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="8" y="6" width="32" height="24" rx="2"/><line x1="8" y1="18" x2="40" y2="18"/><line x1="24" y1="30" x2="24" y2="38"/><line x1="16" y1="38" x2="32" y2="38"/></svg>
                                <div class="stat-info"><h4>Lotlar</h4><div class="val">{{ $totalLots }}</div></div>
                            </a>
                        </div>
                        <div class="stat-card">
                            <a href="{{ route('registry') }}">
                                <svg class="stat-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="24" cy="12" rx="14" ry="5"/><path d="M10 12v8c0 2.8 6.3 5 14 5s14-2.2 14-5v-8"/><path d="M10 20v8c0 2.8 6.3 5 14 5s14-2.2 14-5v-8"/></svg>
                                <div class="stat-info"><h4>Shartnomalar</h4><div class="val">{{ $activeContracts }}</div></div>
                            </a>
                        </div>
                        <div class="stat-card">
                            <a href="{{ route('registry', ['tab' => 'tenants']) }}">
                                <svg class="stat-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="24" cy="24" r="14"/><ellipse cx="24" cy="24" rx="14" ry="5"/><ellipse cx="24" cy="24" rx="5" ry="14"/></svg>
                                <div class="stat-info"><h4>Ijarachilar</h4><div class="val">{{ $totalTenants }}</div></div>
                            </a>
                        </div>
                        <div class="stat-card">
                            <a href="{{ route('registry', ['tab' => 'payments']) }}">
                                <svg class="stat-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="6" y="18" width="36" height="12" rx="2"/><circle cx="14" cy="24" r="3" fill="currentColor"/><circle cx="24" cy="24" r="3" fill="currentColor"/><circle cx="34" cy="24" r="3" fill="currentColor"/></svg>
                                <div class="stat-info"><h4>To'lovlar</h4><div class="val cyan">{{ $totalPayments }}</div></div>
                            </a>
                        </div>
                        <div class="stat-card">
                            <a href="{{ route('registry', ['tab' => 'lots']) }}">
                                <svg class="stat-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M24 4L6 12v12c0 11 8 18 18 22 10-4 18-11 18-22V12L24 4z"/><line x1="24" y1="16" x2="24" y2="26"/><circle cx="24" cy="32" r="2" fill="currentColor"/></svg>
                                <div class="stat-info"><h4>Kechikkan</h4><div class="val red">{{ $overdueCount }}</div></div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Map -->
                <div class="panel map-panel">
                    <div class="map-container">
                        <object type="image/svg+xml" data="/dataset/map.svg" class="uzbekistan-map"></object>
                        <div class="map-marker">
                            <div class="marker-label">Poytaxt Sayilgoh</div>
                            <div class="marker-dot"></div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Chart + Finance side by side -->
                <div class="bottom-row">
                    <!-- Trend Chart -->
                    <div class="panel chart-panel">
                        <div class="panel-title">{{ $period == 'month' ? 'Oylik' : ($period == 'quarter' ? 'Choraklik' : 'Yillik') }} trend {{ $chartYear }} (Reja vs Fakt)</div>
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <span><span class="legend-dot" style="background: #64748b;"></span> Reja</span>
                            <span><span class="legend-dot" style="background: #3b82f6;"></span> Fakt</span>
                        </div>
                    </div>

                    <!-- Finance Grid -->
                    <div class="panel">
                        <div class="panel-title">Moliyaviy ko'rsatkichlar</div>
                        <div class="finance-grid">
                            <div class="finance-card green">
                                <div class="finance-label">Jami To'langan</div>
                                <div class="finance-val green">{{ number_format($totalPaid / 1000000, 1) }} <span>mln</span></div>
                                <div class="finance-sub">{{ $paidPercent }}% bajarildi</div>
                            </div>
                            <div class="finance-card red">
                                <div class="finance-label">Muddati O'tgan</div>
                                <div class="finance-val red">{{ number_format($overdueDebt / 1000000, 1) }} <span>mln</span></div>
                                <div class="finance-sub">{{ $overdueCount }} ta grafik</div>
                            </div>
                            <div class="finance-card amber">
                                <div class="finance-label">Muddati O'tmagan</div>
                                <div class="finance-val amber">{{ number_format($notYetDueDebt / 1000000, 1) }} <span>mln</span></div>
                                <div class="finance-sub">{{ $notYetDueCount }} ta grafik</div>
                            </div>
                            <div class="finance-card orange">
                                <div class="finance-label">Jami Penya</div>
                                <div class="finance-val orange">{{ number_format($totalPenya / 1000000, 1) }} <span>mln</span></div>
                                <div class="finance-sub">Kechikish jarimasi</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="column">
                <div class="panel">
                    <div class="panel-title">Bu oy statistikasi</div>
                    <div class="list-stat">
                        <span class="list-stat-name">To'lovlar soni</span>
                        <span class="list-stat-val">{{ number_format($thisMonthPayments) }}</span>
                    </div>
                    <div class="list-stat">
                        <span class="list-stat-name">To'lovlar summasi</span>
                        <span class="list-stat-val green">{{ number_format($thisMonthSum / 1000000, 1) }} mln</span>
                    </div>
                    <div class="list-stat">
                        <span class="list-stat-name">Faol ijarachilar</span>
                        <span class="list-stat-val">{{ number_format($activeTenants) }}</span>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-title">Bajarilish darajasi</div>
                    <div class="progress-row">
                        <div class="progress-top">
                            <span class="progress-name">To'langan / Reja</span>
                            <span class="progress-vals">{{ $paidPercent }}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: {{ min($paidPercent, 100) }}%"></div>
                        </div>
                    </div>
                    <div class="progress-row">
                        <div class="progress-top">
                            <span class="progress-name">Qoldiq / Reja</span>
                            <span class="progress-vals red">{{ $debtPercent }}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill red" style="width: {{ min($debtPercent, 100) }}%"></div>
                        </div>
                    </div>
                    <div class="progress-row">
                        <div class="progress-top">
                            <span class="progress-name">Kechikkan / Qoldiq</span>
                            <span class="progress-vals amber">{{ $overduePercent }}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill amber" style="width: {{ min($overduePercent, 100) }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-title">Foydalanish</div>
                    <div class="progress-row">
                        <div class="progress-top">
                            <span class="progress-name">Lot foydalanish</span>
                            <span class="progress-vals">{{ $totalLots > 0 ? round(($activeLots / $totalLots) * 100, 1) : 0 }}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: {{ $totalLots > 0 ? ($activeLots / $totalLots) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                    <div class="progress-row">
                        <div class="progress-top">
                            <span class="progress-name">Ijarachi aktivlik</span>
                            <span class="progress-vals">{{ $totalTenants > 0 ? round(($activeTenants / $totalTenants) * 100, 1) : 0 }}%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: {{ $totalTenants > 0 ? ($activeTenants / $totalTenants) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="panel" style="flex: 1;">
                    <div class="panel-title">Tezkor havolalar</div>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <a href="{{ route('registry', ['tab' => 'lots']) }}" style="display: flex; align-items: center; justify-content: space-between; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; text-decoration: none; color: #ef4444; font-size: 11px;">
                            <span>Muddati o'tgan qarzdorlar</span>
                            <span style="font-weight: 700;">{{ $overdueCount }}</span>
                        </a>
                        <a href="{{ route('registry', ['tab' => 'lots']) }}" style="display: flex; align-items: center; justify-content: space-between; padding: 8px; background: rgba(245, 158, 11, 0.1); border-radius: 4px; text-decoration: none; color: #f59e0b; font-size: 11px;">
                            <span>Kutilmoqda</span>
                            <span style="font-weight: 700;">{{ $notYetDueCount }}</span>
                        </a>
                        <a href="{{ route('registry', ['tab' => 'lots']) }}" style="display: flex; align-items: center; justify-content: space-between; padding: 8px; background: rgba(34, 197, 94, 0.1); border-radius: 4px; text-decoration: none; color: #22c55e; font-size: 11px;">
                            <span>To'langan grafiklar</span>
                            <span style="font-weight: 700;">{{ $statusData['tolangan'] }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            // Style the Uzbekistan map
            const mapEl = document.querySelector('.uzbekistan-map');
            if (mapEl) {
                mapEl.addEventListener('load', function() {
                    const svgDoc = this.contentDocument;
                    if (svgDoc) {
                        svgDoc.querySelectorAll('path').forEach(path => {
                            path.style.fill = 'rgba(56, 189, 248, 0.15)';
                            path.style.stroke = '#38bdf8';
                            path.style.strokeWidth = '0.8';
                            path.style.transition = 'all 0.2s';
                            path.style.cursor = 'pointer';
                            path.addEventListener('mouseenter', function() { this.style.fill = 'rgba(59, 130, 246, 0.4)'; });
                            path.addEventListener('mouseleave', function() { this.style.fill = 'rgba(56, 189, 248, 0.15)'; });
                        });
                    }
                });
            }

            // Status Donut Chart
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ["To'langan", 'Kutilmoqda', "Kechikkan"],
                        datasets: [{
                            data: [{{ $statusData['tolangan'] ?? 0 }}, {{ $statusData['kutilmoqda'] ?? 0 }}, {{ $statusData['muddati_otgan'] ?? 0 }}],
                            backgroundColor: ['#3b82f6', '#0891b2', '#ef4444'],
                            borderWidth: 0,
                            cutout: '65%'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // Trend Line Chart
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
                const monthlyData = @json($monthlyData ?? []);
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyData.map(d => d.label),
                        datasets: [
                            {
                                label: 'Reja',
                                data: monthlyData.map(d => d.plan || 0),
                                borderColor: '#64748b',
                                backgroundColor: 'transparent',
                                tension: 0.3,
                                pointRadius: 3,
                                pointBackgroundColor: '#64748b',
                                borderWidth: 2,
                                borderDash: [5, 5]
                            },
                            {
                                label: 'Fakt',
                                data: monthlyData.map(d => d.paid || 0),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.3,
                                pointRadius: 4,
                                pointBackgroundColor: '#3b82f6',
                                borderWidth: 2,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        scales: {
                            x: {
                                grid: { color: 'rgba(56, 189, 248, 0.06)' },
                                ticks: { color: '#64748b', font: { size: 9 }, maxRotation: 45 }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(56, 189, 248, 0.06)' },
                                ticks: {
                                    color: '#64748b', font: { size: 9 },
                                    callback: function(v) {
                                        if (v >= 1e9) return (v / 1e9).toFixed(1) + 'B';
                                        if (v >= 1e6) return (v / 1e6).toFixed(0) + 'M';
                                        if (v >= 1e3) return (v / 1e3).toFixed(0) + 'K';
                                        return v;
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        let val = ctx.parsed.y;
                                        if (val >= 1e9) return ctx.dataset.label + ': ' + (val / 1e9).toFixed(2) + ' mlrd';
                                        if (val >= 1e6) return ctx.dataset.label + ': ' + (val / 1e6).toFixed(1) + ' mln';
                                        return ctx.dataset.label + ': ' + val.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });

        // Filter functions
        function applyFilters() {
            const year = document.getElementById('yearFilter').value;
            const period = document.getElementById('periodFilter').value;
            const status = document.getElementById('statusFilter').value;
            let params = new URLSearchParams();
            if (year) params.set('year', year);
            if (period !== 'month') params.set('period', period);
            if (status !== 'all') params.set('status', status);
            window.location.href = '/data-center' + (params.toString() ? '?' + params.toString() : '');
        }
    </script>
</body>
</html>
