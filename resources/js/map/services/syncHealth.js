const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

const isObject = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);

const toDate = (value) => {
    if (typeof value !== 'string' || value.trim() === '') return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

const normalizeLayerState = (layer) => {
    const status = String(layer?.status ?? '').toLowerCase();

    if (status === 'failure') {
        return {
            state: 'degraded',
            label: 'Falha na atualização',
        };
    }

    if (status === 'warning' || Boolean(layer?.is_stale)) {
        return {
            state: 'delayed',
            label: 'Sincronização atrasada',
        };
    }

    return {
        state: 'healthy',
        label: 'Dados atualizados',
    };
};

const validateHealthPayload = (payload) => {
    if (!isObject(payload)) {
        throw new Error('Payload de health inválido: esperado objeto JSON.');
    }

    if (typeof payload.status !== 'string') {
        throw new Error('Payload de health inválido: campo status ausente.');
    }

    if (!isObject(payload.layers)) {
        throw new Error('Payload de health inválido: campo layers ausente.');
    }

    const currentLayer = payload.layers.focos_current;
    if (!isObject(currentLayer)) {
        throw new Error('Payload de health inválido: layer focos_current ausente.');
    }

    return currentLayer;
};

const normalizeHealthModel = (payload) => {
    const currentLayer = validateHealthPayload(payload);
    const normalized = normalizeLayerState(currentLayer);

    const lastSuccess = toDate(currentLayer.last_success_at);
    const staleAfterMinutes = Number(currentLayer.stale_after_minutes);

    const staleWindow = Number.isFinite(staleAfterMinutes) && staleAfterMinutes > 0
        ? staleAfterMinutes
        : 30;

    const meta = lastSuccess
        ? `Última sincronização: ${lastSuccess.toLocaleString('pt-BR')} · janela ${staleWindow} min`
        : 'Última sincronização: indisponível';

    const signature = [
        normalized.state,
        normalized.label,
        currentLayer.status ?? '',
        currentLayer.last_success_at ?? '',
        currentLayer.records_written ?? '',
        currentLayer.is_stale ? '1' : '0',
    ].join('|');

    return {
        state: normalized.state,
        label: normalized.label,
        meta,
        signature,
        raw: payload,
    };
};

const fetchWithTimeout = async (url, timeoutMs) => {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort('timeout'), timeoutMs);

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Health endpoint respondeu HTTP ${response.status}`);
        }

        return await response.json();
    } finally {
        window.clearTimeout(timeoutId);
    }
};

export const fetchSyncHealth = async ({
    url,
    timeoutMs = 7000,
    retries = 1,
}) => {
    let attempt = 0;
    let lastError = null;

    while (attempt <= retries) {
        try {
            const payload = await fetchWithTimeout(url, timeoutMs);
            return normalizeHealthModel(payload);
        } catch (error) {
            lastError = error;
            if (attempt >= retries) break;
            await wait(350 * (attempt + 1));
            attempt += 1;
        }
    }

    throw lastError ?? new Error('Falha ao consultar health sync.');
};
