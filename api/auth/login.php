<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');

$data = getJsonInput();
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if (!validEmail($email) || $password === '') {
    jsonResponse(false, 'Email and password are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare(
        'SELECT id, role, name, email, password_hash, academic_year,
                monthly_allowance, monthly_savings_goal, is_active
         FROM users WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    if (!$user || $user['role'] !== 'student' || !password_verify($password, $user['password_hash'])) {
        jsonResponse(false, 'Invalid email or password.', [], 401);
    }
    if ((int) $user['is_active'] !== 1) {
        jsonResponse(false, 'Your account has been disabled. Please contact the administrator.', [], 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = 'student';

    jsonResponse(true, 'Login successful.', [
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'academic_year' => $user['academic_year'],
            'monthly_allowance' => (float) $user['monthly_allowance'],
            'monthly_savings_goal' => (float) $user['monthly_savings_goal'],
        ],
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Login could not be completed.', [], 500);
}
