<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('DELETE', 'POST');
$userId = requireStudent();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
if ($id < 1) {
    jsonResponse(false, 'Budget id is required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare('DELETE FROM budgets WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $id, 'user_id' => $userId]);
    if ($statement->rowCount() === 0) {
        jsonResponse(false, 'Budget not found.', [], 404);
    }
    jsonResponse(true, 'Budget deleted successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Budget could not be deleted.', [], 500);
}
