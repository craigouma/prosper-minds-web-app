<?php

/**
 * Brevo transactional email API.
 *
 * The newsletter goes out through this and nothing else does. Registration
 * confirmations, invoices and the unfinished-registration reminder keep using
 * the SMTP mailer in config.php, so a problem here cannot touch the flow that
 * takes people's money.
 *
 * WHY NOT THE CAMPAIGNS API. Brevo also has /v3/emailCampaigns, which sends to
 * contact lists held inside Brevo. Using it would mean copying every subscriber
 * into a third party. The transactional endpoint takes the recipient inline, so
 * the list stays in newsletter_subscribers where it was collected.
 */

const PM_BREVO_ENDPOINT   = 'https://api.brevo.com/v3/smtp/email';
const PM_BREVO_TIMEOUT    = 20;
const PM_BREVO_MAX_ATTACH = 10485760;

function pmBrevoApiKey(): string
{
    return trim((string) getMailSetting('BREVO_API_KEY', 'brevo_api_key', ''));
}

function pmBrevoConfigured(): bool
{
    return pmBrevoApiKey() !== '';
}

/**
 * Send one email.
 *
 * @param array<int, array{name: string, content: string}> $attachments Base64 content.
 * @return array{ok: bool, id: string, error: string}
 */
function pmBrevoSend(
    string $toEmail,
    string $toName,
    string $subject,
    string $html,
    string $fromEmail,
    string $fromName,
    array $attachments = [],
    string $replyTo = ''
): array {
    $key = pmBrevoApiKey();

    if ($key === '') {
        return ['ok' => false, 'id' => '', 'error' => 'No Brevo API key is configured.'];
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'id' => '', 'error' => 'Not a valid address: ' . $toEmail];
    }

    $payload = [
        'sender'      => ['email' => $fromEmail, 'name' => mb_substr($fromName, 0, 70)],
        'to'          => [['email' => $toEmail] + ($toName === '' ? [] : ['name' => mb_substr($toName, 0, 70)])],
        'subject'     => $subject,
        'htmlContent' => $html,
    ];

    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $payload['replyTo'] = ['email' => $replyTo];
    }
    if ($attachments !== []) {
        $payload['attachment'] = $attachments;
    }

    return pmBrevoPost($payload, $key);
}

/**
 * The HTTP call, separated so the sending logic above can be read without it.
 *
 * Returns a failure array rather than throwing, for every outcome including a
 * missing curl extension, a timeout and a malformed response. A newsletter send
 * that dies mid-loop would leave the queue in a state nobody can reason about.
 *
 * @return array{ok: bool, id: string, error: string}
 */
function pmBrevoPost(array $payload, string $key): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'id' => '', 'error' => 'The curl extension is not available on this server.'];
    }

    $body = json_encode($payload);
    if ($body === false) {
        return ['ok' => false, 'id' => '', 'error' => 'The message could not be encoded as JSON.'];
    }

    try {
        $ch = curl_init(PM_BREVO_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => PM_BREVO_TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $key,
            ],
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        // No curl_close(): it has done nothing since PHP 8.0 and raises a
        // deprecation from 8.5. The handle is freed when it goes out of scope.
        // A stray notice here would print into an admin page or a cron mail.
    } catch (Throwable $e) {
        return ['ok' => false, 'id' => '', 'error' => 'The request failed: ' . $e->getMessage()];
    }

    if ($response === false) {
        return ['ok' => false, 'id' => '', 'error' => 'Could not reach Brevo: ' . $curlErr];
    }

    $decoded = json_decode((string) $response, true);

    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'id' => (string) ($decoded['messageId'] ?? ''), 'error' => ''];
    }

    // Brevo puts the useful part in `message`. Keep the status: 401 is a bad key
    // and 400 is usually an unverified sender, and those need different fixes.
    $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';

    return [
        'ok'    => false,
        'id'    => '',
        'error' => 'Brevo returned ' . $status . ($message === '' ? '' : ': ' . $message),
    ];
}

/**
 * Turn a media library path into an attachment Brevo will accept.
 *
 * Base64 rather than a URL: the uploads directory is reachable, but a private
 * file would not be, and sending the bytes means the recipient's copy does not
 * depend on the file still being there next month.
 *
 * @return array{name: string, content: string}|null
 */
function pmBrevoAttachment(string $absolutePath): ?array
{
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        return null;
    }
    if (filesize($absolutePath) > PM_BREVO_MAX_ATTACH) {
        return null;
    }

    $bytes = file_get_contents($absolutePath);
    if ($bytes === false) {
        return null;
    }

    return ['name' => basename($absolutePath), 'content' => base64_encode($bytes)];
}
