<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class TerraBrasilisWfsSchema
{
    /**
     * @return array<int, string>
     */
    public function fieldNames(string $layer): array
    {
        $response = Http::withOptions([
            'verify' => config('services.terrabrasilis.ssl_verify', true),
        ])->timeout(25)
            ->retry(1, 300)
            ->accept('application/xml,text/xml,application/json')
            ->get((string) config('services.terrabrasilis.wfs_url', 'https://terrabrasilis.dpi.inpe.br/geoserver/ows'), [
                'service' => 'WFS',
                'version' => '2.0.0',
                'request' => 'DescribeFeatureType',
                'typeName' => $layer,
            ]);

        $response->throw();

        $body = trim($response->body());
        if ($body === '') {
            return [];
        }

        $json = json_decode($body, true);
        if (is_array($json)) {
            $names = [];
            array_walk_recursive($json, static function ($value, $key) use (&$names): void {
                if ($key === 'name' && is_string($value)) {
                    $names[] = $value;
                }
            });

            return array_values(array_unique($names));
        }

        preg_match_all('/<[^>]*element[^>]*name="([^"]+)"/i', $body, $matches);
        if (! isset($matches[1]) || ! is_array($matches[1])) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', $matches[1]))));
    }

    /**
     * @param array<int, string> $candidates
     */
    public function resolveField(string $layer, array $candidates): ?string
    {
        $fields = array_map('strtolower', $this->fieldNames($layer));

        foreach ($candidates as $candidate) {
            if (in_array(strtolower($candidate), $fields, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
