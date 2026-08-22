<?php
/**
 * Registration funnel analytics for the MAIN SITE.
 *
 * What this is: five counters, so the site owner can see where delegates fall
 * out of the registration flow —
 *
 *     page_view -> form_started -> submit_attempt -> submit_success
 *                                               \-> submit_fail
 *
 * What this is NOT, deliberately:
 *
 *   * There is no third-party anything. One first-party cookie
 *     (pm_funnel_sid), one random UUID, 24 hours, same-site — its only job is
 *     to join one visitor's five rows together so a drop-off rate can be
 *     computed. It carries no meaning outside this table.
 *   * No IP address is stored. No user agent. No fingerprinting signal of any
 *     kind. Nothing here can identify a person, which is the point: the client
 *     serves EU and Kenyan public-sector delegates (GDPR / Kenya DPA 2019) and
 *     an internal conversion-rate report is not worth taking on personal data
 *     it does not need.
 *   * The referrer is reduced to scheme + host + path before storage, so a
 *     query string from someone else's site (which can carry their user ids or
 *     search terms) is never persisted here.
 *
 * SAFETY CONTRACT — read before editing anything below.
 *
 * Tracking is a SECONDARY concern. In August 2026 this site told 36 delegates
 * their registration had failed because a secondary concern (sending email) was
 * allowed to decide the primary answer (was it saved?). See the Phase 2 comment
 * in process-registration.php and commit 2d05cc1. Analytics is exactly the same
 * shape of risk, so:
 *
 *   1. funnelTrackEvent() catches Throwable — not Exception. A broken include
 *      or a missing class raises Error, which catch (Exception) does not catch;
 *      that is precisely how the August failure escaped.
 *   2. It NEVER throws, never echoes, never sets an HTTP status, and returns
 *      void. A caller cannot branch on its outcome even by accident.
 *   3. It refuses to run inside an open transaction. CREATE TABLE causes an
 *      implicit COMMIT in MySQL, so an on-demand schema check mid-transaction
 *      could commit a half-written registration. Analytics is never worth that.
 *   4. Every failure goes to error_log() only. Nothing is ever surfaced to a
 *      visitor.
 */

/** First-party cookie holding the correlation id. */
const FUNNEL_COOKIE = 'pm_funnel_sid';

/** 24 hours. Long enough to join a view to a submit, short enough not to be an identity. */
const FUNNEL_COOKIE_LIFETIME = 86400;

/**
 * The only accepted stage names.
 *
 * Enforced here rather than by a database ENUM so a sixth stage can be added
 * later without an ALTER TABLE on what will become the largest table on the
 * site. Also the whitelist that stops the public beacon endpoint writing a
 * stage it has no business writing (see track-funnel-event.php).
 */
const FUNNEL_EVENT_TYPES = [
    'page_view',
    'form_started',
    'submit_attempt',
    'submit_success',
    'submit_fail',
];

/**
 * Create the funnel_events table if it is missing.
 *
 * Same "ensure schema on demand" convention as ensureFailedNotificationSchema()
 * in includes/config.php and ensureRegistrationInvoiceSchema() in
 * includes/invoice.php, so a deploy does not have to be coordinated with a
 * manual phpMyAdmin step. The equivalent up/down pair is also scripted in
 * database/migrations/2026-08-22-01-create-funnel-events.*.sql for anyone who
 * would rather apply it explicitly.
 */
function ensureFunnelEventsSchema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS funnel_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(64) NOT NULL,
                event_type VARCHAR(20) NOT NULL,
                event_id INT DEFAULT NULL,
                registration_id INT DEFAULT NULL,
                referrer VARCHAR(255) DEFAULT NULL,
                utm_source VARCHAR(100) DEFAULT NULL,
                utm_medium VARCHAR(100) DEFAULT NULL,
                utm_campaign VARCHAR(150) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_funnel_events_session (session_id),
                KEY idx_funnel_events_type (event_type),
                KEY idx_funnel_events_created (created_at),
                KEY idx_funnel_events_event (event_id),
                KEY idx_funnel_events_registration (registration_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (Throwable $e) {
        error_log('Could not create funnel_events table: ' . $e->getMessage());
    }
}

/**
 * A fresh random UUID v4. Not derived from anything about the visitor.
 */
function funnelNewSessionId(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant 1

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/**
 * The correlation id carried by the request, if the cookie is present and sane.
 *
 * Read-only: sends no Set-Cookie header, so it is safe to call after output has
 * started. The format check means a hand-crafted cookie cannot smuggle an
 * oversized or oddly-shaped value into the table.
 */
function funnelSessionIdIfSet(): ?string
{
    $raw = $_COOKIE[FUNNEL_COOKIE] ?? null;

    if (!is_string($raw) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $raw)) {
        return null;
    }

    return $raw;
}

/**
 * The correlation id for this visitor, issuing the cookie on first visit.
 *
 * MUST be called before any output, because it may send a Set-Cookie header.
 * Returns '' if the cookie could not be issued, in which case tracking is
 * skipped rather than written against a throwaway id — a row that can never be
 * joined to anything is noise, not data.
 */
