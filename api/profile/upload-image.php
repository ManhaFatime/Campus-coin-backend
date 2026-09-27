<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

requireMethod('POST');
$userId = requireAuth();

if (!isset($_FILES['profile_image'])) {
    jsonResponse(false, 'Please choose a profile image.', [], 422);
}

$file = $_FILES['profile_image'];
$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
$size = (int) ($file['size'] ?? 0);
$tmpName = (string) ($file['tmp_name'] ?? '');

if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
    jsonResponse(false, 'The profile image upload was not completed.', [], 422);
}

$maxBytes = 3 * 1024 * 1024;

if ($size < 1 || $size > $maxBytes) {
    jsonResponse(false, 'Profile images must be 3 MB or smaller.', [], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($tmpName);

$extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

if (!isset($extensions[$mime])) {
    jsonResponse(false, 'Only JPG, PNG, and WebP profile images are supported.', [], 422);
}

$imageInfo = @getimagesize($tmpName);

if ($imageInfo === false) {
    jsonResponse(false, 'The selected file is not a valid image.', [], 422);
}

try {
    $db = getDatabaseConnection();

    $oldQuery = $db->prepare(
        'SELECT profile_image
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $oldQuery->execute([
        'id' => $userId,
    ]);

    $oldPath = $oldQuery->fetchColumn();

    $uploadDirectory = dirname(__DIR__) . '/uploads/profile-images';

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Profile image directory could not be created.');
        }
    }

    $extension = $extensions[$mime];
    $fileName = sprintf(
        'user-%d-%s.%s',
        $userId,
        bin2hex(random_bytes(12)),
        $extension
    );

    $relativePath = 'uploads/profile-images/' . $fileName;
    $absolutePath = dirname(__DIR__) . '/' . $relativePath;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException('Profile image could not be stored.');
    }

    try {
        $update = $db->prepare(
            'UPDATE users
             SET profile_image = :profile_image
             WHERE id = :id'
        );

        $update->execute([
            'profile_image' => $relativePath,
            'id' => $userId,
        ]);
    } catch (Throwable $exception) {
        @unlink($absolutePath);
        throw $exception;
    }

    if (
        is_string($oldPath) &&
        $oldPath !== '' &&
        str_starts_with($oldPath, 'uploads/profile-images/')
    ) {
        $oldAbsolutePath = dirname(__DIR__) . '/' . ltrim($oldPath, '/');

        if (is_file($oldAbsolutePath)) {
            @unlink($oldAbsolutePath);
        }
    }

    jsonResponse(
        true,
        'Profile photo updated successfully.',
        [
            'profile_image_url' => '/api/' . $relativePath,
        ]
    );
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    jsonResponse(false, 'Profile image could not be uploaded.', [], 500);
}
