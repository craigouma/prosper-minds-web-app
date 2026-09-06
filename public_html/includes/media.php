<?php

const PM_MEDIA_DIR = __DIR__ . '/../assets/uploads';
const PM_MEDIA_URL = '/assets/uploads';

const PM_MEDIA_SIZES = [
    'thumb'  => 400,
    'medium' => 900,
    'large'  => 1600,
];

const PM_MEDIA_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    'application/pdf' => 'pdf',
];

function ensureMediaSchema(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    try {
        // CREATE TABLE implicitly commits in MySQL, so a call inside someone
        // else's transaction would commit their half-written work early.
        if ($pdo->inTransaction()) {
            error_log('cms_media: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `cms_media` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `filename` VARCHAR(180) NOT NULL,
              `original_name` VARCHAR(255) NOT NULL,
              `mime` VARCHAR(64) NOT NULL,
              `bytes` INT NOT NULL DEFAULT 0,
              `width` INT DEFAULT NULL,
              `height` INT DEFAULT NULL,
              `alt_text` VARCHAR(255) DEFAULT NULL,
              `caption` VARCHAR(255) DEFAULT NULL,
              `focal_x` TINYINT UNSIGNED NOT NULL DEFAULT 50,
              `focal_y` TINYINT UNSIGNED NOT NULL DEFAULT 50,
              `uploaded_by` VARCHAR(64) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY `uq_cms_media_filename` (`filename`),
              KEY `idx_cms_media_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `cms_media_usage` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `media_id` INT NOT NULL,
              `entity_type` VARCHAR(48) NOT NULL,
              `entity_id` VARCHAR(64) NOT NULL,
              `label` VARCHAR(160) DEFAULT NULL,
              UNIQUE KEY `uq_cms_media_usage` (`media_id`, `entity_type`, `entity_id`),
              KEY `idx_cms_media_usage_media` (`media_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    } catch (Throwable $e) {
        error_log('cms_media: schema check failed: ' . $e->getMessage());
    }
}

function pmMediaUploadLimitBytes(): int
{
    $toBytes = static function (string $value): int {
        $value = trim($value);
        $unit  = strtolower(substr($value, -1));
        $n     = (int) $value;

        return match ($unit) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    };

    $limits = array_filter([
        $toBytes((string) ini_get('upload_max_filesize')),
        $toBytes((string) ini_get('post_max_size')),
    ]);

    return $limits ? min($limits) : 2 * 1024 * 1024;
}

function pmMediaPath(string $filename, string $size = 'original'): string
{
    return PM_MEDIA_DIR . '/' . ($size === 'original' ? $filename : $size . '/' . $filename);
}

function pmMediaUrl(string $filename, string $size = 'original'): string
{
    return PM_MEDIA_URL . '/' . ($size === 'original' ? $filename : $size . '/' . $filename);
}

function pmMediaIsImage(string $mime): bool
{
    return str_starts_with($mime, 'image/');
}

/**
 * @return array{ok: bool, id?: int, filename?: string, error?: string}
 */
function pmMediaStore(PDO $pdo, array $file, string $uploadedBy, ?string $altText = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => pmMediaUploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'error' => 'That upload could not be read.'];
    }

    // The browser-supplied type and the filename extension are both caller
    // controlled. Only the magic bytes decide what this file actually is.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($file['tmp_name']);

    if (!isset(PM_MEDIA_TYPES[$mime])) {
        return ['ok' => false, 'error' => 'That file type is not accepted. Use JPG, PNG, WEBP, GIF or PDF.'];
    }

    $bytes = (int) ($file['size'] ?? 0);
    if ($bytes > pmMediaUploadLimitBytes()) {
        return ['ok' => false, 'error' => 'That file is larger than this server accepts.'];
    }

    $extension = PM_MEDIA_TYPES[$mime];
    $base      = pmMediaSlug(pathinfo((string) ($file['name'] ?? 'file'), PATHINFO_FILENAME));
    $filename  = $base . '-' . bin2hex(random_bytes(4)) . '.' . $extension;

    if (!pmMediaEnsureDirs()) {
        return ['ok' => false, 'error' => 'The uploads directory is not writable on this server.'];
    }

    if (!move_uploaded_file($file['tmp_name'], pmMediaPath($filename))) {
        return ['ok' => false, 'error' => 'The file could not be saved.'];
    }

    $width = $height = null;
    if (pmMediaIsImage($mime)) {
        $info = @getimagesize(pmMediaPath($filename));
        if ($info) {
            $width  = (int) $info[0];
            $height = (int) $info[1];
        }
        pmMediaGenerateSizes($filename, $mime);
    }

    try {
        ensureMediaSchema($pdo);
        $pdo->prepare(
            'INSERT INTO cms_media (filename, original_name, mime, bytes, width, height, alt_text, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $filename,
            mb_substr((string) ($file['name'] ?? $filename), 0, 255),
            $mime,
            $bytes,
            $width,
            $height,
            $altText !== null && trim($altText) !== '' ? mb_substr(trim($altText), 0, 255) : null,
            mb_substr($uploadedBy, 0, 64),
        ]);

        return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'filename' => $filename];
    } catch (Throwable $e) {
        error_log('cms_media: could not record upload: ' . $e->getMessage());
        pmMediaUnlinkFiles($filename);

        return ['ok' => false, 'error' => 'The upload was not recorded. Nothing has been kept.'];
    }
}

