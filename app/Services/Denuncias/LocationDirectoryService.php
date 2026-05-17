<?php

namespace App\Services\Denuncias;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LocationDirectoryService
{
    private const IBGE_BASE = 'https://servicodados.ibge.gov.br/api/v1/localidades';

    /**
     * @return array<int, array{uf:string,nome:string}>
     */
    public function getStates(): array
    {
        return Cache::remember('denuncias:loc:states:v1', now()->addDays(7), function (): array {
            $response = Http::timeout(12)->acceptJson()->get(self::IBGE_BASE . '/estados', [
                'orderBy' => 'nome',
            ]);

            if (! $response->ok()) {
                return [];
            }

            return collect($response->json())
                ->map(fn (array $row): array => [
                    'uf' => (string) ($row['sigla'] ?? ''),
                    'nome' => (string) ($row['nome'] ?? ''),
                ])
                ->filter(fn (array $row): bool => $row['uf'] !== '' && $row['nome'] !== '')
                ->values()
                ->all();
        });
    }

    /**
     * @return array<int, array{id:int,nome:string}>
     */
    public function getCitiesByUf(string $uf): array
    {
        $uf = strtoupper(trim($uf));

        return Cache::remember("denuncias:loc:cities:{$uf}:v1", now()->addDays(2), function () use ($uf): array {
            $response = Http::timeout(12)->acceptJson()->get(self::IBGE_BASE . "/estados/{$uf}/municipios", [
                'orderBy' => 'nome',
            ]);

            if (! $response->ok()) {
                return [];
            }

            return collect($response->json())
                ->map(fn (array $row): array => [
                    'id' => (int) ($row['id'] ?? 0),
                    'nome' => (string) ($row['nome'] ?? ''),
                ])
                ->filter(fn (array $row): bool => $row['id'] > 0 && $row['nome'] !== '')
                ->values()
                ->all();
        });
    }

    /**
     * Usa distritos do IBGE como aproximação confiável de bairros administrativos.
     *
     * @return array<int, array{nome:string}>
     */
    public function getNeighborhoodsByCityId(int $cityId): array
    {
        return Cache::remember("denuncias:loc:neighborhoods:{$cityId}:v1", now()->addHours(18), function () use ($cityId): array {
            $response = Http::timeout(12)->acceptJson()->get(self::IBGE_BASE . "/municipios/{$cityId}/distritos", [
                'orderBy' => 'nome',
            ]);

            if (! $response->ok()) {
                return [['nome' => 'Centro']];
            }

            $items = collect($response->json())
                ->map(fn (array $row): array => [
                    'nome' => (string) ($row['nome'] ?? ''),
                ])
                ->filter(fn (array $row): bool => $row['nome'] !== '')
                ->unique('nome')
                ->values();

            if ($items->isEmpty()) {
                return [['nome' => 'Centro']];
            }

            return $items->all();
        });
    }
}
