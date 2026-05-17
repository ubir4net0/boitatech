import { inferIntensity, pointKey, toSafeString } from './utils.js';

const Cesium = window.Cesium;

const CORE_SIZE = 16;
const HALO_SIZE = 40;
const CORE_SCALE = 0.92;
const HALO_SCALE = 0.58;

const makeRadialCanvas = ({
    size,
    innerColor,
    middleColor,
    outerColor,
    innerStop = 0.0,
    middleStop = 0.34,
    outerStop = 1.0,
}) => {
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;

    const ctx = canvas.getContext('2d', { alpha: true });
    if (!ctx) return canvas;

    const radius = size / 2;
    const gradient = ctx.createRadialGradient(radius, radius, radius * 0.08, radius, radius, radius);
    gradient.addColorStop(innerStop, innerColor);
    gradient.addColorStop(middleStop, middleColor);
    gradient.addColorStop(outerStop, outerColor);

    ctx.clearRect(0, 0, size, size);
    ctx.fillStyle = gradient;
    ctx.beginPath();
    ctx.arc(radius, radius, radius, 0, Math.PI * 2);
    ctx.fill();

    return canvas;
};

const HOTSPOT_IMAGES = {
    core: makeRadialCanvas({
        size: CORE_SIZE,
        innerColor: 'rgba(255, 248, 246, 0.98)',
        middleColor: 'rgba(241, 46, 32, 0.96)',
        outerColor: 'rgba(255, 0, 0, 0)',
        innerStop: 0.0,
        middleStop: 0.5,
        outerStop: 1.0,
    }),
    halo: makeRadialCanvas({
        size: HALO_SIZE,
        innerColor: 'rgba(255, 126, 74, 0.3)',
        middleColor: 'rgba(255, 68, 34, 0.18)',
        outerColor: 'rgba(255, 0, 0, 0)',
        innerStop: 0.0,
        middleStop: 0.56,
        outerStop: 1.0,
    }),
};

const pulseProfile = (level) => {
    if (level === 'alto') {
        return { speed: 1.75, amp: 0.16, haloAmp: 0.2 };
    }

    if (level === 'baixo') {
        return { speed: 1.05, amp: 0.08, haloAmp: 0.12 };
    }

    return { speed: 1.35, amp: 0.11, haloAmp: 0.16 };
};

const makePulseScale = (CesiumRef, baseScale, level, isHalo = false) => {
    const profile = pulseProfile(level);
    const start = CesiumRef.JulianDate.now();

    return new CesiumRef.CallbackProperty((time) => {
        const elapsed = Math.max(0, CesiumRef.JulianDate.secondsDifference(time, start));
        const wave = (Math.sin(elapsed * profile.speed * Math.PI * 2) + 1) * 0.5;
        const amp = isHalo ? profile.haloAmp : profile.amp;
        return baseScale + (wave * amp);
    }, false);
};

const makeCoreBillboardGraphics = (level = 'medio') => ({
    image: HOTSPOT_IMAGES.core,
    scale: makePulseScale(Cesium, CORE_SCALE, level, false),
    scaleByDistance: new Cesium.NearFarScalar(1000, 1.4, 1000000, 0.62),
    translucencyByDistance: new Cesium.NearFarScalar(1000, 1.0, 1400000, 0.66),
    disableDepthTestDistance: Number.POSITIVE_INFINITY,
    verticalOrigin: Cesium.VerticalOrigin.CENTER,
    horizontalOrigin: Cesium.HorizontalOrigin.CENTER,
});

const makeHaloBillboardGraphics = (level = 'medio') => ({
    image: HOTSPOT_IMAGES.halo,
    scale: makePulseScale(Cesium, HALO_SCALE, level, true),
    scaleByDistance: new Cesium.NearFarScalar(1000, 1.34, 1000000, 0.58),
    translucencyByDistance: new Cesium.NearFarScalar(1000, 0.9, 1400000, 0.34),
    disableDepthTestDistance: Number.POSITIVE_INFINITY,
    verticalOrigin: Cesium.VerticalOrigin.CENTER,
    horizontalOrigin: Cesium.HorizontalOrigin.CENTER,
});

