<?php
/**
 * Letting a person the form guard stopped through, and having them registered.
 *
 * A stopped submission is kept (includes/formguard.php). Releasing one sends it
 * to the real handler again, so the person gets exactly what anyone else would:
 * the invoice and emails for an event, the confirmation for a webinar, the
 * alerts to the team. Nothing is written to a registration table from here.
 *
 * HOW THE REPLAY GETS PAST THE GUARD
 * ----------------------------------
 * The row is moved to 'releasing' for the few seconds the replay takes, and the
 * replay carries a token made with the site's secret and bound to the row and
 * the email (pmGuardReleaseToken). The guard accepts it only in that state, so a
 * released row cannot be replayed twice and a token cannot be borrowed.
 *
 * The replay is an ordinary HTTP request from this server to its own site, so the
 * handler sees what it always sees: a session, a CSRF token, a form post. That
 * keeps the revenue path unchanged. If the server cannot reach its own address
 * the release fails with a message and the row stays stopped.
 *
 * ROWS STOPPED BEFORE THE FULL FORM WAS KEPT
 * ------------------------------------------
 * Older rows hold only a few fields. A webinar, sponsorship, newsletter or
 * contact row can still be rebuilt from them (the admin chooses the session for
 * a webinar). An event row cannot: it has no phone, no event, no tier and no
 * delegates, and registering somebody on guessed details would be wrong. For
 * those, pmGuardInvite() emails the person a link to register themselves.
 */

require_once __DIR__ . '/formguard.php';

/** Where this server reaches its own site. Overridable from the environment so local dev works. */
function pmGuardLoopbackOrigin(): string
{
    $set = function_exists('pm_env') ? trim((string) pm_env('PM_LOOPBACK_ORIGIN', '')) : '';

    if ($set !== '') {
        return rtrim($set, '/');
    }

    return defined('PM_SITE_ORIGIN') ? PM_SITE_ORIGIN : 'https://prosper-minds.com';
}

/** The handler each form's replay is posted to. */
const PM_GUARD_HANDLERS = [
    'event'       => '/process-registration.php',
    'webinar'     => '/process-webinar-registration.php',
    'sponsorship' => '/process-sponsorship.php',
    'newsletter'  => '/newsletter-subscribe.php',
    'contact'     => '/contact-submit.php',
];

/**
 * One stopped submission, as the admin screen and the release need it.
 *
 * @return array<string, mixed>|null
 */
function pmGuardRow(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM form_quarantine WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        return null;
    }

    $row['summary_arr'] = json_decode((string) $row['summary'], true) ?: [];
    $row['payload_arr'] = $row['payload'] !== null ? (json_decode((string) $row['payload'], true) ?: []) : [];

    return $row;
}

/** Can this row be released as it is, without the admin supplying anything more? */
function pmGuardCanRelease(array $row): bool
{
    return $row['payload_arr'] !== [] || in_array($row['form'], ['webinar', 'sponsorship', 'newsletter', 'contact'], true);
}

/**
 * The fields to replay: the whole stored form when there is one, otherwise a
 * rebuild from the few fields an older row kept. Null when it cannot be rebuilt.
 *
 * @param array<string, mixed> $row
 * @param array<string, mixed> $choice What the admin chose, for a webinar row: ['session_id' => int]
 * @return array<string, mixed>|null
 */
function pmGuardReplayFields(array $row, array $choice): ?array
{
    if ($row['payload_arr'] !== []) {
        return $row['payload_arr'];
    }

    $s = $row['summary_arr'];
    unset($s['recaptcha']);
    $email = trim((string) ($s['email'] ?? ''));

    if ($email === '') {
        return null;
    }

    switch ($row['form']) {
        case 'webinar':
            return [
                'name'         => (string) ($s['name'] ?? ''),
                'email'        => $email,
                'organization' => (string) ($s['organization'] ?? ''),
                'country'      => (string) ($s['country'] ?? ''),
                'session_id'   => (string) max(0, (int) ($choice['session_id'] ?? 0)),
                // The form cannot be sent without agreeing to be emailed, so a
                // row that reached the guard was sent by somebody who had.
                'consent'      => 'yes',
                'return_to'    => '/webinars.php',
            ];

        case 'sponsorship':
            return $s;

        case 'newsletter':
            return ['email' => $email, 'source' => 'footer', 'return_to' => '/'];

        case 'contact':
            return $s;
    }

    return null;
}

