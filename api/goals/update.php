<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('PUT', 'PATCH', 'POST');
$userId = requireStudent();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
$title = trim((string) ($data['title'] ?? ''));
$target = positiveAmount($data['target_amount'] ?? null);
$saved = nonNegativeAmount($data['saved_amount'] ?? 0);
$targetDate = trim((string) ($data['target_date'] ?? ''));
$status = trim((string) ($data['status'] ?? 'active'));

if ($id < 1 || $title === '' || $target === null || $saved === null || !in_array($status, ['active', 'completed', 'cancelled'], true) || ($targetDate !== '' && !validDate($targetDate))) {
    jsonResponse(false, 'Please provide valid savings goal details.', [], 422);
}
if ($saved >= $target && $status === 'active') {
    $status = 'completed';
}

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare(
        'UPDATE savings_goals
         SET title = :title, target_amount = :target, saved_amount = :saved,
             target_date = :target_date, status = :status
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'title' => $title,
        'target' => $target,
        'saved' => $saved,
        'target_date' => $targetDate !== '' ? $targetDate : null,
        'status' => $status,
        'id' => $id,
        'user_id' => $userId,
    ]);
    if ($statement->rowCount() === 0) {
        $exists = $db->prepare('SELECT id FROM savings_goals WHERE id = :id AND user_id = :user_id');
        $exists->execute(['id' => $id, 'user_id' => $userId]);
        if (!$exists->fetch()) {
            jsonResponse(false, 'Savings goal not found.', [], 404);
        }
    }
    jsonResponse(true, 'Savings goal updated successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Savings goal could not be updated.', [], 500);
}
