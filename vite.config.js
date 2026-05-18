import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import cesium from 'vite-plugin-cesium';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/landing.js', 'resources/js/mapa.js', 'resources/js/mapa-interativo.js', 'resources/js/dashboard.js', 'resources/js/boitanews.js', 'resources/js/denuncias.js', 'resources/js/ecopontos.js'],
            refresh: true,
        }),
        tailwindcss(),
        cesium({
            rebuildCesium: true,
            cesiumBaseUrl: 'cesium',
        }),
    ],
    build: {
        outDir: 'public/build',
        assetsDir: 'assets',
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    optimizeDeps: {
        include: ['cesium'],
    },
});
