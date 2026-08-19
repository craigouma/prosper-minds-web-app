<?php
declare(strict_types=1);

/**
 * Invoice backlog recovery.
 *
 * Between 14-Aug and 17-Aug 2026 a corrupted vendor/phpmailer file meant that
 * event_registrations rows 12-47 were saved, and their invoice PDFs generated
 * on disk, while every one of those people was told the registration had
 * failed and no email was ever sent. This script re-sends the invitation and
 * invoice emails for those registrations, using the exact same templates,
 * subjects, sender and PDF attachment a successful registration would have.
 *
 * SAFETY MODEL — read before running:
 *
 *   * Dry run is the DEFAULT. Nothing is sent unless --send is passed.
 *   * The mail target defaults to `local`, which forces SMTP at
 *     127.0.0.1:1025 (the Mailpit container in docker-compose.yml). Reaching
 *     real recipients takes an explicit --mail-target=production.
 *   * --mail-target=production combined with --send additionally requires
 *     --confirm-production, so no single typo can mail 36 real people.
 *   * --redirect-to=you@example.com sends every message to one address
 *     instead of the registrants, for a live SMTP smoke test.
 *   * Registrations already sent by this script are skipped on re-runs
 *     (tracked in storage/logs/invoice-backlog-sent.log) unless --force.
 *   * CLI only. Refuses to run over HTTP.
 *
 * USAGE
 *
 *   php tools/send-invoice-backlog.php
 *       Dry run over ids 12-47: who would be emailed what, and whether the
 *       invoice PDF is actually on disk. Sends nothing.
 *
 *   php tools/send-invoice-backlog.php --send
 *       Sends, to the LOCAL Mailpit catcher. Safe.
 *
 *   php tools/send-invoice-backlog.php --send --mail-target=production \
 *       --redirect-to=evans@prosper-minds.com --ids=12 --confirm-production
 *       One real send of one registration, to yourself, over real SMTP.
 *
 *   php tools/send-invoice-backlog.php --send --mail-target=production \
 *       --confirm-production
 *       THE REAL RUN. Emails every listed registrant. Review the dry run first.
 *
 * OPTIONS
 *   --ids=12-47          id range and/or list, e.g. 12-47 or 12,15,20-25
 *   --send               actually send (default: dry run)
 *   --mail-target=X      local (default) | env | production
 *   --redirect-to=EMAIL  send every message to EMAIL instead of the registrant
 *   --include-admin-copies  also send the 3 admin notification copies per
 *                        registration that a live registration sends
 *                        (default off — 36 registrations would be 108 emails)
 *   --force              re-send registrations this script already sent
 *   --confirm-production required with --send --mail-target=production
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("This script is command-line only.\n");
}

$root = dirname(__DIR__);

// ── Arguments ────────────────────────────────────────────────────────────────
$options = [
    'ids' => '12-47',
    'send' => false,
    'mail-target' => 'local',
    'redirect-to' => '',
    'include-admin-copies' => false,
    'force' => false,
    'confirm-production' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if (!str_starts_with($argument, '--')) {
        fwrite(STDERR, "Unrecognised argument: {$argument}\n");
        exit(2);
    }

    $body = substr($argument, 2);
    $eq = strpos($body, '=');
    $key = $eq === false ? $body : substr($body, 0, $eq);
    $value = $eq === false ? true : substr($body, $eq + 1);

    if (!array_key_exists($key, $options)) {
        fwrite(STDERR, "Unknown option: --{$key}\n");
        exit(2);
    }

    $options[$key] = $value;
}

$mailTarget = is_string($options['mail-target']) ? $options['mail-target'] : 'local';
if (!in_array($mailTarget, ['local', 'env', 'production'], true)) {
    fwrite(STDERR, "--mail-target must be one of: local, env, production\n");
    exit(2);
}

$willSend = $options['send'] === true;
$redirectTo = is_string($options['redirect-to']) ? trim($options['redirect-to']) : '';

if ($redirectTo !== '' && !filter_var($redirectTo, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "--redirect-to is not a valid email address\n");
    exit(2);
}

if ($willSend && $mailTarget === 'production' && $options['confirm-production'] !== true) {
    fwrite(STDERR,
        "Refusing to send over production SMTP without --confirm-production.\n"
        . "Run the dry run first, then add --confirm-production when you mean it.\n"
    );
    exit(2);
}

/**
 * Expand "12-47", "12,15", "12,20-25" into a sorted list of ids.
 */
