<?php

use App\Http\Controllers\MapaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mapa', [MapaController::class, 'index'])->name('mapa.index');
