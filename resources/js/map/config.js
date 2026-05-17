const rawConfig = window.BOITATECH_MAP_CONFIG ?? {};
const nav = typeof navigator === 'object' ? navigator : {};
const hw = Number(nav.hardwareConcurrency ?? 4);
const mem = Number(nav.deviceMemory ?? 4);
const dpr = Math.max(1, Number(window.devicePixelRatio ?? 1));
const reducedMotion = typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false;

const isLowPowerDevice = reducedMotion || hw <= 4 || mem <= 4;
const isMidPowerDevice = !isLowPowerDevice && (hw <= 8 || mem <= 8);

export const MAP_CONFIG = {
    apiCurrentUrl: rawConfig.apiCurrentUrl ?? '/api/focos/current',
    apiHealthSyncUrl: rawConfig.apiHealthSyncUrl ?? '/api/health/sync',
    defaultCurrentLimit: Number(rawConfig.defaultCurrentLimit ?? 500),
    cesiumIonToken: rawConfig.cesiumIonToken ?? '',
    brazilBounds: {
        west: Number(rawConfig.brazilBounds?.west ?? -74),
        south: Number(rawConfig.brazilBounds?.south ?? -34),
        east: Number(rawConfig.brazilBounds?.east ?? -34),
        north: Number(rawConfig.brazilBounds?.north ?? 6),
    },
};

export const COLORS = {
    baixo: '#fde047',
    medio: '#f97316',
    alto: '#ef4444',
};

export const VIEWER_OPTIONS = {
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
    requestRenderMode: true,
    maximumRenderTimeChange: 0.6,
};

export const RENDER_PROFILE = {
    tier: isLowPowerDevice ? 'low' : (isMidPowerDevice ? 'mid' : 'high'),
    resolutionScale: isLowPowerDevice
        ? Math.min(dpr, 1.1)
        : isMidPowerDevice
            ? Math.min(dpr, 1.35)
            : Math.min(dpr, 1.8),
    targetFrameRate: isLowPowerDevice ? 30 : (isMidPowerDevice ? 40 : 55),
    maximumRenderTimeChange: isLowPowerDevice ? 1.0 : (isMidPowerDevice ? 0.65 : 0.35),
    maximumScreenSpaceError: isLowPowerDevice ? 2.7 : (isMidPowerDevice ? 2.0 : 1.35),
    fogDensity: isLowPowerDevice ? 0.00011 : (isMidPowerDevice ? 0.00009 : 0.00007),
    tileCacheSize: isLowPowerDevice ? 260 : (isMidPowerDevice ? 420 : 640),
    cameraInertia: {
        spin: isLowPowerDevice ? 0.84 : 0.9,
        translate: isLowPowerDevice ? 0.86 : 0.92,
        zoom: isLowPowerDevice ? 0.8 : 0.88,
    },
};

export const CAMERA = {
    longitude: -53.8,
    latitude: -13.8,
    height: 5_300_000,
    pitch: -1.08,
    heading: -0.08,
    roll: 0,
};

export const CLUSTER_OPTIONS = {
    pixelRange: 46,
    minimumClusterSize: 3,
};
