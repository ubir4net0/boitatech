import { MAP_CONFIG, COLORS } from './config.js';
import { initViewer } from './viewer.js';
import { setupCluster } from './cluster.js';
import { fetchCurrentPoints } from './api.js';
import { bindInteractions } from './eventos.js';
import { renderPoints } from './pontos.js';
import { fetchSyncHealth } from './services/syncHealth.js';
import { buildDefaultDates, debounce, getViewportBbox, toSafeString } from './utils.js';
import { CesiumGestureRuntime } from '../gesture/cesiumGestureRuntime.js';
import { createGestureStatusPresenter } from '../components/gesture/gestureStatusPresenter.js';

const ui = {
    sidebar: document.getElementById('mapSidebar'),
    sidebarToggle: document.getElementById('sidebarToggle'),
    sidebarClose: document.getElementById('sidebarClose'),
    sidebarBackdrop: document.getElementById('sidebarBackdrop'),
    status: document.getElementById('mapaStatus'),
    syncHealthBadge: document.getElementById('syncHealthBadge'),
    syncHealthMeta: document.getElementById('syncHealthMeta'),
    loading: document.getElementById('loading'),
    loadingText: document.getElementById('loadingText'),
    loadingProgressFill: document.getElementById('loadingProgressFill'),
    loadingProgressLabel: document.getElementById('loadingProgressLabel'),
    tooltip: document.getElementById('alertaTooltip'),
    cameraLimitNotice: document.getElementById('cameraLimitNotice'),
    incidentCard: document.getElementById('incidentCard'),
    statCurrentCount: document.getElementById('statCurrentCount'),
    statBiomeCount: document.getElementById('statBiomeCount'),
    statCadence: document.getElementById('statCadence'),
    lastUpdatedRelative: document.getElementById('lastUpdatedRelative'),
    statVisibleCount: document.getElementById('statVisibleCount'),
    statLayerMode: document.getElementById('statLayerMode'),
    statViewport: document.getElementById('statViewport'),
    statResolution: document.getElementById('statResolution'),
    statUrgencyBadge: document.getElementById('statUrgencyBadge'),
    refreshBtn: document.getElementById('refreshBtn'),
    loadMoreBtn: document.getElementById('carregarMaisBtn'),
    toggleCurrent: document.getElementById('toggleCurrent'),
    biomeSelect: document.getElementById('biomeSelect'),
    pointBudgetSelect: document.getElementById('pointBudgetSelect'),
    priorityLevelSelect: document.getElementById('priorityLevelSelect'),
    startDate: document.getElementById('historicoStartDate'),
    endDate: document.getElementById('historicoEndDate'),
    quickRangeButtons: Array.from(document.querySelectorAll('.quick-range [data-range-days]')),
    toggleHandCesiumBtn: document.getElementById('toggleHandCesiumBtn'),
    handInfoDotCesium: document.getElementById('handInfoDotCesium'),
    handStateCesium: document.getElementById('handStateCesium'),
    handHintCesium: document.getElementById('handHintCesium'),
};

const state = {
    viewer: null,
    dataSources: null,
    page: 1,
    hasMore: true,
    requestId: 0,
    entityCache: new Map(),
    cameraConstraint: {
        isCorrecting: false,
        lastCorrectionAt: 0,
    },
    filters: {
        showCurrent: true,
        startDate: '',
        endDate: '',
        biome: '',
        pointBudget: MAP_CONFIG.defaultCurrentLimit,
        priorityLevel: '',
    },
    selectedPointBudget: MAP_CONFIG.defaultCurrentLimit,
    lastRefreshAt: null,
    healthMonitor: {
        inFlight: false,
        timerId: null,
        lastSignature: '',
        nextIntervalMs: 60_000,
    },
    firstRevealDone: false,
    gesture: {
        runtime: null,
        enabled: false,
    },
};

const CESIUM_HAND_CONSENT_KEY = 'boitatech_lgpd_cesium_hand_tracking_consent_v1';

const ensureCesiumGestureConsent = async () => {
    const saved = window.localStorage.getItem(CESIUM_HAND_CONSENT_KEY);
    if (saved === 'granted') return true;

    const granted = window.confirm(
        'Para usar hand tracking no mapa 3D, a webcam será processada localmente no navegador. Nenhuma imagem é enviada para servidores. Deseja continuar?',
    );

    if (!granted) return false;

    window.localStorage.setItem(CESIUM_HAND_CONSENT_KEY, 'granted');
    return true;
};