const buildProperties = (point, layerLabel, intensity) => ({
    kind: layerLabel,
    total: '1',
    uf: toSafeString(point.uf, 'N/D'),
    biome: toSafeString(point.biome, 'N/D'),
    municipio: toSafeString(point.municipio, 'N/D'),
    data: toSafeString(point.data, '-'),
    viewedAt: toSafeString(point.viewedAt, '-'),
    latitude: point.latitude.toFixed(5),
    longitude: point.longitude.toFixed(5),
    satelite: toSafeString(point.satelite, 'N/D'),
    fonte: toSafeString(point.fonte, 'INPE'),
    severity: intensity.level,
});

const shouldKeepPoint = (intensity, filters) => {
    if (!filters.priorityLevel) return true;
    if (filters.priorityLevel === 'critico') return intensity.level === 'alto';
    return filters.priorityLevel === intensity.level;
};

export const renderPoints = ({
    points,
    dataSource,
    entityCache,
    layerName,
    layerLabel,
    filters,
}) => {
    const active = new Set();

    for (const point of points) {
        const intensity = inferIntensity(point);
        if (!shouldKeepPoint(intensity, filters)) {
            continue;
        }

        const key = pointKey(point, layerName);
        active.add(key);

        let pair = entityCache.get(key);
        if (!pair) {
            const halo = dataSource.entities.add({
                id: `${key}:halo`,
                position: Cesium.Cartesian3.fromDegrees(point.longitude, point.latitude, 0),
                billboard: makeHaloBillboardGraphics(intensity.level),
                properties: buildProperties(point, layerLabel, intensity),
            });

            const core = dataSource.entities.add({
                id: key,
                position: Cesium.Cartesian3.fromDegrees(point.longitude, point.latitude, 0),
                billboard: makeCoreBillboardGraphics(intensity.level),
                properties: buildProperties(point, layerLabel, intensity),
            });

            core.__baseScale = CORE_SCALE;
            halo.__baseScale = HALO_SCALE;
            core.__pairedEntity = halo;
            halo.__pairedEntity = core;

            pair = { core, halo };
            entityCache.set(key, pair);
            continue;
        }

        const position = Cesium.Cartesian3.fromDegrees(point.longitude, point.latitude, 0);
        const payload = buildProperties(point, layerLabel, intensity);

        pair.core.position = position;
        pair.core.billboard.scale = makePulseScale(Cesium, CORE_SCALE, intensity.level, false);
        pair.core.billboard.scaleByDistance = new Cesium.NearFarScalar(1000, 1.4, 1000000, 0.62);
        pair.core.billboard.translucencyByDistance = new Cesium.NearFarScalar(1000, 1.0, 1400000, 0.66);
        pair.core.properties = payload;
        pair.core.__baseScale = CORE_SCALE;
        pair.core.show = true;

        pair.halo.position = position;
        pair.halo.billboard.scale = makePulseScale(Cesium, HALO_SCALE, intensity.level, true);
        pair.halo.billboard.scaleByDistance = new Cesium.NearFarScalar(1000, 1.34, 1000000, 0.58);
        pair.halo.billboard.translucencyByDistance = new Cesium.NearFarScalar(1000, 0.9, 1400000, 0.34);
        pair.halo.properties = payload;
        pair.halo.__baseScale = HALO_SCALE;
        pair.halo.show = true;
    }

    for (const [key, pair] of entityCache) {
        if (key.startsWith(`${layerName}:`) && !active.has(key)) {
            if (pair?.core) {
                dataSource.entities.remove(pair.core);
            }
            if (pair?.halo) {
                dataSource.entities.remove(pair.halo);
            }
            entityCache.delete(key);
        }
    }
};

