<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST', 'PATCH');
$userId = requireStudent();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
$pinned = !empty($data['pinned']) ? 1 : 0;
if ($id < 1) {
    jsonResponse(false, 'Tip id is required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare('UPDATE saving_tips SET is_pinned = :pinned WHERE id = :id AND user_id = :user_id');
    $statement->execute(['pinned' => $pinned, 'id' => $id, 'user_id' => $userId]);
    if ($statement->rowCount() === 0) {
        $exists = $db->prepare('SELECT id FROM saving_tips WHERE id = :id AND user_id = :user_id');
        $exists->execute(['id' => $id, 'user_id' => $userId]);
        if (!$exists->fetch()) jsonResponse(false, 'Tip not found.', [], 404);
    }
    jsonResponse(true, $pinned ? 'Tip pinned.' : 'Tip unpinned.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Tip could not be updated.', [], 500);
}
