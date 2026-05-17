import { MAP_CONFIG, UI } from './config.js';
import { state } from './state.js';
import { buildDateRange, debounce, toSafeString } from './utils.js';
import { fetchRealtimeFocos } from '../services/focosRealtimeService.js';
import { createLeafletInteractiveMap } from '../maps/leafletInteractiveMapFactory.js';
import { LeafletGestureRuntime } from '../gesture/leafletGestureRuntime.js';
import { createGestureStatusPresenter } from '../components/gesture/gestureStatusPresenter.js';

const formatTime = (date) => new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' }).format(date);

const HAND_CONSENT_STORAGE_KEY = 'boitatech_lgpd_hand_tracking_consent_v1';

const postLgpdConsent = async ({ granted, context = {} }) => {
    if (!MAP_CONFIG.apiLgpdConsentUrl) return;

    try {
        await fetch(MAP_CONFIG.apiLgpdConsentUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                purpose: 'webcam_hand_tracking',
                granted,
                policy_version: MAP_CONFIG.lgpdPolicyVersion,
                context,
            }),
        });
    } catch (error) {
        console.warn('[InteractiveMap] LGPD consent logging failed', error);
    }
};

const ensureHandTrackingConsent = async () => {
    const saved = window.localStorage.getItem(HAND_CONSENT_STORAGE_KEY);
    if (saved === 'granted') {
        return true;
    }

    const granted = window.confirm(
        'Para usar hand tracking, a webcam será processada localmente no navegador. Nenhuma imagem é armazenada nos servidores. Deseja continuar?',
    );

    await postLgpdConsent({
        granted,
        context: {
            source: 'mapa-interativo',
            policy_url: MAP_CONFIG.lgpdPolicyUrl,
            user_agent: navigator.userAgent,
        },
    });

    if (!granted) {
        return false;
    }

    window.localStorage.setItem(HAND_CONSENT_STORAGE_KEY, 'granted');
    return true;
};

const setSidebarState = (open) => {
    document.body.classList.toggle('sidebar-open', open);
};

const urgencyBadge = (count) => {
    if (count > 160) return '🔴';
    if (count > 80) return '🟠';
    if (count > 30) return '🟡';
    return '🟢';
};

const updateIncidentCard = (point, severity) => {
    if (!UI.incidentCard) return;

    if (!point) {
        UI.incidentCard.textContent = 'Passe o mouse sobre um hotspot para detalhes operacionais.';
        return;
    }

    UI.incidentCard.textContent = `${toSafeString(point.municipio)} (${toSafeString(point.uf)}) • ${toSafeString(point.biome)} • ${toSafeString(point.viewedAt)} • nível ${severity.toUpperCase()}`;
};

const updateKpis = () => {
    const points = state.data.points;
    const clusterCount = state.layers.hotspots?._featureGroup?.getLayers?.().length ?? 0;

    if (UI.kpiPoints) UI.kpiPoints.textContent = String(points.length);
    if (UI.kpiClusters) UI.kpiClusters.textContent = String(clusterCount);
    if (UI.kpiUrgency) UI.kpiUrgency.textContent = urgencyBadge(points.length);
    if (UI.kpiUpdated && state.data.lastUpdatedAt) {
        UI.kpiUpdated.textContent = formatTime(state.data.lastUpdatedAt);
    }
};

const getBboxFromMap = () => {
    const b = state.map.getBounds();
    return {
        west: Math.max(MAP_CONFIG.bounds.west, b.getWest()),
        south: Math.max(MAP_CONFIG.bounds.south, b.getSouth()),
        east: Math.min(MAP_CONFIG.bounds.east, b.getEast()),
        north: Math.min(MAP_CONFIG.bounds.north, b.getNorth()),
    };
};

const renderAll = (points) => {
    state.data.points = points;

    state.mapRenderer?.render({
        points,
        showHeat: state.controls.showHeat,
        showHotspots: state.controls.showHotspots,
    });

    state.data.lastUpdatedAt = new Date();
    updateKpis();
};

const loadPoints = async () => {
    const requestId = ++state.request.id;

    if (state.request.controller) {
        state.request.controller.abort();
    }

    state.request.controller = new AbortController();

    try {
        const points = await fetchRealtimeFocos({
            bbox: getBboxFromMap(),
            filters: state.filters,
            signal: state.request.controller.signal,
        });

        if (requestId !== state.request.id) return;
        renderAll(points);
    } catch (error) {
        if (error.name === 'AbortError') return;
        console.error('[InteractiveMap] loadPoints error', error);
        updateIncidentCard(null);
        if (UI.incidentCard) {
            UI.incidentCard.textContent = 'Falha ao carregar pontos no momento. Tente atualizar.';
        }
    }
};

