const AMAZON_KEYWORDS = [
    'amazônia',
    'amazonia',
    'desmatamento',
    'queimadas',
    'queimada',
    'incêndio florestal',
    'garimpo ilegal',
    'ibama',
    'icmbio',
    'terra indígena',
    'terras indígenas',
    'povos indígenas',
    'floresta amazônica',
    'bioma',
    'cerrado',
    'pantanal',
    'mata atlântica',
    'caatinga',
];

const PRIORITY_SOURCES = new Set([
    'ibama',
    'inpe-noticias',
    'icmbio',
    'mongabay-brasil',
    'infoamazonia',
    'observatorio-do-clima',
    'isa-socioambiental',
    'g1-meio-ambiente',
    'agencia-brasil-meio-ambiente',
    'cnn-brasil-clima',
    'uol-meio-ambiente',
]);

export function recencyScore(publishedAt) {
    if (!publishedAt) return 0;

    const date = new Date(publishedAt);
    if (Number.isNaN(date.getTime())) return 0;

    const hoursOld = Math.abs((Date.now() - date.getTime()) / 36e5);

    if (hoursOld <= 6) return 100;
    if (hoursOld <= 12) return 92;
    if (hoursOld <= 24) return 84;
    if (hoursOld <= 48) return 70;
    if (hoursOld <= 96) return 58;
    if (hoursOld <= 168) return 45;
    if (hoursOld <= 360) return 28;
    return 16;
}

export function amazonRelevanceScore(item = {}) {
    const haystack = `${item.category || ''} ${item.title || ''} ${item.excerpt || ''}`.toLowerCase();
    let score = 0;

    for (const keyword of AMAZON_KEYWORDS) {
        if (!haystack.includes(keyword)) continue;

        if (['amazônia', 'amazonia', 'desmatamento', 'queimadas', 'queimada'].includes(keyword)) {
            score += 15;
        } else if (['incêndio florestal'].includes(keyword)) {
            score += 12;
        } else if (['garimpo ilegal', 'ibama', 'icmbio', 'terra indígena', 'terras indígenas'].includes(keyword)) {
            score += 10;
        } else if (keyword === 'povos indígenas') {
            score += 9;
        } else if (keyword === 'floresta amazônica') {
            score += 12;
        } else {
            score += 6;
        }
    }

    return Math.min(100, score);
}

export function sourceTrustScore(item = {}) {
    const sourceKey = String(item.source?.key || '').toLowerCase();
    const trustMap = {
        ibama: 100,
        'inpe-noticias': 100,
        icmbio: 100,
        'mongabay-brasil': 95,
        infoamazonia: 95,
        'observatorio-do-clima': 92,
        'isa-socioambiental': 90,
        'g1-meio-ambiente': 70,
        'agencia-brasil-meio-ambiente': 65,
        'cnn-brasil-clima': 60,
        'uol-meio-ambiente': 60,
    };

    return trustMap[sourceKey] ?? 30;
}

export function rankingScore(item = {}) {
    const recency = recencyScore(item.published_at);
    const imageBoost = item.has_image ? 25 : 0;
    const amazonBoost = amazonRelevanceScore(item);
    const trust = sourceTrustScore(item);
    const nlp = Math.max(0, Math.min(1, Number(item.nlp_probability ?? 0)));
    const curation = Number(item.curation_score ?? item.quality_score ?? 0);
    const strictBoost = item.publication_layer === 'strict' ? 10 : 0;
    const featuredBoost = item.is_featured ? 8 : 0;

    return Math.round(
        (recency * 1.4)
        + imageBoost
        + (trust * 0.4)
        + (amazonBoost * 1.3)
        + (nlp * 22)
        + (curation * 0.6)
        + strictBoost
        + featuredBoost
    );
}

export function sortByRanking(items = []) {
    return [...items].sort((left, right) => {
        const diff = (Number(right.ranking_score ?? rankingScore(right)) - Number(left.ranking_score ?? rankingScore(left)));
        if (diff !== 0) return diff;

        const leftDate = new Date(left.published_at || 0).getTime();
        const rightDate = new Date(right.published_at || 0).getTime();
        return rightDate - leftDate;
    });
}
