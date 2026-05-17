import L from 'leaflet';
import { MAP_CONFIG } from '../interactive-map/config.js';
import { ensureHeatLayer, renderHeat } from '../interactive-map/renderers/heatLayer.js';
import { ensureHotspotLayer, renderHotspots } from '../interactive-map/renderers/hotspotLayer.js';
import { createBaseLayer } from '../interactive-map/providers/tileProvider.js';

export const createLeafletInteractiveMap = ({ mapId, onHoverPoint, onHoverOut }) => {
    const southWest = L.latLng(MAP_CONFIG.bounds.south, MAP_CONFIG.bounds.west);
    const northEast = L.latLng(MAP_CONFIG.bounds.north, MAP_CONFIG.bounds.east);
    const maxBounds = L.latLngBounds(southWest, northEast);

    const map = L.map(mapId, {
        preferCanvas: true,
        zoomControl: true,
        attributionControl: false,
        maxBounds,
        maxBoundsViscosity: 0.9,
        minZoom: 4,
        maxZoom: 13,
        zoomAnimation: true,
        fadeAnimation: true,
        markerZoomAnimation: false,
    }).setView([-14.2, -53.6], 5);

    const base = createBaseLayer();
    base.addTo(map);

    const heat = ensureHeatLayer(map);
    const hotspots = ensureHotspotLayer(map);

    const render = ({ points, showHeat = true, showHotspots = true }) => {
        renderHotspots({
            hotspotLayer: hotspots,
            points,
            onHover: onHoverPoint,
            onOut: onHoverOut,
        });

        renderHeat(heat, points);

        if (!showHeat && map.hasLayer(heat)) map.removeLayer(heat);
        if (showHeat && !map.hasLayer(heat)) map.addLayer(heat);

        if (!showHotspots && map.hasLayer(hotspots)) map.removeLayer(hotspots);
        if (showHotspots && !map.hasLayer(hotspots)) map.addLayer(hotspots);
    };

    return { map, layers: { base, heat, hotspots }, render };
};
