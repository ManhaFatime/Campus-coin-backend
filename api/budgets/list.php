<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();
$month = monthStart(queryString('month', date('Y-m')));
if (!$month) {
    jsonResponse(false, 'Month must use YYYY-MM format.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $monthEnd = date('Y-m-t', strtotime($month));
    $statement = $db->prepare(
        'SELECT b.id, b.category_id, c.name AS category_name, b.budget_month,
                b.limit_amount,
                COALESCE(SUM(t.amount), 0) AS spent
         FROM budgets b
         JOIN categories c ON c.id = b.category_id
         LEFT JOIN transactions t
           ON t.user_id = b.user_id
          AND t.category_id = b.category_id
          AND t.type = "expense"
          AND t.transaction_date BETWEEN :month_start AND :month_end
         WHERE b.user_id = :user_id AND b.budget_month = :budget_month
         GROUP BY b.id, b.category_id, c.name, b.budget_month, b.limit_amount
         ORDER BY c.name ASC'
    );
    $statement->execute([
        'month_start' => $month,
        'month_end' => $monthEnd,
        'user_id' => $userId,
        'budget_month' => $month,
    ]);
    $rows = $statement->fetchAll();

    foreach ($rows as &$row) {
        $limit = (float) $row['limit_amount'];
        $spent = (float) $row['spent'];
        $row['limit_amount'] = $limit;
        $row['spent'] = $spent;
        $row['remaining'] = max(0, $limit - $spent);
        $row['percentage'] = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0;
    }

    jsonResponse(true, 'Budgets loaded successfully.', ['month' => $month, 'budgets' => $rows]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Budgets could not be loaded.', [], 500);
}
