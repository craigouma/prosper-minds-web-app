<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/contact.php';
require_once '../includes/sponsorship.php';
require_once '../includes/newsletter.php';
requireAdminAuth();
requirePermission('submissions', 'view');

$pageTitle  = 'Submissions';
$activePage = 'submissions';

ensureContactMessageSchema($pdo);
ensureSponsorshipEnquirySchema($pdo);
ensureNewsletterSubscriberSchema($pdo);

$TABS = [
    'enquiries'   => ['label' => 'Enquiries',   'table' => 'contact_messages'],
    'sponsorship' => ['label' => 'Sponsorship', 'table' => 'sponsorship_enquiries'],
    'newsletter'  => ['label' => 'Newsletter',  'table' => 'newsletter_subscribers'],
];

$tab    = isset($_GET['tab']) && isset($TABS[$_GET['tab']]) ? $_GET['tab'] : 'enquiries';
$search = trim((string) ($_GET['q'] ?? ''));
$only   = (string) ($_GET['only'] ?? '');
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $notice = 'That form had expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $id     = (int) ($_POST['id'] ?? 0);

        if ($action === 'handle' && $id > 0) {
            requirePermission('submissions', 'handle');
            $target = $_POST['scope'] === 'sponsorship' ? 'sponsorship_enquiries' : 'contact_messages';
            $state  = $_POST['state'] === 'new' ? 'new' : 'handled';
            $pdo->prepare("UPDATE {$target} SET status = ? WHERE id = ?")->execute([$state, $id]);
            pmAudit($pdo, 'submission_status',
                sprintf('Marked %s #%d as %s', $target === 'contact_messages' ? 'enquiry' : 'sponsorship enquiry', $id, $state),
                $target, $id);
            $notice = $state === 'handled' ? 'Marked as handled.' : 'Reopened.';
        }

        if ($action === 'unsubscribe' && $id > 0) {
            requirePermission('submissions', 'handle');
            $pdo->prepare('UPDATE newsletter_subscribers SET unsubscribed_at = NOW() WHERE id = ? AND unsubscribed_at IS NULL')
                ->execute([$id]);
            pmAudit($pdo, 'newsletter_unsubscribe', sprintf('Unsubscribed newsletter address #%d', $id),
                'newsletter_subscribers', $id);
            $notice = 'Address unsubscribed. The row is kept so a re-import cannot resubscribe them.';
        }
    }
}

if (isset($_GET['export'])) {
    requirePermission('submissions', 'export');
    $which = isset($TABS[$_GET['export']]) ? $_GET['export'] : 'enquiries';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $which . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    if ($which === 'enquiries') {
        fputcsv($out, ['ID', 'Received', 'Name', 'Organisation', 'Email', 'Phone', 'Source', 'Status', 'Notified', 'Message']);
        foreach ($pdo->query('SELECT * FROM contact_messages ORDER BY id DESC') as $r) {
            fputcsv($out, [$r['id'], $r['created_at'], $r['name'], $r['organisation'], $r['email'],
                           $r['phone'], $r['source'], $r['status'], $r['notified'] ? 'yes' : 'no', $r['message']]);
        }
    } elseif ($which === 'sponsorship') {
        fputcsv($out, ['ID', 'Received', 'First Name', 'Last Name', 'Organisation', 'Email', 'Phone',
                       'Country', 'Tier', 'Events', 'Status', 'Notified', 'Message']);
        foreach ($pdo->query('SELECT * FROM sponsorship_enquiries ORDER BY id DESC') as $r) {
            fputcsv($out, [$r['id'], $r['created_at'], $r['first_name'], $r['last_name'], $r['organisation'],
                           $r['email'], $r['phone'], $r['country'], $r['tier'],
                           implode('; ', pmSponsorshipEvents($r['events'])),
                           $r['status'], $r['notified'] ? 'yes' : 'no', $r['message']]);
        }
    } else {
        // Shaped for a mailing provider import rather than as a generic dump:
        // address first, and opted-out rows excluded rather than flagged.
        fputcsv($out, ['EMAIL', 'SUBSCRIBED_AT', 'SOURCE']);
        foreach ($pdo->query('SELECT * FROM newsletter_subscribers WHERE unsubscribed_at IS NULL ORDER BY id DESC') as $r) {
            fputcsv($out, [$r['email'], $r['created_at'], $r['source']]);
        }
    }

    pmAudit($pdo, 'submission_export', 'Exported the ' . $which . ' list to CSV', $TABS[$which]['table']);
    fclose($out);
    exit;
}

$counts = ['enquiries' => 0, 'sponsorship' => 0, 'newsletter' => 0];
try {
    $counts['enquiries']   = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
    $counts['sponsorship'] = (int) $pdo->query("SELECT COUNT(*) FROM sponsorship_enquiries WHERE status = 'new'")->fetchColumn();
    $counts['newsletter']  = (int) $pdo->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE unsubscribed_at IS NULL')->fetchColumn();
} catch (Throwable $e) {
    error_log('submissions: could not count: ' . $e->getMessage());
}

