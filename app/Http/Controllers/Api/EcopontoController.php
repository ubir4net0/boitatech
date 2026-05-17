<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ecoponto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcopontoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $this->validateFilters($request);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = min(30, max(1, (int) ($validated['per_page'] ?? 12)));

        $paginator = Ecoponto::query()
            ->filtrados($validated)
            ->orderBy('nome')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Ecoponto $ponto) => $this->transform($ponto))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'facets' => $this->facets($validated),
            'summary' => [
                'active_total' => (clone Ecoponto::query()->filtrados($validated))->count(),
                'types_total' => count(array_filter((array) config('ecopontos.categories', []))),
                'city' => config('ecopontos.city.name', 'Manaus'),
            ],
        ]);
    }

    public function map(Request $request): JsonResponse
    {
        $validated = $this->validateFilters($request, true);
        $zoom = (int) ($validated['zoom'] ?? 12);
        $limit = min(1800, max(100, (int) ($validated['limit'] ?? 700)));

        $query = Ecoponto::query()
            ->filtrados($validated)
            ->select(['id', 'nome', 'tipo_coleta', 'bairro', 'cidade', 'zona', 'latitude', 'longitude', 'imagem', 'materiais_aceitos', 'horario_funcionamento', 'endereco']);

        if (
            isset($validated['south'], $validated['north'], $validated['west'], $validated['east'])
            && is_numeric($validated['south'])
            && is_numeric($validated['north'])
            && is_numeric($validated['west'])
            && is_numeric($validated['east'])
        ) {
            $south = (float) $validated['south'];
            $north = (float) $validated['north'];
            $west = (float) $validated['west'];
            $east = (float) $validated['east'];

            $query
                ->whereBetween('latitude', [min($south, $north), max($south, $north)])
                ->whereBetween('longitude', [min($west, $east), max($west, $east)]);
        }

        $points = $query->limit($limit)->get();

        if ($zoom >= 14) {
            return response()->json([
                'mode' => 'points',
                'data' => $points->map(fn (Ecoponto $ponto) => $this->transform($ponto))->values()->all(),
            ]);
        }

        $factor = $zoom < 11 ? 20 : ($zoom < 13 ? 45 : 90);

        $clusters = $points
            ->groupBy(function (Ecoponto $ponto) use ($factor): string {
                return floor($ponto->latitude * $factor) . '|' . floor($ponto->longitude * $factor);
            })
            ->map(function ($group) {
                $count = $group->count();
                $first = $group->first();

                if ($count === 1 && $first instanceof Ecoponto) {
                    return [
                        'type' => 'point',
                        'data' => $this->transform($first),
                    ];
                }

                return [
                    'type' => 'cluster',
                    'latitude' => round((float) $group->avg('latitude'), 6),
                    'longitude' => round((float) $group->avg('longitude'), 6),
                    'count' => $count,
                    'types' => $group->groupBy('tipo_coleta')->map(fn ($items) => $items->count())->all(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'mode' => 'clusters',
            'data' => $clusters,
        ]);
    }

    public function show(Ecoponto $ecoponto): JsonResponse
    {
        return response()->json([
            'data' => $this->transform($ecoponto),
            'related' => Ecoponto::query()
                ->where('id', '<>', $ecoponto->id)
                ->where(function ($q) use ($ecoponto): void {
                    $q->where('tipo_coleta', $ecoponto->tipo_coleta)
                        ->orWhere('bairro', $ecoponto->bairro);
                })
                ->orderBy('nome')
                ->limit(8)
                ->get()
                ->map(fn (Ecoponto $item) => $this->transform($item))
                ->values()
                ->all(),
        ]);
    }

    private function validateFilters(Request $request, bool $mapMode = false): array
    {
        $rules = [
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
            'tipo_coleta' => ['nullable', 'string', 'in:' . implode(',', array_keys(config('ecopontos.categories', [])))],
            'bairro' => ['nullable', 'string', 'max:160'],
            'zona' => ['nullable', 'string', 'max:40'],
            'material' => ['nullable', 'string', 'max:40'],
        ];

        if ($mapMode) {
            $rules = [
                ...$rules,
                'south' => ['nullable', 'numeric', 'between:-90,90'],
                'west' => ['nullable', 'numeric', 'between:-180,180'],
                'north' => ['nullable', 'numeric', 'between:-90,90'],
                'east' => ['nullable', 'numeric', 'between:-180,180'],
                'zoom' => ['nullable', 'integer', 'min:4', 'max:20'],
                'limit' => ['nullable', 'integer', 'min:100', 'max:1800'],
            ];
        }

        $validated = $request->validate($rules);

        if (! empty($validated['material'])) {
            $aliases = (array) config('ecopontos.material_aliases', []);
            $normalized = mb_strtolower(trim((string) $validated['material']));
            $validated['material'] = $aliases[$normalized] ?? $normalized;
        }

        return $validated;
    }

    private function facets(array $filters = []): array
    {
        $base = Ecoponto::query()->filtrados($filters);

        return [
            'total' => (clone $base)->count(),
            'zonas' => (clone $base)->selectRaw('zona, COUNT(*) as total')->groupBy('zona')->orderBy('zona')->get()->map(fn ($r) => ['zona' => $r->zona, 'total' => (int) $r->total])->values()->all(),
            'bairros' => (clone $base)->selectRaw('bairro, COUNT(*) as total')->groupBy('bairro')->orderBy('bairro')->limit(50)->get()->map(fn ($r) => ['bairro' => $r->bairro, 'total' => (int) $r->total])->values()->all(),
            'types' => (clone $base)->selectRaw('tipo_coleta, COUNT(*) as total')->groupBy('tipo_coleta')->orderBy('tipo_coleta')->get()->map(fn ($r) => ['tipo_coleta' => $r->tipo_coleta, 'total' => (int) $r->total])->values()->all(),
        ];
    }

    private function transform(Ecoponto $ponto): array
    {
        $meta = $ponto->categoryMeta();

        return [
            'id' => $ponto->id,
            'nome' => $ponto->nome,
            'descricao' => $ponto->descricao,
            'tipo_coleta' => $ponto->tipo_coleta,
            'tipo_label' => $meta['label'] ?? $ponto->tipo_coleta,
            'tipo_icon' => $meta['icon'] ?? '♻️',
            'tipo_color' => $meta['color'] ?? '#3DFF9A',
            'endereco' => $ponto->endereco,
            'bairro' => $ponto->bairro,
            'cidade' => $ponto->cidade,
            'zona' => $ponto->zona,
            'latitude' => $ponto->latitude,
            'longitude' => $ponto->longitude,
            'telefone' => $ponto->telefone,
            'horario_funcionamento' => $ponto->horario_funcionamento,
            'ativo' => $ponto->ativo,
            'materiais_aceitos' => array_values(array_filter((array) $ponto->materiais_aceitos)),
            'imagem' => $ponto->imagePath(),
            'imagem_url' => $ponto->imageUrl(),
            'imagens_urls' => $ponto->imageUrls(),
            'created_at' => optional($ponto->created_at)?->toIso8601String(),
        ];
    }
}
