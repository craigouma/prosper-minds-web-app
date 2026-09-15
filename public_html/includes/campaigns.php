<?php

/**
 * Newsletters: composing them, queueing them, and sending them one at a time.
 *
 * WHY A QUEUE FOR TWO SUBSCRIBERS. Sending inside the admin request works right
 * up until the list is big enough that it does not, and the failure mode is a
 * request that times out halfway with no record of who was reached. The queue
 * is one row per recipient with its own status, so a send is resumable, a
 * failure names the person it failed for, and nobody is ever sent the same
 * newsletter twice.
 *
 * Drained by tools/send-newsletter-queue.php on the cron that already runs.
 */

require_once __DIR__ . '/brevo.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/mail-template-newsletter.php';

const PM_CAMPAIGN_BATCH = 40;

function ensureCampaignSchema(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }
    $checked = true;

    try {
        if ($pdo->inTransaction()) {
            error_log('newsletter_campaigns: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS newsletter_campaigns (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                subject     VARCHAR(300) NOT NULL,
                body_html   LONGTEXT     NOT NULL,
                attachments TEXT         NULL,
                status      VARCHAR(20)  NOT NULL DEFAULT "draft",
                created_by  VARCHAR(100) NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                queued_at   TIMESTAMP    NULL DEFAULT NULL,
                finished_at TIMESTAMP    NULL DEFAULT NULL,
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS newsletter_campaign_recipients (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT          NOT NULL,
                email       VARCHAR(190) NOT NULL,
                status      VARCHAR(20)  NOT NULL DEFAULT "pending",
                error       VARCHAR(500) NULL,
                sent_at     TIMESTAMP    NULL DEFAULT NULL,
                UNIQUE KEY uq_campaign_email (campaign_id, email),
                KEY idx_drain (status, campaign_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        pmCampaignEnsureUnsubscribeToken($pdo);
    } catch (Throwable $e) {
        error_log('newsletter_campaigns: schema setup failed: ' . $e->getMessage());
    }
}

/**
 * Every subscriber needs a token so their unsubscribe link identifies them
 * without putting their address in a URL that ends up in server logs.
 */
function pmCampaignEnsureUnsubscribeToken(PDO $pdo): void
{
    $has = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name   = 'newsletter_subscribers'
            AND column_name  = 'unsubscribe_token'"
    )->fetchColumn();

    if ($has === 0) {
        $pdo->exec('ALTER TABLE newsletter_subscribers
                      ADD COLUMN unsubscribe_token CHAR(32) NULL DEFAULT NULL,
                      ADD UNIQUE KEY uq_unsubscribe_token (unsubscribe_token)');
    }

    $rows = $pdo->query('SELECT id FROM newsletter_subscribers WHERE unsubscribe_token IS NULL')
                ->fetchAll(PDO::FETCH_COLUMN);

    $set = $pdo->prepare('UPDATE newsletter_subscribers SET unsubscribe_token = :t WHERE id = :id');
    foreach ($rows ?: [] as $id) {
        $set->execute([':t' => bin2hex(random_bytes(16)), ':id' => (int) $id]);
    }
}

/** @return array<int, array<string, mixed>> */
function pmCampaignList(PDO $pdo, int $limit = 50): array
{
    try {
        ensureCampaignSchema($pdo);

        $rows = $pdo->query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM newsletter_campaign_recipients r
                      WHERE r.campaign_id = c.id) AS total,
                    (SELECT COUNT(*) FROM newsletter_campaign_recipients r
                      WHERE r.campaign_id = c.id AND r.status = "sent") AS sent,
                    (SELECT COUNT(*) FROM newsletter_campaign_recipients r
                      WHERE r.campaign_id = c.id AND r.status = "failed") AS failed
               FROM newsletter_campaigns c
              ORDER BY c.id DESC
              LIMIT ' . max(1, $limit)
        )->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    } catch (Throwable $e) {
        error_log('newsletter_campaigns: list failed: ' . $e->getMessage());

        return [];
    }
}

function pmCampaignById(PDO $pdo, int $id): ?array
{
    try {
        ensureCampaignSchema($pdo);
        $s = $pdo->prepare('SELECT * FROM newsletter_campaigns WHERE id = :id');
        $s->execute([':id' => $id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        error_log('newsletter_campaigns: fetch failed: ' . $e->getMessage());

        return null;
    }
}

/** @param array<int, string> $attachments Media library filenames. */
function pmCampaignSave(PDO $pdo, ?int $id, string $subject, string $html, array $attachments, string $author): int
{
    ensureCampaignSchema($pdo);

    $json = json_encode(array_values(array_filter($attachments))) ?: '[]';

    if ($id === null || $id <= 0) {
        $s = $pdo->prepare('INSERT INTO newsletter_campaigns (subject, body_html, attachments, created_by)
                            VALUES (:s, :b, :a, :u)');
        $s->execute([':s' => $subject, ':b' => $html, ':a' => $json, ':u' => $author]);

        return (int) $pdo->lastInsertId();
    }

    // A campaign that has already been queued is a record of what was sent.
    $s = $pdo->prepare('UPDATE newsletter_campaigns
                           SET subject = :s, body_html = :b, attachments = :a
                         WHERE id = :id AND status = "draft"');
    $s->execute([':s' => $subject, ':b' => $html, ':a' => $json, ':id' => $id]);

    return $id;
}

/**
 * The media library filenames attached to a campaign.
 *
 * Filenames, never paths. pmMediaPath() resolves them inside the uploads
 * directory, so a crafted value cannot reach a file elsewhere on the server.
 *
 * @return array<int, string>
 */
function pmCampaignAttachments(array $campaign): array
{
    $names = json_decode((string) ($campaign['attachments'] ?? '[]'), true);

    if (!is_array($names)) {
        return [];
    }

    return array_values(array_filter($names, static function ($name): bool {
        return is_string($name) && $name !== '' && basename($name) === $name;
    }));
}

/** @return array<int, array{name: string, content: string}> */
function pmCampaignAttachmentPayload(array $campaign): array
{
    $out = [];
    foreach (pmCampaignAttachments($campaign) as $filename) {
        $one = pmBrevoAttachment(pmMediaPath($filename));
        if ($one !== null) {
            $out[] = $one;
        }
    }

    return $out;
}

/** Addresses that should receive the next newsletter. @return array<int, string> */
function pmCampaignAudience(PDO $pdo): array
{
    try {
        $rows = $pdo->query('SELECT email FROM newsletter_subscribers
                              WHERE unsubscribed_at IS NULL
                              ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

        return $rows ?: [];
    } catch (Throwable $e) {
        error_log('newsletter_campaigns: audience lookup failed: ' . $e->getMessage());

        return [];
    }
}

/**
 * Freeze the audience and mark the campaign ready for the cron.
 *
 * The recipient rows are written now rather than resolved at send time, so
 * somebody subscribing halfway through a send does not receive a newsletter
 * that predates them, and the totals on screen stop moving.
 *
 * @return array{ok: bool, queued: int, error: string}
 */
function pmCampaignQueue(PDO $pdo, int $id): array
{
    ensureCampaignSchema($pdo);

    $campaign = pmCampaignById($pdo, $id);
    if ($campaign === null) {
        return ['ok' => false, 'queued' => 0, 'error' => 'That newsletter no longer exists.'];
    }
    if (($campaign['status'] ?? '') !== 'draft') {
        return ['ok' => false, 'queued' => 0, 'error' => 'That newsletter has already been sent.'];
    }
    if (trim((string) $campaign['subject']) === '') {
        return ['ok' => false, 'queued' => 0, 'error' => 'It needs a subject before it can go out.'];
    }
    // The Send button is already disabled without a key, but disabled is only
    // markup. Refusing here as well is what stops a queue building up behind a
    // send that can never happen.
    if (!pmBrevoConfigured()) {
        return ['ok' => false, 'queued' => 0,
                'error' => 'No Brevo API key is set, so nothing can be sent. Add it under Settings.'];
    }

    $audience = pmCampaignAudience($pdo);
    if ($audience === []) {
        return ['ok' => false, 'queued' => 0, 'error' => 'There is nobody subscribed to send it to.'];
    }

    $insert = $pdo->prepare('INSERT IGNORE INTO newsletter_campaign_recipients (campaign_id, email)
                             VALUES (:c, :e)');
    foreach ($audience as $email) {
        $insert->execute([':c' => $id, ':e' => $email]);
    }

    $pdo->prepare('UPDATE newsletter_campaigns SET status = "sending", queued_at = NOW() WHERE id = :id')
        ->execute([':id' => $id]);

    return ['ok' => true, 'queued' => count($audience), 'error' => ''];
}

/**
 * Send up to $limit queued messages.
 *
 * A recipient is marked before the call, not after, for the reason the
 * registration reminder does the same: an API that accepts a message and then
 * reports a failure must not cause a second copy tomorrow.
 *
 * @return array{sent: int, failed: int, remaining: int}
 */
function pmCampaignDrain(PDO $pdo, int $limit = PM_CAMPAIGN_BATCH): array
{
    ensureCampaignSchema($pdo);

    $sent = 0;
    $failed = 0;

    $due = $pdo->prepare(
        'SELECT r.id, r.campaign_id, r.email, c.subject, c.body_html, c.attachments
           FROM newsletter_campaign_recipients r
           JOIN newsletter_campaigns c ON c.id = r.campaign_id
          WHERE r.status = "pending" AND c.status = "sending"
          ORDER BY r.id
          LIMIT ' . max(1, $limit)
    );
    $due->execute();
    $rows = $due->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $fromEmail = trim((string) getMailSetting('BREVO_FROM_EMAIL', 'brevo_from_email', ''))
        ?: trim((string) getSetting('contact_email', 'info@prosper-minds.com'));
    $fromName = trim((string) getSetting('company_name', 'Prosperminds'));

    foreach ($rows as $row) {
        $pdo->prepare('UPDATE newsletter_campaign_recipients SET status = "sending" WHERE id = :id')
            ->execute([':id' => (int) $row['id']]);

        $attachments = pmCampaignAttachmentPayload($row);

        $result = pmBrevoSend(
            (string) $row['email'],
            '',
            (string) $row['subject'],
            pmCampaignRenderEmail($pdo, (string) $row['subject'], (string) $row['body_html'], (string) $row['email']),
            $fromEmail,
            $fromName,
            $attachments,
            $fromEmail
        );

        if ($result['ok']) {
            $pdo->prepare('UPDATE newsletter_campaign_recipients
                              SET status = "sent", sent_at = NOW(), error = NULL WHERE id = :id')
                ->execute([':id' => (int) $row['id']]);
            $sent++;
        } else {
            $pdo->prepare('UPDATE newsletter_campaign_recipients
                              SET status = "failed", error = :e WHERE id = :id')
                ->execute([':e' => mb_substr($result['error'], 0, 500), ':id' => (int) $row['id']]);
            $failed++;
        }
    }

    pmCampaignCloseFinished($pdo);

    $remaining = (int) $pdo->query(
        'SELECT COUNT(*) FROM newsletter_campaign_recipients r
           JOIN newsletter_campaigns c ON c.id = r.campaign_id
          WHERE r.status = "pending" AND c.status = "sending"'
    )->fetchColumn();

    return ['sent' => $sent, 'failed' => $failed, 'remaining' => $remaining];
}

/**
 * Close any campaign with nothing left to try.
 *
 * A campaign where every single message failed is marked "failed", not "sent".
 * Calling that one sent would put a green badge on a newsletter that reached
 * nobody, which is the one thing the screen must not do.
 */
function pmCampaignCloseFinished(PDO $pdo): void
{
    $pdo->exec(
        'UPDATE newsletter_campaigns c
            SET c.finished_at = NOW(),
                c.status = CASE
                    WHEN EXISTS (SELECT 1 FROM newsletter_campaign_recipients r
                                  WHERE r.campaign_id = c.id AND r.status = "sent")
                    THEN "sent" ELSE "failed" END
          WHERE c.status = "sending"
            AND NOT EXISTS (SELECT 1 FROM newsletter_campaign_recipients r
                             WHERE r.campaign_id = c.id AND r.status IN ("pending", "sending"))'
    );
}

/**
 * Wrap what somebody typed in the branded shell, with their own opt-out link.
 *
 * The unsubscribe link is built here rather than left to whoever writes the
 * newsletter to remember. A marketing email without a working opt-out is the
 * one thing here that is actually unlawful.
 */
function pmCampaignRenderEmail(PDO $pdo, string $subject, string $bodyHtml, string $email): string
{
    return getNewsletterEmailTemplate($subject, $bodyHtml, pmCampaignUnsubscribeUrl($pdo, $email));
}

function pmCampaignUnsubscribeUrl(PDO $pdo, string $email): string
{
    $token = '';
    try {
        $s = $pdo->prepare('SELECT unsubscribe_token FROM newsletter_subscribers WHERE email = :e');
        $s->execute([':e' => $email]);
        $token = (string) $s->fetchColumn();
    } catch (Throwable $e) {
        error_log('newsletter_campaigns: could not read unsubscribe token: ' . $e->getMessage());
    }

    $origin = defined('PM_SITE_ORIGIN') ? PM_SITE_ORIGIN : 'https://prosper-minds.com';

    return $origin . '/newsletter-unsubscribe.php?t=' . rawurlencode($token);
}

/** @return array{ok: bool, error: string} */
function pmCampaignUnsubscribe(PDO $pdo, ?string $token): array
{
    $token = trim((string) $token);

    if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
        return ['ok' => false, 'error' => 'That link is not valid.'];
    }

    try {
        ensureCampaignSchema($pdo);
        $s = $pdo->prepare('UPDATE newsletter_subscribers SET unsubscribed_at = NOW()
                             WHERE unsubscribe_token = :t AND unsubscribed_at IS NULL');
        $s->execute([':t' => $token]);

        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        error_log('newsletter_campaigns: unsubscribe failed: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'That could not be recorded.'];
    }
}
