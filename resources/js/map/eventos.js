import { escapeHtml, formatIncidentHtml, toSafeString } from './utils.js';

export const bindInteractions = ({ viewer, tooltipEl, incidentCardEl }) => {
    const Cesium = window.Cesium;
    const handler = new Cesium.ScreenSpaceEventHandler(viewer.scene.canvas);
    let hovered = null;

    const hideTooltip = () => {
        if (tooltipEl) tooltipEl.style.display = 'none';
    };

    const toUtcLabel = (value) => {
        const date = new Date(String(value ?? '').trim());
        if (Number.isNaN(date.getTime())) return toSafeString(value, '-');
        return `${date.toISOString().slice(0, 16).replace('T', ' ')} UTC`;
    };

    const setHoverSize = (entity, isHovered) => {
        const isBillboard = Boolean(entity?.billboard);
        const isPoint = Boolean(entity?.point);
        if (!isBillboard && !isPoint) return;

        if (isBillboard) {
            const baseScale = entity.__baseScale ?? entity.billboard.scale?.getValue?.() ?? 1;
            entity.billboard.scale = isHovered ? baseScale * 1.12 : baseScale;
        }

        if (isPoint) {
            const baseSize = entity.__basePixelSize ?? entity.point.pixelSize?.getValue?.() ?? 6;
            entity.point.pixelSize = isHovered ? baseSize + 2 : baseSize;
        }

        if (entity.__pairedEntity?.billboard) {
            const pairBaseScale = entity.__pairedEntity.__baseScale
                ?? entity.__pairedEntity.billboard.scale?.getValue?.()
                ?? 1;
            entity.__pairedEntity.billboard.scale = isHovered ? pairBaseScale * 1.09 : pairBaseScale;
        }

        if (entity.__pairedEntity?.point) {
            const pairBase = entity.__pairedEntity.__basePixelSize
                ?? entity.__pairedEntity.point.pixelSize?.getValue?.()
                ?? 6;
            entity.__pairedEntity.point.pixelSize = isHovered ? pairBase + 2 : pairBase;
        }
    };

    const resolveInteractiveEntity = (entity) => {
        if (!entity) return null;
        if (entity.__pairedEntity && entity.id?.endsWith?.(':halo')) {
            return entity.__pairedEntity;
        }
        return entity;
    };

    const extractPayload = (entity) => {
        const props = entity?.properties;
        if (!props) return null;
        return {
            kind: toSafeString(props.kind?.getValue?.(), 'Camada'),
            total: toSafeString(props.total?.getValue?.(), '1'),
            uf: toSafeString(props.uf?.getValue?.(), 'N/D'),
            biome: toSafeString(props.biome?.getValue?.(), 'N/D'),
            municipio: toSafeString(props.municipio?.getValue?.(), 'N/D'),
            data: toSafeString(props.data?.getValue?.(), '-'),
            viewedAt: toSafeString(props.viewedAt?.getValue?.(), '-'),
            latitude: toSafeString(props.latitude?.getValue?.(), '-'),
            longitude: toSafeString(props.longitude?.getValue?.(), '-'),
            fonte: toSafeString(props.fonte?.getValue?.(), 'INPE'),
        };
    };

    const extractClusterPayload = (entity) => {
        if (!entity?.__isCluster) return null;
        return {
            total: toSafeString(entity.__clusterCount, '0'),
            latitude: toSafeString(entity.__clusterLatitude, '-'),
            longitude: toSafeString(entity.__clusterLongitude, '-'),
            region: toSafeString(entity.__clusterRegion, 'Brasil'),
            bounds: entity.__clusterBounds ?? null,
        };
    };

    const zoomToCluster = (payload) => {
        if (!payload) return;

        const Cesium = window.Cesium;
        const bounds = payload.bounds;
        if (bounds && Number.isFinite(bounds.west) && Number.isFinite(bounds.south) && Number.isFinite(bounds.east) && Number.isFinite(bounds.north)) {
            viewer.camera.flyTo({
                destination: Cesium.Rectangle.fromDegrees(bounds.west, bounds.south, bounds.east, bounds.north),
                duration: 0.85,
                easingFunction: Cesium.EasingFunction.QUADRATIC_OUT,
            });
            return;
        }

        const lat = Number(payload.latitude);
        const lon = Number(payload.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;

        const total = Math.max(1, Number(payload.total) || 1);
        const altitude = Math.max(130000, 1_600_000 / Math.sqrt(total + 1));
        viewer.camera.flyTo({
            destination: Cesium.Cartesian3.fromDegrees(lon, lat, altitude),
            duration: 0.85,
            easingFunction: Cesium.EasingFunction.QUADRATIC_OUT,
        });
    };

    handler.setInputAction((movement) => {
        const picked = viewer.scene.pick(movement.endPosition);
        const entity = resolveInteractiveEntity(picked?.id ?? null);

        if (hovered && hovered !== entity) {
            setHoverSize(hovered, false);
        }

        hovered = entity;
        if (hovered) {
            setHoverSize(hovered, true);
        }

        const payload = extractPayload(entity);
        const clusterPayload = extractClusterPayload(entity);

        if (!payload && !clusterPayload) {
            hideTooltip();
            viewer.scene.requestRender();
            return;
        }

        if (clusterPayload) {
            const latVal = escapeHtml(clusterPayload.latitude);
            const lonVal = escapeHtml(clusterPayload.longitude);
            const totalVal = escapeHtml(clusterPayload.total);
            const regionVal = escapeHtml(clusterPayload.region);

            tooltipEl.innerHTML = `
                <div class="tt-row">
                    <span class="tt-icon">🔥</span>
                    <span>${totalVal} alertas agrupados</span>
                </div>
                <div class="tt-row">
                    <span class="tt-icon">🗺️</span>
                    <span>${regionVal}</span>
                </div>
                <div class="tt-row tt-coords">
                    <span class="tt-icon">📍</span>
                    <span>${latVal}, ${lonVal}</span>
                </div>
                <div class="tt-row">
                    <span class="tt-icon">🔎</span>
                    <span>Aproxime o zoom para ver cada foco</span>
                </div>
            `;
        } else {
            const location = payload.uf !== 'N/D'
                ? `${payload.uf} / ${payload.municipio}`
                : payload.municipio !== 'N/D'
                    ? payload.municipio
                    : payload.biome;

            const dateVal = escapeHtml(toUtcLabel(payload.viewedAt !== '-' ? payload.viewedAt : payload.data));
            const locationVal = escapeHtml(location);
            const fonteVal = escapeHtml(payload.fonte || 'INPE');

            tooltipEl.innerHTML = `
                <div class="tt-row">
                    <span class="tt-icon">🔥</span>
                    <span>Foco detectado</span>
                </div>
                <div class="tt-row">
                    <span class="tt-icon">📍</span>
                    <span>${locationVal}</span>
                </div>
                <div class="tt-row tt-coords">
                    <span class="tt-icon">🕒</span>
                    <span>${dateVal}</span>
                </div>
                <div class="tt-row">
                    <span class="tt-icon">📡</span>
                    <span>${fonteVal}</span>
                </div>
            `;
        }

        tooltipEl.style.left = `${movement.endPosition.x + 14}px`;
        tooltipEl.style.top  = `${movement.endPosition.y + 14}px`;
        tooltipEl.style.display = 'block';
        viewer.scene.requestRender();
    }, Cesium.ScreenSpaceEventType.MOUSE_MOVE);

    handler.setInputAction((click) => {
        const picked = viewer.scene.pick(click.position);
        const entity = resolveInteractiveEntity(picked?.id);
        const payload = extractPayload(entity);
        const clusterPayload = extractClusterPayload(entity);

        if (!payload && !clusterPayload) {
            hideTooltip();
            return;
        }

        if (incidentCardEl) {
            if (clusterPayload) {
                incidentCardEl.innerHTML = `
                    <strong>Cluster de alertas</strong>
                    <div>Total agrupado: ${escapeHtml(clusterPayload.total)}</div>
                    <div>Região: ${escapeHtml(clusterPayload.region)}</div>
                    <div>Centro aproximado: ${escapeHtml(clusterPayload.latitude)}, ${escapeHtml(clusterPayload.longitude)}</div>
                    <div>Aproxime o zoom para abrir os focos individuais.</div>
                `;

                zoomToCluster(clusterPayload);
            } else {
                incidentCardEl.innerHTML = formatIncidentHtml(payload);
            }
        }
    }, Cesium.ScreenSpaceEventType.LEFT_CLICK);

    handler.setInputAction(() => {
        hideTooltip();
    }, Cesium.ScreenSpaceEventType.LEFT_DOWN);

    return () => handler.destroy();
};
