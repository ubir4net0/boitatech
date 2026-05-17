import L from 'leaflet';

export const createBaseLayer = () => {
    const dark = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap & CARTO',
        subdomains: 'abcd',
        maxZoom: 19,
        crossOrigin: true,
    });

    dark.on('tileerror', () => {
        // fallback silencioso
    });

    return dark;
};
