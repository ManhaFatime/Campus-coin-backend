<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();
try {
    $db = getDatabaseConnection();
    $statement = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
    $statement->execute(['user_id' => $userId]);
    jsonResponse(true, 'All notifications marked as read.', ['updated' => $statement->rowCount()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Notifications could not be updated.', [], 500);
}
