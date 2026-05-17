import { configureCesiumBaseUrl, disableCesiumIonAndCredit } from './lib/cesium/setupCesium.js';
import * as Cesium from 'cesium';
import 'cesium/Build/Cesium/Widgets/widgets.css';
import '@map-gesture-controls/leaflet/style.css';

configureCesiumBaseUrl();
window.Cesium = Cesium;
disableCesiumIonAndCredit(Cesium);

import('./map/main.js');

/* Legacy monolithic implementation disabled after modular refactor.

const config = window.BOITATECH_MAP_CONFIG ?? {};

const BRAZIL_BOUNDS = {
    west: Number(config.brazilBounds?.west ?? -74),
    south: Number(config.brazilBounds?.south ?? -34),
    east: Number(config.brazilBounds?.east ?? -34),
    north: Number(config.brazilBounds?.north ?? 6),
};

const BRAZIL_CAMERA = {
    longitude: -54.2,
    latitude: -14.2,
    height: 5_800_000,
    pitch: -1.2,
    heading: 0.0,
    roll: 0.0,
};

const BRAZIL_RECTANGLE = window.Cesium
    ? window.Cesium.Rectangle.fromDegrees(BRAZIL_BOUNDS.west, BRAZIL_BOUNDS.south, BRAZIL_BOUNDS.east, BRAZIL_BOUNDS.north)
    : null;

const CONNECTION = navigator.connection ?? navigator.mozConnection ?? navigator.webkitConnection ?? null;
const IS_MOBILE = window.matchMedia('(max-width: 860px)').matches;
const IS_SLOW_CONNECTION = Boolean(CONNECTION?.saveData) || ['slow-2g', '2g', '3g'].includes(CONNECTION?.effectiveType ?? '');

const RENDER_PROFILE = {
    resolutionScale: IS_MOBILE ? 1 : Math.min(window.devicePixelRatio || 1, IS_SLOW_CONNECTION ? 1.05 : 1.35),
    targetFrameRate: IS_SLOW_CONNECTION ? 30 : 45,
    maximumRenderTimeChange: IS_SLOW_CONNECTION ? 1.2 : 0.45,
    pointBudget: IS_MOBILE ? 350 : 500,
    maxZoomDistance: 7_500_000,
    minZoomDistance: IS_MOBILE ? 95_000 : 65_000,
    terrainError: IS_SLOW_CONNECTION ? 3 : 2,
};

const SEVERITY_COLORS = {
    medio: '#fde047',
    alto: '#f97316',
    critico: '#ef4444',
    baixo: '#22c55e',
};

const FIRE_MODEL_URL = typeof config.fireModelUrl === 'string' && config.fireModelUrl.trim() !== ''
    ? config.fireModelUrl
    : null;
const USE_3D_FIRE_MODELS = Boolean(config.enableFireModels) && !IS_SLOW_CONNECTION && !IS_MOBILE && Boolean(FIRE_MODEL_URL);
const FIRE_MODEL_BUDGET = USE_3D_FIRE_MODELS ? 12 : 0;
const CLUSTER_FIRE_MODEL_BUDGET = USE_3D_FIRE_MODELS ? 8 : 0;
const FIRE_ICON_CACHE = new Map();

const BIOME_LABELS = new Set([
    'Amazônia',
    'Cerrado',
    'Caatinga',
    'Mata Atlântica',
    'Pantanal',
    'Pampa',
]);

const state = {
    viewer: null,
    dataSources: {
        currentPoints: null,
        currentClusters: null,
        historicalClusters: null,
        fireRisk: null,
        deforestation: null,
        priorityZones: null,
    },
    isReady: false,
    currentPage: 1,
    currentHasMore: true,
    activeRequestId: 0,
    lastViewportKey: '',
    moveDebounce: null,
    loadingCount: 0,
    loadingProgress: 0,
    limitNoticeTimeout: null,
    hoverEntity: null,
    filterDebounce: null,
    cameraConstraint: {
        isCorrecting: false,
        lastCorrectionAt: 0,
    },
    filters: {
        showCurrent: true,
        showHistorico: false,
        showRiscoFogo: false,
        showDesmatamento: false,
        showPrioritarias: false,
        biome: '',
        startDate: '',
        endDate: '',
        pointBudget: config.defaultCurrentLimit ?? RENDER_PROFILE.pointBudget,
        priorityLevel: '',
    },
};

const ui = {
    status: document.getElementById('mapaStatus'),
    loading: document.getElementById('loading'),
    loadMoreBtn: document.getElementById('carregarMaisBtn'),
    refreshBtn: document.getElementById('refreshBtn'),
    toggleCurrent: document.getElementById('toggleCurrent'),
    toggleHistorico: document.getElementById('toggleHistorico'),
    toggleRiscoFogo: document.getElementById('toggleRiscoFogo'),
    toggleDesmatamento: document.getElementById('toggleDesmatamento'),
    togglePrioritarias: document.getElementById('togglePrioritarias'),
    biomeSelect: document.getElementById('biomeSelect'),
    pointBudgetSelect: document.getElementById('pointBudgetSelect'),
    priorityLevelSelect: document.getElementById('priorityLevelSelect'),
    historicoStartDate: document.getElementById('historicoStartDate'),
    historicoEndDate: document.getElementById('historicoEndDate'),
    tooltip: document.getElementById('alertaTooltip'),
    limitNotice: document.getElementById('cameraLimitNotice'),
    statLayerMode: document.getElementById('statLayerMode'),
    statVisibleCount: document.getElementById('statVisibleCount'),
    statViewport: document.getElementById('statViewport'),
    statResolution: document.getElementById('statResolution'),
    statCurrentCount: document.getElementById('statCurrentCount'),
    statUrgencyBadge: document.getElementById('statUrgencyBadge'),
    incidentCard: document.getElementById('incidentCard'),
    loadingText: document.getElementById('loadingText'),
    loadingProgressFill: document.getElementById('loadingProgressFill'),
    loadingProgressLabel: document.getElementById('loadingProgressLabel'),
    quickRangeButtons: Array.from(document.querySelectorAll('[data-range-days]')),
};

const logDebug = (label, data) => {
    if (typeof data === 'object') {
        console.log(`[Boitatech:${label}]`, JSON.stringify(data, null, 2));
        return;
    }

    console.log(`[Boitatech:${label}]`, data);
};

const logError = (label, error) => {
    console.error(`[Boitatech:${label}]`, error instanceof Error ? error.message : error);
    if (error instanceof Error && error.stack) {
        console.error(error.stack);
    }
};

const todayIso = () => new Date().toISOString().slice(0, 10);

const isoDaysAgo = (days) => {
    const date = new Date();
    date.setUTCDate(date.getUTCDate() - days);
    return date.toISOString().slice(0, 10);
};

const initializeDefaultDates = () => {
    applyQuickRange(30);
};

const updateStatus = (text) => {
    ui.status.textContent = text;
};

const setLoading = (isLoading) => {
    state.loadingCount = Math.max(0, state.loadingCount + (isLoading ? 1 : -1));
    ui.loading.dataset.active = state.loadingCount > 0 ? 'true' : 'false';

    if (isLoading && state.loadingCount === 1) {
        setLoadingProgress(8, 'Sincronizando camadas operacionais...');
    }

    if (!isLoading && state.loadingCount === 0) {
        setLoadingProgress(100, 'Camadas sincronizadas');
        setTimeout(() => {
            if (state.loadingCount === 0) {
                setLoadingProgress(0, 'Carregando camadas geoespaciais...');
            }
        }, 260);
    }

    updateLoadMoreButtonState();
};

const updateLoadMoreButtonState = () => {
    const highDetailMode = getZoomBucket() >= 9;
    ui.loadMoreBtn.disabled = !state.isReady
        || state.loadingCount > 0
        || !state.filters.showCurrent
        || !highDetailMode
        || !state.currentHasMore;
};

const toSafeNumber = (value, min, max) => {
    const number = Number(value);
    if (!Number.isFinite(number)) {
        return null;
    }

    return Math.min(max, Math.max(min, number));
};

const toSafeString = (value, fallback = '-') => {
    if (typeof value !== 'string') {
        return fallback;
    }

    return value.trim().slice(0, 100) || fallback;
};

const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const parseEventDate = (point) => {
    const raw = point.viewedAt && point.viewedAt !== '-' ? point.viewedAt : point.data;
    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const inferPointSeverity = (point) => {
    const eventDate = parseEventDate(point);
    if (!eventDate) {
        return 'medio';
    }

    const hoursAgo = (Date.now() - eventDate.getTime()) / (1000 * 60 * 60);

    if (hoursAgo <= 4) {
        return 'critico';
    }

    if (hoursAgo <= 14) {
        return 'alto';
    }

    return 'medio';
};

const resolveUrgency = (currentPoints, currentClusters, priorityZones) => {
    if (priorityZones > 0 || currentPoints > 160 || currentClusters > 60) {
        return { level: 'critico', label: '🔴 Crítico' };
    }

    if (currentPoints > 70 || currentClusters > 30) {
        return { level: 'alto', label: '🟠 Atenção alta' };
    }

    if (currentPoints > 20 || currentClusters > 10) {
        return { level: 'medio', label: '🟡 Atenção' };
    }

    return { level: 'baixo', label: '🟢 Normal' };
};

const setLoadingProgress = (value, text = null) => {
    state.loadingProgress = Math.max(0, Math.min(100, value));

    if (ui.loadingProgressFill) {
        ui.loadingProgressFill.style.width = `${state.loadingProgress}%`;
    }

    if (ui.loadingProgressLabel) {
        ui.loadingProgressLabel.textContent = `${Math.round(state.loadingProgress)}%`;
    }

    if (text && ui.loadingText) {
        ui.loadingText.textContent = text;
    }
};

const sanitizePoint = (item) => {
    const latitude = toSafeNumber(item?.latitude, -90, 90);
    const longitude = toSafeNumber(item?.longitude, -180, 180);

    if (latitude === null || longitude === null) {
        return null;
    }

    const biome = toSafeString(item?.biome, 'N/D');

    return {
        sourceId: Number(item?.source_id ?? 0),
        latitude,
        longitude,
        layer: toSafeString(item?.layer, 'current'),
        biome: BIOME_LABELS.has(biome) ? biome : biome,
        municipio: toSafeString(item?.municipio, 'N/D'),
        data: toSafeString(item?.data ?? item?.view_date, '-'),
        viewedAt: toSafeString(item?.viewed_at, '-'),
        satelite: toSafeString(item?.satelite, 'N/D'),
    };
};

const sanitizeCluster = (item) => {
    const latitude = toSafeNumber(item?.latitude, -90, 90);
    const longitude = toSafeNumber(item?.longitude, -180, 180);
    const total = Number(item?.total ?? 0);

    if (latitude === null || longitude === null || !Number.isFinite(total) || total <= 0) {
        return null;
    }

    return {
        latitude,
        longitude,
        total,
        biome: toSafeString(item?.biome, 'N/D'),
        municipio: toSafeString(item?.municipio, 'N/D'),
        firstDate: toSafeString(item?.first_view_date, '-'),
        lastDate: toSafeString(item?.last_view_date, '-'),
        latestViewedAt: toSafeString(item?.latest_viewed_at, '-'),
        layer: toSafeString(item?.layer, 'cluster'),
    };
};

const getViewportBbox = () => {
    const rectangle = state.viewer?.camera?.computeViewRectangle();

    if (!rectangle || !window.Cesium) {
        return null;
    }

    let west = window.Cesium.Math.toDegrees(rectangle.west);
    let south = window.Cesium.Math.toDegrees(rectangle.south);
    let east = window.Cesium.Math.toDegrees(rectangle.east);
    let north = window.Cesium.Math.toDegrees(rectangle.north);

    if (![west, south, east, north].every(Number.isFinite)) {
        return null;
    }

    if (west > east) {
        west = BRAZIL_BOUNDS.west;
        east = BRAZIL_BOUNDS.east;
    }

    return {
        west: Number(Math.max(BRAZIL_BOUNDS.west, west).toFixed(2)),
        south: Number(Math.max(BRAZIL_BOUNDS.south, south).toFixed(2)),
        east: Number(Math.min(BRAZIL_BOUNDS.east, east).toFixed(2)),
        north: Number(Math.min(BRAZIL_BOUNDS.north, north).toFixed(2)),
    };
};

const getEffectiveBbox = () => getViewportBbox() ?? {
    west: BRAZIL_BOUNDS.west,
    south: BRAZIL_BOUNDS.south,
    east: BRAZIL_BOUNDS.east,
    north: BRAZIL_BOUNDS.north,
};

const getViewportKey = () => {
    const bbox = getViewportBbox();
    if (!bbox) {
        return 'brazil';
    }

    return `${bbox.west}|${bbox.south}|${bbox.east}|${bbox.north}|${getZoomBucket()}`;
};

const getZoomBucket = () => {
    const height = state.viewer?.camera?.positionCartographic?.height ?? BRAZIL_CAMERA.height;

    if (height > 4_500_000) {
        return 4;
    }
    if (height > 2_700_000) {
        return 6;
    }
    if (height > 1_350_000) {
        return 8;
    }
    if (height > 650_000) {
        return 10;
    }

    return 12;
};

const updateStats = () => {
    const currentPoints = state.dataSources.currentPoints?.entities?.values?.length ?? 0;
    const currentClusters = state.dataSources.currentClusters?.entities?.values?.length ?? 0;
    const historicalClusters = state.dataSources.historicalClusters?.entities?.values?.length ?? 0;
    const fireRisk = state.dataSources.fireRisk?.entities?.values?.length ?? 0;
    const deforestation = state.dataSources.deforestation?.entities?.values?.length ?? 0;
    const priorityZones = state.dataSources.priorityZones?.entities?.values?.length ?? 0;
    const totalVisible = currentPoints + currentClusters + historicalClusters + fireRisk + deforestation + priorityZones;
    const bbox = getViewportBbox();
    const resolution = getZoomBucket() >= 9 ? 'Detalhe' : 'Cluster';
    const urgency = resolveUrgency(currentPoints, currentClusters, priorityZones);

    ui.statVisibleCount.textContent = String(totalVisible);
    ui.statResolution.textContent = resolution;
    ui.statLayerMode.textContent = [
        state.filters.showCurrent ? 'Current' : null,
        state.filters.showHistorico ? 'Histórico' : null,
        state.filters.showRiscoFogo ? 'Risco' : null,
        state.filters.showDesmatamento ? 'DETER' : null,
        state.filters.showPrioritarias ? 'Prioridade' : null,
    ].filter(Boolean).join(' + ') || 'Nenhuma';
    ui.statViewport.textContent = bbox ? `${bbox.west},${bbox.south}` : 'Brasil';

    if (ui.statCurrentCount) {
        ui.statCurrentCount.textContent = String(currentPoints + currentClusters);
    }

    if (ui.statUrgencyBadge) {
        ui.statUrgencyBadge.dataset.level = urgency.level;
        ui.statUrgencyBadge.textContent = urgency.label;
    }
};

const buildBaseParams = () => {
    const params = new URLSearchParams();
    const bbox = getEffectiveBbox();
    params.set('bbox', `${bbox.west},${bbox.south},${bbox.east},${bbox.north}`);

    if (state.filters.biome) {
        params.set('biome', state.filters.biome);
    }

    return params;
};

const fetchJson = async (url, params) => {
    const response = await fetch(`${url}?${params.toString()}`, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`Falha ao consultar camada (${response.status})`);
    }

    return response.json();
};

const fetchCurrentPoints = async (page) => {
    const params = buildBaseParams();
    params.set('page', String(page));
    params.set('limit', String(state.filters.pointBudget));

    const payload = await fetchJson(config.apiCurrentUrl, params);

    return {
        data: Array.isArray(payload?.data) ? payload.data.map(sanitizePoint).filter(Boolean) : [],
        hasMore: Boolean(payload?.meta?.has_more),
    };
};

const fetchClusters = async (layer) => {
    const params = buildBaseParams();
    params.set('layer', layer);
    params.set('zoom', String(getZoomBucket()));
    params.set('limit', String(config.defaultClusterLimit ?? 350));

    if (layer === 'historico') {
        params.set('start_date', state.filters.startDate);
        params.set('end_date', state.filters.endDate);
    }

    const payload = await fetchJson(config.apiClusterUrl, params);

    return Array.isArray(payload?.data)
        ? payload.data.map(sanitizeCluster).filter(Boolean)
        : [];
};

const sanitizeGeoFeature = (feature, fallbackKind) => {
    if (!feature || feature.type !== 'Feature' || !feature.geometry || !feature.properties) {
        return null;
    }

    const geometry = feature.geometry;
    const type = geometry.type;

    if (!['Polygon', 'MultiPolygon'].includes(type) || !Array.isArray(geometry.coordinates)) {
        return null;
    }

    let coordinateCount = 0;
    const walkCoordinates = (value) => {
        if (coordinateCount > 120_000) {
            return;
        }

        if (Array.isArray(value)) {
            for (const item of value) {
                walkCoordinates(item);
            }
            return;
        }

        if (typeof value === 'number' && Number.isFinite(value)) {
            coordinateCount += 1;
        }
    };

    walkCoordinates(geometry.coordinates);
    if (coordinateCount < 8 || coordinateCount > 120_000) {
        return null;
    }

    return {
        geometry,
        kind: fallbackKind,
        properties: feature.properties,
    };
};


const buildEnvironmentalParams = () => {
    const params = new URLSearchParams();
    const bbox = getEffectiveBbox();
    params.set('bbox', `${bbox.west},${bbox.south},${bbox.east},${bbox.north}`);
    return params;
};

const fetchGeoLayer = async (url, extraParams = {}) => {
    const params = buildEnvironmentalParams();
    params.set('start_date', state.filters.startDate);
    params.set('end_date', state.filters.endDate);
    params.set('limit', String(config.defaultPolygonLimit ?? 600));
    for (const [key, value] of Object.entries(extraParams)) {
        if (value !== '' && value !== null && value !== undefined) {
            params.set(key, String(value));
        }
    }

    const payload = await fetchJson(url, params);

    return Array.isArray(payload?.features)
        ? payload.features.map((feature) => sanitizeGeoFeature(feature, 'Camada ambiental')).filter(Boolean)
        : [];
};

const clearDataSource = (dataSource) => {
    dataSource?.entities?.removeAll();
};

const render3DFire = (entity, severity, showFire = true) => {
    if (!showFire || !entity || !entity.position) {
        return;
    }

    const tier = severity === 'critico' ? 'critico' : severity === 'alto' ? 'alto' : 'medio';
    const icon = getFireIconImage(tier);
    const baseScale = severity === 'critico' ? 1.02 : severity === 'alto' ? 0.88 : 0.74;
    const seed = Number(entity.properties?.sourceId?.getValue?.() ?? 0) || Math.random() * 1000;

    if (USE_3D_FIRE_MODELS) {
        entity.model = {
            uri: FIRE_MODEL_URL,
            scale: new window.Cesium.CallbackProperty(() => {
                const wave = Math.sin(Date.now() / 230 + seed) * 0.5 + 0.5;
                return (severity === 'critico' ? 20 : severity === 'alto' ? 15 : 11) * (0.9 + wave * 0.2);
            }, false),
            minimumPixelSize: severity === 'critico' ? 36 : severity === 'alto' ? 28 : 22,
            maximumScale: severity === 'critico' ? 460 : 320,
            runAnimations: true,
            clampAnimations: false,
            heightReference: window.Cesium.HeightReference.RELATIVE_TO_GROUND,
            distanceDisplayCondition: new window.Cesium.DistanceDisplayCondition(0, 1_100_000),
        };
        return;
    }

    if (icon) {
        entity.billboard = {
            image: icon,
            scale: IS_SLOW_CONNECTION
                ? baseScale
                : new window.Cesium.CallbackProperty(() => {
                    const flicker = Math.sin(Date.now() / 260 + seed) * 0.5 + 0.5;
                    const hoverBoost = state.hoverEntity === entity ? 1.18 : 1;
                    return baseScale * (0.9 + flicker * 0.2) * hoverBoost;
                }, false),
            verticalOrigin: window.Cesium.VerticalOrigin.BOTTOM,
            disableDepthTestDistance: Number.POSITIVE_INFINITY,
            distanceDisplayCondition: new window.Cesium.DistanceDisplayCondition(0, severity === 'critico' ? 1_350_000 : 1_000_000),
        };
    }
};

const getFireIconImage = (tier = 'medio') => {
    if (FIRE_ICON_CACHE.has(tier)) {
        return FIRE_ICON_CACHE.get(tier);
    }

    const canvas = document.createElement('canvas');
    canvas.width = 96;
    canvas.height = 96;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return null;
    }

    const glowRadius = tier === 'critico' ? 42 : tier === 'alto' ? 38 : 34;
    const glow = ctx.createRadialGradient(48, 56, 5, 48, 56, glowRadius);
    glow.addColorStop(0, tier === 'critico' ? 'rgba(239,68,68,0.85)' : tier === 'alto' ? 'rgba(249,115,22,0.8)' : 'rgba(253,224,71,0.75)');
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, 96, 96);

    ctx.beginPath();
    ctx.moveTo(48, 14);
    ctx.bezierCurveTo(66, 26, 76, 44, 70, 62);
    ctx.bezierCurveTo(64, 80, 52, 88, 48, 88);
    ctx.bezierCurveTo(44, 88, 30, 80, 26, 62);
    ctx.bezierCurveTo(22, 45, 31, 27, 48, 14);
    ctx.closePath();
    ctx.fillStyle = tier === 'critico' ? 'rgba(239,68,68,0.95)' : 'rgba(249,115,22,0.95)';
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(48, 28);
    ctx.bezierCurveTo(56, 38, 60, 50, 56, 62);
    ctx.bezierCurveTo(53, 70, 49, 74, 48, 74);
    ctx.bezierCurveTo(47, 74, 43, 70, 40, 62);
    ctx.bezierCurveTo(36, 50, 40, 39, 48, 28);
    ctx.closePath();
    ctx.fillStyle = 'rgba(253,224,71,0.95)';
    ctx.fill();

    const dataUrl = canvas.toDataURL('image/png');
    FIRE_ICON_CACHE.set(tier, dataUrl);
    return dataUrl;
};

const renderPoints = (points, append = false) => {
    if (!append) {
        clearDataSource(state.dataSources.currentPoints);
    }

    clearDataSource(state.dataSources.currentClusters);

    let modelsRendered = 0;
    for (const point of points) {
        const severity = inferPointSeverity(point);
        const baseColor = window.Cesium.Color.fromCssColorString(SEVERITY_COLORS[severity] ?? '#f97316');
        const baseSize = severity === 'critico' ? 13 : severity === 'alto' ? 10 : 8;

        const entity = state.dataSources.currentPoints.entities.add({
            position: window.Cesium.Cartesian3.fromDegrees(point.longitude, point.latitude),
            point: {
                pixelSize: IS_SLOW_CONNECTION ? baseSize : new window.Cesium.CallbackProperty(() => {
                    const flicker = Math.sin(Date.now() / 130 + point.sourceId) * 0.5 + 0.5;
                    const hoverBoost = state.hoverEntity === entity ? 1.6 : 1;
                    return baseSize + flicker * 2.6 * hoverBoost;
                }, false),
                color: IS_SLOW_CONNECTION ? baseColor.withAlpha(0.85) : new window.Cesium.CallbackProperty(() => {
                    const flicker = Math.sin(Date.now() / 170 + point.sourceId) * 0.5 + 0.5;
                    const alpha = 0.65 + flicker * 0.35;
                    const boost = state.hoverEntity === entity ? 0.12 : 0;
                    return baseColor.withAlpha(Math.min(1, alpha + boost));
                }, false),
                outlineColor: IS_SLOW_CONNECTION ? window.Cesium.Color.WHITE.withAlpha(0.55) : new window.Cesium.CallbackProperty(() => {
                    const boost = state.hoverEntity === entity ? 0.3 : 0;
                    return window.Cesium.Color.WHITE.withAlpha(0.55 + boost);
                }, false),
                outlineWidth: IS_SLOW_CONNECTION ? 2 : new window.Cesium.CallbackProperty(() => (state.hoverEntity === entity ? 3 : 2), false),
                disableDepthTestDistance: Number.POSITIVE_INFINITY,
            },
            ellipse: IS_SLOW_CONNECTION ? undefined : {
                semiMajorAxis: new window.Cesium.CallbackProperty(() => {
                    const wave = Math.sin(Date.now() / 460 + point.sourceId) * 0.5 + 0.5;
                    const hoverBoost = state.hoverEntity === entity ? 1.35 : 1;
                    return (severity === 'critico' ? 26_000 : severity === 'alto' ? 19_000 : 13_000) * (0.85 + wave * 0.8) * hoverBoost;
                }, false),
                semiMinorAxis: new window.Cesium.CallbackProperty(() => {
                    const wave = Math.sin(Date.now() / 460 + point.sourceId) * 0.5 + 0.5;
                    const hoverBoost = state.hoverEntity === entity ? 1.35 : 1;
                    return (severity === 'critico' ? 26_000 : severity === 'alto' ? 19_000 : 13_000) * (0.85 + wave * 0.8) * hoverBoost;
                }, false),
                material: new window.Cesium.ColorMaterialProperty(
                    new window.Cesium.CallbackProperty(() => {
                        const wave = Math.sin(Date.now() / 420 + point.sourceId) * 0.5 + 0.5;
                        const alpha = severity === 'critico' ? 0.08 + wave * 0.35 : 0.06 + wave * 0.23;
                        return baseColor.withAlpha(alpha);
                    }, false),
                ),
                outline: true,
                outlineWidth: 1,
                outlineColor: new window.Cesium.CallbackProperty(() => baseColor.withAlpha(0.6), false),
                height: 0,
            },
            properties: {
                kind: 'Foco tempo real',
                sourceId: String(point.sourceId ?? ''),
                severity,
                biome: point.biome,
                municipio: point.municipio,
                data: point.data,
                viewedAt: point.viewedAt,
                total: '1',
                latitude: point.latitude.toFixed(5),
                longitude: point.longitude.toFixed(5),
                satelite: point.satelite,
            },
        });

        const prioritizedSeverity = severity === 'critico' || severity === 'alto';
        const canRenderModel = !USE_3D_FIRE_MODELS || prioritizedSeverity || modelsRendered < FIRE_MODEL_BUDGET;
        if (canRenderModel) {
            render3DFire(entity, severity, true);
            modelsRendered += 1;
        }
    }

    state.dataSources.currentPoints.show = state.filters.showCurrent;
    state.viewer.scene.requestRender();
};

const renderClusters = (clusters, dataSource, colorHex, kindLabel) => {
    clearDataSource(dataSource);

    const orderedClusters = [...clusters].sort((a, b) => b.total - a.total);
    let modelCount = 0;

    for (const cluster of orderedClusters) {
        const radius = Math.min(30, 8 + Math.log10(cluster.total + 1) * 8);
        const baseColor = window.Cesium.Color.fromCssColorString(colorHex);
        const intensity = Math.min(1.6, 1 + Math.log10(cluster.total + 1) * 0.28);
        const tier = cluster.total >= 60 ? 'critico' : cluster.total >= 20 ? 'alto' : 'medio';
        const modelBaseScale = tier === 'critico' ? 36 : tier === 'alto' ? 28 : 20;

        const entity = dataSource.entities.add({
            position: window.Cesium.Cartesian3.fromDegrees(cluster.longitude, cluster.latitude),
            ellipse: {
                semiMajorAxis: IS_SLOW_CONNECTION ? radius * 4_500 : new window.Cesium.CallbackProperty(() => {
                    const wave = Math.sin(Date.now() / 520 + cluster.total) * 0.5 + 0.5;
                    const hoverBoost = state.hoverEntity === entity ? 1.24 : 1;
                    return radius * 4_500 * (0.86 + wave * 0.55 * intensity) * hoverBoost;
                }, false),
                semiMinorAxis: IS_SLOW_CONNECTION ? radius * 4_500 : new window.Cesium.CallbackProperty(() => {
                    const wave = Math.sin(Date.now() / 520 + cluster.total) * 0.5 + 0.5;
                    const hoverBoost = state.hoverEntity === entity ? 1.24 : 1;
                    return radius * 4_500 * (0.86 + wave * 0.55 * intensity) * hoverBoost;
                }, false),
                material: IS_SLOW_CONNECTION ? baseColor.withAlpha(0.34) : new window.Cesium.ColorMaterialProperty(
                    new window.Cesium.CallbackProperty(() => {
                        const wave = Math.sin(Date.now() / 440 + cluster.total) * 0.5 + 0.5;
                        return baseColor.withAlpha(0.2 + wave * 0.28);
                    }, false),
                ),
                outline: true,
                outlineColor: IS_SLOW_CONNECTION ? baseColor.withAlpha(0.75) : new window.Cesium.CallbackProperty(() => {
                    const boost = state.hoverEntity === entity ? 0.2 : 0;
                    return baseColor.withAlpha(0.72 + boost);
                }, false),
                height: 0,
            },
            properties: {
                kind: kindLabel,
                total: String(cluster.total),
                biome: cluster.biome,
                municipio: cluster.municipio,
                data: `${cluster.firstDate} → ${cluster.lastDate}`,
                viewedAt: cluster.latestViewedAt,
                latitude: cluster.latitude.toFixed(5),
                longitude: cluster.longitude.toFixed(5),
                satelite: 'Agregado',
            },
        });

        const shouldUseModel = USE_3D_FIRE_MODELS && modelCount < CLUSTER_FIRE_MODEL_BUDGET;
        if (shouldUseModel) {
            entity.model = {
                uri: FIRE_MODEL_URL,
                scale: new window.Cesium.CallbackProperty(() => {
                    const wave = Math.sin(Date.now() / 240 + cluster.total) * 0.5 + 0.5;
                    const hoverBoost = state.hoverEntity === entity ? 1.14 : 1;
                    return modelBaseScale * (0.88 + wave * 0.22) * hoverBoost;
                }, false),
                minimumPixelSize: tier === 'critico' ? 56 : tier === 'alto' ? 44 : 34,
                maximumScale: tier === 'critico' ? 580 : 460,
                runAnimations: true,
                clampAnimations: false,
                heightReference: window.Cesium.HeightReference.RELATIVE_TO_GROUND,
                silhouetteColor: tier === 'critico'
                    ? window.Cesium.Color.fromCssColorString('#ef4444').withAlpha(0.45)
                    : window.Cesium.Color.fromCssColorString('#f97316').withAlpha(0.32),
                silhouetteSize: tier === 'critico' ? 1.1 : 0.7,
                distanceDisplayCondition: new window.Cesium.DistanceDisplayCondition(0, 1_200_000),
            };
            modelCount += 1;
        } else {
            const icon = getFireIconImage(tier);
            if (icon) {
                entity.billboard = {
                    image: icon,
                    scale: new window.Cesium.CallbackProperty(() => {
                        const hoverBoost = state.hoverEntity === entity ? 1.2 : 1;
                        const pulse = Math.sin(Date.now() / 320 + cluster.total) * 0.5 + 0.5;
                        return (tier === 'critico' ? 1.05 : tier === 'alto' ? 0.9 : 0.78) * (0.92 + pulse * 0.16) * hoverBoost;
                    }, false),
                    verticalOrigin: window.Cesium.VerticalOrigin.BOTTOM,
                    disableDepthTestDistance: Number.POSITIVE_INFINITY,
                    distanceDisplayCondition: new window.Cesium.DistanceDisplayCondition(0, 1_300_000),
                };
            }
        }
    }

    state.viewer.scene.requestRender();
};

const geometryToHierarchy = (geometry) => {
    const coordinates = geometry.coordinates;
    let ring = null;

    if (geometry.type === 'Polygon') {
        ring = Array.isArray(coordinates?.[0]) ? coordinates[0] : null;
    } else if (geometry.type === 'MultiPolygon') {
        ring = Array.isArray(coordinates?.[0]?.[0]) ? coordinates[0][0] : null;
    }

    if (!Array.isArray(ring) || ring.length < 4) {
        return null;
    }

    const flat = [];
    for (const pair of ring) {
        if (!Array.isArray(pair) || pair.length < 2) {
            continue;
        }
        const lon = Number(pair[0]);
        const lat = Number(pair[1]);
        if (!Number.isFinite(lon) || !Number.isFinite(lat)) {
            continue;
        }
        flat.push(lon, lat);
    }

    if (flat.length < 8) {
        return null;
    }

    return window.Cesium.Cartesian3.fromDegreesArray(flat);
};

const renderGeoLayer = (features, dataSource, options) => {
    clearDataSource(dataSource);

    for (const feature of features) {
        const hierarchy = geometryToHierarchy(feature.geometry);
        if (!hierarchy) {
            continue;
        }

        const properties = feature.properties ?? {};
        const level = toSafeString(properties.nivel ?? properties.nivel_risco ?? '', '').toLowerCase();
        const isCritical = level === 'critico';
        const levelColor = window.Cesium.Color.fromCssColorString(SEVERITY_COLORS[level] ?? options.color);

        const resolveAlpha = () => {
            if (isCritical && options.pulseCritical) {
                const wave = Math.sin(Date.now() / 280);
                return 0.25 + ((wave + 1) / 2) * 0.35;
            }

            return options.alpha;
        };

        const entity = dataSource.entities.add({
            polygon: {
                hierarchy,
                material: new window.Cesium.ColorMaterialProperty(
                    new window.Cesium.CallbackProperty(() => {
                        const resolvedAlpha = resolveAlpha();
                        const hoverBoost = state.hoverEntity === entity ? 0.12 : 0;
                        return levelColor.withAlpha(Math.min(0.92, resolvedAlpha + hoverBoost));
                    }, false),
                ),
                outline: true,
                outlineColor: new window.Cesium.CallbackProperty(() => {
                    const hoverBoost = state.hoverEntity === entity ? 0.25 : 0;
                    return window.Cesium.Color.fromCssColorString(options.outlineColor ?? options.color).withAlpha(0.72 + hoverBoost);
                }, false),
                outlineWidth: new window.Cesium.CallbackProperty(() => (state.hoverEntity === entity ? 2 : 1), false),
                height: 0,
            },
            properties: {
                kind: options.kind,
                total: toSafeString(properties.score_risco ?? properties.area ?? properties.risco_score ?? '1'),
                biome: toSafeString(properties.biome ?? 'N/D'),
                municipio: toSafeString(properties.municipio ?? 'N/D'),
                data: toSafeString(properties.data ?? properties.data_alerta ?? '-'),
                viewedAt: toSafeString(properties.updated_at ?? '-'),
                latitude: '-',
                longitude: '-',
                satelite: toSafeString(properties.fonte ?? options.kind),
            },
        });
    }

    state.viewer.scene.requestRender();
};

const hideTooltip = () => {
    ui.tooltip.style.display = 'none';
};

const bindTooltip = () => {
    const handler = new window.Cesium.ScreenSpaceEventHandler(state.viewer.scene.canvas);

    const writeIncidentCard = (payload) => {
        if (!ui.incidentCard) {
            return;
        }

        ui.incidentCard.innerHTML = `
            <strong>${escapeHtml(payload.kind)}</strong>
            <div><b>Total:</b> ${escapeHtml(payload.total)}</div>
            <div><b>Bioma:</b> ${escapeHtml(payload.biome)}</div>
            <div><b>Município:</b> ${escapeHtml(payload.municipio)}</div>
            <div><b>Janela:</b> ${escapeHtml(payload.data)}</div>
            <div><b>Última leitura:</b> ${escapeHtml(payload.viewedAt)}</div>
            <div><b>Lat/Lon:</b> ${escapeHtml(payload.latitude)}, ${escapeHtml(payload.longitude)}</div>
        `;
    };

    handler.setInputAction((movement) => {
        const picked = state.viewer.scene.pick(movement.endPosition);
        state.hoverEntity = picked?.id ?? null;
        state.viewer.scene.requestRender();
    }, window.Cesium.ScreenSpaceEventType.MOUSE_MOVE);

    handler.setInputAction((event) => {
        const picked = state.viewer.scene.pick(event.position);

        if (!picked?.id?.properties) {
            hideTooltip();
            return;
        }

        const props = picked.id.properties;
        const kind = toSafeString(props.kind?.getValue(), 'Camada');
        const total = toSafeString(props.total?.getValue(), '1');
        const biome = toSafeString(props.biome?.getValue(), 'N/D');
        const municipio = toSafeString(props.municipio?.getValue(), 'N/D');
        const data = toSafeString(props.data?.getValue(), '-');
        const viewedAt = toSafeString(props.viewedAt?.getValue(), '-');
        const latitude = toSafeString(props.latitude?.getValue(), '-');
        const longitude = toSafeString(props.longitude?.getValue(), '-');

        ui.tooltip.innerHTML = `
            <strong>${escapeHtml(kind)}</strong>
            <div>Total: ${escapeHtml(total)}</div>
            <div>Bioma: ${escapeHtml(biome)}</div>
            <div>Município: ${escapeHtml(municipio)}</div>
            <div>Janela: ${escapeHtml(data)}</div>
            <div>Última leitura: ${escapeHtml(viewedAt)}</div>
            <div>Lat/Lon: ${escapeHtml(latitude)}, ${escapeHtml(longitude)}</div>
        `;
        ui.tooltip.style.left = `${event.position.x + 12}px`;
        ui.tooltip.style.top = `${event.position.y + 12}px`;
        ui.tooltip.style.display = 'block';

        writeIncidentCard({
            kind,
            total,
            biome,
            municipio,
            data,
            viewedAt,
            latitude,
            longitude,
        });
    }, window.Cesium.ScreenSpaceEventType.LEFT_CLICK);

    handler.setInputAction(() => {
        hideTooltip();
        state.hoverEntity = null;
        state.viewer.scene.requestRender();
    }, window.Cesium.ScreenSpaceEventType.LEFT_DOWN);
};

const syncLayerVisibility = () => {
    state.dataSources.currentPoints.show = state.filters.showCurrent && getZoomBucket() >= 9;
    state.dataSources.currentClusters.show = state.filters.showCurrent && getZoomBucket() < 9;
    state.dataSources.historicalClusters.show = state.filters.showHistorico;
    state.dataSources.fireRisk.show = state.filters.showRiscoFogo;
    state.dataSources.deforestation.show = state.filters.showDesmatamento;
    state.dataSources.priorityZones.show = state.filters.showPrioritarias;
    updateLoadMoreButtonState();
    updateStats();
};

const refreshVisibleData = async ({ append = false } = {}) => {
    if (!state.isReady || !state.viewer) {
        updateStatus('Mapa ainda inicializando...');
        return;
    }

    const requestId = ++state.activeRequestId;
    const detailMode = getZoomBucket() >= 9;
    const targetPage = append ? state.currentPage + 1 : 1;
    const totalSteps = [
        state.filters.showCurrent,
        state.filters.showHistorico,
        state.filters.showRiscoFogo,
        state.filters.showDesmatamento,
        state.filters.showPrioritarias,
    ].filter(Boolean).length || 1;
    let doneSteps = 0;
    const advanceProgress = (message) => {
        doneSteps += 1;
        const progress = 15 + (doneSteps / totalSteps) * 75;
        setLoadingProgress(progress, message);
    };

    setLoading(true);
    updateStatus(detailMode ? 'Carregando focos em alta resolução...' : 'Carregando clusters para a área visível...');

    try {
        if (state.filters.showCurrent) {
            if (detailMode) {
                const result = await fetchCurrentPoints(targetPage);
                if (requestId !== state.activeRequestId) {
                    return;
                }

                renderPoints(result.data, append);
                state.currentPage = targetPage;
                state.currentHasMore = result.hasMore;
                advanceProgress('Focos em tempo real atualizados');
            } else {
                const clusters = await fetchClusters('current');
                if (requestId !== state.activeRequestId) {
                    return;
                }

                clearDataSource(state.dataSources.currentPoints);
                renderClusters(clusters, state.dataSources.currentClusters, '#ffe66d', 'Cluster tempo real');
                state.currentPage = 1;
                state.currentHasMore = false;
                advanceProgress('Clusters de foco atualizados');
            }
        } else {
            clearDataSource(state.dataSources.currentPoints);
            clearDataSource(state.dataSources.currentClusters);
            state.currentHasMore = false;
        }

        if (state.filters.showHistorico) {
            const clusters = await fetchClusters('historico');
            if (requestId !== state.activeRequestId) {
                return;
            }

            renderClusters(clusters, state.dataSources.historicalClusters, '#2ec4b6', 'Cluster histórico');
            advanceProgress('Histórico agregado atualizado');
        } else {
            clearDataSource(state.dataSources.historicalClusters);
        }

        if (state.filters.showRiscoFogo) {
            const riskFeatures = await fetchGeoLayer(config.apiRiscoFogoUrl);
            if (requestId !== state.activeRequestId) {
                return;
            }

            renderGeoLayer(riskFeatures, state.dataSources.fireRisk, {
                kind: 'Risco de fogo',
                color: '#3b82f6',
                outlineColor: '#60a5fa',
                alpha: 0.28,
                pulseCritical: false,
            });
            advanceProgress('Risco de fogo sincronizado');
        } else {
            clearDataSource(state.dataSources.fireRisk);
        }

        if (state.filters.showDesmatamento) {
            const deterFeatures = await fetchGeoLayer(config.apiDesmatamentoUrl);
            if (requestId !== state.activeRequestId) {
                return;
            }

            renderGeoLayer(deterFeatures, state.dataSources.deforestation, {
                kind: 'Desmatamento DETER',
                color: '#22c55e',
                outlineColor: '#16a34a',
                alpha: 0.23,
                pulseCritical: false,
            });
            advanceProgress('Desmatamento DETER sincronizado');
        } else {
            clearDataSource(state.dataSources.deforestation);
        }

        if (state.filters.showPrioritarias) {
            const extra = state.filters.priorityLevel ? { nivel: state.filters.priorityLevel } : {};
            const priorityFeatures = await fetchGeoLayer(config.apiZonasPrioritariasUrl, extra);
            if (requestId !== state.activeRequestId) {
                return;
            }

            renderGeoLayer(priorityFeatures, state.dataSources.priorityZones, {
                kind: 'Zona prioritária',
                color: '#ef4444',
                outlineColor: '#f97316',
                alpha: 0.26,
                pulseCritical: true,
            });
            advanceProgress('Zonas prioritárias processadas');
        } else {
            clearDataSource(state.dataSources.priorityZones);
        }

        syncLayerVisibility();
        setLoadingProgress(96, 'Finalizando renderização...');
        updateStatus('Camadas sincronizadas com a área visível do mapa.');
    } catch (error) {
        logError('refreshVisibleData', error);
        updateStatus('Não foi possível carregar as camadas agora. Ajuste o período, biome ou bbox e tente novamente.');
    } finally {
        setLoading(false);
    }
};

const clampValue = (value, min, max) => Math.min(max, Math.max(min, value));

const createBrazilDestination = ({ longitude, latitude, height }) => window.Cesium.Cartesian3.fromDegrees(
    clampValue(longitude, BRAZIL_BOUNDS.west + 0.35, BRAZIL_BOUNDS.east - 0.35),
    clampValue(latitude, BRAZIL_BOUNDS.south + 0.35, BRAZIL_BOUNDS.north - 0.35),
    clampValue(height, RENDER_PROFILE.minZoomDistance, RENDER_PROFILE.maxZoomDistance),
);

const showBoundaryFeedback = (message = 'Navegação limitada ao território brasileiro.') => {
    updateStatus(message);

    if (!ui.limitNotice) {
        return;
    }

    ui.limitNotice.textContent = message;
    ui.limitNotice.dataset.active = 'true';

    if (state.limitNoticeTimeout) {
        clearTimeout(state.limitNoticeTimeout);
    }

    state.limitNoticeTimeout = setTimeout(() => {
        ui.limitNotice.dataset.active = 'false';
    }, 1800);
};

const isInsideBrazilBounds = (cartographic) => {
    if (!cartographic || !window.Cesium) {
        return true;
    }

    const longitude = window.Cesium.Math.toDegrees(cartographic.longitude);
    const latitude = window.Cesium.Math.toDegrees(cartographic.latitude);

    return longitude >= BRAZIL_BOUNDS.west
        && longitude <= BRAZIL_BOUNDS.east
        && latitude >= BRAZIL_BOUNDS.south
        && latitude <= BRAZIL_BOUNDS.north;
};

const enforceBrazilCameraBounds = (reason = 'Navegação limitada ao território brasileiro.') => {
    if (!state.viewer || state.cameraConstraint.isCorrecting || !window.Cesium) {
        return;
    }

    const camera = state.viewer.camera;
    const cartographic = camera.positionCartographic;
    if (!cartographic) {
        return;
    }

    const longitude = window.Cesium.Math.toDegrees(cartographic.longitude);
    const latitude = window.Cesium.Math.toDegrees(cartographic.latitude);
    const height = cartographic.height;
    const clampedLongitude = clampValue(longitude, BRAZIL_BOUNDS.west + 0.35, BRAZIL_BOUNDS.east - 0.35);
    const clampedLatitude = clampValue(latitude, BRAZIL_BOUNDS.south + 0.35, BRAZIL_BOUNDS.north - 0.35);
    const clampedHeight = clampValue(height, RENDER_PROFILE.minZoomDistance, RENDER_PROFILE.maxZoomDistance);
    const outsideBounds = longitude !== clampedLongitude || latitude !== clampedLatitude;
    const invalidHeight = height !== clampedHeight;

    if (!outsideBounds && !invalidHeight) {
        return;
    }

    state.cameraConstraint.isCorrecting = true;
    state.cameraConstraint.lastCorrectionAt = Date.now();
    showBoundaryFeedback(reason);

    camera.flyTo({
        destination: createBrazilDestination({
            longitude: clampedLongitude,
            latitude: clampedLatitude,
            height: clampedHeight,
        }),
        orientation: {
            heading: camera.heading,
            pitch: clampValue(camera.pitch, -window.Cesium.Math.PI_OVER_TWO + 0.12, -0.12),
            roll: 0,
        },
        duration: outsideBounds ? 0.85 : 0.55,
        easingFunction: window.Cesium.EasingFunction.QUADRATIC_OUT,
        complete: () => {
            state.cameraConstraint.isCorrecting = false;
            state.viewer.scene.requestRender();
        },
        cancel: () => {
            state.cameraConstraint.isCorrecting = false;
        },
    });
};

const bindCameraConstraints = () => {
    const controller = state.viewer.scene.screenSpaceCameraController;
    controller.inertiaSpin = 0.9;
    controller.inertiaTranslate = 0.84;
    controller.inertiaZoom = 0.82;
    controller.maximumMovementRatio = IS_MOBILE ? 0.08 : 0.1;
    controller.minimumZoomDistance = RENDER_PROFILE.minZoomDistance;
    controller.maximumZoomDistance = RENDER_PROFILE.maxZoomDistance;
    controller.zoomFactor = IS_MOBILE ? 2.2 : 2.8;
    controller.enableCollisionDetection = true;
    controller.enableLook = true;
    state.viewer.camera.percentageChanged = 0.0025;

    state.viewer.camera.changed.addEventListener(() => {
        if (Date.now() - state.cameraConstraint.lastCorrectionAt < 150) {
            return;
        }

        if (!isInsideBrazilBounds(state.viewer.camera.positionCartographic)) {
            enforceBrazilCameraBounds();
            return;
        }

        const height = state.viewer.camera.positionCartographic?.height ?? BRAZIL_CAMERA.height;
        if (height < RENDER_PROFILE.minZoomDistance || height > RENDER_PROFILE.maxZoomDistance) {
            enforceBrazilCameraBounds('Zoom ajustado para manter a navegação estável no Brasil.');
        }
    });

    state.viewer.camera.moveEnd.addEventListener(() => {
        enforceBrazilCameraBounds();
        updateStats();
    });
};

const createImageryProvider = () => {
    return new window.Cesium.OpenStreetMapImageryProvider({
        url: 'https://tile.openstreetmap.org/',
    });
};

const upgradeToSatelliteImagery = async () => {
    if (!state.viewer) {
        return;
    }

    try {
        const satelliteProvider = await window.Cesium.ArcGisMapServerImageryProvider.fromUrl(
            'https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer',
            { enablePickFeatures: false },
        );

        const layers = state.viewer.scene.imageryLayers;
        layers.removeAll();
        const satelliteLayer = layers.addImageryProvider(satelliteProvider);
        satelliteLayer.gamma = 1.02;
        satelliteLayer.brightness = 1.04;
        satelliteLayer.contrast = 1.15;
        satelliteLayer.saturation = 1.18;
        satelliteLayer.hue = -0.02;
        state.viewer.scene.requestRender();
        logDebug('imagery', 'Imagens de satélite ArcGIS ativadas.');
    } catch (error) {
        logDebug('imagery', 'ArcGIS indisponível, mantendo OpenStreetMap.');
    }
};

const createTerrainProvider = async () => {
    const token = typeof config.cesiumIonToken === 'string' ? config.cesiumIonToken.trim() : '';

    if (!token) {
        return {
            provider: new window.Cesium.EllipsoidTerrainProvider(),
            realisticTerrain: false,
        };
    }

    try {
        window.Cesium.Ion.defaultAccessToken = token;

        return {
            provider: await window.Cesium.createWorldTerrainAsync({
                requestVertexNormals: !IS_SLOW_CONNECTION,
                requestWaterMask: !IS_SLOW_CONNECTION,
            }),
            realisticTerrain: true,
        };
    } catch (error) {
        logError('terrainProvider', error);
        return {
            provider: new window.Cesium.EllipsoidTerrainProvider(),
            realisticTerrain: false,
        };
    }
};

const bindResponsiveBehavior = () => {
    window.addEventListener('resize', () => {
        if (!state.viewer) {
            return;
        }

        state.viewer.resize();
        state.viewer.scene.requestRender();
        updateStats();
    });
};

const attachViewportLazyLoading = () => {
    state.viewer.camera.moveEnd.addEventListener(() => {
        if (state.moveDebounce) {
            clearTimeout(state.moveDebounce);
        }

        state.moveDebounce = setTimeout(() => {
            const viewportKey = getViewportKey();
            if (viewportKey === state.lastViewportKey) {
                return;
            }

            state.lastViewportKey = viewportKey;
            refreshVisibleData({ append: false });
        }, 450);
    });
};

const applyQuickRange = (days) => {
    const start = isoDaysAgo(days);
    const end = todayIso();
    ui.historicoStartDate.value = start;
    ui.historicoEndDate.value = end;
    state.filters.startDate = start;
    state.filters.endDate = end;

    for (const button of ui.quickRangeButtons) {
        button.dataset.active = Number(button.dataset.rangeDays) === days ? 'true' : 'false';
    }
};

const scheduleRefresh = (delay = 260) => {
    if (state.filterDebounce) {
        clearTimeout(state.filterDebounce);
    }

    state.filterDebounce = setTimeout(() => {
        refreshVisibleData({ append: false });
    }, delay);
};

const setupViewer = async () => {
    if (!window.Cesium) {
        throw new Error('CESIUM_LIB_LOAD_FAILED');
    }

    const imageryProvider = createImageryProvider();
    const terrainSetup = await createTerrainProvider();

    state.viewer = new window.Cesium.Viewer('cesiumContainer', {
        animation: false,
        timeline: false,
        homeButton: false,
        geocoder: false,
        sceneModePicker: false,
        navigationHelpButton: false,
        infoBox: false,
        selectionIndicator: false,
        baseLayerPicker: false,
        fullscreenButton: !IS_MOBILE,
        vrButton: false,
        scene3DOnly: true,
        requestRenderMode: true,
        maximumRenderTimeChange: RENDER_PROFILE.maximumRenderTimeChange,
        imageryProvider,
        terrainProvider: terrainSetup.provider,
        contextOptions: {
            requestWebgl1: false,
            webgl: {
                alpha: false,
                antialias: true,
                powerPreference: IS_SLOW_CONNECTION ? 'default' : 'high-performance',
                preserveDrawingBuffer: false,
            },
        },
    });

    const { scene } = state.viewer;
    if (terrainSetup.realisticTerrain) {
        scene.verticalExaggeration = IS_SLOW_CONNECTION ? 1.1 : 1.35;
    }
    scene.globe.baseColor = window.Cesium.Color.fromCssColorString('#09111e');
    scene.globe.enableLighting = true;
    scene.globe.showGroundAtmosphere = true;
    scene.globe.dynamicAtmosphereLighting = true;
    scene.globe.dynamicAtmosphereLightingFromSun = true;
    scene.globe.depthTestAgainstTerrain = terrainSetup.realisticTerrain;
    scene.globe.maximumScreenSpaceError = RENDER_PROFILE.terrainError;
    scene.globe.showWaterEffect = terrainSetup.realisticTerrain;
    scene.globe.atmosphereHueShift = -0.08;
    scene.globe.atmosphereBrightnessShift = 0.06;
    scene.globe.atmosphereSaturationShift = 0.08;
    scene.skyAtmosphere.show = true;
    scene.sun.show = true;
    scene.moon.show = true;
    scene.fog.enabled = true;
    scene.fxaa = true;
    scene.postProcessStages.fxaa.enabled = true;
    scene.highDynamicRange = !IS_SLOW_CONNECTION;
    scene.msaaSamples = IS_SLOW_CONNECTION ? 2 : 4;
    scene.screenSpaceCameraController.enableTilt = true;
    scene.requestRenderMode = true;
    scene.shadowMap.enabled = !IS_SLOW_CONNECTION;
    scene.shadowMap.softShadows = !IS_SLOW_CONNECTION;
    scene.shadowMap.darkness = 0.45;
    state.viewer.shadows = !IS_SLOW_CONNECTION;
    state.viewer.resolutionScale = RENDER_PROFILE.resolutionScale;
    state.viewer.targetFrameRate = RENDER_PROFILE.targetFrameRate;

    if (BRAZIL_RECTANGLE) {
        window.Cesium.Camera.DEFAULT_VIEW_RECTANGLE = BRAZIL_RECTANGLE;
    }

    upgradeToSatelliteImagery();

    state.dataSources.currentPoints = new window.Cesium.CustomDataSource('focos-current-points');
    state.dataSources.currentClusters = new window.Cesium.CustomDataSource('focos-current-clusters');
    state.dataSources.historicalClusters = new window.Cesium.CustomDataSource('focos-historico-clusters');
    state.dataSources.fireRisk = new window.Cesium.CustomDataSource('risco-fogo');
    state.dataSources.deforestation = new window.Cesium.CustomDataSource('desmatamento-deter');
    state.dataSources.priorityZones = new window.Cesium.CustomDataSource('zonas-prioritarias');

    state.viewer.dataSources.add(state.dataSources.currentPoints);
    state.viewer.dataSources.add(state.dataSources.currentClusters);
    state.viewer.dataSources.add(state.dataSources.historicalClusters);
    state.viewer.dataSources.add(state.dataSources.fireRisk);
    state.viewer.dataSources.add(state.dataSources.deforestation);
    state.viewer.dataSources.add(state.dataSources.priorityZones);

    bindTooltip();
    bindCameraConstraints();
    bindResponsiveBehavior();

    await state.viewer.camera.flyTo({
        destination: createBrazilDestination(BRAZIL_CAMERA),
        orientation: {
            heading: BRAZIL_CAMERA.heading,
            pitch: BRAZIL_CAMERA.pitch,
            roll: BRAZIL_CAMERA.roll,
        },
        duration: 2.8,
        easingFunction: window.Cesium.EasingFunction.CUBIC_OUT,
    });

    state.isReady = true;
    state.lastViewportKey = getViewportKey();
    syncLayerVisibility();
};

const bindControls = () => {
    ui.toggleCurrent.addEventListener('change', () => {
        state.filters.showCurrent = ui.toggleCurrent.checked;
        syncLayerVisibility();
        scheduleRefresh(120);
    });

    ui.toggleHistorico.addEventListener('change', () => {
        state.filters.showHistorico = ui.toggleHistorico.checked;
        syncLayerVisibility();
        scheduleRefresh(120);
    });

    ui.toggleRiscoFogo.addEventListener('change', () => {
        state.filters.showRiscoFogo = ui.toggleRiscoFogo.checked;
        syncLayerVisibility();
        scheduleRefresh(120);
    });

    ui.toggleDesmatamento.addEventListener('change', () => {
        state.filters.showDesmatamento = ui.toggleDesmatamento.checked;
        syncLayerVisibility();
        scheduleRefresh(120);
    });

    ui.togglePrioritarias.addEventListener('change', () => {
        state.filters.showPrioritarias = ui.togglePrioritarias.checked;
        syncLayerVisibility();
        scheduleRefresh(120);
    });

    ui.biomeSelect.addEventListener('change', () => {
        state.filters.biome = ui.biomeSelect.value;
        scheduleRefresh();
    });

    ui.pointBudgetSelect.addEventListener('change', () => {
        state.filters.pointBudget = Number(ui.pointBudgetSelect.value || 500);
        updateLoadMoreButtonState();
        scheduleRefresh();
    });

    ui.priorityLevelSelect.addEventListener('change', () => {
        state.filters.priorityLevel = ui.priorityLevelSelect.value;
        scheduleRefresh();
    });

    ui.historicoStartDate.addEventListener('change', () => {
        state.filters.startDate = ui.historicoStartDate.value;
        scheduleRefresh();
    });

    ui.historicoEndDate.addEventListener('change', () => {
        state.filters.endDate = ui.historicoEndDate.value;
        scheduleRefresh();
    });

    for (const button of ui.quickRangeButtons) {
        button.addEventListener('click', () => {
            const days = Number(button.dataset.rangeDays || 30);
            applyQuickRange(days);
            scheduleRefresh(80);
        });
    }

    ui.refreshBtn.addEventListener('click', () => {
        refreshVisibleData({ append: false });
    });

    ui.loadMoreBtn.addEventListener('click', () => {
        if (state.currentHasMore) {
            refreshVisibleData({ append: true });
        }
    });
};

const bootstrap = async () => {
    initializeDefaultDates();
    bindControls();

    try {
        setLoadingProgress(5, 'Inicializando globo 3D...');
        updateStatus('Preparando o globo 3D do Brasil para a Boitatech...');
        await setupViewer();
        setLoadingProgress(18, 'Globo pronto, carregando camadas...');
        attachViewportLazyLoading();
        await refreshVisibleData({ append: false });
        logDebug('bootstrap', 'Mapa Boitatech pronto para monitoramento em escala Brasil');
    } catch (error) {
        logError('bootstrap', error);
        state.isReady = false;
        state.currentHasMore = false;
        updateLoadMoreButtonState();
        updateStatus('Falha ao inicializar o mapa 3D. Verifique conectividade, Cesium Ion ou as APIs ambientais.');
        setLoading(false);
    }
};

bootstrap();
*/
