<?php
/**
 * PFM Insight Live: the free monthly webinar series.
 *
 * A deliberately separate system from the paid-school `events` table, not an
 * extension of it. A webinar has no price, no tiers, no invoice, and one
 * sign-up can cover all thirteen sessions at once, which the `events` schema
 * has no way to express. Keeping the two apart means a change here can never
 * touch the paid registration/invoice path, which is the one piece of this
 * site that must not break.
 *
 * ONE ZOOM LINK PER SESSION, STORED, NOT SHARED. Every session happens to
 * point at the same Zoom room today, but that is a row value set once per
 * session, not a single constant the rows reuse -- changing one session's
 * link later (a new Zoom account, a room collision) never touches another.
 *
 * session_id = 0 MEANS "THE WHOLE SERIES", NOT NULL. A nullable session_id
 * would let MySQL's unique index treat every NULL as distinct, so the same
 * email could register for the series twice with nothing to stop it. 0 is
 * not a real session id, so it is both a safe sentinel and a working key.
 */

function ensureWebinarSchema(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }
    $checked = true;

    try {
        if ($pdo->inTransaction()) {
            error_log('webinars: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS webinar_sessions (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                session_number INT          NOT NULL,
                title          VARCHAR(200) NOT NULL,
                topic          VARCHAR(200) NOT NULL DEFAULT "",
                description    TEXT         NULL,
                best_for       VARCHAR(200) NOT NULL DEFAULT "",
                slug           VARCHAR(160) NOT NULL UNIQUE,
                session_date   DATE         NOT NULL,
                time_label     VARCHAR(100) NOT NULL DEFAULT "12:00 EAT",
                zoom_link      VARCHAR(500) NOT NULL DEFAULT "",
                image_path     VARCHAR(300) NULL,
                is_active      TINYINT(1)   NOT NULL DEFAULT 1,
                sort_order     INT          NOT NULL DEFAULT 0,
                created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Added after the table first shipped, so a database that already has
        // webinar_sessions needs the column too. Checked against
        // information_schema rather than by running the ALTER and swallowing
        // the duplicate-column error on every request.
        try {
            $hasPoster = (int) $pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = DATABASE()
                    AND table_name   = 'webinar_sessions'
                    AND column_name  = 'image_path'"
            )->fetchColumn();

            if ($hasPoster === 0) {
                $pdo->exec('ALTER TABLE webinar_sessions ADD COLUMN image_path VARCHAR(300) NULL AFTER zoom_link');
            }
        } catch (Throwable $e) {
            // A poster is decoration; a failure here must not stop the
            // registration tables below being created.
            error_log('webinars: poster column check failed: ' . $e->getMessage());
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS webinar_registrations (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                session_id        INT          NOT NULL DEFAULT 0,
                name              VARCHAR(150) NOT NULL,
                email             VARCHAR(190) NOT NULL,
                organization      VARCHAR(150) NULL,
                country           VARCHAR(100) NULL,
                unsubscribe_token CHAR(32)     NOT NULL,
                unsubscribed_at   TIMESTAMP    NULL DEFAULT NULL,
                created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_webinar_reg (email, session_id),
                UNIQUE KEY uq_webinar_unsub_token (unsubscribe_token),
                KEY idx_webinar_reg_session (session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // One row per (session, registration) reminder actually sent. Not a
        // column on webinar_registrations: a series sign-up needs a separate
        // "sent" flag per session as each one comes up, not one flag total.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS webinar_reminders_sent (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                session_id     INT       NOT NULL,
                registration_id INT      NOT NULL,
                sent_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_webinar_reminder (session_id, registration_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        error_log('webinars: schema setup failed: ' . $e->getMessage());
    }
}

/** @return array<int, array<string, mixed>> Empty on any failure. */
function pmWebinarSessions(?PDO $pdo, bool $activeOnly = true): array
{
    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        ensureWebinarSchema($pdo);
        $rows = $pdo->query(
            'SELECT * FROM webinar_sessions'
            . ($activeOnly ? ' WHERE is_active = 1' : '')
            . ' ORDER BY sort_order, session_date, id'
        )->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    } catch (Throwable $e) {
        error_log('webinars: session list failed: ' . $e->getMessage());

        return [];
    }
}

/** A webinar's own page anchor. There is one page; sessions are rows on it. */
function pmWebinarSessionsUpcoming(?PDO $pdo): array
{
    $today = date('Y-m-d');

    return array_values(array_filter(
        pmWebinarSessions($pdo),
        static fn (array $s): bool => (string) $s['session_date'] >= $today
    ));
}

function pmWebinarSessionById(?PDO $pdo, int $id): ?array
{
    if (!$pdo instanceof PDO || $id <= 0) {
        return null;
    }

    try {
        ensureWebinarSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM webinar_sessions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        error_log('webinars: session lookup failed: ' . $e->getMessage());

        return null;
    }
}

function pmWebinarSessionBySlug(?PDO $pdo, string $slug): ?array
{
    $slug = trim($slug);
    if (!$pdo instanceof PDO || $slug === '') {
        return null;
    }

    try {
        ensureWebinarSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM webinar_sessions WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        error_log('webinars: session slug lookup failed: ' . $e->getMessage());

        return null;
    }
}

/**
 * "29 October 2026" from a session row's date, for copy and emails.
 */
function pmWebinarDateLong(array $session): string
{
    $ts = strtotime((string) ($session['session_date'] ?? ''));

    return $ts === false ? '' : date('j F Y', $ts);
}

/**
 * Root-relative URL of a session's poster, or '' when it has none.
 *
 * Same rule as pmEventImageUrl(): stored without a leading slash, each path
 * segment encoded. Repeated here rather than called, because the handler and
 * the cron load this file without the page layer that defines that one.
 */
function pmWebinarPosterUrl(array $session): string
{
    $path = trim((string) ($session['image_path'] ?? ''));

    if ($path === '') {
        return '';
    }

    return '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
}

/**
 * Save a registration. sessionId 0 means the whole series.
 *
 * ON DUPLICATE KEY UPDATE rather than refusing a repeat sign-up: someone
 * registering twice with the same email for the same thing is not an error,
 * it is a changed name or organisation, and re-sending a confirmation they
 * already have is harmless.
 *
 * @return array{id: int, token: string}|null null only on a database failure.
 */
function pmWebinarRegister(
    PDO $pdo,
    int $sessionId,
    string $name,
    string $email,
    string $organization,
    string $country
): ?array {
    ensureWebinarSchema($pdo);

    $token = bin2hex(random_bytes(16));

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO webinar_registrations
               (session_id, name, email, organization, country, unsubscribe_token)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               name = VALUES(name),
               organization = VALUES(organization),
               country = VALUES(country),
               unsubscribed_at = NULL'
        );
        $stmt->execute([$sessionId, $name, $email, $organization ?: null, $country ?: null, $token]);

        // lastInsertId() is 0 on an UPDATE branch of an upsert; re-select
        // rather than trust it, so the confirmation email and the unsubscribe
        // link always carry the row that is actually there.
        $find = $pdo->prepare('SELECT id, unsubscribe_token FROM webinar_registrations WHERE email = ? AND session_id = ?');
        $find->execute([$email, $sessionId]);
        $row = $find->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ['id' => (int) $row['id'], 'token' => (string) $row['unsubscribe_token']] : null;
    } catch (Throwable $e) {
        error_log('webinars: registration save failed: ' . $e->getMessage());

        return null;
    }
}

function pmWebinarUnsubscribeByToken(PDO $pdo, string $token): bool
{
    $token = trim($token);
    if ($token === '') {
        return false;
    }

    try {
        ensureWebinarSchema($pdo);
        $stmt = $pdo->prepare('UPDATE webinar_registrations SET unsubscribed_at = NOW() WHERE unsubscribe_token = ? AND unsubscribed_at IS NULL');
        $stmt->execute([$token]);

        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('webinars: unsubscribe failed: ' . $e->getMessage());

        return false;
    }
}

/**
 * Everyone still owed a reminder for one session: registered for that exact
 * session, or for the whole series, and not already reminded for it.
 *
 * @return array<int, array<string, mixed>>
 */
function pmWebinarReminderDue(PDO $pdo, int $sessionId): array
{
    try {
        ensureWebinarSchema($pdo);
        $stmt = $pdo->prepare(
            'SELECT r.* FROM webinar_registrations r
              WHERE r.session_id IN (0, ?)
                AND r.unsubscribed_at IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM webinar_reminders_sent s
                     WHERE s.session_id = ? AND s.registration_id = r.id
                )'
        );
        $stmt->execute([$sessionId, $sessionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('webinars: reminder lookup failed: ' . $e->getMessage());

        return [];
    }
}

/** Stamped before the send, same reasoning as the paid-event resume reminder. */
function pmWebinarReminderMarkSent(PDO $pdo, int $sessionId, int $registrationId): void
{
    try {
        ensureWebinarSchema($pdo);
        $pdo->prepare(
            'INSERT IGNORE INTO webinar_reminders_sent (session_id, registration_id) VALUES (?, ?)'
        )->execute([$sessionId, $registrationId]);
    } catch (Throwable $e) {
        error_log('webinars: could not record reminder sent: ' . $e->getMessage());
    }
}

/** @return array<int, array<string, mixed>> Every registration, newest first. For admin use. */
function pmWebinarRegistrations(PDO $pdo, int $sessionId = -1): array
{
    try {
        ensureWebinarSchema($pdo);

        if ($sessionId >= 0) {
            $stmt = $pdo->prepare('SELECT * FROM webinar_registrations WHERE session_id = ? ORDER BY id DESC');
            $stmt->execute([$sessionId]);
        } else {
            $stmt = $pdo->query('SELECT * FROM webinar_registrations ORDER BY id DESC');
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('webinars: registrations list failed: ' . $e->getMessage());

        return [];
    }
}

function pmWebinarRegistrationCount(PDO $pdo, int $sessionId): int
{
    try {
        ensureWebinarSchema($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM webinar_registrations WHERE session_id IN (0, ?) AND unsubscribed_at IS NULL');
        $stmt->execute([$sessionId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('webinars: registration count failed: ' . $e->getMessage());

        return 0;
    }
}
