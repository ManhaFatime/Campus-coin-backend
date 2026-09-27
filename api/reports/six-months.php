<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();

try {
    $db = getDatabaseConnection();
    $start = date('Y-m-01', strtotime('-5 months'));
    $statement = $db->prepare(
        'SELECT DATE_FORMAT(transaction_date, "%Y-%m") AS month,
                COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS expense
         FROM transactions
         WHERE user_id = :user_id AND transaction_date >= :start
         GROUP BY DATE_FORMAT(transaction_date, "%Y-%m")
         ORDER BY month ASC'
    );
    $statement->execute(['user_id' => $userId, 'start' => $start]);
    $indexed = [];
    foreach ($statement->fetchAll() as $row) {
        $indexed[$row['month']] = $row;
    }

    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-{$i} months"));
        $months[] = [
            'month' => $key,
            'income' => isset($indexed[$key]) ? (float) $indexed[$key]['income'] : 0,
            'expense' => isset($indexed[$key]) ? (float) $indexed[$key]['expense'] : 0,
        ];
    }

    jsonResponse(true, 'Six-month report generated successfully.', ['months' => $months]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Six-month report could not be generated.', [], 500);
}
