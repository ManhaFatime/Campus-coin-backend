<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'Please upload a CSV file.', [], 422);
}
if ($_FILES['file']['size'] > 2 * 1024 * 1024) {
    jsonResponse(false, 'CSV file must be 2 MB or smaller.', [], 413);
}

$handle = fopen($_FILES['file']['tmp_name'], 'r');
if (!$handle) {
    jsonResponse(false, 'CSV file could not be read.', [], 422);
}

$header = fgetcsv($handle);
$expected = ['date', 'type', 'category', 'amount', 'description'];
$normalized = array_map(fn($value) => strtolower(trim((string) $value)), $header ?: []);
if ($normalized !== $expected) {
    fclose($handle);
    jsonResponse(false, 'CSV columns must be: date,type,category,amount,description.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    $inserted = 0;
    $skipped = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 5) {
            $skipped++;
            continue;
        }

        [$date, $type, $categoryName, $amountRaw, $description] = array_map('trim', $row);
        $type = strtolower($type);
        $amount = positiveAmount($amountRaw);

        if (!validDate($date) || !in_array($type, ['income', 'expense'], true) || $amount === null) {
            $skipped++;
            continue;
        }

        $categoryQuery = $db->prepare(
            'SELECT id FROM categories
             WHERE name = :name AND type = :type AND is_active = 1
               AND (user_id IS NULL OR user_id = :user_id)
             ORDER BY user_id IS NOT NULL DESC
             LIMIT 1'
        );
        $categoryQuery->execute([
            'name' => $categoryName,
            'type' => $type,
            'user_id' => $userId,
        ]);
        $categoryId = $categoryQuery->fetchColumn();

        if (!$categoryId) {
            $skipped++;
            continue;
        }

        $insert = $db->prepare(
            'INSERT INTO transactions
                (user_id, category_id, amount, type, description, transaction_date)
             VALUES (:user_id, :category_id, :amount, :type, :description, :transaction_date)'
        );
        $insert->execute([
            'user_id' => $userId,
            'category_id' => $categoryId,
            'amount' => $amount,
            'type' => $type,
            'description' => $description !== '' ? $description : null,
            'transaction_date' => $date,
        ]);
        $transactionId = (int) $db->lastInsertId();
        recordTransactionHistory($db, $transactionId, $userId, 'created', null, [
            'category_id' => (int) $categoryId,
            'amount' => $amount,
            'type' => $type,
            'description' => $description,
            'transaction_date' => $date,
            'source' => 'csv',
        ]);
        $inserted++;
    }

    fclose($handle);
    $db->commit();
    jsonResponse(true, 'CSV import completed.', ['inserted' => $inserted, 'skipped' => $skipped]);
} catch (Throwable $exception) {
    fclose($handle);
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log($exception->getMessage());
    jsonResponse(false, 'CSV import failed.', [], 500);
}
