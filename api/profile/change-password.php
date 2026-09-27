<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireMethod('POST');
$userId = requireAuth();
$data = getJsonInput();
$currentPassword = (string) ($data['current_password'] ?? '');
$newPassword = (string) ($data['new_password'] ?? '');

if ($currentPassword === '' || strlen($newPassword) < 8) {
    jsonResponse(
        false,
        'Current password and a new password of at least 8 characters are required.',
        [],
        422
    );
}

try {
    $db = getDatabaseConnection();

    $statement = $db->prepare(
        'SELECT password_hash
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => $userId,
    ]);

    $hash = $statement->fetchColumn();

    if (!$hash || !password_verify($currentPassword, (string) $hash)) {
        jsonResponse(false, 'Current password is incorrect.', [], 401);
    }

    $update = $db->prepare(
        'UPDATE users
         SET password_hash = :hash
         WHERE id = :id'
    );

    $update->execute([
        'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    jsonResponse(true, 'Password changed successfully.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Password could not be changed.', [], 500);
}
