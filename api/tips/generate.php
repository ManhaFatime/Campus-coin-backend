<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();

try {
    $db = getDatabaseConnection();
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    // Refresh automatically generated tips while keeping anything the student pinned.
    $db->prepare('DELETE FROM saving_tips WHERE user_id = :user_id AND is_pinned = 0 AND is_dismissed = 0')
        ->execute(['user_id' => $userId]);

    $rows = $db->prepare(
        'SELECT c.id, c.name,
                COALESCE(SUM(t.amount), 0) AS spent,
                COALESCE(b.limit_amount, 0) AS budget
         FROM categories c
         LEFT JOIN transactions t
           ON t.category_id = c.id AND t.user_id = :user_tx
          AND t.type = "expense" AND t.transaction_date BETWEEN :start AND :end
         LEFT JOIN budgets b
           ON b.category_id = c.id AND b.user_id = :user_budget AND b.budget_month = :month
         WHERE c.type = "expense" AND c.is_active = 1
           AND (c.user_id IS NULL OR c.user_id = :user_category)
         GROUP BY c.id, c.name, b.limit_amount
         HAVING spent > 0
         ORDER BY spent DESC'
    );
    $rows->execute([
        'user_tx' => $userId,
        'start' => $monthStart,
        'end' => $monthEnd,
        'user_budget' => $userId,
        'month' => $monthStart,
        'user_category' => $userId,
    ]);

    $created = [];
    foreach (array_slice($rows->fetchAll(), 0, 5) as $row) {
        $spent = (float) $row['spent'];
        $budget = (float) $row['budget'];
        $potential = $budget > 0 && $spent > $budget ? $spent - $budget : round($spent * 0.10, 2);
        $priority = $budget > 0 && $spent >= $budget ? 'high' : ($budget > 0 && $spent >= $budget * 0.8 ? 'medium' : 'low');
        $title = $priority === 'high' ? 'Reduce ' . $row['name'] . ' spending' : 'Small saving opportunity in ' . $row['name'];
        $message = $budget > 0
            ? sprintf('You have spent %.2f in %s against a %.2f monthly budget. A smaller weekly cap can help you stay closer to your target.', $spent, $row['name'], $budget)
            : sprintf('%s is one of your larger spending areas this month. Cutting it by about 10%% could save around %.2f.', $row['name'], $potential);

        $insert = $db->prepare(
            'INSERT INTO saving_tips (user_id, title, message, potential_saving, priority)
             VALUES (:user_id, :title, :message, :potential, :priority)'
        );
        $insert->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'potential' => $potential,
            'priority' => $priority,
        ]);
        $created[] = (int) $db->lastInsertId();
    }

    jsonResponse(true, 'Personalized saving tips generated successfully.', ['created_ids' => $created]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Saving tips could not be generated.', [], 500);
}
