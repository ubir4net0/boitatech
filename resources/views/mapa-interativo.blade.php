<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>BoitaTech • Mapa Interativo</title>
    <style>
        :root {
            --bg: #05070d;
            --panel: rgba(9, 13, 22, 0.82);
            --panel-strong: rgba(10, 16, 28, 0.9);
            --border: rgba(255, 255, 255, 0.12);
            --text: #f5f7ff;
            --muted: rgba(225, 231, 245, 0.7);
            --accent: #f97316;
            --accent2: #ef4444;
            --ok: #3DFF9A;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; background: var(--bg); color: var(--text); font-family: Inter, system-ui, sans-serif; }
        #app { position: fixed; inset: 0; display: grid; grid-template-columns: minmax(320px, 380px) 1fr; }

        .sidebar {
            display: flex; flex-direction: column; gap: 12px; padding: 14px;
            background: linear-gradient(180deg, rgba(5, 9, 16, 0.95), rgba(5, 9, 16, 0.84));
            border-right: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(16px);
            z-index: 1000;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px;
        }

        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .title { font-size: 16px; font-weight: 700; letter-spacing: .02em; }
        .subtitle { font-size: 12px; color: var(--muted); margin-top: 3px; }

        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border-radius: 10px; border: 1px solid var(--border);
            background: rgba(255,255,255,0.05); color: var(--text);
            text-decoration: none; padding: 10px; font-size: 12px; cursor: pointer;
        }
        .btn.primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); border-color: rgba(249,115,22,0.7); }
        .btn.ghost { background: rgba(255,255,255,0.04); }
        .btn:disabled { opacity: .6; cursor: not-allowed; }

        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        label { display: block; font-size: 11px; color: var(--muted); margin-bottom: 4px; }
        input, select {
            width: 100%; border-radius: 10px; border: 1px solid var(--border);
            background: var(--panel-strong); color: var(--text); padding: 8px; font-size: 12px;
        }

        .kpis { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .kpi { border: 1px solid var(--border); border-radius: 12px; padding: 10px; background: rgba(255,255,255,0.03); }
        .kpi span { display: block; color: var(--muted); font-size: 11px; }
        .kpi strong { font-size: 19px; }

        #map { position: relative; }
        #leafletMap { position: absolute; inset: 0; }

        .legend { display: flex; flex-wrap: wrap; gap: 8px; font-size: 11px; color: var(--muted); }
        .dot { width: 9px; height: 9px; border-radius: 999px; display: inline-block; margin-right: 6px; vertical-align: middle; }

        .incident {
            min-height: 74px;
            font-size: 12px; line-height: 1.45;
            color: var(--muted);
        }

        .hand-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hand-info-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: rgba(255,255,255,0.22);
            box-shadow: 0 0 0 transparent;
            flex-shrink: 0;
            transition: background .24s ease, box-shadow .24s ease;
        }

        .hand-info-dot.active {
            background: var(--ok);
            box-shadow: 0 0 10px rgba(61,255,154,0.55);
        }

        .hand-info-copy {
            display: grid;
            gap: 2px;
            color: var(--muted);
            font-size: 11px;
        }

        .hand-info-copy strong {
            color: var(--text);
            font-size: 12px;
            font-weight: 600;
        }

        .ol-gesture-overlay {
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.16);
            background: linear-gradient(145deg, rgba(8, 14, 24, 0.78), rgba(9, 14, 22, 0.66));
            backdrop-filter: blur(14px) saturate(1.2);
            box-shadow: 0 24px 50px -24px rgba(0,0,0,0.75), 0 0 24px -18px rgba(249,115,22,0.4);
            overflow: hidden;
        }

        .ol-gesture-badge {
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.14);
            backdrop-filter: blur(6px);
            font-size: 10px;
            letter-spacing: .08em;
        }

        .ol-gesture-reset-label {
            letter-spacing: .08em;
            font-weight: 700;
        }

        .mobile-toggle {
            display: none; position: fixed; left: 10px; top: 10px; z-index: 1200;
            border-radius: 10px; border: 1px solid var(--border); background: var(--panel-strong); color: var(--text);
            padding: 8px 10px; font-size: 13px;
        }

        .leaflet-container { background: #08111e; }
        .hotspot-tooltip {
            border-radius: 10px; border: 1px solid rgba(255,255,255,0.15);
            background: rgba(8, 14, 24, 0.92); color: #f7f9ff;
            padding: 8px 10px; font-size: 12px;
            box-shadow: 0 14px 42px -16px rgba(239,68,68,0.75);
        }

        @media (max-width: 1024px) {
            #app { grid-template-columns: 1fr; }
            .mobile-toggle { display: inline-flex; }
            .sidebar {
                position: fixed; left: 0; top: 0; bottom: 0; width: min(92vw, 360px);
                transform: translateX(-110%); transition: transform .2s ease;
            }
            body.sidebar-open .sidebar { transform: translateX(0); }

            .ol-gesture-overlay { right: 10px !important; bottom: 10px !important; }
        }

        @media (max-width: 680px) {
            .ol-gesture-overlay {
                width: min(220px, calc(100vw - 14px)) !important;
                height: 150px !important;
            }
        }
    </style>
</head>
<body>
<button id="sidebarToggle" class="mobile-toggle" type="button">☰ Painel</button>
<div id="app">
    <aside class="sidebar" id="sidebar">
        <div class="card topbar">
            <div>
                <div class="title">🖐️ Mapa Interativo</div>
                <div class="subtitle">Modo Leaflet operacional premium</div>
            </div>
            <a class="btn ghost" href="{{ route('dashboard.index') }}">Central</a>
        </div>

        <div class="card actions">
            <button id="refreshBtn" class="btn primary" type="button">Atualizar dados</button>
            <button id="toggleHeatBtn" class="btn ghost" type="button" aria-pressed="true">Heatmap ON</button>
            <button id="toggleHotspotsBtn" class="btn ghost" type="button" aria-pressed="true">Hotspots ON</button>
            <button id="toggleHandBtn" class="btn ghost" type="button" aria-pressed="false">Hand Tracking</button>
            <!-- <a class="btn ghost" href="{{ route('lgpd.privacy') }}" target="_blank" rel="noopener">Privacidade</a> -->
            <!-- <a class="btn ghost" href="{{ route('lgpd.requests.form') }}" target="_blank" rel="noopener">Direitos LGPD</a> -->
        </div>

        <div class="card">
            <div class="grid2">
                <div>
                    <label for="startDate">Início</label>
                    <input id="startDate" type="date" />
                </div>
                <div>
                    <label for="endDate">Fim</label>
                    <input id="endDate" type="date" />
                </div>
            </div>
            <div class="grid2" style="margin-top:8px;">
                <div>
                    <label for="biomeSelect">Bioma</label>
                    <select id="biomeSelect">
                        <option value="">Todos</option>
                        <option value="Amazônia">Amazônia</option>
                        <option value="Cerrado">Cerrado</option>
                        <option value="Caatinga">Caatinga</option>
                        <option value="Mata Atlântica">Mata Atlântica</option>
                        <option value="Pantanal">Pantanal</option>
                        <option value="Pampa">Pampa</option>
                    </select>
                </div>
                <div>
                    <label for="pointBudget">Limite</label>
                    <select id="pointBudget">
                        <option value="250">250</option>
                        <option value="500" selected>500</option>
                        <option value="1000">1000</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card kpis">
            <div class="kpi"><span>Pontos carregados</span><strong id="kpiPoints">0</strong></div>
            <div class="kpi"><span>Clusters ativos</span><strong id="kpiClusters">0</strong></div>
            <div class="kpi"><span>Nível operacional</span><strong id="kpiUrgency">🟢</strong></div>
            <div class="kpi"><span>Última sync</span><strong id="kpiUpdated">--:--</strong></div>
        </div>

        <div class="card">
            <div class="legend">
                <span><i class="dot" style="background:#ef4444"></i>Hotspot crítico</span>
                <span><i class="dot" style="background:#f97316"></i>Hotspot alto</span>
                <span><i class="dot" style="background:#fde047"></i>Hotspot moderado</span>
            </div>
            <div id="incidentCard" class="incident" style="margin-top:10px;">Passe o mouse sobre um hotspot para detalhes operacionais.</div>
        </div>

        <div class="card hand-info">
            <span id="handInfoDot" class="hand-info-dot" aria-hidden="true"></span>
            <div class="hand-info-copy">
                <strong id="handState">Hand tracking desativado</strong>
                <span id="handHint">Ative para navegar com gestos. Processamento local — nenhuma imagem é enviada para servidores.</span>
            </div>
        </div>
    </aside>

    <main id="map">
        <div id="leafletMap" aria-label="Mapa interativo operacional"></div>
    </main>
</div>

<script>
    window.BOITATECH_INTERACTIVE_MAP_CONFIG = {
        apiCurrentUrl: '/api/focos/current',
        apiHealthSyncUrl: '/api/health/sync',
        apiLgpdConsentUrl: '/api/lgpd/consent',
        defaultCurrentLimit: 500,
        brazilBounds: { west: -74, south: -34, east: -34, north: 6 },
        handTrackingEnabled: true,
        lgpdPolicyUrl: '{{ route('lgpd.privacy') }}',
        lgpdPolicyVersion: '{{ config('lgpd.policy_version', '2026.05') }}',
    };
</script>
@vite('resources/js/mapa-interativo.js')
</body>
</html>
