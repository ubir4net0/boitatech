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

class FocoController extends Controller
{
    private const BRAZIL_BOUNDS = [
        'west' => -74.5,
        'south' => -34.0,
        'east' => -28.5,
        'north' => 6.5,
    ];

    private const MAX_BBOX_AREA_DEG2 = 2_400.0;
    private const MAX_OFFSET = 20_000;
    private const MAX_DATE_SPAN_DAYS = 31;
    private const PG_STATEMENT_TIMEOUT_MS = 8_000;
    private const CURRENT_CACHE_TTL_SECONDS = 30;
    private const HISTORICO_CACHE_TTL_SECONDS = 300;
    private const CLUSTER_CACHE_TTL_SECONDS = 60;

    public function current(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'bbox' => ['nullable', 'string', 'max:80'],
            'format' => ['nullable', 'in:json,geojson'],
            'biome' => ['nullable', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $page = (int) ($request->integer('page') ?: 1);
        $limit = (int) ($request->integer('limit') ?: 300);
        $offset = ($page - 1) * $limit;
        $format = (string) $request->query('format', 'json');
        $biome = $this->sanitizeText($request->query('biome'));

        if ($offset > self::MAX_OFFSET) {
            return response()->json([
                'message' => 'Profundidade de paginação excedida. Refine bbox, biome ou aproxime o zoom.',
            ], 422);
        }

        $bounds = $this->resolveBounds((string) $request->query('bbox', ''));
        if ($bounds === null) {
            return response()->json([
                'message' => 'Área do bbox excede o limite permitido para consulta pública.',
            ], 422);
        }

        $cacheKey = $this->cacheKey('current', compact('page', 'limit', 'offset', 'format', 'bounds', 'biome'));

        $payload = Cache::remember($cacheKey, self::CURRENT_CACHE_TTL_SECONDS, function () use ($bounds, $limit, $offset, $page, $format, $biome) {
            return DB::connection('pgsql')->transaction(function () use ($bounds, $limit, $offset, $page, $format, $biome) {
                $this->applyStatementTimeout();

                $query = $this->baseSpatialQuery('focos_current', $bounds);

                if ($biome !== null) {
                    $query->where('biome', $biome);
                }

                $query->orderByDesc('viewed_at');
                $total = (clone $query)->count();

                $rows = $query
                    ->select([
                        'source_id',
                        'view_date',
                        'viewed_at',
                        'satelite',
                        'municipio',
                        'biome',
                        'longitude',
                        'latitude',
                    ])
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

                if ($format === 'geojson') {
                    return [
                        'type' => 'FeatureCollection',
                        'features' => $rows->map(fn ($row) => $this->toFeature($row, 'current'))->values()->all(),
                        'meta' => $this->meta($page, $limit, $total, 'focos_current'),
                    ];
                }

                return [
                    'data' => $rows->map(fn ($row) => $this->toPointPayload($row, 'current'))->values()->all(),
                    'meta' => $this->meta($page, $limit, $total, 'focos_current'),
                ];
            });
        });

        return response()->json($payload);
    }

    public function historico(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'bbox' => ['required', 'string', 'max:80'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'biome' => ['nullable', 'string', 'max:80'],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($request): void {
            $this->validateDateSpan($validator, (string) $request->query('start_date'), (string) $request->query('end_date'));
        });

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $page = (int) ($request->integer('page') ?: 1);
        $limit = (int) ($request->integer('limit') ?: 500);
        $offset = ($page - 1) * $limit;
        $biome = $this->sanitizeText($request->query('biome'));
        $startDate = (string) $request->query('start_date');
        $endDate = (string) $request->query('end_date');

        if ($offset > self::MAX_OFFSET) {
            return response()->json([
                'message' => 'Profundidade de paginação excedida. Use clusterização ou reduza o período.',
            ], 422);
        }

        $bounds = $this->resolveBounds((string) $request->query('bbox', ''));
        if ($bounds === null) {
            return response()->json([
                'message' => 'Área do bbox excede o limite permitido para o histórico.',
            ], 422);
        }

        $cacheKey = $this->cacheKey('historico', compact('page', 'limit', 'offset', 'bounds', 'biome', 'startDate', 'endDate'));

        $payload = Cache::remember($cacheKey, self::HISTORICO_CACHE_TTL_SECONDS, function () use ($bounds, $limit, $offset, $page, $startDate, $endDate, $biome) {
            return DB::connection('pgsql')->transaction(function () use ($bounds, $limit, $offset, $page, $startDate, $endDate, $biome) {
                $this->applyStatementTimeout();

                $query = $this->baseSpatialQuery('focos_historico', $bounds)
                    ->whereBetween('view_date', [$startDate, $endDate]);

                if ($biome !== null) {
                    $query->where('biome', $biome);
                }

                $query->orderByDesc('view_date')->orderByDesc('viewed_at');
                $total = (clone $query)->count();

                $rows = $query
                    ->select([
                        'source_id',
                        'source_layer',
                        'view_date',
                        'viewed_at',
                        'satelite',
                        'municipio',
                        'biome',
                        'uf',
                        'longitude',
                        'latitude',
                    ])
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

                return [
                    'data' => $rows->map(fn ($row) => $this->toPointPayload($row, 'historico'))->values()->all(),
                    'meta' => $this->meta($page, $limit, $total, 'focos_historico', [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]),
                ];
            });
        });

        return response()->json($payload);
    }

    public function cluster(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'bbox' => ['required', 'string', 'max:80'],
            'zoom' => ['nullable', 'integer', 'min:1', 'max:18'],
            'layer' => ['nullable', 'in:current,historico'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'biome' => ['nullable', 'string', 'max:80'],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($request): void {
            if ((string) $request->query('layer', 'current') === 'historico') {
                $startDate = (string) $request->query('start_date');
                $endDate = (string) $request->query('end_date');

                if ($startDate === '' || $endDate === '') {
                    $validator->errors()->add('start_date', 'start_date e end_date são obrigatórios para cluster histórico.');
                    return;
                }

                $this->validateDateSpan($validator, $startDate, $endDate);
            }
        });

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $bounds = $this->resolveBounds((string) $request->query('bbox', ''));
        if ($bounds === null) {
            return response()->json([
                'message' => 'Área do bbox excede o limite permitido para clusterização.',
            ], 422);
        }

        $layer = (string) $request->query('layer', 'current');
        $table = $layer === 'historico' ? 'focos_historico' : 'focos_current';
        $zoom = (int) ($request->integer('zoom') ?: 5);
        $clusterLimit = (int) ($request->integer('limit') ?: 350);
        $cellSize = $this->clusterCellSizeForZoom($zoom);
        $biome = $this->sanitizeText($request->query('biome'));
        $startDate = (string) $request->query('start_date', '');
        $endDate = (string) $request->query('end_date', '');

        $cacheKey = $this->cacheKey('cluster', compact('bounds', 'layer', 'zoom', 'clusterLimit', 'cellSize', 'biome', 'startDate', 'endDate'));

        $payload = Cache::remember($cacheKey, self::CLUSTER_CACHE_TTL_SECONDS, function () use ($table, $bounds, $clusterLimit, $cellSize, $layer, $biome, $startDate, $endDate) {
            return DB::connection('pgsql')->transaction(function () use ($table, $bounds, $clusterLimit, $cellSize, $layer, $biome, $startDate, $endDate) {
                $this->applyStatementTimeout();

                $dateClause = '';
                $bindings = [
                    $bounds['west'], $bounds['south'], $bounds['east'], $bounds['north'],
                    $bounds['west'], $bounds['south'], $bounds['east'], $bounds['north'],
                ];

                if ($table === 'focos_historico') {
                    $dateClause = ' AND view_date BETWEEN ? AND ?';
                    $bindings[] = $startDate;
                    $bindings[] = $endDate;
                }

                $biomeClause = '';
                if ($biome !== null) {
                    $biomeClause = ' AND biome = ?';
                    $bindings[] = $biome;
                }

                $bindings[] = $bounds['west'];
                $bindings[] = $cellSize;
                $bindings[] = $bounds['south'];
                $bindings[] = $cellSize;
                $bindings[] = $clusterLimit;

                $sql = <<<SQL
                    WITH filtered AS (
                        SELECT longitude, latitude, viewed_at, view_date, biome, municipio
                        FROM {$table}
                        WHERE geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)
                          AND ST_Intersects(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)){$dateClause}{$biomeClause}
                    )
                    SELECT
                        ROUND(AVG(longitude)::numeric, 6) AS longitude,
                        ROUND(AVG(latitude)::numeric, 6) AS latitude,
                        COUNT(*) AS total,
                        MAX(viewed_at) AS latest_viewed_at,
                        MIN(view_date) AS first_view_date,
                        MAX(view_date) AS last_view_date,
                        MIN(NULLIF(biome, '')) AS biome,
                        MIN(NULLIF(municipio, '')) AS municipio
                    FROM (
                        SELECT *,
                            FLOOR((longitude - ?) / ?) AS grid_x,
                            FLOOR((latitude - ?) / ?) AS grid_y
                        FROM filtered
                    ) clustered
                    GROUP BY grid_x, grid_y
                    ORDER BY total DESC, latest_viewed_at DESC
                    LIMIT ?
                SQL;

                $rows = collect(DB::connection('pgsql')->select($sql, $bindings));

                return [
                    'data' => $rows->map(fn ($row) => [
                        'longitude' => (float) $row->longitude,
                        'latitude' => (float) $row->latitude,
                        'total' => (int) $row->total,
                        'latest_viewed_at' => (string) $row->latest_viewed_at,
                        'first_view_date' => (string) $row->first_view_date,
                        'last_view_date' => (string) $row->last_view_date,
                        'biome' => $this->sanitizeText($row->biome),
                        'municipio' => $this->sanitizeText($row->municipio),
                        'layer' => $layer,
                    ])->values()->all(),
                    'meta' => [
                        'cluster_cell_degrees' => $cellSize,
                        'layer' => $layer,
                        'generated_at' => now()->toIso8601String(),
                    ],
                ];
            });
        });

