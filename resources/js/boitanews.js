import { fetchJson, readBoitaNewsApi } from './news-api';
import { withFallbackImage } from './image-utils';
import { rankingScore } from './news-ranking';
import { createPaginationController } from './news-pagination';
import { renderEmptyState, renderFeed, renderSkeleton } from './news-render';

const api = readBoitaNewsApi();

const state = {
    page: 0,
    perPage: 12,
    lastPage: 1,
    total: 0,
    loading: false,
    reachedEnd: false,
    items: [],
};

const elements = {
    feed: document.querySelector('#news-feed'),
    status: document.querySelector('#news-status'),
    meta: document.querySelector('#news-meta'),
    loadMore: document.querySelector('#load-more'),
};

let controller = null;
let abortController = null;

function setStatus(text) {
    if (elements.status) {
        elements.status.textContent = text;
    }
}

function setMeta() {
    if (!elements.meta) return;

    if (state.total > 0) {
        elements.meta.textContent = `${state.total} notícias • Página ${state.page} de ${state.lastPage}`;
    } else {
        elements.meta.textContent = 'Curadoria editorial ambiental em andamento';
    }
}

function updateButtonState() {
    if (!controller) return;
    controller.setButtonState(state.loading, !state.reachedEnd);
}

function renderItems() {
    if (!elements.feed) return;

    const normalized = state.items.map((item) => {
        const enriched = withFallbackImage(item);
        return {
            ...enriched,
            ranking_score: Number(enriched.ranking_score || rankingScore(enriched)),
        };
    }).filter((item) => item.has_image && item.image_url);

    if (normalized.length === 0) {
        elements.feed.innerHTML = renderEmptyState('Nenhuma notícia curada com mídia válida no momento.');
        return;
    }

    elements.feed.innerHTML = renderFeed(normalized);
}

async function loadPage(page = 1) {
    if (state.loading || state.reachedEnd) return;

    state.loading = true;
    updateButtonState();

    if (elements.feed && state.page === 0) {
        elements.feed.innerHTML = renderSkeleton(8);
    }

    abortController?.abort();
    abortController = new AbortController();

    try {
        const payload = await fetchJson(api.index, {
            page,
            per_page: state.perPage,
        }, abortController.signal);

        const items = Array.isArray(payload?.data) ? payload.data : [];
        const meta = payload?.meta || {};

        state.page = Number(meta.current_page || page);
        state.lastPage = Number(meta.last_page || page);
        state.total = Number(meta.total || 0);

        state.items = page === 1 ? items : [...state.items, ...items];
        state.reachedEnd = state.page >= state.lastPage || items.length === 0;

        renderItems();
        setMeta();
        setStatus(state.reachedEnd ? 'Fim do acervo editorial.' : 'Atualização contínua do acervo ambiental brasileiro.');
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }

        if (elements.feed && state.items.length === 0) {
            elements.feed.innerHTML = renderEmptyState('Não foi possível carregar as notícias agora.');
        }
        setStatus('Falha ao carregar o feed.');
    } finally {
        state.loading = false;
        updateButtonState();
    }
}

async function loadMore() {
    if (state.loading || state.reachedEnd) return;
    await loadPage(state.page + 1);
}

function bootstrap() {
    controller = createPaginationController({
        onLoadMore: loadMore,
        loadMoreButton: elements.loadMore,
    });

    setStatus('Carregando feed editorial.');
    setMeta();
    updateButtonState();
    loadPage(1);
}

bootstrap();
