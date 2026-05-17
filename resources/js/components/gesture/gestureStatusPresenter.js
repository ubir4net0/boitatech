export const createGestureStatusPresenter = ({ handInfoDot, handState, handHint, toggleButton, labels = {} }) => {
    const text = {
        idleTitle: labels.idleTitle || 'Hand tracking desativado',
        idleHint: labels.idleHint || 'Ative para navegar por PAN, ZOOM, ROTATE e RESET.',
        idleButton: labels.idleButton || 'Hand Tracking',
        loadingTitle: labels.loadingTitle || 'Inicializando tracking…',
        loadingHint: labels.loadingHint || 'Solicitando câmera e preparando MediaPipe local.',
        loadingButton: labels.loadingButton || 'Iniciando…',
        activeTitle: labels.activeTitle || 'Hand tracking ativo',
        activeHint: labels.activeHint || 'Processamento local — nenhuma imagem é enviada para servidores.',
        activeButton: labels.activeButton || 'Hand Tracking ON',
        errorTitle: labels.errorTitle || 'Falha no hand tracking',
        errorHint: labels.errorHint || 'Não foi possível iniciar a webcam neste momento.',
        errorButton: labels.errorButton || 'Hand Tracking',
    };

    const setUi = ({ active, title, hint, buttonText }) => {
        handInfoDot?.classList.toggle('active', active);

        if (handState) {
            handState.textContent = title;
        }

        if (handHint) {
            handHint.textContent = hint;
        }

        if (toggleButton) {
            toggleButton.textContent = buttonText;
            toggleButton.setAttribute('aria-pressed', String(active));
        }
    };

    return {
        idle() {
            setUi({
                active: false,
                title: text.idleTitle,
                hint: text.idleHint,
                buttonText: text.idleButton,
            });
        },
        loading() {
            setUi({
                active: false,
                title: text.loadingTitle,
                hint: text.loadingHint,
                buttonText: text.loadingButton,
            });
        },
        active(customHint) {
            setUi({
                active: true,
                title: text.activeTitle,
                hint: customHint || text.activeHint,
                buttonText: text.activeButton,
            });
        },
        error(message) {
            setUi({
                active: false,
                title: text.errorTitle,
                hint: message || text.errorHint,
                buttonText: text.errorButton,
            });
        },
    };
};
