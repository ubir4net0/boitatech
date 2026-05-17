<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class BoitaNewsController extends Controller
{
    public function __invoke(): View
    {
        return view('boitanews.index');
    }
}
