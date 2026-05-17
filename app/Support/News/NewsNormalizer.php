<?php

namespace App\Support\News;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class NewsNormalizer
{
    /**
     * @var array<int, string>
     */
    private array $mandatoryThemeKeywords = [
        'amazônia',
        'amazonia',
        'desmatamento',
        'queimadas',
        'queimada',
        'incêndio florestal',
        'ibama',
        'icmbio',
        'garimpo ilegal',
        'terra indígena',
        'terras indígenas',
        'povos indígenas',
        'floresta amazônica',
        'sustentabilidade',
        'biodiversidade',
        'preservação ambiental',
        'fiscalização ambiental',
        'crime ambiental',
        'bioma',
        'cerrado',
        'pantanal',
        'mata atlântica',
        'caatinga',
        'reserva ambiental',
        'madeira ilegal',
        'seca amazônica',
        'fumaça',
        'inpe',
        'garimpo',
        'conservação ambiental',
        'licenciamento ambiental',
        'crise climática',
        'rio amazônico',
        'unidade de conservação',
    ];

    /**
     * @var array<int, string>
     */
    private array $highSignalThemeKeywords = [
        'amazônia',
        'amazonia',
        'desmatamento',
        'queimadas',
        'queimada',
        'incêndio florestal',
        'garimpo ilegal',
        'madeira ilegal',
        'terra indígena',
        'terras indígenas',
        'povos indígenas',
        'floresta amazônica',
        'fiscalização ambiental',
        'crime ambiental',
        'reserva ambiental',
        'seca amazônica',
        'inpe',
    ];

    /**
     * @var array<int, string>
     */
    private array $amazonStrongKeywords = [
        'amazônia',
        'amazonia',
        'desmatamento',
        'queimadas',
        'queimada',
        'incêndio florestal',
        'garimpo ilegal',
        'ibama',
        'icmbio',
        'terra indígena',
        'terras indígenas',
        'povos indígenas',
        'floresta amazônica',
    ];

