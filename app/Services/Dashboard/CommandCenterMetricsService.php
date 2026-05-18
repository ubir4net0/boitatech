<?php

namespace App\Services\Dashboard;

use App\DTOs\Dashboard\OperationalSnapshotDTO;
use App\Repositories\Dashboard\OperationalMetricsRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CommandCenterMetricsService
{
    public function __construct(
        private readonly OperationalMetricsRepository $repository,
    ) {
    }

    public function snapshot(): OperationalSnapshotDTO
    {
        $payload = Cache::remember('dashboard:command-center:v1', 30, function (): array {
            try {
                $category = $this->repository->denunciasByCategory();
                $states = $this->repository->denunciasByState();
                $biomes = $this->repository->alertasByBiome();

                return [
                    'generated_at' => now()->toIso8601String(),
                    'cards' => $this->repository->cards(),
                    'charts' => [
                        'denuncias_por_categoria' => $category === []
                            ? [['label' => 'Sem dados', 'value' => 0]]
                            : $category,
                        'denuncias_por_estado' => $states === []
                            ? [['label' => 'Sem dados', 'value' => 0]]
                            : $states,
                        'alertas_por_bioma' => $biomes === []
                            ? [['label' => 'Sem dados', 'value' => 0]]
                            : $biomes,
                    ],
                    'feed' => $this->repository->activityFeed(),
                    'map' => $this->repository->mapPoints(),
                ];
            } catch (Throwable) {
                return [
                    'generated_at' => now()->toIso8601String(),
                    'cards' => [
                        'focos' => ['total' => 0, 'delta' => 0, 'subtitle' => 'Sem dados operacionais no momento'],
                        'denuncias' => ['total' => 0, 'subtitle' => 'Sem dados operacionais no momento'],
                        'ecopontos' => ['total' => 0, 'subtitle' => 'Sem dados operacionais no momento'],
                    ],
                    'charts' => [
                        'denuncias_por_categoria' => [['label' => 'Sem dados', 'value' => 0]],
                        'denuncias_por_estado' => [['label' => 'Sem dados', 'value' => 0]],
                        'alertas_por_bioma' => [['label' => 'Sem dados', 'value' => 0]],
                    ],
                    'feed' => [],
                    'map' => [
                        'focos' => [],
                        'denuncias' => [],
                    ],
                ];
            }
        });

        return new OperationalSnapshotDTO(
            generatedAt: (string) ($payload['generated_at'] ?? now()->toIso8601String()),
            cards: (array) ($payload['cards'] ?? []),
            charts: (array) ($payload['charts'] ?? []),
            feed: (array) ($payload['feed'] ?? []),
            map: (array) ($payload['map'] ?? []),
        );
    }
}
