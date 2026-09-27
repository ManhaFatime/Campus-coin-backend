<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Campus Coin',
    'env' => getenv('APP_ENV') ?: 'local',
    'base_url' => rtrim(getenv('APP_BASE_URL') ?: 'http://localhost:8080', '/'),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Karachi',
    'mail_from' => getenv('MAIL_FROM') ?: 'no-reply@campuscoin.local',

    // Frontend origins allowed to call the API with cookies (comma-separated in CORS_ALLOWED_ORIGINS).
    'cors_allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'https://campus-coin.vercel.app,http://localhost:8080,http://localhost:5173')
    ))),

    // Also allow this project's Vercel preview/production URLs, e.g. campus-coin-git-main-xyz.vercel.app.
    'cors_allowed_origin_pattern' => getenv('CORS_ALLOWED_ORIGIN_PATTERN') ?: '#^https://campus-coin(-[a-z0-9-]+)?\.vercel\.app$#',
];
