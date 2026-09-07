<?php

/**
 * Unfinished registrations, and the one reminder each of them earns.
 *
 * The registration form is four steps and the email address is on step two, so
 * somebody who leaves on step three or four has told us who they are and has
 * not told us they are done. This stores just enough to write to them once and
 * to hand them back the form already filled in.
 *
 * CONSENT
 * -------
 * The consent box is on step four, which most of these people never reached.
 * The reminder is therefore scoped to the registration they themselves started:
 * one message, about the course they were booking, carrying a one-click opt-out
 * that is honoured for good. It is not a mailing list and nothing here feeds
 * one. See pmResumeSuppressionReason() for everything that stops a send.
 */

const PM_RESUME_TOKEN_BYTES = 16;
const PM_RESUME_EXPIRY_DAYS = 7;
const PM_RESUME_PURGE_DAYS  = 30;

function ensureResumeSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS registration_resumes (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            token        CHAR(32)     NOT NULL,
            session_id   VARCHAR(64)  NOT NULL,
            event_id     INT          NOT NULL,
            email        VARCHAR(190) NOT NULL,
            first_name   VARCHAR(100) NULL,
            last_name    VARCHAR(100) NULL,
            organization VARCHAR(190) NULL,
            phone        VARCHAR(60)  NULL,
            country      VARCHAR(100) NULL,
            last_step    TINYINT      NOT NULL DEFAULT 2,
            created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            reminded_at  TIMESTAMP    NULL DEFAULT NULL,
            completed_at TIMESTAMP    NULL DEFAULT NULL,
            UNIQUE KEY uniq_token (token),
            UNIQUE KEY uniq_session_event (session_id, event_id),
            KEY idx_sweep (reminded_at, completed_at, updated_at),
            KEY idx_email_event (email, event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS registration_reminder_optouts (
            email      VARCHAR(190) NOT NULL PRIMARY KEY,
            created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function pmResumeNewToken(): string
{
    return bin2hex(random_bytes(PM_RESUME_TOKEN_BYTES));
}

/**
 * Record, or update, one unfinished registration.
 *
 * Keyed on the funnel session plus the event, so a visitor moving back and
 * forth through the form updates one row rather than leaving a trail. The token
 * is generated once and kept, so the link in an already-sent email keeps working
 * if the visitor comes back and types some more.
 *
 * Returns false rather than throwing: every caller is a beacon whose failure
 * must not be visible to the person filling in the form.
 */
function pmResumeCapture(PDO $pdo, string $sessionId, int $eventId, array $fields): bool
{
    $email = trim((string) ($fields['email'] ?? ''));

    if ($sessionId === '' || $eventId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {
        ensureResumeSchema($pdo);

        $statement = $pdo->prepare(
            'INSERT INTO registration_resumes
                (token, session_id, event_id, email, first_name, last_name,
                 organization, phone, country, last_step)
             VALUES (:token, :session_id, :event_id, :email, :first_name, :last_name,
                     :organization, :phone, :country, :last_step)
             ON DUPLICATE KEY UPDATE
                email        = VALUES(email),
                first_name   = VALUES(first_name),
                last_name    = VALUES(last_name),
                organization = VALUES(organization),
                phone        = VALUES(phone),
                country      = VALUES(country),
                last_step    = GREATEST(last_step, VALUES(last_step)),
                updated_at   = CURRENT_TIMESTAMP'
        );

        $statement->execute([
            ':token'        => pmResumeNewToken(),
            ':session_id'   => $sessionId,
            ':event_id'     => $eventId,
            ':email'        => mb_substr($email, 0, 190),
            ':first_name'   => pmResumeTrim($fields['first_name']   ?? null, 100),
            ':last_name'    => pmResumeTrim($fields['last_name']    ?? null, 100),
            ':organization' => pmResumeTrim($fields['organization'] ?? null, 190),
            ':phone'        => pmResumeTrim($fields['phone']        ?? null, 60),
            ':country'      => pmResumeTrim($fields['country']      ?? null, 100),
            ':last_step'    => max(2, min(4, (int) ($fields['last_step'] ?? 2))),
        ]);

        return true;
    } catch (Throwable $e) {
        error_log('registration_resumes: capture failed: ' . $e->getMessage());

        return false;
    }
}

function pmResumeTrim($value, int $length): ?string
{
    $value = trim((string) $value);

    return $value === '' ? null : mb_substr($value, 0, $length);
}

/**
 * One unfinished registration by its emailed token, or null.
 *
 * The token is the only credential, so it is compared in the database as an
 * exact match on an indexed unique column and expires after a week. An expired,
 * completed or unknown token returns null and the form simply opens blank.
 */
function pmResumeByToken(?PDO $pdo, ?string $token): ?array
{
    $token = trim((string) $token);

    if (!$pdo instanceof PDO || !preg_match('/^[0-9a-f]{32}$/', $token)) {
        return null;
    }

    try {
        $statement = $pdo->prepare(
            'SELECT * FROM registration_resumes
              WHERE token = :token
                AND completed_at IS NULL
                AND updated_at > DATE_SUB(NOW(), INTERVAL ' . PM_RESUME_EXPIRY_DAYS . ' DAY)
              LIMIT 1'
        );
        $statement->execute([':token' => $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        error_log('registration_resumes: token lookup failed: ' . $e->getMessage());

        return null;
    }
}

/**
 * Mark every unfinished row for this person and course as done.
 *
 * Called after a registration commits. Matching on email as well as session is
 * deliberate: somebody who abandons on their phone and finishes on a laptop is
 * one person who has registered, and must not then be chased about it.
 */
function pmResumeMarkCompleted(PDO $pdo, string $email, int $eventId): void
{
    try {
        ensureResumeSchema($pdo);

        $statement = $pdo->prepare(
            'UPDATE registration_resumes
                SET completed_at = NOW(), updated_at = updated_at
              WHERE email = :email AND event_id = :event_id AND completed_at IS NULL'
        );
        $statement->execute([':email' => $email, ':event_id' => $eventId]);
    } catch (Throwable $e) {
        error_log('registration_resumes: could not mark completed: ' . $e->getMessage());
    }
}

function pmResumeOptOut(PDO $pdo, string $email): bool
{
    try {
        ensureResumeSchema($pdo);

        $statement = $pdo->prepare(
            'INSERT INTO registration_reminder_optouts (email) VALUES (:email)
             ON DUPLICATE KEY UPDATE created_at = created_at'
        );
        $statement->execute([':email' => mb_substr(trim($email), 0, 190)]);

        return true;
    } catch (Throwable $e) {
        error_log('registration_resumes: opt-out failed: ' . $e->getMessage());

        return false;
    }
}

/**
 * Everything that stops a reminder, in one place so the sweep cannot forget one.
 *
 * Returns the reason as a string, or '' when the row may be emailed. Each of
 * these is a real case rather than a hypothetical: people register from a second
 * device, courses come off the calendar between abandoning and the sweep, and a
 * cron that was switched off for a fortnight must not wake up and mail everyone.
 *
 * @return string
 */
function pmResumeSuppressionReason(PDO $pdo, array $row): string
{
    $email   = (string) ($row['email'] ?? '');
    $eventId = (int) ($row['event_id'] ?? 0);

    $registered = $pdo->prepare(
        'SELECT COUNT(*) FROM event_registrations WHERE email = :email AND event_id = :event_id'
    );
    $registered->execute([':email' => $email, ':event_id' => $eventId]);
    if ((int) $registered->fetchColumn() > 0) {
        return 'already registered';
    }

    $optedOut = $pdo->prepare('SELECT COUNT(*) FROM registration_reminder_optouts WHERE email = :email');
    $optedOut->execute([':email' => $email]);
    if ((int) $optedOut->fetchColumn() > 0) {
        return 'opted out';
    }

    $event = $pdo->prepare('SELECT is_active, event_start_date FROM events WHERE id = :id');
    $event->execute([':id' => $eventId]);
    $event = $event->fetch(PDO::FETCH_ASSOC);

    if (!is_array($event)) {
        return 'event no longer exists';
    }
    if ((int) $event['is_active'] !== 1) {
        return 'event is not on the calendar';
    }
    if (($event['event_start_date'] ?? '') !== '' && strtotime((string) $event['event_start_date']) < strtotime(date('Y-m-d'))) {
        return 'event has already run';
    }

    return '';
}

/**
 * Unfinished registrations old enough to chase and young enough to be relevant.
 *
 * The upper bound is what keeps a cron that has been off for a fortnight from
 * waking up and mailing everybody it missed.
 *
 * @return array<int, array<string, mixed>>
 */
function pmResumeDue(PDO $pdo, int $afterMinutes = 60, int $withinDays = 7): array
{
    try {
        ensureResumeSchema($pdo);

        $statement = $pdo->prepare(
            'SELECT * FROM registration_resumes
              WHERE reminded_at IS NULL
                AND completed_at IS NULL
                AND updated_at <= DATE_SUB(NOW(), INTERVAL :after MINUTE)
                AND updated_at >  DATE_SUB(NOW(), INTERVAL :within DAY)
              ORDER BY updated_at'
        );
        $statement->bindValue(':after', $afterMinutes, PDO::PARAM_INT);
        $statement->bindValue(':within', $withinDays, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('registration_resumes: could not list due reminders: ' . $e->getMessage());

        return [];
    }
}

/**
 * updated_at is restated deliberately. The column carries ON UPDATE
 * CURRENT_TIMESTAMP, so without this, stamping a reminder would rewrite the
 * moment the visitor actually left, which is what the purge window and the
 * "abandoned more than an hour ago" test both read.
 */
function pmResumeMarkReminded(PDO $pdo, int $id): void
{
    $statement = $pdo->prepare(
        'UPDATE registration_resumes SET reminded_at = NOW(), updated_at = updated_at WHERE id = :id'
    );
    $statement->execute([':id' => $id]);
}

/**
 * Drop rows nobody will act on again.
 *
 * These hold a name, an email and an employer for somebody who never completed
 * anything, so they are kept for a month and no longer. Opt-outs are never
 * purged: forgetting one would mean mailing that person again.
 */
function pmResumePurge(PDO $pdo, int $olderThanDays = PM_RESUME_PURGE_DAYS): int
{
    try {
        ensureResumeSchema($pdo);

        $statement = $pdo->prepare(
            'DELETE FROM registration_resumes WHERE updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        );
        $statement->bindValue(':days', $olderThanDays, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    } catch (Throwable $e) {
        error_log('registration_resumes: purge failed: ' . $e->getMessage());

        return 0;
    }
}

/**
 * PM_SITE_ORIGIN is defined by the page layout, which the reminder sweep does
 * not load: it runs on cron and renders nothing. The literal is the same one.
 */
function pmResumeOrigin(): string
{
    return defined('PM_SITE_ORIGIN') ? PM_SITE_ORIGIN : 'https://prosper-minds.com';
}

function pmResumeUrl(array $row): string
{
    return pmResumeOrigin() . '/event-registration.php?id=' . (int) $row['event_id']
         . '&resume=' . rawurlencode((string) $row['token']);
}

function pmResumeOptOutUrl(array $row): string
{
    return pmResumeOrigin() . '/registration-reminder-optout.php?t=' . rawurlencode((string) $row['token']);
}
