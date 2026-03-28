<?php

declare(strict_types=1);

namespace App\Utils;

use Psr\Http\Message\UploadedFileInterface;

class Upload
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_SIZE_BYTES     = 5 * 1024 * 1024; // 5 MB

    /**
     * Handle an uploaded avatar file.
     * Returns the relative URL path on success (e.g. /uploads/avatars/avatar-xxx.jpg),
     * or throws \RuntimeException on validation failure.
     */
    public static function handleAvatar(?UploadedFileInterface $file): ?string
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        self::validateFile($file);

        $dir = __DIR__ . '/../../uploads/avatars';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext      = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        $filename = 'avatar-' . time() . $ext;
        $file->moveTo($dir . '/' . $filename);

        return '/uploads/avatars/' . $filename;
    }

    /**
     * Handle an uploaded train image.
     * Returns the relative URL path on success.
     */
    public static function handleTrainImage(?UploadedFileInterface $file): ?string
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        self::validateFile($file);

        $dir = __DIR__ . '/../../uploads/trains';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext      = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        $filename = 'train-' . time() . $ext;
        $file->moveTo($dir . '/' . $filename);

        return '/uploads/trains/' . $filename;
    }

    /**
     * Delete a file by its relative URL path (e.g. /uploads/avatars/avatar-xxx.jpg).
     */
    public static function deleteFile(?string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }

        $fullPath = __DIR__ . '/../../' . ltrim($relativePath, '/');
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Validate extension and file size.
     * Throws \RuntimeException if invalid.
     */
    private static function validateFile(UploadedFileInterface $file): void
    {
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Only jpg, jpeg, png, webp images allowed');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new \RuntimeException('File size must not exceed 5 MB');
        }
    }
}
