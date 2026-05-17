<?php

namespace App\Services\Denuncias;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * @return array{latitude:float,longitude:float,source:string}|null
     */
    public function resolve(string $estado, string $cidade, ?string $bairro = null): ?array
    {
        $estado = strtoupper(trim($estado));
        $cidade = trim($cidade);
        $bairro = trim((string) $bairro);

        $cacheKey = 'denuncias:geo:v2:' . hash('xxh3', implode('|', [$estado, mb_strtolower($cidade), mb_strtolower($bairro)]));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($estado, $cidade, $bairro): ?array {
            $queries = [];

            if ($bairro !== '') {
                $queries[] = "{$bairro}, {$cidade}, {$estado}, Brasil";
            }

            $queries[] = "{$cidade}, {$estado}, Brasil";

            foreach ($queries as $query) {
                $result = $this->geocode($query);
                if ($result !== null) {
                    return $result;
                }
            }

            return null;
        });
    }

    /**
     * @return array{latitude:float,longitude:float,source:string}|null
     */
    private function geocode(string $query): ?array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => config('app.name', 'BoitaTech') . '/1.0 (contato@boitatech.local)',
            ])
            ->get(self::NOMINATIM_URL, [
                'q' => $query,
                'format' => 'jsonv2',
                'countrycodes' => 'br',
                'addressdetails' => 1,
                'limit' => 1,
            ]);

        if (! $response->ok()) {
            return null;
        }

        $first = collect($response->json())->first();
        if (! is_array($first)) {
            return null;
        }

        $latitude = isset($first['lat']) ? (float) $first['lat'] : null;
        $longitude = isset($first['lon']) ? (float) $first['lon'] : null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        // Hard safety bounds Brasil
        if ($longitude < -74.8 || $longitude > -33.0 || $latitude < -34.9 || $latitude > 6.4) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => 'nominatim',
        ];
    }
}
