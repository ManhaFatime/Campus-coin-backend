<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare(
        'SELECT id, title, target_amount, saved_amount, target_date, status, created_at, updated_at
         FROM savings_goals WHERE user_id = :user_id ORDER BY status = "active" DESC, created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);
    jsonResponse(true, 'Savings goals loaded successfully.', ['goals' => $statement->fetchAll()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Savings goals could not be loaded.', [], 500);
}