        return response()->json($payload);
    }

    private function applyStatementTimeout(): void
    {
        DB::connection('pgsql')->statement('SET LOCAL statement_timeout = ' . self::PG_STATEMENT_TIMEOUT_MS);
    }

    private function baseSpatialQuery(string $table, array $bounds)
    {
        return DB::connection('pgsql')
            ->table($table)
            ->whereRaw(
                'geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
                [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']]
            )
            ->whereRaw(
                'ST_Intersects(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))',
                [$bounds['west'], $bounds['south'], $bounds['east'], $bounds['north']]
            );
    }

    private function resolveBounds(string $bbox): ?array
    {
        if ($bbox === '') {
            return self::BRAZIL_BOUNDS;
        }

        $parts = array_map('trim', explode(',', $bbox));
        if (count($parts) !== 4) {
            return self::BRAZIL_BOUNDS;
        }

        [$west, $south, $east, $north] = array_map(static fn ($value) => is_numeric($value) ? (float) $value : null, $parts);

        if ($west === null || $south === null || $east === null || $north === null) {
            return self::BRAZIL_BOUNDS;
        }

        if ($west >= $east || $south >= $north) {
            return self::BRAZIL_BOUNDS;
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

    private function toFeature(object $row, string $layer): array
    {
        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $row->longitude, (float) $row->latitude],
            ],
            'properties' => $this->toPointPayload($row, $layer),
        ];
    }

    private function toPointPayload(object $row, string $layer): array
    {
        return [
            'source_id' => (int) $row->source_id,
            'source_layer' => isset($row->source_layer) ? $this->sanitizeText($row->source_layer) : 'current',
            'view_date' => (string) $row->view_date,
            'viewed_at' => (string) $row->viewed_at,
            'satelite' => $this->sanitizeText($row->satelite),
            'municipio' => $this->sanitizeText($row->municipio),
            'biome' => $this->sanitizeText($row->biome),
            'uf' => $this->sanitizeText($row->uf ?? null),
            'latitude' => (float) $row->latitude,
            'longitude' => (float) $row->longitude,
            'tipo_alerta' => 'queimada',
            'data' => (string) $row->view_date,
            'layer' => $layer,
        ];
    }

    private function validateDateSpan(ValidationValidator $validator, string $startDate, string $endDate): void
    {
        if ($startDate === '' || $endDate === '') {
            return;
        }

        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end = CarbonImmutable::createFromFormat('Y-m-d', $endDate)->startOfDay();
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

    private function meta(int $page, int $limit, int $total, string $source, array $extra = []): array
    {
        $pages = max(1, (int) ceil($total / max(1, $limit)));

        return array_merge([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => $pages,
            'has_more' => $page < $pages,
            'source' => $source,
            'generated_at' => now()->toIso8601String(),
        ], $extra);
    }

    private function clusterCellSizeForZoom(int $zoom): float
    {
        return match (true) {
            $zoom <= 4 => 2.5,
            $zoom <= 6 => 1.25,
            $zoom <= 8 => 0.45,
            $zoom <= 10 => 0.18,
            default => 0.08,
        };
    }

    private function sanitizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, 120);
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
        return 'focos:' . $prefix . ':' . hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
