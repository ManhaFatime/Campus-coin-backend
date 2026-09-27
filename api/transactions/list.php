<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();

$type = queryString('type');
$categoryId = queryInt('category_id');
$from = queryString('from');
$to = queryString('to');
$search = queryString('search');
$page = max(1, queryInt('page', 1));
$limit = min(100, max(1, queryInt('limit', 25)));
$offset = ($page - 1) * $limit;

if ($type !== '' && !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(false, 'Invalid transaction type.', [], 422);
}
if ($from !== '' && !validDate($from)) {
    jsonResponse(false, 'Invalid start date.', [], 422);
}
if ($to !== '' && !validDate($to)) {
    jsonResponse(false, 'Invalid end date.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $where = ['t.user_id = :user_id'];
    $params = ['user_id' => $userId];

    if ($type !== '') {
        $where[] = 't.type = :type';
        $params['type'] = $type;
    }
    if ($categoryId > 0) {
        $where[] = 't.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }
    if ($from !== '') {
        $where[] = 't.transaction_date >= :from_date';
        $params['from_date'] = $from;
    }
    if ($to !== '') {
        $where[] = 't.transaction_date <= :to_date';
        $params['to_date'] = $to;
    }
    if ($search !== '') {
        $where[] = '(t.description LIKE :search_description OR c.name LIKE :search_category)';
        $params['search_description'] = '%' . $search . '%';
        $params['search_category'] = '%' . $search . '%';
    }

    $whereSql = implode(' AND ', $where);

    $count = $db->prepare(
        "SELECT COUNT(*)
         FROM transactions t
         JOIN categories c ON c.id = t.category_id
         WHERE {$whereSql}"
    );
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $sql = "SELECT t.id, t.category_id, c.name AS category_name, t.amount, t.type,
                   t.description, t.transaction_date, t.is_recurring,
                   t.recurring_frequency, t.recurring_end_date,
                   t.ai_suggested_category_id, t.created_at, t.updated_at
            FROM transactions t
            JOIN categories c ON c.id = t.category_id
            WHERE {$whereSql}
            ORDER BY t.transaction_date DESC, t.id DESC
            LIMIT {$limit} OFFSET {$offset}";
    $statement = $db->prepare($sql);
    $statement->execute($params);

    jsonResponse(true, 'Transactions loaded successfully.', [
        'transactions' => $statement->fetchAll(),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
        ],
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Transactions could not be loaded.', [], 500);
}
