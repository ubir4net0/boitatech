<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Boitatech | Monitoramento Ambiental do Brasil</title>

    <style>
        :root {
            color-scheme: dark;
            --bg: rgba(7, 12, 22, 0.82);
            --border: rgba(255, 255, 255, 0.12);
            --text: rgba(255, 255, 255, 0.92);
            --muted: rgba(255, 255, 255, 0.72);
            --accent: #ff6b2c;
            --accent-2: #2ec4b6;
            --critical: #ef4444;
            --high: #f97316;
            --medium: #fde047;
            --safe: #22c55e;
        }

        html,
        body {
            margin: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #08101c;
            color: var(--text);
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        #app {
            position: relative;
            width: 100%;
            height: 100%;
        }

        #cesiumContainer {
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 20%, rgba(70, 130, 180, 0.24), transparent 28%),
                radial-gradient(circle at 80% 16%, rgba(255, 180, 80, 0.18), transparent 24%),
                linear-gradient(180deg, #07111f 0%, #09172a 45%, #03070f 100%);
        }

        .cesium-viewer,
        .cesium-viewer-cesiumWidgetContainer,
        .cesium-widget,
        .cesium-widget canvas {
            width: 100%;
            height: 100%;
        }

        .shell {
            position: absolute;
            inset: 0;
            pointer-events: none;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: clamp(10px, 1.2vw, 14px);
            gap: clamp(10px, 1.2vw, 14px);
            z-index: 20;
        }

        .sidebar-toggle {
            pointer-events: auto;
            display: none;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: rgba(6, 12, 22, 0.86);
            color: var(--text);
            backdrop-filter: blur(12px);
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            z-index: 18;
            background: rgba(1, 6, 13, 0.5);
            opacity: 0;
            pointer-events: none;
            transition: opacity 180ms ease;
        }

        .panel,
        .loading,
        .tooltip {
            pointer-events: auto;
            background: var(--bg);
            border: 1px solid var(--border);
            backdrop-filter: blur(14px);
            box-shadow: 0 12px 34px rgba(0, 0, 0, 0.28);
        }

        .panel {
            width: min(390px, calc(100vw - 26px));
            max-height: calc(100vh - 24px);
            border-radius: 16px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 220ms ease, opacity 180ms ease;
            will-change: transform;
        }

        .panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 2px;
        }

        .sidebar-close {
            display: none;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.06);
            color: var(--text);
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
        }

        .panel-scroll {
            overflow-y: auto;
            overflow-x: hidden;
            max-height: calc(100vh - 84px);
            padding-right: 4px;
        }

        .panel-scroll::-webkit-scrollbar {
            width: 8px;
        }

        .panel-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.24);
            border-radius: 999px;
        }

        .panel-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .panel h1 {
            margin: 0;
            font-size: 20px;
            line-height: 1.2;
            letter-spacing: 0.01em;
        }

        .panel p,
        .panel label,
        .panel small,
        .panel span {
            color: var(--muted);
            font-size: 12px;
        }

        .status {
            margin: 8px 0 0;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.82);
        }

        .project-meta {
            display: grid;
            gap: 4px;
            margin-top: 6px;
        }

        .project-subtitle {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.86);
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            color: rgba(167, 243, 208, 0.95);
            background: rgba(16, 185, 129, 0.14);
            border: 1px solid rgba(52, 211, 153, 0.32);
            border-radius: 999px;
            padding: 4px 10px;
            width: fit-content;
        }

        .header-badge[data-state="healthy"] {
            color: rgba(167, 243, 208, 0.95);
            background: rgba(16, 185, 129, 0.14);
            border-color: rgba(52, 211, 153, 0.32);
        }

        .header-badge[data-state="delayed"] {
            color: rgba(254, 240, 138, 0.95);
            background: rgba(234, 179, 8, 0.16);
            border-color: rgba(250, 204, 21, 0.38);
        }

        .header-badge[data-state="degraded"] {
            color: rgba(254, 202, 202, 0.98);
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(248, 113, 113, 0.45);
        }

        .sync-health-meta {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.68);
            line-height: 1.35;
        }

        .brief-card {
            margin-top: 8px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.09);
            background: rgba(255, 255, 255, 0.03);
            padding: 8px 9px;
        }

        .brief-card h3 {
            margin: 0 0 6px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.94);
        }

        .brief-list {
            margin: 0;
            padding-left: 14px;
            display: grid;
            gap: 4px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.75);
        }

        .mini-kpis {
            margin-top: 8px;
            display: grid;
            gap: 6px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .kpi {
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 9px;
            padding: 7px;
            background: rgba(255, 255, 255, 0.03);
        }

        .kpi strong {
            display: block;
            color: #fff;
            font-size: 14px;
            margin-top: 3px;
        }

        .hero-stat {
            margin-top: 10px;
            border-radius: 12px;
            padding: 10px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            background: linear-gradient(160deg, rgba(239, 68, 68, 0.14), rgba(249, 115, 22, 0.06));
        }

        .hero-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .hero-head h2 {
            margin: 0;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.88);
            letter-spacing: 0.01em;
        }

        .urgency-badge {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            background: rgba(34, 197, 94, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .urgency-badge[data-level="medio"] {
            background: rgba(250, 204, 21, 0.62);
            color: #111;
        }

        .urgency-badge[data-level="alto"] {
            background: rgba(249, 115, 22, 0.75);
        }

        .urgency-badge[data-level="critico"] {
            background: rgba(239, 68, 68, 0.82);
        }

        .hero-value {
            margin-top: 6px;
            font-size: 30px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: #fff;
            text-shadow: 0 0 14px rgba(239, 68, 68, 0.28);
        }

        .hero-sub {
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 12px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px;
            margin-top: 10px;
        }

        .stat {
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.09);
            padding: 8px;
            background: rgba(255, 255, 255, 0.03);
        }

        .stat strong {
            display: block;
            font-size: 15px;
            color: var(--text);
        }

        .controls {
            display: grid;
            gap: 8px;
            margin-top: 12px;
        }

        .section-title-muted {
            font-size: 11px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.58);
            margin: 2px 0;
        }

        .section-card {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.02);
            overflow: hidden;
        }

        .section-card > summary {
            list-style: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            user-select: none;
        }

        .section-card > summary::-webkit-details-marker {
            display: none;
        }

        .section-card > summary::after {
            content: '▾';
            font-size: 12px;
            color: rgba(255, 255, 255, 0.76);
            transition: transform 140ms ease;
        }

        .section-card:not([open]) > summary::after {
            transform: rotate(-90deg);
        }

        .section-body {
            padding: 0 8px 8px;
            display: grid;
            gap: 6px;
        }

        .toggle-row,
        .field-row,
        .actions {
            display: grid;
            gap: 8px;
        }

        .toggle-grid,
        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .chip-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
            padding: 7px 9px;
            background: rgba(255, 255, 255, 0.03);
        }

        .field label {
            display: block;
            margin-bottom: 5px;
        }

        .quick-range {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
        }

        .quick-range button {
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            font-size: 11px;
            padding: 7px 4px;
            cursor: pointer;
            transition: transform 120ms ease, border-color 120ms ease, background 120ms ease;
        }

        .quick-range button:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.32);
        }

        .quick-range button[data-active="true"] {
            background: linear-gradient(145deg, rgba(249, 115, 22, 0.55), rgba(239, 68, 68, 0.45));
            border-color: rgba(249, 115, 22, 0.75);
        }

        .field input,
        .field select,
        .actions button,
        .actions .action-link {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
            padding: 9px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .field select {
            background-color: rgba(12, 20, 34, 0.95);
            color: rgba(255, 255, 255, 0.95);
            border-color: rgba(255, 255, 255, 0.22);
            color-scheme: dark;
        }

        .field select option {
            background: #0b1322;
            color: #f3f4f6;
        }

        .field select:focus {
            outline: 2px solid rgba(249, 115, 22, 0.45);
            outline-offset: 1px;
            border-color: rgba(249, 115, 22, 0.72);
        }

        .actions {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .actions button {
            cursor: pointer;
        }

        .actions .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
        }

        .actions button.primary {
            background: linear-gradient(135deg, var(--accent), #c81e1e);
        }

        .actions button.secondary {
            background: rgba(255, 255, 255, 0.06);
        }

        .hand-state-chip {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            padding: 8px 10px;
            margin-top: 2px;
        }

        .hand-state-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: rgba(255,255,255,0.26);
            box-shadow: 0 0 0 transparent;
            margin-top: 4px;
            transition: background .24s ease, box-shadow .24s ease;
        }

        .hand-state-dot.active {
            background: #3DFF9A;
            box-shadow: 0 0 10px rgba(61,255,154,0.58);
        }

        .hand-state-copy {
            display: grid;
            gap: 2px;
            color: var(--muted);
        }

        .hand-state-copy strong {
            font-size: 12px;
            color: var(--text);
            font-weight: 600;
        }

        .hand-state-copy span {
            font-size: 11px;
            line-height: 1.4;
        }

        .ol-gesture-overlay {
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.16);
            background: linear-gradient(145deg, rgba(8, 14, 24, 0.78), rgba(9, 14, 22, 0.66));
            backdrop-filter: blur(14px) saturate(1.2);
            box-shadow: 0 24px 50px -24px rgba(0,0,0,0.75), 0 0 24px -18px rgba(249,115,22,0.4);
            overflow: hidden;
        }

        .loading {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            align-self: flex-start;
            flex-direction: column;
            min-width: 220px;
        }

        .loading[data-active="true"] {
            display: inline-flex;
        }

        .boundary-notice {
            position: absolute;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%) translateY(12px);
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(239, 68, 68, 0.16);
            border: 1px solid rgba(248, 113, 113, 0.48);
            color: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.24);
            font-size: 12px;
            letter-spacing: 0.01em;
            opacity: 0;
            pointer-events: none;
            transition: opacity 180ms ease, transform 220ms ease;
            z-index: 32;
        }

        .boundary-notice[data-active="true"] {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .loading .pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent-2);
            box-shadow: 0 0 0 rgba(46, 196, 182, 0.6);
            animation: pulse 1.2s infinite;
        }

        .loading-top {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.9);
        }

        .loading-track {
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            overflow: hidden;
        }

        .loading-fill {
            height: 100%;
            width: 8%;
            border-radius: inherit;
            background: linear-gradient(90deg, #f97316, #ef4444);
            box-shadow: 0 0 14px rgba(239, 68, 68, 0.45);
            transition: width 180ms ease;
        }

        .loading-caption {
            width: 100%;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.74);
        }

        .tooltip {
            position: absolute;
            z-index: 30;
            pointer-events: none;
            min-width: 165px;
            max-width: 245px;
            padding: 8px 10px;
            border-radius: 9px;
            font-size: 11px;
            line-height: 1.45;
            display: none;
            background: rgba(10, 14, 22, 0.9);
            border: 1px solid rgba(239, 68, 68, 0.28);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 5px 18px rgba(239, 68, 68, 0.14), 0 2px 8px rgba(0,0,0,0.45);
            color: rgba(255, 255, 255, 0.92);
        }

        .tt-row {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin-bottom: 2px;
        }

        .tt-row:last-child { margin-bottom: 0; }

        .tt-icon {
            flex-shrink: 0;
            font-size: 12px;
            line-height: 1.4;
        }

        .tt-coords {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: rgba(255, 180, 180, 0.9);
        }

        .tooltip strong {
            display: block;
            margin-bottom: 6px;
        }

        .incident-card {
            margin-top: 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            padding: 10px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.86);
            min-height: 72px;
        }

        .incident-card strong {
            display: block;
            margin-bottom: 4px;
            color: #fff;
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--muted);
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(46, 196, 182, 0.6); }
            70% { box-shadow: 0 0 0 10px rgba(46, 196, 182, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 196, 182, 0); }
        }

        @media (max-width: 1366px) {
            .panel {
                width: min(360px, calc(100vw - 24px));
            }

            .hero-value {
                font-size: 30px;
            }

            .mini-kpis {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px) {
            .sidebar-toggle {
                display: inline-flex;
            }

            .shell {
                align-items: flex-start;
            }

            .panel {
                position: fixed;
                left: 10px;
                top: 10px;
                bottom: 10px;
                width: min(360px, calc(100vw - 20px));
                max-height: none;
                z-index: 25;
                transform: translateX(calc(-100% - 20px));
                opacity: 0;
            }

            body.sidebar-open .panel {
                transform: translateX(0);
                opacity: 1;
            }

            body.sidebar-open .sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }

            .sidebar-close {
                display: inline-flex;
            }

            .actions,
            .toggle-grid,
            .field-grid,
            .stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .shell {
                padding: 8px;
                gap: 8px;
            }

            .sidebar-toggle {
                padding: 9px 11px;
                font-size: 12px;
            }

            .panel {
                width: calc(100vw - 16px);
                left: 8px;
                top: 8px;
                bottom: 8px;
                border-radius: 12px;
            }

            .panel-scroll {
                max-height: calc(100vh - 78px);
            }
        }
    </style>