const revealEntitiesGradually = (dataSource, batchSize = 80) => {
    const entities = dataSource?.entities?.values ?? [];
    if (!Array.isArray(entities) || entities.length === 0) {
        return;
    }

    for (const entity of entities) {
        entity.show = false;
    }

    let index = 0;
    const tick = () => {
        const end = Math.min(index + batchSize, entities.length);
        for (let i = index; i < end; i += 1) {
            entities[i].show = true;
        }

        dataSource.show = true;
        state.viewer?.scene?.requestRender();

        index = end;
        if (index < entities.length) {
            window.requestAnimationFrame(tick);
        }
    };

    window.requestAnimationFrame(tick);
};

const getCameraHeight = () => {
    const h = state.viewer?.camera?.positionCartographic?.height;
    return Number.isFinite(h) ? h : 6_000_000;
};

const getLodPreset = (height) => {
    if (height >= 3_200_000) {
        return { budgetCap: 260, pixelRange: 58, minimumClusterSize: 4, label: 'Macro' };
    }

    if (height >= 1_600_000) {
        return { budgetCap: 420, pixelRange: 50, minimumClusterSize: 3, label: 'Regional' };
    }

    if (height >= 700_000) {
        return { budgetCap: 680, pixelRange: 42, minimumClusterSize: 2, label: 'Sub-regional' };
    }

    return { budgetCap: 1000, pixelRange: 34, minimumClusterSize: 2, label: 'Detalhado' };
};

const applyDynamicLod = () => {
    if (!state.viewer || !state.dataSources?.current?.clustering) return;

    const lod = getLodPreset(getCameraHeight());
    state.filters.pointBudget = Math.max(120, Math.min(state.selectedPointBudget, lod.budgetCap));

    const clustering = state.dataSources.current.clustering;
    clustering.pixelRange = lod.pixelRange;
    clustering.minimumClusterSize = lod.minimumClusterSize;

    if (ui.statResolution) {
        ui.statResolution.textContent = `${lod.label} · LOD`;
    }
};

const formatRelativeUpdate = (date) => {
    if (!date) return 'agora';
    const diffMs = Date.now() - date.getTime();
    const min = Math.max(0, Math.floor(diffMs / 60000));
    if (min < 1) return 'agora';
    if (min === 1) return 'há 1 minuto';
    if (min < 60) return `há ${min} minutos`;
    const hrs = Math.floor(min / 60);
    return hrs === 1 ? 'há 1 hora' : `há ${hrs} horas`;
};

const updateRelativeClock = () => {
    if (ui.lastUpdatedRelative) {
        ui.lastUpdatedRelative.textContent = formatRelativeUpdate(state.lastRefreshAt);
    }
};

const applySyncHealth = (healthModel, { force = false } = {}) => {
    if (!healthModel || (!force && state.healthMonitor.lastSignature === healthModel.signature)) {
        return;
    }

    state.healthMonitor.lastSignature = healthModel.signature;

    if (ui.syncHealthBadge) {
        ui.syncHealthBadge.dataset.state = healthModel.state;
        ui.syncHealthBadge.textContent = `● ${healthModel.label}`;
    }

    if (ui.syncHealthMeta) {
        ui.syncHealthMeta.textContent = healthModel.meta;
    }
};

const scheduleHealthPoll = (delayMs) => {
    if (state.healthMonitor.timerId) {
        window.clearTimeout(state.healthMonitor.timerId);
    }

    state.healthMonitor.timerId = window.setTimeout(() => {
        pollSyncHealth();
    }, Math.max(10_000, delayMs));
};

const pollSyncHealth = async ({ force = false } = {}) => {
    if (state.healthMonitor.inFlight) return;
    if (document.hidden && !force) {
        scheduleHealthPoll(state.healthMonitor.nextIntervalMs);
        return;
    }

    state.healthMonitor.inFlight = true;

    try {
        const healthModel = await fetchSyncHealth({
            url: MAP_CONFIG.apiHealthSyncUrl,
            timeoutMs: 7000,
            retries: 1,
        });

        applySyncHealth(healthModel);
        state.healthMonitor.nextIntervalMs = 60_000;
    } catch (error) {
        console.error('Falha ao consultar /api/health/sync:', error);
        applySyncHealth({
            state: 'degraded',
            label: 'Falha na atualização',
            meta: 'Health indisponível no momento. Nova tentativa em breve.',
            signature: `degraded:${Date.now()}`,
        }, { force: true });
        state.healthMonitor.nextIntervalMs = 120_000;
    } finally {
        state.healthMonitor.inFlight = false;
        scheduleHealthPoll(state.healthMonitor.nextIntervalMs);
    }
};

