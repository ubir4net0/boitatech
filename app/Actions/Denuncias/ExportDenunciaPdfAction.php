<?php

namespace App\Actions\Denuncias;

use App\DTOs\Denuncias\DenunciaPdfData;
use App\Exceptions\PdfGenerationException;
use App\Models\Denuncia;
use App\Services\PDF\DenunciaPdfAssetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ExportDenunciaPdfAction
{
    public function __construct(
        private readonly DenunciaPdfAssetService $assetService,
    ) {
    }

    public function handle(Denuncia $denuncia): Response
    {
        try {
            if (! app()->bound('dompdf.wrapper')) {
                throw new PdfGenerationException('Serviço de PDF não inicializado.');
            }

            $denuncia->loadCount('confirmacoes');

            $imageDataUris = $this->assetService->resolveImageDataUris($denuncia);
            $confidence = $this->buildConfidence($denuncia, count($imageDataUris));

            $dto = new DenunciaPdfData(
                id: (int) $denuncia->id,
                title: $this->safeText((string) $denuncia->titulo, 140),
                categoryLabel: $this->safeText((string) ($denuncia->categoryMeta()['label'] ?? $denuncia->categoria), 80),
                description: $this->safeText((string) $denuncia->descricao, 2200),
                bairro: $this->safeText((string) ($denuncia->bairro ?: 'Não informado'), 120),
                ruaAproximada: $this->safeText((string) ($denuncia->endereco_aproximado ?: 'Não informada'), 180),
                cidade: $this->safeText((string) ($denuncia->cidade ?: 'Não informada'), 120),
                estado: $this->safeText((string) ($denuncia->estado ?: 'UF'), 8),
                regiaoAproximada: $this->safeText($denuncia->approximateRegion(), 180),
                reportedAt: optional($denuncia->created_at)->format('d/m/Y H:i') ?: now()->format('d/m/Y H:i'),
                confirmations: (int) $denuncia->confirmacoes_count,
                confidenceLevel: $confidence['level'],
                confidenceScore: $confidence['score'],
                reliabilityChecks: $confidence['checks'],
                imageDataUris: $imageDataUris,
                mapDataUri: $this->assetService->buildApproximateMapDataUri($denuncia),
                exportedAt: now()->format('d/m/Y H:i'),
            );

            $filename = 'denuncia-' . $dto->id . '-boitatech.pdf';

            return Pdf::loadView('pdf.denuncias.report', ['report' => $dto])
                ->setWarnings(false)
                ->setOption(['isRemoteEnabled' => false])
                ->setPaper('a4')
                ->download($filename);
        } catch (PdfGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Falha na geração de PDF da denúncia.', [
                'denuncia_id' => $denuncia->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new PdfGenerationException('Não foi possível gerar o PDF no momento.', previous: $exception);
        }
    }

    /**
     * @return array{level:string, score:int, checks:array<int, array{label:string, valid:bool}>}
     */
    private function buildConfidence(Denuncia $denuncia, int $imagesCount): array
    {
        $hasImage = $imagesCount > 0;
        $hasCommunity = (int) $denuncia->confirmacoes_count > 0;
        $hasLocation = filled($denuncia->cidade) && filled($denuncia->estado);
        $hasCategory = filled($denuncia->categoria);

        $score = 0;
        $score += $hasImage ? 35 : 0;
        $score += $hasCommunity ? 25 : 0;
        $score += $hasLocation ? 20 : 0;
        $score += $hasCategory ? 20 : 0;

        $level = match (true) {
            $score >= 80 => 'Alto',
            $score >= 50 => 'Médio',
            default => 'Inicial',
        };

        return [
            'score' => $score,
            'level' => $level,
            'checks' => [
                ['label' => 'Evidência visual anexada', 'valid' => $hasImage],
                ['label' => 'Relato comunitário', 'valid' => $hasCommunity],
                ['label' => 'Localização aproximada validada', 'valid' => $hasLocation],
                ['label' => 'Categoria ambiental identificada', 'valid' => $hasCategory],
            ],
        ];
    }

    private function safeText(string $value, int $maxLength): string
    {
        return Str::limit(trim(strip_tags($value)), $maxLength, '…');
    }
}
