<?php
/**
 * Contact form enquiries.
 *
 * WHY THIS EXISTS
 * ---------------
 * The contact form on the current live index.php is a set of inputs inside a
 * <form> with no action and no method and no name attributes. It posts nowhere.
 * Every enquiry ever typed into it was discarded by the browser. PROJECT.md
 * section 5, Priority 3 flags exactly this and notes it was never confirmed
 * whether the form delivers anywhere; it does not.
 *
 * THE RULE THIS FILE IS BUILT ON
 * ------------------------------
 * A message must never be lost, and the visitor must never be told it was lost
 * when it was not. Those are two different failures and this codebase has
 * already paid for both:
 *
 *   * In August 2026 thirty-six delegates were told their registration had
 *     failed because sending email was allowed to answer "was it saved?".
 *     Commit 2d05cc1 fixed that. The same trap is available here, and the same
 *     answer applies: the DATABASE ROW is the outcome, the email about it is
 *     not.
 *   * The live contact form is the opposite failure: it always reported nothing
 *     at all and stored nothing at all.
 *
 * So pmContactStore() returns true only on a confirmed INSERT, and the alert
 * email is sent afterwards by the caller, outside that judgement. A mail
 * failure marks the row `notified = 0` and is logged; it never turns a stored
 * message into a reported failure, and it never discards the message.
 *
 * That is why pmContactStore() reports its outcome at all, unlike the read
 * helpers in includes/content.php: a person pressing Send genuinely needs to
 * know whether their message was kept.
 *
 * WHAT IS STORED
 * --------------
 * Name, organisation, email, phone, the message, which form it came from, and
 * a timestamp. No IP address, no user agent, no referrer. Same
 * data-minimisation position as funnel_events and newsletter_subscribers, and
 * the same reason: none of the three helps anyone answer an enquiry.
 */

/** Longest message accepted. Comfortably above a real enquiry and far below
 *  anything worth storing from a script. TEXT would hold 65,535 bytes; there is
 *  no reason to let a public form use them. */
const PM_CONTACT_MESSAGE_MAX = 5000;

/**
 * Create the contact_messages table if it is missing.
 *
 * Same "ensure schema on demand" convention as ensureFailedNotificationSchema()
 * in includes/config.php, ensureFunnelEventsSchema() in includes/funnel.php,
 * ensurePageContentSchema() in includes/content.php and
 * ensureNewsletterSubscriberSchema() in includes/newsletter.php. The equivalent
 * up/down pair is scripted in
 * database/migrations/2026-08-28-04-create-contact-messages.*.sql.
 */
