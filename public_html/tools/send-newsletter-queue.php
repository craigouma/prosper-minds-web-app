<?php
declare(strict_types=1);

/**
 * Newsletter queue drain. Runs on cron beside the reminder sweep.
 *
 * A newsletter is queued by the admin panel as one row per recipient. This
 * sends a batch of them through Brevo and stops. Whatever is left waits for the
 * next run, which is what keeps a large list from either timing out a request
 * or emptying the day's sending allowance in one go.
 *
 * SAFETY MODEL
 *
 *   * Dry run is the DEFAULT. Nothing is sent unless --send is passed.
 *   * A recipient is marked before the API call, so a call that succeeds and
 *     then reports a failure cannot produce a second copy on the next run.
 *   * Only campaigns an admin explicitly pressed Send on are touched. A draft
 *     has no recipient rows at all.
 *   * A campaign closes itself once no recipient is still pending.
 *   * CLI only. Refuses to run over HTTP.
 *
 * USAGE
 *
 *   php tools/send-newsletter-queue.php            Dry run: what is waiting.
 *   php tools/send-newsletter-queue.php --send     The real run. Cron calls this.
 *
 * OPTIONS
 *
 *   --send          Actually send.
 *   --limit=40      Most messages in one run. Keep it under the daily allowance.
 *   --quiet         Only print if something happened. For cron.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

require_once $root . '/includes/config.php';
require_once $root . '/includes/campaigns.php';

$options = getopt('', ['send', 'limit::', 'quiet']);

$send  = array_key_exists('send', $options);
$quiet = array_key_exists('quiet', $options);
$limit = max(1, (int) ($options['limit'] ?? PM_CAMPAIGN_BATCH));

$out = static function (string $line) use ($quiet): void {
    if (!$quiet) {
        echo $line, PHP_EOL;
    }
};

ensureCampaignSchema($pdo);

// Before the early exit below. A campaign whose last messages all failed has
// nothing pending, so without this it would sit on "sending" for ever.
pmCampaignCloseFinished($pdo);

$waiting = (int) $pdo->query(
    'SELECT COUNT(*) FROM newsletter_campaign_recipients r
       JOIN newsletter_campaigns c ON c.id = r.campaign_id
      WHERE r.status = "pending" AND c.status = "sending"'
)->fetchColumn();

if ($waiting === 0) {
    $out('Nothing waiting to send.');
    exit;
}

if (!pmBrevoConfigured()) {
    // Loud, because the queue will otherwise sit here filling up silently.
    fwrite(STDERR, 'Newsletter queue: ' . $waiting . ' message(s) waiting but no Brevo API key is set.' . PHP_EOL);
    exit(1);
}

if (!$send) {
    $out($waiting . ' message(s) waiting. DRY RUN, nothing sent. Pass --send to send.');

    foreach ($pdo->query(
        'SELECT c.id, c.subject, COUNT(r.id) AS pending
           FROM newsletter_campaigns c
           JOIN newsletter_campaign_recipients r ON r.campaign_id = c.id
          WHERE c.status = "sending" AND r.status = "pending"
          GROUP BY c.id, c.subject'
    ) as $row) {
        $out(sprintf('  #%d %-52s %d waiting', $row['id'], $row['subject'], $row['pending']));
    }

    exit;
}

$result = pmCampaignDrain($pdo, $limit);

$out(sprintf('Sent %d, failed %d, %d still waiting.',
    $result['sent'], $result['failed'], $result['remaining']));

if ($result['failed'] > 0) {
    // Cron mails stderr, so a failure reaches somebody rather than only the log.
    fwrite(STDERR, sprintf('Newsletter queue: %d message(s) failed. See the Newsletter screen.' . PHP_EOL,
        $result['failed']));
}
