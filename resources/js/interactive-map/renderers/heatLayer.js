import L from 'leaflet';
import 'leaflet.heat';
import { inferSeverity } from '../utils.js';

const intensity = (severity) => {
    if (severity === 'critico') return 1;
    if (severity === 'alto') return 0.85;
    if (severity === 'baixo') return 0.45;
    return 0.65;
};

export const ensureHeatLayer = (map) => {
    const layer = L.heatLayer([], {
        radius: 24,
        blur: 18,
        maxZoom: 11,
        minOpacity: 0.25,
        gradient: {
            0.2: '#7f1d1d',
            0.45: '#b91c1c',
            0.7: '#ef4444',
            1: '#fb7185',
        },
    });

    layer.addTo(map);
    return layer;
};

export const renderHeat = (heatLayer, points) => {
    const payload = points.map((point) => {
        const severity = inferSeverity(point);
        return [point.latitude, point.longitude, intensity(severity)];
    });

    heatLayer.setLatLngs(payload);
};
