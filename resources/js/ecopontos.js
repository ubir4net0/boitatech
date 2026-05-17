import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const page = window.BOITATECH_ECOPONTOS_PAGE ?? { mode: 'index' };

const state = {
    map: null,
    mapLayer: null,
    listPage: 1,
    listMeta: { current_page: 1, last_page: 1, total: 0, per_page: 12 },
    items: [],
    filter: {
        q: '',
        bairro: '',
        tipo_coleta: '',
        material: '',
    },
};

const debounce = (fn, wait = 280) => {
    let timer;
    return (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), wait);
    };
};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        ...options,
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
};

const toParams = (extra = {}) => {
    const params = new URLSearchParams();
    const merged = { ...state.filter, ...extra };

    Object.entries(merged).forEach(([key, value]) => {
        if (value !== null && value !== undefined && String(value).trim() !== '') {
            params.set(key, String(value).trim());
        }
    });

    return params;
};

const typeMeta = (slug) => page.types?.[slug] ?? { icon: '♻️', color: '#3DFF9A', label: slug };

/**
 * Gera HTML de placeholder premium quando imagem não está disponível.
 * Renderiza badge com ícone, nome e CTA elegante.
 */
const premiumPlaceholderImage = (item) => {
    const bgColor = item.tipo_color || typeMeta(item.tipo_coleta).color || '#3DFF9A';
    const bgGradient = `linear-gradient(135deg, color-mix(in srgb, ${bgColor} 12%, rgba(5,5,5,0.8)), color-mix(in srgb, ${bgColor} 6%, rgba(5,5,5,0.9)))`;
    
    return `
        <div class="eco-placeholder" style="--eco-placeholder-color:${escapeHtml(bgColor)};--eco-placeholder-bg:${escapeHtml(bgGradient)};">
            <div class="eco-placeholder-content">
                <div class="eco-placeholder-icon">${item.tipo_icon || '♻️'}</div>
                <div class="eco-placeholder-text">${escapeHtml(item.tipo_label || 'Coleta')}</div>
                <div class="eco-placeholder-action">Imagem será carregada em breve</div>
            </div>
        </div>
    `;
};

