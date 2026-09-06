<?php

const PM_PAGE_STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled'];

const PM_BLOCK_TYPES = [
    'hero'        => 'Hero',
    'richtext'    => 'Rich text',
    'image'       => 'Image',
    'imagetext'   => 'Image and text',
    'stats'       => 'Statistics row',
    'cards'       => 'Card grid',
    'testimonials'=> 'Testimonial set',
    'agenda'      => 'Agenda or accordion',
    'cta'         => 'Call to action band',
    'eventlist'   => 'Event list',
    'contact'     => 'Contact block',
    'embed'       => 'Embed',
];

function ensurePagesSchema(PDO $pdo): void
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
            error_log('cms_pages: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `cms_pages` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `title` VARCHAR(180) NOT NULL,
              `slug` VARCHAR(180) NOT NULL,
              `template` VARCHAR(32) NOT NULL DEFAULT 'standard',
              `page_type` VARCHAR(16) NOT NULL DEFAULT 'flexible',
              `parent_id` INT DEFAULT NULL,
              `status` VARCHAR(16) NOT NULL DEFAULT 'draft',
              `publish_at` DATETIME DEFAULT NULL,
              `seo_title` VARCHAR(255) DEFAULT NULL,
              `seo_description` VARCHAR(320) DEFAULT NULL,
              `noindex` TINYINT(1) NOT NULL DEFAULT 0,
              `trashed_at` TIMESTAMP NULL DEFAULT NULL,
              `updated_by` VARCHAR(64) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `uq_cms_pages_slug` (`slug`),
              KEY `idx_cms_pages_status` (`status`, `trashed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `cms_page_blocks` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `page_id` INT NOT NULL,
              `block_type` VARCHAR(24) NOT NULL,
              `appearance` VARCHAR(8) NOT NULL DEFAULT 'light',
              `sort_order` INT NOT NULL DEFAULT 0,
              `payload` LONGTEXT DEFAULT NULL,
              KEY `idx_cms_page_blocks_page` (`page_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `cms_revisions` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `entity_type` VARCHAR(32) NOT NULL,
              `entity_id` INT NOT NULL,
              `snapshot` LONGTEXT NOT NULL,
              `note` VARCHAR(180) DEFAULT NULL,
              `author` VARCHAR(64) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_cms_revisions_entity` (`entity_type`, `entity_id`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `cms_preview_tokens` (
              `token` CHAR(48) PRIMARY KEY,
              `page_id` INT NOT NULL,
              `expires_at` DATETIME NOT NULL,
              `created_by` VARCHAR(64) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (Throwable $e) {
        error_log('cms_pages: schema check failed: ' . $e->getMessage());
    }
}

function pmPageSlugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-') ?: 'page';
}

/** @return array<int, array<string, mixed>> */
function pmPagesList(PDO $pdo, bool $trashed = false): array
{
    try {
        ensurePagesSchema($pdo);
        $sql = 'SELECT * FROM cms_pages WHERE trashed_at IS ' . ($trashed ? 'NOT NULL' : 'NULL')
             . ' ORDER BY COALESCE(parent_id, id), parent_id IS NOT NULL, title';
        return $pdo->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('cms_pages: list failed: ' . $e->getMessage());

        return [];
    }
}

function pmPageFind(PDO $pdo, int $id): ?array
{
    try {
        ensurePagesSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM cms_pages WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function pmPageFindBySlug(PDO $pdo, string $slug): ?array
{
    try {
        ensurePagesSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM cms_pages WHERE slug = ? AND trashed_at IS NULL');
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('cms_pages: slug lookup failed: ' . $e->getMessage());

        return null;
    }
}

/**
 * Whether a page should be served to the public right now.
 *
 * A scheduled page becomes visible when its time passes, which is checked on
 * read rather than by a job, because this hosting has no reliable scheduler.
 */
function pmPageIsLive(array $page): bool
{
    if ($page['trashed_at'] !== null) {
        return false;
    }
    if ($page['status'] === 'published') {
        return true;
    }
    if ($page['status'] === 'scheduled' && $page['publish_at'] !== null) {
        return strtotime((string) $page['publish_at']) <= time();
    }

    return false;
}

/** @return array<int, array<string, mixed>> */
function pmPageBlocks(PDO $pdo, int $pageId): array
{
    try {
        ensurePagesSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM cms_page_blocks WHERE page_id = ? ORDER BY sort_order, id');
        $stmt->execute([$pageId]);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            $decoded = json_decode((string) $row['payload'], true);
            $row['data'] = is_array($decoded) ? $decoded : [];
        }

        return $rows;
    } catch (Throwable $e) {
        error_log('cms_page_blocks: read failed: ' . $e->getMessage());

        return [];
    }
}

function pmPageSnapshot(PDO $pdo, int $pageId): string
{
    $page   = pmPageFind($pdo, $pageId);
    $blocks = pmPageBlocks($pdo, $pageId);

    return (string) json_encode(['page' => $page, 'blocks' => $blocks],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Store a revision. Best effort: a page save must never fail because its
 * history could not be written.
 */
function pmRevisionStore(PDO $pdo, string $entityType, int $entityId, string $snapshot, string $note, string $author): void
{
    try {
        ensurePagesSchema($pdo);
        $pdo->prepare('INSERT INTO cms_revisions (entity_type, entity_id, snapshot, note, author) VALUES (?, ?, ?, ?, ?)')
            ->execute([$entityType, $entityId, $snapshot, mb_substr($note, 0, 180), mb_substr($author, 0, 64)]);
    } catch (Throwable $e) {
        error_log('cms_revisions: could not store a revision: ' . $e->getMessage());
    }
}

/** @return array<int, array<string, mixed>> */
function pmRevisionList(PDO $pdo, string $entityType, int $entityId, int $limit = 50): array
{
    try {
        ensurePagesSchema($pdo);
        $stmt = $pdo->prepare('SELECT id, note, author, created_at FROM cms_revisions
                                WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT ' . (int) $limit);
        $stmt->execute([$entityType, $entityId]);

        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function pmRevisionFind(PDO $pdo, int $id): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM cms_revisions WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Restore a revision by writing the old content forward as a new state, and
 * recording the restore as its own revision. Nothing is overwritten in place,
 * so a restore can itself be undone.
 */
function pmRevisionRestore(PDO $pdo, int $revisionId, string $author): bool
{
    $revision = pmRevisionFind($pdo, $revisionId);
    if (!$revision) {
        return false;
    }

    $snapshot = json_decode((string) $revision['snapshot'], true);
    if (!is_array($snapshot) || empty($snapshot['page']['id'])) {
        return false;
    }

    $pageId = (int) $snapshot['page']['id'];

    try {
        pmRevisionStore($pdo, 'page', $pageId, pmPageSnapshot($pdo, $pageId),
                        'Before restoring revision ' . $revisionId, $author);

        $p = $snapshot['page'];
        $pdo->prepare('UPDATE cms_pages SET title = ?, seo_title = ?, seo_description = ?, noindex = ?, updated_by = ? WHERE id = ?')
            ->execute([$p['title'], $p['seo_title'], $p['seo_description'], (int) $p['noindex'], $author, $pageId]);

        $pdo->prepare('DELETE FROM cms_page_blocks WHERE page_id = ?')->execute([$pageId]);
        $stmt = $pdo->prepare('INSERT INTO cms_page_blocks (page_id, block_type, appearance, sort_order, payload) VALUES (?, ?, ?, ?, ?)');
        foreach ($snapshot['blocks'] ?? [] as $block) {
            $stmt->execute([$pageId, $block['block_type'], $block['appearance'], $block['sort_order'], $block['payload']]);
        }

        pmRevisionStore($pdo, 'page', $pageId, pmPageSnapshot($pdo, $pageId),
                        'Restored revision ' . $revisionId, $author);

        return true;
    } catch (Throwable $e) {
        error_log('cms_revisions: restore failed: ' . $e->getMessage());

        return false;
    }
}

function pmPreviewTokenCreate(PDO $pdo, int $pageId, string $author, int $hours = 48): ?string
{
    try {
        ensurePagesSchema($pdo);
        $token = bin2hex(random_bytes(24));
        $pdo->prepare('INSERT INTO cms_preview_tokens (token, page_id, expires_at, created_by) VALUES (?, ?, ?, ?)')
            ->execute([$token, $pageId, date('Y-m-d H:i:s', time() + $hours * 3600), mb_substr($author, 0, 64)]);

        return $token;
    } catch (Throwable $e) {
        error_log('cms_preview_tokens: could not create a token: ' . $e->getMessage());

        return null;
    }
}

function pmPreviewTokenPageId(PDO $pdo, string $token): ?int
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT page_id FROM cms_preview_tokens WHERE token = ? AND expires_at > NOW()');
        $stmt->execute([$token]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    } catch (Throwable $e) {
        return null;
    }
}