function ensureContactMessageSchema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    try {
        // CREATE TABLE implicitly commits in MySQL. Nothing should call this
        // mid-transaction, but the guard costs nothing and the cost of being
        // wrong is a half-written row committed early.
        if ($pdo->inTransaction()) {
            error_log('contact_messages: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                organisation VARCHAR(200) DEFAULT NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(60) DEFAULT NULL,
                message TEXT NOT NULL,
                source VARCHAR(64) DEFAULT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'new',
                notified TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_contact_messages_created (created_at),
                KEY idx_contact_messages_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (Throwable $e) {
        error_log('Could not create contact_messages table: ' . $e->getMessage());
    }
}

/**
 * Check a submitted enquiry and return the cleaned values plus any problems.
 *
 * Separated from storage so the contact page can re-render the form with the
 * visitor's own words still in it. Retyping a paragraph because a phone number
 * was mistyped is how a real enquiry gets abandoned.
 *
 * Errors are keyed by field name so each control can carry aria-invalid and
 * its own message, rather than one generic sentence at the top of the form.
 * The wording is plain and says what to do, and never quotes the input back.
 *
 * @param array<string, mixed> $input Usually $_POST.
 * @return array{values: array<string, string>, errors: array<string, string>}
 */
function pmContactValidate(array $input): array
{
    $trim = static function (string $key, int $max) use ($input): string {
        $value = $input[$key] ?? '';

        if (!is_string($value)) {
            return '';
        }

        // Control characters other than tab and newline have no business in a
        // contact form and are the shape header-injection attempts take.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return mb_substr(trim($value), 0, $max);
    };

    $values = [
        'name'         => $trim('name', 160),
        'organisation' => $trim('organisation', 200),
        'email'        => $trim('email', 190),
        'phone'        => $trim('phone', 60),
        'message'      => $trim('message', PM_CONTACT_MESSAGE_MAX),
    ];

    $errors = [];

    if ($values['name'] === '') {
        $errors['name'] = 'Please tell us your name so we know who to reply to.';
    }

    // Newlines are stripped above, so an address cannot carry a header. The
    // remaining check is that it is an address at all: a reply is the entire
    // point of the form and there is no other way back to the sender.
    if ($values['email'] === '') {
        $errors['email'] = 'Please give an email address so we can reply.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That does not look like a valid email address. Please check it.';
    }

    if ($values['message'] === '') {
        $errors['message'] = 'Please tell us what your enquiry is about.';
    }

    // Organisation and phone are optional on purpose. A delegate asking whether
    // a course still has seats should not have to name their department first.

    return ['values' => $values, 'errors' => $errors];
}

/**
 * Store one enquiry. Returns the new row id, or 0 if nothing was stored.
 *
 * Unlike the content helpers this one reports its outcome, because the visitor
 * is told "we have your message" on the strength of it. It still never throws:
 * a database failure is a calm false, an error_log() line and a message the
 * caller can show, not a stack trace on a marketing page.
 *
 * @param array<string, string> $values Already through pmContactValidate().
 */
function pmContactStore(?PDO $pdo, array $values, string $source = 'contact'): int
{
    if (!$pdo instanceof PDO) {
        error_log('contact_messages: no database handle available, enquiry NOT stored');

        return 0;
    }

    try {
        ensureContactMessageSchema($pdo);

        $pdo->prepare(
            'INSERT INTO contact_messages (name, organisation, email, phone, message, source)
                  VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $values['name'] ?? '',
            ($values['organisation'] ?? '') !== '' ? $values['organisation'] : null,
            $values['email'] ?? '',
            ($values['phone'] ?? '') !== '' ? $values['phone'] : null,
            $values['message'] ?? '',
            substr(trim($source), 0, 64),
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        // Logged with no personal data in the line. The message itself is not
        // echoed into error_log, which on shared hosting is not a private place.
        error_log('contact_messages: could not store an enquiry: ' . $e->getMessage());

        return 0;
    }
}

/**
 * Record that the alert email for a stored enquiry did go out.
 *
 * Best effort and deliberately unable to fail loudly: by the time this runs the
 * message is already safe and the visitor has already been told so. If the
 * update fails the only cost is that a delivered notification still reads as
 * undelivered, which errs towards someone checking a message twice rather than
 * towards nobody checking it at all.
 */
function pmContactMarkNotified(?PDO $pdo, int $messageId): void
{
    if (!$pdo instanceof PDO || $messageId <= 0) {
        return;
    }

    try {
        $pdo->prepare('UPDATE contact_messages SET notified = 1 WHERE id = ?')->execute([$messageId]);
    } catch (Throwable $e) {
        error_log('contact_messages: could not flag message ' . $messageId . ' as notified: ' . $e->getMessage());
    }
}

/**
 * The plain-text alert sent to the programme office.
 *
 * Plain text, not HTML: it is an internal notification whose whole job is to be
 * readable and forwardable, and a text body cannot carry markup out of a public
 * form into someone's inbox.
 *
 * @param array<string, string> $values
 */
function pmContactAlertBody(array $values, int $messageId): string
{
    $lines = [
        'A new enquiry was submitted through the website contact form.',
        '',
        'Reference: contact_messages #' . $messageId,
        'Name: ' . ($values['name'] ?? ''),
        'Organisation: ' . (($values['organisation'] ?? '') !== '' ? $values['organisation'] : 'Not given'),
        'Email: ' . ($values['email'] ?? ''),
        'Phone: ' . (($values['phone'] ?? '') !== '' ? $values['phone'] : 'Not given'),
        '',
        'Message:',
        $values['message'] ?? '',
        '',
        'This message is stored in the contact_messages table whether or not this',
        'email arrived, so it can be recovered from there if anything is missing.',
    ];

    return implode("\n", $lines);
}
