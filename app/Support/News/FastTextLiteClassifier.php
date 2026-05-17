<?php

namespace App\Support\News;

class FastTextLiteClassifier
{
    /**
     * @var array<string, array<string, float>>
     */
    private array $model;

    public function __construct()
    {
        $this->model = $this->loadModel();
    }

    /**
     * @return array{label:string, ambient_probability:float, score:float, evidence:array<int, string>}
     */
    public function classify(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '') {
            return [
                'label' => 'nao_ambiental',
                'ambient_probability' => 0.0,
                'score' => -999.0,
                'evidence' => [],
            ];
        }

        $features = $this->extractFeatures($normalized);
        $positive = 0.0;
        $negative = 0.0;
        $evidence = [];

        foreach ($features as $feature => $weight) {
            $featureWeight = (float) ($this->model['ambiental_brasil'][$feature] ?? 0.0);
            $oppositeWeight = (float) ($this->model['nao_ambiental'][$feature] ?? 0.0);

            if ($featureWeight !== 0.0) {
                $positive += $featureWeight * $weight;
                $evidence[] = '+ ' . $feature;
            }

            if ($oppositeWeight !== 0.0) {
                $negative += $oppositeWeight * $weight;
                $evidence[] = '- ' . $feature;
            }
        }

        $score = $positive - $negative;
        $ambientProbability = $this->sigmoid($score);
        $label = $ambientProbability >= 0.5 ? 'ambiental_brasil' : 'nao_ambiental';

        return [
            'label' => $label,
            'ambient_probability' => round($ambientProbability, 4),
            'score' => round($score, 4),
            'evidence' => array_values(array_unique($evidence)),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function loadModel(): array
    {
        $path = (string) config('boitanews.nlp.fasttext_model_path', storage_path('app/boitanews/fasttext-model.json'));
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && isset($decoded['ambiental_brasil'], $decoded['nao_ambiental'])) {
                return [
                    'ambiental_brasil' => is_array($decoded['ambiental_brasil']) ? $decoded['ambiental_brasil'] : [],
                    'nao_ambiental' => is_array($decoded['nao_ambiental']) ? $decoded['nao_ambiental'] : [],
                ];
            }
        }

        return [
            'ambiental_brasil' => [
                'amazônia' => 2.8,
                'amazonia' => 2.8,
                'desmatamento' => 3.2,
                'queimadas' => 3.1,
                'incêndio' => 2.4,
                'ibama' => 2.6,
                'icmbio' => 2.4,
                'garimpo' => 2.0,
                'indígena' => 2.1,
                'indigena' => 2.1,
                'biodiversidade' => 2.2,
                'floresta' => 1.8,
                'preservação' => 2.1,
                'preservacao' => 2.1,
                'sustentabilidade' => 1.8,
                'fiscalização' => 2.2,
                'fiscalizacao' => 2.2,
                'bioma' => 1.7,
                'pantanal' => 1.9,
                'cerrado' => 1.9,
                'mata atlântica' => 1.9,
                'caatinga' => 1.9,
                'brasil' => 1.5,
                'amazonas' => 1.8,
                'pará' => 1.8,
                'garimpo ilegal' => 3.0,
                'terra indígena' => 2.8,
            ],
            'nao_ambiental' => [
                'pcc' => 4.5,
                'futebol' => 4.5,
                'bbb' => 4.5,
                'novela' => 3.8,
                'celebridade' => 3.8,
                'goleiro' => 3.8,
                'assalto' => 3.5,
                'homicídio' => 3.5,
                'crime urbano' => 3.2,
                'política' => 2.2,
                'internacional' => 2.4,
                'guerra' => 3.1,
                'tsunami' => 3.0,
                'megatsunami' => 4.0,
                'alaska' => 3.5,
                'rússia' => 3.0,
                'ucrânia' => 3.0,
                'china' => 1.8,
                'eua' => 1.8,
                'entretenimento' => 3.4,
                'cantor' => 3.2,
                'atriz' => 3.2,
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    private function extractFeatures(string $text): array
    {
        $features = [];

        $tokens = preg_split('/\s+/u', $text) ?: [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $features[$token] = ($features[$token] ?? 0.0) + 1.0;
        }

        $phrases = [
            'garimpo ilegal',
            'terra indígena',
            'terras indígenas',
            'floresta amazônica',
            'fiscalização ambiental',
            'crime ambiental',
            'meio ambiente',
            'mudanças climáticas',
            'mudança climática',
            'preservação ambiental',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($text, $phrase)) {
                $features[$phrase] = ($features[$phrase] ?? 0.0) + 2.5;
            }
        }

        $length = mb_strlen($text);
        for ($size = 3; $size <= 5; $size++) {
            if ($length < $size) {
                continue;
            }

            for ($offset = 0; $offset <= $length - $size; $offset++) {
                $ngram = mb_substr($text, $offset, $size);
                if (trim($ngram) === '') {
                    continue;
                }

                if (preg_match('/[\p{L}\p{N}]/u', $ngram) !== 1) {
                    continue;
                }

                $features['#' . $ngram] = ($features['#' . $ngram] ?? 0.0) + 0.15;
            }
        }

        return $features;
    }

    private function sigmoid(float $score): float
    {
        return 1.0 / (1.0 + exp(-$score));
    }
}
