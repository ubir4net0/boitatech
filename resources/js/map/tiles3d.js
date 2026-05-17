import { MAP_CONFIG } from './config.js';

// Estrutura pronta para Google/Cesium photorealistic 3D Tiles.
// Permanece desativada por padrão para custo/performance.
export const bootstrapPhotorealisticTiles = async (viewer, Cesium) => {
    const mode = String(MAP_CONFIG.photorealistic3dMode ?? 'off').toLowerCase();
    const url = typeof MAP_CONFIG.photorealistic3dUrl === 'string' ? MAP_CONFIG.photorealistic3dUrl.trim() : '';

    if (mode === 'off' || url === '') {
        return { enabled: false, reason: 'disabled' };
    }

    try {
        let tileset = null;

        if (typeof Cesium.Cesium3DTileset?.fromUrl === 'function') {
            tileset = await Cesium.Cesium3DTileset.fromUrl(url, {
                maximumScreenSpaceError: 8,
                dynamicScreenSpaceError: true,
                dynamicScreenSpaceErrorDensity: 0.002,
                dynamicScreenSpaceErrorFactor: 4.0,
                cullWithChildrenBounds: true,
                preloadWhenHidden: false,
                skipLevelOfDetail: true,
            });
        } else {
            tileset = new Cesium.Cesium3DTileset({
                url,
                maximumScreenSpaceError: 8,
                dynamicScreenSpaceError: true,
                cullWithChildrenBounds: true,
                preloadWhenHidden: false,
                skipLevelOfDetail: true,
            });
        }

        viewer.scene.primitives.add(tileset);
        tileset.show = true;

        return { enabled: true, tileset };
    } catch (error) {
        console.warn('Falha ao carregar Photorealistic 3D Tiles.', error);
        return { enabled: false, reason: 'load_error' };
    }
};
