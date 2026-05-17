<?php

return [
    'policy_version' => env('LGPD_POLICY_VERSION', '2026.05'),
    'dpo_email' => env('LGPD_DPO_EMAIL', env('MAIL_FROM_ADDRESS', 'privacidade@boitatech.local')),
    'retention' => [
        'consents_days' => (int) env('LGPD_RETENTION_CONSENTS_DAYS', 365),
        'requests_days' => (int) env('LGPD_RETENTION_REQUESTS_DAYS', 730),
        'confirmations_days' => (int) env('LGPD_RETENTION_CONFIRMATIONS_DAYS', 180),
    ],
    'request_due_days' => (int) env('LGPD_REQUEST_DUE_DAYS', 15),
];
