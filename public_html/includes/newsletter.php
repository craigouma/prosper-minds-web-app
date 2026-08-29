<?php
/**
 * Newsletter subscriptions.
 *
 * pmNewsletterSubscribe() catches Throwable and never throws: a signup must not
 * be able to 500 the page it sits in. A repeat address is reported as success,
 * because telling a stranger an address is already subscribed leaks who is on
 * the list.
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