const markerIcon = (item) => {
    const color = item.tipo_color || typeMeta(item.tipo_coleta).color || '#3DFF9A';
    const icon = item.tipo_icon || typeMeta(item.tipo_coleta).icon || '♻️';

    return L.divIcon({
        className: '',
        html: `<div class="eco-marker" style="background:${color};box-shadow:0 0 22px ${color}, 0 0 0 6px color-mix(in srgb, ${color} 24%, transparent);">${escapeHtml(icon)}</div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -16],
    });
};

const clusterIcon = (count) => L.divIcon({
    className: '',
    html: `<div class="eco-cluster">${count}</div>`,
    iconSize: [38, 38],
    iconAnchor: [19, 19],
});

const popupHtml = (item) => {
    const mats = Array.isArray(item.materiais_aceitos)
        ? item.materiais_aceitos.map((value) => escapeHtml(String(value))).join(', ')
        : '';

    const routeUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(item.latitude)},${encodeURIComponent(item.longitude)}`;

    return `
        <article class="eco-popup">
            <div class="eco-popup__img">
                ${item.imagem_url ? `<img src="${escapeHtml(item.imagem_url)}" alt="${escapeHtml(item.nome)}" loading="lazy" width="340" height="128" decoding="async">` : premiumPlaceholderImage(item)}
            </div>
            <div class="eco-popup__title">${escapeHtml(item.nome)}</div>
            <div class="eco-popup__text">${escapeHtml(item.endereco)}<br>${escapeHtml(item.bairro)} · ${escapeHtml(item.cidade || 'Manaus')}</div>
            <div class="eco-popup__text" style="margin-top:6px;"><strong>Tipo:</strong> ${escapeHtml(item.tipo_label || '')}</div>
            <div class="eco-popup__text"><strong>Materiais:</strong> ${mats || 'Consultar unidade'}</div>
            <div class="eco-popup__text"><strong>Horário:</strong> ${escapeHtml(item.horario_funcionamento || 'Não informado')}</div>
            <div class="eco-popup__actions">
                <a class="eco-popup__btn" href="${escapeHtml((page.detailBaseUrl || '/ecopontos') + '/' + item.id)}">Ver detalhes</a>
                <a class="eco-popup__btn" href="${escapeHtml(routeUrl)}" target="_blank" rel="noopener">Abrir rota</a>
            </div>
        </article>
    `;
};

const renderMapData = (payload) => {
    if (!state.map || !state.mapLayer) return;

    state.mapLayer.clearLayers();

    (payload.data ?? []).forEach((entry) => {
        if (entry.type === 'cluster') {
            const marker = L.marker([entry.latitude, entry.longitude], { icon: clusterIcon(entry.count) });
            marker.on('click', () => {
                state.map?.setView([entry.latitude, entry.longitude], Math.min((state.map?.getZoom() || 12) + 2, 18), { animate: true });
            });
            marker.addTo(state.mapLayer);
            return;
        }

        const item = entry.type === 'point' ? entry.data : entry;
        const marker = L.marker([item.latitude, item.longitude], { icon: markerIcon(item), keyboard: false });
        marker.bindPopup(popupHtml(item), { maxWidth: 340, className: 'eco-popup-wrapper' });
        marker.addTo(state.mapLayer);
    });
};

const updateSummary = (payload) => {
    const meta = document.getElementById('ecoResultsMeta');
    const summaryMeta = document.getElementById('ecoSummaryMeta');
    const loadMore = document.getElementById('loadMoreEcopontos');

    if (meta) {
        meta.textContent = `${payload.summary?.active_total ?? state.listMeta.total ?? 0} pontos ativos em ${payload.summary?.city ?? 'Manaus'}`;
    }

    if (summaryMeta) {
        summaryMeta.textContent = `${state.listMeta.total ?? 0} locais encontrados · ${payload.summary?.types_total ?? 0} tipos de coleta`;
    }

    if (loadMore) {
        loadMore.style.display = state.listMeta.current_page < state.listMeta.last_page ? 'inline-flex' : 'none';
    }
};

const renderList = (items, append = false) => {
    const wrap = document.getElementById('ecopontosList');
    if (!wrap) return;

    if (!append) {
        wrap.innerHTML = '';
    }

    if (!append && items.length === 0) {
        wrap.innerHTML = '<div class="eco-card" style="padding:22px;color:#B8B8B8;">Nenhum local de coleta encontrado para os filtros atuais.</div>';
        return;
    }

    const html = items.map((item) => {
        const badgeStyle = `background:color-mix(in srgb, ${item.tipo_color} 16%, rgba(5,5,5,0.55));border:1px solid color-mix(in srgb, ${item.tipo_color} 38%, transparent);color:${item.tipo_color};`;
        const materials = (item.materiais_aceitos || []).slice(0, 4)
            .map((mat) => `<span class="eco-card__chip">${escapeHtml(String(mat))}</span>`)
            .join('');

        return `
            <article class="eco-card" data-eco-id="${item.id}">
                <div class="eco-card__img">
                    ${item.imagem_url ? `<img src="${escapeHtml(item.imagem_url)}" alt="${escapeHtml(item.nome)}" loading="lazy" width="420" height="190" decoding="async" />` : premiumPlaceholderImage(item)}
                    <div class="eco-card__badge" style="${badgeStyle}">${escapeHtml(item.tipo_icon)} ${escapeHtml(item.tipo_label)}</div>
                </div>
                <div class="eco-card__body">
                    <div class="eco-card__location">${escapeHtml(item.bairro)} · ${escapeHtml(item.cidade || 'Manaus')}</div>
                    <div class="eco-card__title">${escapeHtml(item.nome)}</div>
                    <div class="eco-card__meta">${escapeHtml(item.endereco)}<br>${escapeHtml(item.horario_funcionamento || 'Horário não informado')}</div>
                    <div class="eco-card__materials">${materials}</div>
                    <div class="eco-card__actions">
                        <a href="${escapeHtml((page.detailBaseUrl || '/ecopontos') + '/' + item.id)}" class="eco-popup__btn">Ver detalhes</a>
                        <button type="button" class="eco-popup__btn" data-fly-to="${item.id}">Abrir no mapa</button>
                    </div>
                </div>
            </article>
        `;
    }).join('');

    wrap.insertAdjacentHTML('beforeend', html);

    wrap.querySelectorAll('[data-fly-to]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = Number(button.getAttribute('data-fly-to'));
            const item = state.items.find((entry) => entry.id === id);
            if (!item || !state.map) return;
            state.map.setView([item.latitude, item.longitude], 16, { animate: true });
        });
    });
};

const loadList = async (append = false) => {
    const params = toParams({ page: state.listPage, per_page: 12 });
    const payload = await fetchJson(`${page.apiIndex}?${params.toString()}`);

    state.listMeta = payload.meta ?? state.listMeta;

    if (!append) {
        state.items = payload.data ?? [];
        renderList(state.items, false);
    } else {
        state.items = [...state.items, ...(payload.data ?? [])];
        renderList(payload.data ?? [], true);
    }

    updateSummary(payload);
};

const loadMapData = async () => {
    if (!state.map) return;

    const bounds = state.map.getBounds();
    const params = toParams({
        south: bounds.getSouth(),
        west: bounds.getWest(),
        north: bounds.getNorth(),
        east: bounds.getEast(),
        zoom: state.map.getZoom(),
        limit: 1200,
    });

    try {
        const payload = await fetchJson(`${page.apiMap}?${params.toString()}`);
        renderMapData(payload);
    } catch (_) {
        // silêncio intencional para manter a UX
    }
};

const debouncedLoadMapData = debounce(loadMapData, 220);

