import L from 'leaflet';
import 'leaflet.markercluster';
import { inferSeverity, severityColor, toSafeString } from '../utils.js';

const sizeBySeverity = {
    critico: 18,
    alto: 14,
    medio: 11,
    baixo: 9,
};

const hotspotIcon = (severity) => {
    const color = severityColor(severity);
    const size = sizeBySeverity[severity] ?? 11;
    const halo = Math.max(20, size * 2.1);

    return L.divIcon({
        className: 'bt-hotspot-icon',
        html: `<span style="
            width:${size}px;
            height:${size}px;
            border-radius:999px;
            display:block;
            background:${color};
            box-shadow:0 0 ${Math.round(halo * 0.7)}px ${color}, 0 0 ${halo}px rgba(239,68,68,.45);
            border:1px solid rgba(255,255,255,.52);
        "></span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
    });
};

const clusterIcon = (cluster) => {
    const count = cluster.getChildCount();
    const size = Math.min(56, 34 + Math.log(count + 1) * 8);

    return L.divIcon({
        className: 'bt-cluster-icon',
        html: `<span style="
            width:${size}px;
            height:${size}px;
            border-radius:999px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:12px;
            font-weight:700;
            color:#fff;
            background:radial-gradient(circle, rgba(239,68,68,.95), rgba(153,27,27,.95));
            border:1px solid rgba(255,255,255,.35);
            box-shadow:0 0 28px rgba(239,68,68,.58);
        ">${count}</span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
    });
};

export const ensureHotspotLayer = (map) => {
    const layer = L.markerClusterGroup({
        chunkedLoading: true,
        chunkProgress: () => {},
        chunkDelay: 18,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        removeOutsideVisibleBounds: true,
        maxClusterRadius: 55,
        iconCreateFunction: clusterIcon,
    });

    layer.addTo(map);
    return layer;
};

export const renderHotspots = ({ hotspotLayer, points, onHover, onOut }) => {
    hotspotLayer.clearLayers();

    const markers = points.map((point) => {
        const severity = inferSeverity(point);
        const marker = L.marker([point.latitude, point.longitude], {
            icon: hotspotIcon(severity),
            keyboard: false,
            riseOnHover: true,
        });

        marker.bindTooltip(
            `${toSafeString(point.municipio)} · ${toSafeString(point.uf)}<br>${toSafeString(point.viewedAt)}`,
            {
                direction: 'top',
                className: 'hotspot-tooltip',
                offset: [0, -8],
            },
        );

        marker.on('mouseover', () => onHover?.(point, severity));
        marker.on('mouseout', () => onOut?.());

        return marker;
    });

    hotspotLayer.addLayers(markers);
};