function pmMediaUploadError(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is larger than this server accepts.',
        UPLOAD_ERR_PARTIAL    => 'The upload did not finish. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'Choose a file to upload.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'This server could not write the file.',
        default => 'The upload failed.',
    };
}

function pmMediaSlug(string $name): string
{
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? '');
    $slug = trim($slug, '-');

    return $slug !== '' ? mb_substr($slug, 0, 80) : 'file';
}

function pmMediaEnsureDirs(): bool
{
    $dirs = [PM_MEDIA_DIR];
    foreach (array_keys(PM_MEDIA_SIZES) as $size) {
        $dirs[] = PM_MEDIA_DIR . '/' . $size;
    }

    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }

    return is_writable(PM_MEDIA_DIR);
}

function pmMediaGenerateSizes(string $filename, string $mime): void
{
    if (!function_exists('imagecreatetruecolor')) {
        error_log('cms_media: GD is not available, no sizes generated');

        return;
    }

    $source = pmMediaPath($filename);

    try {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png'  => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            'image/gif'  => @imagecreatefromgif($source),
            default      => false,
        };

        if (!$image) {
            return;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);

        foreach (PM_MEDIA_SIZES as $name => $maxWidth) {
            $target = pmMediaPath($filename, $name);

            // Never scale up. A 300px logo asked for at 1600 stays 300.
            $scale = min(1, $maxWidth / max(1, $srcW));
            $dstW  = max(1, (int) round($srcW * $scale));
            $dstH  = max(1, (int) round($srcH * $scale));

            $canvas = imagecreatetruecolor($dstW, $dstH);

            // PNG, WEBP and GIF can carry transparency, which becomes black
            // without this.
            if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            }

            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            match ($mime) {
                'image/jpeg' => imagejpeg($canvas, $target, 82),
                'image/png'  => imagepng($canvas, $target, 6),
                'image/webp' => imagewebp($canvas, $target, 82),
                'image/gif'  => imagegif($canvas, $target),
                default      => null,
            };

        }

    } catch (Throwable $e) {
        error_log('cms_media: could not generate sizes for ' . $filename . ': ' . $e->getMessage());
    }
}

function pmMediaUnlinkFiles(string $filename): void
{
    // basename() is the guard that stops a crafted row deleting outside the
    // uploads directory.
    $filename = basename($filename);

    @unlink(pmMediaPath($filename));
    foreach (array_keys(PM_MEDIA_SIZES) as $size) {
        @unlink(pmMediaPath($filename, $size));
    }
}

function pmMediaDelete(PDO $pdo, int $id): bool
{
    try {
        $row = pmMediaFind($pdo, $id);
        if (!$row) {
            return false;
        }

        $pdo->prepare('DELETE FROM cms_media WHERE id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM cms_media_usage WHERE media_id = ?')->execute([$id]);
        pmMediaUnlinkFiles($row['filename']);

        return true;
    } catch (Throwable $e) {
        error_log('cms_media: delete failed for ' . $id . ': ' . $e->getMessage());

        return false;
    }
}

function pmMediaFind(PDO $pdo, int $id): ?array
{
    try {
        ensureMediaSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM cms_media WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    } catch (Throwable $e) {
        error_log('cms_media: lookup failed: ' . $e->getMessage());

        return null;
    }
}

/** @return array<int, array<string, mixed>> */
function pmMediaUsage(PDO $pdo, int $id): array
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM cms_media_usage WHERE media_id = ? ORDER BY entity_type, entity_id');
        $stmt->execute([$id]);

        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function pmMediaRecordUsage(PDO $pdo, int $mediaId, string $entityType, string $entityId, ?string $label = null): void
{
    if ($mediaId <= 0) {
        return;
    }

    try {
        ensureMediaSchema($pdo);
        $pdo->prepare(
            'INSERT INTO cms_media_usage (media_id, entity_type, entity_id, label)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE label = VALUES(label)'
        )->execute([$mediaId, mb_substr($entityType, 0, 48), mb_substr($entityId, 0, 64),
                    $label !== null ? mb_substr($label, 0, 160) : null]);
    } catch (Throwable $e) {
        error_log('cms_media: could not record usage: ' . $e->getMessage());
    }
}

function pmMediaClearUsage(PDO $pdo, string $entityType, string $entityId): void
{
    try {
        $pdo->prepare('DELETE FROM cms_media_usage WHERE entity_type = ? AND entity_id = ?')
            ->execute([$entityType, $entityId]);
    } catch (Throwable $e) {
        error_log('cms_media: could not clear usage: ' . $e->getMessage());
    }
}

function pmMediaHumanSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }

    return $bytes . ' B';
}
