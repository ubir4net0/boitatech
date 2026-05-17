<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

class EnvironmentalLayerController extends Controller
{
    private const BRAZIL_BOUNDS = [
        'west' => -74.5,
        'south' => -34.0,
        'east' => -28.5,
        'north' => 6.5,
    ];

    private const MAX_BBOX_AREA_DEG2 = 2_400.0;
    private const MAX_DATE_SPAN_DAYS = 31;
    private const MAX_LIMIT = 2_000;
    private const PG_STATEMENT_TIMEOUT_MS = 12_000;

    public function riscoFogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'bbox' => ['required', 'string', 'max:80'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'nivel' => ['nullable', 'in:baixo,medio,alto,critico'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ]);

        $validator->after(fn (ValidationValidator $validator) => $this->validateDateSpan(
            $validator,
            (string) $request->query('start_date'),
            (string) $request->query('end_date'),
        ));

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $bounds = $this->resolveBounds((string) $request->query('bbox'));
        if ($bounds === null) {
            return response()->json(['message' => 'bbox fora dos limites permitidos para consulta.'], 422);
        }

        $startDate = (string) $request->query('start_date');
        $endDate = (string) $request->query('end_date');
        $nivel = (string) $request->query('nivel', '');
        $limit = (int) ($request->integer('limit') ?: 800);

        $cacheKey = $this->cacheKey('risco-fogo', compact('bounds', 'startDate', 'endDate', 'nivel', 'limit'));

        $payload = Cache::remember($cacheKey, 120, function () use ($bounds, $startDate, $endDate, $nivel, $limit) {
            return DB::connection('pgsql')->transaction(function () use ($bounds, $startDate, $endDate, $nivel, $limit) {
                $this->applyStatementTimeout();

                $query = DB::connection('pgsql')
                    ->table('risco_fogo')
                    ->whereBetween('data', [$startDate, $endDate])
                    ->whereRaw('geometria && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']])
                    ->whereRaw('ST_Intersects(geometria, ST_MakeEnvelope(?, ?, ?, ?, 4326))', [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']]);

                if ($nivel !== '') {
                    $query->where('nivel_risco', $nivel);
                }

                $rows = $query
                    ->orderByDesc('data')
                    ->orderByDesc('risco_score')
                    ->limit($limit)
                    ->selectRaw('id, data, nivel_risco, risco_score, fonte, ST_AsGeoJSON(geometria)::json as geometry')
                    ->get();

                return [
                    'type' => 'FeatureCollection',
                    'features' => $rows->map(fn ($row) => [
                        'type' => 'Feature',
                        'geometry' => $row->geometry,
                        'properties' => [
                            'id' => (int) $row->id,
                            'data' => (string) $row->data,
                            'nivel_risco' => (string) $row->nivel_risco,
                            'risco_score' => (float) $row->risco_score,
                            'fonte' => (string) $row->fonte,
                        ],
                    ])->values()->all(),
                    'meta' => [
                        'source' => 'risco_fogo',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ];
            });
        });

        return response()->json($payload);
    }

    public function desmatamento(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'bbox' => ['required', 'string', 'max:80'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ]);

        $validator->after(fn (ValidationValidator $validator) => $this->validateDateSpan(
            $validator,
            (string) $request->query('start_date'),
            (string) $request->query('end_date'),
        ));

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $bounds = $this->resolveBounds((string) $request->query('bbox'));
        if ($bounds === null) {
            return response()->json(['message' => 'bbox fora dos limites permitidos para consulta.'], 422);
        }

        $startDate = (string) $request->query('start_date');
        $endDate = (string) $request->query('end_date');
        $limit = (int) ($request->integer('limit') ?: 800);

        $cacheKey = $this->cacheKey('desmatamento', compact('bounds', 'startDate', 'endDate', 'limit'));

        $payload = Cache::remember($cacheKey, 180, function () use ($bounds, $startDate, $endDate, $limit) {
            return DB::connection('pgsql')->transaction(function () use ($bounds, $startDate, $endDate, $limit) {
                $this->applyStatementTimeout();

                $rows = DB::connection('pgsql')
                    ->table('desmatamento_deter')
                    ->whereBetween('data_alerta', [$startDate, $endDate])
                    ->whereRaw('geometria && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']])
                    ->whereRaw('ST_Intersects(geometria, ST_MakeEnvelope(?, ?, ?, ?, 4326))', [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']])
                    ->orderByDesc('data_alerta')
                    ->orderByDesc('area')
                    ->limit($limit)
                    ->selectRaw('id, data_alerta, area, fonte, ST_AsGeoJSON(geometria)::json as geometry')
                    ->get();

                return [
                    'type' => 'FeatureCollection',
                    'features' => $rows->map(fn ($row) => [
                        'type' => 'Feature',
                        'geometry' => $row->geometry,
                        'properties' => [
                            'id' => (int) $row->id,
                            'data_alerta' => (string) $row->data_alerta,
                            'area' => (float) $row->area,
                            'fonte' => (string) $row->fonte,
                        ],
                    ])->values()->all(),
                    'meta' => [
                        'source' => 'desmatamento_deter',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ];
            });
        });

        return response()->json($payload);
    }

