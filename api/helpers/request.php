<?php

declare(strict_types=1);

function requireMethod(string ...$methods): void
{
    $allowed = array_map('strtoupper', $methods);
    $current = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if (!in_array($current, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        jsonResponse(false, 'Method not allowed.', [], 405);
    }
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        jsonResponse(false, 'Invalid JSON request.', [], 400);
    }

    return $data;
}

function queryString(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

function queryInt(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int) $_GET[$key] : $default;
}
