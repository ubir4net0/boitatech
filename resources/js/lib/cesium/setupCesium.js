/**
 * setupCesium.js — Configuração centralizada do CesiumJS para o BoitaTech.
 *
 * Responsabilidades:
 *  - Desativar Ion token e crédito padrão (sem assets externos pagos)
 *  - Exportar opções base de Viewer (sem duplicação)
 *  - Exportar helper de acesso seguro a objetos opcionais da cena
 *
 * Importado por: denuncias.js, map/viewer.js
 */

/**
 * Resolve a base URL correta dos assets internos do Cesium.
 *
 * - Dev (Vite): http://127.0.0.1:5173/cesium/
 * - Build Laravel: /build/cesium/
 */
export const resolveCesiumBaseUrl = () => {
    try {
        const moduleUrl = new URL(import.meta.url);
        if (moduleUrl.port === '5173' || moduleUrl.port === '5174' || moduleUrl.port === '5175') {
            return `${moduleUrl.origin}/cesium/`;
        }
    } catch (_) {
        // fallback abaixo
    }

    return '/build/cesium/';
};

/**
 * Define globalmente `window.CESIUM_BASE_URL`.
 * Deve ser executado antes do primeiro `new Cesium.Viewer(...)`.
 */
export const configureCesiumBaseUrl = () => {
    if (typeof window === 'undefined') {
        return '';
    }

    const baseUrl = resolveCesiumBaseUrl();
    window.CESIUM_BASE_URL = baseUrl;
    return baseUrl;
};

/**
 * Desativa o token Ion e o crédito padrão do Cesium.
 * Deve ser chamado UMA VEZ antes de qualquer new Cesium.Viewer().
 *
 * @param {typeof import('cesium')} Cesium
 */
export const disableCesiumIonAndCredit = (Cesium) => {
    try {
        if (Cesium.Ion) {
            Cesium.Ion.defaultAccessToken = undefined;
        }
        if (Cesium.CreditDisplay && Cesium.Credit) {
            Cesium.CreditDisplay.cesiumCredit = new Cesium.Credit('', true);
        }
    } catch (_) {
        // Não-fatal: versões antigas do Cesium podem não expor esses objetos.
    }
};

/**
 * Opções padrão de Viewer para globos sem efeitos astronômicos/atmosféricos.
 * Não inclui terrainProvider nem imageryProvider — são responsabilidade do caller.
 */
export const CESIUM_VIEWER_BASE_OPTIONS = {
    animation: false,
    timeline: false,
    geocoder: false,
    baseLayerPicker: false,
    sceneModePicker: false,
    navigationHelpButton: false,
    homeButton: false,
    fullscreenButton: false,
    infoBox: false,
    selectionIndicator: false,
    scene3DOnly: true,
    requestRenderMode: true,
    shouldAnimate: false,
    skyBox: false,
    skyAtmosphere: false,
    baseLayer: false,
};

/**
 * Aplica configurações mínimas pós-criação de Viewer para desativar
 * todos os recursos que disparam carregamento de assets astronômicos:
 *   - IAU2006_XYS (sol/lua)
 *   - approximateTerrainHeights (atmosphere/ground)
 *   - ion-credit.png
 *
 * TODOS os acessos a objetos opcionais (sun, moon, skyAtmosphere, fog, postProcessStages)
 * são guardados com null checks para compatibilidade com qualquer combinação de opções.
 *
 * @param {import('cesium').Viewer} viewer
 * @param {typeof import('cesium')} Cesium
 */
export const applyCesiumSafeDefaults = (viewer, Cesium) => {
    const { scene } = viewer;

    // Desativar objetos astronômicos — existem independentemente de skyAtmosphere: false
    if (scene.sun) scene.sun.show = false;
    if (scene.moon) scene.moon.show = false;
    if (scene.skyAtmosphere) scene.skyAtmosphere.show = false;

    // Desativar iluminação e atmosfera do globo
    if (scene.globe) {
        scene.globe.enableLighting = false;
        scene.globe.dynamicAtmosphereLighting = false;
        scene.globe.dynamicAtmosphereLightingFromSun = false;
        scene.globe.showGroundAtmosphere = false;
        scene.globe.depthTestAgainstTerrain = false;
        scene.globe.baseColor = Cesium.Color.fromCssColorString('#07111a');
    }

    // HDR desativado (evita cálculos extras de iluminação)
    scene.highDynamicRange = false;

    // Proteção contra renderError loop infinito
    let renderErrorCount = 0;
    scene.renderError.addEventListener((sceneArg, error) => {
        renderErrorCount += 1;
        console.error('[Cesium] Render error', renderErrorCount, error);
        if (renderErrorCount >= 1 && viewer.useDefaultRenderLoop) {
            viewer.useDefaultRenderLoop = false;
            console.warn('[Cesium] Render loop disabled.');
        }
        if (renderErrorCount >= 2 && !viewer.isDestroyed()) {
            viewer.destroy();
            console.warn('[Cesium] Viewer destroyed after repeated errors.');
        }
    });
};
