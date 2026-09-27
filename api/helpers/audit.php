<?php

declare(strict_types=1);

function recordTransactionHistory(
    PDO $db,
    int $transactionId,
    int $userId,
    string $action,
    ?array $oldData,
    ?array $newData
): void {
    $statement = $db->prepare(
        'INSERT INTO transaction_history
            (transaction_id, user_id, action, old_data, new_data)
         VALUES
            (:transaction_id, :user_id, :action, :old_data, :new_data)'
    );

    $statement->execute([
        'transaction_id' => $transactionId,
        'user_id' => $userId,
        'action' => $action,
        'old_data' => $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
        'new_data' => $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
    ]);
}
