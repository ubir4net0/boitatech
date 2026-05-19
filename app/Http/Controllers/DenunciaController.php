<?php

namespace App\Http\Controllers;

use App\Actions\Denuncias\ExportDenunciaPdfAction;
use App\Exceptions\PdfGenerationException;
use App\Models\Denuncia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
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

    public function pdf(Denuncia $denuncia, ExportDenunciaPdfAction $action): Response|JsonResponse|RedirectResponse
    {
        try {
            return $action->handle($denuncia);
        } catch (PdfGenerationException $exception) {
            Log::warning('Exportação PDF indisponível para denúncia.', [
                'denuncia_id' => $denuncia->id,
                'message' => $exception->getMessage(),
            ]);

            $friendlyMessage = 'Não foi possível gerar o PDF no momento.';

            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'message' => $friendlyMessage,
                ], 503);
            }

            return back()->with('pdf_error', $friendlyMessage);
        }
    }
}