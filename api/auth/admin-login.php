<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');

$data = getJsonInput();
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if (!validEmail($email) || $password === '') {
    jsonResponse(false, 'Admin email and password are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare(
        'SELECT id, role, name, email, password_hash, is_active
         FROM users WHERE email = :email AND role = "admin" LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(false, 'Invalid administrator credentials.', [], 401);
    }
    if ((int) $user['is_active'] !== 1) {
        jsonResponse(false, 'This administrator account is disabled.', [], 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = 'admin';

    jsonResponse(true, 'Administrator login successful.', [
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => 'admin',
        ],
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Administrator login could not be completed.', [], 500);
}
