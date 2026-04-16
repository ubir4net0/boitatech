<?php

use App\Http\Controllers\Api\AlertaController;
use Illuminate\Support\Facades\Route;

Route::get('/alertas', [AlertaController::class, 'index'])->name('api.alertas.index');
