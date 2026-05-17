const defaultHeaders = {
    Accept: 'application/json',
};

export async function fetchJson(url, params = {}, signal = null) {
    const target = new URL(url, window.location.origin);

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            target.searchParams.set(key, value);
        }
    });

    const response = await fetch(target.toString(), {
        headers: defaultHeaders,
        signal,
    });

    if (!response.ok) {
        throw new Error('Falha ao carregar notícias.');
    }

    return response.json();
}

export function readBoitaNewsApi() {
    return window.BOITANEWS_API || {};
}