const setLoading = (isLoading, text = 'Atualizando camadas...') => {
    if (ui.loading) {
        ui.loading.dataset.active = isLoading ? 'true' : 'false';
    }
    if (ui.loadingText) {
        ui.loadingText.textContent = text;
    }
};

const setProgress = (value) => {
    const pct = Math.max(0, Math.min(100, Math.round(value)));
    if (ui.loadingProgressFill) ui.loadingProgressFill.style.width = `${pct}%`;
    if (ui.loadingProgressLabel) ui.loadingProgressLabel.textContent = `${pct}%`;
};

const resolveUrgency = (count) => {
    if (count > 160) return { label: '🔴 Crítico', level: 'critico' };
    if (count > 70) return { label: '🟠 Atenção alta', level: 'alto' };
    if (count > 20) return { label: '🟡 Atenção', level: 'medio' };
    return { label: '🟢 Normal', level: 'baixo' };
};

const syncStats = (currentCount, visibleCount, viewportLabel, resolutionLabel, biomeCount = 0) => {
    if (ui.statCurrentCount) ui.statCurrentCount.textContent = String(currentCount);
    if (ui.statBiomeCount) ui.statBiomeCount.textContent = String(biomeCount);
    if (ui.statCadence) ui.statCadence.textContent = '10 min';
    if (ui.statVisibleCount) ui.statVisibleCount.textContent = String(visibleCount);
    if (ui.statViewport) ui.statViewport.textContent = viewportLabel;
    if (ui.statResolution) ui.statResolution.textContent = resolutionLabel;

    const urgency = resolveUrgency(currentCount);
    if (ui.statUrgencyBadge) {
        ui.statUrgencyBadge.dataset.level = urgency.level;
        ui.statUrgencyBadge.textContent = urgency.label;
    }
};

const applyQuickRange = (days) => {
    const end = new Date();
    const start = new Date(end);
    start.setDate(end.getDate() - days);

    state.filters.startDate = start.toISOString().slice(0, 10);
    state.filters.endDate = end.toISOString().slice(0, 10);

    if (ui.startDate) ui.startDate.value = state.filters.startDate;
    if (ui.endDate) ui.endDate.value = state.filters.endDate;

    for (const button of ui.quickRangeButtons) {
        button.dataset.active = Number(button.dataset.rangeDays) === days ? 'true' : 'false';
    }
};

const setBoundaryNotice = (isVisible) => {
    if (!ui.cameraLimitNotice) return;
    ui.cameraLimitNotice.dataset.active = isVisible ? 'true' : 'false';
};

const isCompactLayout = () => window.matchMedia('(max-width: 1024px)').matches;

const setSidebarOpen = (isOpen) => {
    if (!ui.sidebar) return;

    document.body.classList.toggle('sidebar-open', isOpen);
    if (ui.sidebarToggle) {
        ui.sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
};

const bindResponsiveSidebar = () => {
    if (!ui.sidebar || !ui.sidebarToggle) return;

    ui.sidebarToggle.addEventListener('click', () => {
        const willOpen = !document.body.classList.contains('sidebar-open');
        setSidebarOpen(willOpen);
    });

    ui.sidebarClose?.addEventListener('click', () => setSidebarOpen(false));
    ui.sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            setSidebarOpen(false);
        }
    });

    window.addEventListener('resize', debounce(() => {
        if (!isCompactLayout()) {
            setSidebarOpen(false);
        }
    }, 120));
};

const isOutsideBrazilBounds = () => {
    const Cesium = window.Cesium;
    const rectangle = state.viewer.camera.computeViewRectangle(state.viewer.scene.globe.ellipsoid);
    if (!rectangle) return false;

    const bounds = MAP_CONFIG.brazilBounds;
    const west = Cesium.Math.toDegrees(rectangle.west);
    const south = Cesium.Math.toDegrees(rectangle.south);
    const east = Cesium.Math.toDegrees(rectangle.east);
    const north = Cesium.Math.toDegrees(rectangle.north);

    return west < bounds.west || south < bounds.south || east > bounds.east || north > bounds.north;
};

