<?php

declare(strict_types=1);

function getAccessibleCategory(PDO $db, int $categoryId, int $userId): ?array
{
    $statement = $db->prepare(
        'SELECT id, user_id, name, type, is_default, is_active
         FROM categories
         WHERE id = :id
           AND is_active = 1
           AND (user_id IS NULL OR user_id = :user_id)
         LIMIT 1'
    );

    $statement->execute([
        'id' => $categoryId,
        'user_id' => $userId,
    ]);

    $category = $statement->fetch();
    return $category ?: null;
}
