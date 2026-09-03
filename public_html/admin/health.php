<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/media.php';
requireAdminAuth();
requirePermission('health', 'view');

$pageTitle  = 'Site health';
$activePage = 'health';

/**
 * One check. Never throws: a health screen that dies on the thing it is
 * checking tells you nothing, which is the opposite of the point.
 */
function pmCheck(string $title, callable $probe, string $why): array
{
    try {
        $result = $probe();
    } catch (Throwable $e) {
        $result = ['state' => 'bad', 'detail' => 'This check failed: ' . $e->getMessage()];
    }

    return $result + ['title' => $title, 'why' => $why, 'state' => 'bad', 'detail' => ''];
}

$checks = [];

$checks[] = pmCheck('Database', static function () use ($pdo) {
    $n = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

    return ['state' => 'good', 'detail' => 'Reachable. ' . $n . ' events on file.'];
}, 'Everything else on this page depends on it.');

$checks[] = pmCheck('Outgoing mail', static function () use ($pdo, $siteSettings) {
    $host = trim((string) ($siteSettings['smtp_host'] ?? ''));
    $user = trim((string) ($siteSettings['smtp_user'] ?? ''));

    if ($host === '' || $user === '') {
        return ['state' => 'bad', 'detail' => 'No SMTP host or user is configured, so nothing can be sent.'];
    }

    $unsent = (int) $pdo->query('SELECT COUNT(*) FROM failed_notifications WHERE resolved = 0')->fetchColumn();
    $recent = (int) $pdo->query("SELECT COUNT(*) FROM failed_notifications
                                  WHERE resolved = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

    if ($recent > 0) {
        return ['state' => 'bad', 'detail' => $recent . ' message(s) failed to send in the last seven days, '
                                              . $unsent . ' unresolved in total. Somebody was not told something.'];
    }
    if ($unsent > 0) {
        return ['state' => 'warn', 'detail' => $unsent . ' older unsent notification(s) are still unresolved.'];
    }

    return ['state' => 'good', 'detail' => 'Configured as ' . $host . ', with nothing unsent.'];
}, 'August 2026: mail stopped and 36 registrations went unacknowledged for weeks.');

$checks[] = pmCheck('Registrations arriving', static function () use ($pdo) {
    $week  = (int) $pdo->query("SELECT COUNT(*) FROM event_registrations
                                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM event_registrations')->fetchColumn();

    if ($total === 0) {
        return ['state' => 'warn', 'detail' => 'No registration has ever been recorded.'];
    }
    if ($week === 0) {
        return ['state' => 'warn', 'detail' => 'None in the last seven days. That may be quiet trading, or the form may be broken.'];
    }

    return ['state' => 'good', 'detail' => $week . ' in the last seven days, ' . $total . ' in total.'];
}, 'A silent form looks exactly like a quiet week until somebody checks.');

$checks[] = pmCheck('Sponsorship enquiries', static function () use ($pdo) {
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables
                                  WHERE table_schema = DATABASE() AND table_name = 'sponsorship_enquiries'")->fetchColumn();

    return $exists === 0
        ? ['state' => 'bad', 'detail' => 'The table is missing, so enquiries are not being stored.']
        : ['state' => 'good', 'detail' => (int) $pdo->query('SELECT COUNT(*) FROM sponsorship_enquiries')->fetchColumn()
                                          . ' enquiry record(s) held.'];
}, 'These used to be emailed and never stored, so a mail failure lost them silently.');

$checks[] = pmCheck('Invoice files are private', static function () {
    $htaccess = __DIR__ . '/../assets/invoices/.htaccess';

    if (!is_file($htaccess)) {
        return ['state' => 'bad', 'detail' => 'assets/invoices/ has no .htaccess. The PDFs may be downloadable by anyone.'];
    }
    if (!str_contains((string) file_get_contents($htaccess), 'Require all denied')) {
        return ['state' => 'bad', 'detail' => 'The .htaccess exists but does not deny access.'];
    }

    return ['state' => 'good', 'detail' => 'Denied to the web. Delegates receive a signed link that expires.'];
}, 'Invoice numbers are sequential, so a readable directory can simply be walked.');

$checks[] = pmCheck('Uploads cannot run code', static function () {
    $htaccess = __DIR__ . '/../assets/uploads/.htaccess';

    if (!is_file($htaccess) || !str_contains((string) file_get_contents($htaccess), 'engine off')) {
        return ['state' => 'bad', 'detail' => 'assets/uploads/ does not switch the PHP engine off.'];
    }

    return ['state' => 'good', 'detail' => 'The PHP engine is off in the uploads directory.'];
}, 'A web root that will execute an uploaded file is one bug from remote code execution.');

$checks[] = pmCheck('Writable directories', static function () {
    $bad = [];
    foreach (['assets/uploads', 'assets/invoices', 'storage/logs'] as $dir) {
        $path = __DIR__ . '/../' . $dir;
        if (!is_dir($path) || !is_writable($path)) {
            $bad[] = $dir;
        }
    }

    return $bad
        ? ['state' => 'bad', 'detail' => 'Not writable: ' . implode(', ', $bad)]
        : ['state' => 'good', 'detail' => 'Uploads, invoices and logs are all writable.'];
}, 'A read-only directory turns an upload or an invoice into a silent failure.');

$checks[] = pmCheck('Content layer', static function () use ($pdo) {
    $rows = (int) $pdo->query('SELECT COUNT(*) FROM page_content')->fetchColumn();

    return $rows === 0
        ? ['state' => 'warn', 'detail' => 'No content rows. Pages are falling back to their built-in copy.']
        : ['state' => 'good', 'detail' => $rows . ' content rows across the site.'];
}, 'Every page has a fallback, so an empty table shows up here rather than as a blank page.');

$checks[] = pmCheck('Scheduled pages', static function () use ($pdo) {
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables
                                  WHERE table_schema = DATABASE() AND table_name = 'cms_pages'")->fetchColumn();
    if ($exists === 0) {
        return ['state' => 'good', 'detail' => 'No CMS pages yet.'];
    }

    $due = (int) $pdo->query("SELECT COUNT(*) FROM cms_pages
                               WHERE status = 'scheduled' AND publish_at <= NOW() AND trashed_at IS NULL")->fetchColumn();

    return ['state' => 'good', 'detail' => $due > 0
        ? $due . ' scheduled page(s) are now past their time and are being served.'
        : 'Nothing waiting.'];
}, 'Scheduling is evaluated when a page is read, so it cannot silently stall.');

$checks[] = pmCheck('Leftover setup scripts', static function () {
    $found = [];
    foreach (['setup_database.php', 'insert_events.php', 'test_email.php', '_phase1-preview.php'] as $file) {
        if (is_file(__DIR__ . '/../' . $file)) {
            $found[] = $file;
        }
    }

    return $found
        ? ['state' => 'bad', 'detail' => 'Still present and reachable: ' . implode(', ', $found)]
        : ['state' => 'good', 'detail' => 'None of the known setup or preview scripts are present.'];
}, 'Two of these accepted unauthenticated writes on any request.');

$checks[] = pmCheck('PHP', static function () {
    $state = version_compare(PHP_VERSION, '8.1', '>=') ? 'good' : 'warn';

    return ['state' => $state, 'detail' => 'Running PHP ' . PHP_VERSION
            . '. Largest upload accepted is ' . pmMediaHumanSize(pmMediaUploadLimitBytes()) . '.'];
}, 'An unsupported version stops receiving security fixes.');

$counts = ['good' => 0, 'warn' => 0, 'bad' => 0];
foreach ($checks as $check) {
    $counts[$check['state']] = ($counts[$check['state']] ?? 0) + 1;
}

require_once 'header.php';
?>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-info"><h3><?php echo $counts['bad']; ?></h3><p>Needs attention</p></div></div>
  <div class="stat-card"><div class="stat-info"><h3><?php echo $counts['warn']; ?></h3><p>Worth a look</p></div></div>
  <div class="stat-card"><div class="stat-info"><h3><?php echo $counts['good']; ?></h3><p>Fine</p></div></div>
  <div class="stat-card"><div class="stat-info"><h3><?php echo count($checks); ?></h3><p>Checks run</p></div></div>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2 class="card-title">Site health</h2>
      <p class="card-subtitle">Checked now, on this request. Nothing here is cached.</p>
    </div>
  </div>

  <div class="table-responsive">
    <table>
      <thead><tr><th>Check</th><th>State</th><th>What it found</th><th>Why it is checked</th></tr></thead>
      <tbody>
<?php
usort($checks, static function (array $a, array $b): int {
    $rank = ['bad' => 0, 'warn' => 1, 'good' => 2];

    return ($rank[$a['state']] ?? 3) <=> ($rank[$b['state']] ?? 3);
});
foreach ($checks as $check):
    $badge = $check['state'] === 'good' ? 'badge-green' : ($check['state'] === 'warn' ? 'badge-orange' : 'badge-red');
    $word  = $check['state'] === 'good' ? 'Fine' : ($check['state'] === 'warn' ? 'Look' : 'Attention');
?>
        <tr>
          <td><?php echo htmlspecialchars($check['title']); ?></td>
          <td><span class="badge <?php echo $badge; ?>"><?php echo $word; ?></span></td>
          <td><?php echo htmlspecialchars($check['detail']); ?></td>
          <td class="text-muted"><?php echo htmlspecialchars($check['why']); ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'footer.php'; ?>
