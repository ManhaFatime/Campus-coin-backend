<?php

declare(strict_types=1);

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentRole(): ?string
{
    return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
}

function requireAuth(): int
{
    $userId = currentUserId();

    if (!$userId) {
        jsonResponse(false, 'Please log in to continue.', [], 401);
    }

    return $userId;
}

function requireStudent(): int
{
    $userId = requireAuth();

    if (currentRole() !== 'student') {
        jsonResponse(false, 'Student access is required.', [], 403);
    }

    return $userId;
}

function requireAdmin(): int
{
    $userId = requireAuth();

    if (currentRole() !== 'admin') {
        jsonResponse(false, 'Administrator access is required.', [], 403);
    }

    return $userId;
}