const enforceBrazilCameraBounds = () => {
    if (state.cameraConstraint.isCorrecting || !state.viewer) return;

    const now = Date.now();
    if (now - state.cameraConstraint.lastCorrectionAt < 800) return;

    if (!isOutsideBrazilBounds()) {
        setBoundaryNotice(false);
        return;
    }

    state.cameraConstraint.isCorrecting = true;
    setBoundaryNotice(true);

    const Cesium = window.Cesium;
    const bounds = MAP_CONFIG.brazilBounds;
    const destination = Cesium.Rectangle.fromDegrees(
        bounds.west,
        bounds.south,
        bounds.east,
        bounds.north,
    );

    state.viewer.camera.flyTo({
        destination,
        duration: 0.8,
        easingFunction: Cesium.EasingFunction.CUBIC_OUT,
        complete: () => {
            state.cameraConstraint.isCorrecting = false;
            state.cameraConstraint.lastCorrectionAt = Date.now();
            window.setTimeout(() => setBoundaryNotice(false), 1200);
        },
        cancel: () => {
            state.cameraConstraint.isCorrecting = false;
            state.cameraConstraint.lastCorrectionAt = Date.now();
        },
    });
};

const syncVisibility = () => {
    state.dataSources.current.show = state.filters.showCurrent;
    const layerMode = 'Tempo real';

    if (ui.statLayerMode) {
        ui.statLayerMode.textContent = layerMode;
    }

    applyDynamicLod();

    state.viewer.scene.requestRender();
};

const refresh = async (append = false) => {
    const thisRequest = ++state.requestId;
    if (!append) state.page = 1;

    setLoading(true);
    setProgress(8);

    try {
        const bbox = getViewportBbox(state.viewer);
        const viewportLabel = `${bbox.west.toFixed(1)}, ${bbox.south.toFixed(1)} → ${bbox.east.toFixed(1)}, ${bbox.north.toFixed(1)}`;

        const tasks = [];

        if (state.filters.showCurrent) {
            tasks.push(fetchCurrentPoints(state.filters, bbox, state.page));
        } else {
            tasks.push(Promise.resolve({ data: [], hasMore: false }));
        }

        setProgress(40);
        const [currentPayload] = await Promise.all(tasks);

        if (thisRequest !== state.requestId) return;

        setProgress(72);

        renderPoints({
            points: currentPayload.data,
            dataSource: state.dataSources.current,
            entityCache: state.entityCache,
            layerName: 'current',
            layerLabel: 'Foco tempo real',
            filters: state.filters,
        });

        if (!state.firstRevealDone) {
            revealEntitiesGradually(state.dataSources.current);
            state.firstRevealDone = true;
        }

        state.hasMore = Boolean(currentPayload.hasMore);
        if (ui.loadMoreBtn) {
            ui.loadMoreBtn.disabled = !state.hasMore || !state.filters.showCurrent;
        }

        const currentCount = currentPayload.data.length;
        const biomeCount = new Set(currentPayload.data.map((item) => (item?.biome || '').trim()).filter(Boolean)).size;
        const visibleCount = Array.from(state.entityCache.keys()).filter((key) => key.startsWith('current:')).length;
        syncStats(currentCount, visibleCount, viewportLabel, 'EntityCluster', biomeCount);

        setProgress(100);
        setLoading(false, 'Camadas sincronizadas');
        state.lastRefreshAt = new Date();
        updateRelativeClock();
        if (ui.status) {
            ui.status.textContent = `Atualizado: ${new Date().toLocaleTimeString('pt-BR')} · ${toSafeString(currentCount, '0')} focos atuais`;
        }

        syncVisibility();
    } catch (error) {
        console.error(error);
        setLoading(false, 'Falha ao atualizar camadas');
        if (ui.status) {
            ui.status.textContent = `Erro ao carregar dados: ${toSafeString(error?.message, 'desconhecido')}`;
        }
    } finally {
        state.viewer.scene.requestRender();
    }
};

