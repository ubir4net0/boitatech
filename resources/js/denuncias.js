import gsap from 'gsap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import Chart from 'chart.js/auto';

const page = window.BOITATECH_DENUNCIAS_PAGE ?? {};
const categories = page.categories ?? {};
const statuses = page.statuses ?? {};
const configuredBounds = page.map?.brazil_bounds ?? {};
const BRAZIL_BOUNDS = {
    west: Number(configuredBounds.west ?? -74.5),
    south: Number(configuredBounds.south ?? -34.5),
    east: Number(configuredBounds.east ?? -28.0),
    north: Number(configuredBounds.north ?? 6.2),
};
const BRAZIL_CENTER = [-14.2350, -51.9253];
const MAP_DEFAULT_ZOOM = 4;

const state = {
    items: [],
    analytics: null,
    mainMap: null,
    mainMarkersLayer: null,
    mainMapListenersBound: false,
    charts: {},
    reportMap: null,
    reportMarker: null,
    selectedGeo: null,
    viewMode: 'feed', // 'feed' | 'map'
    meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
    locationCache: {
        states: null,
        citiesByUf: new Map(),
        neighborhoodsByCityId: new Map(),
        geocodeByKey: new Map(),
    },
    filter: {
        categoria: '',
        estado: '',
        periodo: '',
        q: '',
        cidade: '',
        bairro: '',
    },
    request: {
        controller: null,
        id: 0,
        loading: false,
    },
};

const MAP_LOG_PREFIX = '[DenunciasMap]';
const logInfo = (message, data) => console.info(MAP_LOG_PREFIX, message, data ?? '');
const logWarn = (message, data) => console.warn(MAP_LOG_PREFIX, message, data ?? '');
const logError = (message, data) => console.error(MAP_LOG_PREFIX, message, data ?? '');

const categoryColor = (slug) => categories[slug]?.color ?? '#3DFF9A';

const formatDate = (iso) => {
    if (!iso) return '—';
    const date = new Date(iso);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('pt-BR');
};

const setText = (selector, value) => {
    const el = document.querySelector(selector);
    if (el) el.textContent = value;
};


const debounce = (fn, wait = 300) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), wait);
    };
};

const setLoading = (loading) => {
    state.request.loading = loading;
    document.querySelectorAll('[data-filter-input], #resetFilters').forEach((el) => {
        el.disabled = loading;
    });
    document.querySelector('[data-map-shell]')?.classList.toggle('is-loading', loading);
    document.querySelectorAll('[data-chart-card]').forEach((card) => {
        card.classList.toggle('is-loading', loading);
    });
};

const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        ...options,
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        const message = payload?.message
            || (payload?.errors ? Object.values(payload.errors)[0]?.[0] : null)
            || `HTTP ${response.status}`;
        throw new Error(message);
    }

    return payload;
};

const locationApiBase = String(page.apiLocationsBase ?? '/api/denuncias/localidades').replace(/\/$/, '');

const locationApi = {
    states: () => `${locationApiBase}/estados`,
    cities: (uf) => `${locationApiBase}/${encodeURIComponent(uf)}/cidades`,
    neighborhoods: (cityId, uf) => `${locationApiBase}/cidades/${encodeURIComponent(cityId)}/bairros?uf=${encodeURIComponent(uf)}`,
    geocode: ({ estado, cidade, bairro }) => `${locationApiBase}/geocode?estado=${encodeURIComponent(estado)}&cidade=${encodeURIComponent(cidade)}&bairro=${encodeURIComponent(bairro ?? '')}`,
};

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const sanitizeColor = (value, fallback = '#3DFF9A') => {
    const raw = String(value ?? '').trim();
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(raw) ? raw : fallback;
};

const sanitizeUrl = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    try {
        const parsed = new URL(raw, window.location.origin);
        return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : null;
    } catch {
        return null;
    }
};

const ensurePreviewCard = () => {
    let el = document.querySelector('[data-map-preview-card]');
    if (el) return el;

    el = document.createElement('aside');
    el.className = 'preview-card glass';
    el.setAttribute('data-map-preview-card', 'true');
    document.body.appendChild(el);
    return el;
};

const renderPreview = (item) => {
    const card = ensurePreviewCard();
    const safeImage = sanitizeUrl(item?.imagem_url);
    const safeTitle = escapeHtml(item?.titulo ?? 'Denúncia ambiental');
    const safeCategory = escapeHtml(item?.categoria_label ?? 'Categoria');
    const safeCity = escapeHtml(item?.cidade ?? 'Cidade não informada');
    const safeState = escapeHtml(item?.estado ?? 'UF');
    const safeDate = escapeHtml(item?.created_at_human ?? formatDate(item?.created_at));

    card.innerHTML = `
        ${safeImage ? `<img src="${safeImage}" alt="${safeTitle}" loading="lazy" />` : ''}
        <div class="preview-card__title">${safeTitle}</div>
        <div class="preview-card__meta">
            <span>${safeCategory}</span>
            <span>${safeCity} · ${safeState}</span>
            <span>${safeDate}</span>
        </div>
    `;
    card.classList.add('is-visible');
};

