<?php

declare(strict_types=1);

$appConfig = require __DIR__ . '/app.php';
date_default_timezone_set($appConfig['timezone']);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// CORS: the frontend is hosted on a different domain (Vercel) and sends cookies.
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originAllowed = $requestOrigin !== '' && (
    in_array($requestOrigin, $appConfig['cors_allowed_origins'], true)
    || preg_match($appConfig['cors_allowed_origin_pattern'], $requestOrigin) === 1
);

if ($originAllowed) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
}
header('Vary: Origin', false);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code($originAllowed ? 204 : 403);
    exit;
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

// Cross-site cookies must be SameSite=None + Secure; plain-HTTP local dev keeps Lax.
session_name('campus_coin_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => $isHttps ? 'None' : 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once dirname(__DIR__) . '/helpers/response.php';
require_once dirname(__DIR__) . '/helpers/request.php';
require_once dirname(__DIR__) . '/helpers/validation.php';
require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/helpers/categories.php';
require_once dirname(__DIR__) . '/helpers/audit.php';
require_once dirname(__DIR__) . '/helpers/budget_alerts.php';
require_once dirname(__DIR__) . '/helpers/content_notifications.php';
require_once dirname(__DIR__) . '/helpers/recurring.php';
require_once dirname(__DIR__) . '/helpers/mail.php';
