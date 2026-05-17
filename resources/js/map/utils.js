import { MAP_CONFIG, COLORS } from './config.js';

export const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export const toNumber = (value, fallback = 0) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
};

export const toSafeString = (value, fallback = '') => {
    if (value === null || value === undefined) return fallback;
    const normalized = String(value).trim();
    return normalized.length > 0 ? normalized : fallback;
};

export const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

export const debounce = (fn, wait = 250) => {
    let timer = null;
    return (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), wait);
    };
};

export const buildDefaultDates = (days = 30) => {
    const end = new Date();
    const start = new Date(end);
    start.setDate(end.getDate() - days);
    return {
        startDate: start.toISOString().slice(0, 10),
        endDate: end.toISOString().slice(0, 10),
    };
};

export const getViewRectangle = (viewer) => viewer.camera.computeViewRectangle(viewer.scene.globe.ellipsoid);

export const getViewportBbox = (viewer) => {
    const Cesium = window.Cesium;
    const rectangle = getViewRectangle(viewer);
    const bounds = MAP_CONFIG.brazilBounds;

    if (!rectangle) {
        return bounds;
    }

    return {
        west: clamp(Cesium.Math.toDegrees(rectangle.west), bounds.west, bounds.east),
        south: clamp(Cesium.Math.toDegrees(rectangle.south), bounds.south, bounds.north),
        east: clamp(Cesium.Math.toDegrees(rectangle.east), bounds.west, bounds.east),
        north: clamp(Cesium.Math.toDegrees(rectangle.north), bounds.south, bounds.north),
    };
};

export const sanitizePoint = (row) => {
    const latitude = toNumber(row?.latitude, NaN);
    const longitude = toNumber(row?.longitude, NaN);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null;
    }

    const bounds = MAP_CONFIG.brazilBounds;
    if (
        longitude < bounds.west || longitude > bounds.east ||
        latitude < bounds.south || latitude > bounds.north
    ) {
        return null;
    }

    return {
        sourceId: toSafeString(row?.sourceId ?? row?.id ?? `${longitude}:${latitude}:${row?.viewedAt ?? ''}`),
        latitude,
        longitude,
        uf: toSafeString(row?.uf ?? row?.estado ?? row?.state, 'N/D'),
        biome: toSafeString(row?.biome, 'N/D'),
        municipio: toSafeString(row?.municipio, 'N/D'),
        satelite: toSafeString(row?.satelite, 'N/D'),
        fonte: toSafeString(row?.fonte, 'INPE'),
        data: toSafeString(row?.data, '-'),
        viewedAt: toSafeString(row?.viewedAt ?? row?.view_date ?? row?.created_at, '-'),
        rawSeverity: toSafeString(row?.severity ?? row?.nivel_risco, '').toLowerCase(),
    };
};

const inferByDate = (point) => {
    const ref = new Date(point.viewedAt);
    if (Number.isNaN(ref.getTime())) return 'medio';
    const hours = Math.max(0, (Date.now() - ref.getTime()) / 36e5);
    if (hours <= 4) return 'alto';
    if (hours <= 24) return 'medio';
    return 'baixo';
};

export const inferIntensity = (point) => {
    const level = ['alto', 'medio', 'baixo'].includes(point.rawSeverity)
        ? point.rawSeverity
        : inferByDate(point);

    if (level === 'alto') {
        return { level, color: COLORS.alto, size: 10 };
    }
    if (level === 'baixo') {
        return { level, color: COLORS.baixo, size: 7 };
    }

    return { level: 'medio', color: COLORS.medio, size: 8 };
};

export const pointKey = (point, layerName) => `${layerName}:${point.sourceId}`;

export const sanitizeFeature = (feature) => {
    if (!feature || feature.type !== 'Feature' || !feature.geometry || !feature.properties) {
        return null;
    }

    const geometryType = feature.geometry.type;
    if (!['Polygon', 'MultiPolygon'].includes(geometryType)) {
        return null;
    }

    return {
        type: feature.type,
        geometry: feature.geometry,
        properties: feature.properties,
    };
};

export const formatIncidentHtml = (payload) => `
    <strong>${escapeHtml(payload.kind)}</strong>
    <div><b>Total:</b> ${escapeHtml(payload.total)}</div>
    <div><b>Bioma:</b> ${escapeHtml(payload.biome)}</div>
    <div><b>Município:</b> ${escapeHtml(payload.municipio)}</div>
    <div><b>Janela:</b> ${escapeHtml(payload.data)}</div>
    <div><b>Última leitura:</b> ${escapeHtml(payload.viewedAt)}</div>
    <div><b>Lat/Lon:</b> ${escapeHtml(payload.latitude)}, ${escapeHtml(payload.longitude)}</div>
`;
