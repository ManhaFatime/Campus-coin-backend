<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('PUT', 'PATCH', 'POST');
$userId = requireStudent();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
$name = trim((string) ($data['name'] ?? ''));
$type = trim((string) ($data['type'] ?? ''));

if ($id < 1 || $name === '' || strlen($name) > 80 || !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(false, 'Valid category details are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $query = $db->prepare('SELECT id FROM categories WHERE id = :id AND user_id = :user_id AND is_default = 0 LIMIT 1');
    $query->execute(['id' => $id, 'user_id' => $userId]);
    if (!$query->fetch()) {
        jsonResponse(false, 'Personal category not found.', [], 404);
    }

    $update = $db->prepare('UPDATE categories SET name = :name, type = :type WHERE id = :id AND user_id = :user_id');
    $update->execute(['name' => $name, 'type' => $type, 'id' => $id, 'user_id' => $userId]);
    jsonResponse(true, 'Category updated successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Category could not be updated.', [], 500);
}
