import { MAP_CONFIG, COLORS } from './config.js';

export const debounce = (fn, wait = 300) => {
    let timer = null;
    return (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), wait);
    };
};

export const toSafeString = (value, fallback = '-') => {
    const normalized = String(value ?? '').trim();
    return normalized || fallback;
};

export const inferSeverity = (point) => {
    const explicit = String(point.rawSeverity ?? '').toLowerCase();
    if (['critico', 'alto', 'medio', 'baixo'].includes(explicit)) return explicit;

    const ref = new Date(point.viewedAt ?? point.data ?? null);
    if (Number.isNaN(ref.getTime())) return 'medio';
    const hours = (Date.now() - ref.getTime()) / 3_600_000;
    if (hours <= 3) return 'critico';
    if (hours <= 10) return 'alto';
    if (hours <= 24) return 'medio';
    return 'baixo';
};

export const severityColor = (severity) => COLORS[severity] ?? COLORS.medio;

export const sanitizePoint = (row) => {
    const lat = Number(row?.latitude);
    const lng = Number(row?.longitude);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;

    if (
        lng < MAP_CONFIG.bounds.west || lng > MAP_CONFIG.bounds.east
        || lat < MAP_CONFIG.bounds.south || lat > MAP_CONFIG.bounds.north
    ) {
        return null;
    }

    return {
        id: toSafeString(row?.sourceId ?? row?.id ?? `${lat}:${lng}:${row?.viewedAt ?? ''}`),
        latitude: lat,
        longitude: lng,
        biome: toSafeString(row?.biome),
        municipio: toSafeString(row?.municipio),
        uf: toSafeString(row?.uf ?? row?.estado),
        satelite: toSafeString(row?.satelite),
        fonte: toSafeString(row?.fonte ?? 'INPE'),
        data: toSafeString(row?.data),
        viewedAt: toSafeString(row?.viewedAt ?? row?.created_at ?? row?.data),
        rawSeverity: toSafeString(row?.severity ?? row?.nivel_risco, '').toLowerCase(),
    };
};

export const buildDateRange = (days = 30) => {
    const end = new Date();
    const start = new Date(end);
    start.setDate(end.getDate() - days);
    return {
        startDate: start.toISOString().slice(0, 10),
        endDate: end.toISOString().slice(0, 10),
    };
};