/**
 * One request to this site's own address.
 *
 * @param array<string, mixed>|null $post null for a GET
 * @return array{status: int, body: string, cookie: string}
 */
function pmGuardLoopbackRequest(string $path, ?array $post, string $cookie = ''): array
{
    $headers = ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'];

    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $jar  = '';
    $ch   = curl_init(pmGuardLoopbackOrigin() . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'Prosperminds-admin-release',
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$jar): int {
            if (stripos($line, 'Set-Cookie:') === 0 && preg_match('/Set-Cookie:\s*([^;\r\n]+)/i', $line, $m) === 1) {
                $jar .= ($jar === '' ? '' : '; ') . trim($m[1]);
            }

            return strlen($line);
        },
    ]);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    return ['status' => $status, 'body' => is_string($body) ? $body : '', 'cookie' => $jar];
}

/**
 * Let one stopped submission through.
 *
 * @param array<string, mixed> $choice for a webinar row stopped before the full form was kept: ['session_id' => int]
 * @return array{ok: bool, message: string, link: string}
 */
function pmGuardRelease(PDO $pdo, int $id, string $by, array $choice = []): array
{
    $fail = static fn (string $message): array => ['ok' => false, 'message' => $message, 'link' => ''];

    $row = pmGuardRow($pdo, $id);

    if ($row === null || $row['status'] !== 'blocked') {
        return $fail('That one has already been dealt with, or no longer exists.');
    }

    $fields = pmGuardReplayFields($row, $choice);

    if ($fields === null) {
        return $fail('This was stopped before the whole form was kept, so it cannot be put on the list automatically. Use "Invite to register" to email them a link instead.');
    }

    $email = trim((string) ($row['summary_arr']['email'] ?? ($fields['email'] ?? '')));

    // Claim the row. Only one release can hold it, and only while it holds it
    // does the guard accept the token.
    $claim = $pdo->prepare("UPDATE form_quarantine SET status = 'releasing' WHERE id = ? AND status = 'blocked'");
    $claim->execute([$id]);

    if ($claim->rowCount() !== 1) {
        return $fail('Somebody else is dealing with this one.');
    }

    $result = $fail('The release did not finish.');

    try {
        // A session, and the CSRF token that goes with it, from the home page: its
        // footer form carries the site-wide token every handler checks.
        $home  = pmGuardLoopbackRequest('/', null);
        $token = preg_match('/name="csrf_token" value="([a-f0-9]{16,})"/', $home['body'], $m) === 1 ? $m[1] : '';

        if ($home['status'] !== 200 || $token === '' || $home['cookie'] === '') {
            throw new RuntimeException('This server could not reach its own site to replay the form (status ' . $home['status'] . ').');
        }

        $post = $fields + [
            'csrf_token'    => $token,
            'pm_release'    => pmGuardReleaseToken($pdo, $id, $email),
            'pm_release_id' => (string) $id,
        ];

        $reply = pmGuardLoopbackRequest(PM_GUARD_HANDLERS[$row['form']] ?? '/', $post, $home['cookie']);
        $json  = json_decode($reply['body'], true);

        if (!is_array($json)) {
            throw new RuntimeException('The registration page answered with something unexpected (status ' . $reply['status'] . ').');
        }

        if (($json['success'] ?? false) !== true) {
            $result = $fail('The form was refused again: ' . trim((string) ($json['message'] ?? 'no reason given')));
        } else {
            $link = $row['form'] === 'event'
                ? 'registrations.php?search=' . rawurlencode($email)
                : ($row['form'] === 'webinar' ? 'registrations.php?tab=webinars&search=' . rawurlencode($email) : '');
            $what = isset($json['invoice_number']) ? 'invoice ' . $json['invoice_number'] : trim((string) ($json['message'] ?? 'done'));
            $result = ['ok' => true, 'message' => $email . ' is registered (' . $what . ').', 'link' => $link];
        }
    } catch (Throwable $e) {
        error_log('formguard: release of #' . $id . ' failed: ' . $e->getMessage());
        $result = $fail($e->getMessage());
    }

    // Whatever happened, the row leaves 'releasing' so the token stops working.
    $note = mb_substr($result['message'], 0, 255);
    $pdo->prepare(
        $result['ok']
            ? "UPDATE form_quarantine SET status = 'released', resolved_at = NOW(), resolved_by = ?, resolution = ? WHERE id = ?"
            : "UPDATE form_quarantine SET status = 'blocked', resolution = ? WHERE id = ?"
    )->execute($result['ok'] ? [$by, $note, $id] : [$note, $id]);

    if ($result['ok']) {
        pmAudit($pdo, 'blocked_release', 'Let a stopped ' . $row['form'] . ' submission through: ' . $email . ' (row #' . $id . ')');
    }

    return $result;
}

