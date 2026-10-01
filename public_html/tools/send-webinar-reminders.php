<?php
declare(strict_types=1);

/**
 * Reminder sweep for PFM Insight Live. Runs on cron, once a day.
 *
 * The series page promises a reminder before every session, to everyone
 * registered for that one session and everyone registered for the whole
 * series. This finds whichever sessions fall within the reminder window and
 * sends one email per eligible registration, same safety model as
 * send-registration-reminders.php:
 *
 *   * Dry run is the DEFAULT. Nothing is sent unless --send is passed.
 *   * ONE reminder per (session, registration), ever -- webinar_reminders_sent
 *     is stamped BEFORE the send, not after, so a mail server that accepts
 *     and then bounces cannot cause a second attempt at the same person for
 *     the same session.
 *   * An unsubscribed registration is never eligible (pmWebinarReminderDue()
 *     excludes it at the query).
 *   * CLI only. Refuses to run over HTTP.
 *
 * USAGE
 *
 *   php tools/send-webinar-reminders.php
 *       Dry run. Lists who would be emailed, for which session.
 *
 *   php tools/send-webinar-reminders.php --send
 *       The real run. This is what cron calls, once a day.
 *
 * OPTIONS
 *
 *   --send            Actually send. Without it nothing leaves the building.
 *   --days-before=1   How many days ahead of a session counts as "due".
 *   --limit=200       Most messages to send in one sweep.
 *   --quiet           Only print if something happened. For cron.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

require_once $root . '/includes/config.php';
require_once $root . '/includes/webinars.php';
require_once $root . '/includes/mail-template-webinar.php';

$options = getopt('', ['send', 'days-before::', 'limit::', 'quiet']);

$send       = array_key_exists('send', $options);
$quiet      = array_key_exists('quiet', $options);
$daysBefore = max(0, (int) ($options['days-before'] ?? 1));
$limit      = max(1, (int) ($options['limit'] ?? 200));

$out = static function (string $line) use ($quiet): void {
    if (!$quiet) {
        echo $line, PHP_EOL;
    }
};

$out($send ? 'Sending webinar reminders.' : 'DRY RUN. Nothing will be sent. Pass --send to send.');

$targetDate = date('Y-m-d', strtotime('+' . $daysBefore . ' days'));
$out('Sessions due on ' . $targetDate . ' (today + ' . $daysBefore . ' day(s)).');
$out('');

$sessions = array_values(array_filter(
    pmWebinarSessions($pdo),
    static fn (array $s): bool => (string) $s['session_date'] === $targetDate
));

if ($sessions === []) {
    $out('No session falls on that date. Nothing to remind.');
    exit;
}

$sentTotal = 0;

foreach ($sessions as $session) {
    $title = (string) $session['title'];
    $due   = pmWebinarReminderDue($pdo, (int) $session['id']);

    $out(sprintf('Session #%d: %s -- %d due', $session['id'], $title, count($due)));

    foreach ($due as $registration) {
        if ($sentTotal >= $limit) {
            $out(sprintf('Reached the limit of %d. The rest wait for the next sweep.', $limit));
            break 2;
        }

        $label = sprintf('#%d %s', $registration['id'], $registration['email']);

        if (!$send) {
            $out(sprintf('  WOULD SEND  %-40s', $label));
            $sentTotal++;
            continue;
        }

        // Stamped before the send, for the reason every reminder sweep on this
        // site stamps before sending: a send that throws halfway must not
        // leave the pair eligible for a second attempt tomorrow.
        pmWebinarReminderMarkSent($pdo, (int) $session['id'], (int) $registration['id']);

        $ok = sendEmail(
            (string) $registration['email'],
            getWebinarReminderSubject($session),
            getWebinarReminderTemplate(
                $registration,
                $session,
                (defined('PM_SITE_ORIGIN') ? PM_SITE_ORIGIN : 'https://prosper-minds.com')
                    . '/webinar-unsubscribe.php?token=' . urlencode((string) $registration['unsubscribe_token'])
            )
        );

        if ($ok) {
            $out(sprintf('  SENT  %-40s', $label));
            $sentTotal++;
        } else {
            $out(sprintf('  FAIL  %-40s mail was not accepted', $label));
            recordFailedNotification(
                $pdo,
                null,
                (string) $registration['email'],
                getWebinarReminderSubject($session),
                'The webinar reminder for registration ' . $registration['id'] . ' (session ' . $session['id'] . ') was not accepted by the mail server.'
            );
        }
    }
}

$out('');
$out(sprintf('%s: %d.', $send ? 'Sent' : 'Would send', $sentTotal));