    /**
     * @var array<int, string>
     */
    private array $blacklistTerms = [
        // Crime / violência urbana
        'pcc',
        'goleiro bruno',
        'assalto',
        'homicídio',
        'crime urbano',
        'crimes urbanos',
        'lavagem de dinheiro',
        'tráfico de drogas',
        'assassinato',
        'sequestro',
        'chacina',
        // Entretenimento / celebridades
        'celebridade',
        'celebridades',
        'bbb',
        'novela',
        'cantor',
        'atriz',
        'reality show',
        'fofoca',
        'horóscopo',
        'gossip',
        'streamings',
        'netflix',
        'prime video',
        // Esportes
        'futebol',
        'esportes',
        'brasileirão',
        'libertadores',
        'copa do mundo',
        'nba',
        'nfl',
        'fórmula 1',
        'formula 1',
        'tênis',
        'vôlei',
        'basquete',
        'olimpíadas',
        // Geopolítica / guerra internacional
        'rússia',
        'ucrânia',
        'putin',
        'otan',
        'nato',
        'guerra na ucrânia',
        'israel',
        'palestina',
        'gaza',
        'líbano',
        'síria',
        'iran',
        'irã',
        'coreia do norte',
        'taiwan',
        'exército russo',
        'exército israelense',
        // Fenômenos naturais não relacionados ao Brasil
        'alaska',
        'tsunami',
        'megatsunami',
        'furacão',
        'terremoto',
        // Turismo e hotelaria
        'hóspede',
        'hospedagem',
        'hotel',
        'pousada',
        'hostel',
        'check-in digital',
        'ficha de hóspede',
        // Economia / finanças (quando não ambiental)
        'mega-sena',
        'powerball',
        'loteria',
        'sorteio da mega',
        'bolsa de valores',
        'mercado financeiro',
        'criptomoeda',
        'criptomoedas',
        'bitcoin',
        // Política nacional genérica (sem nexo ambiental)
        'senador ciro',
        'operação compliance',
        'emenda master',
        // Organizações estrangeiras não relacionadas
        'liga da juventude comunista',
        'partido comunista da china',
        'congresso dos eua',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private array $contextualBlacklistGroups = [
        'crime_urbano' => ['pcc', 'crime urbano', 'polícia urbana', 'assalto', 'homicídio', 'tráfico', 'prisão', 'lava jato'],
        'esporte' => ['futebol', 'campeonato', 'brasileirão', 'libertadores', 'goleiro'],
        'entretenimento' => ['bbb', 'novela', 'reality show', 'celebridade', 'cantor', 'atriz', 'entretenimento'],
        'politica_aleatoria' => ['eleição', 'senador', 'deputado', 'presidente', 'governo federal', 'congresso'],
        'internacional_irrelevante' => ['guerra', 'ucrânia', 'rússia', 'china', 'alaska', 'megatsunami'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private array $themeCategoryMap = [
        'amazonia' => ['amazônia', 'amazonia', 'floresta amazônica', 'amazônia legal', 'seca amazônica', 'rio amazônico'],
        'queimadas' => ['queimada', 'queimadas', 'incêndio florestal', 'fogo florestal', 'focos de calor', 'fumaça'],
        'desmatamento' => ['desmatamento', 'supressão vegetal', 'desflorestamento', 'derrubada da floresta', 'exploração madeireira'],
        'crimes-ambientais' => ['crime ambiental', 'garimpo ilegal', 'madeira ilegal', 'grilagem', 'extração ilegal', 'tráfico de animais', 'vazamento de petróleo', 'derramamento de petróleo'],
        'fiscalizacao' => ['fiscalização', 'ibama', 'icmbio', 'multa ambiental', 'operação ambiental', 'enforcement ambiental'],
        'povos-indigenas' => ['povos indígenas', 'terra indígena', 'terras indígenas', 'indígena', 'indigena', 'aldeia', 'etnia'],
        'biodiversidade' => ['biodiversidade', 'fauna', 'flora', 'espécie ameaçada', 'extinção', 'ecossistema', 'conservação da natureza'],
        'sustentabilidade' => ['sustentabilidade', 'bioeconomia', 'energia limpa', 'reflorestamento', 'economia verde', 'preservação ambiental'],
        'clima' => ['clima', 'climática', 'climático', 'mudança climática', 'mudanças climáticas', 'crise climática', 'seca'],
    ];

    /**
     * @var array<int, string>
     */
    private array $brazilEntities = [
        'brasil',
        'brasileiro',
        'brasileira',
        'amazônia legal',
        'ibama',
        'icmbio',
        'inpe',
        'mma',
        'ministério do meio ambiente',
        'pará',
        'amazonas',
        'rondônia',
        'acre',
        'roraima',
        'amapá',
        'maranhão',
        'tocantins',
        'mato grosso',
        'mato grosso do sul',
        'paraná',
        'são paulo',
        'rio de janeiro',
        'minas gerais',
        'bahia',
        'goiás',
        'brasília',
        'pantanal',
        'cerrado',
        'mata atlântica',
        'caatinga',
        'pampa',
    ];

    public function __construct(
        private readonly NewsContentSanitizer $sanitizer,
        private readonly ArticleImageResolver $imageResolver,
        private readonly FastTextLiteClassifier $classifier,
    ) {
    }

    public function normalize(array $item, array $source): ?array
    {
        $assessment = $this->assess($item, $source);

        return $assessment['decision'] === 'approved' ? $assessment['row'] : null;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $source
     * @return array{decision:string,reason:string,row:?array<string,mixed>,scores:array<string,int>,matched_terms:array<int,string>,blocked_terms:array<int,string>,theme_terms:array<int,string>}
     */
    public function assess(array $item, array $source): array
    {
        $title = $this->sanitizer->cleanText($item['title'] ?? null, 350);
        $url = $this->sanitizer->cleanUrl($item['url'] ?? null, 1500);

        if ($title === null || $url === null) {
            return $this->emptyAssessment('discarded', 'invalid_input');
        }

        $language = mb_strtolower((string) ($item['language'] ?? ($source['language'] ?? 'pt-BR')));
        if (! str_starts_with($language, 'pt')) {
            return $this->emptyAssessment('discarded', 'non_portuguese');
        }

        $publishedAt = $this->normalizeDate($item['published_at'] ?? null);
        if ($publishedAt === null) {
            return $this->emptyAssessment('discarded', 'missing_publication_date');
        }

        $excerpt = $this->sanitizer->cleanExcerpt($item['excerpt'] ?? null, 1200);
        $combined = $this->buildHaystack($title, $excerpt);
        $canonicalUrl = $this->sanitizer->canonicalizeUrl($url);
        $normalizedTitle = $this->sanitizer->normalizeTitle($title);
        $normalizedExcerpt = $excerpt !== null ? $this->sanitizer->normalizeTitle($excerpt) : '';

        $requiredKeywords = collect((array) config('boitanews.required_keywords', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if ($requiredKeywords !== [] && $this->matchedTerms($combined, $requiredKeywords) === []) {
            return $this->emptyAssessment('discarded', 'missing_required_keyword');
        }

        $blockedTerms = $this->matchedTerms($combined, $this->blacklistTerms);
        if ($blockedTerms !== []) {
            return $this->emptyAssessment('discarded', 'blacklist_context', [
                'blocked_terms' => $blockedTerms,
            ]);
        }

        $blockedContext = $this->matchedContextualBlacklist($combined);
        if ($blockedContext !== []) {
            return $this->emptyAssessment('discarded', 'contextual_blacklist', [
                'blocked_terms' => $blockedContext,
            ]);
        }

        $themeTerms = $this->matchedTerms($combined, $this->mandatoryThemeKeywords);
        if ($themeTerms === []) {
            return $this->emptyAssessment('discarded', 'missing_thematic_whitelist');
        }

        if (! $this->hasBrazilianContextStrict($combined)) {
            return $this->emptyAssessment('discarded', 'missing_brazilian_context', [
                'theme_terms' => $themeTerms,
            ]);
        }

        if (! $this->hasBrazilianEnvironmentalFocus($combined)) {
            return $this->emptyAssessment('discarded', 'missing_brazilian_environmental_focus', [
                'theme_terms' => $themeTerms,
            ]);
        }

        $scores = $this->scoreContextual($combined, $themeTerms);
        $highSignalTerms = $this->matchedTerms($combined, $this->highSignalThemeKeywords);
        $strongAmazonTerms = $this->matchedTerms($combined, $this->amazonStrongKeywords);
        $trustScore = $this->resolveSourceTrustScore($source);
        $nlp = $this->classifier->classify($combined);
        $nlpScore = (int) round($nlp['ambient_probability'] * 100);
        $nlpContribution = (int) round((($nlp['ambient_probability'] - 0.5) * 20));
        $trustContribution = (int) round(($trustScore - 50) / 5);
        $finalScore = max(0, $scores['final_score'] + $nlpContribution + $trustContribution);
        $strictNlpThreshold = (float) config('boitanews.nlp.min_ambient_probability', 0.70);
        $softNlpThreshold = (float) config('boitanews.nlp.review_probability_floor', 0.55);

        // Hard discard: NLP muito baixo + contexto fraco
        if ($nlp['ambient_probability'] < 0.42 && $scores['negative_score'] >= 20) {
            return $this->emptyAssessment('discarded', 'nlp_very_low_and_negative_context', [
                'theme_terms' => $themeTerms,
                'blocked_terms' => $blockedTerms,
            ]);
        }

        if ($highSignalTerms === [] && $nlp['ambient_probability'] < 0.62) {
            return $this->emptyAssessment('pending_review', 'missing_high_signal_environmental_context', [
                'theme_terms' => $themeTerms,
                'blocked_terms' => $blockedTerms,
            ]);
        }

        $weakThematicDensity = count($themeTerms) <= 1 && count($highSignalTerms) === 0;
        if ($weakThematicDensity && $nlp['ambient_probability'] < 0.74) {
            return $this->emptyAssessment('pending_review', 'weak_thematic_density', [
                'theme_terms' => $themeTerms,
                'blocked_terms' => $scores['matched_negative'] ?? [],
            ]);
        }

        if ($scores['negative_score'] >= 14 && count($strongAmazonTerms) === 0) {
            return $this->emptyAssessment('pending_review', 'negative_context_without_strong_amazon_signal', [
                'theme_terms' => $themeTerms,
                'blocked_terms' => $scores['matched_negative'] ?? [],
            ]);
        }

        $signal1StrongAmazon = count($strongAmazonTerms) > 0;
        $signal2NlpStrict = $nlp['ambient_probability'] >= $strictNlpThreshold;
        $signal2NlpSoft = $nlp['ambient_probability'] >= $softNlpThreshold;
        $signal3Trust = $trustScore >= 80;
        $strictSignalCount = (int) $signal1StrongAmazon + (int) $signal2NlpStrict + (int) $signal3Trust;
        $softSignalCount = (int) $signal1StrongAmazon + (int) $signal2NlpSoft + (int) $signal3Trust;

        $strictFloor = $this->strictFloorByTrust($trustScore);
        $softFloor = $this->softFloorByTrust($trustScore);
        $quarantineFloor = max(8, $softFloor - 8);
        $severeNegativeContext = $scores['negative_score'] >= 30;

        if ($strictSignalCount >= 2 && $finalScore >= $strictFloor) {
            $decision = 'approved';
            $reason = 'strict_layer_ok';
            $publicationLayer = 'strict';
        } elseif ($softSignalCount >= 1 && $finalScore >= $softFloor) {
            $decision = 'approved';
            $reason = 'soft_layer_ok';
            $publicationLayer = 'soft';
        } elseif ($softSignalCount >= 1 && $finalScore >= $quarantineFloor) {
            $decision = 'pending_review';
            $reason = 'quarantine_layer';
            $publicationLayer = 'quarantine';
        } elseif ($severeNegativeContext || $finalScore <= 3) {
            $decision = 'discarded';
            $reason = 'semantic_score_extremely_low';
            $publicationLayer = 'discarded';
        } else {
            $decision = 'pending_review';
            $reason = 'insufficient_signals_quarantine';
            $publicationLayer = 'quarantine';
        }

        $category = $this->resolveSemanticCategory($combined);
        $imageUrl = $decision === 'discarded' ? null : $this->sanitizer->cleanImageUrl($item['image_url'] ?? null);
        $qualityScore = $this->qualityScoreV2($title, $excerpt, $publishedAt, $scores, $imageUrl !== null);
        $sourceName = $this->sanitizer->cleanText($item['source_name'] ?? null, 120)
            ?? $this->sanitizer->cleanText($source['name'] ?? null, 120)
            ?? 'Fonte desconhecida';

        $row = [
            'source_key' => (string) ($source['key'] ?? 'unknown'),
            'source_name' => $sourceName,
            'external_id' => $this->sanitizer->cleanText($item['external_id'] ?? null, 190),
            'title' => $title,
            'excerpt' => $excerpt,
            'image_url' => $imageUrl,
            'url' => $url,
            'canonical_url' => $canonicalUrl,
            'source_url_hash' => hash('sha256', $canonicalUrl),
            'title_hash' => hash('sha256', mb_strtolower($title)),
            'content_hash' => hash('sha256', implode('|', [$canonicalUrl, $normalizedTitle, $normalizedExcerpt])),
            'normalized_title' => $normalizedTitle,
            'title_signature' => $this->sanitizer->titleSignature($title),
            'language' => $this->sanitizer->cleanText($item['language'] ?? ($source['language'] ?? 'pt-BR'), 5),
            'country' => 'BR',
            'category' => $category,
            'published_at' => $decision === 'pending_review' ? null : $publishedAt,
            'ingested_at' => now()->utc(),
            'is_featured' => $decision === 'approved' && $publicationLayer === 'strict' && $qualityScore >= 84,
            'quality_score' => $qualityScore,
            'review_status' => $decision,
            'review_reason' => $reason,
            'curation_score' => $finalScore,
            'metadata' => [
                'author' => $this->sanitizer->cleanText($item['author'] ?? null, 120),
                'scores' => array_merge($scores, [
                    'nlp_score' => $nlpScore,
                    'nlp_label' => (string) $nlp['label'],
                    'nlp_probability' => (float) $nlp['ambient_probability'],
                    'trust_score' => $trustScore,
                    'trust_contribution' => $trustContribution,
                    'strict_signal_count' => $strictSignalCount,
                    'soft_signal_count' => $softSignalCount,
                    'strong_amazon_keywords' => count($strongAmazonTerms),
                    'nlp_strict_threshold' => $strictNlpThreshold,
                    'nlp_soft_threshold' => $softNlpThreshold,
                    'strict_floor' => $strictFloor,
                    'soft_floor' => $softFloor,
                ]),
                'matched_terms' => $themeTerms,
                'blocked_terms' => array_values(array_unique(array_merge($blockedTerms, $blockedContext))),
                'nlp_evidence' => $nlp['evidence'],
                'review_status' => $decision,
                'review_reason' => $reason,
                'publication_layer' => $publicationLayer,
                'source_trust_score' => $trustScore,
                'curation_version' => 'boitanews-v5',
            ],
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ];

        return [
            'decision' => $decision,
            'reason' => $reason,
            'row' => $row,
            'scores' => array_merge($scores, [
                'nlp_score' => $nlpScore,
                'nlp_label' => (string) $nlp['label'],
                'nlp_probability' => (int) round($nlp['ambient_probability'] * 100),
                'trust_score' => $trustScore,
                'strict_signal_count' => $strictSignalCount,
                'soft_signal_count' => $softSignalCount,
                'strong_amazon_keywords' => count($strongAmazonTerms),
            ]),
            'matched_terms' => $themeTerms,
            'blocked_terms' => array_values(array_unique(array_merge($blockedTerms, $blockedContext))),
            'theme_terms' => $themeTerms,
        ];
    }

    /**
     * @param array{environmental_score:int,amazon_score:int,brazil_score:int,negative_score:int,positive_score:int,final_score:int,matched_positive:array<int,string>,matched_negative:array<int,string>} $scores
     */
    private function qualityScoreV2(string $title, ?string $excerpt, ?CarbonImmutable $publishedAt, array $scores, bool $hasImage = false): int
    {
        $score = 35;

        if (mb_strlen($title) >= 40) {
            $score += 8;
        }

        if ($excerpt !== null && mb_strlen($excerpt) >= 120) {
            $score += 10;
        } elseif ($excerpt !== null && mb_strlen($excerpt) >= 40) {
            $score += 5;
        }

        if ($publishedAt !== null) {
            $score += 5;
        }

        if ($hasImage) {
            $score += 5;
        }

        $score += min(40, $scores['final_score'] * 4);

        return min(100, $score);
    }

    private function resolveSemanticCategory(string $haystack): string
    {
        foreach ($this->themeCategoryMap as $category => $keywords) {
            if ($this->containsAny($haystack, $keywords)) {
                return $category;
            }
        }

        return 'sustentabilidade';
    }

    /**
     * @param array<int, string> $themeTerms
     * @return array{environmental_score:int,amazon_score:int,brazil_score:int,negative_score:int,positive_score:int,final_score:int,matched_positive:array<int,string>,matched_negative:array<int,string>}
     */
    private function scoreContextual(string $haystack, array $themeTerms = []): array
    {
        $weightMap = [
            'amazônia' => 15,
            'amazonia' => 15,
            'desmatamento' => 15,
            'queimadas' => 15,
            'queimada' => 15,
            'incêndio florestal' => 12,
            'ibama' => 10,
            'icmbio' => 10,
            'garimpo ilegal' => 10,
            'terra indígena' => 10,
            'terras indígenas' => 10,
            'povos indígenas' => 9,
            'floresta amazônica' => 12,
            'sustentabilidade' => 5,
            'biodiversidade' => 5,
            'preservação ambiental' => 6,
            'fiscalização ambiental' => 7,
            'crime ambiental' => 7,
            'bioma' => 5,
            'cerrado' => 5,
            'pantanal' => 5,
            'mata atlântica' => 5,
            'caatinga' => 5,
            'reserva ambiental' => 6,
            'madeira ilegal' => 7,
            'seca amazônica' => 7,
            'fumaça' => 5,
            'brasil' => 3,
            'brasileiro' => 3,
            'brasileira' => 3,
            'pará' => 3,
            'amazonas' => 3,
            'rondônia' => 3,
            'acre' => 3,
            'roraima' => 3,
            'amapá' => 3,
            'maranhão' => 3,
            'tocantins' => 3,
            'mato grosso' => 3,
            'mato grosso do sul' => 3,
            'goiás' => 3,
            'bahia' => 3,
            'brasília' => 3,
        ];

        $negativeWeights = [
            'pcc' => 30,
            'futebol' => 30,
            'goleiro' => 20,
            'celebridade' => 20,
            'crime urbano' => 20,
            'bbb' => 30,
            'entretenimento' => 20,
            'assalto' => 20,
            'homicídio' => 20,
            'tráfico' => 20,
            'prisão' => 20,
            'cantor' => 20,
            'atriz' => 20,
            'política internacional' => 8,
            'guerra' => 20,
            'alaska' => 15,
            'eua' => 8,
            'rússia' => 15,
            'ucrânia' => 15,
            'china' => 10,
            'tsunami' => 8,
            'megatsunami' => 15,
        ];

        $matchedPositive = [];
        $matchedNegative = [];
        $positiveScore = 0;
        $negativeScore = 0;
        $amazonScore = 0;
        $brazilScore = 0;

        foreach ($weightMap as $keyword => $weight) {
            if (! $this->containsKeyword($haystack, $keyword)) {
                continue;
            }

            $matchedPositive[] = $keyword;
            $positiveScore += $weight;

            if (in_array($keyword, ['amazônia', 'amazonia', 'desmatamento', 'queimadas', 'queimada', 'incêndio florestal', 'garimpo ilegal', 'terra indígena', 'terras indígenas', 'povos indígenas', 'floresta amazônica', 'seca amazônica'], true)) {
                $amazonScore += $weight;
            }

            if (in_array($keyword, ['brasil', 'brasileiro', 'brasileira', 'ibama', 'icmbio', 'inpe', 'pará', 'amazonas', 'rondônia', 'acre', 'roraima', 'amapá', 'maranhão', 'tocantins', 'mato grosso', 'mato grosso do sul', 'goiás', 'bahia', 'brasília', 'pantanal', 'cerrado', 'mata atlântica', 'caatinga'], true)) {
                $brazilScore += $weight;
            }
        }

        foreach ($negativeWeights as $keyword => $weight) {
            if (! $this->containsKeyword($haystack, $keyword)) {
                continue;
            }

            $matchedNegative[] = $keyword;
            $negativeScore += $weight;
        }

        foreach ($themeTerms as $term) {
            if (! in_array($term, $matchedPositive, true)) {
                $matchedPositive[] = $term;
            }
        }

        $contextBonus = 0;
        if ($this->containsAny($haystack, ['brasil', 'brasileiro', 'brasileira'])) {
            $contextBonus += 3;
        }

        if ($this->containsAny($haystack, ['amazônia', 'amazonia', 'floresta amazônica', 'amazônia legal'])) {
            $contextBonus += 4;
        }

        if ($this->containsAny($haystack, ['ibama', 'icmbio', 'inpe', 'mma'])) {
            $contextBonus += 3;
        }

        $environmentalScore = min(40, $positiveScore);
        $amazonScore = min(30, $amazonScore + (int) round(count(array_intersect($themeTerms, ['amazônia', 'amazonia', 'queimadas', 'queimada', 'desmatamento', 'garimpo ilegal', 'terra indígena', 'terras indígenas', 'povos indígenas'])) * 2));
        $brazilScore = min(30, $brazilScore + (int) round(count(array_intersect($themeTerms, ['ibama', 'icmbio', 'inpe', 'pará', 'amazonas', 'rondônia', 'acre', 'roraima', 'amapá', 'maranhão', 'tocantins', 'mato grosso', 'mato grosso do sul', 'goiás', 'bahia', 'brasília'])) * 2));
        $finalScore = max(0, $environmentalScore + $amazonScore + $brazilScore + $contextBonus - $negativeScore);

        return [
            'environmental_score' => $environmentalScore,
            'amazon_score' => $amazonScore,
            'brazil_score' => $brazilScore,
            'negative_score' => $negativeScore,
            'positive_score' => $positiveScore,
            'final_score' => $finalScore,
            'matched_positive' => array_values(array_unique($matchedPositive)),
            'matched_negative' => array_values(array_unique($matchedNegative)),
        ];
    }

    private function hasBrazilianContextStrict(string $haystack): bool
    {
        return $this->containsAny($haystack, $this->brazilEntities);
    }

    private function hasBrazilianEnvironmentalFocus(string $haystack): bool
    {
        $hasBrazil = $this->containsAny($haystack, $this->brazilEntities);
        $hasBiomesOrAmazon = $this->containsAny($haystack, [
            'amazônia', 'amazonia', 'floresta amazônica', 'amazônia legal',
            'cerrado', 'pantanal', 'mata atlântica', 'caatinga', 'pampa',
            'desmatamento', 'queimadas', 'queimada', 'incêndio florestal',
        ]);

        return $hasBrazil && $hasBiomesOrAmazon;
    }

    /**
     * @param array<int, string> $keywords
     * @return array<int, string>
     */
    private function matchedTerms(string $haystack, array $keywords): array
    {
        $matches = [];

        foreach ($keywords as $keyword) {
            $needle = mb_strtolower(trim($keyword));
            if ($needle !== '' && $this->containsKeyword($haystack, $needle)) {
                $matches[] = $needle;
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{decision:string,reason:string,row:null,scores:array<string,int>,matched_terms:array<int,string>,blocked_terms:array<int,string>,theme_terms:array<int,string>}
     */
    private function emptyAssessment(string $decision, string $reason, array $extra = []): array
    {
        return [
            'decision' => $decision,
            'reason' => $reason,
            'row' => null,
            'scores' => [
                'environmental_score' => 0,
                'amazon_score' => 0,
                'brazil_score' => 0,
                'negative_score' => 0,
                'positive_score' => 0,
                'final_score' => 0,
            ],
            'matched_terms' => array_values(array_map('strval', $extra['theme_terms'] ?? [])),
            'blocked_terms' => array_values(array_map('strval', $extra['blocked_terms'] ?? [])),
            'theme_terms' => array_values(array_map('strval', $extra['theme_terms'] ?? [])),
        ];
    }

    private function normalizeDate(mixed $date): ?CarbonImmutable
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array{environmental:int,amazon:int,brazil:int,keyword_matches:int,negative:int,total:int} $scores
     */
    private function qualityScore(string $title, ?string $excerpt, ?CarbonImmutable $publishedAt, array $scores): int
    {
        $score = 30;

        if (mb_strlen($title) >= 40) {
            $score += 10;
        }

        if ($excerpt !== null && mb_strlen($excerpt) >= 120) {
            $score += 15;
        }

        if ($publishedAt !== null) {
            $score += 10;
        }

        $score += (int) round($scores['total'] * 0.45);

        return min(100, $score);
    }

    private function resolveCategory(string $title, ?string $excerpt, string $fallback): ?string
    {
        $validTaxonomy = [
            'amazonia', 'queimadas', 'desmatamento', 'clima',
            'biodiversidade', 'povos-indigenas', 'fiscalizacao',
            'sustentabilidade', 'crimes-ambientais',
        ];

        $haystack = mb_strtolower(trim($title . ' ' . ($excerpt ?? '')));

        $rules = [
            'crimes-ambientais' => ['crime ambiental', 'garimpo ilegal', 'madeira ilegal', 'grilagem', 'extração ilegal', 'tráfico de animais', 'illegal mining', 'illegal logging', 'wildlife trafficking', 'land grabbing', 'minerais críticos', 'pl dos minerais', 'vazamento de petróleo', 'oil spill', 'derramamento de petróleo'],
            'queimadas'         => ['queimada', 'incêndio florestal', 'fogo florestal', 'focos de calor', 'brigadista', 'queimadas no pantanal', 'incêndios no cerrado'],
            'desmatamento'      => ['desmatamento', 'supressão vegetal', 'deter', 'desflorestamento', 'derrubada da floresta', 'exploração madeireira'],
            'clima'             => ['clima', 'climática', 'climático', 'aquecimento global', 'emissões de co2', 'emissões de carbono', 'cop30', 'cop 30', 'crise hídrica', 'seca', 'chuvas extremas', 'evento climático extremo'],
            'biodiversidade'    => ['biodiversidade', 'fauna', 'flora', 'espécie ameaçada', 'extinção', 'ecossistema', 'reintrodução de espécies', 'conservação da natureza'],
            'povos-indigenas'   => ['povos indígenas', 'terras indígenas', 'etnia', 'aldeia', 'indigena', 'indígena', 'indigenous', 'indigenous people', 'native people', 'tribal'],
            'fiscalizacao'      => ['fiscalização', 'ibama', 'icmbio', 'multa ambiental', 'operação policial', 'environmental enforcement', 'environmental fine', 'environmental crime', 'conservation law'],
            'sustentabilidade'  => ['sustentabilidade', 'bioeconomia', 'energia limpa', 'conservação ambiental', 'reflorestamento', 'agricultura sustentável', 'transição energética', 'economia verde'],
            'amazonia'          => ['amazônia', 'amazonia', 'floresta amazônica', 'pan-amazônia', 'amazônia legal'],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, mb_strtolower($keyword))) {
                    return $category;
                }
            }
        }

        // Only accept fallback if it is already in our taxonomy (source-level config category)
        $cleanFallback = mb_strtolower(trim($fallback));
        if ($cleanFallback !== '' && in_array($cleanFallback, $validTaxonomy, true)) {
            return $cleanFallback;
        }

        return null;
    }

    private function buildHaystack(string $title, ?string $excerpt): string
    {
        return mb_strtolower(trim($title . ' ' . ($excerpt ?? '')));
    }

    /**
     * @return array{environmental:int,amazon:int,brazil:int,keyword_matches:int,negative:int,total:int}
     */
    private function scoreEditorialRelevance(string $haystack): array
    {
        $weightMap = [
            'amazônia' => 5,
            'amazonia' => 5,
            'desmatamento' => 5,
            'queimada' => 4,
            'queimadas' => 4,
            'clima' => 3,
            'mudanças climáticas' => 4,
            'mudança climática' => 4,
            'meio ambiente' => 3,
            'ambiental' => 2,
            'ibama' => 4,
            'icmbio' => 4,
            'inpe' => 4,
            'indígena' => 3,
            'indigena' => 3,
            'sustentabilidade' => 3,
            'biodiversidade' => 2,
            'pantanal' => 3,
            'cerrado' => 3,
            'mata atlântica' => 3,
            'caatinga' => 3,
            'bioma' => 2,
            'garimpo' => 3,
            'fiscalização ambiental' => 4,
            'licenciamento ambiental' => 3,
            'unidade de conservação' => 4,
            'preservação ambiental' => 3,
            'crise climática' => 3,
        ];

        $totalWeighted = 0;
        $matches = 0;
        $amazonMatches = 0;
        $brazilMatches = 0;

        foreach ($weightMap as $keyword => $weight) {
            if (str_contains($haystack, $keyword)) {
                $totalWeighted += $weight;
                $matches++;

                if (in_array($keyword, ['amazônia', 'amazonia', 'desmatamento', 'queimada', 'queimadas', 'garimpo'], true)) {
                    $amazonMatches++;
                }

                if (in_array($keyword, ['ibama', 'icmbio', 'inpe', 'indígena', 'indigena', 'pantanal', 'cerrado', 'mata atlântica', 'caatinga'], true)) {
                    $brazilMatches++;
                }
            }
        }

        $negativeWeights = [
            'alaska' => 6,
            'megatsunami' => 8,
            'tsunami' => 6,
            'europa' => 4,
            'ásia' => 4,
            'asia' => 4,
            'estados unidos' => 4,
            'china' => 4,
            'trump' => 5,
            'guerra' => 5,
            'bolsa de valores' => 5,
            'esportes' => 4,
            'entretenimento' => 4,
            'celebridades' => 4,
            'turismo internacional' => 4,
            'furacão' => 5,
            'hurricane' => 5,
            'terremoto' => 6,
            'nasa' => 3,
            'astronomia' => 5,
        ];

        $negativeScore = 0;
        foreach ($negativeWeights as $keyword => $weight) {
            if (str_contains($haystack, $keyword)) {
                $negativeScore += $weight;
            }
        }

        $environmental = min(50, $totalWeighted * 3);
        $amazon = min(20, $amazonMatches * 5);
        $brazil = min(30, $brazilMatches * 6);
        $finalTotal = max(0, $totalWeighted - $negativeScore);

        return [
            'environmental' => $environmental,
            'amazon' => $amazon,
            'brazil' => $brazil,
            'keyword_matches' => $matches,
            'negative' => $negativeScore,
            'total' => $finalTotal,
        ];
    }

    /**
     * @param array{environmental:int,amazon:int,brazil:int,keyword_matches:int,negative:int,total:int} $scores
     */
    private function passesEditorialGate(array $scores): bool
    {
        if ($scores['keyword_matches'] < 1) {
            return false;
        }

        if ($scores['negative'] >= 6) {
            return false;
        }

        if ($scores['amazon'] < 5 && $scores['brazil'] < 6) {
            return false;
        }

        return $scores['total'] >= 6;
    }

    private function isBlockedContext(string $haystack): bool
    {
        $blocked = [
            'alaska', 'tsunami', 'aviação', 'aviation', 'airline', 'airport', 'voo internacional',
            'earthquake', 'terremoto', 'war', 'guerra', 'ukraine', 'ucrânia', 'israel', 'gaza',
            'otán', 'nato', 'hurricane', 'furacão', 'tornado',
            'premier league', 'nba', 'nfl', 'fórmula 1', 'formula 1', 'tennis',
            'champions league', 'libertadores', 'brasileirão', 'futebol', 'gol da rodada',
            'novela', 'celebridade', 'bbb', 'reality show', 'fofoca', 'horóscopo',
            'bolsa de valores', 'mercado financeiro', 'criptomoeda', 'gossip',
            'guerra na ucrânia', 'gaza', 'otan', 'putin', 'trump',
            'mega-sena', 'loteria', 'powerball',
        ];

        $allowIfAlsoEnvironmental = [
            'petróleo', 'energia', 'clima', 'desmatamento', 'amazônia', 'queimadas',
            'meio ambiente', 'sustentabilidade', 'ibama', 'icmbio', 'inpe',
        ];

        foreach ($blocked as $term) {
            if (str_contains($haystack, $term)) {
                return ! $this->containsAny($haystack, $allowIfAlsoEnvironmental);
            }
        }

        return false;
    }

    private function hasMandatoryEnvironmentalTheme(string $haystack): bool
    {
        return $this->containsAny($haystack, $this->mandatoryThemeKeywords);
    }

    /**
     * @param array<string, mixed> $source
     */
    private function hasBrazilianContext(string $haystack, string $url, array $source): bool
    {
        $host = mb_strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $sourceName = mb_strtolower((string) ($source['name'] ?? ''));

        $brazilEntities = [
            'brasil', 'brasileiro', 'brasileira', 'amazônia legal', 'amazônia',
            'ibama', 'icmbio', 'inpe', 'mma', 'ministério do meio ambiente',
            'pantanal', 'cerrado', 'mata atlântica', 'caatinga', 'pampa',
            'pará', 'amazonas', 'rondônia', 'acre', 'roraima', 'amapá', 'maranhão',
            'tocantins', 'mato grosso', 'mato grosso do sul', 'paraná', 'são paulo',
            'rio de janeiro', 'minas gerais', 'bahia', 'goiás', 'brasília',
        ];

        if ($host !== '' && (str_ends_with($host, '.br') || $host === 'gov.br' || str_ends_with($host, '.gov.br'))) {
            return true;
        }

        if ($sourceName !== '' && str_contains($sourceName, 'brasil')) {
            return true;
        }

        return $this->containsAny($haystack, $brazilEntities);
    }

    /**
     * @param array<int, string> $keywords
     */
    private function countKeywordMatches(string $haystack, array $keywords): int
    {
        $count = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, mb_strtolower($keyword))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<int, string> $keywords
     */
    private function containsAny(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($this->containsKeyword($haystack, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function containsKeyword(string $haystack, string $keyword): bool
    {
        $needle = mb_strtolower(trim($keyword));
        if ($needle === '') {
            return false;
        }

        $pattern = '/(^|[^\p{L}\p{N}])' . preg_quote($needle, '/') . '([^\p{L}\p{N}]|$)/u';

        return preg_match($pattern, $haystack) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function matchedContextualBlacklist(string $haystack): array
    {
        $matches = [];
        $strongEnvironmentalContext = $this->containsAny($haystack, $this->amazonStrongKeywords);

        foreach ($this->contextualBlacklistGroups as $group => $terms) {
            $groupMatches = $this->matchedTerms($haystack, $terms);
            if ($groupMatches === []) {
                continue;
            }

            $shouldBlock = match ($group) {
                'crime_urbano', 'esporte', 'entretenimento' => true,
                'politica_aleatoria' => count($groupMatches) >= 2 && ! $strongEnvironmentalContext,
                'internacional_irrelevante' => (count($groupMatches) >= 2) || (! $strongEnvironmentalContext && count($groupMatches) >= 1),
                default => count($groupMatches) >= 1,
            };

            if (! $shouldBlock) {
                continue;
            }

            $matches[] = 'group:' . $group;
            foreach ($groupMatches as $term) {
                $matches[] = $term;
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @param array<string, mixed> $source
     */
    private function resolveSourceTrustScore(array $source): int
    {
        $key = mb_strtolower(trim((string) ($source['key'] ?? '')));
        $name = mb_strtolower(trim((string) ($source['name'] ?? '')));
        $configuredScore = $source['trust_score'] ?? null;

        if (is_int($configuredScore) || is_float($configuredScore) || (is_string($configuredScore) && is_numeric($configuredScore))) {
            $baseScore = max(0, min(100, (int) round((float) $configuredScore)));

            return $this->applyNoisePenaltyToTrust($baseScore, $key);
        }

        $map = (array) config('boitanews.trust_scores', []);
        if ($key !== '' && isset($map[$key])) {
            $baseScore = max(0, min(100, (int) round((float) $map[$key])));

            return $this->applyNoisePenaltyToTrust($baseScore, $key);
        }

        if ($name !== '' && isset($map[$name])) {
            $baseScore = max(0, min(100, (int) round((float) $map[$name])));

            return $this->applyNoisePenaltyToTrust($baseScore, $key);
        }

        return $this->applyNoisePenaltyToTrust(30, $key);
    }

    private function applyNoisePenaltyToTrust(int $baseTrust, string $sourceKey): int
    {
        if ($sourceKey === '' || ! (bool) config('boitanews.trust_auto_adjust.enabled', true)) {
            return $baseTrust;
        }

        $connection = (string) config('boitanews.connection', 'pgsql');
        $windowDays = max(1, (int) config('boitanews.trust_auto_adjust.window_days', 14));
        $maxPenalty = max(0, (int) config('boitanews.trust_auto_adjust.max_penalty', 12));
        $minSamples = max(10, (int) config('boitanews.trust_auto_adjust.min_samples', 40));
        $neutralNoiseRate = max(0.2, min(0.9, (float) config('boitanews.trust_auto_adjust.neutral_noise_rate', 0.50)));

        $penalty = (int) Cache::remember(
            'boitanews:trust-penalty:' . $sourceKey,
            now()->addMinutes(10),
            function () use ($connection, $sourceKey, $windowDays, $maxPenalty, $minSamples, $neutralNoiseRate): int {
                try {
                    $rows = DB::connection($connection)
                        ->table('portal.noticias_curation_events')
                        ->selectRaw("COUNT(*) as total, SUM(CASE WHEN decision = 'discarded' THEN 1 ELSE 0 END) as discarded")
                        ->where('source_key', $sourceKey)
                        ->where('happened_at', '>=', now()->utc()->subDays($windowDays))
                        ->first();

                    $total = (int) ($rows->total ?? 0);
                    $discarded = (int) ($rows->discarded ?? 0);
                    if ($total < $minSamples) {
                        return 0;
                    }

                    $noiseRate = $discarded / max(1, $total);
                    if ($noiseRate <= $neutralNoiseRate) {
                        return 0;
                    }

                    $overNoise = min(1.0, ($noiseRate - $neutralNoiseRate) / max(0.01, (1.0 - $neutralNoiseRate)));

                    return min($maxPenalty, (int) round($overNoise * $maxPenalty));
                } catch (Throwable) {
                    return 0;
                }
            }
        );

        return max(0, min(100, $baseTrust - $penalty));
    }

    private function strictFloorByTrust(int $trustScore): int
    {
        return match (true) {
            $trustScore >= 95 => 24,
            $trustScore >= 90 => 26,
            $trustScore >= 80 => 28,
            $trustScore >= 70 => 32,
            $trustScore >= 60 => 36,
            default => 40,
        };
    }

    private function softFloorByTrust(int $trustScore): int
    {
        return match (true) {
            $trustScore >= 95 => 14,
            $trustScore >= 90 => 16,
            $trustScore >= 80 => 18,
            $trustScore >= 70 => 22,
            $trustScore >= 60 => 24,
            default => 28,
        };
    }

}
