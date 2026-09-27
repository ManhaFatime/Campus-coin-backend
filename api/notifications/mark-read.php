<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST', 'PATCH');
$userId = requireStudent();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
if ($id < 1) jsonResponse(false, 'Notification id is required.', [], 422);

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $id, 'user_id' => $userId]);
    if ($statement->rowCount() === 0) jsonResponse(false, 'Notification not found.', [], 404);
    jsonResponse(true, 'Notification marked as read.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Notification could not be updated.', [], 500);
}
