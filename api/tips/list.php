<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();
$includeDismissed = queryInt('include_dismissed', 0) === 1;

try {
    $db = getDatabaseConnection();
    $sql = 'SELECT id, title, message, potential_saving, priority, is_pinned, is_dismissed, created_at
            FROM saving_tips WHERE user_id = :user_id';
    if (!$includeDismissed) {
        $sql .= ' AND is_dismissed = 0';
    }
    $sql .= ' ORDER BY is_pinned DESC, FIELD(priority, "high", "medium", "low"), potential_saving DESC, created_at DESC';
    $statement = $db->prepare($sql);
    $statement->execute(['user_id' => $userId]);
    jsonResponse(true, 'Saving tips loaded successfully.', ['tips' => $statement->fetchAll()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Saving tips could not be loaded.', [], 500);
}
