<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST', 'PUT', 'PATCH');
$userId = requireStudent();
$data = getJsonInput();
$categoryId = (int) ($data['category_id'] ?? 0);
$month = monthStart(trim((string) ($data['month'] ?? date('Y-m'))));
$limit = positiveAmount($data['limit_amount'] ?? null);

if ($categoryId < 1 || !$month || $limit === null) {
    jsonResponse(false, 'Valid category, month and budget amount are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $category = getAccessibleCategory($db, $categoryId, $userId);
    if (!$category || $category['type'] !== 'expense') {
        jsonResponse(false, 'Budgets can only be created for accessible expense categories.', [], 422);
    }

    $statement = $db->prepare(
        'INSERT INTO budgets (user_id, category_id, budget_month, limit_amount)
         VALUES (:user_id, :category_id, :budget_month, :limit_amount)
         ON DUPLICATE KEY UPDATE limit_amount = VALUES(limit_amount), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'user_id' => $userId,
        'category_id' => $categoryId,
        'budget_month' => $month,
        'limit_amount' => $limit,
    ]);

    refreshBudgetAlert($db, $userId, $categoryId, $month);
    jsonResponse(true, 'Budget saved successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Budget could not be saved.', [], 500);
}
