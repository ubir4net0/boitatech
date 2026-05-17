<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Noticia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class NoticiaController extends Controller
{
    /**
     * @var array<int, string>
     */
    private array $validCategories = ['amazonia', 'queimadas', 'desmatamento', 'clima', 'biodiversidade', 'povos-indigenas', 'fiscalizacao', 'sustentabilidade', 'bioeconomia', 'monitoramento'];

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'page' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'per_page' => ['nullable', 'integer', 'min:6', 'max:24'],
            'category' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Parâmetros inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(6, min(24, (int) $request->integer('per_page', 12)));
        $category = trim((string) $request->query('category', ''));
        $source = trim((string) $request->query('source', ''));

        $cacheKey = 'boitanews:index:' . hash('xxh3', json_encode([$page, $perPage, $category, $source]));
        $ttl = (int) config('boitanews.cache_ttl_seconds.index', 120);

        $payload = Cache::remember($cacheKey, $ttl, function () use ($page, $perPage, $category, $source) {
            $query = Noticia::query()
                ->whereNotNull('imagem_path')
                ->where('imagem_path', '<>', '')
                ->when($category !== '', fn (Builder $q) => $q->where('categoria', $category))
                ->when($source !== '', fn (Builder $q) => $q->where('fonte', $source))
                ->orderByDesc('is_destaque')
                ->orderByDesc('publicado_em')
                ->orderByDesc('id');

            $pageData = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => collect($pageData->items())->map(fn ($row) => $this->transform($row))->values()->all(),
                'meta' => [
                    'current_page' => $pageData->currentPage(),
                    'last_page' => $pageData->lastPage(),
                    'per_page' => $pageData->perPage(),
                    'total' => $pageData->total(),
                ],
            ];
        });

        return response()->json([
            'data' => $payload['data'],
            'meta' => $payload['meta'],
        ]);
    }

    public function destaques(): JsonResponse
    {
        $ttl = (int) config('boitanews.cache_ttl_seconds.featured', 180);

        $payload = Cache::remember('boitanews:featured', $ttl, fn () => Noticia::query()
            ->where('is_destaque', true)
            ->whereNotNull('imagem_path')
            ->where('imagem_path', '<>', '')
            ->orderByDesc('publicado_em')
            ->limit(6)
            ->get()
            ->map(fn (Noticia $row) => $this->transform($row))
            ->values()
            ->all());

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function recentes(): JsonResponse
    {
        $ttl = (int) config('boitanews.cache_ttl_seconds.recent', 120);

        $payload = Cache::remember('boitanews:recent', $ttl, fn () => Noticia::query()
            ->whereNotNull('imagem_path')
            ->where('imagem_path', '<>', '')
            ->orderByDesc('publicado_em')
            ->limit(15)
            ->get()
            ->map(fn (Noticia $row) => $this->transform($row))
            ->values()
            ->all());

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function relevantes(): JsonResponse
    {
        $ttl = (int) config('boitanews.cache_ttl_seconds.recent', 120);

        $payload = Cache::remember('boitanews:relevant', $ttl, fn () => Noticia::query()
            ->whereIn('categoria', ['amazonia', 'desmatamento', 'queimadas', 'fiscalizacao', 'clima', 'biodiversidade', 'bioeconomia', 'monitoramento'])
            ->whereNotNull('imagem_path')
            ->where('imagem_path', '<>', '')
            ->orderByDesc('is_destaque')
            ->orderByDesc('publicado_em')
            ->limit(9)
            ->get()
            ->map(fn (Noticia $row) => $this->transform($row))
            ->values()
            ->all());

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function categorias(): JsonResponse
    {
        $ttl = (int) config('boitanews.cache_ttl_seconds.categories', 300);

        $payload = Cache::remember('boitanews:categories', $ttl, function () {
            $totals = Noticia::query()
                ->reorder()
                ->whereNotNull('categoria')
                ->where('categoria', '<>', '')
                ->selectRaw('categoria, COUNT(*) as total')
                ->groupBy('categoria')
                ->get()
                ->mapWithKeys(fn ($row) => [(string) $row->categoria => (int) $row->total])
                ->all();

            $order = $this->validCategories;

            return collect($order)->map(function ($category) use ($totals): array {
                $key = (string) $category;

                return [
                    'category' => $key,
                    'label' => $this->categoryLabel($key),
                    'total' => (int) ($totals[$key] ?? 0),
                ];
            })->values()->all();
        });

        return response()->json([
            'data' => $payload,
        ]);
    }

    private function transform(mixed $row): array
    {
        $displayTitle = trim((string) ($row->titulo ?? 'Sem título'));
        $displayExcerpt = trim((string) ($row->resumo ?? 'Resumo não disponível.'));
        $imageUrl = $this->resolveLocalImageUrl((string) ($row->imagem_path ?? $row->imagem_url ?? ''));
        $hasImage = $imageUrl !== null;
        $publishedAt = $row->publicado_em;
        $sourceDisplayName = trim((string) ($row->fonte ?? 'Fonte oficial'));

        return [
            'id' => (int) $row->id,
            'title' => $displayTitle,
            'excerpt' => $displayExcerpt,
            'image_path' => (string) ($row->imagem_path ?? ''),
            'image_url' => $imageUrl,
            'has_image' => $hasImage,
            'image_is_fallback' => false,
            'source' => [
                'key' => strtolower(str_replace(' ', '-', $sourceDisplayName)),
                'name' => $sourceDisplayName,
                'trust_score' => 90,
            ],
            'url' => (string) ($row->link_original ?? '#'),
            'category' => $row->categoria,
            'category_label' => $this->categoryLabel((string) ($row->categoria ?? '')),
            'published_at' => $publishedAt,
            'quality_score' => 92,
            'is_featured' => (bool) ($row->is_destaque ?? false),
            'curation_score' => 90,
            'nlp_probability' => 0.95,
            'publication_layer' => 'strict',
            'ranking_score' => 95,
        ];
    }

    private function resolveLocalImageUrl(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'noticias/')) {
            return asset('storage/' . $trimmed);
        }

        if (str_starts_with($trimmed, '/storage/')) {
            return url($trimmed);
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($trimmed);
            if (! is_array($parsed)) {
                return null;
            }

            $host = strtolower((string) ($parsed['host'] ?? ''));
            $path = (string) ($parsed['path'] ?? '');
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

            if ($host !== '' && $appHost !== '' && $host === $appHost && str_starts_with($path, '/storage/')) {
                return $trimmed;
            }
        }

        return null;
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'amazonia' => 'Amazônia',
            'queimadas' => 'Queimadas',
            'desmatamento' => 'Desmatamento',
            'clima' => 'Clima',
            'biodiversidade' => 'Biodiversidade',
            'povos-indigenas' => 'Povos Indígenas',
            'fiscalizacao' => 'Fiscalização',
            'sustentabilidade' => 'Sustentabilidade',
            'bioeconomia' => 'Bioeconomia',
            'monitoramento' => 'Monitoramento',
            default => ucfirst(str_replace('-', ' ', $category !== '' ? $category : 'Geral')),
        };
    }

}
