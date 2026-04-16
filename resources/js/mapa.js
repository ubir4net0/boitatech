const AMAZONIA_CAMERA = {
    longitude: -60,
    latitude: -3,
    height: 2_400_000,
};

const TIPO_ALERTA_VALIDOS = new Set([
    'desmatamento',
    'queimada',
    'garimpo_ilegal',
    'invasao',
]);

const state = {
    viewer: null,
    dataSource: null,
    isReady: false,
    currentPage: 1,
    hasMore: true,
    activeRequestId: 0,
    lastBboxKey: '',
    moveDebounce: null,
    loadingCount: 0,
};

const ui = {
    status: document.getElementById('mapaStatus'),
    loading: document.getElementById('loading'),
    loadMoreBtn: document.getElementById('carregarMaisBtn'),
    tooltip: document.getElementById('alertaTooltip'),
};

const config = window.BOITATECH_MAP_CONFIG ?? {};

const updateStatus = (text) => {
    ui.status.textContent = text;
};

const updateLoadMoreButtonState = () => {
    ui.loadMoreBtn.disabled = !state.isReady || state.loadingCount > 0 || !state.hasMore;
};

const setLoading = (isLoading) => {
    state.loadingCount = Math.max(0, state.loadingCount + (isLoading ? 1 : -1));
    ui.loading.dataset.active = state.loadingCount > 0 ? 'true' : 'false';
    updateLoadMoreButtonState();
};

const toSafeNumber = (value, min, max) => {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return null;
    }

    return Math.min(max, Math.max(min, number));
};

const toSafeString = (value, fallback = '-') => {
    if (typeof value !== 'string') {
        return fallback;
    }

    return value.trim().slice(0, 80) || fallback;
};

const sanitizeAlerta = (item) => {
    const latitude = toSafeNumber(item?.latitude, -90, 90);
    const longitude = toSafeNumber(item?.longitude, -180, 180);

    if (latitude === null || longitude === null) {
        return null;
    }

    const tipo = toSafeString(item?.tipo_alerta, 'desconhecido').toLowerCase();
    const tipoAlerta = TIPO_ALERTA_VALIDOS.has(tipo) ? tipo : 'desconhecido';

    return {
        latitude,
        longitude,
        tipo_alerta: tipoAlerta,
        data: toSafeString(item?.data, '-'),
    };
};

const getViewportBbox = () => {
    const rectangle = state.viewer?.camera?.computeViewRectangle();

    if (!rectangle || !window.Cesium) {
        return null;
    }

    const west = window.Cesium.Math.toDegrees(rectangle.west);
    const south = window.Cesium.Math.toDegrees(rectangle.south);
    const east = window.Cesium.Math.toDegrees(rectangle.east);
    const north = window.Cesium.Math.toDegrees(rectangle.north);

    if (![west, south, east, north].every(Number.isFinite)) {
        return null;
    }

    return {
        west: Number(west.toFixed(2)),
        south: Number(south.toFixed(2)),
        east: Number(east.toFixed(2)),
        north: Number(north.toFixed(2)),
    };
};

const getBboxKey = (bbox) => {
    if (!bbox) {
        return 'global';
    }

    return `${bbox.west}|${bbox.south}|${bbox.east}|${bbox.north}`;
};

const fetchAlertas = async (page = 1) => {
    const bbox = getViewportBbox();
    const params = new URLSearchParams({
        page: String(page),
        limit: String(config.defaultLimit ?? 80),
    });

    if (bbox) {
        params.set('bbox', `${bbox.west},${bbox.south},${bbox.east},${bbox.north}`);
    }

    const response = await fetch(`${config.apiAlertasUrl}?${params.toString()}`, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`Falha ao consultar alertas (${response.status})`);
    }

    const payload = await response.json();
    const alertas = Array.isArray(payload?.data)
        ? payload.data.map(sanitizeAlerta).filter(Boolean)
        : [];

    return {
        alertas,
        hasMore: Boolean(payload?.meta?.has_more),
    };
};

const createSquarePolygon = (longitude, latitude, delta) => {
    return window.Cesium.Cartesian3.fromDegreesArray([
        longitude - delta,
        latitude - delta,
        longitude + delta,
        latitude - delta,
        longitude + delta,
        latitude + delta,
        longitude - delta,
        latitude + delta,
    ]);
};

const renderAlertas = (alertas, append = false) => {
    if (!append) {
        state.dataSource.entities.removeAll();
    }

    for (const alerta of alertas) {
        const delta = 0.08;

        state.dataSource.entities.add({
            polygon: {
                hierarchy: createSquarePolygon(alerta.longitude, alerta.latitude, delta),
                material: window.Cesium.Color.RED.withAlpha(0.33),
                outline: true,
                outlineColor: window.Cesium.Color.RED.withAlpha(0.95),
                height: 80,
                extrudedHeight: 380,
                perPositionHeight: false,
            },
            properties: {
                tipo_alerta: alerta.tipo_alerta,
                data: alerta.data,
                latitude: alerta.latitude.toFixed(5),
                longitude: alerta.longitude.toFixed(5),
            },
        });
    }

    state.viewer.scene.requestRender();
};

const hideTooltip = () => {
    ui.tooltip.style.display = 'none';
};