function parseIdSelection(string $selection): array
{
    $ids = [];

    foreach (explode(',', $selection) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
            $from = (int) $m[1];
            $to = (int) $m[2];
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            for ($i = $from; $i <= $to; $i++) {
                $ids[] = $i;
            }
            continue;
        }

        if (ctype_digit($part)) {
            $ids[] = (int) $part;
            continue;
        }

        fwrite(STDERR, "Could not parse id selection: {$part}\n");
        exit(2);
    }

    $ids = array_values(array_unique($ids));
    sort($ids);

    return $ids;
}

$selectedIds = parseIdSelection((string) $options['ids']);
if ($selectedIds === []) {
    fwrite(STDERR, "No ids selected.\n");
    exit(2);
}

// ── Mail target ──────────────────────────────────────────────────────────────
// Set before config.php is loaded so createConfiguredMailer() picks these up
// through getMailSetting(). $_SERVER is checked ahead of the .env file, so this
// wins over whatever a developer has in .env.
if ($mailTarget === 'local') {
    $_SERVER['SMTP_HOST'] = '127.0.0.1';
    $_SERVER['SMTP_PORT'] = '1025';
    $_SERVER['SMTP_SECURE'] = 'none';
    $_SERVER['SMTP_USER'] = 'local@example.test';
    $_SERVER['SMTP_PASS'] = 'mailpit-accepts-anything';
    $_SERVER['SMTP_FROM_EMAIL'] = 'local@example.test';
}

require_once $root . '/includes/config.php';
require_once $root . '/includes/invoice.php';
require_once $root . '/includes/mail-template-user.php';
require_once $root . '/includes/mail-template-admin.php';

if ($mailTarget === 'production') {
    // Pin SMTP to whatever the site_settings table says, so a leftover .env on
    // the machine running this cannot silently redirect a real send.
    $_SERVER['SMTP_HOST'] = getSetting('smtp_host', 'mail.prosper-minds.com');
    $_SERVER['SMTP_PORT'] = getSetting('smtp_port', '587');
    $_SERVER['SMTP_SECURE'] = getSetting('smtp_secure', 'tls');
    $_SERVER['SMTP_USER'] = getSetting('smtp_user', '');
    $_SERVER['SMTP_PASS'] = getSetting('smtp_pass', '');
    $_SERVER['SMTP_FROM_EMAIL'] = getSetting('smtp_from_email', '');
}
// $mailTarget === 'env' deliberately touches nothing.

// ── Already-sent ledger ──────────────────────────────────────────────────────
$ledgerPath = $root . '/storage/logs/invoice-backlog-sent.log';

function loadAlreadySent(string $ledgerPath): array
{
    if (!is_file($ledgerPath)) {
        return [];
    }

    $sent = [];
    foreach (file($ledgerPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/\bid=(\d+)\b.*\bstatus=sent\b/', $line, $m)) {
            $sent[(int) $m[1]] = true;
        }
    }

    return $sent;
}

function recordLedger(string $ledgerPath, int $id, string $email, string $status, string $detail = ''): void
{
    $dir = dirname($ledgerPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents(
        $ledgerPath,
        sprintf("[%s] id=%d email=%s status=%s %s\n", date('c'), $id, $email, $status, $detail),
        FILE_APPEND
    );
}

$alreadySent = $options['force'] === true ? [] : loadAlreadySent($ledgerPath);

// ── Load the registrations ───────────────────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$stmt = $pdo->prepare(
    "SELECT * FROM event_registrations WHERE id IN ({$placeholders}) ORDER BY id"
);
$stmt->execute($selectedIds);
$registrations = $stmt->fetchAll();

$availableEventsStmt = $pdo->query(
    "SELECT id, title, date_display, location FROM events WHERE is_active = 1 ORDER BY event_start_date, id"
);
$availableEvents = $availableEventsStmt ? $availableEventsStmt->fetchAll() : [];

$eventCache = [];
function loadEvent(PDO $pdo, ?int $eventId): ?array
{
    global $eventCache;

    if ($eventId === null || $eventId <= 0) {
        return null;
    }
    if (array_key_exists($eventId, $eventCache)) {
        return $eventCache[$eventId];
    }

    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
    $stmt->execute([$eventId]);
    $row = $stmt->fetch();

    return $eventCache[$eventId] = ($row ?: null);
}

