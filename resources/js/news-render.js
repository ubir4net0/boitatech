import { normalizeImageUrl } from './image-utils';

const esc = (value) => {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
};

const relativeDate = (iso) => {
    if (!iso) return 'há pouco';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return 'há pouco';

    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    if (seconds < 90) return 'há 1 minuto';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `há ${minutes} minutos`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `há ${hours} horas`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `há ${days} dias`;

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const sourceThemeClass = (sourceKey) => {
    const key = String(sourceKey ?? '').toLowerCase();

    if (key.includes('g1')) return 'news-pill--g1';
    if (key.includes('cnn')) return 'news-pill--cnn';
    if (key.includes('terra')) return 'news-pill--terra';
    if (key.includes('mongabay')) return 'news-pill--mongabay';
    if (key.includes('inpe') || key.includes('ibama') || key.includes('icmbio') || key.includes('govbr')) return 'news-pill--gov';
    if (key.includes('uol') || key.includes('folha') || key.includes('estadao')) return 'news-pill--media';

    return 'news-pill--default';
};

export function renderSkeleton(count = 6) {
    return Array.from({ length: count }, () => `
        <article class="skeleton-card">
            <div class="skeleton" style="height: 210px; border-radius: 0;"></div>
            <div style="padding: 14px; display: grid; gap: 9px;">
                <div class="skeleton" style="height: 22px; width: 70%;"></div>
                <div class="skeleton" style="height: 16px; width: 100%;"></div>
                <div class="skeleton" style="height: 16px; width: 88%;"></div>
                <div class="skeleton" style="height: 16px; width: 78%;"></div>
            </div>
        </article>
    `).join('');
}

export function renderNewsCard(item) {
    const image = normalizeImageUrl(item.image_url);
    if (!image) {
        return '';
    }
    const sourceName = item.source?.name || 'Fonte oficial';
    const sourceKey = item.source?.key || '';
    const sourceTheme = sourceThemeClass(sourceKey);
    const ranking = Number(item.ranking_score || 0);
    const excerpt = item.excerpt || 'Resumo não disponível.';
    const category = item.category_label || 'Ambiental';

    return `
        <article class="news-card" data-ranking="${ranking}">
            <a href="${esc(item.url)}" target="_blank" rel="noopener noreferrer" class="news-card__media">
                <img
                    src="${esc(image)}"
                    alt="${esc(item.title)}"
                    loading="lazy"
                    decoding="async"
                />
            </a>

            <div class="news-card__body">
                <div class="news-card__badges">
                    <span class="news-pill news-pill--source ${sourceTheme}">${esc(sourceName)}</span>
                    <span class="news-pill">${esc(category)}</span>
                </div>

                <h2 class="news-card__title">
                    <a href="${esc(item.url)}" target="_blank" rel="noopener noreferrer">
                        ${esc(item.title)}
                    </a>
                </h2>

                <p class="news-card__desc">
                    ${esc(excerpt)}
                </p>

                <div class="news-card__footer">
                    <time>${esc(relativeDate(item.published_at))}</time>
                    <a href="${esc(item.url)}" target="_blank" rel="noopener noreferrer" class="news-card__link">
                        Ler na fonte oficial
                    </a>
                </div>
            </div>
        </article>
    `;
}

export function renderFeed(items = []) {
    return items.map(renderNewsCard).join('');
}

export function renderEmptyState(message = 'Nenhuma notícia encontrada.') {
    return `
        <div class="feed-empty">
            ${esc(message)}
        </div>
    `;
}