const bindTooltip = () => {
    const handler = new window.Cesium.ScreenSpaceEventHandler(state.viewer.scene.canvas);

    handler.setInputAction((event) => {
        const picked = state.viewer.scene.pick(event.position);

        if (!picked?.id?.properties) {
            hideTooltip();
            return;
        }

        const tipo = toSafeString(picked.id.properties.tipo_alerta?.getValue());
        const data = toSafeString(picked.id.properties.data?.getValue());
        const latitude = toSafeString(picked.id.properties.latitude?.getValue());
        const longitude = toSafeString(picked.id.properties.longitude?.getValue());

        ui.tooltip.innerHTML = '';

        const title = document.createElement('strong');
        title.textContent = `Alerta: ${tipo}`;

        const line1 = document.createElement('div');
        line1.textContent = `Data: ${data}`;

        const line2 = document.createElement('div');
        line2.textContent = `Lat/Lon: ${latitude}, ${longitude}`;

        ui.tooltip.append(title, line1, line2);
        ui.tooltip.style.left = `${event.position.x + 12}px`;
        ui.tooltip.style.top = `${event.position.y + 12}px`;
        ui.tooltip.style.display = 'block';
    }, window.Cesium.ScreenSpaceEventType.LEFT_CLICK);

    handler.setInputAction(() => {
        hideTooltip();
    }, window.Cesium.ScreenSpaceEventType.LEFT_DOWN);
};

const loadAlertas = async ({ append = false } = {}) => {
    if (!state.isReady || !state.viewer || !state.dataSource) {
        updateStatus('Mapa ainda não inicializado. Configure o token CESIUM_ION_TOKEN no .env.');
        return;
    }

    const requestId = ++state.activeRequestId;
    const targetPage = append ? state.currentPage + 1 : 1;

    setLoading(true);
    updateStatus(append ? 'Carregando próxima página...' : 'Atualizando alertas da área visível...');

    try {
        const { alertas, hasMore } = await fetchAlertas(targetPage);

        // Ignora respostas antigas para evitar re-render de requests concorrentes.
        if (requestId !== state.activeRequestId) {
            return;
        }

        renderAlertas(alertas, append);
        state.currentPage = targetPage;
        state.hasMore = hasMore;
        updateLoadMoreButtonState();

        updateStatus(
            `Alertas carregados: ${state.dataSource.entities.values.length} • Página ${state.currentPage}`,
        );
    } catch (error) {
        console.error(error);
        updateStatus('Não foi possível carregar alertas agora.');
    } finally {
        setLoading(false);
    }
};

const attachViewportLazyLoading = () => {
    state.viewer.camera.moveEnd.addEventListener(() => {
        if (state.moveDebounce) {
            clearTimeout(state.moveDebounce);
        }

        state.moveDebounce = setTimeout(() => {
            const key = getBboxKey(getViewportBbox());

            if (key === state.lastBboxKey) {
                return;
            }

            state.lastBboxKey = key;
            loadAlertas({ append: false });
        }, 500);
    });
};

const setupViewer = async () => {
    if (!window.Cesium) {
        throw new Error('CesiumJS não foi carregado.');
    }

    const token = String(config.cesiumIonToken ?? '').trim();

    if (!token) {
        throw new Error('Defina CESIUM_ION_TOKEN no arquivo .env.');
    }

    window.Cesium.Ion.defaultAccessToken = token;

    state.viewer = new window.Cesium.Viewer('cesiumContainer', {
        animation: false,
        timeline: false,
        homeButton: false,
        geocoder: false,
        sceneModePicker: false,
        navigationHelpButton: false,
        infoBox: false,
        baseLayerPicker: false,
        fullscreenButton: true,
        requestRenderMode: true,
        terrain: window.Cesium.Terrain.fromWorldTerrain(),
    });

    const imageryProvider = await window.Cesium.IonImageryProvider.fromAssetId(2);
    state.viewer.imageryLayers.removeAll();
    state.viewer.imageryLayers.addImageryProvider(imageryProvider);

    state.viewer.scene.globe.enableLighting = true;
    state.viewer.scene.globe.depthTestAgainstTerrain = true;
    state.viewer.scene.fxaa = true;
    state.viewer.scene.postProcessStages.fxaa.enabled = true;
    state.viewer.resolutionScale = Math.min(window.devicePixelRatio || 1, 2);

    state.dataSource = new window.Cesium.CustomDataSource('alertas-amazonia');
    state.viewer.dataSources.add(state.dataSource);
    state.isReady = true;
    updateLoadMoreButtonState();

    bindTooltip();

    await state.viewer.camera.flyTo({
        destination: window.Cesium.Cartesian3.fromDegrees(
            AMAZONIA_CAMERA.longitude,
            AMAZONIA_CAMERA.latitude,
            AMAZONIA_CAMERA.height,
        ),
        duration: 2.8,
    });

    state.lastBboxKey = getBboxKey(getViewportBbox());
};

const bootstrap = async () => {
    ui.loadMoreBtn.addEventListener('click', () => {
        if (state.isReady && state.hasMore) {
            loadAlertas({ append: true });
        }
    });

    try {
        await setupViewer();
        attachViewportLazyLoading();
        await loadAlertas({ append: false });
    } catch (error) {
        console.error(error);
        state.isReady = false;
        state.hasMore = false;
        updateLoadMoreButtonState();
        updateStatus('Erro ao inicializar o mapa. Verifique o token Cesium no .env.');
        setLoading(false);
    }
};

bootstrap();
