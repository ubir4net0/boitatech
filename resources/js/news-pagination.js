export function createPaginationController({
    onLoadMore,
    onReachEnd,
    loadMoreButton,
}) {
    const setButtonState = (loading, hasMore) => {
        if (!loadMoreButton) return;
        loadMoreButton.hidden = !hasMore;
        loadMoreButton.disabled = loading;
        loadMoreButton.textContent = loading ? 'Carregando...' : 'Mostrar mais notícias';
    };

    const stop = () => {
        // Paginação manual: sem observação automática de sentinel.
    };

    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', () => onLoadMore?.());
    }

    return {
        setButtonState,
        stop,
        onReachEnd,
    };
}
