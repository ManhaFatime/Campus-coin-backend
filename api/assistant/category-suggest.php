<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
requireMethod('POST');
$userId = requireStudent();
$data = getJsonInput();
$description = strtolower(trim((string) ($data['description'] ?? '')));
$type = trim((string) ($data['type'] ?? 'expense'));

if ($description === '' || !in_array($type, ['income', 'expense'], true)) {
    jsonResponse(false, 'Description and transaction type are required.', [], 422);
}

$rules = [
    'expense' => [
        'Food' => ['cafe', 'coffee', 'food', 'lunch', 'dinner', 'breakfast', 'restaurant', 'canteen', 'pizza', 'burger'],
        'Transport' => ['uber', 'careem', 'bus', 'fuel', 'petrol', 'taxi', 'rickshaw', 'transport'],
        'Hostel/Rent' => ['hostel', 'rent', 'room', 'accommodation'],
        'Academics' => ['book', 'tuition', 'stationery', 'course', 'exam', 'printing', 'notes'],
        'Subscriptions' => ['netflix', 'spotify', 'subscription', 'youtube', 'app', 'software'],
        'Entertainment' => ['movie', 'cinema', 'outing', 'game', 'concert'],
    ],
    'income' => [
        'Allowance' => ['allowance', 'family', 'parents'],
        'Part-time Job' => ['salary', 'job', 'freelance', 'gig', 'client'],
        'Scholarship' => ['scholarship', 'stipend'],
        'Gift' => ['gift', 'birthday', 'present'],
    ],
];

$suggestedName = null;
foreach ($rules[$type] as $categoryName => $keywords) {
    foreach ($keywords as $keyword) {
        if (str_contains($description, $keyword)) {
            $suggestedName = $categoryName;
            break 2;
        }
    }
}

$suggestedName ??= $type === 'expense' ? 'Miscellaneous' : 'Other Income';

try {
    $db = getDatabaseConnection();
    $statement = $db->prepare(
        'SELECT id, name, type
         FROM categories
         WHERE name = :name AND type = :type AND is_active = 1
           AND (user_id IS NULL OR user_id = :user_id)
         ORDER BY user_id IS NOT NULL DESC LIMIT 1'
    );
    $statement->execute(['name' => $suggestedName, 'type' => $type, 'user_id' => $userId]);
    $category = $statement->fetch();

    jsonResponse(true, 'Category suggestion generated.', [
        'suggestion' => $category ?: null,
        'method' => 'keyword_rules',
        'advisory' => true,
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Category suggestion could not be generated.', [], 500);
}
