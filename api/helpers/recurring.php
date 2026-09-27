<?php

declare(strict_types=1);

function processRecurringTransactions(PDO $db, int $userId): int
{
    $templates = $db->prepare(
        'SELECT * FROM transactions
         WHERE user_id = :user_id
           AND is_recurring = 1
           AND recurring_parent_id IS NULL
           AND next_recurring_date IS NOT NULL
           AND next_recurring_date <= CURDATE()
           AND (recurring_end_date IS NULL OR next_recurring_date <= recurring_end_date)'
    );
    $templates->execute(['user_id' => $userId]);

    $created = 0;
    $db->beginTransaction();

    try {
        foreach ($templates->fetchAll() as $template) {
            $nextDate = $template['next_recurring_date'];
            $safety = 0;

            while ($nextDate <= date('Y-m-d') && $safety < 24) {
                if ($template['recurring_end_date'] && $nextDate > $template['recurring_end_date']) {
                    break;
                }

                $duplicate = $db->prepare(
                    'SELECT id FROM transactions
                     WHERE user_id = :user_id AND recurring_parent_id = :parent_id
                       AND transaction_date = :date LIMIT 1'
                );
                $duplicate->execute([
                    'user_id' => $userId,
                    'parent_id' => $template['id'],
                    'date' => $nextDate,
                ]);

                if (!$duplicate->fetch()) {
                    $insert = $db->prepare(
                        'INSERT INTO transactions
                            (user_id, category_id, amount, type, description, transaction_date,
                             is_recurring, recurring_parent_id, ai_suggested_category_id)
                         VALUES
                            (:user_id, :category_id, :amount, :type, :description, :date,
                             0, :parent_id, :ai_category)'
                    );
                    $insert->execute([
                        'user_id' => $userId,
                        'category_id' => $template['category_id'],
                        'amount' => $template['amount'],
                        'type' => $template['type'],
                        'description' => $template['description'],
                        'date' => $nextDate,
                        'parent_id' => $template['id'],
                        'ai_category' => $template['ai_suggested_category_id'],
                    ]);

                    $newId = (int) $db->lastInsertId();
                    recordTransactionHistory($db, $newId, $userId, 'created', null, [
                        'recurring_parent_id' => (int) $template['id'],
                        'transaction_date' => $nextDate,
                        'amount' => (float) $template['amount'],
                        'type' => $template['type'],
                    ]);

                    if ($template['type'] === 'expense') {
                        refreshBudgetAlert($db, $userId, (int) $template['category_id'], $nextDate);
                    }

                    $created++;
                }

                $nextDate = nextRecurringDate($nextDate, $template['recurring_frequency']);
                $safety++;
            }

            $nextDateForDatabase = $nextDate;

            if (
                $template['recurring_end_date'] &&
                $nextDate > $template['recurring_end_date']
            ) {
                $nextDateForDatabase = null;
            }

            $update = $db->prepare(
                'UPDATE transactions
                 SET next_recurring_date = :next_date
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute([
                'next_date' => $nextDateForDatabase,
                'id' => $template['id'],
                'user_id' => $userId,
            ]);
        }

        $db->commit();
        return $created;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
