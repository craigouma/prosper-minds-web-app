<?php

function ensureTestimonialSchema(PDO $pdo): void
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
            error_log('cms_testimonials: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `cms_testimonials` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `quote` TEXT NOT NULL,
              `role` VARCHAR(160) DEFAULT NULL,
              `org` VARCHAR(200) DEFAULT NULL,
              `event_id` INT DEFAULT NULL,
              `is_published` TINYINT(1) NOT NULL DEFAULT 1,
              `sort_order` INT NOT NULL DEFAULT 0,
              `added_by` VARCHAR(64) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY `idx_cms_testimonials_live` (`is_published`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    } catch (Throwable $e) {
        error_log('cms_testimonials: schema check failed: ' . $e->getMessage());
    }
}

/**
 * Published reviews for the public site.
 *
 * Returns the caller's $default whenever the table is missing, unreachable or
 * empty. Same contract as the content and menu layers: a section that empties
 * itself because a table went missing is worse than one briefly out of date.
 *
 * @param array<int, array{quote: string, role: string, org: string}> $default
 * @return array<int, array{quote: string, role: string, org: string}>
 */
function pmTestimonials(?PDO $pdo, array $default = []): array
{
    if (!$pdo instanceof PDO) {
        return $default;
    }

    try {
        $rows = $pdo->query('SELECT quote, role, org FROM cms_testimonials
                              WHERE is_published = 1 ORDER BY sort_order, id')->fetchAll();

        if (!$rows) {
            return $default;
        }

        return array_map(static function (array $row): array {
            return [
                'quote' => (string) $row['quote'],
                'role'  => (string) ($row['role'] ?? ''),
                'org'   => (string) ($row['org'] ?? ''),
            ];
        }, $rows);
    } catch (Throwable $e) {
        error_log('cms_testimonials: read failed: ' . $e->getMessage());

        return $default;
    }
}

/** @return array<int, array<string, mixed>> */
function pmTestimonialsAll(PDO $pdo): array
{
    try {
        ensureTestimonialSchema($pdo);

        return $pdo->query('SELECT * FROM cms_testimonials ORDER BY sort_order, id')->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('cms_testimonials: list failed: ' . $e->getMessage());

        return [];
    }
}

/**
 * Seed the table once from the JSON row the homepage has been reading, so
 * nothing on the live site changes the first time this screen is opened.
 */
function pmTestimonialsSeedOnce(PDO $pdo, array $seed): void
{
    try {
        ensureTestimonialSchema($pdo);

        if ((int) $pdo->query('SELECT COUNT(*) FROM cms_testimonials')->fetchColumn() > 0) {
            return;
        }

        $stmt = $pdo->prepare('INSERT INTO cms_testimonials (quote, role, org, sort_order, added_by)
                               VALUES (?, ?, ?, ?, ?)');
        $sort = 0;
        foreach ($seed as $item) {
            if (trim((string) ($item['quote'] ?? '')) === '') {
                continue;
            }
            $stmt->execute([
                mb_substr((string) $item['quote'], 0, 2000),
                mb_substr((string) ($item['role'] ?? ''), 0, 160) ?: null,
                mb_substr((string) ($item['org'] ?? ''), 0, 200) ?: null,
                $sort,
                'seed',
            ]);
            $sort += 10;
        }
    } catch (Throwable $e) {
        error_log('cms_testimonials: seed failed: ' . $e->getMessage());
    }
}

/**
 * Reviews are open to every signed-in staff member, with no per-account
 * permission gate, because that is what the client asked for. It is safe to be
 * generous here: a delete goes to the trash for 30 days and every change is
 * written to the audit log with the account name.
 */
function pmCanManageTestimonials(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}
