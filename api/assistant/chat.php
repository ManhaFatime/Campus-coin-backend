<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireMethod('POST');

$data = getJsonInput();

$message = trim((string) ($data['message'] ?? ''));
$language = trim((string) ($data['language'] ?? 'english'));
$history = $data['history'] ?? [];

if ($message === '') {
    jsonResponse(false, 'Please type or speak a message first.', [], 422);
}

$messageLength = function_exists('mb_strlen')
    ? mb_strlen($message)
    : strlen($message);

if ($messageLength > 1500) {
    jsonResponse(false, 'Please keep each message under 1500 characters.', [], 422);
}

if (!in_array($language, ['english', 'urdu', 'roman-urdu'], true)) {
    $language = 'english';
}

if (!is_array($history)) {
    $history = [];
}

/*
 * Public/session rate guard.
 */
$now = time();
$windowSeconds = 600;
$maxRequests = 40;

$requestTimes = $_SESSION['campus_ai_request_times'] ?? [];

if (!is_array($requestTimes)) {
    $requestTimes = [];
}

$requestTimes = array_values(array_filter(
    $requestTimes,
    static fn ($timestamp): bool =>
        is_int($timestamp) && $timestamp >= ($now - $windowSeconds)
));

if (count($requestTimes) >= $maxRequests) {
    jsonResponse(
        false,
        'Campus AI is receiving too many requests from this session. Please wait a few minutes and try again.',
        [],
        429
    );
}

$requestTimes[] = $now;
$_SESSION['campus_ai_request_times'] = $requestTimes;

$config = require dirname(__DIR__) . '/config/gemini.php';

$apiKey = trim((string) ($config['api_key'] ?? ''));
$primaryModel = trim((string) ($config['model'] ?? 'gemini-3.8-flash'));
$fallbackModel = trim((string) ($config['fallback_model'] ?? 'gemini-3.5-flash-lite'));

if ($apiKey === '' || $apiKey === 'PASTE_YOUR_GEMINI_API_KEY_HERE') {
    jsonResponse(
        false,
        'Campus AI is not configured yet. Add your Gemini API key in api/config/gemini.php or the GEMINI_API_KEY environment variable.',
        [],
        503
    );
}

if ($primaryModel === '') {
    $primaryModel = 'gemini-3.8-flash';
}

if ($fallbackModel === '') {
    $fallbackModel = 'gemini-3.5-flash-lite';
}

$languageInstruction = match ($language) {
    'urdu' => 'Reply primarily in natural Urdu script unless the user explicitly asks for another language.',
    'roman-urdu' => 'Reply primarily in clear Roman Urdu using Latin letters unless the user explicitly asks for another language.',
    default => 'Reply primarily in clear English unless the user explicitly asks for another language.',
};

$systemInstruction = <<<PROMPT
You are Campus AI, the friendly AI money companion inside CampusCoin, a student budgeting and money-management website.

Your role:
- Help students understand budgeting, saving, spending habits, goals, allowances, recurring expenses, and basic financial planning.
- You may also answer normal practical questions when useful, but keep a helpful student-friendly tone.
- Be concise by default: usually 60-150 words unless the user asks for detail.
- Use simple explanations, short steps, and realistic student examples.
- Never claim that you can see the user's live CampusCoin balance, transactions, budgets, profile, or account data unless that information is explicitly included in the conversation.
- Do not invent account values or pretend an action was completed inside CampusCoin.
- Do not ask for passwords, bank credentials, card numbers, OTPs, API keys, or other secrets.
- For high-stakes financial, legal, medical, or emergency situations, clearly state the limits of general guidance and encourage appropriate professional help when needed.
- If the user writes in Urdu or Roman Urdu, understand it naturally and answer in the requested style.
- Keep formatting readable in plain text; avoid excessive markdown.

{$languageInstruction}
PROMPT;

$contents = [];

/* Keep only recent valid conversation turns. */
$history = array_slice($history, -12);

foreach ($history as $item) {
    if (!is_array($item)) {
        continue;
    }

    $role = (string) ($item['role'] ?? '');
    $text = trim((string) ($item['text'] ?? ''));

    if ($text === '' || !in_array($role, ['user', 'assistant'], true)) {
        continue;
    }

    $textLength = function_exists('mb_strlen')
        ? mb_strlen($text)
        : strlen($text);

    if ($textLength > 2500) {
        $text = function_exists('mb_substr')
            ? mb_substr($text, 0, 2500)
            : substr($text, 0, 2500);
    }

    $contents[] = [
        'role' => $role === 'assistant' ? 'model' : 'user',
        'parts' => [
            ['text' => $text],
        ],
    ];
}

$contents[] = [
    'role' => 'user',
    'parts' => [
        ['text' => $message],
    ],
];

$payload = [
    'system_instruction' => [
        'parts' => [
            ['text' => $systemInstruction],
        ],
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.55,
        'topP' => 0.9,
        'maxOutputTokens' => 650,
    ],
];

if (!function_exists('curl_init')) {
    jsonResponse(
        false,
        'PHP cURL is not enabled. Enable the curl extension in XAMPP before using Campus AI.',
        [],
        500
    );
}

/**
 * Send one request to Gemini.
 *
 * @return array{
 *   success: bool,
 *   status: int,
 *   decoded: array<string,mixed>|null,
 *   raw: string,
 *   curl_error: string
 * }
 */
