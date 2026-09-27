<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();
$data = getJsonInput();
$name = trim((string) ($data['name'] ?? ''));
$type = trim((string) ($data['type'] ?? ''));

if ($name === '' || strlen($name) > 80 || !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(false, 'A valid category name and type are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $exists = $db->prepare(
        'SELECT id FROM categories
         WHERE name = :name AND type = :type
           AND (user_id = :user_id OR user_id IS NULL)
           AND is_active = 1 LIMIT 1'
    );
    $exists->execute(['name' => $name, 'type' => $type, 'user_id' => $userId]);
    if ($exists->fetch()) {
        jsonResponse(false, 'This category already exists.', [], 409);
    }

    $statement = $db->prepare(
        'INSERT INTO categories (user_id, name, type, is_default)
         VALUES (:user_id, :name, :type, 0)'
    );
    $statement->execute(['user_id' => $userId, 'name' => $name, 'type' => $type]);

    jsonResponse(true, 'Category created successfully.', ['id' => (int) $db->lastInsertId()], 201);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Category could not be created.', [], 500);
}
