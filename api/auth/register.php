<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');

$data = getJsonInput();
$name = trim((string) ($data['name'] ?? ''));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
$academicYear = trim((string) ($data['academic_year'] ?? ''));
$allowance = nonNegativeAmount($data['monthly_allowance'] ?? 0);
$goal = nonNegativeAmount($data['monthly_savings_goal'] ?? 0);

if (strlen($name) < 2 || strlen($name) > 100) {
    jsonResponse(false, 'Please enter a valid name.', [], 422);
}
if (!validEmail($email)) {
    jsonResponse(false, 'Please enter a valid email address.', [], 422);
}
if (strlen($password) < 8) {
    jsonResponse(false, 'Password must contain at least 8 characters.', [], 422);
}
if ($allowance === null || $goal === null) {
    jsonResponse(false, 'Financial values cannot be negative.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $exists = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        jsonResponse(false, 'An account with this email already exists.', [], 409);
    }

    $statement = $db->prepare(
        'INSERT INTO users
            (role, name, email, password_hash, academic_year, monthly_allowance, monthly_savings_goal)
         VALUES
            ("student", :name, :email, :password_hash, :academic_year, :allowance, :goal)'
    );
    $statement->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'academic_year' => $academicYear !== '' ? $academicYear : null,
        'allowance' => $allowance,
        'goal' => $goal,
    ]);

    $userId = (int) $db->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = 'student';

    jsonResponse(true, 'Your Campus Coin account has been created successfully.', [
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => 'student',
            'academic_year' => $academicYear !== '' ? $academicYear : null,
            'monthly_allowance' => $allowance,
            'monthly_savings_goal' => $goal,
        ],
    ], 201);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Account could not be created.', [], 500);
}
