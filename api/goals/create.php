<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();
$data = getJsonInput();
$title = trim((string) ($data['title'] ?? ''));
$target = positiveAmount($data['target_amount'] ?? null);
$saved = nonNegativeAmount($data['saved_amount'] ?? 0);
$targetDate = trim((string) ($data['target_date'] ?? ''));

if ($title === '' || strlen($title) > 120 || $target === null || $saved === null || ($targetDate !== '' && !validDate($targetDate))) {
    jsonResponse(false, 'Please provide valid savings goal details.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $status = $saved >= $target ? 'completed' : 'active';
    $statement = $db->prepare(
        'INSERT INTO savings_goals (user_id, title, target_amount, saved_amount, target_date, status)
         VALUES (:user_id, :title, :target, :saved, :target_date, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => $title,
        'target' => $target,
        'saved' => $saved,
        'target_date' => $targetDate !== '' ? $targetDate : null,
        'status' => $status,
    ]);
    jsonResponse(true, 'Savings goal created successfully.', ['id' => (int) $db->lastInsertId()], 201);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Savings goal could not be created.', [], 500);
}
