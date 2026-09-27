<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
requireAdmin();

try {
    $db = getDatabaseConnection();
    $monthly = $db->query(
        'SELECT DATE_FORMAT(transaction_date, "%Y-%m") AS month,
                COUNT(*) AS transactions,
                SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) AS expense
         FROM transactions
         WHERE transaction_date >= DATE_SUB(DATE_FORMAT(CURDATE(), "%Y-%m-01"), INTERVAL 5 MONTH)
         GROUP BY DATE_FORMAT(transaction_date, "%Y-%m") ORDER BY month ASC'
    )->fetchAll();

    $categories = $db->query(
        'SELECT c.name, c.type, COUNT(t.id) AS usage_count, COALESCE(SUM(t.amount), 0) AS total_amount
         FROM categories c LEFT JOIN transactions t ON t.category_id = c.id
         GROUP BY c.id, c.name, c.type ORDER BY usage_count DESC LIMIT 12'
    )->fetchAll();

    jsonResponse(true, 'Usage statistics loaded successfully.', [
        'monthly_activity' => $monthly,
        'category_usage' => $categories,
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Usage statistics could not be loaded.', [], 500);
}
