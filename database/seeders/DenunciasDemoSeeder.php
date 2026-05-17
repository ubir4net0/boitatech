<?php

namespace Database\Seeders;

use App\Models\Denuncia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DenunciasDemoSeeder extends Seeder
{
    private const IMAGE_WIDTH = 1600;
    private const IMAGE_HEIGHT = 900;

    public function run(): void
    {
        $disk = Storage::disk('public');

        // 1) Limpeza completa das imagens demonstrativas
        $disk->deleteDirectory('denuncias');
        $disk->makeDirectory('denuncias');

        $blueprints = $this->blueprints();
        $prepared = [];

        // 2) Pré-download das imagens com fallback local para evitar URLs quebradas
        foreach ($blueprints as $index => $blueprint) {
            $prepared[] = [
                ...$blueprint,
                'imagens' => $this->prepareImages($blueprint, $index + 1),
            ];
        }

        // 3) Limpeza de tabelas + reset de sequência + inserção consistente
        DB::connection('pgsql')->transaction(function () use ($prepared): void {
            $this->truncateDemoTables();

            foreach ($prepared as $item) {
                $createdAt = CarbonImmutable::parse($item['created_at']);
                $titulo = $this->sanitize($item['titulo']);
                $descricao = $this->sanitize($item['descricao']);
                $bairro = $this->sanitize($item['bairro']);
                $endereco = $this->sanitize($item['endereco_aproximado']);

                $denuncia = Denuncia::create([
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'categoria' => $item['categoria'],
                    'latitude' => (float) $item['latitude'],
                    'longitude' => (float) $item['longitude'],
                    'estado' => $item['estado'],
                    'cidade' => $this->sanitize($item['cidade']),
                    'bairro' => $bairro,
                    'endereco_aproximado' => $endereco,
                    'imagem' => $item['imagens'][0] ?? null,
                    'imagens' => $item['imagens'],
                    'status' => 'publica',
                    'confirmations_count' => (int) $item['confirmations_count'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $this->seedConfirmacoes($denuncia->id, (int) $item['confirmations_count'], $createdAt);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blueprints(): array
    {
        $now = CarbonImmutable::now();

        return [
            [
                'titulo' => 'Fumaça intensa em área de mata próxima ao Distrito Industrial',
                'descricao' => 'Desde o início da tarde, moradores relatam fumaça densa cobrindo o bairro e forte cheiro de queimado. O foco parece avançar na borda da vegetação, com cinzas chegando às casas e reduzindo a visibilidade nas vias locais.',
                'categoria' => 'queimadas',
                'estado' => 'AM',
                'cidade' => 'Manaus',
                'bairro' => 'Distrito Industrial II',
                'endereco_aproximado' => 'Rua Jutaí, próximo ao acesso da BR-319',
                'latitude' => -3.1514,
                'longitude' => -59.9725,
                'confirmations_count' => 17,
                'created_at' => $now->subDays(3)->setTime(16, 40, 0)->toDateTimeString(),
                'image_queries' => ['wildfire,forest,smoke', 'brazil,forest,fire'],
            ],
            [
                'titulo' => 'Supressão de vegetação com maquinário pesado em área periurbana',
                'descricao' => 'Comunidade registrou retirada acelerada de cobertura vegetal com tratores e correntões durante toda a manhã. Há clareiras recentes e acúmulo de troncos, com risco de ampliação da área desmatada nas próximas semanas.',
                'categoria' => 'desmatamento',
                'estado' => 'PA',
                'cidade' => 'Santarém',
                'bairro' => 'Área do Eixo Forte',
                'endereco_aproximado' => 'Estrada do Irurama, trecho de acesso rural',
                'latitude' => -2.4506,
                'longitude' => -54.7040,
                'confirmations_count' => 14,
                'created_at' => $now->subDays(7)->setTime(9, 15, 0)->toDateTimeString(),
                'image_queries' => ['deforestation,amazon,logging', 'forest,logging,bulldozer'],
            ],
            [
                'titulo' => 'Descarte de resíduos às margens do igarapé com odor forte',
                'descricao' => 'Foram observados sacos com resíduos misturados e líquidos escuros escorrendo para o curso d\'água após chuva. O local fica próximo a moradias e já apresenta proliferação de insetos e mau cheiro persistente.',
                'categoria' => 'descarte-irregular',
                'estado' => 'RO',
                'cidade' => 'Porto Velho',
                'bairro' => 'Nova Esperança',
                'endereco_aproximado' => 'Rua da Beira, próximo ao igarapé do bairro',
                'latitude' => -8.7305,
                'longitude' => -63.8975,
                'confirmations_count' => 11,
                'created_at' => $now->subDays(11)->setTime(18, 5, 0)->toDateTimeString(),
                'image_queries' => ['garbage,dump,environment', 'trash,illegal,dumping'],
            ],
            [
                'titulo' => 'Sinais de garimpo ilegal e assoreamento em curso d\'água local',
                'descricao' => 'Moradores notaram movimentação noturna de máquinas e aumento de turbidez no córrego. Há relatos de abertura de trilhas e remoção de solo em área sensível, com possível contaminação por rejeitos.',
                'categoria' => 'garimpo-ilegal',
                'estado' => 'MT',
                'cidade' => 'Sinop',
                'bairro' => 'Boa Esperança',
                'endereco_aproximado' => 'Estrada de acesso vicinal ao norte do município',
                'latitude' => -11.8794,
                'longitude' => -55.5032,
                'confirmations_count' => 19,
                'created_at' => $now->subDays(16)->setTime(6, 50, 0)->toDateTimeString(),
                'image_queries' => ['illegal,mining,river', 'mining,excavator,forest'],
            ],
            [
                'titulo' => 'Água escura e espumas recorrentes em trecho de canal urbano',
                'descricao' => 'Após o início da noite, o canal apresentou coloração escura e formação de espuma junto às galerias de drenagem. Moradores apontam descarte irregular de efluentes e risco à fauna local.',
                'categoria' => 'poluicao-ambiental',
                'estado' => 'SP',
                'cidade' => 'Cubatão',
                'bairro' => 'Vila Esperança',
                'endereco_aproximado' => 'Avenida Principal, trecho próximo ao canal',
                'latitude' => -23.8918,
                'longitude' => -46.4251,
                'confirmations_count' => 22,
                'created_at' => $now->subDays(22)->setTime(20, 20, 0)->toDateTimeString(),
                'image_queries' => ['pollution,river,water', 'industrial,pollution,smoke'],
            ],
            [
                'titulo' => 'Área protegida com ocupação irregular e supressão recente',
                'descricao' => 'Relato comunitário indica abertura de vias improvisadas, cercamento e retirada de vegetação em faixa de proteção ambiental. A atividade avançou nos últimos dias e já alterou a paisagem da encosta.',
                'categoria' => 'invasao-area-protegida',
                'estado' => 'BA',
                'cidade' => 'Salvador',
                'bairro' => 'São Cristóvão',
                'endereco_aproximado' => 'Rua de acesso à área de dunas e vegetação nativa',
                'latitude' => -12.9127,
                'longitude' => -38.3551,
                'confirmations_count' => 13,
                'created_at' => $now->subDays(28)->setTime(14, 10, 0)->toDateTimeString(),
                'image_queries' => ['protected,area,invasion', 'environmental,crime,land'],
            ],
        ];
    }

    private function truncateDemoTables(): void
    {
        $conn = DB::connection('pgsql');
        $tables = [];

        foreach (['denuncia_confirmacoes', 'denuncia_imagens', 'denuncias'] as $table) {
            if (Schema::connection('pgsql')->hasTable($table)) {
                $tables[] = '"' . $table . '"';
            }
        }

        if (empty($tables)) {
            return;
        }

        $conn->statement('TRUNCATE TABLE ' . implode(', ', $tables) . ' RESTART IDENTITY CASCADE');
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array<int, string>
     */
    private function prepareImages(array $blueprint, int $seedIndex): array
    {
        $paths = [];
        $queries = array_values(array_filter((array) ($blueprint['image_queries'] ?? [])));
        $slug = Str::slug((string) ($blueprint['cidade'] ?? 'demo'));

        foreach ($queries as $slot => $query) {
            $lock = abs((int) crc32($slug . '-' . $seedIndex . '-' . $slot));
            $queryPath = str_replace(' ', ',', trim((string) $query));
            $url = sprintf(
                'https://loremflickr.com/%d/%d/%s?lock=%d',
                self::IMAGE_WIDTH,
                self::IMAGE_HEIGHT,
                $queryPath,
                $lock,
            );

            $path = $this->downloadImageOrFallback($url, $slug, $seedIndex, $slot + 1, (string) $blueprint['categoria']);
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        if (empty($paths)) {
            $fallback = $this->makeFallbackImage($slug, $seedIndex, 1, (string) $blueprint['categoria']);
            $paths[] = $fallback;
        }

        return $paths;
    }

    private function downloadImageOrFallback(string $url, string $slug, int $seedIndex, int $slot, string $categoria): ?string
    {
        try {
            $response = Http::withoutVerifying()
                ->timeout(20)
                ->retry(2, 250, null, false)
                ->withHeaders([
                    'User-Agent' => 'BoitaTech Demo Seeder/1.0',
                    'Accept' => 'image/*,*/*;q=0.8',
                ])
                ->get($url);

            if (! $response->successful()) {
                return $this->makeFallbackImage($slug, $seedIndex, $slot, $categoria);
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if (! str_contains($contentType, 'image/')) {
                return $this->makeFallbackImage($slug, $seedIndex, $slot, $categoria);
            }

            $ext = $this->extensionFromContentType($contentType);
            $path = sprintf('denuncias/%s_%d_%d_%s.%s', $slug, $seedIndex, $slot, Str::lower((string) Str::uuid()), $ext);

            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (Throwable) {
            return $this->makeFallbackImage($slug, $seedIndex, $slot, $categoria);
        }
    }

    private function makeFallbackImage(string $slug, int $seedIndex, int $slot, string $categoria): string
    {
        $category = mb_strtoupper(str_replace('-', ' ', $categoria));
        $safeCategory = $this->xmlEscape($category);
        $safeTitle = $this->xmlEscape('DEMONSTRAÇÃO VISUAL');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900" viewBox="0 0 1600 900">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#121212"/>
      <stop offset="100%" stop-color="#1f2937"/>
    </linearGradient>
  </defs>
  <rect width="1600" height="900" fill="url(#g)"/>
  <g opacity="0.9">
    <text x="90" y="390" fill="#f5f5f5" font-family="Arial, Helvetica, sans-serif" font-size="56" font-weight="700">{$safeTitle}</text>
    <text x="90" y="470" fill="#3DFF9A" font-family="Arial, Helvetica, sans-serif" font-size="42" font-weight="600">{$safeCategory}</text>
    <text x="90" y="545" fill="#d1d5db" font-family="Arial, Helvetica, sans-serif" font-size="30">Imagem de fallback automática para seed de apresentação</text>
  </g>
</svg>
SVG;

        $path = sprintf('denuncias/%s_%d_%d_%s.svg', $slug, $seedIndex, $slot, Str::lower((string) Str::uuid()));
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    private function extensionFromContentType(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'image/png') => 'png',
            str_contains($contentType, 'image/webp') => 'webp',
            str_contains($contentType, 'image/gif') => 'gif',
            default => 'jpg',
        };
    }

    private function seedConfirmacoes(int $denunciaId, int $count, CarbonImmutable $baseCreatedAt): void
    {
        if ($count <= 0 || ! Schema::connection('pgsql')->hasTable('denuncia_confirmacoes')) {
            return;
        }

        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $at = $baseCreatedAt->addMinutes($i * 9);
            $rows[] = [
                'denuncia_id' => $denunciaId,
                'ip_hash' => hash('sha256', "demo-ip-{$denunciaId}-{$i}"),
                'user_agent_hash' => hash('sha256', "demo-ua-{$denunciaId}-{$i}"),
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        DB::connection('pgsql')->table('denuncia_confirmacoes')->insert($rows);
    }

    private function sanitize(string $value): string
    {
        return trim(strip_tags($value));
    }

    private function xmlEscape(string $value): string
    {
        return str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            $value,
        );
    }
}