const hidePreview = () => {
    const card = document.querySelector('[data-map-preview-card]');
    if (card) card.classList.remove('is-visible');
};

const updateStats = (analytics) => {
    const total = Number(analytics?.total_denuncias ?? analytics?.total ?? state.items.length ?? 0);
    const confirmations = Number(analytics?.total_confirmacoes ?? analytics?.confirmations_total ?? 0);
    const statesCount = Number(
        analytics?.estados_ativos
        ?? analytics?.states_count
        ?? analytics?.states_active
        ?? analytics?.by_state?.length
        ?? analytics?.por_estado?.length
        ?? 0,
    );

    setText('[data-stat="total"]', String(total));
    setText('[data-stat="states"]', String(statesCount));
    setText('[data-stat="community"]', String(confirmations));
};

const chartTheme = {
    color: '#f5f5f5',
    muted: '#a1a1aa',
    grid: 'rgba(161,161,170,.22)',
};

const destroyChart = (key) => {
    if (state.charts[key]) {
        state.charts[key].destroy();
        state.charts[key] = null;
    }
};

const renderCharts = (analytics) => {
    const byCategory = Array.isArray(analytics?.por_categoria)
        ? analytics.por_categoria
        : (Array.isArray(analytics?.by_category) ? analytics.by_category : []);
    const byState = Array.isArray(analytics?.por_estado)
        ? analytics.por_estado
        : (Array.isArray(analytics?.by_state) ? analytics.by_state : []);

    const categoriaCanvas = document.getElementById('chartCategoria');
    const estadoCanvas = document.getElementById('chartEstado');
    if (!categoriaCanvas || !estadoCanvas) return;

    destroyChart('categoria');
    destroyChart('estado');

    state.charts.categoria = new Chart(categoriaCanvas, {
        type: 'doughnut',
        data: {
            labels: byCategory.map((i) => i.label),
            datasets: [{
                data: byCategory.map((i) => Number(i.total ?? 0)),
                backgroundColor: byCategory.map((i) => sanitizeColor(i.color, '#3DFF9A')),
                borderColor: '#050505',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: chartTheme.color } },
            },
        },
    });

    state.charts.estado = new Chart(estadoCanvas, {
        type: 'bar',
        data: {
            labels: byState.map((i) => i.estado),
            datasets: [{
                label: 'Denúncias',
                data: byState.map((i) => Number(i.total ?? 0)),
                backgroundColor: '#3DFF9A',
                borderRadius: 8,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: chartTheme.color } },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { color: chartTheme.muted, precision: 0 },
                    grid: { color: chartTheme.grid },
                },
                y: {
                    ticks: { color: chartTheme.color },
                    grid: { display: false },
                },
            },
        },
    });
};

const buildQuery = () => {
    const query = new URLSearchParams();
    query.set('page', String(state.meta.current_page));
    query.set('per_page', String(state.meta.per_page ?? 12));

    Object.entries(state.filter).forEach(([key, value]) => {
        if (value !== null && value !== undefined && String(value).trim() !== '') {
            query.set(key, String(value).trim());
        }
    });

    return query;
};

const getClusterCellSizeByZoom = (zoom) => {
    if (zoom <= 4) return 3;
    if (zoom <= 5) return 2;
    if (zoom <= 6) return 1;
    if (zoom <= 7) return 0.5;
    if (zoom <= 8) return 0.25;
    return 0;
};