function callGemini(
    string $apiKey,
    string $model,
    array $payload
): array {
    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
        rawurlencode($model)
    );

    $curl = curl_init($url);

    if ($curl === false) {
        return [
            'success' => false,
            'status' => 0,
            'decoded' => null,
            'raw' => '',
            'curl_error' => 'Could not initialize cURL.',
        ];
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
    ]);

    $rawResponse = curl_exec($curl);
    $curlError = curl_error($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    curl_close($curl);

    $raw = is_string($rawResponse) ? $rawResponse : '';
    $decoded = $raw !== '' ? json_decode($raw, true) : null;

    return [
        'success' =>
            $rawResponse !== false &&
            $curlError === '' &&
            $statusCode >= 200 &&
            $statusCode < 300 &&
            is_array($decoded),
        'status' => $statusCode,
        'decoded' => is_array($decoded) ? $decoded : null,
        'raw' => $raw,
        'curl_error' => $curlError,
    ];
}

/**
 * Transient Gemini errors should be retried.
 * Google recommends exponential backoff for 429/503.
 */
function isRetryableGeminiStatus(int $status): bool
{
    return in_array($status, [0, 429, 500, 502, 503, 504], true);
}

/**
 * Retry a model up to 3 times with short exponential backoff.
 */
function callGeminiWithRetry(
    string $apiKey,
    string $model,
    array $payload,
    int $maxAttempts = 3
): array {
    $last = [
        'success' => false,
        'status' => 0,
        'decoded' => null,
        'raw' => '',
        'curl_error' => '',
    ];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $last = callGemini($apiKey, $model, $payload);

        if ($last['success']) {
            $last['model'] = $model;
            $last['attempt'] = $attempt;
            return $last;
        }

        if (!isRetryableGeminiStatus((int) $last['status'])) {
            break;
        }

        if ($attempt < $maxAttempts) {
            /*
             * 450ms -> 1000ms, with small random jitter.
             * Keeps the UI responsive while handling brief 429/503 spikes.
             */
            $baseDelayMs = $attempt === 1 ? 450 : 1000;
            $jitterMs = random_int(50, 220);
            usleep(($baseDelayMs + $jitterMs) * 1000);
        }
    }

    $last['model'] = $model;
    return $last;
}

/*
 * 1) Try the primary production model with automatic retries.
 */
$result = callGeminiWithRetry(
    $apiKey,
    $primaryModel,
    $payload,
    3
);

/*
 * 2) If Gemini is still temporarily busy/rate-limited, use a lighter
 *    stable Flash model as a fallback instead of immediately failing.
 */
if (
    !$result['success'] &&
    isRetryableGeminiStatus((int) $result['status']) &&
    $fallbackModel !== $primaryModel
) {
    $result = callGeminiWithRetry(
        $apiKey,
        $fallbackModel,
        $payload,
        2
    );
}

if (!$result['success']) {
    $statusCode = (int) ($result['status'] ?? 0);
    $decoded = $result['decoded'];
    $rawResponse = (string) ($result['raw'] ?? '');
    $curlError = trim((string) ($result['curl_error'] ?? ''));

    if ($curlError !== '') {
        error_log('Campus AI Gemini cURL error: ' . $curlError);
    }

    $geminiMessage = '';

    if (is_array($decoded)) {
        $geminiMessage = trim(
            (string) ($decoded['error']['message'] ?? '')
        );
    }

    error_log(sprintf(
        'Campus AI Gemini failure HTTP %d using %s: %s',
        $statusCode,
        (string) ($result['model'] ?? 'unknown-model'),
        $geminiMessage !== ''
            ? $geminiMessage
            : ($rawResponse !== '' ? $rawResponse : $curlError)
    ));

    $safeMessage = match ($statusCode) {
        400 => 'Gemini rejected the request. Please try a shorter or simpler message.',
        401, 403 => 'Campus AI authentication failed. Please check the Gemini API key.',
        404 => 'The configured Gemini model is unavailable. Please check api/config/gemini.php.',
        429 => 'Campus AI is busy right now because the Gemini request limit was reached. Please wait a few seconds and try again.',
        500, 502, 503, 504, 0 => 'Campus AI is temporarily busy. Please try again in a moment.',
        default => 'Campus AI could not complete the request. Please try again shortly.',
    };

    jsonResponse(false, $safeMessage, [], 502);
}

$decoded = $result['decoded'];

if (!is_array($decoded)) {
    jsonResponse(false, 'Campus AI received an invalid response from Gemini.', [], 502);
}

$replyParts = $decoded['candidates'][0]['content']['parts'] ?? [];
$reply = '';

if (is_array($replyParts)) {
    foreach ($replyParts as $part) {
        if (is_array($part) && isset($part['text'])) {
            $reply .= (string) $part['text'];
        }
    }
}

$reply = trim($reply);

if ($reply === '') {
    $blockReason = trim((string) ($decoded['promptFeedback']['blockReason'] ?? ''));

    if ($blockReason !== '') {
        jsonResponse(
            false,
            'Campus AI could not answer that request. Please rephrase it and try again.',
            [],
            422
        );
    }

    jsonResponse(false, 'Campus AI returned an empty response. Please try again.', [], 502);
}

jsonResponse(true, 'Campus AI response generated.', [
    'reply' => $reply,
    'model' => (string) ($result['model'] ?? $primaryModel),
    'language' => $language,
]);
