<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('POST');
requireAdmin();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
if ($id < 1) jsonResponse(false, 'User id is required.', [], 422);

try {
    $db = getDatabaseConnection();
    $exists = $db->prepare('SELECT id FROM users WHERE id = :id AND role = "student" LIMIT 1');
    $exists->execute(['id' => $id]);
    if (!$exists->fetch()) jsonResponse(false, 'Student account not found.', [], 404);

    $temporary = 'Cc@' . bin2hex(random_bytes(4));
    $statement = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id AND role = "student"');
    $statement->execute(['hash' => password_hash($temporary, PASSWORD_DEFAULT), 'id' => $id]);
    $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $id]);

    jsonResponse(true, 'A temporary password has been generated.', ['temporary_password' => $temporary]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Password could not be reset.', [], 500);
}
