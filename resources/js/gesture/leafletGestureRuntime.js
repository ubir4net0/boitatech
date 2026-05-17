let gestureModulePromise = null;

const loadGestureModule = async () => {
    if (!gestureModulePromise) {
        gestureModulePromise = import('@map-gesture-controls/leaflet');
    }

    return gestureModulePromise;
};

const getResponsiveWebcamConfig = () => {
    const mobile = window.innerWidth <= 680;
    const tablet = window.innerWidth <= 1024;

    if (mobile) {
        return {
            enabled: true,
            mode: 'corner',
            position: 'top-right',
            width: 220,
            height: 150,
            opacity: 0.9,
        };
    }

    if (tablet) {
        return {
            enabled: true,
            mode: 'corner',
            position: 'bottom-right',
            width: 260,
            height: 170,
            opacity: 0.92,
        };
    }

    return {
        enabled: true,
        mode: 'corner',
        position: 'bottom-right',
        width: 300,
        height: 190,
        opacity: 0.94,
    };
};

const buildStartErrorMessage = (error) => {
    const name = String(error?.name || '');
    const message = String(error?.message || '').toLowerCase();

    if (name === 'NotAllowedError' || name === 'SecurityError') {
        return 'Permissão de câmera negada. Libere o acesso no navegador e tente novamente.';
    }

    if (name === 'NotReadableError' || message.includes('could not start video source')) {
        return 'A câmera não pôde ser iniciada. Verifique permissão de câmera no navegador/Windows, feche abas antigas com webcam e tente novamente.';
    }

    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        return 'Nenhuma câmera foi encontrada neste dispositivo.';
    }

    if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
        return 'A configuração de vídeo não foi suportada pela câmera. Tente novamente.';
    }

    return 'Falha ao iniciar câmera/gestos. Verifique permissões e tente novamente.';
};

const buildConstraintAttempts = async (constraints, originalGetUserMedia) => {
    const attempts = [
        constraints,
        { audio: false, video: { facingMode: 'user' } },
        { audio: false, video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' } },
        { audio: false, video: true },
    ];

    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoInputs = devices.filter((device) => device.kind === 'videoinput');

        for (const device of videoInputs) {
            attempts.push({
                audio: false,
                video: {
                    deviceId: { exact: device.deviceId },
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                },
            });
        }
    } catch {
        // enumerateDevices pode falhar antes da permissão; seguimos com fallbacks genéricos.
    }

    const unique = [];
    const seen = new Set();
    for (const candidate of attempts) {
        const key = JSON.stringify(candidate);
        if (seen.has(key)) continue;
        seen.add(key);
        unique.push(candidate);
    }

    let lastError = null;
    for (const candidate of unique) {
        try {
            return await originalGetUserMedia(candidate);
        } catch (error) {
            lastError = error;
            await new Promise((resolve) => window.setTimeout(resolve, 120));
        }
    }

    throw lastError ?? new Error('GET_USER_MEDIA_FAILED');
};

const withRelaxedGetUserMedia = async (work) => {
    const mediaDevices = navigator.mediaDevices;
    const originalGetUserMedia = mediaDevices?.getUserMedia?.bind(mediaDevices);

    if (!mediaDevices || !originalGetUserMedia) {
        return work();
    }

    mediaDevices.getUserMedia = async (constraints) => {
        return buildConstraintAttempts(constraints, originalGetUserMedia);
    };

    try {
        return await work();
    } finally {
        mediaDevices.getUserMedia = originalGetUserMedia;
    }
};

export class LeafletGestureRuntime {
    constructor({ map, onStatus }) {
        this.map = map;
        this.onStatus = onStatus;
        this.controller = null;
        this.running = false;
    }

    async start() {
        if (this.running) return true;

        this.onStatus?.('loading', 'Carregando modelo de gestos e preparando webcam...');

        try {
            const { GestureMapController } = await loadGestureModule();

            this.controller = new GestureMapController({
                map: this.map,
                webcam: getResponsiveWebcamConfig(),
                tuning: {
                    actionDwellMs: 40,
                    releaseGraceMs: 80,
                    panDeadzonePx: 0,
                    zoomDeadzoneRatio: 0,
                    rotateDeadzoneRad: 0,
                    smoothingAlpha: 0.35,
                    minDetectionConfidence: 0.6,
                    minTrackingConfidence: 0.6,
                    minPresenceConfidence: 0.6,
                },
                debug: false,
            });

            await withRelaxedGetUserMedia(() => this.controller.start());
            this.running = true;
            this.onStatus?.('active', 'Tracking ativo. Processamento local — nenhuma imagem é enviada para servidores.');
            return true;
        } catch (error) {
            console.error('[GestureRuntime] start failed', error);
            this.stop();
            this.onStatus?.('error', buildStartErrorMessage(error));
            return false;
        }
    }

    stop() {
        if (this.controller) {
            this.controller.stop();
            this.controller = null;
        }

        this.running = false;
        this.onStatus?.('idle', 'Hand tracking desativado. Processamento local quando ativo.');
    }

    destroy() {
        this.stop();
    }
}
