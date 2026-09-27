<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();

try {
    $db = getDatabaseConnection();
    $created = processRecurringTransactions($db, $userId);
    jsonResponse(true, 'Recurring transactions processed successfully.', ['created' => $created]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Recurring transactions could not be processed. Ensure the API migration has been run.', [], 500);
}
