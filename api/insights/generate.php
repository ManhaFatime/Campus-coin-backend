<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();
$data = getJsonInput();
$month = monthStart(trim((string) ($data['month'] ?? date('Y-m'))));
if (!$month) {
    jsonResponse(false, 'Month must use YYYY-MM format.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $end = date('Y-m-t', strtotime($month));
    $previous = date('Y-m-01', strtotime($month . ' -1 month'));
    $previousEnd = date('Y-m-t', strtotime($previous));

    $totals = $db->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS expense
         FROM transactions WHERE user_id = :user_id AND transaction_date BETWEEN :start AND :end'
    );
    $totals->execute(['user_id' => $userId, 'start' => $month, 'end' => $end]);
    $current = $totals->fetch();
    $totals->execute(['user_id' => $userId, 'start' => $previous, 'end' => $previousEnd]);
    $previousTotals = $totals->fetch();

    $top = $db->prepare(
        'SELECT c.name, SUM(t.amount) AS total
         FROM transactions t JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = :user_id AND t.type = "expense"
           AND t.transaction_date BETWEEN :start AND :end
         GROUP BY c.id, c.name ORDER BY total DESC LIMIT 1'
    );
    $top->execute(['user_id' => $userId, 'start' => $month, 'end' => $end]);
    $topCategory = $top->fetch();

    $income = (float) $current['income'];
    $expense = (float) $current['expense'];
    $previousExpense = (float) $previousTotals['expense'];
    $change = $previousExpense > 0 ? (($expense - $previousExpense) / $previousExpense) * 100 : null;

    $summary = sprintf(
        'You recorded %.2f in income and %.2f in expenses this month, leaving a balance of %.2f.',
        $income,
        $expense,
        $income - $expense
    );
    if ($topCategory) {
        $summary .= sprintf(' Your highest spending category was %s at %.2f.', $topCategory['name'], (float) $topCategory['total']);
    }
    if ($change !== null) {
        $summary .= sprintf(' Overall spending was %s %.0f%% compared with the previous month.', $change >= 0 ? 'up' : 'down', abs($change));
    }

    $advice = $expense > $income
        ? 'Your expenses are currently above your income. Review your largest categories and set a realistic weekly cap for the rest of the month.'
        : 'You are currently spending within your recorded income. Consider moving part of the remaining balance toward your savings goal.';

    $statement = $db->prepare(
        'INSERT INTO insights (user_id, insight_month, summary_text, advice_text, source)
         VALUES (:user_id, :month, :summary, :advice, "system")'
    );
    $statement->execute([
        'user_id' => $userId,
        'month' => $month,
        'summary' => $summary,
        'advice' => $advice,
    ]);

    jsonResponse(true, 'Monthly insight generated successfully.', [
        'id' => (int) $db->lastInsertId(),
        'month' => $month,
        'summary_text' => $summary,
        'advice_text' => $advice,
        'source' => 'system',
    ], 201);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Monthly insight could not be generated.', [], 500);
}
