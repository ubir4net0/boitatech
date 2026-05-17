<?php

namespace App\Http\Controllers;

use App\Models\Ecoponto;
use Illuminate\View\View;

class EcopontoController extends Controller
{
    public function index(): View
    {
        return view('ecopontos.index', [
            'types' => config('ecopontos.categories', []),
            'city' => config('ecopontos.city', []),
        ]);
    }

    public function show(Ecoponto $ecoponto): View
    {
        return view('ecopontos.show', [
            'ecoponto' => $ecoponto,
            'types' => config('ecopontos.categories', []),
            'city' => config('ecopontos.city', []),
        ]);
    }
}
