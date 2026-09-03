<?php

const PM_TRASH_DAYS = 30;

const PM_TRASH_TYPES = [
    'page'       => 'Page',
    'block'      => 'Page block',
    'media'      => 'File',
    'menu_item'  => 'Menu item',
    'event'      => 'Event',
    'testimonial'=> 'Delegate review',
    'admin_user' => 'Account',
];

function ensureTrashSchema(PDO $pdo): void
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
            error_log('cms_trash: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `cms_trash` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `entity_type` VARCHAR(32) NOT NULL,
              `entity_id` VARCHAR(64) NOT NULL,
              `label` VARCHAR(200) NOT NULL,
              `context` VARCHAR(200) DEFAULT NULL,
              `snapshot` LONGTEXT NOT NULL,
              `deleted_by` VARCHAR(64) DEFAULT NULL,
              `deleted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `restored_at` TIMESTAMP NULL DEFAULT NULL,
              KEY `idx_cms_trash_open` (`restored_at`, `deleted_at`),
              KEY `idx_cms_trash_entity` (`entity_type`, `entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    } catch (Throwable $e) {
        error_log('cms_trash: schema check failed: ' . $e->getMessage());
    }
}

/**
 * Put something in the trash. Returns false when the snapshot could not be
 * stored, and the caller must then NOT delete the original: a delete whose
 * undo was never written is a permanent delete wearing a friendly label.
 */
function pmTrashPut(
    PDO $pdo,
    string $entityType,
    $entityId,
    string $label,
    array $snapshot,
    string $deletedBy,
    ?string $context = null
): bool {
    try {
        ensureTrashSchema($pdo);

        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            error_log('cms_trash: snapshot could not be encoded for ' . $entityType . ' ' . $entityId);

            return false;
        }

        $pdo->prepare(
            'INSERT INTO cms_trash (entity_type, entity_id, label, context, snapshot, deleted_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            mb_substr($entityType, 0, 32),
            mb_substr((string) $entityId, 0, 64),
            mb_substr($label, 0, 200),
            $context !== null ? mb_substr($context, 0, 200) : null,
            $json,
            mb_substr($deletedBy, 0, 64),
        ]);

        return true;
    } catch (Throwable $e) {
        error_log('cms_trash: could not store a snapshot: ' . $e->getMessage());

        return false;
    }
}

/** @return array<int, array<string, mixed>> */
function pmTrashList(PDO $pdo, string $type = ''): array
{
    try {
        ensureTrashSchema($pdo);
        $sql  = 'SELECT * FROM cms_trash WHERE restored_at IS NULL';
        $args = [];
        if ($type !== '' && isset(PM_TRASH_TYPES[$type])) {
            $sql .= ' AND entity_type = ?';
            $args[] = $type;
        }
        $stmt = $pdo->prepare($sql . ' ORDER BY deleted_at DESC LIMIT 300');
        $stmt->execute($args);

        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('cms_trash: list failed: ' . $e->getMessage());

        return [];
    }
}

