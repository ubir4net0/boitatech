export const resolveTerrainProvider = async (Cesium) => {
    console.info('[Cesium] Terrain disabled. Using EllipsoidTerrainProvider.');
    return new Cesium.EllipsoidTerrainProvider();
};

export const createOfflineImageryProvider = (Cesium) => {
    try {
        return new Cesium.TileMapServiceImageryProvider({
            url: Cesium.buildModuleUrl('Assets/Textures/NaturalEarthII'),
        });
    } catch (error) {
        console.warn('Falha ao carregar NaturalEarthII local. Usando OpenStreetMap como fallback.', error);
        return new Cesium.OpenStreetMapImageryProvider({
            url: 'https://tile.openstreetmap.org/',
        });
    }
};

const createArcGisImagery = async (Cesium) => {
    try {
        if (typeof Cesium.ArcGisMapServerImageryProvider?.fromUrl === 'function') {
            return await Cesium.ArcGisMapServerImageryProvider.fromUrl(
                'https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer',
            );
        }

        return new Cesium.ArcGisMapServerImageryProvider({
            url: 'https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer',
        });
    } catch (error) {
        console.warn('Falha ao carregar ArcGIS World Imagery.', error);
        return null;
    }
};

const createOsmFallback = (Cesium) => new Cesium.OpenStreetMapImageryProvider({
    url: 'https://tile.openstreetmap.org/',
});

export const resolveImageryProvider = async (Cesium) => {
    const provider = await createArcGisImagery(Cesium);
    if (provider) {
        return provider;
    }

    console.warn('ArcGIS indisponível. Usando fallback OpenStreetMap imagery.');
    return createOsmFallback(Cesium);
};
