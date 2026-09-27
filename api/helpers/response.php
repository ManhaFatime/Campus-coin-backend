<?php

declare(strict_types=1);

function jsonResponse(bool $success, string $message, array $data = [], int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}
