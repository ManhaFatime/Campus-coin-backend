<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireMethod('GET');
$userId = requireAuth();

try {
    $db = getDatabaseConnection();

    $statement = $db->prepare(
        'SELECT id, role, name, email, profile_image, academic_year,
                monthly_allowance, monthly_savings_goal, is_active, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => $userId,
    ]);

    $user = $statement->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        $_SESSION = [];
        session_destroy();
        jsonResponse(false, 'Your session is no longer valid.', [], 401);
    }

    jsonResponse(true, 'Authenticated user loaded successfully.', [
        'user' => [
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'name' => $user['name'],
            'email' => $user['email'],
            'profile_image_url' => !empty($user['profile_image'])
                ? '/api/' . ltrim((string) $user['profile_image'], '/')
                : null,
            'academic_year' => $user['academic_year'],
            'monthly_allowance' => (float) $user['monthly_allowance'],
            'monthly_savings_goal' => (float) $user['monthly_savings_goal'],
            'created_at' => $user['created_at'],
        ],
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Unable to verify your session.', [], 500);
}
