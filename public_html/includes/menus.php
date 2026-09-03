<?php

const PM_MENU_LOCATIONS = ['header' => 'Header', 'footer' => 'Footer'];

const PM_MENU_LINK_TYPES = [
    'page'     => 'A page',
    'event'    => 'An event',
    'external' => 'An external address',
    'anchor'   => 'A section on this page',
];

function ensureMenuSchema(PDO $pdo): void
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
            error_log('cms_menu_items: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `cms_menu_items` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `location` VARCHAR(16) NOT NULL,
              `parent_id` INT DEFAULT NULL,
              `label` VARCHAR(120) NOT NULL,
              `link_type` VARCHAR(16) NOT NULL DEFAULT \'page\',
              `target` VARCHAR(255) NOT NULL,
              `sort_order` INT NOT NULL DEFAULT 0,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY `idx_cms_menu_location` (`location`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    } catch (Throwable $e) {
        error_log('cms_menu_items: schema check failed: ' . $e->getMessage());
    }
}

/**
 * Menu items for one location, already resolved to hrefs.
 *
 * Returns the caller's $default whenever the table is missing, unreachable or
 * empty. Same contract as the content helpers: a navigation bar that vanishes
 * because a table is gone is worse than one that is briefly out of date.
 *
 * @param array<string, array{label: string, href: string}> $default
 * @return array<string, array{label: string, href: string}>
 */
function pmMenu(?PDO $pdo, string $location, array $default = []): array
{
    if (!$pdo instanceof PDO) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM cms_menu_items
              WHERE location = ? AND is_active = 1 AND parent_id IS NULL
              ORDER BY sort_order, id'
        );
        $stmt->execute([$location]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return $default;
        }

        $items = [];
        foreach ($rows as $row) {
            $href = pmMenuHref($row);
            if ($href === null) {
                continue;
            }
            $items['item' . $row['id']] = ['label' => (string) $row['label'], 'href' => $href];
        }

        return $items ?: $default;
    } catch (Throwable $e) {
        error_log('cms_menu_items: read failed: ' . $e->getMessage());

        return $default;
    }
}

function pmMenuHref(array $row): ?string
{
    $target = trim((string) $row['target']);

    if ($target === '') {
        return null;
    }

    return match ($row['link_type']) {
        'event'    => '/event.php?id=' . rawurlencode($target),
        'anchor'   => str_starts_with($target, '#') ? $target : '#' . $target,
        // Only http and https. A menu row is admin-written, but javascript:
        // and data: URLs have no business in navigation whatever the source.
        'external' => preg_match('#^https?://#i', $target) === 1 ? $target : null,
        default    => '/' . ltrim($target, '/'),
    };
}

/** @return array<int, array<string, mixed>> */
function pmMenuAll(PDO $pdo, string $location): array
{
    try {
        ensureMenuSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM cms_menu_items WHERE location = ? ORDER BY sort_order, id');
        $stmt->execute([$location]);

        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('cms_menu_items: list failed: ' . $e->getMessage());

        return [];
    }
}

function pmMenuSeedFromDefaults(PDO $pdo, string $location, array $items): void
{
    try {
        ensureMenuSchema($pdo);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM cms_menu_items')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $sort = 0;
        $stmt = $pdo->prepare(
            'INSERT INTO cms_menu_items (location, label, link_type, target, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([$location, $item['label'], 'page', ltrim($item['href'], '/'), $sort]);
            $sort += 10;
        }
    } catch (Throwable $e) {
        error_log('cms_menu_items: seed failed: ' . $e->getMessage());
    }
}