const applyFilters = async () => {
    state.listPage = 1;
    await Promise.all([loadList(false), loadMapData()]);
};

const bindWhereDiscard = () => {
    const wrap = document.getElementById('whereDiscardChips');
    if (!wrap) return;

    const chips = [
        { key: 'pilhas', label: '🪫 Pilhas' },
        { key: 'eletronicos', label: '🔋 Eletrônicos' },
        { key: 'plastico', label: '🧴 Plástico' },
        { key: 'vidro', label: '🍾 Vidro' },
        { key: 'papel', label: '📄 Papel' },
        { key: 'metal', label: '🔩 Metal' },
    ];

    wrap.innerHTML = chips.map((chip) => `<button type="button" class="eco-chip" data-material-chip="${chip.key}">${chip.label}</button>`).join('');

    wrap.querySelectorAll('[data-material-chip]').forEach((chip) => {
        chip.addEventListener('click', async () => {
            const key = chip.getAttribute('data-material-chip') || '';
            state.filter.material = state.filter.material === key ? '' : key;
            wrap.querySelectorAll('[data-material-chip]').forEach((node) => {
                node.classList.toggle('is-active', node.getAttribute('data-material-chip') === state.filter.material);
            });
            await applyFilters();
        });
    });
};

const bindFilters = () => {
    const q = document.getElementById('filterQ');
    const bairro = document.getElementById('filterBairro');
    const tipoColeta = document.getElementById('filterTipoColeta');
    const reset = document.getElementById('resetEcoFilters');
    const loadMore = document.getElementById('loadMoreEcopontos');

    const applyDebounced = debounce(async () => {
        await applyFilters();
    }, 260);

    q?.addEventListener('input', () => {
        state.filter.q = q.value;
        applyDebounced();
    });

    bairro?.addEventListener('input', () => {
        state.filter.bairro = bairro.value;
        applyDebounced();
    });

    tipoColeta?.addEventListener('change', async () => {
        state.filter.tipo_coleta = tipoColeta.value;
        await applyFilters();
    });

    reset?.addEventListener('click', async () => {
        state.filter = { q: '', bairro: '', tipo_coleta: '', material: '' };
        if (q) q.value = '';
        if (bairro) bairro.value = '';
        if (tipoColeta) tipoColeta.value = '';
        document.querySelectorAll('[data-material-chip]').forEach((chip) => chip.classList.remove('is-active'));
        await applyFilters();
    });

    loadMore?.addEventListener('click', async () => {
        if (state.listMeta.current_page >= state.listMeta.last_page) return;
        state.listPage += 1;
        await loadList(true);
    });
};

const initIndexPage = async () => {
    const mapElement = document.getElementById('ecopontosMap');
    if (!mapElement) return;

    const center = page.city?.center ?? [-3.1190, -60.0217];
    const zoom = Number(page.city?.default_zoom ?? 12);

    state.map = L.map(mapElement, {
        zoomControl: true,
        preferCanvas: true,
        minZoom: 10,
        maxZoom: 19,
    }).setView(center, zoom);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 20,
        subdomains: 'abcd',
        attribution: '&copy; OpenStreetMap &copy; CARTO',
    }).addTo(state.map);

    state.mapLayer = L.layerGroup().addTo(state.map);

    if (page.city?.bounds) {
        const b = page.city.bounds;
        const bounds = L.latLngBounds([[b.south, b.west], [b.north, b.east]]);
        state.map.setMaxBounds(bounds.pad(0.18));
    }

    state.map.on('moveend zoomend', debouncedLoadMapData);

    bindWhereDiscard();
    bindFilters();

    await Promise.all([loadList(false), loadMapData()]);
};

const initShowPage = () => {
    const target = document.getElementById('ecopontoLeaflet');
    const ponto = page.ecoponto;
    if (!target || !ponto) return;

    const map = L.map(target, { zoomControl: true, preferCanvas: true })
        .setView([Number(ponto.latitude), Number(ponto.longitude)], 15);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 20,
        subdomains: 'abcd',
        attribution: '&copy; OpenStreetMap &copy; CARTO',
    }).addTo(map);

    L.marker([Number(ponto.latitude), Number(ponto.longitude)], {
        icon: L.divIcon({
            className: '',
            html: `<div class="eco-marker" style="background:${ponto.tipo_color};box-shadow:0 0 22px ${ponto.tipo_color};">${escapeHtml(ponto.tipo_icon || '♻️')}</div>`,
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        }),
    })
        .addTo(map)
        .bindPopup(`<strong>${escapeHtml(ponto.nome)}</strong><br>${escapeHtml(ponto.endereco)}<br>${escapeHtml(ponto.bairro)} · ${escapeHtml(ponto.cidade || 'Manaus')}`)
        .openPopup();
};

document.addEventListener('DOMContentLoaded', async () => {
    if (page.mode === 'show') {
        initShowPage();
        return;
    }

    await initIndexPage();
});
