<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('DELETE', 'POST');
requireAdmin();
$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
$type = trim((string) ($data['content_type'] ?? ''));

if ($id < 1 || !in_array($type, ['announcement', 'tip_template'], true)) {
    jsonResponse(false, 'Content id and type are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $db->beginTransaction();

    $table = $type === 'announcement' ? 'announcements' : 'content_items';

    $exists = $db->prepare("SELECT id FROM {$table} WHERE id = :id LIMIT 1");
    $exists->execute(['id' => $id]);

    if (!$exists->fetch()) {
        $db->rollBack();
        jsonResponse(false, 'Content item not found.', [], 404);
    }

    deleteAdminContentReferences($db, $type, $id);

    $statement = $db->prepare("DELETE FROM {$table} WHERE id = :id");
    $statement->execute(['id' => $id]);

    $db->commit();

    jsonResponse(true, 'Content and related student references deleted successfully.');
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log($exception->getMessage());
    jsonResponse(false, 'Content could not be deleted.', [], 500);
}
