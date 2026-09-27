<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();

try {
    $db = getDatabaseConnection();
    processRecurringTransactions($db, $userId);

    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    $totals = $db->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS expense
         FROM transactions
         WHERE user_id = :user_id AND transaction_date BETWEEN :start AND :end'
    );
    $totals->execute(['user_id' => $userId, 'start' => $monthStart, 'end' => $monthEnd]);
    $summary = $totals->fetch();
    $income = (float) $summary['income'];
    $expense = (float) $summary['expense'];

    $topCategory = $db->prepare(
        'SELECT c.id, c.name, SUM(t.amount) AS total
         FROM transactions t
         JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = :user_id AND t.type = "expense"
           AND t.transaction_date BETWEEN :start AND :end
         GROUP BY c.id, c.name
         ORDER BY total DESC
         LIMIT 1'
    );
    $topCategory->execute(['user_id' => $userId, 'start' => $monthStart, 'end' => $monthEnd]);

    $recent = $db->prepare(
        'SELECT t.id, t.amount, t.type, t.description, t.transaction_date,
                c.name AS category_name
         FROM transactions t
         JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = :user_id
         ORDER BY t.transaction_date DESC, t.id DESC
         LIMIT 5'
    );
    $recent->execute(['user_id' => $userId]);

    $budget = $db->prepare(
        'SELECT COALESCE(SUM(limit_amount), 0) AS total_budget
         FROM budgets
         WHERE user_id = :user_id AND budget_month = :month'
    );
    $budget->execute(['user_id' => $userId, 'month' => $monthStart]);
    $totalBudget = (float) $budget->fetchColumn();

    $unread = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
    $unread->execute(['user_id' => $userId]);

    $tips = $db->prepare(
        'SELECT id, title, message, potential_saving, priority, is_pinned
         FROM saving_tips
         WHERE user_id = :user_id AND is_dismissed = 0
         ORDER BY is_pinned DESC, FIELD(priority, "high", "medium", "low"), potential_saving DESC, created_at DESC
         LIMIT 3'
    );
    $tips->execute(['user_id' => $userId]);

    $announcements = $db->query(
        'SELECT id, title, message, created_at
         FROM announcements
         WHERE is_active = 1
         ORDER BY created_at DESC LIMIT 3'
    )->fetchAll();

    jsonResponse(true, 'Dashboard loaded successfully.', [
        'month' => date('Y-m'),
        'income' => $income,
        'expense' => $expense,
        'balance' => $income - $expense,
        'top_category' => $topCategory->fetch() ?: null,
        'budget' => [
            'limit' => $totalBudget,
            'spent' => $expense,
            'percentage' => $totalBudget > 0 ? round(($expense / $totalBudget) * 100, 1) : 0,
        ],
        'recent_transactions' => $recent->fetchAll(),
        'tips' => $tips->fetchAll(),
        'unread_notifications' => (int) $unread->fetchColumn(),
        'announcements' => $announcements,
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Dashboard could not be loaded.', [], 500);
}