const bindFilters = () => {
    const scheduleRefresh = debounce(() => refresh(false), 350);

    ui.toggleCurrent?.addEventListener('change', (event) => {
        state.filters.showCurrent = event.target.checked;
        syncVisibility();
        scheduleRefresh();
        if (isCompactLayout()) setSidebarOpen(false);
    });

    ui.startDate?.addEventListener('change', (event) => {
        state.filters.startDate = event.target.value;
        scheduleRefresh();
    });

    ui.endDate?.addEventListener('change', (event) => {
        state.filters.endDate = event.target.value;
        scheduleRefresh();
    });

    ui.biomeSelect?.addEventListener('change', (event) => {
        state.filters.biome = event.target.value;
        scheduleRefresh();
    });

    ui.pointBudgetSelect?.addEventListener('change', (event) => {
        state.selectedPointBudget = Number(event.target.value || MAP_CONFIG.defaultCurrentLimit);
        applyDynamicLod();
        scheduleRefresh();
    });

    ui.priorityLevelSelect?.addEventListener('change', (event) => {
        state.filters.priorityLevel = event.target.value;
        scheduleRefresh();
    });

    for (const button of ui.quickRangeButtons) {
        button.addEventListener('click', () => {
            const days = Number(button.dataset.rangeDays || 30);
            applyQuickRange(days);
            scheduleRefresh();
        });
    }

    ui.refreshBtn?.addEventListener('click', () => {
        refresh(false);
        if (isCompactLayout()) setSidebarOpen(false);
    });
    ui.loadMoreBtn?.addEventListener('click', () => {
        if (!state.hasMore) return;
        state.page += 1;
        refresh(true);
        if (isCompactLayout()) setSidebarOpen(false);
    });

    state.viewer.camera.moveEnd.addEventListener(debounce(() => {
        enforceBrazilCameraBounds();
        applyDynamicLod();
        refresh(false);
    }, 500));

    state.viewer.camera.changed.addEventListener(debounce(() => {
        enforceBrazilCameraBounds();
    }, 120));
};

const bindCesiumGestureControls = () => {
    const presenter = createGestureStatusPresenter({
        handInfoDot: ui.handInfoDotCesium,
        handState: ui.handStateCesium,
        handHint: ui.handHintCesium,
        toggleButton: ui.toggleHandCesiumBtn,
        labels: {
            idleButton: '🖐️ Hand Tracking 3D',
            loadingButton: 'Iniciando 3D…',
            activeButton: '🖐️ Hand Tracking 3D ON',
            errorButton: '🖐️ Hand Tracking 3D',
            idleHint: 'Ative para controlar pan, zoom e rotação da câmera 3D por gestos.',
        },
    });

    presenter.idle();

    state.gesture.runtime = new CesiumGestureRuntime({
        viewer: state.viewer,
        onStatus: (status, message) => {
            if (status === 'loading') {
                presenter.loading();
                return;
            }

            if (status === 'active') {
                presenter.active(message);
                return;
            }

            if (status === 'error') {
                presenter.error(message);
                state.gesture.enabled = false;
                return;
            }

            presenter.idle();
        },
    });

    ui.toggleHandCesiumBtn?.addEventListener('click', async () => {
        if (!state.gesture.runtime) return;

        if (!state.gesture.enabled) {
            const consentGranted = await ensureCesiumGestureConsent();
            if (!consentGranted) return;

            presenter.loading();
            const started = await state.gesture.runtime.start();
            if (!started) return;

            state.gesture.enabled = true;
            return;
        }

        state.gesture.runtime.stop();
        state.gesture.enabled = false;
        presenter.idle();
    });
};

const initFilters = () => {
    const defaults = buildDefaultDates(30);
    state.filters.startDate = defaults.startDate;
    state.filters.endDate = defaults.endDate;
    state.filters.biome = ui.biomeSelect?.value ?? '';
    state.selectedPointBudget = Number(ui.pointBudgetSelect?.value || MAP_CONFIG.defaultCurrentLimit);
    state.filters.pointBudget = state.selectedPointBudget;
    state.filters.priorityLevel = ui.priorityLevelSelect?.value ?? '';

    if (ui.startDate) ui.startDate.value = defaults.startDate;
    if (ui.endDate) ui.endDate.value = defaults.endDate;
    applyQuickRange(30);
};

const bootstrap = async () => {
    initFilters();
    const { viewer, dataSources } = await initViewer();
    state.viewer = viewer;
    state.dataSources = dataSources;

    setupCluster(state.dataSources.current, COLORS.alto);

    bindInteractions({
        viewer: state.viewer,
        tooltipEl: ui.tooltip,
        incidentCardEl: ui.incidentCard,
    });

    bindResponsiveSidebar();
    bindFilters();
    bindCesiumGestureControls();

    updateRelativeClock();
    window.setInterval(updateRelativeClock, 30_000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollSyncHealth({ force: true });
        }
    });
    syncVisibility();
    applyDynamicLod();
    enforceBrazilCameraBounds();
    await refresh(false);
    pollSyncHealth({ force: true });

    window.addEventListener('beforeunload', () => {
        state.gesture.runtime?.destroy?.();
    });
};

bootstrap().catch((error) => {
    console.error(error);
    if (ui.status) {
        ui.status.textContent = 'Falha ao iniciar o mapa.';
    }
});