const inBounds = (item, bounds) => {
    const lat = Number(item.latitude);
    const lng = Number(item.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return false;
    return bounds.contains([lat, lng]);
};

const clusterItems = (items, zoom) => {
    const cellSize = getClusterCellSizeByZoom(zoom);
    if (cellSize <= 0) {
        return items.map((item) => ({ latitude: Number(item.latitude), longitude: Number(item.longitude), items: [item] }));
    }

    const grouped = new Map();
    items.forEach((item) => {
        const lat = Number(item.latitude);
        const lng = Number(item.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const latKey = Math.round(lat / cellSize);
        const lngKey = Math.round(lng / cellSize);
        const key = `${latKey}:${lngKey}`;

        const current = grouped.get(key) ?? { latitudeSum: 0, longitudeSum: 0, items: [] };
        current.latitudeSum += lat;
        current.longitudeSum += lng;
        current.items.push(item);
        grouped.set(key, current);
    });

    return Array.from(grouped.values()).map((group) => ({
        latitude: group.latitudeSum / group.items.length,
        longitude: group.longitudeSum / group.items.length,
        items: group.items,
    }));
};

const buildHotspotIcon = ({ color, icon, count = null }) => {
    const safeColor = sanitizeColor(color, '#ef4444');
    const safeIcon = escapeHtml(String(icon ?? '📍').slice(0, 3));
    const safeCount = count ? escapeHtml(String(count)) : null;

    if (safeCount) {
        return L.divIcon({
            className: 'denuncia-marker-cluster',
            html: `<div class="denuncia-hotspot denuncia-hotspot--cluster" style="--hotspot-color:${safeColor};"><span>${safeCount}</span></div>`,
            iconSize: [46, 46],
            iconAnchor: [23, 23],
        });
    }

    return L.divIcon({
        className: 'denuncia-marker-single',
        html: `<div class="denuncia-hotspot" style="--hotspot-color:${safeColor};"><span>${safeIcon}</span></div>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
        popupAnchor: [0, -14],
    });
};

const buildTooltipHtml = (item) => {
    const safeImage = sanitizeUrl(item.imagem_url);
    const safeCategory = escapeHtml(item.categoria_label);
    const safeBairro = escapeHtml(item.bairro || 'Bairro não informado');
    const safeRua = escapeHtml(item.endereco_aproximado || 'Referência não informada');
    const safeCidade = escapeHtml(item.cidade);
    const safeEstado = escapeHtml(item.estado);
    const safeDate = escapeHtml(formatDate(item.created_at));

    return `
        <div class="denuncia-map-tooltip">
            ${safeImage ? `<img src="${safeImage}" alt="${escapeHtml(item.titulo)}" loading="lazy" />` : ''}
            <div class="denuncia-map-tooltip__line"><strong>Categoria:</strong> ${safeCategory}</div>
            <div class="denuncia-map-tooltip__line"><strong>Bairro:</strong> ${safeBairro}</div>
            <div class="denuncia-map-tooltip__line"><strong>Rua:</strong> ${safeRua}</div>
            <div class="denuncia-map-tooltip__line"><strong>Cidade:</strong> ${safeCidade}</div>
            <div class="denuncia-map-tooltip__line"><strong>Estado:</strong> ${safeEstado}</div>
            <div class="denuncia-map-tooltip__line"><strong>Data:</strong> ${safeDate}</div>
        </div>
    `;
};

const buildPopupHtml = (item) => {
    const safeImage = sanitizeUrl(item.imagem_url);
    const safeTitle = escapeHtml(item.titulo);
    const safeCategory = escapeHtml(item.categoria_label);
    const safeBairro = escapeHtml(item.bairro || 'Bairro não informado');
    const safeRua = escapeHtml(item.endereco_aproximado || 'Referência não informada');
    const safeCidade = escapeHtml(item.cidade);
    const safeEstado = escapeHtml(item.estado);
    const safeDate = escapeHtml(formatDate(item.created_at));
    const safeHref = `${page.detailBaseUrl}/${encodeURIComponent(String(item.id))}`;

    return `
        <article class="denuncia-map-popup">
            ${safeImage ? `<img src="${safeImage}" alt="${safeTitle}" loading="lazy" />` : ''}
            <h4>${safeTitle}</h4>
            <p><strong>Categoria:</strong> ${safeCategory}</p>
            <p><strong>Bairro:</strong> ${safeBairro}</p>
            <p><strong>Rua:</strong> ${safeRua}</p>
            <p><strong>Cidade:</strong> ${safeCidade} · ${safeEstado}</p>
            <p><strong>Data:</strong> ${safeDate}</p>
            <a href="${safeHref}" class="denuncia-map-popup__link">Ver detalhes</a>
        </article>
    `;
};

const ensureMainLeaflet = () => {
    const container = document.getElementById('denunciasLeafletMain');
    if (!container) return null;
    if (state.mainMap) return state.mainMap;

    const mapBounds = L.latLngBounds(
        [BRAZIL_BOUNDS.south - 6, BRAZIL_BOUNDS.west - 12],
        [BRAZIL_BOUNDS.north + 4, BRAZIL_BOUNDS.east + 12],
    );

    state.mainMap = L.map(container, {
        zoomControl: true,
        preferCanvas: true,
        minZoom: 3,
        maxZoom: 18,
        maxBounds: mapBounds,
        maxBoundsViscosity: 0.72,
        worldCopyJump: false,
    }).setView(BRAZIL_CENTER, MAP_DEFAULT_ZOOM);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 19,
    }).addTo(state.mainMap);

    state.mainMarkersLayer = L.layerGroup().addTo(state.mainMap);

    if (!state.mainMapListenersBound) {
        const rerender = debounce(() => renderMainMap(state.items), 90);
        state.mainMap.on('moveend zoomend', rerender);
        state.mainMapListenersBound = true;
    }

    logInfo('Leaflet map initialized for denúncias module.');
    return state.mainMap;
};

const renderMainMap = (items) => {
    const map = ensureMainLeaflet();
    if (!map || !state.mainMarkersLayer) return;

    state.mainMarkersLayer.clearLayers();
    if (!Array.isArray(items) || items.length === 0) {
        hidePreview();
        return;
    }

    const zoom = map.getZoom();
    const visibleBounds = map.getBounds().pad(0.4);
    const candidates = zoom >= 7 ? items.filter((item) => inBounds(item, visibleBounds)) : items;
    const groups = clusterItems(candidates, zoom);

    groups.forEach((group) => {
        if (group.items.length <= 1) {
            const item = group.items[0];
            if (!item) return;

            const marker = L.marker([group.latitude, group.longitude], {
                icon: buildHotspotIcon({
                    color: '#ef4444',
                    icon: item.categoria_icon ?? '📍',
                }),
            });

            marker.bindTooltip(buildTooltipHtml(item), {
                direction: 'top',
                offset: [0, -14],
                sticky: true,
                opacity: 0.98,
                className: 'denuncia-leaflet-tooltip-shell',
            });
            marker.bindPopup(buildPopupHtml(item), {
                className: 'denuncia-leaflet-popup-shell',
                maxWidth: 320,
            });
            marker.on('mouseover', () => renderPreview(item));
            marker.on('mouseout', () => hidePreview());

            state.mainMarkersLayer.addLayer(marker);
            return;
        }

        const clusterMarker = L.marker([group.latitude, group.longitude], {
            icon: buildHotspotIcon({ color: '#ff5a36', count: group.items.length }),
        });

        clusterMarker.bindTooltip(`<strong>${group.items.length}</strong> denúncias na região`, {
            direction: 'top',
            offset: [0, -12],
            sticky: true,
            opacity: 0.95,
            className: 'denuncia-leaflet-tooltip-shell',
        });

        clusterMarker.on('click', () => {
            const points = group.items
                .map((item) => [Number(item.latitude), Number(item.longitude)])
                .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));

            if (points.length === 0) return;
            const bounds = L.latLngBounds(points);
            map.fitBounds(bounds.pad(0.45), { animate: true, duration: 0.35, maxZoom: Math.min(zoom + 2, 11) });
        });

        state.mainMarkersLayer.addLayer(clusterMarker);
    });
};

const renderViewer = async (items) => {
    renderMainMap(items);
};

const ensureReportLeaflet = () => {
    const container = document.getElementById('reportLeaflet');
    if (!container) return null;
    if (state.reportMap) return state.reportMap;

    state.reportMap = L.map(container, {
        zoomControl: true,
        preferCanvas: true,
        dragging: true,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false,
    }).setView([-14.2, -53.1], 4);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 19,
    }).addTo(state.reportMap);

    state.reportMarker = L.marker([-14.2, -53.1], {
        icon: L.divIcon({
            className: 'denuncia-marker',
            html: '<div style="width:18px;height:18px;border-radius:999px;background:#3DFF9A;box-shadow:0 0 18px #3DFF9A,0 0 0 5px rgba(5,5,5,0.42);border:2px solid #050505;"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        }),
    }).addTo(state.reportMap);

    return state.reportMap;
};

const updateReportMapPosition = (latitude, longitude, zoom = 12) => {
    const map = ensureReportLeaflet();
    if (!map || !state.reportMarker) return;

    state.reportMarker.setLatLng([latitude, longitude]);
    map.setView([latitude, longitude], zoom, { animate: true, duration: 0.5 });
};

const setSelectLoading = (select, loading, loadingLabel) => {
    if (!select) return;
    select.disabled = loading;
    if (!loading) return;
    const keepValue = select.value;
    select.innerHTML = `<option value="">${loadingLabel}</option>`;
    select.value = keepValue;
};

const populateSelect = (select, options, placeholder, { valueKey = 'value', labelKey = 'label' } = {}) => {
    if (!select) return;
    select.innerHTML = '';
    const first = document.createElement('option');
    first.value = '';
    first.textContent = placeholder;
    select.appendChild(first);

    options.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item[valueKey] ?? '');
        option.textContent = String(item[labelKey] ?? '');
        if (item.id) option.dataset.cityId = String(item.id);
        select.appendChild(option);
    });

    select.disabled = false;
};

const getGeocodeForSelection = async ({ estado, cidade, bairro }) => {
    const key = `${estado}|${cidade}|${bairro}`.toLowerCase();
    if (state.locationCache.geocodeByKey.has(key)) {
        return state.locationCache.geocodeByKey.get(key);
    }

    const payload = await fetchJson(locationApi.geocode({ estado, cidade, bairro }));
    const coords = payload?.data ?? null;
    if (coords) {
        state.locationCache.geocodeByKey.set(key, coords);
    }
    return coords;
};

const loadStates = async (estadoSelect) => {
    if (!estadoSelect) return;
    if (state.locationCache.states) {
        populateSelect(estadoSelect, state.locationCache.states, 'Estado', { valueKey: 'uf', labelKey: 'nome' });
        return;
    }

    setSelectLoading(estadoSelect, true, 'Carregando estados...');
    const payload = await fetchJson(locationApi.states());
    const states = payload?.data ?? [];
    state.locationCache.states = states;
    populateSelect(estadoSelect, states, 'Estado', { valueKey: 'uf', labelKey: 'nome' });
};

const loadCities = async (uf, cidadeSelect, bairroSelect) => {
    if (!cidadeSelect || !bairroSelect) return;
    const bairroList = document.getElementById('reportBairroList');

    bairroSelect.value = '';
    bairroSelect.placeholder = 'Bairro (ou distrito)';
    bairroSelect.disabled = true;
    if (bairroList) bairroList.innerHTML = '';

    if (!uf) {
        cidadeSelect.innerHTML = '<option value="">Cidade</option>';
        cidadeSelect.disabled = true;
        return;
    }

    if (state.locationCache.citiesByUf.has(uf)) {
        populateSelect(cidadeSelect, state.locationCache.citiesByUf.get(uf), 'Cidade', { valueKey: 'nome', labelKey: 'nome' });
        return;
    }

    setSelectLoading(cidadeSelect, true, 'Carregando cidades...');
    const payload = await fetchJson(locationApi.cities(uf));
    const cities = payload?.data ?? [];
    state.locationCache.citiesByUf.set(uf, cities);
    populateSelect(cidadeSelect, cities, 'Cidade', { valueKey: 'nome', labelKey: 'nome' });
};

const loadNeighborhoods = async ({ uf, cityId, bairroSelect }) => {
    if (!bairroSelect) return;
    const bairroList = document.getElementById('reportBairroList');
    if (!uf || !cityId) {
        bairroSelect.value = '';
        bairroSelect.placeholder = 'Bairro (ou distrito)';
        bairroSelect.disabled = true;
        if (bairroList) bairroList.innerHTML = '';
        return;
    }

    const applyBairroSuggestions = (bairros) => {
        if (bairroList) {
            bairroList.innerHTML = '';
            bairros.forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.nome ?? '');
                bairroList.appendChild(option);
            });
        }

        bairroSelect.disabled = false;
        bairroSelect.placeholder = bairros.length > 0
            ? 'Bairro (sugestões carregadas)'
            : 'Bairro';
    };

    const cacheKey = `${uf}:${cityId}`;
    if (state.locationCache.neighborhoodsByCityId.has(cacheKey)) {
        const bairros = state.locationCache.neighborhoodsByCityId.get(cacheKey);
        applyBairroSuggestions(bairros);
        return;
    }

    bairroSelect.value = '';
    bairroSelect.placeholder = 'Carregando bairros/distritos...';
    bairroSelect.disabled = true;

    try {
        const payload = await fetchJson(locationApi.neighborhoods(cityId, uf));
        const bairros = payload?.data ?? [];
        state.locationCache.neighborhoodsByCityId.set(cacheKey, bairros);
        applyBairroSuggestions(bairros);
    } catch (err) {
        console.warn('[Bairros] Falha ao carregar bairros da cidade:', cityId, err?.message ?? err);
        // Libera o campo para digitação manual mesmo sem sugestões
        bairroSelect.disabled = false;
        bairroSelect.placeholder = 'Digite o bairro manualmente';
    }
};

const renderSkeletons = (count = 6) => {
    const grid = document.getElementById('denunciasFeedGrid');
    if (!grid) return;
    grid.innerHTML = Array.from({ length: count }).map(() => `
        <div class="skeleton-card">
            <div class="skeleton" style="height:200px;"></div>
            <div style="padding:16px;display:grid;gap:10px;">
                <div class="skeleton" style="height:12px;width:55%;border-radius:8px;"></div>
                <div class="skeleton" style="height:17px;width:82%;border-radius:8px;"></div>
                <div class="skeleton" style="height:44px;border-radius:8px;"></div>
            </div>
        </div>
    `).join('');
};

const renderFeed = (items, append = false) => {
    const grid = document.getElementById('denunciasFeedGrid');
    if (!grid) return;

    if (!append) {
        if (items.length === 0) {
            grid.innerHTML = `
                <div class="feed-empty">
                    <div class="feed-empty__icon">📋</div>
                    <p>Nenhuma denúncia encontrada para os filtros selecionados.</p>
                </div>`;
            return;
        }
        grid.innerHTML = '';
    }

    items.forEach((item) => {
        const card = document.createElement('article');
        card.className = 'denuncia-card glass';
        const bairroLabel = item.bairro ? `${escapeHtml(item.bairro)} · ` : '';
        const safeImageUrl = sanitizeUrl(item.imagem_url);
        const safeColor = sanitizeColor(item.categoria_color, '#3DFF9A');
        const imgHtml = safeImageUrl
            ? `<img src="${safeImageUrl}" alt="${escapeHtml(item.titulo)}" loading="lazy" />`
            : `<div class="denuncia-card__placeholder">${item.categoria_icon}</div>`;
        card.innerHTML = `
            <a href="${page.detailBaseUrl}/${item.id}" class="denuncia-card__image-wrap" aria-label="${escapeHtml(item.titulo)}">
                ${imgHtml}
                <span class="denuncia-card__cat-badge" style="background:color-mix(in srgb,${safeColor} 16%,rgba(5,5,5,.72));color:${safeColor};border:1px solid color-mix(in srgb,${safeColor} 30%,transparent);">${item.categoria_icon} ${escapeHtml(item.categoria_label)}</span>
            </a>
            <div class="denuncia-card__body">
                <div class="denuncia-card__location">${bairroLabel}${escapeHtml(item.cidade)} &mdash; ${escapeHtml(item.estado)}</div>
                <h3 class="denuncia-card__title">${escapeHtml(item.titulo)}</h3>
                <p class="denuncia-card__desc">${escapeHtml(item.preview || '')}</p>
                <div class="denuncia-card__footer">
                    <time>${escapeHtml(item.created_at_human || '')}</time>
                    <span>${item.confirmations_count} confirmações</span>
                </div>
            </div>
            <div class="denuncia-card__actions">
                <a href="${page.detailBaseUrl}/${item.id}" class="denuncia-card__btn">Ver detalhes →</a>
            </div>`;
        grid.appendChild(card);
    });
};

const updateFeedMeta = () => {
    const countEl = document.getElementById('feedCount');
    if (countEl) {
        countEl.textContent = state.meta.total > 0 ? `${state.meta.total} denúncia${state.meta.total !== 1 ? 's' : ''}` : '';
    }
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (loadMoreBtn) {
        const hasMore = state.meta.current_page < state.meta.last_page;
        loadMoreBtn.style.display = hasMore ? 'inline-flex' : 'none';
        const remaining = state.meta.total - state.items.length;
        if (hasMore) loadMoreBtn.textContent = `Carregar mais (${remaining} restantes)`;
    }
};

const applyResponse = (payload, append = false) => {
    const items = payload?.data ?? [];
    state.meta = { ...state.meta, ...(payload?.meta ?? {}) };
    state.items = append ? [...state.items, ...items] : items;
    state.analytics = payload?.analytics ?? null;
    updateStats(state.analytics);
    renderCharts(state.analytics);
    renderFeed(items, append);
    updateFeedMeta();
    if (state.viewMode === 'map') renderViewer(state.items);
};

const loadDenunciasCore = async (append = false) => {
    if (!append) {
        state.meta.current_page = 1;
        renderSkeletons();
    }

    state.request.id += 1;
    const requestId = state.request.id;
    state.request.controller?.abort?.();
    const controller = new AbortController();
    state.request.controller = controller;

    setLoading(true);
    try {
        const payload = await fetchJson(`${page.apiIndex}?${buildQuery().toString()}`, { signal: controller.signal });
        if (requestId !== state.request.id) return;
        applyResponse(payload, append);
    } catch (error) {
        if (error?.name !== 'AbortError') {
            console.error(error);
            if (!append) {
                const grid = document.getElementById('denunciasFeedGrid');
                if (grid) grid.innerHTML = `<div class="feed-empty"><div class="feed-empty__icon">⚠️</div><p>Erro ao carregar denúncias. Tente novamente.</p></div>`;
            }
        }
    } finally {
        if (requestId === state.request.id) setLoading(false);
    }
};

const loadDenuncias = debounce(() => loadDenunciasCore(false), 200);

const loadMoreDenuncias = () => {
    if (state.meta.current_page >= state.meta.last_page) return;
    state.meta.current_page += 1;
    loadDenunciasCore(true);
};

const bindFilters = () => {
    const elements = {
        q:         document.getElementById('filterQ'),
        categoria: document.getElementById('filterCategoria'),
        estado:    document.getElementById('filterEstado'),
        periodo:   document.getElementById('filterPeriodo'),
        reset:     document.getElementById('resetFilters'),
    };

    Object.entries(elements).forEach(([key, el]) => {
        if (!el || key === 'reset') return;
        const evt = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(evt, () => {
            state.filter[key] = el.value;
            loadDenuncias();
        });
    });

    elements.reset?.addEventListener('click', () => {
        state.filter = { categoria: '', estado: '', periodo: '', q: '', cidade: '', bairro: '' };
        ['filterQ', 'filterCategoria', 'filterEstado', 'filterPeriodo'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        loadDenuncias();
    });

    document.getElementById('loadMoreBtn')?.addEventListener('click', loadMoreDenuncias);
};

const bindImageUpload = () => {
    const input   = document.getElementById('imagensInput');
    const area    = document.getElementById('imageUploadArea');
    const previews= document.getElementById('imagePreviews');
    const countEl = document.getElementById('imageUploadCount');
    const feedback= document.getElementById('formFeedback');
    if (!input) return;

    const refreshPreviews = () => {
        const files = Array.from(input.files ?? []);
        if (previews) {
            previews.innerHTML = '';
            files.forEach((f) => {
                const img = document.createElement('img');
                img.className = 'image-preview-thumb';
                img.src = URL.createObjectURL(f);
                img.alt = f.name;
                previews.appendChild(img);
            });
        }
        if (countEl) {
            countEl.textContent = files.length > 0
                ? `${files.length} imagem${files.length !== 1 ? 's' : ''} selecionada${files.length !== 1 ? 's' : ''}`
                : '';
        }
        area?.classList.toggle('has-files', files.length > 0);
    };

    input.addEventListener('change', () => {
        const files = Array.from(input.files ?? []);
        if (files.length > 5) {
            if (feedback) feedback.textContent = 'Máximo de 5 imagens permitidas.';
            return;
        }
        const oversized = files.find((f) => f.size > 6 * 1024 * 1024);
        if (oversized) {
            if (feedback) feedback.textContent = `${oversized.name} excede o limite de 6 MB.`;
            return;
        }
        if (feedback) feedback.textContent = '';
        refreshPreviews();
    });

    // Drag-and-drop visual hint
    area?.addEventListener('dragover', (e) => { e.preventDefault(); area.classList.add('drag-over'); });
    area?.addEventListener('dragleave', () => area.classList.remove('drag-over'));
    area?.addEventListener('drop', () => area.classList.remove('drag-over'));
};

const bindMapToggle = () => {
    const btn         = document.getElementById('mapToggleBtn');
    const collapsible = document.getElementById('mapCollapsible');
    if (!btn || !collapsible) return;

    const syncMapUiState = () => {
        const isOpen = collapsible.classList.contains('is-open');
        btn.textContent = isOpen ? '🗺️ Fechar mapa' : '🗺️ Ver no mapa';
        state.viewMode = isOpen ? 'map' : 'feed';
    };

    syncMapUiState();
    if (state.viewMode === 'map') {
        window.setTimeout(() => renderViewer(state.items), 80);
    }

    btn.addEventListener('click', () => {
        const isOpen = collapsible.classList.toggle('is-open');
        btn.textContent = isOpen ? '🗺️ Fechar mapa' : '🗺️ Ver no mapa';
        state.viewMode = isOpen ? 'map' : 'feed';
        if (isOpen) {
            // Lazy-init Leaflet and populate with current items
            window.setTimeout(() => renderViewer(state.items), 80);
        }
    });
};

const bindForm = () => {
    const form = document.getElementById('denunciaForm');
    if (!form) return;

    const feedback = document.getElementById('formFeedback');
    const estadoSelect = document.getElementById('reportEstado');
    const cidadeSelect = document.getElementById('reportCidade');
    const bairroInput = document.getElementById('reportBairro');

    ensureReportLeaflet();

    const debouncedPreview = debounce(async () => {
        const estado = estadoSelect?.value ?? '';
        const cidade = cidadeSelect?.value ?? '';
        const bairro = bairroInput?.value?.trim() ?? '';
        if (!estado || !cidade) return;

        try {
            const coords = await getGeocodeForSelection({ estado, cidade, bairro });
            if (!coords) return;
            state.selectedGeo = coords;
            updateReportMapPosition(Number(coords.latitude), Number(coords.longitude), bairro ? 13 : 10);
        } catch {
            // sem spam de erro no UX
        }
    }, 280);

    loadStates(estadoSelect).catch(() => {
        if (feedback) feedback.textContent = 'Falha ao carregar estados. Tente novamente em instantes.';
    });

    estadoSelect?.addEventListener('change', async () => {
        const uf = estadoSelect.value;
        if (feedback) feedback.textContent = uf ? 'Carregando cidades...' : 'Selecione estado, cidade e bairro para posicionamento automático.';
        await loadCities(uf, cidadeSelect, bairroInput);
        state.selectedGeo = null;
        if (!uf) {
            updateReportMapPosition(-14.2, -53.1, 4);
        }
    });

    cidadeSelect?.addEventListener('change', async () => {
        const selected = cidadeSelect.options[cidadeSelect.selectedIndex];
        const cityId = Number(selected?.dataset?.cityId || 0);
        const uf = estadoSelect?.value ?? '';

        if (feedback) feedback.textContent = cityId ? 'Carregando bairros...' : 'Selecione uma cidade.';
        try {
            await loadNeighborhoods({ uf, cityId, bairroSelect: bairroInput });
        } catch (err) {
            console.error('[Bairros] Erro inesperado ao carregar bairros:', err);
            if (bairroInput) {
                bairroInput.disabled = false;
                bairroInput.placeholder = 'Digite o bairro';
            }
            if (feedback) feedback.textContent = 'Não foi possível carregar bairros. Digite o nome manualmente.';
        }
        state.selectedGeo = null;
        debouncedPreview();
    });

    bairroInput?.addEventListener('input', () => {
        state.selectedGeo = null;
        if (feedback) feedback.textContent = bairroInput.value ? 'Localizando bairro digitado...' : 'Digite o bairro para aumentar a precisão.';
        debouncedPreview();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const bairroValue = bairroInput?.value?.trim() ?? '';

        if (!estadoSelect?.value || !cidadeSelect?.value || !bairroValue) {
            if (feedback) feedback.textContent = 'Preencha estado, cidade e bairro antes de enviar.';
            return;
        }

        // Image required — at least 1
        const imagensInput = document.getElementById('imagensInput');
        const imagensFiles = Array.from(imagensInput?.files ?? []);
        if (imagensFiles.length === 0) {
            if (feedback) feedback.textContent = 'Adicione pelo menos 1 imagem da ocorrência.';
            imagensInput?.closest('.image-upload-area')?.classList.add('drag-over');
            return;
        }

        if (!state.selectedGeo) {
            if (feedback) feedback.textContent = 'Localizando região...';
            try {
                state.selectedGeo = await getGeocodeForSelection({
                    estado: estadoSelect.value,
                    cidade: cidadeSelect.value,
                    bairro: bairroValue,
                });
            } catch {
                state.selectedGeo = null;
            }
        }

        if (!state.selectedGeo) {
            if (feedback) feedback.textContent = 'Não foi possível localizar a região selecionada. Tente novamente.';
            return;
        }

        const formData = new FormData(form);

        try {
            if (feedback) feedback.textContent = 'Enviando denúncia...';
            const response = await fetch(page.apiStore, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': page.csrf, Accept: 'application/json' },
                body: formData,
                credentials: 'same-origin',
            });
            let payload = null;
            try {
                payload = await response.json();
            } catch {
                payload = null;
            }
            if (!response.ok) {
                const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                throw new Error(firstError || payload?.message || 'Falha ao registrar denúncia.');
            }
            // Reset form
            form.reset();
            if (cidadeSelect) { cidadeSelect.innerHTML = '<option value="">Cidade</option>'; cidadeSelect.disabled = true; }
            if (bairroInput)  { bairroInput.value = ''; bairroInput.placeholder = 'Bairro (ou distrito)'; bairroInput.disabled = true; }
            document.getElementById('reportBairroList')?.replaceChildren();
            // Clear image previews
            const previewsEl = document.getElementById('imagePreviews');
            const countEl    = document.getElementById('imageUploadCount');
            const areaEl     = document.getElementById('imageUploadArea');
            if (previewsEl) previewsEl.innerHTML = '';
            if (countEl)    countEl.textContent = '';
            areaEl?.classList.remove('has-files', 'drag-over');

            state.selectedGeo = null;
            updateReportMapPosition(-14.2, -53.1, 4);
            if (feedback) feedback.textContent = '✅ Denúncia registrada! Está pública e visível no feed.';
            loadDenuncias();
        } catch (error) {
            if (feedback) feedback.textContent = error.message;
        }
    });
};

const initShowPage = () => {
    const mapContainer = document.getElementById('denunciaLeaflet');
    const confirmButton = document.getElementById('confirmarDenuncia');
    if (!mapContainer) return;

    const denuncia = page.denuncia ?? {};
    // Use privacy-offset coordinates (lat_display/lng_display) — never exact
    const lat = Number(denuncia.lat_display ?? denuncia.latitude ?? -14.2);
    const lng = Number(denuncia.lng_display ?? denuncia.longitude ?? -53.1);
    const map = L.map(mapContainer, { zoomControl: true, preferCanvas: true }).setView([lat, lng], 12);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 19,
    }).addTo(map);

    const safeCategoryColor = sanitizeColor(denuncia.categoria_color, '#3DFF9A');
    const safeTitulo = escapeHtml(denuncia.titulo);
    const safeCidade = escapeHtml(denuncia.cidade);
    const safeEstado = escapeHtml(denuncia.estado);

    const marker = L.marker([lat, lng], {
        icon: L.divIcon({
            className: 'denuncia-marker',
            html: `<div style="width:24px;height:24px;border-radius:999px;background:${safeCategoryColor};box-shadow:0 0 22px ${safeCategoryColor}, 0 0 0 6px rgba(5,5,5,0.42);border:2px solid #050505;"></div>`,
            iconSize: [24, 24],
            iconAnchor: [12, 12],
        }),
    }).addTo(map);

    marker.bindPopup(`<div style="min-width:220px;background:#050505;color:#f5f5f5;padding:6px 0;"><strong>${safeTitulo}</strong><br>${safeCidade} &bull; ${safeEstado}</div>`);

    confirmButton?.addEventListener('click', async () => {
        confirmButton.disabled = true;
        try {
            const response = await fetch(page.apiConfirm, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': page.csrf, Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (!response.ok && response.status !== 409) throw new Error(payload?.message || 'Falha ao confirmar.');

            const count = payload?.confirmations ?? payload?.data?.confirmations_count ?? denuncia.confirmations_count;
            const counter = document.getElementById('confirmationsCount');
            if (counter) {
                counter.textContent = String(count);
                gsap.fromTo(counter, { scale: 1 }, { scale: 1.12, duration: 0.18, yoyo: true, repeat: 1, ease: 'power2.out' });
            }
            confirmButton.textContent = 'Confirmação registrada';
        } catch (error) {
            confirmButton.disabled = false;
            confirmButton.textContent = '👍 Confirmar ocorrência';
            console.error(error);
        }
    });
};

const boot = () => {
    const hasIndex = document.getElementById('denunciasLeafletMain');
    const hasShow = document.getElementById('denunciaLeaflet');
    const drawer = document.querySelector('[data-report-drawer]');
    const backdrop = document.querySelector('[data-report-backdrop]');

    const openDrawer = () => {
        drawer?.classList.add('is-open');
        backdrop?.classList.add('is-open');
        window.setTimeout(() => {
            state.reportMap?.invalidateSize?.();
        }, 60);
    };

    const closeDrawer = () => {
        drawer?.classList.remove('is-open');
        backdrop?.classList.remove('is-open');
    };

    document.querySelectorAll('[data-open-report]').forEach((button) => button.addEventListener('click', openDrawer));
    document.querySelectorAll('[data-close-report]').forEach((button) => button.addEventListener('click', closeDrawer));
    backdrop?.addEventListener('click', closeDrawer);

    if (hasIndex) {
        bindFilters();
        bindImageUpload();
        bindMapToggle();
        bindForm();
        loadDenuncias();
    }

    if (hasShow) {
        initShowPage();
    }
};

document.addEventListener('DOMContentLoaded', boot);