/** Mark a stopped submission as a bot, so it leaves the list but is kept until it expires. */
function pmGuardDismiss(PDO $pdo, int $id, string $by): bool
{
    $stmt = $pdo->prepare(
        "UPDATE form_quarantine SET status = 'dismissed', resolved_at = NOW(), resolved_by = ?, resolution = 'Confirmed as a bot'
          WHERE id = ? AND status = 'blocked'"
    );
    $stmt->execute([$by, $id]);

    return $stmt->rowCount() === 1;
}

/**
 * Email a person whose event registration was stopped before the whole form was
 * kept, with a link to register themselves. Nothing is registered for them: the
 * details that would make a registration (phone, tier, delegates) were never
 * stored, and filling them in from guesses would put somebody on an invoice who
 * did not ask to be.
 *
 * @return array{ok: bool, message: string}
 */
function pmGuardInvite(PDO $pdo, int $id, int $eventId, string $by): array
{
    $row = pmGuardRow($pdo, $id);

    if ($row === null || $row['status'] !== 'blocked' || $row['form'] !== 'event') {
        return ['ok' => false, 'message' => 'That one cannot be invited: it has been dealt with, or it is not an event registration.'];
    }

    $email = trim((string) ($row['summary_arr']['email'] ?? ''));

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'message' => 'There is no usable email address to write to.'];
    }

    $stmt = $pdo->prepare('SELECT id, title, location, date_display, slug FROM events WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($event)) {
        return ['ok' => false, 'message' => 'Choose which school to invite them to.'];
    }

    require_once __DIR__ . '/mail-template-release.php';

    $origin = defined('PM_SITE_ORIGIN') ? PM_SITE_ORIGIN : 'https://prosper-minds.com';
    $slug   = trim((string) ($event['slug'] ?? ''));
    $url    = $origin . ($slug !== '' ? '/school/' . rawurlencode($slug) . '/register' : '/event-registration.php?id=' . (int) $event['id']);
    $first  = trim((string) ($row['summary_arr']['first_name'] ?? ''));

    $subject = 'Please register again for ' . trim((string) $event['title']);

    try {
        $sent = sendEmail($email, $subject, getRegistrationInviteTemplate($first, $event, $url));
    } catch (Throwable $e) {
        $sent = false;
        error_log('formguard: invite to ' . $email . ' failed: ' . $e->getMessage());
    }

    if (!$sent) {
        recordFailedNotification($pdo, null, $email, $subject, 'Invitation to register again was not accepted by the mail server.');

        return ['ok' => false, 'message' => 'The email could not be sent just now. Nothing was changed; try again shortly.'];
    }

    $note = mb_substr('Invited to register for ' . $event['title'], 0, 255);
    $pdo->prepare(
        "UPDATE form_quarantine SET status = 'invited', resolved_at = NOW(), resolved_by = ?, resolution = ? WHERE id = ? AND status = 'blocked'"
    )->execute([$by, $note, $id]);

    pmAudit($pdo, 'blocked_invite', 'Invited ' . $email . ' to register again for ' . $event['title'] . ' (row #' . $id . ')');

    return ['ok' => true, 'message' => $email . ' has been emailed a link to register for ' . $event['title'] . '.'];
}