</head>
<body>
<div id="app">
    <div id="cesiumContainer" aria-label="Mapa 3D ambiental do Brasil"></div>

    <div class="shell">
        <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-controls="mapSidebar" aria-expanded="false">☰ Filtros</button>

        <aside id="mapSidebar" class="panel" aria-live="polite">
            <div class="panel-header">
                <h1>Boitatech</h1>
                <button id="sidebarClose" class="sidebar-close" type="button" aria-label="Fechar painel">×</button>
            </div>

            <div class="panel-scroll">
                <div class="project-meta">
                    <span id="syncHealthBadge" class="header-badge" data-state="healthy">● Verificando sincronização</span>
                    <span class="project-subtitle">Monitoramento Ambiental em Tempo Quase Real</span>
                    <p class="status" id="mapaStatus">Inicializando dados de monitoramento...</p>
                    <small id="syncHealthMeta" class="sync-health-meta">Consultando status operacional...</small>
                    <small>Última atualização: <strong id="lastUpdatedRelative">agora</strong></small>
                </div>

                <section class="brief-card" aria-label="Resumo dos dados">
                    <h3>🔥 Monitoramento em Tempo Real</h3>
                    <ul class="brief-list">
                        <li>Focos de calor detectados por satélites ambientais em tempo quase real.</li>
                        <li>Fontes públicas do INPE: BDQueimadas e TerraBrasilis.</li>
                        <li>Atualização contínua para resposta operacional no território brasileiro.</li>
                    </ul>
                </section>

                <div class="mini-kpis">
                    <div class="kpi"><span>🔥 Focos ativos</span><strong id="statCurrentCount">0</strong></div>
                    <div class="kpi"><span>🌿 Biomas na tela</span><strong id="statBiomeCount">0</strong></div>
                    <div class="kpi"><span>📡 Cadência</span><strong id="statCadence">10 min</strong></div>
                </div>

                <section class="hero-stat" aria-live="polite">
                    <div class="hero-head">
                        <h2>Volume em decisão</h2>
                        <span id="statUrgencyBadge" class="urgency-badge" data-level="baixo">🟢 Normal</span>
                    </div>
                    <div id="statVisibleCount" class="hero-value">0</div>
                    <div class="hero-sub">Elementos atualmente renderizados na viewport</div>
                </section>

                <div class="stats">
                    <div class="stat">
                        <span>Camada ativa</span>
                        <strong id="statLayerMode">Current</strong>
                    </div>
                    <div class="stat">
                        <span>Viewport</span>
                        <strong id="statViewport">Brasil</strong>
                    </div>
                    <div class="stat">
                        <span>Resolução</span>
                        <strong id="statResolution">Cluster</strong>
                    </div>
                    <div class="stat">
                        <span>Interação</span>
                        <strong>Hover + Click</strong>
                    </div>
                </div>

                <div class="controls">
                    <div class="section-title-muted">Filtros e controles</div>

                    <label class="chip-toggle"><input id="toggleCurrent" type="checkbox" checked> 🔥 Exibir monitoramento em tempo real</label>

                    <details class="section-card" open>
                        <summary>Data</summary>
                        <div class="section-body">
                            <div class="quick-range" role="group" aria-label="Períodos rápidos">
                                <button type="button" data-range-days="1">Hoje</button>
                                <button type="button" data-range-days="7">7 dias</button>
                                <button type="button" data-range-days="15">15 dias</button>
                                <button type="button" data-range-days="30" data-active="true">30 dias</button>
                            </div>
                            <div class="field-grid">
                                <div class="field">
                                    <label for="historicoStartDate">Início</label>
                                    <input id="historicoStartDate" type="date">
                                </div>
                                <div class="field">
                                    <label for="historicoEndDate">Fim</label>
                                    <input id="historicoEndDate" type="date">
                                </div>
                            </div>
                        </div>
                    </details>

                    <details class="section-card" open>
                        <summary>Região</summary>
                        <div class="section-body">
                            <div class="field-grid">
                                <div class="field">
                                    <label for="biomeSelect">Bioma</label>
                                    <select id="biomeSelect">
                                        <option value="">Todos os biomas</option>
                                        <option value="Amazônia">Amazônia</option>
                                        <option value="Cerrado">Cerrado</option>
                                        <option value="Caatinga">Caatinga</option>
                                        <option value="Mata Atlântica">Mata Atlântica</option>
                                        <option value="Pantanal">Pantanal</option>
                                        <option value="Pampa">Pampa</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </details>

                    <details class="section-card" open>
                        <summary>Intensidade e volume</summary>
                        <div class="section-body">
                            <div class="field-grid">
                                <div class="field">
                                    <label for="pointBudgetSelect">Orçamento de pontos</label>
                                    <select id="pointBudgetSelect">
                                        <option value="250">Leve (250)</option>
                                        <option value="500" selected>Equilibrado (500)</option>
                                        <option value="1000">Detalhado (1000)</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="priorityLevelSelect">Nível prioritário</label>
                                    <select id="priorityLevelSelect">
                                        <option value="">Todos os níveis</option>
                                        <option value="critico">Crítico</option>
                                        <option value="alto">Alto</option>
                                        <option value="medio">Médio</option>
                                        <option value="baixo">Baixo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </details>

                    <details class="section-card" open>
                        <summary>Ações e leitura operacional</summary>
                        <div class="section-body">
                            <div class="actions">
                                <button id="refreshBtn" class="primary" type="button">Atualizar camada</button>
                                <button id="carregarMaisBtn" class="secondary" type="button" disabled>Carregar mais pontos</button>
                                <button id="toggleHandCesiumBtn" class="secondary" type="button" aria-pressed="false">🖐️ Hand Tracking 3D</button>
                                <a href="{{ route('mapa.interativo') }}" class="action-link secondary" aria-label="Abrir mapa interativo com hand tracking">🖐️ Mapa Interativo</a>
                            </div>

                            <div class="hand-state-chip" aria-live="polite">
                                <span id="handInfoDotCesium" class="hand-state-dot" aria-hidden="true"></span>
                                <div class="hand-state-copy">
                                    <strong id="handStateCesium">Hand tracking desativado</strong>
                                    <span id="handHintCesium">Ative para controlar a câmera 3D por gestos. Processamento local — nenhuma imagem é enviada para servidores.</span>
                                </div>
                            </div>

                            <div class="legend">
                                <span class="legend-item"><span class="dot" style="background:#ff6b2c"></span> Focos atuais</span>
                                <span class="legend-item"><span class="dot" style="background:#ef4444"></span> Clusters</span>
                                <span class="legend-item"><span class="dot" style="background:#22c55e"></span> Cobertura nacional</span>
                            </div>

                            <div id="incidentCard" class="incident-card">
                                <strong>Painel de incidente</strong>
                                Clique em um foco ou polígono para ver detalhes operacionais.
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </aside>

        <div id="loading" class="loading" data-active="true">
            <div class="loading-top"><span class="pulse"></span><span id="loadingText">Carregando camadas geoespaciais...</span></div>
            <div class="loading-track"><div id="loadingProgressFill" class="loading-fill"></div></div>
            <div class="loading-caption"><span>Progresso</span><span id="loadingProgressLabel">0%</span></div>
        </div>
    </div>

    <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

    <div id="cameraLimitNotice" class="boundary-notice" data-active="false">Navegação limitada ao território brasileiro.</div>
    <div id="alertaTooltip" class="tooltip"></div>
</div>

<script>
    window.BOITATECH_MAP_CONFIG = {
        apiCurrentUrl: '/api/focos/current',
        apiHealthSyncUrl: '/api/health/sync',
        defaultCurrentLimit: 500,
        cesiumIonToken: @js(config('services.cesium.ion_token')),
        photorealistic3dMode: @js(config('services.cesium.photorealistic_mode', 'off')),
        photorealistic3dUrl: @js(config('services.cesium.photorealistic_tileset_url', '')),
        brazilBounds: {
            west: -74,
            south: -34,
            east: -34,
            north: 6,
        },
    };
</script>
@vite('resources/js/mapa.js')
</body>
</html>
