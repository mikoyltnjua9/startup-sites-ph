<?php
// Shared file-upload handling. Validates size/extension AND the file's real
// content type (not just the client-supplied one, which is trivially
// spoofable), writes it under a random filename so nothing is guessable or
// collides, and returns the stored filename to save in the DB.

declare(strict_types=1);

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10MB

const ALLOWED_UPLOADS = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

const ALLOWED_IMAGE_UPLOADS = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];

/**
 * @param array $file One entry from $_FILES.
 * @param array<string,string> $allowed Extension => expected MIME map.
 * @return array{0: ?string, 1: ?string} [stored filename, error message]
 */
function handle_upload(array $file, array $allowed = ALLOWED_UPLOADS): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null]; // nothing submitted — not an error
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Upload failed (error code ' . $file['error'] . ').'];
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return [null, 'File is too large (max 10MB).'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [null, 'Invalid upload.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        return [null, 'That file type isn\'t allowed. Allowed: ' . implode(', ', array_keys($allowed))];
    }

    // Trust the file's actual bytes, not the extension or the browser's
    // reported Content-Type, both of which are easy to spoof.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $expectedMime = $allowed[$ext];
    $mimeOk = $realMime === $expectedMime
        // A couple of common near-equivalents different systems report.
        || ($ext === 'jpg' && $realMime === 'image/jpeg')
        || ($expectedMime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && $realMime === 'application/zip')
        || ($expectedMime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' && $realMime === 'application/zip');

    if (!$mimeOk) {
        return [null, 'That file\'s content doesn\'t match its extension.'];
    }

    $uploadDir = __DIR__ . '/../uploads/';
    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
        return [null, 'Could not save the uploaded file.'];
    }

    return [$storedName, null];
}

function delete_upload(?string $storedName): void
{
    if (!$storedName) {
        return;
    }
    $path = __DIR__ . '/../uploads/' . basename($storedName);
    if (is_file($path)) {
        @unlink($path);
    }
}
