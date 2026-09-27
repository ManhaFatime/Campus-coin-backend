<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('DELETE', 'POST');
$userId = requireStudent();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);

if ($id < 1) {
    jsonResponse(false, 'Category id is required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $query = $db->prepare('SELECT id FROM categories WHERE id = :id AND user_id = :user_id AND is_default = 0 LIMIT 1');
    $query->execute(['id' => $id, 'user_id' => $userId]);
    if (!$query->fetch()) {
        jsonResponse(false, 'Personal category not found.', [], 404);
    }

    $used = $db->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = :id');
    $used->execute(['id' => $id]);
    if ((int) $used->fetchColumn() > 0) {
        jsonResponse(false, 'This category is used by transactions and cannot be deleted. You can rename it instead.', [], 409);
    }

    $db->prepare('DELETE FROM categories WHERE id = :id AND user_id = :user_id')->execute(['id' => $id, 'user_id' => $userId]);
    jsonResponse(true, 'Category deleted successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Category could not be deleted.', [], 500);
}
