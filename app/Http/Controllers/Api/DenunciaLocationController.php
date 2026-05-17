<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Denuncias\GeocodingService;
use App\Services\Denuncias\LocationDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DenunciaLocationController extends Controller
{
    public function __construct(
        private readonly LocationDirectoryService $directory,
        private readonly GeocodingService $geocoding,
    ) {
    }

    public function states(): JsonResponse
    {
        return response()->json([
            'data' => $this->directory->getStates(),
        ]);
    }

    public function cities(string $uf): JsonResponse
    {
        return response()->json([
            'data' => $this->directory->getCitiesByUf($uf),
        ]);
    }

    public function neighborhoods(Request $request, int $cityId): JsonResponse
    {
        $request->validate([
            'uf' => ['required', 'string', 'size:2'],
        ]);

        return response()->json([
            'data' => $this->directory->getNeighborhoodsByCityId($cityId),
        ]);
    }

    public function geocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', 'size:2'],
            'cidade' => ['required', 'string', 'min:2', 'max:120'],
            'bairro' => ['nullable', 'string', 'max:160'],
        ]);

        $resolved = $this->geocoding->resolve(
            (string) $validated['estado'],
            (string) $validated['cidade'],
            (string) ($validated['bairro'] ?? ''),
        );

        if ($resolved === null) {
            return response()->json([
                'message' => 'Não foi possível localizar essa região no momento.',
                'data' => null,
            ], 422);
        }

        return response()->json([
            'data' => $resolved,
        ]);
    }
}
