<?php

namespace App\Http\Controllers;

use App\Models\Denuncia;
use Illuminate\View\View;

class DenunciaController extends Controller
{
    public function index(): View
    {
        return view('denuncias.index', [
            'categories' => config('denuncias.categories', []),
            'statuses' => config('denuncias.statuses', []),
        ]);
    }

    public function show(Denuncia $denuncia): View
    {
        $denuncia->loadCount('confirmacoes');

        return view('denuncias.show', [
            'denuncia' => $denuncia,
            'categories' => config('denuncias.categories', []),
            'statuses' => config('denuncias.statuses', []),
        ]);
    }
}