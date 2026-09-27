<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('GET');
$userId = requireStudent();

try {
    $db = getDatabaseConnection();

    $statement = $db->prepare(
        'SELECT id, item_type, item_id, created_at
         FROM bookmarks
         WHERE user_id = :user_id
         ORDER BY created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);
    $bookmarks = $statement->fetchAll();

    foreach ($bookmarks as &$bookmark) {
        $type = (string) $bookmark['item_type'];
        $itemId = (int) $bookmark['item_id'];

        if ($type === 'tip') {
            $item = $db->prepare(
                'SELECT id, title, message, potential_saving, priority
                 FROM saving_tips
                 WHERE id = :id
                   AND user_id = :user_id'
            );
            $item->execute(['id' => $itemId, 'user_id' => $userId]);
        } elseif ($type === 'insight') {
            $item = $db->prepare(
                'SELECT id, insight_month, summary_text, advice_text, source
                 FROM insights
                 WHERE id = :id
                   AND user_id = :user_id'
            );
            $item->execute(['id' => $itemId, 'user_id' => $userId]);
        } elseif ($type === 'announcement') {
            $item = $db->prepare(
                'SELECT id, title, message, is_active, created_at
                 FROM announcements
                 WHERE id = :id'
            );
            $item->execute(['id' => $itemId]);
        } else {
            $item = $db->prepare(
                'SELECT id, title, message, is_active, created_at
                 FROM content_items
                 WHERE id = :id
                   AND content_type = "tip_template"'
            );
            $item->execute(['id' => $itemId]);
        }

        $bookmark['item'] = $item->fetch() ?: null;
    }
    unset($bookmark);

    jsonResponse(true, 'Bookmarks loaded successfully.', ['bookmarks' => $bookmarks]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Bookmarks could not be loaded.', [], 500);
}
