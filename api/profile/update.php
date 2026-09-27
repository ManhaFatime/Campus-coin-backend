<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireMethod('PUT', 'PATCH', 'POST');
$userId = requireAuth();
$role = currentRole();
$data = getJsonInput();

$name = trim((string) ($data['name'] ?? ''));

if (strlen($name) < 2 || strlen($name) > 100) {
    jsonResponse(false, 'Please provide a valid name.', [], 422);
}

try {
    $db = getDatabaseConnection();

    if ($role === 'admin') {
        $statement = $db->prepare(
            'UPDATE users
             SET name = :name
             WHERE id = :id
               AND role = "admin"'
        );

        $statement->execute([
            'name' => $name,
            'id' => $userId,
        ]);
    } else {
        $academicYear = trim((string) ($data['academic_year'] ?? ''));
        $allowance = nonNegativeAmount($data['monthly_allowance'] ?? 0);
        $goal = nonNegativeAmount($data['monthly_savings_goal'] ?? 0);

        if ($allowance === null || $goal === null) {
            jsonResponse(false, 'Please provide valid profile details.', [], 422);
        }

        $statement = $db->prepare(
            'UPDATE users
             SET name = :name,
                 academic_year = :academic_year,
                 monthly_allowance = :allowance,
                 monthly_savings_goal = :goal
             WHERE id = :id
               AND role = "student"'
        );

        $statement->execute([
            'name' => $name,
            'academic_year' => $academicYear !== '' ? $academicYear : null,
            'allowance' => $allowance,
            'goal' => $goal,
            'id' => $userId,
        ]);
    }

    $query = $db->prepare(
        'SELECT id, role, name, email, profile_image, academic_year,
                monthly_allowance, monthly_savings_goal, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $query->execute([
        'id' => $userId,
    ]);

    $profile = $query->fetch();

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
        'Profile updated successfully.',
        ['profile' => $profile]
    );
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Profile could not be updated.', [], 500);
}
