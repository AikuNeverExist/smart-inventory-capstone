<?php

function profileAvatarDirectory(): string
{
    return __DIR__ . '/../uploads/profile';
}

function profileAvatarWebPath(int $userId): ?string
{
    $dir = profileAvatarDirectory();

    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $file = $dir . '/user_' . $userId . '.' . $ext;
        if (is_file($file)) {
            return '/smart_inventory/uploads/profile/user_' . $userId . '.' . $ext . '?v=' . filemtime($file);
        }
    }

    return null;
}

function removeProfileAvatar(int $userId): void
{
    $dir = profileAvatarDirectory();

    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $file = $dir . '/user_' . $userId . '.' . $ext;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function saveProfileAvatar(int $userId, array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [false, 'Choose a profile picture first.'];
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'The profile picture could not be uploaded.'];
    }

    if ((int)($upload['size'] ?? 0) > 2 * 1024 * 1024) {
        return [false, 'Profile picture must be 2 MB or smaller.'];
    }

    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, 'Invalid profile picture upload.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        return [false, 'Use a JPG, PNG, or WEBP image.'];
    }

    $dir = profileAvatarDirectory();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return [false, 'Could not create the profile picture folder.'];
    }

    removeProfileAvatar($userId);
    $destination = $dir . '/user_' . $userId . '.' . $extensions[$mime];

    if (!move_uploaded_file($tmp, $destination)) {
        return [false, 'The profile picture could not be saved.'];
    }

    return [true, 'Profile picture updated.'];
}
