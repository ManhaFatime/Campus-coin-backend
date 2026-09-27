<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('DELETE', 'POST');
requireAdmin();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
if ($id < 1) jsonResponse(false, 'Category id is required.', [], 422);

try {
    $db = getDatabaseConnection();
    $used = $db->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = :id');
    $used->execute(['id' => $id]);
    if ((int) $used->fetchColumn() > 0) {
        $statement = $db->prepare('UPDATE categories SET is_active = 0 WHERE id = :id AND user_id IS NULL');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() === 0) jsonResponse(false, 'Default category not found.', [], 404);
        jsonResponse(true, 'Category is in use, so it was disabled instead of permanently deleted.');
    }

    $statement = $db->prepare('DELETE FROM categories WHERE id = :id AND user_id IS NULL');
    $statement->execute(['id' => $id]);
    if ($statement->rowCount() === 0) jsonResponse(false, 'Default category not found.', [], 404);
    jsonResponse(true, 'Default category deleted successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Default category could not be deleted.', [], 500);
}
