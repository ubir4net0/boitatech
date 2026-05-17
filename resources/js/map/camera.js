import { CAMERA, MAP_CONFIG, RENDER_PROFILE } from './config.js';

export const applyCameraTuning = (viewer, Cesium) => {
    const cameraController = viewer.scene.screenSpaceCameraController;

    cameraController.inertiaSpin = RENDER_PROFILE.cameraInertia.spin;
    cameraController.inertiaTranslate = RENDER_PROFILE.cameraInertia.translate;
    cameraController.inertiaZoom = RENDER_PROFILE.cameraInertia.zoom;
    cameraController.maximumMovementRatio = 0.09;
    cameraController.zoomFactor = 2.1;
    cameraController.maximumZoomDistance = 7_000_000;
    cameraController.minimumZoomDistance = 12_000;
    cameraController.maximumTiltAngle = Cesium.Math.toRadians(74);
    cameraController.enableLook = false;
    cameraController.enableCollisionDetection = true;
    viewer.camera.percentageChanged = 0.0025;

    const bounds = MAP_CONFIG.brazilBounds;
    viewer.camera.constrainedAxis = Cesium.Cartesian3.UNIT_Z;
    Cesium.Camera.DEFAULT_VIEW_RECTANGLE = Cesium.Rectangle.fromDegrees(
        bounds.west,
        bounds.south,
        bounds.east,
        bounds.north,
    );
};

export const cinematicFlyToBrazil = async (viewer, Cesium) => {
    const amazonEntry = {
        longitude: -61.2,
        latitude: -3.6,
        height: 2_400_000,
        heading: -0.08,
        pitch: -1.12,
        roll: 0,
    };

    await viewer.camera.flyTo({
        destination: Cesium.Cartesian3.fromDegrees(amazonEntry.longitude, amazonEntry.latitude, amazonEntry.height),
        orientation: {
            heading: amazonEntry.heading,
            pitch: amazonEntry.pitch,
            roll: amazonEntry.roll,
        },
        duration: 1.5,
        easingFunction: Cesium.EasingFunction.QUADRATIC_OUT,
        maximumHeight: 6_300_000,
    });

    await viewer.camera.flyTo({
        destination: Cesium.Cartesian3.fromDegrees(CAMERA.longitude, CAMERA.latitude, CAMERA.height),
        orientation: {
            heading: CAMERA.heading,
            pitch: CAMERA.pitch,
            roll: CAMERA.roll,
        },
        duration: 2.4,
        easingFunction: Cesium.EasingFunction.CUBIC_OUT,
        maximumHeight: 8_500_000,
    });
};
