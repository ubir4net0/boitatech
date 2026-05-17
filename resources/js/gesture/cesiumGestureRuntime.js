import { CesiumGestureAdapter } from './adapters/cesiumGestureAdapter.js';

let coreModulePromise = null;

const loadCoreModule = async () => {
    if (!coreModulePromise) {
        coreModulePromise = import('@map-gesture-controls/core');
    }
    return coreModulePromise;
};

const buildStartErrorMessage = (error) => {
    const name = String(error?.name || '');
    const message = String(error?.message || '').toLowerCase();

    if (name === 'NotAllowedError' || name === 'SecurityError') {
        return 'Permissão de câmera negada. Libere o acesso no navegador e tente novamente.';
    }

    if (name === 'NotReadableError' || message.includes('could not start video source')) {
        return 'A câmera não pôde ser iniciada. Verifique privacidade do Windows/navegador e feche abas antigas com webcam.';
    }

    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        return 'Nenhuma câmera foi encontrada neste dispositivo.';
    }

    if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
        return 'A configuração de vídeo não foi suportada pela câmera. Tente novamente.';
    }

    return 'Falha ao iniciar câmera/gestos. Verifique permissões e tente novamente.';
};

const withRelaxedGetUserMedia = async (work) => {
    const mediaDevices = navigator.mediaDevices;
    const originalGetUserMedia = mediaDevices?.getUserMedia?.bind(mediaDevices);

    if (!mediaDevices || !originalGetUserMedia) {
        return work();
    }

    mediaDevices.getUserMedia = async (constraints) => {
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
            // continue com tentativas genéricas
        }

        let lastError = null;
        for (const candidate of attempts) {
            try {
                return await originalGetUserMedia(candidate);
            } catch (error) {
                lastError = error;
                await new Promise((resolve) => window.setTimeout(resolve, 120));
            }
        }

        throw lastError ?? new Error('GET_USER_MEDIA_FAILED');
    };

    try {
        return await work();
    } finally {
        mediaDevices.getUserMedia = originalGetUserMedia;
    }
};

export class CesiumGestureRuntime {
    constructor({ viewer, onStatus }) {
        this.viewer = viewer;
        this.onStatus = onStatus;
        this.adapter = null;
        this.gestureController = null;
        this.stateMachine = null;
        this.overlay = null;
        this.landmarks = null;
        this.lastFrame = null;
        this.rafHandle = null;
        this.started = false;
        this.paused = false;
        this.resetPoseStart = null;
        this.resetPoseTriggered = false;
        this.resetPoseGraceTimer = null;
        this.resetPoseDurationMs = 1000;
        this.resetPoseGraceMs = 300;

        this.handleVisibilityChange = () => {
            if (document.hidden) {
                this.pause();
            } else {
                this.resume();
            }
        };
    }

    async start() {
        if (this.started) return true;

        this.onStatus?.('loading', 'Inicializando MediaPipe e webcam para controle gestual...');

        try {
            const {
                GestureController,
                GestureStateMachine,
                WebcamOverlay,
                DEFAULT_TUNING_CONFIG,
                LANDMARKS,
            } = await loadCoreModule();

            this.landmarks = LANDMARKS;
            this.adapter = new CesiumGestureAdapter({ viewer: this.viewer });
            this.stateMachine = new GestureStateMachine({
                ...DEFAULT_TUNING_CONFIG,
                actionDwellMs: 40,
                releaseGraceMs: 90,
                panDeadzonePx: 0,
                smoothingAlpha: 0.35,
                minDetectionConfidence: 0.6,
                minTrackingConfidence: 0.6,
                minPresenceConfidence: 0.6,
            });

            this.gestureController = new GestureController(
                {
                    ...DEFAULT_TUNING_CONFIG,
                    actionDwellMs: 40,
                    releaseGraceMs: 90,
                    panDeadzonePx: 0,
                    smoothingAlpha: 0.35,
                    minDetectionConfidence: 0.6,
                    minTrackingConfidence: 0.6,
                    minPresenceConfidence: 0.6,
                },
                (frame) => {
                    this.lastFrame = frame;
                },
            );

            this.overlay = new WebcamOverlay({
                enabled: true,
                mode: 'corner',
                opacity: 0.93,
                position: window.innerWidth <= 860 ? 'top-right' : 'bottom-right',
                width: window.innerWidth <= 860 ? 260 : 340,
                height: window.innerWidth <= 860 ? 176 : 220,
            });

            const video = await withRelaxedGetUserMedia(() => this.gestureController.init());
            this.overlay.attachVideo(video);
            this.overlay.mount(this.viewer.container);

            this.gestureController.start();
            this.started = true;
            this.paused = false;
            this.renderLoop();
            document.addEventListener('visibilitychange', this.handleVisibilityChange);

            this.onStatus?.('active', 'Hand tracking ativo no Cesium. Processamento local — nenhuma imagem é enviada para servidores.');
            return true;
        } catch (error) {
            console.error('[CesiumGestureRuntime] start failed', error);
            this.stop();
            this.onStatus?.('error', buildStartErrorMessage(error));
            return false;
        }
    }

