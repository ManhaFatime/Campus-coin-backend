<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('GET');
requireAdmin();

try {
    $db = getDatabaseConnection();
    $statement = $db->query(
        'SELECT id, name, type, is_default, is_active, created_at
         FROM categories
         WHERE user_id IS NULL
         ORDER BY type ASC, name ASC'
    );
    jsonResponse(true, 'Default categories loaded successfully.', ['categories' => $statement->fetchAll()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Default categories could not be loaded.', [], 500);
}
