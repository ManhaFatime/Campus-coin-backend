<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
requireMethod('GET');
requireAdmin();
$type = queryString('type');
if ($type !== '' && !in_array($type, ['announcement', 'tip_template'], true)) {
    jsonResponse(false, 'Invalid content type.', [], 422);
}

try {
    $db = getDatabaseConnection();
    $items = [];

    if ($type === '' || $type === 'announcement') {
        $announcements = $db->query(
            'SELECT id, "announcement" AS content_type, title, message, is_active, created_at, updated_at
             FROM announcements ORDER BY created_at DESC'
        )->fetchAll();
        $items = array_merge($items, $announcements);
    }

    if ($type === '' || $type === 'tip_template') {
        $tips = $db->query(
            'SELECT id, "tip_template" AS content_type, title, message, is_active, created_at, updated_at
             FROM content_items ORDER BY created_at DESC'
        )->fetchAll();
        $items = array_merge($items, $tips);
    }

    usort($items, fn(array $a, array $b) => strcmp($b['created_at'], $a['created_at']));
    jsonResponse(true, 'Content loaded successfully.', ['items' => $items]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Content could not be loaded. Run setup/001_extra_tables.sql if this is a new installation.', [], 500);
}
