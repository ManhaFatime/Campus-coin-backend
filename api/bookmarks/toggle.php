<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();
$data = getJsonInput();
$type = trim((string) ($data['item_type'] ?? ''));
$itemId = (int) ($data['item_id'] ?? 0);

$allowedTypes = ['tip', 'insight', 'announcement', 'tip_template'];

if (!in_array($type, $allowedTypes, true) || $itemId < 1) {
    jsonResponse(false, 'Valid bookmark details are required.', [], 422);
}

try {
    $db = getDatabaseConnection();

    // Check the bookmark first so a student can always remove an existing
    // bookmark even if the source content was later disabled.
    $bookmark = $db->prepare(
        'SELECT id
         FROM bookmarks
         WHERE user_id = :user_id
           AND item_type = :type
           AND item_id = :item_id
         LIMIT 1'
    );
    $bookmark->execute([
        'user_id' => $userId,
        'type' => $type,
        'item_id' => $itemId,
    ]);

    $existingId = $bookmark->fetchColumn();

    if ($existingId) {
        $db->prepare(
            'DELETE FROM bookmarks
             WHERE id = :id
               AND user_id = :user_id'
        )->execute([
            'id' => $existingId,
            'user_id' => $userId,
        ]);

        jsonResponse(true, 'Bookmark removed.', ['bookmarked' => false]);
    }

    if ($type === 'tip') {
        $exists = $db->prepare(
            'SELECT id
             FROM saving_tips
             WHERE id = :id
               AND user_id = :user_id
             LIMIT 1'
        );
        $exists->execute(['id' => $itemId, 'user_id' => $userId]);
    } elseif ($type === 'insight') {
        $exists = $db->prepare(
            'SELECT id
             FROM insights
             WHERE id = :id
               AND user_id = :user_id
             LIMIT 1'
        );
        $exists->execute(['id' => $itemId, 'user_id' => $userId]);
    } elseif ($type === 'announcement') {
        $exists = $db->prepare(
            'SELECT id
             FROM announcements
             WHERE id = :id
               AND is_active = 1
             LIMIT 1'
        );
        $exists->execute(['id' => $itemId]);
    } else {
        $exists = $db->prepare(
            'SELECT id
             FROM content_items
             WHERE id = :id
               AND content_type = "tip_template"
               AND is_active = 1
             LIMIT 1'
        );
        $exists->execute(['id' => $itemId]);
    }

    if (!$exists->fetch()) {
        jsonResponse(false, 'The selected item was not found or is no longer active.', [], 404);
    }

    $insert = $db->prepare(
        'INSERT INTO bookmarks (user_id, item_type, item_id)
         VALUES (:user_id, :type, :item_id)'
    );
    $insert->execute([
        'user_id' => $userId,
        'type' => $type,
        'item_id' => $itemId,
    ]);

    jsonResponse(true, 'Bookmark added.', ['bookmarked' => true], 201);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Bookmark could not be updated.', [], 500);
}
