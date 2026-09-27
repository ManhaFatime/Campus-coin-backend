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
    $daily = $db->prepare(
        'SELECT transaction_date AS date, SUM(amount) AS expense
         FROM transactions
         WHERE user_id = :user_id AND type = "expense"
           AND transaction_date BETWEEN :start AND :end
         GROUP BY transaction_date ORDER BY transaction_date ASC'
    );
    $daily->execute(['user_id' => $userId, 'start' => $month, 'end' => $monthEnd]);

    $weekly = $db->prepare(
        'SELECT WEEK(transaction_date, 1) AS week_number,
                MIN(transaction_date) AS week_start,
                MAX(transaction_date) AS week_end,
                SUM(amount) AS expense
         FROM transactions
         WHERE user_id = :user_id AND type = "expense"
           AND transaction_date BETWEEN :start AND :end
         GROUP BY WEEK(transaction_date, 1)
         ORDER BY week_number ASC'
    );
    $weekly->execute(['user_id' => $userId, 'start' => $month, 'end' => $monthEnd]);

    jsonResponse(true, 'Daily and weekly spending summaries generated successfully.', [
        'month' => $month,
        'daily' => $daily->fetchAll(),
        'weekly' => $weekly->fetchAll(),
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Spending summaries could not be generated.', [], 500);
}
