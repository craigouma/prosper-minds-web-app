<?php

// Errors are logged, never printed. A notice printed into the response would
// corrupt the JSON that process-registration.php returns, break any header()
// redirect issued after it, and disclose the absolute server path. Set
// PM_DISPLAY_ERRORS=1 in a local .env to see them on screen instead.
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', (getenv('PM_DISPLAY_ERRORS') === '1') ? '1' : '0');

// Everyone reading this runs on East Africa Time. Set here and on the database
// connection below: if only one of them moved, a row written now would read
// back three hours out.
date_default_timezone_set('Africa/Nairobi');

require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Database credentials ────────────────────────────────────────────────────
// db-credentials.php no longer holds literal credentials; it reads them from
// the environment / a gitignored .env file. See .env.example and env.php.
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db-credentials.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+03:00'");
} catch (PDOException $e) {
    die("Database connection failed. Please contact the administrator.");
}

// ── Site settings loaded from DB (falls back to defaults if table missing) ──
$siteSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    foreach ($stmt->fetchAll() as $row) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Table not yet created – first run
}

function getSetting(string $key, string $default = ''): string {
    global $siteSettings;
    return $siteSettings[$key] ?? $default;
}

/**
 * Resolve a mail setting: environment variable first, then the site_settings
 * table, then the hardcoded default.
 *
 * Production behaviour is unchanged as long as none of these environment
 * variables are set — the admin UI keeps managing SMTP through site_settings
 * exactly as before. The override exists so a developer (or the invoice
 * recovery script) can force all outbound mail to a local mail-catcher without
 * editing the database, and so the SMTP password can eventually be moved out
 * of the database into the server environment.
 */
function getMailSetting(string $envKey, string $settingKey, string $default = ''): string {
    $fromEnv = pm_env($envKey);
    if ($fromEnv !== null && $fromEnv !== '') {
        return $fromEnv;
    }

    return getSetting($settingKey, $default);
}

// Convenience constants (admin templates reference these)
if (!defined('ADMIN_EMAIL'))   define('ADMIN_EMAIL',   getSetting('admin_email',   'info@prosper-minds.com'));
if (!defined('COMPANY_NAME'))  define('COMPANY_NAME',  getSetting('company_name',  'ProsperMinds'));
if (!defined('COMPANY_COLOR')) define('COMPANY_COLOR', getSetting('company_color', '#00B140'));

// ── Email via PHPMailer ─────────────────────────────────────────────────────
function createConfiguredMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host      = getMailSetting('SMTP_HOST', 'smtp_host', 'mail.prosper-minds.com');
    $mail->SMTPAuth  = true;
    $mail->Username  = getMailSetting('SMTP_USER', 'smtp_user', 'info@prosper-minds.com');
    $mail->Password  = getMailSetting('SMTP_PASS', 'smtp_pass', '');
    $mail->Timeout   = 30;
    $secure          = getMailSetting('SMTP_SECURE', 'smtp_secure', 'tls');
    if ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($secure === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
    }
    $mail->Port = (int) getMailSetting('SMTP_PORT', 'smtp_port', '587');

    $fromEmail = getMailSetting('SMTP_FROM_EMAIL', 'smtp_from_email', '')
        ?: getMailSetting('SMTP_USER', 'smtp_user', ADMIN_EMAIL);
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = ADMIN_EMAIL;
    }

    $mail->setFrom($fromEmail, COMPANY_NAME);
    // Always add reply-to pointing to admin inbox
    $mail->addReplyTo(ADMIN_EMAIL, COMPANY_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    // Deliverability headers – help avoid spam filters (Gmail, Outlook, etc.)
    $mail->XMailer = 'ProsperMinds Mailer 1.0';
    $mail->addCustomHeader('X-Mailer', 'ProsperMinds Mailer 1.0');
    $mail->addCustomHeader('X-Priority', '3');                // Normal priority
    $mail->addCustomHeader('Importance', 'Normal');
    // Note: no "Precedence: bulk" header — that flags transactional mail as
    // bulk/mass mail to Gmail/Outlook spam filters and hurts deliverability.

    return $mail;
}

/**
 * Create the failed_notifications table if it is missing.
 *
 * Follows the same "ensure schema on demand" convention already used by
 * ensureRegistrationInvoiceSchema() in includes/invoice.php, so a deploy does
 * not have to be coordinated with a manual phpMyAdmin step. The equivalent
 * up/down migration is also scripted in database/migrations/ for anyone who
 * would rather apply it explicitly.
 */
function ensureFailedNotificationSchema(PDO $pdo): void {
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS failed_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                registration_id INT DEFAULT NULL,
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                error_message TEXT DEFAULT NULL,
                resolved TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_failed_notifications_registration (registration_id),
                KEY idx_failed_notifications_resolved (resolved)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (PDOException $e) {
        error_log('Could not create failed_notifications table: ' . $e->getMessage());
    }
}

/**
 * Record an email that could not be delivered, so it is visible to an admin
 * and can be retried, instead of vanishing.
 *
 * This is called from failure paths and must never throw: it writes to the
 * PHP error log unconditionally, and additionally to the failed_notifications
 * table when the database is reachable.
 */