$where = [];
$args  = [];

if ($tab === 'newsletter') {
    if ($only === 'active')       { $where[] = 'unsubscribed_at IS NULL'; }
    if ($only === 'unsubscribed') { $where[] = 'unsubscribed_at IS NOT NULL'; }
    if ($search !== '') { $where[] = 'email LIKE ?'; $args[] = '%' . $search . '%'; }
} else {
    if ($only === 'new')     { $where[] = "status = 'new'"; }
    if ($only === 'handled') { $where[] = "status = 'handled'"; }
    if ($search !== '') {
        if ($tab === 'sponsorship') {
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR organisation LIKE ? OR email LIKE ?)';
            array_push($args, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
        } else {
            $where[] = '(name LIKE ? OR organisation LIKE ? OR email LIKE ? OR message LIKE ?)';
            array_push($args, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
        }
    }
}

$sql = 'SELECT * FROM ' . $TABS[$tab]['table']
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY id DESC LIMIT 300';

$rows = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('submissions: query failed: ' . $e->getMessage());
    $notice = 'That list could not be read. The error has been logged.';
}

$csrfToken = generateCsrfToken();
$canHandle = hasPermission('submissions', 'handle');
$canExport = hasPermission('submissions', 'export');

function pmSubUrl(array $overrides = []): string
{
    $base = ['tab' => $_GET['tab'] ?? 'enquiries', 'q' => $_GET['q'] ?? '', 'only' => $_GET['only'] ?? ''];
    $q    = array_filter(array_merge($base, $overrides), static function ($v) { return $v !== '' && $v !== null; });

    return 'submissions.php' . ($q ? '?' . http_build_query($q) : '');
}

require_once 'header.php';
?>

<?php if ($notice !== ''): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div>
<?php endif; ?>

<div class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="card-title">Submissions</h2>
            <p class="card-subtitle">Everything the public forms have sent, in one place</p>
        </div>
<?php if ($canExport): ?>
        <a class="btn btn-outline btn-sm" style="margin-left:auto"
           href="<?php echo htmlspecialchars(pmSubUrl(['export' => $tab])); ?>">Export this list</a>
<?php endif; ?>
    </div>

    <div style="display:flex;gap:0;border-bottom:1px solid var(--pma-border);padding:0 8px;flex-wrap:wrap">
<?php foreach ($TABS as $key => $meta): ?>
        <a class="tab-btn <?php echo $key === $tab ? 'active' : ''; ?>"
           style="text-decoration:none"
           href="<?php echo htmlspecialchars('submissions.php?tab=' . $key); ?>">
            <?php echo htmlspecialchars($meta['label']); ?>
            <span style="font-family:var(--pma-mono);margin-left:6px"><?php echo (int) $counts[$key]; ?></span>
        </a>
<?php endforeach; ?>
    </div>

    <form method="GET" action="submissions.php"
          style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;padding:14px 16px;border-bottom:1px solid var(--pma-border)">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <div class="form-group" style="margin:0;flex:1 1 240px">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" class="form-control"
                   value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="<?php echo $tab === 'newsletter' ? 'Email address' : 'Name, organisation, email or text'; ?>">
        </div>
        <div class="form-group" style="margin:0;flex:0 0 190px">
            <label for="only">Show</label>
            <select id="only" name="only" class="form-control">
<?php
$options = $tab === 'newsletter'
    ? ['' => 'Everyone', 'active' => 'Subscribed only', 'unsubscribed' => 'Unsubscribed only']
    : ['' => 'All', 'new' => 'Not handled', 'handled' => 'Handled'];
foreach ($options as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value); ?>"
                    <?php echo $only === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
<?php if ($search !== '' || $only !== ''): ?>
        <a class="btn btn-outline btn-sm" href="<?php echo htmlspecialchars('submissions.php?tab=' . $tab); ?>">Clear</a>
<?php endif; ?>
    </form>

<?php if (!$rows): ?>
    <div class="empty-state">
        <span class="empty-state-title">Nothing here yet</span>
<?php if ($search !== '' || $only !== ''): ?>
        No <?php echo htmlspecialchars(strtolower($TABS[$tab]['label'])); ?> match that filter.
<?php elseif ($tab === 'newsletter'): ?>
        Nobody has subscribed through the footer form yet.
<?php elseif ($tab === 'sponsorship'): ?>
        No sponsorship enquiries have been recorded. Enquiries submitted before September 2026 were
        never stored, so they exist only in the programme office mailbox.
<?php else: ?>
        No enquiries have come through the contact form yet.
<?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table>
<?php if ($tab === 'newsletter'): ?>
            <thead>
                <tr>
                    <th>Email</th><th>Source</th><th>Subscribed</th><th>State</th>
