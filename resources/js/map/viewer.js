import { MAP_CONFIG, RENDER_PROFILE, VIEWER_OPTIONS } from './config.js';
import { applyCameraTuning, cinematicFlyToBrazil } from './camera.js';
import { applyCinematicEffects } from './effects.js';
import { setupPerformanceManager } from './performance.js';
import { createOfflineImageryProvider, resolveImageryProvider, resolveTerrainProvider } from './providers.js';
import { configureCesiumBaseUrl, disableCesiumIonAndCredit, applyCesiumSafeDefaults } from '../lib/cesium/setupCesium.js';

export const initViewer = async (containerId = 'cesiumContainer') => {
    const Cesium = window.Cesium;

    if (!Cesium) {
        throw new Error('CESIUM_LIB_UNAVAILABLE');
    }

    configureCesiumBaseUrl();

    disableCesiumIonAndCredit(Cesium);

    const terrainProvider = await resolveTerrainProvider(Cesium);
    const offlineImageryProvider = createOfflineImageryProvider(Cesium);

    const viewer = new Cesium.Viewer(containerId, {
        ...VIEWER_OPTIONS,
        maximumRenderTimeChange: RENDER_PROFILE.maximumRenderTimeChange,
        skyBox: false,
        skyAtmosphere: false,
        shouldAnimate: false,
        baseLayer: false,
        terrainProvider,
    });

    console.info('[Cesium] Viewer initialized');

    applyCesiumSafeDefaults(viewer, Cesium);

    viewer.imageryLayers.removeAll();
    viewer.imageryLayers.addImageryProvider(offlineImageryProvider);
    console.info('[Cesium] Offline imagery loaded');

    try {
        const imageryProvider = await resolveImageryProvider(Cesium);
        viewer.imageryLayers.removeAll();
        const remoteLayer = viewer.imageryLayers.addImageryProvider(imageryProvider);

        const onProviderError = () => {
            console.warn('[Cesium] Remote imagery error. Reverting to offline imagery.');
            try {
                viewer.imageryLayers.remove(remoteLayer, true);
                if (viewer.imageryLayers.length === 0) {
                    viewer.imageryLayers.addImageryProvider(createOfflineImageryProvider(Cesium));
                }
                viewer.scene.requestRender();
            } catch (fallbackError) {
                console.error('[Cesium] Failed to restore offline imagery.', fallbackError);
            }
        };

        if (imageryProvider?.errorEvent?.addEventListener) {
            imageryProvider.errorEvent.addEventListener(onProviderError);
        }

        console.info('[Cesium] Remote imagery loaded');
    } catch (error) {
        console.warn('[Cesium] Remote imagery unavailable. Keeping offline imagery.', error);
    }

    const baseImagery = viewer.imageryLayers.get(0);
    if (baseImagery) {
        baseImagery.brightness = 0.86;
        baseImagery.contrast = 1.16;
        baseImagery.saturation = 0.77;
        baseImagery.gamma = 0.95;
        baseImagery.hue = 0.0;
    }

    viewer.resolutionScale = RENDER_PROFILE.resolutionScale;
    viewer.targetFrameRate = RENDER_PROFILE.targetFrameRate;
    viewer.shadows = false;
    viewer.clock.shouldAnimate = false;
    // applyCesiumSafeDefaults já cobriu sun/moon/skyAtmosphere/globe/highDynamicRange acima.

    applyCinematicEffects(viewer, Cesium);
    applyCameraTuning(viewer, Cesium);
    await cinematicFlyToBrazil(viewer, Cesium);
    const detachPerformanceManager = setupPerformanceManager(viewer);

    const dataSources = {
        current: new Cesium.CustomDataSource('focos-current'),
    };

    await Promise.all(Object.values(dataSources).map((source) => viewer.dataSources.add(source)));

    return {
        viewer,
        dataSources,
        dispose: () => {
            detachPerformanceManager?.();
        },
    };
};