function recordFailedNotification(
    ?PDO $pdo,
    ?int $registrationId,
    string $recipient,
    string $subject,
    string $error
): void {
    error_log(sprintf(
        'UNSENT NOTIFICATION | registration_id=%s | to=%s | subject=%s | error=%s',
        $registrationId !== null ? (string) $registrationId : 'n/a',
        $recipient,
        $subject,
        $error
    ));

    logEmailDelivery('failed', $recipient, $subject, 'registration_id=' . ($registrationId ?? 'n/a') . ' ' . $error);

    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        ensureFailedNotificationSchema($pdo);
        $pdo->prepare(
            'INSERT INTO failed_notifications (registration_id, recipient, subject, error_message)
             VALUES (?, ?, ?, ?)'
        )->execute([$registrationId, $recipient, $subject, $error]);
    } catch (Throwable $e) {
        error_log('Could not record failed notification: ' . $e->getMessage());
    }
}

function logEmailDelivery(string $status, string $to, string $subject, string $details = ''): void {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $line = sprintf(
        "[%s] %s | to=%s | subject=%s | %s%s",
        date('Y-m-d H:i:s'),
        strtoupper($status),
        $to,
        $subject,
        $details,
        PHP_EOL
    );

    file_put_contents($logDir . '/email-delivery.log', $line, FILE_APPEND);
}

function sendEmail(string $to, string $subject, string $message, array $attachments = []): bool {
    $results = sendEmailMessages([
        [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'attachments' => $attachments,
        ],
    ]);

    return $results[0]['success'] ?? false;
}

function sendEmailMessages(array $messages): array {
    $results = [];
    $messageCount = count($messages);

    foreach ($messages as $index => $messageData) {
        $to = (string) ($messageData['to'] ?? '');
        $subject = (string) ($messageData['subject'] ?? '');
        $message = (string) ($messageData['message'] ?? '');
        $attachments = $messageData['attachments'] ?? [];

        // A fresh connection per message (instead of one shared SMTPKeepAlive
        // session for the whole batch) avoids mail servers/receivers treating
        // several different recipients sent back-to-back on one session as a
        // spam blast and silently dropping messages after the first.
        $mail = null;

        try {
            $mail = createConfiguredMailer();
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], PHP_EOL, $message)));
            $mail->addAddress($to);

            // Unique Message-ID per email helps Gmail not treat it as duplicate/spam
            $domain = parse_url('https://' . ($mail->Host ?? 'prosper-minds.com'), PHP_URL_HOST) ?? 'prosper-minds.com';
            $mail->MessageID = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . $domain . '>';

            foreach ($attachments as $attachment) {
                if (!empty($attachment['path']) && is_file($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
                }
            }

            $mail->send();
            logEmailDelivery('success', $to, $subject, 'attachments=' . count($attachments));
            $results[$index] = ['success' => true, 'to' => $to];
        } catch (Exception $e) {
            $errorInfo = $mail instanceof PHPMailer ? $mail->ErrorInfo : $e->getMessage();
            error_log("Email error to {$to}: {$errorInfo}");
            logEmailDelivery('failed', $to, $subject, $errorInfo);
            $results[$index] = ['success' => false, 'to' => $to, 'error' => $errorInfo];
        } finally {
            if ($mail instanceof PHPMailer) {
                $mail->smtpClose();
            }
        }

        if ($index < $messageCount - 1) {
            usleep(750000);
        }
    }

    ksort($results);
    return array_values($results);
}

function sanitizeInput(string $data): string {
    return htmlspecialchars(stripslashes(trim($data)));
}

// ── Funnel analytics, loaded defensively ────────────────────────────────────
// includes/funnel.php is a secondary concern: registration funnel counters for
// the admin panel. It is loaded here, once, so every entry point gets the same
// helpers — but a plain require would make a missing or truncated funnel.php a
// fatal on every page of the site. That is not hypothetical: the August 2026
// outage was one truncated file inside vendor/, and funnel.php is the newest
// file in this deploy, so it is the likeliest one to arrive incomplete.
//
// So: check it exists, load it inside try/catch (a parse error in an included
// file raises ParseError, an Error, which catch (Exception) would miss), and if
// anything at all goes wrong, define no-op stand-ins with the same signatures.
// Call sites then need no guard, and a broken analytics layer costs the site
// nothing but its analytics.
if (is_file(__DIR__ . '/funnel.php')) {
    try {
        require_once __DIR__ . '/funnel.php';
    } catch (Throwable $funnelLoadError) {
        error_log('Funnel analytics unavailable: ' . $funnelLoadError->getMessage());
    }
}

if (!function_exists('funnelTrackEvent')) {
    function ensureFunnelEventsSchema(PDO $pdo): void {}
    function funnelTrackEvent(PDO $pdo, string $eventType, array $context = []): void {}
    function funnelSessionId(): string { return ''; }
    function funnelSessionIdIfSet(): ?string { return null; }
    function funnelSanitiseReferrer(?string $referrer): ?string { return null; }
    function funnelUtmFromQuery(array $query): array { return []; }
    function funnelStageLoggedToday(PDO $pdo, string $sessionId, string $eventType, ?int $eventId): bool { return true; }
}
