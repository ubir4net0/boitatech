<?php

namespace Database\Seeders;

use App\Models\Ecoponto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder local de ecopontos.
 * 
 * Responsabilidades:
 * - Sincronização idempotente de dados
 * - Persistência apenas do caminho local da imagem
 * - Sem URLs externas e sem dependências remotas
 */
class EcopontosSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Iniciando sincronização de ecopontos...');

        $dataset = $this->dataset();
        $existingIds = Ecoponto::pluck('id')->toArray();
        $processedIds = [];

        DB::connection('pgsql')->transaction(function () use ($dataset, &$processedIds): void {
            foreach ($dataset as $entry) {
                $ponto = Ecoponto::updateOrCreate(
                    [
                        'nome' => $this->sanitize((string) $entry['nome']),
                        'bairro' => $this->sanitize((string) $entry['bairro']),
                        'endereco' => $this->sanitize((string) $entry['endereco']),
                    ],
                    [
                        'descricao' => $this->buildDescription($entry),
                        'tipo_coleta' => (string) $entry['tipo_coleta'],
                        'cidade' => 'Manaus',
                        'ativo' => true,
                        'zona' => $this->sanitize((string) $entry['zona']),
                        'latitude' => $this->approximateCoordinates(
                            (string) $entry['bairro'],
                            (string) $entry['zona']
                        )['latitude'],
                        'longitude' => $this->approximateCoordinates(
                            (string) $entry['bairro'],
                            (string) $entry['zona']
                        )['longitude'],
                        'telefone' => ! empty($entry['telefone']) ? $this->sanitize((string) $entry['telefone']) : null,
                        'horario_funcionamento' => $this->sanitize((string) $entry['horario_funcionamento']),
                        'materiais_aceitos' => $this->sanitizeMaterials((array) ($entry['materiais_aceitos'] ?? [])),
                        'imagem' => $this->localImagePath($entry),
                        'imagens' => [],
                    ]
                );

                $processedIds[] = $ponto->id;
            }
        });

        // Informa sobre removidos (dados que existem no DB mas não estão no dataset)
        $removed = array_diff($existingIds, $processedIds);
        if (!empty($removed)) {
            $this->command->warn(sprintf(
                'ℹ️  %d ecoponto(s) existente(s) não sincronizado(s) (podem ter sido removidos do dataset)',
                count($removed)
            ));
        }

        $this->command->info(sprintf(
            '✅ Sincronização concluída: %d ecoponto(s) processado(s)',
            count($processedIds)
        ));
        $this->command->line('📸 Paths locais definidos em storage/app/public/ecopontos/*.webp (public/storage/ecopontos)');
    }

    /**
     * Gera caminho local padronizado da imagem.
     */
    private function localImagePath(array $entry): string
    {
        $nome = $this->sanitize((string) ($entry['nome'] ?? 'ecoponto'));
        $bairro = $this->sanitize((string) ($entry['bairro'] ?? 'manaus'));

        $slug = Str::slug(trim($nome . ' ' . $bairro));

        return 'ecopontos/' . $slug . '.webp';
    }

    /**
     * Sanitiza e normaliza lista de materiais aceitos.
     *
     * @return array<int, string>
     */
    private function sanitizeMaterials(array $materials): array
    {
        return array_values(array_unique(array_map(
            fn (string $value): string => trim(mb_strtolower($value)),
            $materials
        )));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dataset(): array
    {
        return [
            ['nome' => 'Supermercado DB — Adrianópolis', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Jornalista Umberto Calderaro, Adrianópolis', 'bairro' => 'Adrianópolis', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal', 'eletronicos']],
            ['nome' => 'Supermercado Empório DB — Cidade Nova', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Max Teixeira, Cidade Nova', 'bairro' => 'Cidade Nova', 'zona' => 'Norte', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Vitória — Flores', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Torquato Tapajós, Flores', 'bairro' => 'Flores', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Pátio Gourmet — Aleixo', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Via Láctea, Aleixo', 'bairro' => 'Aleixo', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Nova Era — Novo Aleixo', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Governador José Lindoso, Novo Aleixo', 'bairro' => 'Novo Aleixo', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Nova Era — Santa Etelvina', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Torquato Tapajós, Santa Etelvina', 'bairro' => 'Santa Etelvina', 'zona' => 'Norte', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Veneza — Parque 10', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Tancredo Neves, Parque 10 de Novembro', 'bairro' => 'Parque 10 de Novembro', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Veneza — Lagoa Azul', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Torquato Tapajós, Lagoa Azul', 'bairro' => 'Lagoa Azul', 'zona' => 'Norte', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Pátio Gourmet — Adrianópolis (Rua Terezinha)', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Rua Terezinha, Adrianópolis', 'bairro' => 'Adrianópolis', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Cezar — São José Operário', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Rua 7B, São José Operário', 'bairro' => 'São José Operário', 'zona' => 'Leste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Yroyak — Nossa Senhora das Graças', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Rio Madeira, Nossa Senhora das Graças', 'bairro' => 'Nossa Senhora das Graças', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Nova Era — Flores (Av. Torquato Tapajós)', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Torquato Tapajós, Flores', 'bairro' => 'Flores', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Yroyak — Ponta Negra', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Coronel Teixeira, Ponta Negra', 'bairro' => 'Ponta Negra', 'zona' => 'Oeste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Nova Era — Santo Antônio', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Brasil, Santo Antônio', 'bairro' => 'Santo Antônio', 'zona' => 'Oeste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Carrefour — Ponta Negra', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Pedro Teixeira, Ponta Negra', 'bairro' => 'Ponta Negra', 'zona' => 'Oeste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Carrefour — Flores', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Djalma Batista, Flores', 'bairro' => 'Flores', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado DB — Dom Pedro', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Pedro Teixeira, Dom Pedro', 'bairro' => 'Dom Pedro', 'zona' => 'Centro-Oeste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado DB — Coroado', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Cosme Ferreira, Coroado', 'bairro' => 'Coroado', 'zona' => 'Leste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Carrefour — Adrianópolis', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Jornalista Umberto Calderaro, Adrianópolis', 'bairro' => 'Adrianópolis', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Assaí — Aleixo', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Efigênio Salles, Aleixo', 'bairro' => 'Aleixo', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Assaí — São José', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Autaz Mirim, São José', 'bairro' => 'São José Operário', 'zona' => 'Leste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado DB — Campos Elíseos', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Campos Elíseos, Redenção', 'bairro' => 'Redenção', 'zona' => 'Centro-Oeste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Hiper DB — Ponta Negra', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Coronel Teixeira, Ponta Negra', 'bairro' => 'Ponta Negra', 'zona' => 'Oeste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Supermercado Assaí — Flores', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Torquato Tapajós, Flores', 'bairro' => 'Flores', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Hiper DB — Japiim', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Rodrigo Otávio, Japiim', 'bairro' => 'Japiim', 'zona' => 'Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Empório DB — Ponta Negra', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Coronel Teixeira, Ponta Negra', 'bairro' => 'Ponta Negra', 'zona' => 'Oeste', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Hiper DB — Centro', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Constantino Nery, Centro', 'bairro' => 'Centro', 'zona' => 'Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Hiper DB — Colônia Santo Antônio', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Francisco Queiroz, Colônia Santo Antônio', 'bairro' => 'Colônia Santo Antônio', 'zona' => 'Norte', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
            ['nome' => 'Hiper DB — Adrianópolis', 'tipo_coleta' => 'coleta-seletiva', 'endereco' => 'Avenida Mario Ypiranga, Adrianópolis', 'bairro' => 'Adrianópolis', 'zona' => 'Centro-Sul', 'horario_funcionamento' => 'Seg a Sáb · 07:00 às 22:00', 'materiais_aceitos' => ['papel', 'papelao', 'plastico', 'vidro', 'metal']],
        ];
    }

    private function buildDescription(array $entry): string
    {
        $label = config('ecopontos.categories.' . $entry['tipo_coleta'] . '.label', $entry['tipo_coleta']);
        $materials = $this->sanitizeMaterials((array) ($entry['materiais_aceitos'] ?? []));
        $materialText = implode(', ', array_map(fn (string $value): string => str_replace(['eletronicos', 'papelao'], ['eletrônicos', 'papelão'], $value), $materials));

        if ($entry['tipo_coleta'] === 'descarte-eletronico') {
            return sprintf(
                'Ponto público de %s no bairro %s, estruturado para receber %s com triagem adequada e encaminhamento à reciclagem especializada.',
                $label,
                $entry['bairro'],
                $materialText
            );
        }

        if ($entry['tipo_coleta'] === 'pilhas-baterias') {
            return sprintf(
                'Unidade de %s no bairro %s, adequada para %s e armazenamento seguro até a destinação final licenciada.',
                strtolower($label),
                $entry['bairro'],
                $materialText
            );
        }

        if ($entry['tipo_coleta'] === 'reciclaveis-gerais') {
            return sprintf(
                'Ponto de apoio para recicláveis gerais no bairro %s, com recebimento de %s e orientação para descarte consciente.',
                $entry['bairro'],
                $materialText
            );
        }

        return sprintf(
            'Ponto de %s em %s, com recebimento gratuito de %s e encaminhamento à cadeia local de reciclagem.',
            strtolower($label),
            $entry['bairro'],
            $materialText
        );
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    private function approximateCoordinates(string $bairro, string $zona): array
    {
        $key = mb_strtolower(trim($bairro));
        $bases = [
            'adrianópolis' => [-3.0998, -60.0121],
            'cidade nova' => [-3.0236, -60.0180],
            'flores' => [-3.0535, -60.0228],
            'aleixo' => [-3.0989, -59.9898],
            'novo aleixo' => [-3.0242, -59.9735],
            'santa etelvina' => [-3.0010, -60.0320],
            'parque 10 de novembro' => [-3.0822, -60.0048],
            'lagoa azul' => [-3.0108, -60.0405],
            'são josé operário' => [-3.0640, -59.9550],
            'sao jose operario' => [-3.0640, -59.9550],
            'nossa senhora das graças' => [-3.0940, -60.0135],
            'nossa senhora das gracas' => [-3.0940, -60.0135],
            'são geraldo' => [-3.0850, -60.0218],
            'sao geraldo' => [-3.0850, -60.0218],
            'chapada' => [-3.0878, -60.0210],
            'ponta negra' => [-3.0876, -60.0850],
            'santo antônio' => [-3.1230, -60.0442],
            'santo antonio' => [-3.1230, -60.0442],
            'coroado' => [-3.1050, -59.9658],
            'coroado iii' => [-3.1024, -59.9620],
            'dom pedro' => [-3.0746, -60.0550],
            'redenção' => [-3.0685, -60.0050],
            'redencao' => [-3.0685, -60.0050],
            'japiim' => [-3.1230, -59.9958],
            'centro' => [-3.1325, -60.0230],
            'colônia santo antônio' => [-3.0215, -60.0315],
            'colonia santo antonio' => [-3.0215, -60.0315],
            'crespo' => [-3.1390, -59.9870],
            'nova cidade' => [-3.0158, -59.9920],
            'parque dez de novembro' => [-3.0822, -60.0048],
        ];

        $base = $bases[$key] ?? match (mb_strtolower(trim($zona))) {
            'norte' => [-3.0100, -60.0300],
            'leste' => [-3.0600, -59.9600],
            'oeste' => [-3.0900, -60.0700],
            'sul' => [-3.1200, -59.9990],
            'centro-oeste' => [-3.0700, -60.0400],
            default => [-3.0820, -60.0230],
        };

        return ['latitude' => (float) $base[0], 'longitude' => (float) $base[1]];
    }

    private function sanitize(string $value): string
    {
        return trim(strip_tags($value));
    }
}

