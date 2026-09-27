<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();

$from = queryString('from', date('Y-m-01'));
$to = queryString('to', date('Y-m-d'));
$type = queryString('type');
$categoryId = queryInt('category_id');

if (!validDate($from) || !validDate($to) || $from > $to) {
    jsonResponse(false, 'Please provide a valid date range.', [], 422);
}
if ($type !== '' && !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(false, 'Invalid report type.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $where = ['t.user_id = :user_id', 't.transaction_date BETWEEN :from_date AND :to_date'];
    $params = ['user_id' => $userId, 'from_date' => $from, 'to_date' => $to];
    if ($type !== '') {
        $where[] = 't.type = :type';
        $params['type'] = $type;
    }
    if ($categoryId > 0) {
        $where[] = 't.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }
    $whereSql = implode(' AND ', $where);

    $transactions = $db->prepare(
        "SELECT t.id, t.amount, t.type, t.description, t.transaction_date, c.name AS category_name
         FROM transactions t JOIN categories c ON c.id = t.category_id
         WHERE {$whereSql}
         ORDER BY t.transaction_date DESC, t.id DESC"
    );
    $transactions->execute($params);
    $rows = $transactions->fetchAll();

    $income = 0.0; $expense = 0.0;
    foreach ($rows as $row) {
        if ($row['type'] === 'income') $income += (float) $row['amount'];
        else $expense += (float) $row['amount'];
    }

    $categories = $db->prepare(
        "SELECT c.id, c.name, t.type, SUM(t.amount) AS total, COUNT(*) AS transaction_count
         FROM transactions t JOIN categories c ON c.id = t.category_id
         WHERE {$whereSql}
         GROUP BY c.id, c.name, t.type ORDER BY total DESC"
    );
    $categories->execute($params);

    jsonResponse(true, 'Filtered report generated successfully.', [
        'filters' => ['from' => $from, 'to' => $to, 'type' => $type ?: null, 'category_id' => $categoryId ?: null],
        'summary' => ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense],
        'categories' => $categories->fetchAll(),
        'transactions' => $rows,
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Filtered report could not be generated.', [], 500);
}
