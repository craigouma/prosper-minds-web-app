<?php

function ensureRedirectSchema(PDO $pdo): void
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
            error_log('cms_redirects: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `cms_redirects` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `from_path` VARCHAR(255) NOT NULL,
              `to_path` VARCHAR(255) NOT NULL,
              `status_code` SMALLINT NOT NULL DEFAULT 301,
              `hits` INT NOT NULL DEFAULT 0,
              `last_hit_at` TIMESTAMP NULL DEFAULT NULL,
              `created_by` VARCHAR(64) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY `uq_cms_redirects_from` (`from_path`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `cms_not_found` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `path` VARCHAR(255) NOT NULL,
              `hits` INT NOT NULL DEFAULT 1,
              `referrer` VARCHAR(255) DEFAULT NULL,
              `last_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY `uq_cms_not_found_path` (`path`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    } catch (Throwable $e) {
        error_log('cms_redirects: schema check failed: ' . $e->getMessage());
    }
}

function pmRequestPath(): string
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

    return '/' . ltrim(mb_substr($path, 0, 255), '/');
}

/**
 * Send the visitor on if a redirect matches, and exit.
 *
 * Called from 404.php, so it only ever runs for a request that already found
 * nothing. That means the redirect table cannot shadow a real page.
 */
function pmRedirectMaybe(?PDO $pdo, string $path): void
{
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM cms_redirects WHERE from_path = ? LIMIT 1');
        $stmt->execute([$path]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $target = (string) $row['to_path'];

        // Only a path on this site, or an http(s) address. Without this a
        // redirect row would be an open redirect anyone could be sent through.
        if (!str_starts_with($target, '/') && preg_match('#^https?://#i', $target) !== 1) {
            return;
        }

        $pdo->prepare('UPDATE cms_redirects SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?')
            ->execute([(int) $row['id']]);

        $code = in_array((int) $row['status_code'], [301, 302, 307, 308], true) ? (int) $row['status_code'] : 301;
        header('Location: ' . $target, true, $code);
        exit;
    } catch (Throwable $e) {
        error_log('cms_redirects: lookup failed: ' . $e->getMessage());
    }
}

/** Record that something asked for a path that does not exist. Best effort. */
function pmNotFoundRecord(?PDO $pdo, string $path): void
{
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        ensureRedirectSchema($pdo);

        $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $referrer = $referrer !== '' ? mb_substr($referrer, 0, 255) : null;

        $pdo->prepare(
            'INSERT INTO cms_not_found (path, referrer) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen_at = NOW(),
                                     referrer = COALESCE(VALUES(referrer), referrer)'
        )->execute([$path, $referrer]);
    } catch (Throwable $e) {
        error_log('cms_not_found: could not record ' . $path . ': ' . $e->getMessage());
    }
}