const bindFilters = () => {
    UI.startDate?.addEventListener('change', () => {
        state.filters.startDate = UI.startDate.value;
        loadPoints();
    });

    UI.endDate?.addEventListener('change', () => {
        state.filters.endDate = UI.endDate.value;
        loadPoints();
    });

    UI.biomeSelect?.addEventListener('change', () => {
        state.filters.biome = UI.biomeSelect.value;
        loadPoints();
    });

    UI.pointBudget?.addEventListener('change', () => {
        state.filters.pointBudget = Number(UI.pointBudget.value || MAP_CONFIG.defaultCurrentLimit);
        loadPoints();
    });
};

const bindButtons = (gestureRuntime, gestureUi) => {
    UI.refreshBtn?.addEventListener('click', () => {
        loadPoints();
    });

    UI.toggleHeatBtn?.addEventListener('click', () => {
        state.controls.showHeat = !state.controls.showHeat;
        UI.toggleHeatBtn.setAttribute('aria-pressed', String(state.controls.showHeat));
        UI.toggleHeatBtn.textContent = state.controls.showHeat ? 'Heatmap ON' : 'Heatmap OFF';
        renderAll(state.data.points);
    });

    UI.toggleHotspotsBtn?.addEventListener('click', () => {
        state.controls.showHotspots = !state.controls.showHotspots;
        UI.toggleHotspotsBtn.setAttribute('aria-pressed', String(state.controls.showHotspots));
        UI.toggleHotspotsBtn.textContent = state.controls.showHotspots ? 'Hotspots ON' : 'Hotspots OFF';
        renderAll(state.data.points);
    });

    UI.toggleHandBtn?.addEventListener('click', async () => {
        const isRunning = gestureRuntime?.controller?.enabled ?? false;

        if (!isRunning) {
            const consentGranted = await ensureHandTrackingConsent();
            if (!consentGranted) return;

            gestureUi.loading();
            const started = await gestureRuntime.start();
            if (!started) return;
            gestureUi.active();
            return;
        }

        gestureRuntime.stop();
        gestureUi.idle();
    });
};

const setupMap = () => {
    const mapBundle = createLeafletInteractiveMap({
        mapId: UI.mapId,
        onHoverPoint: updateIncidentCard,
        onHoverOut: () => updateIncidentCard(null),
    });

    state.map = mapBundle.map;
    state.layers.base = mapBundle.layers.base;
    state.layers.heat = mapBundle.layers.heat;
    state.layers.hotspots = mapBundle.layers.hotspots;
    state.mapRenderer = mapBundle;

    const debouncedLoad = debounce(loadPoints, 320);
    state.map.on('moveend', debouncedLoad);
    state.map.on('zoomend', debouncedLoad);
};

const setupDefaultFilters = () => {
    const range = buildDateRange(30);
    state.filters.startDate = range.startDate;
    state.filters.endDate = range.endDate;
    state.filters.biome = '';
    state.filters.pointBudget = Number(UI.pointBudget?.value || MAP_CONFIG.defaultCurrentLimit);

    if (UI.startDate) UI.startDate.value = range.startDate;
    if (UI.endDate) UI.endDate.value = range.endDate;
};

const bootstrapSidebarMobile = () => {
    UI.sidebarToggle?.addEventListener('click', () => {
        setSidebarState(!document.body.classList.contains('sidebar-open'));
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) {
            setSidebarState(false);
        }
    });
};

const bootstrap = async () => {
    setupDefaultFilters();
    setupMap();

    const gestureUi = createGestureStatusPresenter({
        handInfoDot: UI.handInfoDot,
        handState: UI.handState,
        handHint: UI.handHint,
        toggleButton: UI.toggleHandBtn,
    });

    gestureUi.idle();

    const gestureRuntime = new LeafletGestureRuntime({
        map: state.map,
        onStatus: (status, message) => {
            if (status === 'loading') gestureUi.loading();
            else if (status === 'active') gestureUi.active(message);
            else if (status === 'error') gestureUi.error(message);
            else gestureUi.idle();
        },
    });

    bindFilters();
    bindButtons(gestureRuntime, gestureUi);
    bootstrapSidebarMobile();

    await loadPoints();

    window.addEventListener('beforeunload', () => {
        gestureRuntime.destroy();
    });
};

bootstrap().catch((error) => {
    console.error('[InteractiveMap] bootstrap error', error);
    if (UI.incidentCard) {
        UI.incidentCard.textContent = 'Falha ao iniciar mapa interativo.';
    }
});
