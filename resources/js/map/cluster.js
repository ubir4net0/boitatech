import { CLUSTER_OPTIONS } from './config.js';

const dominantBiome = (entities, now) => {
    const counts = new Map();

    for (const entity of entities) {
        if (typeof entity?.id === 'string' && entity.id.endsWith(':halo')) continue;
        const biome = String(entity?.properties?.biome?.getValue?.(now) ?? '').trim();
        if (!biome || biome === 'N/D') continue;
        counts.set(biome, (counts.get(biome) ?? 0) + 1);
    }

    if (counts.size === 0) return 'Brasil';

    return Array.from(counts.entries())
        .sort((a, b) => b[1] - a[1])[0][0];
};

const computeBounds = (Cesium, positions) => {
    if (!positions.length) return null;

    let west = Number.POSITIVE_INFINITY;
    let south = Number.POSITIVE_INFINITY;
    let east = Number.NEGATIVE_INFINITY;
    let north = Number.NEGATIVE_INFINITY;

    for (const cartesian of positions) {
        const c = Cesium.Cartographic.fromCartesian(cartesian);
        const lon = Cesium.Math.toDegrees(c.longitude);
        const lat = Cesium.Math.toDegrees(c.latitude);

        west = Math.min(west, lon);
        south = Math.min(south, lat);
        east = Math.max(east, lon);
        north = Math.max(north, lat);
    }

    const lonSpan = Math.max(0.2, east - west);
    const latSpan = Math.max(0.2, north - south);
    const padding = Math.min(4, Math.max(0.6, Math.max(lonSpan, latSpan) * 0.35));

    return {
        west: west - padding,
        south: south - padding,
        east: east + padding,
        north: north + padding,
    };
};

export const setupCluster = (dataSource, colorCss = '#ef4444') => {
    const Cesium = window.Cesium;
    const clustering = dataSource.clustering;

    clustering.enabled = true;
    clustering.pixelRange = CLUSTER_OPTIONS.pixelRange;
    clustering.minimumClusterSize = CLUSTER_OPTIONS.minimumClusterSize;

    const listener = clustering.clusterEvent.addEventListener((entities, cluster) => {
        const coreEntities = entities.filter((entity) => !(typeof entity?.id === 'string' && entity.id.endsWith(':halo')));
        const sourceEntities = coreEntities.length > 0 ? coreEntities : entities;

        const now = Cesium.JulianDate.now();
        const validPositions = sourceEntities
            .map((entity) => entity.position?.getValue?.(now))
            .filter(Boolean);

        let latitude = null;
        let longitude = null;

        if (validPositions.length > 0) {
            let sumLat = 0;
            let sumLon = 0;

            for (const cartesian of validPositions) {
                const cartographic = Cesium.Cartographic.fromCartesian(cartesian);
                sumLat += Cesium.Math.toDegrees(cartographic.latitude);
                sumLon += Cesium.Math.toDegrees(cartographic.longitude);
            }

            latitude = (sumLat / validPositions.length).toFixed(5);
            longitude = (sumLon / validPositions.length).toFixed(5);
        }

        const count = sourceEntities.length;
        const bounds = computeBounds(Cesium, validPositions);
        const region = dominantBiome(sourceEntities, now);

        const clusterId = {
            __isCluster: true,
            __clusterCount: count,
            __clusterLatitude: latitude,
            __clusterLongitude: longitude,
            __clusterRegion: region,
            __clusterBounds: bounds,
        };

        const intensity = Math.min(1, Math.log2(count + 1) / 6.8);
        const size = Math.min(38, 12 + Math.log2(count + 1) * 4.4);
        const alpha = Math.min(0.9, 0.66 + intensity * 0.16);
        const baseColor = Cesium.Color.fromCssColorString(colorCss);

        cluster.billboard.show = false;
        cluster.label.show = false;

        cluster.point.show = true;
        cluster.point.pixelSize = size;
        cluster.point.color = baseColor.withAlpha(alpha);
        cluster.point.scaleByDistance = new Cesium.NearFarScalar(1000, 1.28, 1000000, 0.56);
        cluster.point.translucencyByDistance = new Cesium.NearFarScalar(1000, 0.96, 1500000, 0.62);
        cluster.point.disableDepthTestDistance = Number.POSITIVE_INFINITY;
        cluster.point.id = clusterId;
    });

    return () => listener();
};
