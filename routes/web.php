<?php

use App\Http\Controllers\Admin\NewsSourcesController;
use App\Http\Controllers\BoitaNewsController;
use App\Http\Controllers\DenunciaController;
use App\Http\Controllers\EcopontoController;
use App\Http\Controllers\LgpdController;
use App\Http\Controllers\MapaController;
use App\Http\Controllers\MapaInterativoController;
use App\Http\Middleware\ProtectNewsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mapa', [MapaController::class, 'index'])->name('mapa.index');
Route::get('/mapa-interativo', [MapaInterativoController::class, 'index'])->name('mapa.interativo');
Route::get('/boitanews', BoitaNewsController::class)->name('boitanews.index');
Route::get('/ecopontos', [EcopontoController::class, 'index'])->name('ecopontos.index');
Route::get('/ecopontos/{ecoponto}', [EcopontoController::class, 'show'])->name('ecopontos.show');
Route::get('/denuncias', [DenunciaController::class, 'index'])->name('denuncias.index');
Route::get('/denuncias/{denuncia}', [DenunciaController::class, 'show'])->name('denuncias.show');
Route::get('/privacidade', [LgpdController::class, 'privacy'])->name('lgpd.privacy');
Route::get('/lgpd/solicitacoes', [LgpdController::class, 'requestForm'])->name('lgpd.requests.form');
Route::post('/lgpd/solicitacoes', [LgpdController::class, 'storeRequest'])->name('lgpd.requests.store');
Route::get('/admin/news/sources', NewsSourcesController::class)
    ->middleware(ProtectNewsAdmin::class)
    ->name('admin.news.sources');
