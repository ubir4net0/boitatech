import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { createDashboardCharts } from './dashboard/charts.js';

const config = window.BOITATECH_DASHBOARD ?? { initial: {}, dataEndpoint: '/dashboard/data' };

const state = {
    payload: config.initial ?? {},
    map: null,
    mapLayers: { focos: null, denuncias: null },
    charts: null,
    mapReady: false,
};

const numberFmt = new Intl.NumberFormat('pt-BR');
const dateFmt = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

const byId = (id) => document.getElementById(id);

const debounce = (fn, wait = 180) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), wait);
    };
};

const setText = (id, value) => {
    const el = byId(id);
    if (!el) return;
    el.textContent = String(value ?? '—');
    el.classList.remove('skeleton');
};

const setLoading = (loading) => {
    const cards = byId('kpiCards');
    if (!cards) return;
    cards.dataset.loading = loading ? 'true' : 'false';
};

// Renderiza apenas os 3 KPI cards principais (Focos, Denúncias, Ecopontos)
const renderCards = (cards = {}) => {
    setText('cardFocosTotal', numberFmt.format(cards.focos?.total ?? 0));
    setText('cardFocosDelta', cards.focos?.subtitle ?? 'Sem variação recente');

    setText('cardDenunciasTotal', numberFmt.format(cards.denuncias?.total ?? 0));
    setText('cardDenunciasMeta', cards.denuncias?.subtitle ?? 'Sem dados');

    setText('cardEcopontosTotal', numberFmt.format(cards.ecopontos?.total ?? 0));
    setText('cardEcopontosMeta', cards.ecopontos?.subtitle ?? 'Sem dados');
};

const renderFeed = (feed = []) => {
    const host = byId('opsFeed');
    if (!host) return;

    host.textContent = '';

    if (!Array.isArray(feed) || feed.length === 0) {
        const item = document.createElement('div');
        item.className = 'rounded-lg border border-slate-800/60 bg-slate-900/40 p-2.5 text-xs text-slate-400';
        item.textContent = 'Sem eventos recentes.';
        host.appendChild(item);
        return;
    }

    feed.forEach((entry, idx) => {
        const row = document.createElement('article');
        row.className = 'rounded-lg border border-slate-800/60 bg-slate-900/40 p-2.5 transition-all hover:border-emerald-500/20 hover:bg-slate-900/60';
        row.style.animation = `fadeInUp 0.3s ease forwards`;
        row.style.animationDelay = `${idx * 40}ms`;
        row.style.opacity = '0';

        const title = document.createElement('p');
        title.className = 'text-xs font-medium text-slate-200';
        title.textContent = `${entry.icon ?? '•'} ${entry.title ?? 'Atualização'}`;

        const meta = document.createElement('p');
        meta.className = 'mt-1 text-[11px] text-slate-400';
        meta.textContent = entry.meta ?? 'Operação em andamento';

        const at = document.createElement('div');
        at.className = 'mt-1.5 text-[10px] text-slate-500';
        at.textContent = entry.at ? dateFmt.format(new Date(entry.at)) : 'agora';

        row.appendChild(title);
        row.appendChild(meta);
        row.appendChild(at);
        host.appendChild(row);
    });
};

const ensureCharts = () => {
    if (state.charts) return state.charts;

    state.charts = createDashboardCharts({
        categoryCanvas: byId('chartCategory'),
        stateCanvas: byId('chartState'),
        biomeCanvas: byId('chartBiome'),
        numberFormatter: numberFmt,
    });

    return state.charts;
};

const renderCharts = (charts = {}) => {
    const manager = ensureCharts();

    manager.render({
        category: charts.denuncias_por_categoria ?? [],
        state: charts.denuncias_por_estado ?? [],
        biome: charts.alertas_por_bioma ?? [],
    });
};

const renderMap = () => {
    if (state.mapReady) return;

    const host = byId('opsMap');
    if (!host) return;

    state.map = L.map(host, {
        zoomControl: true,
        preferCanvas: true,
        minZoom: 3,
        maxZoom: 12,
        scrollWheelZoom: true,
        dragging: true,
    }).setView([-11.2, -54.5], 4);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap',
        opacity: 0.9,
    }).addTo(state.map);

    state.mapLayers.focos = L.layerGroup().addTo(state.map);
    state.mapLayers.denuncias = L.layerGroup().addTo(state.map);

    state.mapReady = true;
    renderMapLayers(state.payload.map ?? {});
};

const renderMapLayers = (mapPayload = {}) => {
    if (!state.mapReady || !state.map) return;

    state.mapLayers.focos?.clearLayers();
    state.mapLayers.denuncias?.clearLayers();

    const focos = Array.isArray(mapPayload.focos) ? mapPayload.focos : [];
    const denuncias = Array.isArray(mapPayload.denuncias) ? mapPayload.denuncias : [];

    focos.forEach((point) => {
        if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) return;
        L.circleMarker([point.lat, point.lng], {
            radius: 4,
            color: '#fb923c',
            weight: 0,
            fillColor: '#f97316',
            fillOpacity: 0.8,
        }).addTo(state.mapLayers.focos);
    });

    denuncias.forEach((point) => {
        if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) return;
        L.circleMarker([point.lat, point.lng], {
            radius: 3.5,
            color: '#ef4444',
            weight: 0.5,
            fillColor: '#ef4444',
            fillOpacity: 0.75,
        }).addTo(state.mapLayers.denuncias);
    });
};

const renderGeneratedAt = (iso) => {
    const el = byId('generatedAt');
    if (!el || !iso) return;
    el.textContent = dateFmt.format(new Date(iso));
};

const renderAll = (payload) => {
    state.payload = payload;
    renderGeneratedAt(payload.generated_at);
    renderCards(payload.cards ?? {});
    renderFeed(payload.feed ?? []);
    renderCharts(payload.charts ?? {});
    renderMapLayers(payload.map ?? {});
};

const fetchData = async () => {
    setLoading(true);

    const response = await fetch(config.dataEndpoint, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('DASHBOARD_FETCH_FAILED');
    }

    const payload = await response.json();
    renderAll(payload);
    setLoading(false);
};

const bootMapWhenVisible = () => {
    const mapEl = byId('opsMap');
    if (!mapEl) return;

    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                renderMap();
                io.disconnect();
            }
        });
    }, { rootMargin: '120px' });

    io.observe(mapEl);
};

const bindEvents = () => {
    const refreshBtn = byId('refreshDataBtn');
    refreshBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        setLoading(true);
        fetchData().catch(() => setLoading(false));
    });

    const onResize = debounce(() => {
        state.map?.invalidateSize(false);
        state.charts?.resize();
    }, 180);

    window.addEventListener('resize', onResize, { passive: true });

    window.addEventListener('beforeunload', () => {
        state.charts?.destroy();
    });
};

const bootstrap = () => {
    renderAll(state.payload);
    bootMapWhenVisible();
    bindEvents();

    // Carrega dados após interação do usuário (preferível a executar logo)
    if (typeof requestIdleCallback === 'function') {
        requestIdleCallback(
            () => fetchData().catch(() => setLoading(false)),
            { timeout: 2000 }
        );
    } else {
        setTimeout(() => fetchData().catch(() => setLoading(false)), 500);
    }
};

bootstrap();

