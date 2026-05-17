export class CesiumGestureAdapter {
    constructor({ viewer }) {
        this.viewer = viewer;
        this.initialPose = this.capturePose();
        this.panScale = 1.75;
        this.zoomScale = 0.6;
        this.rotateScale = 0.9;
    }

    capturePose() {
        const camera = this.viewer?.camera;
        if (!camera) return null;

        return {
            destination: camera.positionWC?.clone?.() ?? null,
            heading: Number(camera.heading ?? 0),
            pitch: Number(camera.pitch ?? 0),
            roll: Number(camera.roll ?? 0),
        };
    }

    apply(output) {
        if (!this.viewer || !output) return;

        if (output.panDelta) {
            this.pan(output.panDelta.x, output.panDelta.y);
        }

        if (typeof output.zoomDelta === 'number') {
            this.zoom(output.zoomDelta);
        }

        if (typeof output.rotateDelta === 'number') {
            this.rotate(output.rotateDelta);
        }

        this.viewer.scene?.requestRender?.();
    }

    pan(dx, dy) {
        const camera = this.viewer?.camera;
        if (!camera) return;

        const height = Number(camera.positionCartographic?.height ?? 5_000_000);
        const movementBase = Math.max(2_500, Math.min(520_000, height * 0.11));

        camera.moveRight(-dx * movementBase * this.panScale);
        camera.moveUp(dy * movementBase * this.panScale);
    }

    zoom(delta) {
        const camera = this.viewer?.camera;
        if (!camera) return;

        const height = Number(camera.positionCartographic?.height ?? 5_000_000);
        const amount = Math.max(850, Math.min(400_000, height * Math.abs(delta) * this.zoomScale));

        if (delta > 0) {
            camera.zoomIn(amount);
            return;
        }

        camera.zoomOut(amount);
    }

    rotate(deltaRad) {
        const camera = this.viewer?.camera;
        if (!camera || !Number.isFinite(deltaRad)) return;

        const strength = deltaRad * this.rotateScale;
        camera.rotateRight(-strength);
    }

    resetPose() {
        const Cesium = window.Cesium;
        const camera = this.viewer?.camera;
        if (!Cesium || !camera || !this.initialPose?.destination) return;

        camera.flyTo({
            destination: this.initialPose.destination,
            orientation: {
                heading: this.initialPose.heading,
                pitch: this.initialPose.pitch,
                roll: this.initialPose.roll,
            },
            duration: 0.9,
            easingFunction: Cesium.EasingFunction.CUBIC_OUT,
        });
    }
}
