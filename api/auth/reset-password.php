<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');

$data = getJsonInput();
$token = trim((string) ($data['token'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($token === '' || strlen($password) < 8) {
    jsonResponse(false, 'A valid reset token and a password of at least 8 characters are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $tokenHash = hash('sha256', $token);

    $query = $db->prepare(
        'SELECT id, user_id
         FROM password_reset_tokens
         WHERE token_hash = :token_hash
           AND used_at IS NULL
           AND expires_at > NOW()
         LIMIT 1'
    );
    $query->execute(['token_hash' => $tokenHash]);
    $reset = $query->fetch();

    if (!$reset) {
        jsonResponse(false, 'This reset link is invalid or has expired.', [], 400);
    }

    $db->beginTransaction();
    $update = $db->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $update->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $reset['user_id'],
    ]);
    $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id')
        ->execute(['id' => $reset['id']]);
    $db->commit();

    jsonResponse(true, 'Your password has been reset successfully.');
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log($exception->getMessage());
    jsonResponse(false, 'Password could not be reset.', [], 500);
}
