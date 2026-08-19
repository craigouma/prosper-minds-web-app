<?php
/**
 * Minimal session-based CSRF protection.
 *
 * One token per session (not per request), so a delegate can submit the form
 * more than once — retrying after a validation error, or registering for a
 * second event — without the token going stale under them.
 *
 * Usage:
 *   - On the page that renders the form, BEFORE any output:
 *         require_once 'includes/csrf.php';
 *         formCsrfEnsureSession();
 *     then inside the <form>:
 *         <?php echo formCsrfField(); ?>
 *   - In the handler the form posts to:
 *         if (!formCsrfValidate($_POST['csrf_token'] ?? null)) { ...reject... }
 */

/**
 * Start the session if one is not already running.
 *
 * Must be called before anything is written to the output buffer, otherwise
 * PHP cannot send the session cookie.
 */
function formCsrfEnsureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Return the current session's CSRF token, creating one on first use.
 */
function formCsrfToken(): string
{
    formCsrfEnsureSession();

    if (empty($_SESSION['form_csrf_token']) || !is_string($_SESSION['form_csrf_token'])) {
        $_SESSION['form_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['form_csrf_token'];
}

/**
 * Hidden input carrying the token, ready to drop inside a <form>.
 */
function formCsrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(formCsrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

/**
 * Constant-time comparison of a submitted token against the session's.
 *
 * Returns false when the token is missing, the wrong type, or no token has
 * been issued for this session yet — never "allow by default".
 */
function formCsrfValidate(mixed $submittedToken): bool
{
    formCsrfEnsureSession();

    $expected = $_SESSION['form_csrf_token'] ?? '';

    if (!is_string($expected) || $expected === '' || !is_string($submittedToken) || $submittedToken === '') {
        return false;
    }

    return hash_equals($expected, $submittedToken);
}
