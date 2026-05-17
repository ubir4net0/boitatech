import { RENDER_PROFILE } from './config.js';

export const setupPerformanceManager = (viewer) => {
    const { scene } = viewer;
    let lastFrameTs = performance.now();
    let smoothedFrameMs = 16;

    const adjustQuality = () => {
        if (!scene?.globe) return;

        const lowFps = smoothedFrameMs > 34;
        const highFps = smoothedFrameMs < 22;

        if (lowFps) {
            scene.globe.maximumScreenSpaceError = Math.min(3.2, scene.globe.maximumScreenSpaceError + 0.08);
            scene.globe.tileCacheSize = Math.max(220, Math.floor(scene.globe.tileCacheSize * 0.98));
            viewer.resolutionScale = Math.max(1.0, viewer.resolutionScale - 0.02);
        } else if (highFps) {
            scene.globe.maximumScreenSpaceError = Math.max(RENDER_PROFILE.maximumScreenSpaceError, scene.globe.maximumScreenSpaceError - 0.04);
            scene.globe.tileCacheSize = Math.min(RENDER_PROFILE.tileCacheSize, Math.floor(scene.globe.tileCacheSize * 1.01));
            viewer.resolutionScale = Math.min(RENDER_PROFILE.resolutionScale, viewer.resolutionScale + 0.01);
        }
    };

    const onPostRender = () => {
        const now = performance.now();
        const frameMs = now - lastFrameTs;
        lastFrameTs = now;

        smoothedFrameMs = (smoothedFrameMs * 0.92) + (frameMs * 0.08);
        adjustQuality();
    };

    scene.postRender.addEventListener(onPostRender);

    return () => {
        scene.postRender.removeEventListener(onPostRender);
    };
};
