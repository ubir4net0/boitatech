<?php

use App\Http\Controllers\Api\DenunciaController;
use App\Http\Controllers\Api\DenunciaLocationController;
use App\Http\Controllers\Api\EcopontoController;
use App\Http\Controllers\Api\EnvironmentalLayerController;
use App\Http\Controllers\Api\FocoController;
use App\Http\Controllers\Api\LgpdConsentController;
use App\Http\Controllers\Api\NoticiaController;
use App\Http\Controllers\Api\SyncHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:mapa-api')->group(function (): void {
    Route::get('/alertas', [FocoController::class, 'current'])->name('api.alertas.index');
    Route::get('/focos/current', [FocoController::class, 'current'])->name('api.focos.current');
    Route::get('/focos/historico', [FocoController::class, 'historico'])->name('api.focos.historico');
    Route::get('/focos/cluster', [FocoController::class, 'cluster'])->name('api.focos.cluster');
});

Route::middleware('throttle:environment-api')->group(function (): void {
    Route::get('/risco-fogo', [EnvironmentalLayerController::class, 'riscoFogo'])->name('api.risco-fogo.index');
    Route::get('/desmatamento', [EnvironmentalLayerController::class, 'desmatamento'])->name('api.desmatamento.index');
    Route::get('/zonas-prioritarias', [EnvironmentalLayerController::class, 'zonasPrioritarias'])->name('api.zonas-prioritarias.index');
    Route::get('/health/sync', SyncHealthController::class)->name('api.health.sync');
});

Route::prefix('noticias')->middleware('throttle:news-api')->group(function (): void {
    Route::get('/', [NoticiaController::class, 'index'])->name('api.noticias.index');
    Route::get('/destaques', [NoticiaController::class, 'destaques'])->name('api.noticias.destaques');
    Route::get('/recentes', [NoticiaController::class, 'recentes'])->name('api.noticias.recentes');
    Route::get('/relevantes', [NoticiaController::class, 'relevantes'])->name('api.noticias.relevantes');
    Route::get('/categorias', [NoticiaController::class, 'categorias'])->name('api.noticias.categorias');
});

Route::prefix('denuncias')->middleware('throttle:denuncias-api')->group(function (): void {
    Route::prefix('localidades')->middleware('throttle:denuncias-geo')->group(function (): void {
        Route::get('/estados', [DenunciaLocationController::class, 'states'])->name('api.denuncias.locations.states');
        Route::get('/{uf}/cidades', [DenunciaLocationController::class, 'cities'])->name('api.denuncias.locations.cities');
        Route::get('/cidades/{cityId}/bairros', [DenunciaLocationController::class, 'neighborhoods'])->name('api.denuncias.locations.neighborhoods');
        Route::get('/geocode', [DenunciaLocationController::class, 'geocode'])->name('api.denuncias.locations.geocode');
    });

    Route::get('/', [DenunciaController::class, 'index'])->name('api.denuncias.index');
    Route::get('/{denuncia}', [DenunciaController::class, 'show'])->name('api.denuncias.show');
    Route::post('/', [DenunciaController::class, 'store'])->name('api.denuncias.store');
    Route::post('/{denuncia}/confirmar', [DenunciaController::class, 'confirm'])
        ->middleware('throttle:denuncias-confirm')
        ->name('api.denuncias.confirm');
});

Route::prefix('ecopontos')->middleware('throttle:denuncias-api')->group(function (): void {
    Route::get('/', [EcopontoController::class, 'index'])->name('api.ecopontos.index');
    Route::get('/map', [EcopontoController::class, 'map'])->name('api.ecopontos.map');
    Route::get('/{ecoponto}', [EcopontoController::class, 'show'])->name('api.ecopontos.show');
});

Route::prefix('lgpd')->middleware('throttle:denuncias-api')->group(function (): void {
    Route::post('/consent', [LgpdConsentController::class, 'store'])->name('api.lgpd.consent.store');
});