<?php if ($canHandle): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($rows as $r): $out = $r['unsubscribed_at'] !== null; ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['email']); ?></td>
                    <td class="text-muted"><?php echo htmlspecialchars((string) $r['source']); ?></td>
                    <td class="text-muted"><?php echo htmlspecialchars(date('j M Y', strtotime($r['created_at']))); ?></td>
                    <td>
                        <span class="badge <?php echo $out ? 'badge-gray' : 'badge-green'; ?>">
                            <?php echo $out ? 'Unsubscribed' : 'Subscribed'; ?>
                        </span>
                    </td>
<?php if ($canHandle): ?>
                    <td class="num">
<?php if (!$out): ?>
                        <form method="POST" action="<?php echo htmlspecialchars(pmSubUrl()); ?>" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="unsubscribe">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm">Unsubscribe</button>
                        </form>
<?php endif; ?>
                    </td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
<?php elseif ($tab === 'sponsorship'): ?>
            <thead>
                <tr>
                    <th>Received</th><th>Organisation</th><th>Contact</th><th>Tier</th>
                    <th>Events</th><th>Status</th><th>Emailed</th>
<?php if ($canHandle): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($rows as $r): $handled = $r['status'] === 'handled'; ?>
                <tr>
                    <td class="text-muted"><?php echo htmlspecialchars(date('j M Y', strtotime($r['created_at']))); ?></td>
                    <td><?php echo htmlspecialchars($r['organisation']); ?></td>
                    <td>
                        <?php echo htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name'])); ?><br>
                        <a href="mailto:<?php echo htmlspecialchars($r['email']); ?>"><?php echo htmlspecialchars($r['email']); ?></a>
                    </td>
                    <td><?php echo htmlspecialchars((string) $r['tier']); ?></td>
                    <td class="text-muted"><?php echo htmlspecialchars(implode(', ', pmSponsorshipEvents($r['events']))); ?></td>
                    <td>
                        <span class="badge <?php echo $handled ? 'badge-green' : 'badge-orange'; ?>">
                            <?php echo $handled ? 'Handled' : 'Not handled'; ?>
                        </span>
                    </td>
                    <td>
<?php if (!$r['notified']): ?>
                        <span class="badge badge-red">Not sent</span>
<?php else: ?>
                        <span class="badge badge-gray">Sent</span>
<?php endif; ?>
                    </td>
<?php if ($canHandle): ?>
                    <td class="num">
                        <form method="POST" action="<?php echo htmlspecialchars(pmSubUrl()); ?>" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="handle">
                            <input type="hidden" name="scope" value="sponsorship">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <input type="hidden" name="state" value="<?php echo $handled ? 'new' : 'handled'; ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?php echo $handled ? 'Reopen' : 'Mark handled'; ?></button>
                        </form>
                    </td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
<?php else: ?>
            <thead>
                <tr>
                    <th>Received</th><th>From</th><th>Message</th><th>Source</th><th>Status</th><th>Emailed</th>
<?php if ($canHandle): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($rows as $r): $handled = $r['status'] === 'handled'; ?>
                <tr>
                    <td class="text-muted"><?php echo htmlspecialchars(date('j M Y', strtotime($r['created_at']))); ?></td>
                    <td>
                        <?php echo htmlspecialchars($r['name']); ?><br>
                        <a href="mailto:<?php echo htmlspecialchars($r['email']); ?>"><?php echo htmlspecialchars($r['email']); ?></a>
<?php if (!empty($r['organisation'])): ?>
                        <br><span class="text-muted"><?php echo htmlspecialchars($r['organisation']); ?></span>
<?php endif; ?>
                    </td>
                    <td style="max-width:420px"><?php echo nl2br(htmlspecialchars($r['message'])); ?></td>
                    <td class="text-muted"><?php echo htmlspecialchars((string) $r['source']); ?></td>
                    <td>
                        <span class="badge <?php echo $handled ? 'badge-green' : 'badge-orange'; ?>">
                            <?php echo $handled ? 'Handled' : 'Not handled'; ?>
                        </span>
                    </td>
                    <td>
<?php if (!$r['notified']): ?>
                        <span class="badge badge-red">Not sent</span>
<?php else: ?>
                        <span class="badge badge-gray">Sent</span>
<?php endif; ?>
                    </td>
<?php if ($canHandle): ?>
                    <td class="num">
                        <form method="POST" action="<?php echo htmlspecialchars(pmSubUrl()); ?>" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="handle">
                            <input type="hidden" name="scope" value="enquiries">
                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                            <input type="hidden" name="state" value="<?php echo $handled ? 'new' : 'handled'; ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?php echo $handled ? 'Reopen' : 'Mark handled'; ?></button>
                        </form>
                    </td>
<?php endif; ?>
                </tr>
<?php endforeach; ?>
            </tbody>
<?php endif; ?>
        </table>
    </div>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
