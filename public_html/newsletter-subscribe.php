<?php
/**
 * Endpoint for the footer newsletter form.
 *
 * return_to is re-validated here and never trusted from the request: it is a
 * path only, rejecting anything starting "//" to close an open redirect.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/csrf.php';

// includes/newsletter.php is new, and the August 2026 outage was one new file
// arriving incomplete. A truncated PHP file raises ParseError, which extends
// Error, which catch (Exception) does not catch. Load it the same defensive way
// includes/config.php loads includes/funnel.php.
if (is_file(__DIR__ . '/includes/newsletter.php')) {
    try {
        require_once __DIR__ . '/includes/newsletter.php';
    } catch (Throwable $newsletterLoadError) {
        error_log('Newsletter helpers unavailable: ' . $newsletterLoadError->getMessage());
    }
}

if (!function_exists('pmNewsletterSubscribe')) {
    function pmNewsletterSubscribe(?PDO $pdo, string $email, string $source = 'footer'): array {
        error_log('newsletter_subscribers: helpers unavailable, subscription dropped');

        return [
            'status'  => 'error',
            'success' => false,
            'message' => 'We could not save that just now. Please try again shortly.',
        ];
    }
}

formCsrfEnsureSession();

/**
 * Is the caller asking for a JSON answer rather than a redirect?
 */
function newsletterWantsJson(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

/**
 * A safe same-site path to send the visitor back to.
 *
 * Only a root-relative path is ever accepted. Anything else — an absolute URL,
 * a protocol-relative "//evil.example", a scheme, a backslash trick — is
 * discarded in favour of the home page. This endpoint is public and
 * unauthenticated; without this check it would be an open redirect, which is
 * exactly the kind of thing that gets a domain flagged by the same Google Ads
 * review the client is already waiting on.
 */
function newsletterReturnPath(mixed $raw): string
{
    if (!is_string($raw) || $raw === '' || strlen($raw) > 512) {
        return '/';
    }

    // Fragments are re-added by the caller; a submitted one is not trusted.
    $path = strtok($raw, '#');
    if ($path === false || $path === '') {
        return '/';
    }

    if ($path[0] !== '/' || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
        return '/';
    }

    if (str_contains($path, "\r") || str_contains($path, "\n")) {
        return '/';
    }

    return $path;
}

/**
 * Answer the caller and stop. Redirect by default, JSON on request.
 */
function newsletterRespond(string $status, bool $success, string $message, string $returnPath): never
{
    if (newsletterWantsJson()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'status'  => $status,
            'message' => $message,
        ]);
        exit;
    }

    $separator = str_contains($returnPath, '?') ? '&' : '?';
    $location = $returnPath . $separator . 'newsletter=' . rawurlencode($status) . '#newsletter';

    // 303: the result of a POST is a resource to GET, and the visitor must not
    // be able to re-submit by refreshing.
    header('Location: ' . $location, true, 303);
    exit;
}

$returnPath = newsletterReturnPath($_POST['return_to'] ?? null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Not routed through the database at all: a GET to this URL is not a
    // visitor failing to subscribe, it is someone typing the URL in.
    newsletterRespond('method', false, 'Please use the subscribe form.', $returnPath);
}

// Reject anything that did not come from a form this session rendered, before
// any input is read, so a forged cross-site post cannot reach the database.
if (!formCsrfValidate($_POST['csrf_token'] ?? null)) {
    error_log('Newsletter signup rejected: missing or invalid CSRF token');
    newsletterRespond('csrf', false, 'That form had expired. Please try once more.', $returnPath);
}

// Honeypot. The field is visually hidden, aria-hidden and out of the tab order,
// so a person cannot fill it; a bot that fills every input it finds can. Report
// success and store nothing — telling a bot it was caught only teaches it.
if (trim((string) ($_POST['company'] ?? '')) !== '') {
    error_log('Newsletter signup ignored: honeypot field was filled');
    newsletterRespond('ok', true, 'Confirmed. A note goes out whenever new dates are published.', $returnPath);
}

$result = pmNewsletterSubscribe(
    $pdo ?? null,
    (string) ($_POST['email'] ?? ''),
    (string) ($_POST['source'] ?? 'footer')
);

newsletterRespond(
    (string) $result['status'],
    (bool) $result['success'],
    (string) $result['message'],
    $returnPath
);
