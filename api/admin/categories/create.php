<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('POST');
requireAdmin();
$data = getJsonInput();
$name = trim((string) ($data['name'] ?? ''));
$type = trim((string) ($data['type'] ?? ''));

if ($name === '' || strlen($name) > 80 || !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(false, 'A valid category name and type are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $exists = $db->prepare('SELECT id FROM categories WHERE user_id IS NULL AND name = :name AND type = :type LIMIT 1');
    $exists->execute(['name' => $name, 'type' => $type]);
    if ($exists->fetch()) jsonResponse(false, 'This default category already exists.', [], 409);

    $statement = $db->prepare('INSERT INTO categories (user_id, name, type, is_default, is_active) VALUES (NULL, :name, :type, 1, 1)');
    $statement->execute(['name' => $name, 'type' => $type]);
    jsonResponse(true, 'Default category created successfully.', ['id' => (int) $db->lastInsertId()], 201);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Default category could not be created.', [], 500);
}
