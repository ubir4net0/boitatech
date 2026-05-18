<?php

namespace App\Repositories\Dashboard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalMetricsRepository
{
    public function cards(): array
    {
        $now = now();

        $focosTotal = 0;
        $focosRecent = 0;
        $focosPrev = 0;

        if ($this->hasTable('focos_current')) {
            $query = DB::connection('pgsql')->table('focos_current');
            $focosTotal = (int) (clone $query)->count();
            $focosRecent = (int) (clone $query)->where('viewed_at', '>=', $now->copy()->subDay())->count();
            $focosPrev = (int) (clone $query)
                ->whereBetween('viewed_at', [$now->copy()->subDays(2), $now->copy()->subDay()])
                ->count();
        }

        $denunciasTotal = 0;
        $denunciasRecent = 0;

        if ($this->hasTable('denuncias')) {
            $query = DB::connection('pgsql')->table('denuncias');
            $denunciasTotal = (int) (clone $query)->count();
            $denunciasRecent = (int) (clone $query)->where('created_at', '>=', $now->copy()->subDays(7))->count();
        }

        $ecopontosTotal = 0;
        $zonesCovered = 0;

        if ($this->hasTable('pontos_coleta')) {
            $query = DB::connection('pgsql')->table('pontos_coleta')->where('ativo', true);
            $ecopontosTotal = (int) (clone $query)->count();
            $zonesCovered = (int) (clone $query)->whereNotNull('zona')->distinct()->count('zona');
        }

        $delta = $focosRecent - $focosPrev;
        $coverage = (int) min(100, round(($zonesCovered / 6) * 100));

        return [
            'focos' => [
                'total' => $focosTotal,
                'delta' => $delta,
                'subtitle' => $delta >= 0 ? "+{$delta} vs. janela anterior" : "{$delta} vs. janela anterior",
            ],
            'denuncias' => [
                'total' => $denunciasTotal,
                'subtitle' => "{$denunciasRecent} nos últimos 7 dias",
            ],
            'ecopontos' => [
                'total' => $ecopontosTotal,
                'subtitle' => "Cobertura estimada {$coverage}% da cidade",
            ],
        ];
    }

    public function denunciasByCategory(): array
    {
        if (! $this->hasTable('denuncias')) {
            return [];
        }

        $raw = DB::connection('pgsql')->table('denuncias')
            ->selectRaw('categoria, COUNT(*) as total')
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->limit(7)
            ->get();

        $categories = (array) config('denuncias.categories', []);

        return $raw->map(function ($row) use ($categories): array {
            $slug = (string) ($row->categoria ?? 'sem-categoria');
            $meta = (array) ($categories[$slug] ?? []);

            return [
                'label' => (string) ($meta['label'] ?? ucfirst(str_replace('-', ' ', $slug))),
                'value' => (int) ($row->total ?? 0),
            ];
        })->values()->all();
    }