// ── Report header ────────────────────────────────────────────────────────────
$mode = $willSend ? 'SEND' : 'DRY RUN';
echo str_repeat('=', 78), "\n";
printf("Invoice backlog recovery — %s\n", $mode);
printf("  ids selected      : %s (%d)\n", $options['ids'], count($selectedIds));
printf("  rows found        : %d\n", count($registrations));
printf("  mail target       : %s\n", $mailTarget);
printf("  smtp host:port    : %s:%s\n",
    getMailSetting('SMTP_HOST', 'smtp_host', 'mail.prosper-minds.com'),
    getMailSetting('SMTP_PORT', 'smtp_port', '587')
);
printf("  redirect-to       : %s\n", $redirectTo !== '' ? $redirectTo : '(none — real registrant addresses)');
printf("  admin copies      : %s\n", $options['include-admin-copies'] === true ? 'yes' : 'no');
printf("  skip already sent : %s\n", $options['force'] === true ? 'no (--force)' : 'yes');
echo str_repeat('=', 78), "\n\n";

$missingIds = array_values(array_diff($selectedIds, array_map(
    static fn(array $r): int => (int) $r['id'],
    $registrations
)));
if ($missingIds !== []) {
    printf("Note: no such registration for id(s): %s\n\n", implode(', ', $missingIds));
}

// ── Walk the backlog ─────────────────────────────────────────────────────────
$counts = ['sent' => 0, 'would_send' => 0, 'skipped' => 0, 'failed' => 0, 'no_pdf' => 0];

