<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();
$month = monthStart(queryString('month', date('Y-m')));
if (!$month) {
    jsonResponse(false, 'Month must use YYYY-MM format.', [], 422);
}
$monthEnd = date('Y-m-t', strtotime($month));

try {
    $db = getDatabaseConnection();
    $totals = $db->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS expense
         FROM transactions
         WHERE user_id = :user_id AND transaction_date BETWEEN :start AND :end'
    );
    $totals->execute(['user_id' => $userId, 'start' => $month, 'end' => $monthEnd]);
    $summary = $totals->fetch();

    $categories = $db->prepare(
        'SELECT c.id AS category_id, c.name, t.type, SUM(t.amount) AS total, COUNT(*) AS transaction_count
         FROM transactions t
         JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = :user_id AND t.transaction_date BETWEEN :start AND :end
         GROUP BY c.id, c.name, t.type
         ORDER BY t.type ASC, total DESC'
    );
    $categories->execute(['user_id' => $userId, 'start' => $month, 'end' => $monthEnd]);

    $transactions = $db->prepare(
        'SELECT t.id, t.amount, t.type, t.description, t.transaction_date, c.name AS category_name
         FROM transactions t
         JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = :user_id AND t.transaction_date BETWEEN :start AND :end
         ORDER BY t.transaction_date DESC, t.id DESC'
    );
    $transactions->execute(['user_id' => $userId, 'start' => $month, 'end' => $monthEnd]);

    $income = (float) $summary['income'];
    $expense = (float) $summary['expense'];
    jsonResponse(true, 'Monthly report generated successfully.', [
        'month' => $month,
        'summary' => ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense],
        'categories' => $categories->fetchAll(),
        'transactions' => $transactions->fetchAll(),
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Monthly report could not be generated.', [], 500);
}