    public function denunciasByState(): array
    {
        if (! $this->hasTable('denuncias')) {
            return [];
        }

        return DB::connection('pgsql')->table('denuncias')
            ->selectRaw('estado, COUNT(*) as total')
            ->whereNotNull('estado')
            ->where('estado', '<>', '')
            ->groupBy('estado')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'label' => strtoupper((string) ($row->estado ?? 'N/D')),
                'value' => (int) ($row->total ?? 0),
            ])
            ->values()
            ->all();
    }

    public function alertasByBiome(): array
    {
        if (! $this->hasTable('focos_current')) {
            return [];
        }

        $biomes = ['Amazônia', 'Cerrado', 'Mata Atlântica', 'Caatinga', 'Pantanal', 'Pampa'];
        $results = [];

        foreach ($biomes as $biome) {
            $count = (int) DB::connection('pgsql')->table('focos_current')
                ->where('biome', $biome)
                ->count();

            $results[] = [
                'label' => $biome,
                'value' => $count,
            ];
        }

        return $results;
    }

    public function activityFeed(): array
    {
        $items = collect();

        if ($this->hasTable('focos_current')) {
            $focos = DB::connection('pgsql')->table('focos_current')
                ->select(['municipio', 'biome', 'viewed_at'])
                ->orderByDesc('viewed_at')
                ->limit(4)
                ->get()
                ->map(fn ($row): array => [
                    'icon' => '🔥',
                    'title' => 'Novo foco detectado',
                    'meta' => $this->safeText(trim(((string) ($row->municipio ?? 'Brasil')) . ' • ' . ((string) ($row->biome ?? 'Monitoramento ambiental'))), 82),
                    'at' => (string) ($row->viewed_at ?? now()->toIso8601String()),
                ]);

            $items = $items->merge($focos);
        }

        if ($this->hasTable('denuncias')) {
            $denuncias = DB::connection('pgsql')->table('denuncias')
                ->select(['titulo', 'cidade', 'estado', 'created_at'])
                ->orderByDesc('created_at')
                ->limit(4)
                ->get()
                ->map(fn ($row): array => [
                    'icon' => '📢',
                    'title' => 'Nova denúncia registrada',
                    'meta' => $this->safeText(trim(((string) ($row->cidade ?? 'N/D')) . ' • ' . ((string) ($row->estado ?? 'BR'))), 82),
                    'at' => (string) ($row->created_at ?? now()->toIso8601String()),
                ]);

            $items = $items->merge($denuncias);
        }

        if ($this->hasTable('pontos_coleta')) {
            $ecopontos = DB::connection('pgsql')->table('pontos_coleta')
                ->select(['nome', 'bairro', 'updated_at'])
                ->where('ativo', true)
                ->orderByDesc('updated_at')
                ->limit(2)
                ->get()
                ->map(fn ($row): array => [
                    'icon' => '♻️',
                    'title' => 'Ecoponto em operação',
                    'meta' => $this->safeText(trim(((string) ($row->nome ?? 'Ecoponto')) . ' • ' . ((string) ($row->bairro ?? 'Manaus'))), 82),
                    'at' => (string) ($row->updated_at ?? now()->toIso8601String()),
                ]);

            $items = $items->merge($ecopontos);
        }

        return $items
            ->sortByDesc(fn (array $item) => strtotime($item['at'] ?? now()->toIso8601String()))
            ->take(8)
            ->values()
            ->all();
    }

    public function mapPoints(): array
    {
        $focos = [];
        $denuncias = [];

        if ($this->hasTable('focos_current')) {
            $focos = DB::connection('pgsql')->table('focos_current')
                ->select(['latitude', 'longitude'])
                ->whereBetween('latitude', [-34, 6])
                ->whereBetween('longitude', [-74, -28])
                ->orderByDesc('viewed_at')
                ->limit(260)
                ->get()
                ->map(fn ($row): array => [
                    'lat' => (float) ($row->latitude ?? 0),
                    'lng' => (float) ($row->longitude ?? 0),
                ])
                ->values()
                ->all();
        }

        if ($this->hasTable('denuncias')) {
            $denuncias = DB::connection('pgsql')->table('denuncias')
                ->select(['latitude', 'longitude', 'categoria'])
                ->where('status', 'publica')
                ->orderByDesc('created_at')
                ->limit(120)
                ->get()
                ->map(fn ($row): array => [
                    'lat' => (float) ($row->latitude ?? 0),
                    'lng' => (float) ($row->longitude ?? 0),
                    'categoria' => $this->safeText((string) ($row->categoria ?? 'denuncia'), 42),
                ])
                ->values()
                ->all();
        }

        return [
            'focos' => $focos,
            'denuncias' => $denuncias,
        ];
    }

    private function hasTable(string $table): bool
    {
        return Schema::connection('pgsql')->hasTable($table);
    }

    private function safeText(string $value, int $maxLength = 120): string
    {
        $clean = trim(strip_tags($value));
        if ($clean === '') {
            return 'N/D';
        }

        return mb_substr($clean, 0, $maxLength);
    }
}
