<?php

declare(strict_types=1);

/**
 * Synchronize administrator-created announcements and tip templates
 * with student notifications.
 *
 * IMPORTANT:
 * The current CampusCoin `notifications` table has `created_at`
 * but does NOT have an `updated_at` column, so notification queries
 * in this helper intentionally do not reference `updated_at`.
 */

function isAdminContentType(string $type): bool
{
    return in_array($type, ['announcement', 'tip_template'], true);
}

function syncAdminContentNotifications(
    PDO $db,
    string $contentType,
    int $contentId,
    string $title,
    string $message,
    bool $active
): void {
    if (!isAdminContentType($contentType) || $contentId < 1) {
        throw new InvalidArgumentException('Invalid administrator content reference.');
    }

    if (!$active) {
        $delete = $db->prepare(
            'DELETE FROM notifications
             WHERE source_type = :source_type
               AND source_id = :source_id'
        );

        $delete->execute([
            'source_type' => $contentType,
            'source_id' => $contentId,
        ]);

        return;
    }

    $insert = $db->prepare(
        'INSERT INTO notifications
            (
                user_id,
                title,
                message,
                type,
                source_type,
                source_id,
                is_read,
                created_at
            )
         SELECT
            users.id,
            :title,
            :message,
            :notification_type,
            :source_type,
            :source_id,
            0,
            NOW()
         FROM users
         WHERE users.role = "student"
           AND users.is_active = 1
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            message = VALUES(message),
            type = VALUES(type)'
    );

    $insert->execute([
        'title' => $title,
        'message' => $message,
        'notification_type' => $contentType,
        'source_type' => $contentType,
        'source_id' => $contentId,
    ]);
}

/**
 * Self-healing notification synchronization for the logged-in student.
 * This also covers students who registered after content was published.
 */
function syncActiveAdminContentNotificationsForStudent(PDO $db, int $userId): void
{
    if ($userId < 1) {
        return;
    }

    $removeAnnouncements = $db->prepare(
        'DELETE notifications
         FROM notifications
         LEFT JOIN announcements
           ON announcements.id = notifications.source_id
          AND notifications.source_type = "announcement"
         WHERE notifications.user_id = :user_id
           AND notifications.source_type = "announcement"
           AND (
                announcements.id IS NULL
                OR announcements.is_active = 0
           )'
    );
    $removeAnnouncements->execute(['user_id' => $userId]);

    $removeTips = $db->prepare(
        'DELETE notifications
         FROM notifications
         LEFT JOIN content_items
           ON content_items.id = notifications.source_id
          AND notifications.source_type = "tip_template"
         WHERE notifications.user_id = :user_id
           AND notifications.source_type = "tip_template"
           AND (
                content_items.id IS NULL
                OR content_items.is_active = 0
           )'
    );
    $removeTips->execute(['user_id' => $userId]);

    $announcementInsert = $db->prepare(
        'INSERT INTO notifications
            (
                user_id,
                title,
                message,
                type,
                source_type,
                source_id,
                is_read,
                created_at
            )
         SELECT
            :user_id,
            announcements.title,
            announcements.message,
            "announcement",
            "announcement",
            announcements.id,
            0,
            NOW()
         FROM announcements
         WHERE announcements.is_active = 1
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            message = VALUES(message),
            type = VALUES(type)'
    );
    $announcementInsert->execute(['user_id' => $userId]);

    $tipInsert = $db->prepare(
        'INSERT INTO notifications
            (
                user_id,
                title,
                message,
                type,
                source_type,
                source_id,
                is_read,
                created_at
            )
         SELECT
            :user_id,
            content_items.title,
            content_items.message,
            "tip_template",
            "tip_template",
            content_items.id,
            0,
            NOW()
         FROM content_items
         WHERE content_items.content_type = "tip_template"
           AND content_items.is_active = 1
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            message = VALUES(message),
            type = VALUES(type)'
    );
    $tipInsert->execute(['user_id' => $userId]);
}

function deleteAdminContentReferences(PDO $db, string $contentType, int $contentId): void
{
    if (!isAdminContentType($contentType) || $contentId < 1) {
        return;
    }

    $deleteNotifications = $db->prepare(
        'DELETE FROM notifications
         WHERE source_type = :source_type
           AND source_id = :source_id'
    );
    $deleteNotifications->execute([
        'source_type' => $contentType,
        'source_id' => $contentId,
    ]);

    $deleteBookmarks = $db->prepare(
        'DELETE FROM bookmarks
         WHERE item_type = :item_type
           AND item_id = :item_id'
    );
    $deleteBookmarks->execute([
        'item_type' => $contentType,
        'item_id' => $contentId,
    ]);
}
