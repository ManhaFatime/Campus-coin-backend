<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();
$type = queryString('type');

if ($type !== '' && !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(false, 'Invalid category type.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $sql = 'SELECT id, user_id, name, type, is_default, is_active, created_at
            FROM categories
            WHERE is_active = 1
              AND (user_id IS NULL OR user_id = :user_id)';
    $params = ['user_id' => $userId];

    if ($type !== '') {
        $sql .= ' AND type = :type';
        $params['type'] = $type;
    }

    $sql .= ' ORDER BY is_default DESC, name ASC';
    $statement = $db->prepare($sql);
    $statement->execute($params);

    jsonResponse(true, 'Categories loaded successfully.', ['categories' => $statement->fetchAll()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Categories could not be loaded.', [], 500);
}