function funnelSessionId(): string
{
    static $sessionId = null;

    if ($sessionId !== null) {
        return $sessionId;
    }

    $existing = funnelSessionIdIfSet();
    if ($existing !== null) {
        return $sessionId = $existing;
    }

    if (headers_sent()) {
        return $sessionId = '';
    }

    $sessionId = funnelNewSessionId();

    setcookie(FUNNEL_COOKIE, $sessionId, [
        'expires'  => time() + FUNNEL_COOKIE_LIFETIME,
        'path'     => '/',
        // Lax, not Strict: a delegate arriving from a Google Ads click or an
        // emailed link must keep the same id, or every campaign visit looks
        // like a fresh visitor with no funnel.
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']),
        // No JavaScript reads this. The form-start beacon is same-origin, so
        // the browser attaches the cookie for it automatically.
        'httponly' => true,
    ]);

    // Visible to the rest of THIS request too, so the page view that issues the
    // cookie is recorded under the same id the next request will send back.
    $_COOKIE[FUNNEL_COOKIE] = $sessionId;

    return $sessionId;
}

/**
 * Reduce a referrer to scheme + host + path.
 *
 * Data minimisation: the useful signal is "which site sent them", and a foreign
 * query string can contain that site's own user identifiers or search terms,
 * which this table has no business holding.
 */
function funnelSanitiseReferrer(?string $referrer): ?string
{
    if (!is_string($referrer) || trim($referrer) === '') {
        return null;
    }

    $parts = parse_url(trim($referrer));
    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }

    $clean = ($parts['scheme'] ?? 'http') . '://' . $parts['host'] . ($parts['path'] ?? '');

    return substr($clean, 0, 255);
}

/**
 * Campaign parameters, when a visitor arrives with them on the query string.
 *
 * Truncated to the column widths so an absurdly long parameter is trimmed
 * rather than rejected by the database.
 */
function funnelUtmFromQuery(array $query): array
{
    $pick = static function (string $key, int $maxLength) use ($query): ?string {
        $value = $query[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : substr($value, 0, $maxLength);
    };

    return [
        'utm_source'   => $pick('utm_source', 100),
        'utm_medium'   => $pick('utm_medium', 100),
        'utm_campaign' => $pick('utm_campaign', 150),
    ];
}

/**
 * Has this session already logged this stage for this event today?
 *
 * Used by the public beacon endpoint so a visitor reloading the page twenty
 * times produces one form_started, not twenty. Returns true ("already there,
 * skip") on any error, because the safe failure for a deduplication check is to
 * write nothing.
 */
function funnelStageLoggedToday(PDO $pdo, string $sessionId, string $eventType, ?int $eventId): bool
{
    try {
        ensureFunnelEventsSchema($pdo);

        $stmt = $pdo->prepare(
            'SELECT 1 FROM funnel_events
              WHERE session_id = ?
                AND event_type = ?
                AND event_id <=> ?
                AND created_at >= CURDATE()
              LIMIT 1'
        );
        $stmt->execute([$sessionId, $eventType, $eventId]);

        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('funnel_events: dedupe check failed for ' . $eventType . ' — ' . $e->getMessage());

        return true;
    }
}

/**
 * Record one funnel stage. Best effort, and structurally unable to do harm.
 *
 * Returns void by design: there is no outcome for a caller to branch on, so no
 * caller can accidentally make a delegate's answer depend on analytics. See the
 * SAFETY CONTRACT at the top of this file.
 *
 * @param array $context session_id, event_id, registration_id, referrer, utm
 */
function funnelTrackEvent(PDO $pdo, string $eventType, array $context = []): void
{
    try {
        if (!in_array($eventType, FUNNEL_EVENT_TYPES, true)) {
            error_log('funnel_events: refused unknown event_type ' . $eventType);

            return;
        }

        // Point 3 of the safety contract: CREATE TABLE implicitly commits in
        // MySQL, so touching the database here mid-transaction could commit a
        // half-written registration.
        if ($pdo->inTransaction()) {
            error_log('funnel_events: skipped ' . $eventType . ', called inside an open transaction');

            return;
        }

        $sessionId = $context['session_id'] ?? funnelSessionId();
        if (!is_string($sessionId) || $sessionId === '') {
            return;
        }

        $utm = $context['utm'] ?? funnelUtmFromQuery($_GET ?? []);

        ensureFunnelEventsSchema($pdo);

        $pdo->prepare(
            'INSERT INTO funnel_events
                (session_id, event_type, event_id, registration_id, referrer, utm_source, utm_medium, utm_campaign)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $sessionId,
            $eventType,
            isset($context['event_id']) && (int) $context['event_id'] > 0 ? (int) $context['event_id'] : null,
            isset($context['registration_id']) && (int) $context['registration_id'] > 0 ? (int) $context['registration_id'] : null,
            $context['referrer'] ?? null,
            $utm['utm_source'] ?? null,
            $utm['utm_medium'] ?? null,
            $utm['utm_campaign'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Quiet on purpose. A visitor must never learn that analytics exists,
        // let alone that it is broken.
        error_log('funnel_events: could not record ' . $eventType . ' — ' . $e->getMessage());
    }
}
