<?php
/**
 * Beacon endpoint: "this person has told us who they are but has not finished."
 *
 * Built on the same rules as track-funnel-event.php, for the same reasons:
 * it answers 204 immediately whatever happens, requires the session's CSRF
 * token so another origin cannot write rows, and reads nothing back. It is
 * called while somebody is filling in a form, so it must never be able to
 * produce output, an error, or a delay that they could notice.
 *
 * It writes exactly one row per session per event and nothing else.
 */

http_response_code(204);

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        exit;
    }

    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/csrf.php';
    require_once __DIR__ . '/includes/funnel.php';
    require_once __DIR__ . '/includes/resume.php';

    if (!formCsrfValidate($_POST['csrf_token'] ?? null)) {
        error_log('registration_resumes: beacon rejected, missing or invalid CSRF token');
        exit;
    }

    // Read-only: the response has already gone, so no cookie can be set now. A
    // visitor with no funnel session cannot be keyed on, so there is nothing to
    // store against.
    $sessionId = funnelSessionIdIfSet();
    if ($sessionId === null) {
        exit;
    }

    pmResumeCapture($pdo, $sessionId, (int) ($_POST['event_id'] ?? 0), [
        'email'        => $_POST['email']        ?? '',
        'first_name'   => $_POST['first_name']   ?? '',
        'last_name'    => $_POST['last_name']    ?? '',
        'organization' => $_POST['organization'] ?? '',
        'phone'        => $_POST['phone']        ?? '',
        'country'      => $_POST['country']      ?? '',
        'last_step'    => $_POST['last_step']    ?? 2,
    ]);
} catch (Throwable $e) {
    error_log('registration_resumes: beacon failed: ' . $e->getMessage());
}
