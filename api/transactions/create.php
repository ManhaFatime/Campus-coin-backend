<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();
$data = getJsonInput();

$categoryId = (int) ($data['category_id'] ?? 0);
$amount = positiveAmount($data['amount'] ?? null);
$type = trim((string) ($data['type'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$date = trim((string) ($data['transaction_date'] ?? date('Y-m-d')));
$isRecurring = !empty($data['is_recurring']) ? 1 : 0;
$frequency = $isRecurring ? trim((string) ($data['recurring_frequency'] ?? 'monthly')) : null;
$endDate = $isRecurring ? trim((string) ($data['recurring_end_date'] ?? '')) : '';
$aiCategory = isset($data['ai_suggested_category_id']) ? (int) $data['ai_suggested_category_id'] : null;

if ($categoryId < 1 || $amount === null || !in_array($type, ['income', 'expense'], true) || !validDate($date)) {
    jsonResponse(false, 'Please provide valid transaction details.', [], 422);
}
if ($isRecurring && !in_array($frequency, ['weekly', 'monthly', 'yearly'], true)) {
    jsonResponse(false, 'Invalid recurring frequency.', [], 422);
}
if ($endDate !== '' && !validDate($endDate)) {
    jsonResponse(false, 'Invalid recurring end date.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $category = getAccessibleCategory($db, $categoryId, $userId);
    if (!$category || $category['type'] !== $type) {
        jsonResponse(false, 'The selected category does not match this transaction type.', [], 422);
    }

    if ($aiCategory) {
        $suggested = getAccessibleCategory($db, $aiCategory, $userId);
        if (!$suggested || $suggested['type'] !== $type) {
            $aiCategory = null;
        }
    }

    $db->beginTransaction();
    $statement = $db->prepare(
        'INSERT INTO transactions
            (user_id, category_id, amount, type, description, transaction_date,
             is_recurring, recurring_frequency, recurring_end_date, ai_suggested_category_id, next_recurring_date)
         VALUES
            (:user_id, :category_id, :amount, :type, :description, :transaction_date,
             :is_recurring, :frequency, :end_date, :ai_category, :next_recurring_date)'
    );
    $statement->execute([
        'user_id' => $userId,
        'category_id' => $categoryId,
        'amount' => $amount,
        'type' => $type,
        'description' => $description !== '' ? $description : null,
        'transaction_date' => $date,
        'is_recurring' => $isRecurring,
        'frequency' => $isRecurring ? $frequency : null,
        'end_date' => ($isRecurring && $endDate !== '') ? $endDate : null,
        'ai_category' => $aiCategory ?: null,
        'next_recurring_date' => $isRecurring ? nextRecurringDate($date, $frequency) : null,
    ]);

    $id = (int) $db->lastInsertId();
    $newData = [
        'id' => $id,
        'category_id' => $categoryId,
        'amount' => $amount,
        'type' => $type,
        'description' => $description,
        'transaction_date' => $date,
    ];
    recordTransactionHistory($db, $id, $userId, 'created', null, $newData);
    $db->commit();

    if ($type === 'expense') {
        refreshBudgetAlert($db, $userId, $categoryId, $date);
    }

    jsonResponse(true, 'Transaction added successfully.', ['transaction' => $newData], 201);
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log($exception->getMessage());
    jsonResponse(false, 'Transaction could not be added.', [], 500);
}
