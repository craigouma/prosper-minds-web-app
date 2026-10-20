<?php
/**
 * Alerts to the team when somebody registers.
 *
 * The people to tell are a setting (notify_registration_emails, comma
 * separated, edited under Settings), not code, so Lydia and Shillah, or
 * whoever replaces them, can be changed without a deploy. With nothing set no
 * alert is sent and nothing else changes.
 *
 * An alert is a secondary concern. A failure here is recorded in
 * failed_notifications and never reaches the person who registered, and a
 * burst is capped so a flood can never bury anyone's inbox.
 */

/** Alerts allowed per kind per hour before the rest are held back. */
const PM_NOTIFY_HOURLY_CAP = 20;

/** @return array<int, string> Valid, distinct, lower-cased addresses. */
function pmNotifyRecipients(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute(['notify_registration_emails']);
        $raw = (string) ($stmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        error_log('notify: could not read recipients: ' . $e->getMessage());

        return [];
    }

    $out = [];
    foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $address) {
        $address = strtolower(trim($address));

        if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
            $out[$address] = $address;
        }
    }

    return array_slice(array_values($out), 0, 10);
}

/**
 * May another alert of this kind go out this hour? 'send', 'notice' (the one
 * message that says the rest are held back) or 'skip'. Counted in the guard's
 * throttle table so there is one place that holds counters.
 */
function pmNotifyAllowance(PDO $pdo, string $kind): string
{
    try {
        pmGuardEnsureSchema($pdo);

        $form = substr('notify-' . $kind, 0, 24);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM form_throttle WHERE form = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)'
        );
        $stmt->execute([$form]);
        $count = (int) $stmt->fetchColumn();

        $pdo->prepare('INSERT INTO form_throttle (form, ip_hash) VALUES (?, ?)')->execute([$form, 'notify-all-00000']);

        if ($count < PM_NOTIFY_HOURLY_CAP) {
            return 'send';
        }

        return $count === PM_NOTIFY_HOURLY_CAP ? 'notice' : 'skip';
    } catch (Throwable $e) {
        error_log('notify: could not count alerts, sending: ' . $e->getMessage());

        return 'send';
    }
}

/**
 * Email every recipient. Never throws.
 *
 * @param string $kind 'event' or 'webinar'
 * @param array<int, string> $alreadyTold Addresses that get this alert some other way, so they are not sent it twice.
 */
function pmNotifyAdmins(PDO $pdo, string $kind, string $subject, string $htmlBody, ?int $registrationId = null, array $alreadyTold = []): void
{
    try {
        $told       = array_map('strtolower', $alreadyTold);
        $recipients = array_values(array_filter(
            pmNotifyRecipients($pdo),
            static fn (string $to): bool => !in_array($to, $told, true)
        ));

        if ($recipients === []) {
            return;
        }

        $allowance = pmNotifyAllowance($pdo, $kind);

        if ($allowance === 'skip') {
            return;
        }

        if ($allowance === 'notice') {
            $subject  = 'More ' . $kind . ' registrations than usual';
            $htmlBody = '<p>More than ' . PM_NOTIFY_HOURLY_CAP . ' ' . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8')
                . ' registrations have arrived in the last hour, so alerts for the rest are held back until the hour is up. '
                . 'Every registration is still saved. Open the Registrations screen in the admin panel to see them all.</p>';
        }

        foreach ($recipients as $to) {
            try {
                if (!sendEmail($to, $subject, $htmlBody)) {
                    recordFailedNotification($pdo, $registrationId, $to, $subject, 'Registration alert was not accepted by the mail server.');
                }
            } catch (Throwable $e) {
                recordFailedNotification($pdo, $registrationId, $to, $subject, $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('notify: alert failed: ' . $e->getMessage());
    }
}
