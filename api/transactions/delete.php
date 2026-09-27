<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireMethod('DELETE', 'POST');

$userId = requireStudent();
$data = getJsonInput();

$id = (int) ($data['id'] ?? 0);

if ($id < 1) {
    jsonResponse(
        false,
        'Transaction id is required.',
        [],
        422
    );
}

try {
    $db = getDatabaseConnection();

    $query = $db->prepare(
        'SELECT *
         FROM transactions
         WHERE id = :id
           AND user_id = :user_id
         LIMIT 1'
    );

    $query->execute([
        'id' => $id,
        'user_id' => $userId,
    ]);

    $transaction = $query->fetch();

    if (!$transaction) {
        jsonResponse(
            false,
            'Transaction not found.',
            [],
            404
        );
    }

    $db->beginTransaction();

    /*
     * Save the complete transaction snapshot before deletion.
     * This keeps the required transaction history intact.
     */
    recordTransactionHistory(
        $db,
        $id,
        $userId,
        'deleted',
        $transaction,
        null
    );

    $delete = $db->prepare(
        'DELETE FROM transactions
         WHERE id = :id
           AND user_id = :user_id'
    );

    $delete->execute([
        'id' => $id,
        'user_id' => $userId,
    ]);

    $db->commit();

    /*
     * A deleted expense lowers the month's spending.
     * Recalculate its previous budget so stale near-limit/exceeded
     * notifications can be cleared.
     */
    if (
        ($transaction['type'] ?? '') ===
        'expense'
    ) {
        refreshBudgetAlert(
            $db,
            $userId,
            (int) $transaction['category_id'],
            (string) $transaction['transaction_date']
        );
    }

    jsonResponse(
        true,
        'Transaction deleted successfully.'
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
        'Transaction could not be deleted.',
        [],
        500
    );
}
