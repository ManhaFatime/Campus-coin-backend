<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('POST', 'PATCH');
requireAdmin();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
$active = !empty($data['is_active']) ? 1 : 0;
if ($id < 1) jsonResponse(false, 'User id is required.', [], 422);

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare('UPDATE users SET is_active = :active WHERE id = :id AND role = "student"');
    $statement->execute(['active' => $active, 'id' => $id]);
    if ($statement->rowCount() === 0) {
        $exists = $db->prepare('SELECT id FROM users WHERE id = :id AND role = "student"');
        $exists->execute(['id' => $id]);
        if (!$exists->fetch()) jsonResponse(false, 'Student account not found.', [], 404);
    }
    jsonResponse(true, $active ? 'Student account enabled.' : 'Student account disabled.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'User status could not be updated.', [], 500);
}
