<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('POST');
$adminId = requireAdmin();
$data = getJsonInput();
$type = trim((string) ($data['content_type'] ?? ''));
$title = trim((string) ($data['title'] ?? ''));
$message = trim((string) ($data['message'] ?? ''));

if (!in_array($type, ['announcement', 'tip_template'], true) || $title === '' || $message === '') {
    jsonResponse(false, 'Content type, title and message are required.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $db->beginTransaction();

    if ($type === 'announcement') {
        $statement = $db->prepare(
            'INSERT INTO announcements (title, message, created_by, is_active)
             VALUES (:title, :message, :created_by, 1)'
        );
    } else {
        $statement = $db->prepare(
            'INSERT INTO content_items (content_type, title, message, created_by, is_active)
             VALUES ("tip_template", :title, :message, :created_by, 1)'
        );
    }

    $statement->execute([
        'title' => $title,
        'message' => $message,
        'created_by' => $adminId,
    ]);

    $contentId = (int) $db->lastInsertId();

    syncAdminContentNotifications(
        $db,
        $type,
        $contentId,
        $title,
        $message,
        true
    );

    $db->commit();

    jsonResponse(
        true,
        'Content created and sent to student notifications.',
        [
            'id' => $contentId,
            'content_type' => $type,
        ],
        201
    );
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log($exception->getMessage());
    jsonResponse(
        false,
        'Content could not be created. Run setup/001_extra_tables.sql and setup/002_admin_content_notifications_bookmarks.sql if needed.',
        [],
        500
    );
}