    public function zonasPrioritarias(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'bbox' => ['required', 'string', 'max:80'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'nivel' => ['nullable', 'in:baixo,medio,alto,critico'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:800'],
        ]);

        $validator->after(fn (ValidationValidator $validator) => $this->validateDateSpan(
            $validator,
            (string) $request->query('start_date'),
            (string) $request->query('end_date'),
        ));

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $bounds = $this->resolveBounds((string) $request->query('bbox'));
        if ($bounds === null) {
            return response()->json(['message' => 'bbox fora dos limites permitidos para consulta.'], 422);
        }

        $startDate = (string) $request->query('start_date');
        $endDate = (string) $request->query('end_date');
        $nivel = (string) $request->query('nivel', '');
        $limit = (int) ($request->integer('limit') ?: 300);

        $cacheKey = $this->cacheKey('zonas-prioritarias', compact('bounds', 'startDate', 'endDate', 'nivel', 'limit'));

        $payload = Cache::remember($cacheKey, 120, function () use ($bounds, $startDate, $endDate, $nivel, $limit) {
            return DB::connection('pgsql')->transaction(function () use ($bounds, $startDate, $endDate, $nivel, $limit) {
                $this->applyStatementTimeout();

                $query = DB::connection('pgsql')
                    ->table('zonas_prioritarias')
                    ->whereBetween(DB::raw('updated_at::date'), [$startDate, $endDate])
                    ->whereRaw('geometria && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']])
                    ->whereRaw('ST_Intersects(geometria, ST_MakeEnvelope(?, ?, ?, ?, 4326))', [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']]);

                if ($nivel !== '') {
                    $query->where('nivel', $nivel);
                }

                $rows = $query
                    ->orderByDesc('score_risco')
                    ->orderByDesc('updated_at')
                    ->limit($limit)
                    ->selectRaw('id, score_risco, nivel, updated_at, fonte, ST_AsGeoJSON(geometria)::json as geometry')
                    ->get();

                return [
                    'type' => 'FeatureCollection',
                    'features' => $rows->map(fn ($row) => [
                        'type' => 'Feature',
                        'geometry' => $row->geometry,
                        'properties' => [
                            'id' => (int) $row->id,
                            'score_risco' => (float) $row->score_risco,
                            'nivel' => (string) $row->nivel,
                            'updated_at' => (string) $row->updated_at,
                            'fonte' => (string) $row->fonte,
                        ],
                    ])->values()->all(),
                    'meta' => [
                        'source' => 'zonas_prioritarias',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ];
            });
        });

        return response()->json($payload);
    }

    private function validateDateSpan(ValidationValidator $validator, string $startDate, string $endDate): void
    {
        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $startDate);
            $end = CarbonImmutable::createFromFormat('Y-m-d', $endDate);
        } catch (\Throwable) {
            $validator->errors()->add('start_date', 'Período inválido.');
            return;
        }

        if ($start->greaterThan($end)) {
            $validator->errors()->add('start_date', 'start_date não pode ser maior que end_date.');
            return;
        }

        if ($start->diffInDays($end) > self::MAX_DATE_SPAN_DAYS) {
            $validator->errors()->add('end_date', 'Janela máxima permitida é de ' . self::MAX_DATE_SPAN_DAYS . ' dias por requisição.');
        }
    }

    private function resolveBounds(string $bbox): ?array
    {
        $parts = array_map('trim', explode(',', $bbox));
        if (count($parts) !== 4) {
            return null;
        }

        [$west, $south, $east, $north] = array_map(static fn ($value) => is_numeric($value) ? (float) $value : null, $parts);

        if ($west === null || $south === null || $east === null || $north === null) {
            return null;
        }

        if ($west >= $east || $south >= $north) {
            return null;
        }

        $clamped = [
            'west' => max(self::BRAZIL_BOUNDS['west'], $west),
            'south' => max(self::BRAZIL_BOUNDS['south'], $south),
            'east' => min(self::BRAZIL_BOUNDS['east'], $east),
            'north' => min(self::BRAZIL_BOUNDS['north'], $north),
        ];

        $area = ($clamped['east'] - $clamped['west']) * ($clamped['north'] - $clamped['south']);

        return $area > self::MAX_BBOX_AREA_DEG2 ? null : $clamped;
    }

    private function applyStatementTimeout(): void
    {
        DB::connection('pgsql')->statement('SET LOCAL statement_timeout = ' . self::PG_STATEMENT_TIMEOUT_MS);
    }

    private function validationError(ValidationValidator $validator): JsonResponse
    {
        return response()->json([
            'message' => 'Parâmetros inválidos.',
            'errors' => $validator->errors(),
        ], 422);
    }

    private function cacheKey(string $prefix, array $payload): string
    {
        return 'environment:' . $prefix . ':' . hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
