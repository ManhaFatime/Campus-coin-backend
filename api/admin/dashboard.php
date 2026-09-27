<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
requireAdmin();

try {
    $db = getDatabaseConnection();
    $summary = [
        'total_users' => (int) $db->query('SELECT COUNT(*) FROM users WHERE role = "student"')->fetchColumn(),
        'active_users' => (int) $db->query('SELECT COUNT(*) FROM users WHERE role = "student" AND is_active = 1')->fetchColumn(),
        'total_transactions' => (int) $db->query('SELECT COUNT(*) FROM transactions')->fetchColumn(),
        'new_users_this_month' => (int) $db->query('SELECT COUNT(*) FROM users WHERE role = "student" AND created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")')->fetchColumn(),
    ];

    $growth = $db->query(
        'SELECT DATE_FORMAT(created_at, "%Y-%m") AS month, COUNT(*) AS users
         FROM users
         WHERE role = "student" AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), "%Y-%m-01"), INTERVAL 5 MONTH)
         GROUP BY DATE_FORMAT(created_at, "%Y-%m") ORDER BY month ASC'
    )->fetchAll();

    $categories = $db->query(
        'SELECT c.name, COUNT(t.id) AS usage_count, COALESCE(SUM(t.amount), 0) AS total_amount
         FROM categories c LEFT JOIN transactions t ON t.category_id = c.id
         WHERE c.type = "expense"
         GROUP BY c.id, c.name ORDER BY usage_count DESC, total_amount DESC LIMIT 7'
    )->fetchAll();

    jsonResponse(true, 'Administrator dashboard loaded successfully.', [
        'summary' => $summary,
        'user_growth' => $growth,
        'most_used_categories' => $categories,
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Administrator dashboard could not be loaded.', [], 500);
}
