<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class MapaInterativoController extends Controller
{
    public function index(): View
    {
        return view('mapa-interativo');
    }
}
