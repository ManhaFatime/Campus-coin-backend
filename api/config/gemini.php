<?php

declare(strict_types=1);

/*
 * CampusCoin Gemini configuration.
 *
 * The API key is never stored in this file. Provide it with either:
 *   - the GEMINI_API_KEY environment variable (recommended for hosting), or
 *   - config/gemini.local.php (git-ignored), returning ['api_key' => '...'].
 *     See config/gemini.local.example.php.
 */

$localGeminiConfig = is_file(__DIR__ . '/gemini.local.php')
    ? (array) require __DIR__ . '/gemini.local.php'
    : [];

return [
    'api_key' => getenv('GEMINI_API_KEY') ?: (string) ($localGeminiConfig['api_key'] ?? ''),

    // Primary high-quality model.
    'model' => getenv('GEMINI_MODEL') ?: 'gemini-3.8-flash',

    // Used only when the primary model is temporarily overloaded/rate-limited.
    'fallback_model' => getenv('GEMINI_FALLBACK_MODEL') ?: 'gemini-3.5-flash-lite',
];
