<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>BoitaTech | Mapa da Amazônia</title>

    <link rel="stylesheet" href="https://cesium.com/downloads/cesiumjs/releases/1.123/Build/Cesium/Widgets/widgets.css">

    <style>
        :root {
            color-scheme: dark;
        }

        html,
        body {
            margin: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #0b1220;
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
        }

        .hud {
            position: absolute;
            top: 16px;
            left: 16px;
            z-index: 20;
            min-width: 240px;
            max-width: 420px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(5, 10, 19, 0.74);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
        }

        .hud h1 {
            margin: 0;
            font-size: 15px;
            line-height: 1.3;
        }

        .hud p {
            margin: 8px 0 0;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.86);
        }

        .hud button {
            margin-top: 10px;
            background: #c81e1e;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 8px;
            color: #fff;
            padding: 8px 10px;
            cursor: pointer;
            font-size: 12px;
        }

        .hud button:disabled {
            opacity: 0.5;
            cursor: wait;
        }

        .loading {
            position: absolute;
            right: 16px;
            top: 16px;
            z-index: 20;
            padding: 8px 10px;
            border-radius: 8px;
            background: rgba(5, 10, 19, 0.74);
            border: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 12px;
            display: none;
        }

        .loading[data-active="true"] {
            display: block;
        }

        .tooltip {
            position: absolute;
            z-index: 30;
            pointer-events: none;
            min-width: 180px;
            max-width: 320px;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(14, 16, 22, 0.94);
            font-size: 12px;
            line-height: 1.4;
            display: none;
        }

        .tooltip strong {
            display: block;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
<div id="app">
    <div id="cesiumContainer" aria-label="Mapa 3D da Amazônia"></div>

    <aside class="hud" aria-live="polite">
        <h1>BoitaTech • Monitoramento da Amazônia</h1>
        <p id="mapaStatus">Carregando mapa...</p>
        <button id="carregarMaisBtn" type="button" disabled>Carregar mais alertas</button>
    </aside>

    <div id="loading" class="loading" data-active="true">Carregando dados...</div>
    <div id="alertaTooltip" class="tooltip"></div>
</div>

<script src="https://cesium.com/downloads/cesiumjs/releases/1.123/Build/Cesium/Cesium.js"></script>
<script>
    window.BOITATECH_MAP_CONFIG = {
        cesiumIonToken: @js($cesiumIonToken),
        apiAlertasUrl: @js(route('api.alertas.index')),
        defaultLimit: 80,
    };
</script>
@vite('resources/js/mapa.js')
</body>
</html>
