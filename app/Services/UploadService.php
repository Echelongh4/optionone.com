<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

class UploadService
{
    private const URL_PREFIX = 'storage/uploads';

    public function store(?array $file, string $directory): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new HttpException(500, 'The file upload failed.');
        }

        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new HttpException(500, 'The file is too large. Maximum size is 2MB.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $mimeType = mime_content_type($tmpName) ?: 'application/octet-stream';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!array_key_exists($mimeType, $allowed)) {
            throw new HttpException(500, 'Only JPG, PNG, and WebP images are allowed.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mimeType];
        $relativeDirectory = trim($directory, '/');
        $diskRoot = $this->diskRoot();
        $absoluteDirectory = base_path($diskRoot . '/' . $relativeDirectory);

        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new HttpException(500, 'Unable to prepare the upload directory.');
        }

        $relativePath = self::URL_PREFIX . '/' . $relativeDirectory . '/' . $filename;
        $destination = base_path($diskRoot . '/' . $relativeDirectory . '/' . $filename);

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new HttpException(500, 'Unable to move the uploaded file.');
        }

        return $relativePath;
    }

    private function diskRoot(): string
    {
        $disk = strtolower(trim((string) config('app.uploads_disk', 'storage')));

        return match ($disk) {
            'public' => 'public/' . self::URL_PREFIX,
            default => self::URL_PREFIX,
        };
    }
}