function pmTrashFind(PDO $pdo, int $id): ?array
{
    try {
        ensureTrashSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM cms_trash WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function pmTrashDaysLeft(array $row): int
{
    $deleted = strtotime((string) $row['deleted_at']);

    return max(0, PM_TRASH_DAYS - (int) floor((time() - $deleted) / 86400));
}

/**
 * Put a snapshot back. Each type knows how to rebuild itself; anything not
 * listed here is reported rather than silently doing nothing.
 *
 * @return array{ok: bool, message: string}
 */
function pmTrashRestore(PDO $pdo, int $trashId): array
{
    $row = pmTrashFind($pdo, $trashId);
    if (!$row || $row['restored_at'] !== null) {
        return ['ok' => false, 'message' => 'That item is not in the trash.'];
    }

    $data = json_decode((string) $row['snapshot'], true);
    if (!is_array($data)) {
        return ['ok' => false, 'message' => 'That item cannot be read and was not restored.'];
    }

    try {
        switch ($row['entity_type']) {
            case 'page':
                $pdo->prepare('UPDATE cms_pages SET trashed_at = NULL WHERE id = ?')
                    ->execute([(int) $row['entity_id']]);
                break;

            case 'block':
                $pdo->prepare('INSERT INTO cms_page_blocks (page_id, block_type, appearance, sort_order, payload)
                               VALUES (?, ?, ?, ?, ?)')
                    ->execute([$data['page_id'], $data['block_type'], $data['appearance'],
                               $data['sort_order'], $data['payload']]);
                break;

            case 'testimonial':
                $pdo->prepare('INSERT INTO cms_testimonials (quote, role, org, event_id, is_published, sort_order, added_by)
                               VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$data['quote'], $data['role'], $data['org'], $data['event_id'],
                               $data['is_published'], $data['sort_order'], $data['added_by']]);
                break;

            case 'menu_item':
                $pdo->prepare('INSERT INTO cms_menu_items (location, label, link_type, target, sort_order, is_active)
                               VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$data['location'], $data['label'], $data['link_type'],
                               $data['target'], $data['sort_order'], $data['is_active']]);
                break;

            case 'media':
                // The files were left on disk precisely so this can work.
                $pdo->prepare('INSERT INTO cms_media (filename, original_name, mime, bytes, width, height,
                                                      alt_text, caption, focal_x, focal_y, uploaded_by)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$data['filename'], $data['original_name'], $data['mime'], $data['bytes'],
                               $data['width'], $data['height'], $data['alt_text'], $data['caption'],
                               $data['focal_x'], $data['focal_y'], $data['uploaded_by']]);
                break;

            case 'event':
            case 'admin_user':
                // The id is written back deliberately. Registrations point at
                // an event id and permissions at an account id, so a restore
                // that allocated a new one would restore the row and lose
                // everything attached to it.
                $table = $row['entity_type'] === 'event' ? 'events' : 'admin_users';
                $cols  = array_keys($data);
                $place = implode(', ', array_fill(0, count($cols), '?'));
                $pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . $place . ')')
                    ->execute(array_values($data));
                break;

            default:
                return ['ok' => false, 'message' => 'This kind of item cannot be restored automatically.'];
        }

        $pdo->prepare('UPDATE cms_trash SET restored_at = NOW() WHERE id = ?')->execute([$trashId]);

        return ['ok' => true, 'message' => 'Restored.'];
    } catch (Throwable $e) {
        error_log('cms_trash: restore failed for ' . $trashId . ': ' . $e->getMessage());

        return ['ok' => false, 'message' => 'That item could not be restored. The error has been logged.'];
    }
}

/**
 * Remove what has passed its 30 days. Runs opportunistically when the trash is
 * viewed, because this hosting has no scheduler, and never lets its own
 * failure interrupt the page.
 */
function pmTrashPurgeExpired(PDO $pdo): int
{
    try {
        ensureTrashSchema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM cms_trash
                                WHERE restored_at IS NULL
                                  AND deleted_at < DATE_SUB(NOW(), INTERVAL ' . PM_TRASH_DAYS . ' DAY)');
        $stmt->execute();
        $expired = $stmt->fetchAll() ?: [];

        foreach ($expired as $row) {
            if ($row['entity_type'] === 'media' && function_exists('pmMediaUnlinkFiles')) {
                $data = json_decode((string) $row['snapshot'], true);
                if (is_array($data) && !empty($data['filename'])) {
                    pmMediaUnlinkFiles((string) $data['filename']);
                }
            }
            if ($row['entity_type'] === 'page') {
                $pdo->prepare('DELETE FROM cms_page_blocks WHERE page_id = ?')->execute([(int) $row['entity_id']]);
                $pdo->prepare('DELETE FROM cms_pages WHERE id = ?')->execute([(int) $row['entity_id']]);
            }
            $pdo->prepare('DELETE FROM cms_trash WHERE id = ?')->execute([(int) $row['id']]);
        }

        return count($expired);
    } catch (Throwable $e) {
        error_log('cms_trash: purge failed: ' . $e->getMessage());

        return 0;
    }
}

function pmTrashCount(PDO $pdo): int
{
    try {
        ensureTrashSchema($pdo);

        return (int) $pdo->query('SELECT COUNT(*) FROM cms_trash WHERE restored_at IS NULL')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
