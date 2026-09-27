<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('DELETE', 'POST');
$userId = requireStudent();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
if ($id < 1) {
    jsonResponse(false, 'Savings goal id is required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare('DELETE FROM savings_goals WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $id, 'user_id' => $userId]);
    if ($statement->rowCount() === 0) {
        jsonResponse(false, 'Savings goal not found.', [], 404);
    }
    jsonResponse(true, 'Savings goal deleted successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Savings goal could not be deleted.', [], 500);
}
