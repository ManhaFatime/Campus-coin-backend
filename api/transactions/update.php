<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireMethod('PUT', 'PATCH', 'POST');

$userId = requireStudent();
$data = getJsonInput();

$id = (int) ($data['id'] ?? 0);
$categoryId = (int) ($data['category_id'] ?? 0);
$amount = positiveAmount($data['amount'] ?? null);
$type = trim((string) ($data['type'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$date = trim((string) ($data['transaction_date'] ?? ''));
$isRecurring = !empty($data['is_recurring']) ? 1 : 0;
$frequency = $isRecurring
    ? trim((string) ($data['recurring_frequency'] ?? 'monthly'))
    : null;
$endDate = $isRecurring
    ? trim((string) ($data['recurring_end_date'] ?? ''))
    : '';

if (
    $id < 1 ||
    $categoryId < 1 ||
    $amount === null ||
    !in_array($type, ['income', 'expense'], true) ||
    !validDate($date)
) {
    jsonResponse(
        false,
        'Please provide valid transaction details.',
        [],
        422
    );
}

if (
    $isRecurring &&
    !in_array($frequency, ['weekly', 'monthly', 'yearly'], true)
) {
    jsonResponse(
        false,
        'Invalid recurring frequency.',
        [],
        422
    );
}

if ($endDate !== '' && !validDate($endDate)) {
    jsonResponse(
        false,
        'Invalid recurring end date.',
        [],
        422
    );
}

try {
    $db = getDatabaseConnection();

    /*
     * Load the old transaction first.
     * We need its old category/date after the update so the old budget
     * can also be recalculated when an expense is moved or changed.
     */
    $oldQuery = $db->prepare(
        'SELECT *
         FROM transactions
         WHERE id = :id
           AND user_id = :user_id
         LIMIT 1'
    );

    $oldQuery->execute([
        'id' => $id,
        'user_id' => $userId,
    ]);

    $old = $oldQuery->fetch();

    if (!$old) {
        jsonResponse(
            false,
            'Transaction not found.',
            [],
            404
        );
    }

    $category = getAccessibleCategory(
        $db,
        $categoryId,
        $userId
    );

    if (
        !$category ||
        $category['type'] !== $type
    ) {
        jsonResponse(
            false,
            'The selected category does not match this transaction type.',
            [],
            422
        );
    }

    $db->beginTransaction();

    $update = $db->prepare(
        'UPDATE transactions
         SET category_id = :category_id,
             amount = :amount,
             type = :type,
             description = :description,
             transaction_date = :transaction_date,
             is_recurring = :is_recurring,
             recurring_frequency = :frequency,
             recurring_end_date = :end_date,
             next_recurring_date = :next_recurring_date
         WHERE id = :id
           AND user_id = :user_id'
    );

    $update->execute([
        'category_id' => $categoryId,
        'amount' => $amount,
        'type' => $type,
        'description' => $description !== ''
            ? $description
            : null,
        'transaction_date' => $date,
        'is_recurring' => $isRecurring,
        'frequency' => $isRecurring
            ? $frequency
            : null,
        'end_date' => (
            $isRecurring &&
            $endDate !== ''
        )
            ? $endDate
            : null,
        'next_recurring_date' => $isRecurring
            ? nextRecurringDate($date, $frequency)
            : null,
        'id' => $id,
        'user_id' => $userId,
    ]);

    $new = array_merge(
        $old,
        [
            'category_id' => $categoryId,
            'amount' => $amount,
            'type' => $type,
            'description' => $description,
            'transaction_date' => $date,
            'is_recurring' => $isRecurring,
            'recurring_frequency' => $frequency,
            'recurring_end_date' => $endDate !== ''
                ? $endDate
                : null,
        ]
    );

    recordTransactionHistory(
        $db,
        $id,
        $userId,
        'updated',
        $old,
        $new
    );

    $db->commit();

    /*
     * Recalculate the OLD budget context first.
     * This fixes stale alerts when:
     * - an expense amount is reduced,
     * - an expense becomes income,
     * - its category changes,
     * - or its month/date changes.
     */
    if (($old['type'] ?? '') === 'expense') {
        refreshBudgetAlert(
            $db,
            $userId,
            (int) $old['category_id'],
            (string) $old['transaction_date']
        );
    }

    /*
     * Then calculate the NEW expense context.
     */
    if ($type === 'expense') {
        $sameBudgetContext =
            ($old['type'] ?? '') === 'expense' &&
            (int) $old['category_id'] === $categoryId &&
            substr((string) $old['transaction_date'], 0, 7) ===
                substr($date, 0, 7);

        if (!$sameBudgetContext) {
            refreshBudgetAlert(
                $db,
                $userId,
                $categoryId,
                $date
            );
        } else {
            /*
             * Same category/month still needs one refresh because
             * the amount may have changed.
             */
            refreshBudgetAlert(
                $db,
                $userId,
                $categoryId,
                $date
            );
        }
    }

    jsonResponse(
        true,
        'Transaction updated successfully.'
    );
} catch (Throwable $exception) {
    if (
        isset($db) &&
        $db->inTransaction()
    ) {
        $db->rollBack();
    }

    error_log($exception->getMessage());

    jsonResponse(
        false,
        'Transaction could not be updated.',
        [],
        500
    );
}
