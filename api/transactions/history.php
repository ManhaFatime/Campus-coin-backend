<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();
$transactionId = queryInt('transaction_id');

try {
    $db = getDatabaseConnection();
    $sql = 'SELECT id, transaction_id, action, old_data, new_data, changed_at
            FROM transaction_history WHERE user_id = :user_id';
    $params = ['user_id' => $userId];
    if ($transactionId > 0) {
        $sql .= ' AND transaction_id = :transaction_id';
        $params['transaction_id'] = $transactionId;
    }
    $sql .= ' ORDER BY changed_at DESC, id DESC LIMIT 200';
    $statement = $db->prepare($sql);
    $statement->execute($params);
    $history = $statement->fetchAll();
    foreach ($history as &$item) {
        $item['old_data'] = $item['old_data'] ? json_decode($item['old_data'], true) : null;
        $item['new_data'] = $item['new_data'] ? json_decode($item['new_data'], true) : null;
    }
    jsonResponse(true, 'Transaction history loaded successfully.', ['history' => $history]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Transaction history could not be loaded.', [], 500);
}
