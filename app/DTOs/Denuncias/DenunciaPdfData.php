<?php

namespace App\DTOs\Denuncias;

final readonly class DenunciaPdfData
{
    /**
     * @param list<string> $imageDataUris
     * @param array<int, array{label:string, valid:bool}> $reliabilityChecks
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $categoryLabel,
        public string $description,
        public string $bairro,
        public string $ruaAproximada,
        public string $cidade,
        public string $estado,
        public string $regiaoAproximada,
        public string $reportedAt,
        public int $confirmations,
        public string $confidenceLevel,
        public int $confidenceScore,
        public array $reliabilityChecks,
        public array $imageDataUris,
        public ?string $mapDataUri,
        public string $exportedAt,
    ) {
    }
}
