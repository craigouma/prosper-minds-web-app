<?php
/**
 * Newsletter subscriptions.
 *
 * WHY THIS EXISTS
 * ---------------
 * The newsletter field in the current live footer (index.php, and the same
 * markup copied into all three service-*.php pages) is a <form> with no action,
 * no method and no name attribute. It posts nowhere. Every address a visitor
 * has ever typed into it has been silently discarded. PROJECT.md section 5,
 * Priority 3 flags exactly this: "Confirm the contact form and newsletter
 * signup are wired to a real destination rather than silently discarding
 * submissions." This closes the newsletter half of it for the rebuilt pages.
 *
 * The rebuilt footer (includes/layout/footer.php) posts to
 * newsletter-subscribe.php, which calls pmNewsletterSubscribe() below. The
 * live pages are deliberately NOT changed in this phase; they keep their dead
 * form until Phase 2 replaces them wholesale.
 *
 * WHAT IS STORED, AND WHAT IS NOT
 * -------------------------------
 * The email address, the page that collected it, and a timestamp. Nothing
 * else. No IP address, no user agent, no referrer — the same data-minimisation
 * position taken for funnel_events, and for the same reason: the audience
 * includes EU and Kenyan public-sector delegates (GDPR / Kenya DPA 2019) and a
 * mailing list is not worth taking on personal data it does not need.
 *
 * An email address IS personal data. See PHASE1-FOUNDATION-PROGRESS.md,
 * "Before this reaches production", for the consent-wording and unsubscribe
 * questions that are a client decision, not a technical one.
 *
 * SAFETY CONTRACT
 * ---------------
 * A newsletter signup is a SECONDARY concern; the primary outcome is that the
 * page the form sits in renders and keeps working. So:
 *
 *   1. pmNewsletterSubscribe() catches Throwable, not Exception, and never
 *      throws. A missing table or an unreachable database returns an outcome
 *      array, it does not become a 500 on a page whose real job was to show a
 *      delegate a course agenda.
 *   2. The failure message shown to a visitor is calm and actionable and never
 *      exposes a database error. Details go to error_log() only.
 *   3. A repeat submission of an address already on the list is reported as
 *      success, not as an error. It is not a failure from the subscriber's
 *      point of view, and telling a stranger "that address is already
 *      subscribed" leaks who is on the list.
 */

/**
 * Create the newsletter_subscribers table if it is missing.
 *
 * Same "ensure schema on demand" convention as ensureFailedNotificationSchema()
 * in includes/config.php, ensureFunnelEventsSchema() in includes/funnel.php and
 * ensurePageContentSchema() in includes/content.php. The equivalent up/down
 * pair is scripted in
 * database/migrations/2026-08-28-02-create-newsletter-subscribers.*.sql.
 *
 * email carries a UNIQUE index, which is what makes a repeat submit idempotent
 * at the database level rather than by a read-then-write race in PHP.
 */
function ensureNewsletterSubscriberSchema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    try {
        // CREATE TABLE implicitly commits in MySQL. Nothing should be calling
        // this mid-transaction, but the guard costs nothing and the cost of
        // being wrong is a half-written row committed early.
        if ($pdo->inTransaction()) {
            error_log('newsletter_subscribers: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                source VARCHAR(64) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_newsletter_subscribers_email (email),
                KEY idx_newsletter_subscribers_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (Throwable $e) {
        error_log('Could not create newsletter_subscribers table: ' . $e->getMessage());
    }
}

/**
 * Normalise an address for storage and comparison.
 *
 * Lower-cased and trimmed, so "Name@Example.com" and "name@example.com " are
 * one subscriber rather than two. Returns '' for anything that is not a valid
 * address or is too long for the column.
 *
 * 190 characters, not 255: utf8mb4 stores four bytes per character, and a
 * UNIQUE index on a VARCHAR(255) utf8mb4 column needs 1020 bytes, which is
 * over the 767-byte limit on older InnoDB row formats this database may still
 * be using. 190 is the long-standing safe maximum and is far above the longest
 * address anyone has ever actually typed.
 */
function pmNewsletterNormaliseEmail(string $email): string
{
    $email = strtolower(trim($email));

    if ($email === '' || strlen($email) > 190) {
        return '';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    return $email;
}

/**
 * Record a subscription. Best effort, and structurally unable to do harm.
 *
 * Returns an outcome array rather than a bare bool because the endpoint needs
 * to distinguish "we did not accept that address" from "we could not reach the
 * database", and phrase them differently to the visitor:
 *
 *   ['status' => 'ok',      'success' => true,  'message' => ...]
 *   ['status' => 'invalid', 'success' => false, 'message' => ...]
 *   ['status' => 'error',   'success' => false, 'message' => ...]
 *
 * 'ok' covers both a first subscription and a repeat of one already stored;
 * see point 3 of the safety contract above.
 *
 * @param string $source Which form collected it, e.g. 'footer'. Free text,
 *                       truncated to the column width, never shown to anyone.
 * @return array{status: string, success: bool, message: string}
 */
function pmNewsletterSubscribe(?PDO $pdo, string $email, string $source = 'footer'): array
{
    $normalised = pmNewsletterNormaliseEmail($email);

    if ($normalised === '') {
        return [
            'status'  => 'invalid',
            'success' => false,
            'message' => 'Please enter a valid email address.',
        ];
    }

    if (!$pdo instanceof PDO) {
        error_log('newsletter_subscribers: no database handle available, subscription dropped');

        return [
            'status'  => 'error',
            'success' => false,
            'message' => 'We could not save that just now. Please try again shortly.',
        ];
    }

    try {
        ensureNewsletterSubscriberSchema($pdo);

        // The UNIQUE index makes the repeat case a no-op at the database level.
        // "id = id" is a deliberate no-op update: it must not touch created_at,
        // because the first time an address was given is the fact worth keeping.
        $pdo->prepare(
            'INSERT INTO newsletter_subscribers (email, source)
                  VALUES (?, ?)
             ON DUPLICATE KEY UPDATE id = id'
        )->execute([$normalised, substr(trim($source), 0, 64)]);

        return [
            'status'  => 'ok',
            'success' => true,
            'message' => 'Confirmed. A note goes out whenever new dates are published.',
        ];
    } catch (Throwable $e) {
        error_log('newsletter_subscribers: could not record a subscription — ' . $e->getMessage());

        return [
            'status'  => 'error',
            'success' => false,
            'message' => 'We could not save that just now. Please try again shortly.',
        ];
    }
}
