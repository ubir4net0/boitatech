<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AlertaController extends Controller
{
    private const AMAZON_BOUNDS = [
        'west' => -75.0,
        'south' => -15.0,
        'east' => -45.0,
        'north' => 5.0,
    ];

    private const ALERT_TYPES = [
        'desmatamento',
        'queimada',
        'garimpo_ilegal',
        'invasao',
    ];

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'bbox' => ['nullable', 'string'], // formato: west,south,east,north
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Parâmetros inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $page = (int) ($request->integer('page') ?: 1);
        $limit = (int) ($request->integer('limit') ?: 60);

        $bounds = $this->resolveBounds((string) $request->query('bbox', ''));
        $alertas = $this->buildMockAlertas($page, $limit, $bounds);

        return response()->json([
            'data' => $alertas,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'has_more' => $page < 20,
            ],
        ]);
    }

    /**
     * @return array{west: float, south: float, east: float, north: float}
     */
    private function resolveBounds(string $bbox): array
    {
        $default = self::AMAZON_BOUNDS;

        if ($bbox === '') {
            return $default;
        }

        $parts = array_map('trim', explode(',', $bbox));

        if (count($parts) !== 4) {
            return $default;
        }

        [$west, $south, $east, $north] = array_map(static function ($value) {
            return is_numeric($value) ? (float) $value : null;
        }, $parts);

        if ($west === null || $south === null || $east === null || $north === null) {
            return $default;
        }

        if ($west >= $east || $south >= $north) {
            return $default;
        }

        return [
            'west' => max(self::AMAZON_BOUNDS['west'], $west),
            'south' => max(self::AMAZON_BOUNDS['south'], $south),
            'east' => min(self::AMAZON_BOUNDS['east'], $east),
            'north' => min(self::AMAZON_BOUNDS['north'], $north),
        ];
    }

    /**
     * @param  array{west: float, south: float, east: float, north: float}  $bounds
     * @return array<int, array{latitude: float, longitude: float, tipo_alerta: string, data: string}>
     */
    private function buildMockAlertas(int $page, int $limit, array $bounds): array
    {
        $alertas = [];

        for ($i = 0; $i < $limit; $i++) {
            $seed = crc32(sprintf('%d-%d', $page, $i));

            $longitude = $this->randomFloat($seed, $bounds['west'], $bounds['east']);
            $latitude = $this->randomFloat($seed >> 1, $bounds['south'], $bounds['north']);
            $tipo = self::ALERT_TYPES[$seed % count(self::ALERT_TYPES)];
            $data = now()->subDays($seed % 60)->toDateString();

            $alertas[] = [
                'latitude' => round((float) $latitude, 6),
                'longitude' => round((float) $longitude, 6),
                'tipo_alerta' => $tipo,
                'data' => $data,
            ];
        }

        return $alertas;
    }

    private function randomFloat(int $seed, float $min, float $max): float
    {
        $normalized = abs(($seed % 1000000) / 1000000);

        return $min + (($max - $min) * $normalized);
    }
}
