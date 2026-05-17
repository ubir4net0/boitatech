<?php

namespace App\Console\Commands;

use App\Models\Ecoponto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Comando para diagnóstico do estado de imagens dos ecopontos.
 * Útil para validar integridade, detectar duplicatas, identificar missing files.
 */
class DiagnoseEcopontoImages extends Command
{
    protected $signature = 'ecopontos:diagnose-images {--detail : Exibir detalhes por ecoponto}';

    protected $description = 'Diagnostica estado de imagens, duplicatas, e integridade de arquivos';

    public function handle(): int
    {
        $this->info('🔍 Iniciando diagnóstico de imagens de ecopontos...');
        $this->newLine();

        $stats = $this->gatherStats();
        $this->displayStats($stats);

        if ($this->option('detail')) {
            $this->newLine();
            $this->displayDetailedReport($stats);
        }

        return Command::SUCCESS;
    }

    /**
     * Coleta estatísticas gerais e por ecoponto.
     */
    private function gatherStats(): array
    {
        $ecopontos = Ecoponto::all();

        $stats = [
            'total' => $ecopontos->count(),
            'with_images' => 0,
            'without_images' => 0,
            'missing_files' => 0,
            'invalid_paths' => 0,
            'duplicates' => 0,
            'paths' => [],
            'details' => [],
        ];

        foreach ($ecopontos as $ecoponto) {
            $path = $ecoponto->imagePath();

            $detail = [
                'id' => $ecoponto->id,
                'nome' => $ecoponto->nome,
                'bairro' => $ecoponto->bairro,
                'path' => $ecoponto->imagem,
                'has_file' => false,
                'file_exists' => false,
            ];

            if (! $ecoponto->imagem) {
                $stats['without_images']++;
                $stats['details'][] = $detail;
                continue;
            }

            if (! $path) {
                $stats['invalid_paths']++;
                $stats['details'][] = $detail;
                continue;
            }

            $stats['with_images']++;
            $detail['path'] = $path;
            $detail['has_file'] = true;

            $absolute = public_path($path);
            if (File::exists($absolute)) {
                $detail['has_file'] = true;
                $detail['file_exists'] = true;
            } else {
                $stats['missing_files']++;
                $this->warn("  ⚠️  Arquivo faltando: {$path} (ID: {$ecoponto->id})");
            }

            if (isset($stats['paths'][$path])) {
                $stats['paths'][$path]++;
            } else {
                $stats['paths'][$path] = 1;
            }

            $stats['details'][] = $detail;
        }

        $stats['duplicates'] = count(array_filter($stats['paths'], fn (int $count): bool => $count > 1));

        return $stats;
    }

    /**
     * Exibe resumo das estatísticas.
     */
    private function displayStats(array $stats): void
    {
        $this->line('📊 <fg=blue>RESUMO DE IMAGENS</>');
        $this->line(str_repeat('─', 50));

        $this->line(sprintf(
            '  Total de ecopontos: <fg=cyan>%d</>',
            $stats['total']
        ));

        $this->line(sprintf(
            '  ✅ Com imagem local: <fg=green>%d</> (%.1f%%)',
            $stats['with_images'],
            ($stats['total'] > 0) ? ($stats['with_images'] / $stats['total'] * 100) : 0
        ));

        $this->line(sprintf(
            '  ❌ Sem imagens: <fg=red>%d</>',
            $stats['without_images']
        ));

        $this->line(sprintf(
            '  ⚠️  Paths inválidos: <fg=yellow>%d</>',
            $stats['invalid_paths']
        ));

        $this->newLine();
        $this->line('📁 <fg=blue>INTEGRIDADE</>');
        $this->line(str_repeat('─', 50));

        if ($stats['duplicates'] > 0) {
            $this->line(sprintf(
                '  Paths duplicados: <fg=yellow>%d</>',
                $stats['duplicates']
            ));
        } else {
            $this->line('  ✅ Sem paths duplicados');
        }

        if ($stats['missing_files'] > 0) {
            $this->line(sprintf(
                '  ⚠️  Arquivos faltando: <fg=red>%d</>',
                $stats['missing_files']
            ));
        } else {
            $this->line('  ✅ Todos os arquivos existem no storage');
        }

        $this->newLine();
    }

    /**
     * Exibe relatório detalhado por ecoponto.
     */
    private function displayDetailedReport(array $stats): void
    {
        $this->line('📋 <fg=blue>DETALHES POR ECOPONTO</>');
        $this->line(str_repeat('─', 80));

        foreach ($stats['details'] as $detail) {
            $status = match (true) {
                $detail['has_file'] && $detail['file_exists'] => '✅',
                $detail['has_file'] && !$detail['file_exists'] => '⚠️',
                default => '❌',
            };

            $this->line(sprintf(
                "%s ID:%d | %s (%s)",
                $status,
                $detail['id'],
                $detail['nome'],
                $detail['bairro']
            ));

            if (!empty($detail['path'])) {
                $this->line(sprintf(
                    "   └─ Path: %s",
                    $detail['path']
                ));
            }
        }
    }
}
