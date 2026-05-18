<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\CommandCenterMetricsService;
use App\ViewModels\Dashboard\CommandCenterViewModel;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CommandCenterMetricsService $metrics,
    ) {
    }

    public function index(): View
    {
        $viewModel = new CommandCenterViewModel($this->metrics->snapshot());

        return view('dashboard', [
            'viewModel' => $viewModel,
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->metrics->snapshot()->toArray());
    }
}
