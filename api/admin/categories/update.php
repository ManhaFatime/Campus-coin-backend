<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('PUT', 'PATCH', 'POST');
requireAdmin();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
$name = trim((string) ($data['name'] ?? ''));
$type = trim((string) ($data['type'] ?? ''));
$active = array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1;

if ($id < 1 || $name === '' || strlen($name) > 80 || !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(false, 'Valid category details are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare(
        'UPDATE categories SET name = :name, type = :type, is_active = :active
         WHERE id = :id AND user_id IS NULL AND is_default = 1'
    );
    $statement->execute(['name' => $name, 'type' => $type, 'active' => $active, 'id' => $id]);
    if ($statement->rowCount() === 0) {
        $exists = $db->prepare('SELECT id FROM categories WHERE id = :id AND user_id IS NULL');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) jsonResponse(false, 'Default category not found.', [], 404);
    }
    jsonResponse(true, 'Default category updated successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Default category could not be updated.', [], 500);
}
