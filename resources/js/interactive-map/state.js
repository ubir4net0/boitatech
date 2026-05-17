import { MAP_CONFIG } from './config.js';

export const state = {
    map: null,
    mapRenderer: null,
    layers: {
        heat: null,
        hotspots: null,
        base: null,
    },
    controls: {
        showHeat: true,
        showHotspots: true,
    },
    data: {
        points: [],
        lastUpdatedAt: null,
    },
    filters: {
        startDate: '',
        endDate: '',
        biome: '',
        pointBudget: MAP_CONFIG.defaultCurrentLimit,
    },
    request: {
        id: 0,
        controller: null,
    },
};
