<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cesium' => [
        'ion_token' => env('CESIUM_ION_TOKEN', ''),
        'photorealistic_mode' => env('CESIUM_PHOTOREALISTIC_MODE', 'off'),
        'photorealistic_tileset_url' => env('CESIUM_PHOTOREALISTIC_TILESET_URL', ''),
    ],

    'terrabrasilis' => [
        'wfs_url' => env('TERRABRASILIS_WFS_URL', 'https://terrabrasilis.dpi.inpe.br/geoserver/ows'),
        'current_layer' => env('TERRABRASILIS_CURRENT_LAYER', 'active-fire-today'),
        'historical_layer' => env('TERRABRASILIS_HISTORICAL_LAYER', 'bdqueimadas:focos'),
        'deter_layer' => env('TERRABRASILIS_DETER_LAYER', 'deter:desmatamento_100'),
        'deter_date_field' => env('TERRABRASILIS_DETER_DATE_FIELD', 'data_alerta'),
        'ssl_verify' => env('TERRABRASILIS_SSL_VERIFY', true),
    ],

    'cptec' => [
        'risco_fogo_geojson_url' => env('CPTEC_RISCO_FOGO_GEOJSON_URL', ''),
        'risk_source' => env('CPTEC_RISCO_FOGO_SOURCE', 'INPE/CPTEC'),
        'ssl_verify' => env('CPTEC_SSL_VERIFY', true),
    ],

];
