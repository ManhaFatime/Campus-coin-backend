<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireMethod('GET');
$userId = requireAuth();

try {
    $db = getDatabaseConnection();

    $statement = $db->prepare(
        'SELECT id, role, name, email, profile_image, academic_year,
                monthly_allowance, monthly_savings_goal, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => $userId,
    ]);

    $profile = $statement->fetch();

    if (!$profile) {
        jsonResponse(false, 'Profile not found.', [], 404);
    }

    $profile['id'] = (int) $profile['id'];
    $profile['monthly_allowance'] = (float) $profile['monthly_allowance'];
    $profile['monthly_savings_goal'] = (float) $profile['monthly_savings_goal'];
    $profile['profile_image_url'] = !empty($profile['profile_image'])
        ? '/api/' . ltrim((string) $profile['profile_image'], '/')
        : null;

    unset($profile['profile_image']);

    jsonResponse(
        true,
        'Profile loaded successfully.',
        ['profile' => $profile]
    );
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Profile could not be loaded.', [], 500);
}
