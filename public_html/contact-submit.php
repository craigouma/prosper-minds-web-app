<?php
/**
 * Contact form endpoint.
 *
 * Posted to by the form on contact.php. Built to the same shape as
 * newsletter-subscribe.php, which is the pattern in this codebase for a public,
 * unauthenticated write endpoint.
 *
 * WHAT IT ANSWERS WITH
 * --------------------
 * By default a 303 redirect back to /contact.php#enquiry, with the outcome
 * carried in the session rather than the query string. The newsletter endpoint
 * puts its status on the URL because its only state is one word; a contact form
 * that failed validation has to give the visitor their own paragraph back,
 * which does not belong in a URL and must not be bookmarkable or shareable.
 *
 * If the caller asks for JSON (Accept: application/json, or the
 * X-Requested-With header a fetch/XHR would send) it answers
 * {"success": bool, "status": "...", "message": "...", "errors": {...}},
 * matching the shape process-registration.php and newsletter-subscribe.php
 * already use. local-dev/verify.sh section 10c uses this path.
 *
 * WHAT IT NEVER DOES
 * ------------------
 * It never emits a 500, never prints a database error and never leaves the
 * visitor on a blank page. And it never loses a message: the row is committed
 * before the notification email is attempted, success is reported on the row,
 * and a mail failure is recorded against the row rather than shown to the
 * sender. That is the August 2026 rule (commit 2d05cc1) applied to a second
 * form. See the header of includes/contact.php.
 *
 * Statuses, in the order they are decided:
 *   method   the request was not a POST
 *   csrf     missing, malformed or forged token, nothing is stored
 *   invalid  a field failed validation, nothing is stored
 *   error    the row could not be written, nothing is stored
 *   ok       stored. The email may or may not have gone out; the sender is
 *            told their message is with us either way, because it is.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/csrf.php';

// includes/contact.php is new, and the August 2026 outage was one new file
// arriving incomplete. A truncated PHP file raises ParseError, which extends
// Error, which catch (Exception) does not catch. Load it the same defensive way
// includes/config.php loads includes/funnel.php and newsletter-subscribe.php
// loads includes/newsletter.php.
if (is_file(__DIR__ . '/includes/contact.php')) {
    try {
        require_once __DIR__ . '/includes/contact.php';
    } catch (Throwable $contactLoadError) {
        error_log('Contact helpers unavailable: ' . $contactLoadError->getMessage());
    }
}

if (!function_exists('pmContactValidate')) {
    // Stand-ins. Note these deliberately do NOT pretend the message was stored:
    // an enquiry is not a secondary concern the way a page's copy is, and
    // telling someone we have their message when we do not is the exact failure
    // this whole endpoint exists to prevent. It fails honestly instead.
    function pmContactValidate(array $input): array { return ['values' => [], 'errors' => []]; }
    function pmContactStore(?PDO $pdo, array $values, string $source = 'contact'): int {
        error_log('contact_messages: helpers unavailable, enquiry NOT stored');

        return 0;
    }
    function pmContactMarkNotified(?PDO $pdo, int $messageId): void {}
    function pmContactAlertBody(array $values, int $messageId): string { return ''; }
}

formCsrfEnsureSession();

/**
 * Is the caller asking for a JSON answer rather than a redirect?
 */
function contactWantsJson(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

/**
 * Answer the caller and stop.
 *
 * The redirect target is a fixed literal, not anything submitted. The
 * newsletter endpoint accepts a return_to because its form is in the footer of
 * every page; this form exists on exactly one page, so there is nothing to
 * decide and therefore no open redirect to get wrong.
 *
 * @param array<string, string> $errors
 * @param array<string, string> $values
 */
function contactRespond(
    string $status,
    bool $success,
    string $message,
    array $errors = [],
    array $values = []
): never {
    if (contactWantsJson()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'status'  => $status,
            'message' => $message,
            'errors'  => $errors,
        ]);
        exit;
    }

    // Carried in the session, not the URL: a failed submission has to hand the
    // visitor their own paragraph back, and a query string is the wrong place
    // for someone's enquiry. Read and cleared once by contact.php.
    $_SESSION['pm_contact_flash'] = [
        'status'  => $status,
        'success' => $success,
        'message' => $message,
        'errors'  => $errors,
        // Never repopulate on success. The form is empty again, and holding a
        // delivered message in the session is a copy of personal data with no
        // purpose.
        'values'  => $success ? [] : $values,
    ];

    // 303: the result of a POST is a resource to GET, and the visitor must not
    // be able to re-send by refreshing.
    header('Location: /contact.php#enquiry', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    contactRespond('method', false, 'Please use the enquiry form.');
}

// Reject anything that did not come from a form this session rendered, before
// any input is read, so a forged cross-site post cannot reach the database.
if (!formCsrfValidate($_POST['csrf_token'] ?? null)) {
    error_log('Contact enquiry rejected: missing or invalid CSRF token');
    contactRespond('csrf', false, 'That form had expired. Please send it once more.');
}

// Honeypot. The field is visually hidden, aria-hidden and out of the tab order,
// so a person cannot fill it; a bot that fills every input it finds can. Report
// success and store nothing. Telling a bot it was caught only teaches it, and
// the same reasoning is already applied in newsletter-subscribe.php.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    error_log('Contact enquiry ignored: honeypot field was filled');
    contactRespond('ok', true, 'Thank you. Your enquiry is with the programme office.');
}

$checked = pmContactValidate($_POST);

if ($checked['errors'] !== []) {
    contactRespond(
        'invalid',
        false,
        'Please check the highlighted fields and send the form again.',
        $checked['errors'],
        $checked['values']
    );
}

$messageId = pmContactStore($pdo ?? null, $checked['values'], 'contact');

if ($messageId <= 0) {
    // Nothing was written, so nothing may be claimed. The visitor is given a
    // way to reach us that does not depend on the thing that just failed.
    contactRespond(
        'error',
        false,
        'We could not send that just now. Please email info@prosper-minds.com or call +254 740 582302.',
        [],
        $checked['values']
    );
}

// ── The message is safe from here. Everything below is a notification. ──────
//
// It runs AFTER the commit and its outcome is deliberately not allowed to
// change the answer given to the sender. In August 2026 thirty-six delegates
// were told their registration had failed because sending email was allowed to
// decide whether it had been saved; this is the same trap and the same fix.
//
// Throwable, not Exception: sendEmailMessages() in includes/config.php catches
// Exception, so a ParseError inside vendor/phpmailer would escape it. That is
// precisely how the August failure escaped.
try {
    $sent = sendEmail(
        ADMIN_EMAIL,
        'Website enquiry from ' . $checked['values']['name'],
        // The body is built as plain text because it is an internal
        // notification, then escaped and line-broken because the shared mailer
        // is configured isHTML(true). Escaping matters: the message came from a
        // public form and must not be able to put markup in an inbox.
        nl2br(htmlspecialchars(pmContactAlertBody($checked['values'], $messageId), ENT_QUOTES, 'UTF-8'))
    );

    if ($sent) {
        pmContactMarkNotified($pdo ?? null, $messageId);
    } else {
        error_log('contact_messages: enquiry #' . $messageId . ' stored but the alert email failed');
    }
} catch (Throwable $mailError) {
    error_log('contact_messages: enquiry #' . $messageId . ' stored, alert email threw: ' . $mailError->getMessage());
}

contactRespond('ok', true, 'Thank you. Your enquiry is with the programme office.');
