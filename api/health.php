<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
requireMethod('GET');

try {
    $db = getDatabaseConnection();
    $categories = (int) $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();

    jsonResponse(true, 'Campus Coin API and database are working correctly.', [
        'database' => getenv('DB_NAME') ?: 'campus_coin_db',
        'categories' => $categories,
        'php_version' => PHP_VERSION,
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'The API could not connect to the database.', [], 500);
}
