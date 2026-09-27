<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');

$data = getJsonInput();
$email = strtolower(trim((string) ($data['email'] ?? '')));

if (!validEmail($email)) {
    jsonResponse(false, 'Please enter a valid email address.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare('SELECT id, email, is_active FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    $responseData = [];

    if ($user && (int) $user['is_active'] === 1) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id AND used_at IS NULL')
            ->execute(['user_id' => $user['id']]);

        $insert = $db->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        $insert->execute([
            'user_id' => $user['id'],
            'token_hash' => $tokenHash,
            'expires_at' => $expires,
        ]);

        sendPasswordResetEmail($email, $token);

        if ($appConfig['env'] !== 'production') {
            $responseData['development_reset_token'] = $token;
        }
    }

    jsonResponse(true, 'If an active account exists for this email, password reset instructions have been prepared.', $responseData);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Password reset request could not be processed.', [], 500);
}
