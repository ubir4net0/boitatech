<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class MapaController extends Controller
{
    public function index(): View
    {
        return view('mapa', [
            'cesiumIonToken' => (string) config('services.cesium.ion_token', ''),
        ]);
    }
}
