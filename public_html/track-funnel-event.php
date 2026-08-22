<?php
/**
 * Fire-and-forget beacon endpoint for ONE client-side funnel stage:
 * form_started (the visitor touched the registration form).
 *
 * Design rules, all of them deliberate:
 *
 *   * It always answers 204, immediately, whatever happens. This is analytics
 *     called from navigator.sendBeacon() — nobody is waiting for it and nobody
 *     can act on an error, so an error message would only leak information.
 *   * It accepts ONE event_type. A browser must not be able to write
 *     submit_success: conversion counts have to come from the server knowing a
 *     row was committed, or the funnel is decorative. See
 *     process-registration.php.
 *   * It reads nothing and returns nothing about any registration. The only
 *     thing it can do is append one row to funnel_events.
 *   * It requires the session's CSRF token, so another origin cannot pump
 *     numbers into the client's reports.
 *   * It dedupes on session + event + day, so a visitor reloading the form
 *     twenty times counts once.
 */

// Answer first. Everything after this point is bookkeeping the caller neither
// sees nor waits for.
http_response_code(204);

if (function_exists('fastcgi_finish_request')) {
    // Under FPM this returns the 204 to the browser now and runs the rest after.
    fastcgi_finish_request();
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        exit;
    }

    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/csrf.php';

    // Same check the registration handler does, before anything is read.
    if (!formCsrfValidate($_POST['csrf_token'] ?? null)) {
        error_log('funnel_events: beacon rejected, missing or invalid CSRF token');
        exit;
    }

    // Only the client-side stage. Anything else is either a mistake or someone
    // trying to inflate the client's conversion numbers.
    if (($_POST['event_type'] ?? '') !== 'form_started') {
        exit;
    }

    // Read-only: no Set-Cookie, because the response has already gone. A
    // visitor with no pm_funnel_sid cookie cannot be correlated to a page view,
    // and an uncorrelatable row is noise, so skip it.
    $sessionId = funnelSessionIdIfSet();
    if ($sessionId === null) {
        exit;
    }

    $eventId = (int) ($_POST['event_id'] ?? 0);
    $eventId = $eventId > 0 ? $eventId : null;

    if (funnelStageLoggedToday($pdo, $sessionId, 'form_started', $eventId)) {
        exit;
    }

    funnelTrackEvent($pdo, 'form_started', [
        'session_id' => $sessionId,
        'event_id'   => $eventId,
        'referrer'   => funnelSanitiseReferrer($_SERVER['HTTP_REFERER'] ?? null),
        'utm'        => [],
    ]);
} catch (Throwable $e) {
    // Includes a broken include, an unreachable database, a missing table.
    // Catch Throwable, not Exception: a truncated include raises ParseError,
    // which extends Error. Logged, never shown.
    error_log('funnel_events: beacon failed — ' . $e->getMessage());
}
