import { RENDER_PROFILE } from './config.js';

export const applyCinematicEffects = (viewer, Cesium) => {
    const { scene } = viewer;

    scene.globe.baseColor = Cesium.Color.fromCssColorString('#11253b');
    scene.globe.depthTestAgainstTerrain = false;
    scene.globe.enableLighting = false;
    scene.globe.dynamicAtmosphereLighting = false;
    scene.globe.dynamicAtmosphereLightingFromSun = false;
    scene.globe.preloadSiblings = true;
    scene.globe.preloadAncestors = true;
    scene.globe.maximumScreenSpaceError = RENDER_PROFILE.maximumScreenSpaceError;
    scene.globe.dynamicScreenSpaceError = true;
    scene.globe.dynamicScreenSpaceErrorDensity = 0.0015;
    scene.globe.dynamicScreenSpaceErrorFactor = 3.6;
    scene.globe.dynamicScreenSpaceErrorHeightFalloff = 0.25;
    scene.globe.tileCacheSize = RENDER_PROFILE.tileCacheSize;
    scene.globe.loadingDescendantLimit = 18;
    scene.globe.showGroundAtmosphere = false;

    if (scene.skyAtmosphere) scene.skyAtmosphere.show = false;
    if (scene.sun) scene.sun.show = false;
    if (scene.moon) scene.moon.show = false;

    scene.fog.enabled = true;
    scene.fog.density = RENDER_PROFILE.fogDensity;
    scene.fog.minimumBrightness = 0.06;
    scene.fog.screenSpaceErrorFactor = 2.0;

    scene.highDynamicRange = false;
    scene.fxaa = true;
    scene.msaaSamples = RENDER_PROFILE.tier === 'low' ? 2 : 4;

    if (scene.postProcessStages?.bloom) {
        scene.postProcessStages.bloom.enabled = false;
    }

    if (scene.postProcessStages?.fxaa) {
        scene.postProcessStages.fxaa.enabled = true;
    }

    scene.requestRenderMode = true;
};
