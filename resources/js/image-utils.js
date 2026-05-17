export function isValidImageUrl(url) {
    if (typeof url !== 'string' || url.trim() === '') {
        return false;
    }

    try {
        const parsed = new URL(url, window.location.origin);
        return ['http:', 'https:'].includes(parsed.protocol);
    } catch {
        return false;
    }
}

export function normalizeImageUrl(url) {
    return isValidImageUrl(url) ? url : null;
}

export function withFallbackImage(item = {}) {
    const hasImage = Boolean(item.has_image) && isValidImageUrl(item.image_url);
    return {
        ...item,
        has_image: hasImage,
        image_url: hasImage ? item.image_url : null,
        image_is_fallback: false,
    };
}
