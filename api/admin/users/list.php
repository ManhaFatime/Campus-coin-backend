<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

requireMethod('GET');
requireAdmin();
$search = queryString('search');
$status = queryString('status');

try {
    $db = getDatabaseConnection();
    $where = ['role = "student"'];
    $params = [];

    if ($search !== '') {
        $where[] = '(name LIKE :search_name OR email LIKE :search_email)';
        $params['search_name'] = '%' . $search . '%';
        $params['search_email'] = '%' . $search . '%';
    }

    if ($status === 'active') {
        $where[] = 'is_active = 1';
    }

    if ($status === 'disabled') {
        $where[] = 'is_active = 0';
    }

    $statement = $db->prepare(
        'SELECT id, name, email, profile_image, academic_year, monthly_allowance,
                monthly_savings_goal, is_active, created_at
         FROM users
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY created_at DESC'
    );

    $statement->execute($params);
    $users = $statement->fetchAll();

    foreach ($users as &$user) {
        $user['id'] = (int) $user['id'];
        $user['is_active'] = (int) $user['is_active'];
        $user['profile_image_url'] = !empty($user['profile_image'])
            ? '/api/' . ltrim((string) $user['profile_image'], '/')
            : null;

        unset($user['profile_image']);
    }
    unset($user);

    jsonResponse(
        true,
        'Users loaded successfully.',
        ['users' => $users]
    );
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Users could not be loaded.', [], 500);
}
