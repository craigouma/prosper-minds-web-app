<?php
declare(strict_types=1);

/**
 * Reminder sweep for unfinished registrations. Runs on cron.
 *
 * Somebody who reached step two of the registration form gave us an email
 * address and then left. This finds those, waits an hour in case they are
 * simply making tea, and sends each one a single message with a link back to
 * the form already filled in.
 *
 * SAFETY MODEL
 *
 *   * Dry run is the DEFAULT. Nothing is sent unless --send is passed.
 *   * ONE reminder per unfinished registration, ever. reminded_at is stamped
 *     whether or not the send succeeded, because a mail server that accepts and
 *     then bounces must not cause a second attempt at the same person.
 *   * Every suppression lives in pmResumeSuppressionReason(): already
 *     registered (on any device), opted out, event withdrawn, event already run.
 *   * Rows older than --within days are skipped entirely, so a cron that has
 *     been switched off for a fortnight cannot wake up and mail everyone it
 *     missed.
 *   * Rows are purged after 30 days. They hold a name and an employer for
 *     somebody who never completed anything.
 *   * CLI only. Refuses to run over HTTP.
 *
 * USAGE
 *
 *   php tools/send-registration-reminders.php
 *       Dry run. Lists who would be emailed and who is suppressed, and why.
 *
 *   php tools/send-registration-reminders.php --send
 *       The real run. This is what cron calls.
 *
 * OPTIONS
 *
 *   --send            Actually send. Without it nothing leaves the building.
 *   --after=60        Minutes to wait after somebody stops typing. Default 60.
 *   --within=7        Ignore anything abandoned more than this many days ago.
 *   --limit=100       Most messages to send in one sweep.
 *   --no-purge        Skip the retention delete.
 *   --quiet           Only print if something happened. For cron.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

require_once $root . '/includes/config.php';
require_once $root . '/includes/events.php';
require_once $root . '/includes/resume.php';
require_once $root . '/includes/mail-template-resume.php';

$options = getopt('', ['send', 'after::', 'within::', 'limit::', 'no-purge', 'quiet']);

$send   = array_key_exists('send', $options);
$quiet  = array_key_exists('quiet', $options);
$after  = max(1, (int) ($options['after']  ?? 60));
$within = max(1, (int) ($options['within'] ?? 7));
$limit  = max(1, (int) ($options['limit']  ?? 100));

$out = static function (string $line) use ($quiet): void {
    if (!$quiet) {
        echo $line, PHP_EOL;
    }
};

$out($send ? 'Sending reminders.' : 'DRY RUN. Nothing will be sent. Pass --send to send.');
$out(sprintf('Abandoned more than %d minutes ago and less than %d days ago.', $after, $within));
$out('');

$due = pmResumeDue($pdo, $after, $within);

if ($due === []) {
    $out('Nothing to remind.');
} else {
    $sent = 0;
    $skipped = 0;

    foreach ($due as $row) {
        if ($sent >= $limit) {
            $out(sprintf('Reached the limit of %d. The rest wait for the next sweep.', $limit));
            break;
        }

        $label = sprintf('#%d %s (event %d)', $row['id'], $row['email'], $row['event_id']);

        try {
            $reason = pmResumeSuppressionReason($pdo, $row);
        } catch (Throwable $e) {
            // A failed suppression check must never be read as "safe to send".
            $out(sprintf('  SKIP  %-52s could not be checked: %s', $label, $e->getMessage()));
            $skipped++;
            continue;
        }

        if ($reason !== '') {
            $out(sprintf('  SKIP  %-52s %s', $label, $reason));
            if ($send) {
                pmResumeMarkReminded($pdo, (int) $row['id']);
            }
            $skipped++;
            continue;
        }

        $event = pmEventById($pdo, (int) $row['event_id']);
        if ($event === null) {
            $out(sprintf('  SKIP  %-52s event could not be loaded', $label));
            $skipped++;
            continue;
        }

        if (!$send) {
            $out(sprintf('  WOULD SEND  %-46s %s', $label, $event['title']));
            $sent++;
            continue;
        }

        // Stamped BEFORE the send, not after. A send that throws halfway, or
        // succeeds and then reports a failure, must not leave the row eligible
        // for a second attempt at the same person tomorrow.
        pmResumeMarkReminded($pdo, (int) $row['id']);

        $ok = sendEmail(
            (string) $row['email'],
            getResumeReminderSubject($event),
            getResumeReminderTemplate($row, $event, pmResumeUrl($row), pmResumeOptOutUrl($row))
        );

        if ($ok) {
            $out(sprintf('  SENT  %-52s %s', $label, $event['title']));
            $sent++;
        } else {
            $out(sprintf('  FAIL  %-52s mail was not accepted', $label));
            recordFailedNotification(
                $pdo,
                null,
                (string) $row['email'],
                getResumeReminderSubject($event),
                'The reminder for unfinished registration ' . $row['id'] . ' was not accepted by the mail server.'
            );
        }
    }

    $out('');
    $out(sprintf('%s: %d. Suppressed: %d.', $send ? 'Sent' : 'Would send', $sent, $skipped));
}

if (!array_key_exists('no-purge', $options)) {
    $purged = $send ? pmResumePurge($pdo) : 0;
    if ($purged > 0) {
        $out(sprintf('Purged %d abandoned registration(s) older than %d days.', $purged, PM_RESUME_PURGE_DAYS));
    }
}
