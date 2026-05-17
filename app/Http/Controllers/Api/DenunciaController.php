<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDenunciaRequest;
use App\Models\Denuncia;
use App\Models\DenunciaConfirmacao;
use App\Models\LgpdConsent;
use App\Services\Denuncias\GeocodingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DenunciaController extends Controller
{
    public function __construct(
        private readonly GeocodingService $geocoding,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page'      => ['nullable', 'integer', 'min:1', 'max:500'],
                'per_page'  => ['nullable', 'integer', 'min:1', 'max:200'],
                'categoria' => ['nullable', 'string', 'in:' . implode(',', array_keys(config('denuncias.categories', [])))],
                'estado'    => ['nullable', 'string', 'max:2'],
                'status'    => ['nullable', 'string', 'in:' . implode(',', array_keys(config('denuncias.statuses', [])))],
                'periodo'   => ['nullable', 'string', 'in:7d,30d,90d'],
                'q'         => ['nullable', 'string', 'max:120'],
                'cidade'    => ['nullable', 'string', 'max:120'],
                'bairro'    => ['nullable', 'string', 'max:160'],
            ]);

            $page = (int) ($validated['page'] ?? 1);
            $perPage = (int) ($validated['per_page'] ?? 24);

            $query = Denuncia::query()
                ->filtradas($validated)
                ->orderByDesc('created_at')
                ->orderByDesc('confirmations_count');

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $items = collect($paginator->items())->map(fn (Denuncia $denuncia) => $this->transform($denuncia))->values()->all();

            return response()->json([
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'analytics' => $this->analytics($validated),
            ]);
        } catch (Throwable $exception) {
            Log::error('Falha ao listar denúncias.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Painel de denúncias temporariamente em recuperação.',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 0,
                    'total' => 0,
                ],
                'analytics' => $this->emptyAnalytics(),
            ], 200);
        }
    }

    public function show(Denuncia $denuncia): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->transform($denuncia->loadCount('confirmacoes')),
                'related' => collect(Denuncia::query()
                    ->where('id', '<>', $denuncia->id)
                    ->where('categoria', $denuncia->categoria)
                    ->latest()
                    ->limit(6)
                    ->get())
                    ->map(fn (Denuncia $item) => $this->transform($item))
                    ->values()
                    ->all(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Falha ao carregar denúncia.', [
                'denuncia_id' => $denuncia->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível carregar a denúncia agora.',
                'data' => $this->transform($denuncia),
                'related' => [],
            ], 200);
        }
    }

    public function store(StoreDenunciaRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $imagePaths = [];

            $resolvedCoordinates = $this->geocoding->resolve(
                (string) $data['estado'],
                (string) $data['cidade'],
                (string) ($data['bairro'] ?? ''),
            );

            if ($resolvedCoordinates === null) {
                throw ValidationException::withMessages([
                    'bairro' => 'Não foi possível localizar a cidade/bairro selecionados agora. Tente novamente em instantes.',
                ]);
            }

            foreach ($request->file('imagens', []) as $file) {
                $imagePaths[] = $file->storePublicly(
                    'denuncias/' . now()->format('Y/m'),
                    'public'
                );
            }

            $supportsMultipleImages = Schema::connection('pgsql')->hasColumn('denuncias', 'imagens');

            $denuncia = DB::transaction(function () use ($data, $imagePaths, $resolvedCoordinates, $supportsMultipleImages): Denuncia {
                $payload = [
                    'titulo'              => strip_tags((string) $data['titulo']),
                    'descricao'           => strip_tags((string) $data['descricao']),
                    'categoria'           => (string) $data['categoria'],
                    'latitude'            => (float) $resolvedCoordinates['latitude'],
                    'longitude'           => (float) $resolvedCoordinates['longitude'],
                    'estado'              => strtoupper((string) $data['estado']),
                    'cidade'              => strip_tags((string) $data['cidade']),
                    'bairro'              => strip_tags((string) $data['bairro']),
                    'endereco_aproximado' => isset($data['endereco_aproximado']) ? strip_tags((string) $data['endereco_aproximado']) : null,
                    'imagem'              => $imagePaths[0] ?? null,
                    'status'              => 'publica',
                    'confirmations_count' => 0,
                    'lgpd_consent_at'     => now(),
                    'lgpd_consent_version'=> (string) ($data['lgpd_policy_version'] ?? config('lgpd.policy_version')),
                ];

                if ($supportsMultipleImages) {
                    $payload['imagens'] = $imagePaths;
                }

                return Denuncia::create($payload);
            });

            LgpdConsent::query()->create([
                'purpose' => 'denuncia_publicacao',
                'policy_version' => (string) ($data['lgpd_policy_version'] ?? config('lgpd.policy_version')),
                'granted' => true,
                'context' => [
                    'denuncia_id' => $denuncia->id,
                    'channel' => 'denuncias_form',
                ],
                'ip_hash' => hash_hmac('sha256', (string) ($request->ip() ?? 'unknown'), (string) config('app.key')),
                'user_agent_hash' => hash_hmac('sha256', substr((string) ($request->userAgent() ?? ''), 0, 160), (string) config('app.key')),
                'consented_at' => now(),
            ]);

            return response()->json([
                'message' => 'Denúncia registrada com sucesso.',
                'data' => $this->transform($denuncia),
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Falha ao registrar denúncia.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Não foi possível registrar a denúncia no momento. Tente novamente em instantes.',
            ], 500);
        }
    }

    public function confirm(Request $request, Denuncia $denuncia): JsonResponse
    {
        $request->validate([
            'confirmar' => ['sometimes', 'boolean'],
        ]);

        $ipHash = hash_hmac('sha256', (string) ($request->ip() ?? 'unknown'), (string) config('app.key'));
        $uaHash = hash_hmac('sha256', substr((string) ($request->userAgent() ?? ''), 0, 160), (string) config('app.key'));

        try {
            $result = DB::transaction(function () use ($denuncia, $ipHash, $uaHash): array {
                DenunciaConfirmacao::query()->create([
                    'denuncia_id' => $denuncia->id,
                    'ip_hash' => $ipHash,
                    'user_agent_hash' => $uaHash,
                ]);

                $denuncia->increment('confirmations_count');
                $denuncia->refresh();

                return [
                    'count' => $denuncia->confirmations_count,
                    'already_confirmed' => false,
                ];
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23505') {
                throw $exception;
            }

            return response()->json([
                'message' => 'Você já confirmou esta ocorrência.',
                'data' => $this->transform($denuncia->fresh()->loadCount('confirmacoes')),
            ], 409);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Você já confirmou esta ocorrência.',
                'errors' => $exception->errors(),
                'data' => $this->transform($denuncia->fresh()->loadCount('confirmacoes')),
            ], 409);
        }

        return response()->json([
            'message' => 'Confirmação comunitária registrada.',
            'data' => $this->transform($denuncia),
            'confirmations' => $result['count'],
        ]);
    }

    private function transform(Denuncia $denuncia): array
    {
        $categoryMeta = $denuncia->categoryMeta();

        return [
            'id'                  => $denuncia->id,
            'titulo'              => $denuncia->titulo,
            'descricao'           => $denuncia->descricao,
            'categoria'           => $denuncia->categoria,
            'categoria_label'     => $categoryMeta['label'] ?? $denuncia->categoria,
            'categoria_icon'      => $categoryMeta['icon'] ?? '📍',
            'categoria_color'     => $categoryMeta['color'] ?? '#3DFF9A',
            'estado'              => $denuncia->estado,
            'cidade'              => $denuncia->cidade,
            'bairro'              => $denuncia->bairro,
            'endereco_aproximado' => $denuncia->endereco_aproximado,
            'regiao_aproximada'   => $denuncia->approximateRegion(),
            // Privacy-offset coordinates — exact position never exposed publicly
            'latitude'            => $denuncia->publicLatitude(),
            'longitude'           => $denuncia->publicLongitude(),
            'confirmations_count' => $denuncia->confirmations_count,
            'imagem_url'          => $denuncia->imageUrl(),
            'imagens_urls'        => $denuncia->imageUrls(),
            'created_at'          => optional($denuncia->created_at)?->toIso8601String(),
            'created_at_human'    => optional($denuncia->created_at)?->diffForHumans(),
            'preview'             => Str::limit($denuncia->descricao, 160),
        ];
    }

    private function emptyAnalytics(): array
    {
        return [
            'total'              => 0,
            'total_denuncias'    => 0,
            'states_count'       => 0,
            'estados_ativos'     => 0,
            'confirmations_total'=> 0,
            'total_confirmacoes' => 0,
            'by_category'        => [],
            'by_state'           => [],
            'por_categoria'      => [],
            'por_estado'         => [],
        ];
    }

    private function analytics(array $filters = []): array
    {
        $base = Denuncia::query()->filtradas($filters);
        $total = (clone $base)->count();
        $confirmationsTotal = (int) ((clone $base)->sum('confirmations_count') ?? 0);

        $byCategory = (clone $base)
            ->selectRaw('categoria, COUNT(*) as total')
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'categoria' => $row->categoria,
                'label'     => config('denuncias.categories.' . $row->categoria . '.label', $row->categoria),
                'total'     => (int) $row->total,
                'color'     => config('denuncias.categories.' . $row->categoria . '.color', '#3DFF9A'),
            ])
            ->values()
            ->all();

        $byState = (clone $base)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'estado' => $row->estado,
                'total'  => (int) $row->total,
            ])
            ->values()
            ->all();

        return [
            'total'               => $total,
            'total_denuncias'     => $total,
            'states_count'        => count($byState),
            'estados_ativos'      => count($byState),
            'confirmations_total' => $confirmationsTotal,
            'total_confirmacoes'  => $confirmationsTotal,
            'by_category'         => $byCategory,
            'by_state'            => $byState,
            'por_categoria'       => $byCategory,
            'por_estado'          => $byState,
        ];
    }
}