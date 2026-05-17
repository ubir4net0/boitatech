import { MAP_CONFIG } from '../config.js';
import { sanitizePoint } from '../utils.js';

const fetchJson = async (url, signal) => {
    const response = await fetch(url, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
};

const buildParams = ({ bbox, filters }) => {
    const params = new URLSearchParams();
    params.set('bbox', `${bbox.west},${bbox.south},${bbox.east},${bbox.north}`);
    params.set('start_date', filters.startDate);
    params.set('end_date', filters.endDate);
    params.set('limit', String(filters.pointBudget || MAP_CONFIG.defaultCurrentLimit));

    if (filters.biome) {
        params.set('biome', filters.biome);
    }

    return params;
};

export const fetchCurrentFocos = async ({ bbox, filters, signal }) => {
    const params = buildParams({ bbox, filters });
    const payload = await fetchJson(`${MAP_CONFIG.apiCurrentUrl}?${params.toString()}`, signal);
    const rawData = Array.isArray(payload?.data) ? payload.data : [];

    return rawData.map(sanitizePoint).filter(Boolean);
};
