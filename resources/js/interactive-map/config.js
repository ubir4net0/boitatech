const raw = window.BOITATECH_INTERACTIVE_MAP_CONFIG ?? {};

export const MAP_CONFIG = {
    apiCurrentUrl: raw.apiCurrentUrl ?? '/api/focos/current',
    apiHealthSyncUrl: raw.apiHealthSyncUrl ?? '/api/health/sync',
    apiLgpdConsentUrl: raw.apiLgpdConsentUrl ?? '/api/lgpd/consent',
    defaultCurrentLimit: Number(raw.defaultCurrentLimit ?? 500),
    lgpdPolicyUrl: raw.lgpdPolicyUrl ?? '/privacidade',
    lgpdPolicyVersion: raw.lgpdPolicyVersion ?? '2026.05',
    bounds: {
        west: Number(raw.brazilBounds?.west ?? -74),
        south: Number(raw.brazilBounds?.south ?? -34),
        east: Number(raw.brazilBounds?.east ?? -34),
        north: Number(raw.brazilBounds?.north ?? 6),
    },
};

export const UI = {
    mapId: 'leafletMap',
    refreshBtn: document.getElementById('refreshBtn'),
    toggleHeatBtn: document.getElementById('toggleHeatBtn'),
    toggleHotspotsBtn: document.getElementById('toggleHotspotsBtn'),
    toggleHandBtn: document.getElementById('toggleHandBtn'),
    startDate: document.getElementById('startDate'),
    endDate: document.getElementById('endDate'),
    biomeSelect: document.getElementById('biomeSelect'),
    pointBudget: document.getElementById('pointBudget'),
    incidentCard: document.getElementById('incidentCard'),
    kpiPoints: document.getElementById('kpiPoints'),
    kpiClusters: document.getElementById('kpiClusters'),
    kpiUrgency: document.getElementById('kpiUrgency'),
    kpiUpdated: document.getElementById('kpiUpdated'),
    handState: document.getElementById('handState'),
    handHint: document.getElementById('handHint'),
    handInfoDot: document.getElementById('handInfoDot'),
    sidebarToggle: document.getElementById('sidebarToggle'),
};

export const COLORS = {
    critico: '#ef4444',
    alto: '#f97316',
    medio: '#fde047',
    baixo: '#22c55e',
};