foreach ($registrations as $registration) {
    $id = (int) $registration['id'];
    $email = trim((string) $registration['email']);
    $name = trim($registration['first_name'] . ' ' . $registration['last_name']);
    $invoiceNumber = (string) ($registration['invoice_number'] ?? '');

    if (isset($alreadySent[$id])) {
        printf("  #%-3d %-34s SKIPPED — already sent by this script\n", $id, $email);
        $counts['skipped']++;
        continue;
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        printf("  #%-3d %-34s SKIPPED — no valid email address on the row\n", $id, $email ?: '(blank)');
        $counts['skipped']++;
        continue;
    }

    // Locate the PDF: prefer the stored invoice_path, fall back to the
    // conventional assets/invoices/<invoice_number>.pdf.
    $invoiceAbsolutePath = '';
    $storedPath = trim((string) ($registration['invoice_path'] ?? ''));
    if ($storedPath !== '' && is_file($root . '/' . ltrim($storedPath, '/'))) {
        $invoiceAbsolutePath = $root . '/' . ltrim($storedPath, '/');
    } elseif ($invoiceNumber !== '' && is_file($root . '/assets/invoices/' . $invoiceNumber . '.pdf')) {
        $invoiceAbsolutePath = $root . '/assets/invoices/' . $invoiceNumber . '.pdf';
    }

    if ($invoiceAbsolutePath === '') {
        printf(
            "  #%-3d %-34s NO INVOICE PDF on disk (invoice_number=%s, invoice_path=%s)\n",
            $id, $email, $invoiceNumber !== '' ? $invoiceNumber : '(none)', $storedPath !== '' ? $storedPath : '(none)'
        );
        $counts['no_pdf']++;
        continue;
    }

    $event = loadEvent($pdo, isset($registration['event_id']) ? (int) $registration['event_id'] : null);
    $eventTitle = $event['title'] ?? (string) $registration['event_name'];

    $attendees = json_decode((string) ($registration['attendee_details'] ?? ''), true);
    if (!is_array($attendees)) {
        $attendees = [];
    }

    $registrationData = [
        'first_name' => $registration['first_name'],
        'last_name' => $registration['last_name'],
        'phone' => $registration['phone'],
        'email' => $email,
        'organization' => $registration['organization'],
        'country' => $registration['country'],
        'address' => $registration['address'],
        'gender' => $registration['gender'],
        'meal_preference' => $registration['meal_preference'],
        'future_topics' => $registration['future_topics'],
        'event_name' => $registration['event_name'],
        'attendee_count' => (int) $registration['attendee_count'],
        'attendees' => $attendees,
        'invoice_number' => $invoiceNumber,
        'currency_code' => $registration['currency_code'],
        'unit_price_amount' => $registration['unit_price_amount'],
        'total_amount' => $registration['total_amount'],
    ];

    $eventDetails = [
        'name' => $eventTitle,
        'date' => $event['date_display'] ?? '',
        'location' => $event['location'] ?? '',
        'price' => $event['price'] ?? '',
        'invoice_number' => $invoiceNumber,
        'attendee_count' => (int) $registration['attendee_count'],
        'total_amount' => $registration['total_amount'],
        'currency_code' => $registration['currency_code'],
    ];

    $recipient = $redirectTo !== '' ? $redirectTo : $email;

    if (!$willSend) {
        printf(
            "  #%-3d %-34s -> %-34s %s | %s %s | %s (%s KB)\n",
            $id,
            $email,
            $recipient,
            $invoiceNumber,
            $registrationData['currency_code'],
            number_format((float) $registration['total_amount'], 2),
            basename($invoiceAbsolutePath),
            number_format(filesize($invoiceAbsolutePath) / 1024, 1)
        );
        printf("       %s | %s | %d delegate(s) | registered %s\n",
            $name,
            $eventTitle,
            (int) $registration['attendee_count'],
            (string) $registration['created_at']
        );
        $counts['would_send']++;
        continue;
    }

    $welcomeBody = getWelcomeEmailTemplate($registrationData, $eventDetails, $availableEvents);
    $invoiceBody = getInvoiceEmailTemplate($registrationData, $eventDetails);
    $invoiceAttachment = [
        ['path' => $invoiceAbsolutePath, 'name' => basename($invoiceAbsolutePath)],
    ];

    $messages = [
        [
            'to' => $recipient,
            'subject' => 'Your Invitation - ' . $eventTitle,
            'message' => $welcomeBody,
            'attachments' => [],
        ],
        [
            'to' => $recipient,
            'subject' => 'Your Invoice - ' . $eventTitle,
            'message' => $invoiceBody,
            'attachments' => $invoiceAttachment,
        ],
    ];

    if ($options['include-admin-copies'] === true) {
        $adminRecipient = $redirectTo !== '' ? $redirectTo : ADMIN_EMAIL;
        $adminBody = getAdminEmailTemplate($registrationData);
        $messages[] = [
            'to' => $adminRecipient,
            'subject' => 'New Event Registration - ' . $eventTitle,
            'message' => $adminBody,
            'attachments' => [],
        ];
        $messages[] = [
            'to' => $adminRecipient,
            'subject' => '[Copy] Your Invitation - ' . $eventTitle,
            'message' => $welcomeBody,
            'attachments' => [],
        ];
        $messages[] = [
            'to' => $adminRecipient,
            'subject' => '[Copy] Your Invoice - ' . $eventTitle,
            'message' => $invoiceBody,
            'attachments' => $invoiceAttachment,
        ];
    }

    try {
        $results = sendEmailMessages($messages);
    } catch (Throwable $e) {
        printf("  #%-3d %-34s FAILED — %s\n", $id, $recipient, $e->getMessage());
        recordFailedNotification($pdo, $id, $recipient, 'Invoice backlog recovery', $e->getMessage());
        recordLedger($ledgerPath, $id, $recipient, 'failed', 'error=' . $e->getMessage());
        $counts['failed']++;
        continue;
    }

    $failures = [];
    foreach ($messages as $index => $message) {
        if (($results[$index]['success'] ?? false) === true) {
            continue;
        }
        $error = (string) ($results[$index]['error'] ?? 'Unknown mail error');
        $failures[] = $message['subject'] . ': ' . $error;
        recordFailedNotification($pdo, $id, (string) $message['to'], (string) $message['subject'], $error);
    }

    if ($failures === []) {
        printf("  #%-3d %-34s SENT (%d message(s), invoice %s)\n", $id, $recipient, count($messages), $invoiceNumber);
        recordLedger($ledgerPath, $id, $recipient, 'sent', 'messages=' . count($messages) . ' invoice=' . $invoiceNumber);
        $counts['sent']++;
    } else {
        printf("  #%-3d %-34s FAILED — %s\n", $id, $recipient, implode(' | ', $failures));
        recordLedger($ledgerPath, $id, $recipient, 'failed', 'errors=' . count($failures));
        $counts['failed']++;
    }
}

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n", str_repeat('-', 78), "\n";
if ($willSend) {
    printf("sent=%d  failed=%d  skipped=%d  no_pdf=%d\n",
        $counts['sent'], $counts['failed'], $counts['skipped'], $counts['no_pdf']);
    if ($counts['failed'] > 0) {
        echo "Failures are recorded in the failed_notifications table and the PHP error log.\n";
    }
    printf("Ledger: %s\n", $ledgerPath);
} else {
    printf("would send to %d registration(s); skipped=%d; missing PDF=%d\n",
        $counts['would_send'], $counts['skipped'], $counts['no_pdf']);
    echo "Nothing was sent. Add --send when the list above is correct.\n";
}
echo str_repeat('-', 78), "\n";

exit($counts['failed'] > 0 ? 1 : 0);
