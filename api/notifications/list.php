<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();
$unreadOnly = queryInt('unread', 0) === 1;

try {
    $db = getDatabaseConnection();

    // Ensures active administrator announcements/tips exist for this student,
    // including students who registered after the content was originally created.
    syncActiveAdminContentNotificationsForStudent($db, $userId);

    $sql = 'SELECT
                notifications.id,
                notifications.title,
                notifications.message,
                notifications.type,
                notifications.source_type,
                notifications.source_id,
                notifications.is_read,
                notifications.created_at,
                CASE
                    WHEN notifications.source_type IS NOT NULL
                     AND notifications.source_id IS NOT NULL
                     AND EXISTS (
                        SELECT 1
                        FROM bookmarks
                        WHERE bookmarks.user_id = notifications.user_id
                          AND bookmarks.item_type = notifications.source_type
                          AND bookmarks.item_id = notifications.source_id
                     )
                    THEN 1
                    ELSE 0
                END AS is_bookmarked
            FROM notifications
            WHERE notifications.user_id = :user_id';

    if ($unreadOnly) {
        $sql .= ' AND notifications.is_read = 0';
    }

    $sql .= ' ORDER BY notifications.created_at DESC LIMIT 100';

    $statement = $db->prepare($sql);
    $statement->execute(['user_id' => $userId]);

    jsonResponse(
        true,
        'Notifications loaded successfully.',
        ['notifications' => $statement->fetchAll()]
    );
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Notifications could not be loaded.', [], 500);
}
