import { MAP_CONFIG } from './config.js';
import { sanitizePoint } from './utils.js';

const fetchJson = async (url, params) => {
    const response = await fetch(`${url}?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
};

const baseParams = (filters, bbox, includeBiome = true) => {
    const params = new URLSearchParams();
    params.set('bbox', `${bbox.west},${bbox.south},${bbox.east},${bbox.north}`);
    params.set('start_date', filters.startDate);
    params.set('end_date', filters.endDate);

    if (includeBiome && filters.biome) {
        params.set('biome', filters.biome);
    }

    return params;
};

export const fetchCurrentPoints = async (filters, bbox, page = 1) => {
    const params = baseParams(filters, bbox, true);
    params.set('page', String(page));
    params.set('limit', String(filters.pointBudget || MAP_CONFIG.defaultCurrentLimit));

    const payload = await fetchJson(MAP_CONFIG.apiCurrentUrl, params);
    return {
        data: Array.isArray(payload?.data) ? payload.data.map(sanitizePoint).filter(Boolean) : [],
        hasMore: Boolean(payload?.meta?.has_more),
    };
};