    stop() {
        this.started = false;
        this.paused = false;

        if (this.rafHandle !== null) {
            cancelAnimationFrame(this.rafHandle);
            this.rafHandle = null;
        }

        this.gestureController?.destroy?.();
        this.gestureController = null;
        this.stateMachine?.reset?.();
        this.stateMachine = null;
        this.overlay?.unmount?.();
        this.overlay = null;
        this.adapter = null;
        this.lastFrame = null;
        this.resetPoseStart = null;
        this.resetPoseTriggered = false;
        this.resetPoseGraceTimer = null;

        document.removeEventListener('visibilitychange', this.handleVisibilityChange);

        this.onStatus?.('idle', 'Hand tracking desativado.');
    }

    destroy() {
        this.stop();
    }

    pause() {
        if (!this.started || this.paused) return;
        this.paused = true;
        this.gestureController?.stop?.();
        this.stateMachine?.reset?.();
    }

    resume() {
        if (!this.started || !this.paused) return;
        this.paused = false;
        this.gestureController?.start?.();
    }

    isPrayPose(leftLandmarks, rightLandmarks) {
        const wristIndex = this.landmarks?.WRIST ?? 0;
        const lw = leftLandmarks?.[wristIndex];
        const rw = rightLandmarks?.[wristIndex];
        if (!lw || !rw) return false;

        const dx = lw.x - rw.x;
        const dy = lw.y - rw.y;
        return Math.sqrt((dx * dx) + (dy * dy)) < 0.45;
    }

    updateResetPose(frame) {
        const left = frame?.leftHand;
        const right = frame?.rightHand;
        const now = frame?.timestamp ?? performance.now();
        let progress = 0;

        const isActiveGesture = (hand) => hand && (hand.gesture === 'fist' || hand.gesture === 'pinch');

        if (
            left
            && right
            && !isActiveGesture(left)
            && !isActiveGesture(right)
            && this.isPrayPose(left.landmarks, right.landmarks)
        ) {
            this.resetPoseGraceTimer = null;
            if (this.resetPoseStart === null) {
                this.resetPoseStart = now;
                this.resetPoseTriggered = false;
            }

            const elapsed = now - this.resetPoseStart;
            progress = Math.min(1, elapsed / this.resetPoseDurationMs);

            if (!this.resetPoseTriggered && progress >= 1) {
                this.resetPoseTriggered = true;
                this.adapter?.resetPose?.();
            }
        } else if (this.resetPoseStart !== null) {
            if (this.resetPoseGraceTimer === null) {
                this.resetPoseGraceTimer = now;
            } else if (now - this.resetPoseGraceTimer >= this.resetPoseGraceMs) {
                this.resetPoseStart = null;
                this.resetPoseTriggered = false;
                this.resetPoseGraceTimer = null;
            }

            if (this.resetPoseStart !== null) {
                const elapsed = now - this.resetPoseStart;
                progress = Math.min(1, elapsed / this.resetPoseDurationMs);
            }
        } else {
            this.resetPoseGraceTimer = null;
        }

        return progress;
    }

    renderLoop = () => {
        if (!this.started) return;

        this.rafHandle = requestAnimationFrame(this.renderLoop);

        if (this.paused) {
            this.overlay?.render?.(null, 'idle', 0);
            return;
        }

        const frame = this.lastFrame;
        if (!frame || !this.stateMachine) {
            this.overlay?.render?.(null, 'idle', 0);
            return;
        }

        const output = this.stateMachine.update(frame);
        this.adapter?.apply?.(output);

        const resetProgress = this.updateResetPose(frame);
        this.overlay?.render?.(frame, output.mode, resetProgress);
    };
}
