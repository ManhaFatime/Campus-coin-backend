<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare(
        'SELECT id, insight_month, summary_text, advice_text, source, created_at
         FROM insights WHERE user_id = :user_id
         ORDER BY insight_month DESC, created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);
    jsonResponse(true, 'Insights loaded successfully.', ['insights' => $statement->fetchAll()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Insights could not be loaded.', [], 500);
}
