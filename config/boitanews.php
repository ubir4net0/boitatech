<?php

return [
    'connection' => env('BOITANEWS_DB_CONNECTION', 'pgsql'),
    'ssl_verify' => env('BOITANEWS_SSL_VERIFY', true),
    'allow_insecure_fallback' => env('BOITANEWS_ALLOW_INSECURE_FALLBACK', true),

    'frontend' => [
        'brand' => 'BoitaNews',
        'logo_path' => '/BoitaNews.png',
    ],

    'admin' => [
        'token' => env('BOITANEWS_ADMIN_TOKEN', ''),
    ],

    'ingestion' => [
        'default_timeout_seconds' => (int) env('BOITANEWS_TIMEOUT_SECONDS', 20),
        'default_max_payload_bytes' => (int) env('BOITANEWS_MAX_PAYLOAD_BYTES', 3_500_000),
        'default_retry_attempts' => (int) env('BOITANEWS_RETRY_ATTEMPTS', 3),
        'default_backoff_ms' => (int) env('BOITANEWS_RETRY_BACKOFF_MS', 500),
        'circuit_breaker_failures' => (int) env('BOITANEWS_CIRCUIT_BREAKER_FAILURES', 3),
        'circuit_breaker_cooldown_seconds' => (int) env('BOITANEWS_CIRCUIT_BREAKER_COOLDOWN', 300),
        'schedule_minutes' => (int) env('BOITANEWS_SCHEDULE_MINUTES', 30),
    ],

    'metadata' => [
        'fetch_timeout_seconds' => (int) env('BOITANEWS_METADATA_TIMEOUT_SECONDS', 10),
        'max_html_bytes' => (int) env('BOITANEWS_METADATA_MAX_HTML_BYTES', 3_000_000),
        'cache_seconds' => (int) env('BOITANEWS_METADATA_CACHE_SECONDS', 21_600),
    ],

    'images' => [
        'fetch_timeout_seconds' => (int) env('BOITANEWS_IMAGE_TIMEOUT_SECONDS', 10),
        'max_download_bytes' => (int) env('BOITANEWS_IMAGE_MAX_DOWNLOAD_BYTES', 8_000_000),
        'min_width' => (int) env('BOITANEWS_IMAGE_MIN_WIDTH', 480),
        'min_height' => (int) env('BOITANEWS_IMAGE_MIN_HEIGHT', 260),
    ],

    'security' => [
        'allowed_source_domains' => [
            'g1.globo.com',
            'agenciabrasil.ebc.com.br',
            'news.mongabay.com',
            'greenpeace.org',
            'brasildefato.com.br',
            'gov.br',
            'inpe.br',
            'socioambiental.org',
            'imazon.org.br',
            'ipam.org.br',
            'mapbiomas.org',
            'oc.eco.br',
            'wwf.org.br',
            'oeco.org.br',
            'infoamazonia.org',
            'sumauma.com',
            'jornal.usp.br',
            'sbtnews.sbt.com.br',
            'cnnbrasil.com.br',
            'folha.uol.com.br',
            'estadao.com.br',
            'uol.com.br',
            'terra.com.br',
            'reuters.com',
            'bbc.co.uk',
            'bbc.com',
            'edition.cnn.com',
            'nationalgeographic.com',
            'earthobservatory.nasa.gov',
            'unep.org',
            'news.un.org',
            'climatechangenews.com',
            'e360.yale.edu',
            'mongabay.com',
            'earth.org',
            'carbonbrief.org',
            'theguardian.com',
        ],
        'allowed_image_domains' => [
            'glbimg.com',
            'cdn.cnnbrasil.com.br',
            'conteudo.imguol.com.br',
            'uol.com.br',
            'akamaized.net',
            'cloudfront.net',
            'gov.br',
            'inpe.br',
            'mongabay.com',
            'mongabayimages.s3.amazonaws.com',
        ],
    ],

    'queue' => [
        'fetch' => env('BOITANEWS_QUEUE_FETCH', 'news-fetch'),
        'process' => env('BOITANEWS_QUEUE_PROCESS', 'news-process'),
    ],

    'allowed_sources' => [
        'g1-meio-ambiente',
        'agencia-brasil-meio-ambiente',
        'mongabay-brasil',
        'inpe-noticias',
        'wwf-brasil',
        'icmbio',
        'ibama',
        'infoamazonia',
    ],

    'required_keywords' => [
        'desmatamento',
        'amazônia',
        'amazonia',
        'queimada',
        'queimadas',
        'ibama',
        'inpe',
        'floresta',
        'garimpo',
        'ambiental',
        'meio ambiente',
        'mudanças climáticas',
        'mudanca climatica',
        'bioeconomia',
    ],

    'cache_ttl_seconds' => [
        'homepage' => 120,
        'index' => 120,
        'featured' => 180,
        'recent' => 120,
        'categories' => 300,
    ],

    'dedup' => [
        'title_similarity_threshold' => (float) env('BOITANEWS_TITLE_SIMILARITY_THRESHOLD', 92),
        'lookback_days' => (int) env('BOITANEWS_DEDUP_LOOKBACK_DAYS', 7),
        'candidate_limit' => (int) env('BOITANEWS_DEDUP_CANDIDATE_LIMIT', 80),
    ],

    'nlp' => [
        'fasttext_model_path' => env('BOITANEWS_FASTTEXT_MODEL_PATH', storage_path('app/boitanews/fasttext-model.json')),
        'min_ambient_probability' => (float) env('BOITANEWS_NLP_MIN_AMBIENT_PROBABILITY', 0.70),
        'review_probability_floor' => (float) env('BOITANEWS_NLP_REVIEW_PROBABILITY_FLOOR', 0.55),
    ],

    'trust_scores' => [
        // ultra confiáveis
        'ibama' => 100,
        'inpe-noticias' => 100,
        'icmbio' => 100,
        'mongabay-brasil' => 95,
        'infoamazonia' => 95,
        'observatorio-do-clima' => 92,
        'isa-socioambiental' => 90,
        // médias
        'g1-meio-ambiente' => 70,
        'agencia-brasil-meio-ambiente' => 65,
        'cnn-brasil-clima' => 60,
        'uol-meio-ambiente' => 60,
        // genéricas
        'default' => 30,
    ],

    'trust_auto_adjust' => [
        'enabled' => (bool) env('BOITANEWS_TRUST_AUTO_ADJUST', true),
        'window_days' => (int) env('BOITANEWS_TRUST_AUTO_ADJUST_WINDOW_DAYS', 14),
        'max_penalty' => (int) env('BOITANEWS_TRUST_AUTO_ADJUST_MAX_PENALTY', 12),
        'min_samples' => (int) env('BOITANEWS_TRUST_AUTO_ADJUST_MIN_SAMPLES', 40),
        'neutral_noise_rate' => (float) env('BOITANEWS_TRUST_AUTO_ADJUST_NEUTRAL_NOISE_RATE', 0.50),
    ],

    'categories' => [
        'amazonia',
        'queimadas',
        'desmatamento',
        'clima',
        'biodiversidade',
        'povos-indigenas',
        'fiscalizacao',
        'sustentabilidade',
        'crimes-ambientais',
    ],

    'sources' => [
        // BRASIL
        'g1-meio-ambiente' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'G1 Meio Ambiente',
            'url' => 'https://g1.globo.com/dynamo/meio-ambiente/rss2.xml', 'category' => 'amazonia',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 40, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 600,
            'trust_score' => 70,
        ],
        'agencia-brasil-meio-ambiente' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Agência Brasil Meio Ambiente',
            'url' => 'https://agenciabrasil.ebc.com.br/rss/geral/feed.xml', 'category' => 'fiscalizacao',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 35, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
            'trust_score' => 65,
        ],
        'mongabay-brasil' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Mongabay Brasil',
            'url' => 'https://news.mongabay.com/pt/feed/', 'category' => 'biodiversidade',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 30, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
            'trust_score' => 95,
        ],
        'greenpeace-brasil' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Greenpeace Brasil',
            'url' => 'https://www.greenpeace.org/brasil/feed/', 'category' => 'clima',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
        ],
        'brasil-de-fato-meio-ambiente' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'Brasil de Fato Meio Ambiente',
            'url' => 'https://www.brasildefato.com.br/rss', 'category' => 'fiscalizacao',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
            'trust_score' => 30,
        ],
        'icmbio' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'ICMBio',
            'url' => 'https://www.gov.br/icmbio/pt-br/assuntos/noticias/rss', 'category' => 'biodiversidade',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 30,
            'timeout_seconds' => 25, 'retry_attempts' => 3, 'retry_backoff_ms' => 900,
            'trust_score' => 100,
        ],
        'ibama' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'IBAMA',
            'url' => 'https://www.gov.br/ibama/pt-br/assuntos/noticias/rss', 'category' => 'fiscalizacao',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 30,
            'timeout_seconds' => 25, 'retry_attempts' => 3, 'retry_backoff_ms' => 900,
            'trust_score' => 100,
        ],
        'govbr-meio-ambiente' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'gov.br Meio Ambiente',
            'url' => 'https://www.gov.br/mma/pt-br/assuntos/noticias/rss', 'category' => 'sustentabilidade',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 30,
            'timeout_seconds' => 25, 'retry_attempts' => 3, 'retry_backoff_ms' => 900,
        ],
        'inpe-noticias' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'INPE Notícias',
            'url' => 'http://www.inpe.br/noticias/rss.php', 'category' => 'desmatamento',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 30,
            'timeout_seconds' => 25, 'retry_attempts' => 3, 'retry_backoff_ms' => 1000,
            'trust_score' => 100,
        ],
        'isa-socioambiental' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'ISA Socioambiental',
            'url' => 'https://www.socioambiental.org/pt-br/rss.xml', 'category' => 'povos-indigenas',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
            'trust_score' => 90,
        ],
        'imazon' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Imazon',
            'url' => 'https://imazon.org.br/feed/', 'category' => 'desmatamento',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'ipam-amazonia' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'IPAM Amazônia',
            'url' => 'https://ipam.org.br/feed/', 'category' => 'amazonia',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'mapbiomas' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'MapBiomas',
            'url' => 'https://mapbiomas.org/feed', 'category' => 'desmatamento',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'observatorio-do-clima' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Observatório do Clima',
            'url' => 'https://oc.eco.br/feed/', 'category' => 'clima',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
            'trust_score' => 92,
        ],
        'wwf-brasil' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'WWF Brasil',
            'url' => 'https://www.wwf.org.br/?feed=rss2', 'category' => 'sustentabilidade',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
        ],
        'oeco' => [
            'type' => 'rss', 'enabled' => true, 'name' => '((o))eco',
            'url' => 'https://oeco.org.br/feed/', 'category' => 'biodiversidade',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'infoamazonia' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'InfoAmazonia',
            'url' => 'https://infoamazonia.org/feed/', 'category' => 'amazonia',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 22, 'retry_attempts' => 3, 'retry_backoff_ms' => 800,
            'trust_score' => 95,
        ],
        'sumauma' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Sumaúma',
            'url' => 'https://sumauma.com/feed/', 'category' => 'amazonia',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 22, 'retry_attempts' => 3, 'retry_backoff_ms' => 800,
        ],
        'jornal-usp-ambiente' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Jornal da USP Ambiente',
            'url' => 'https://jornal.usp.br/editorias/sustentabilidade/feed/', 'category' => 'sustentabilidade',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 45,
            'timeout_seconds' => 22, 'retry_attempts' => 3, 'retry_backoff_ms' => 800,
        ],
        'sbt-meio-ambiente' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'SBT Meio Ambiente',
            'url' => 'https://www.sbtnews.com.br/rss', 'category' => 'clima',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
            'trust_score' => 30,
        ],
        'cnn-brasil-clima' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'CNN Brasil Clima',
            'url' => 'https://www.cnnbrasil.com.br/tudo-sobre/meio-ambiente/feed/', 'category' => 'clima',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
            'trust_score' => 60,
        ],
        'folha-ambiente' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Folha Ambiente',
            'url' => 'https://feeds.folha.uol.com.br/ambiente/rss091.xml', 'category' => 'sustentabilidade',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'estadao-sustentabilidade' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Estadão Sustentabilidade',
            'url' => 'https://www.estadao.com.br/sustentabilidade/rss/', 'category' => 'sustentabilidade',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'uol-meio-ambiente' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'UOL Meio Ambiente',
            'url' => 'https://rss.uol.com.br/feed/noticias.xml', 'category' => 'clima',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
            'trust_score' => 60,
        ],
        'terra-planeta' => [
            'type' => 'rss', 'enabled' => true, 'name' => 'Terra Planeta',
            'url' => 'https://www.terra.com.br/planeta/rss.xml', 'category' => 'clima',
            'language' => 'pt-BR', 'country' => 'BR', 'max_items' => 20, 'refresh_minutes' => 60,
        ],

        // INTERNACIONAIS
        'reuters-climate' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'Reuters Climate',
            'url' => 'https://www.reuters.com/world/environment/rss', 'category' => 'clima',
            'language' => 'en', 'country' => 'US', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
        ],
        'bbc-climate' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'BBC Climate',
            'url' => 'https://feeds.bbci.co.uk/news/science_and_environment/rss.xml', 'category' => 'clima',
            'language' => 'en', 'country' => 'GB', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
        ],
        'cnn-climate' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'CNN Climate',
            'url' => 'https://edition.cnn.com/specials/world/cnn-climate/rss.xml', 'category' => 'clima',
            'language' => 'en', 'country' => 'US', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'national-geographic' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'National Geographic',
            'url' => 'https://www.nationalgeographic.com/pages/topic/latest-stories/rss', 'category' => 'biodiversidade',
            'language' => 'en', 'country' => 'US', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'nasa-earth-observatory' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'NASA Earth Observatory',
            'url' => 'https://earthobservatory.nasa.gov/feeds/eo.rss', 'category' => 'clima',
            'language' => 'en', 'country' => 'US', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
        ],
        'unep' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'UNEP',
            'url' => 'https://www.unep.org/news-and-stories/rss.xml', 'category' => 'sustentabilidade',
            'language' => 'en', 'country' => 'UN', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'onu-climate-news' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'ONU Climate News',
            'url' => 'https://news.un.org/feed/subscribe/en/news/topic/climate-change/feed/rss.xml', 'category' => 'clima',
            'language' => 'en', 'country' => 'UN', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
        ],
        'climate-home-news' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'Climate Home News',
            'url' => 'https://www.climatechangenews.com/feed/', 'category' => 'clima',
            'language' => 'en', 'country' => 'GB', 'max_items' => 20, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
        ],
        'yale-environment-360' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'Yale Environment 360',
            'url' => 'https://e360.yale.edu/feed', 'category' => 'clima',
            'language' => 'en', 'country' => 'US', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'mongabay-global' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'Mongabay Global',
            'url' => 'https://news.mongabay.com/feed/', 'category' => 'biodiversidade',
            'language' => 'en', 'country' => 'US', 'max_items' => 25, 'refresh_minutes' => 60,
        ],
        'earth-org' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'Earth.org',
            'url' => 'https://earth.org/feed/', 'category' => 'clima',
            'language' => 'en', 'country' => 'INT', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'carbon-brief' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'Carbon Brief',
            'url' => 'https://www.carbonbrief.org/feed/', 'category' => 'clima',
            'language' => 'en', 'country' => 'GB', 'max_items' => 20, 'refresh_minutes' => 60,
        ],
        'the-guardian-environment' => [
            'type' => 'rss', 'enabled' => false, 'name' => 'The Guardian Environment',
            'url' => 'https://www.theguardian.com/environment/rss', 'category' => 'clima',
            'language' => 'en', 'country' => 'GB', 'max_items' => 25, 'refresh_minutes' => 30,
            'timeout_seconds' => 20, 'retry_attempts' => 3, 'retry_backoff_ms' => 700,
        ],
    ],
];
