<?php
/**
 * Endpoint for both PFM Insight Live sign-up forms on webinars.php: "join the
 * series" and "register for one session" post here, distinguished only by
 * session_id (0 = the whole series). Mirrors newsletter-subscribe.php's
 * contract throughout, for the same reasons: works with or without
 * JavaScript, a honeypot catches bots without telling them so, and return_to
 * is a same-site path only, never trusted as a full URL.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/webinars.php';
require_once __DIR__ . '/includes/mail-template-webinar.php';

formCsrfEnsureSession();

function webinarWantsJson(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

/** Same rule as newsletterReturnPath(): a root-relative path, or home. */
function webinarReturnPath(mixed $raw): string
{
    if (!is_string($raw) || $raw === '' || strlen($raw) > 512) {
        return '/webinars.php';
    }

    $path = strtok($raw, '#');
    if ($path === false || $path === '') {
        return '/webinars.php';
    }

    if ($path[0] !== '/' || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
        return '/webinars.php';
    }

    if (str_contains($path, "\r") || str_contains($path, "\n")) {
        return '/webinars.php';
    }

    return $path;
}

function webinarRespond(string $status, bool $success, string $message, string $returnPath): never
{
    if (webinarWantsJson()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'status' => $status, 'message' => $message]);
        exit;
    }

    $separator = str_contains($returnPath, '?') ? '&' : '?';
    $location = $returnPath . $separator . 'webinar=' . rawurlencode($status) . '#join';

    header('Location: ' . $location, true, 303);
    exit;
}

$returnPath = webinarReturnPath($_POST['return_to'] ?? null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webinarRespond('method', false, 'Please use the registration form.', $returnPath);
}

if (!formCsrfValidate($_POST['csrf_token'] ?? null)) {
    error_log('Webinar registration rejected: missing or invalid CSRF token');
    webinarRespond('csrf', false, 'That form had expired. Please try once more.', $returnPath);
}

// Honeypot: visually hidden, aria-hidden, out of the tab order. A bot that
// fills every field it finds gets told it worked and nothing is stored.
if (trim((string) ($_POST['company'] ?? '')) !== '') {
    error_log('Webinar registration ignored: honeypot field was filled');
    webinarRespond('ok', true, 'Registered. Check your email for the join link.', $returnPath);
}

$sessionId    = (int) ($_POST['session_id'] ?? 0);
$name         = trim((string) ($_POST['name'] ?? ''));
$email        = trim((string) ($_POST['email'] ?? ''));
$organization = trim((string) ($_POST['organization'] ?? ''));
$country      = trim((string) ($_POST['country'] ?? ''));
$consent      = isset($_POST['consent']);

if ($name === '' || $email === '') {
    webinarRespond('invalid', false, 'Please enter your name and email.', $returnPath);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    webinarRespond('invalid', false, 'Please enter a valid email address.', $returnPath);
}
if (!$consent) {
    webinarRespond('invalid', false, 'Please confirm you are happy to be emailed about this series.', $returnPath);
}

$isSeries = $sessionId <= 0;
$session  = $isSeries ? null : pmWebinarSessionById($pdo, $sessionId);

if (!$isSeries && $session === null) {
    webinarRespond('invalid', false, 'That session is no longer available. Please choose another.', $returnPath);
}

$saved = pmWebinarRegister($pdo, $isSeries ? 0 : $sessionId, $name, $email, $organization, $country);

if ($saved === null) {
    webinarRespond('error', false, 'We could not save your registration. Please email info@prosper-minds.com and we will add you directly.', $returnPath);
}

// The response never depends on whether this email sends: a saved
// registration is a successful registration, the same rule the paid
// registration flow follows, for the same reason.
$pmOrigin = defined('PM_SITE_ORIGIN') ? PM_SITE_ORIGIN : 'https://prosper-minds.com';
$unsubscribeUrl = $pmOrigin . '/webinar-unsubscribe.php?token=' . urlencode($saved['token']);
$upcoming       = $isSeries ? pmWebinarSessionsUpcoming($pdo) : [];
$subject        = getWebinarConfirmationSubject($session ?? ['title' => 'PFM Insight Live'], $isSeries);
$body           = getWebinarConfirmationTemplate($name, $isSeries, $session, $upcoming, $unsubscribeUrl);

try {
    $ok = sendEmail($email, $subject, $body);
    if (!$ok) {
        recordFailedNotification($pdo, null, $email, $subject, 'Webinar confirmation was not accepted by the mail server.');
    }
} catch (Throwable $e) {
    recordFailedNotification($pdo, null, $email, $subject, $e->getMessage());
}

webinarRespond(
    'ok',
    true,
    $isSeries
        ? 'You are registered for every PFM Insight Live session. Check your email for the join link.'
        : 'You are registered. Check your email for the join link.',
    $returnPath
);
