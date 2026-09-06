<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/redirects.php';
requireAdminAuth();
requirePermission('redirects', 'view');

$pageTitle  = 'Redirects and 404s';
$activePage = 'redirects';

ensureRedirectSchema($pdo);

$notice = '';
$error  = '';
$tab    = ($_GET['tab'] ?? '') === 'notfound' ? 'notfound' : 'redirects';
$canEdit = hasPermission('redirects', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        requirePermission('redirects', 'edit');
        $action = (string) ($_POST['action'] ?? '');
        $who    = (string) ($_SESSION['admin_username'] ?? 'unknown');

        if ($action === 'add') {
            $from = '/' . ltrim(trim((string) ($_POST['from_path'] ?? '')), '/');
            $to   = trim((string) ($_POST['to_path'] ?? ''));
            $code = (int) ($_POST['status_code'] ?? 301);

            if ($from === '/' || $to === '') {
                $error = 'A redirect needs both an old address and a new one.';
            } elseif (!str_starts_with($to, '/') && preg_match('#^https?://#i', $to) !== 1) {
                $error = 'The destination must be a path on this site or an http address.';
            } elseif ($from === $to) {
                $error = 'That would send the address to itself.';
            } else {
                $pdo->prepare('INSERT INTO cms_redirects (from_path, to_path, status_code, created_by)
                               VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE to_path = VALUES(to_path), status_code = VALUES(status_code)')
                    ->execute([mb_substr($from, 0, 255), mb_substr($to, 0, 255),
                               in_array($code, [301, 302], true) ? $code : 301, $who]);
                pmAudit($pdo, 'redirect_add', 'Pointed ' . $from . ' at ' . $to, 'cms_redirects');
                $notice = 'Redirect saved. It takes effect immediately.';
            }
        }

        if ($action === 'delete' && (int) ($_POST['id'] ?? 0) > 0) {
            $id = (int) $_POST['id'];
            $pdo->prepare('DELETE FROM cms_redirects WHERE id = ?')->execute([$id]);
            pmAudit($pdo, 'redirect_delete', 'Removed redirect #' . $id, 'cms_redirects', $id);
            $notice = 'Redirect removed.';
        }

        if ($action === 'clear_404' && (int) ($_POST['id'] ?? 0) > 0) {
            $pdo->prepare('DELETE FROM cms_not_found WHERE id = ?')->execute([(int) $_POST['id']]);
            $notice = 'Cleared from the list.';
        }
    }
}

$redirects = $notFound = [];
try {
    $redirects = $pdo->query('SELECT * FROM cms_redirects ORDER BY hits DESC, id DESC LIMIT 300')->fetchAll();
    $notFound  = $pdo->query('SELECT * FROM cms_not_found ORDER BY hits DESC, last_seen_at DESC LIMIT 300')->fetchAll();
} catch (Throwable $e) {
    $error = 'Those lists could not be read.';
}

$csrfToken = generateCsrfToken();
require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="pma-split">
  <div>
    <div class="table-card" style="margin-bottom:0">
      <div class="table-card-header">
        <div>
          <h2 class="card-title">Redirects and 404s</h2>
          <p class="card-subtitle">A redirect is only consulted after a request has found nothing, so it can
             never shadow a real page.</p>
        </div>
      </div>

      <div style="display:flex;border-bottom:1px solid var(--pma-border);padding:0 8px">
        <a class="tab-btn <?php echo $tab === 'redirects' ? 'active' : ''; ?>" style="text-decoration:none"
           href="redirects.php">Redirects <span style="font-family:var(--pma-mono);margin-left:6px"><?php
           echo count($redirects); ?></span></a>
        <a class="tab-btn <?php echo $tab === 'notfound' ? 'active' : ''; ?>" style="text-decoration:none"
           href="redirects.php?tab=notfound">Not found <span style="font-family:var(--pma-mono);margin-left:6px"><?php
           echo count($notFound); ?></span></a>
      </div>

<?php if ($tab === 'redirects'): ?>
<?php if (!$redirects): ?>
      <div class="empty-state">
        <span class="empty-state-title">No redirects yet</span>
        Add one on the right when an address changes, so links in partner mailings keep working.
      </div>
<?php else: ?>
      <div class="table-responsive">
        <table>
          <thead><tr><th>Old address</th><th>Goes to</th><th>Code</th><th class="num">Used</th><th>Last used</th>
<?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
<?php foreach ($redirects as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['from_path']); ?></td>
              <td class="text-muted"><?php echo htmlspecialchars($r['to_path']); ?></td>
              <td class="text-muted"><?php echo (int) $r['status_code']; ?></td>
              <td class="num"><?php echo (int) $r['hits']; ?></td>
              <td class="text-muted"><?php echo $r['last_hit_at']
                ? htmlspecialchars(date('j M Y', strtotime($r['last_hit_at']))) : 'Never'; ?></td>
<?php if ($canEdit): ?>
              <td class="num">
                <form method="POST" action="redirects.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                  <button type="submit" class="btn btn-outline btn-sm"
                          data-confirm="Remove this redirect? Anyone following the old link will get a 404 again.">Remove</button>
                </form>
              </td>
<?php endif; ?>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>

<?php else: ?>
<?php if (!$notFound): ?>
      <div class="empty-state">
        <span class="empty-state-title">Nothing has 404ed</span>
        Addresses that people ask for and this site does not have will appear here.
      </div>
<?php else: ?>
      <div class="table-responsive">
        <table>
          <thead><tr><th>Address asked for</th><th class="num">Times</th><th>Last seen</th><th>Came from</th><th></th></tr></thead>
          <tbody>
<?php foreach ($notFound as $n): ?>
            <tr>
              <td><?php echo htmlspecialchars($n['path']); ?></td>
              <td class="num"><?php echo (int) $n['hits']; ?></td>
              <td class="text-muted"><?php echo htmlspecialchars(date('j M Y', strtotime($n['last_seen_at']))); ?></td>
              <td class="text-muted"><?php echo htmlspecialchars(mb_strimwidth((string) $n['referrer'], 0, 40, '...')); ?></td>
              <td class="num" style="white-space:nowrap">
<?php if ($canEdit): ?>
                <a class="btn btn-outline btn-sm"
                   href="<?php echo htmlspecialchars('redirects.php?from=' . urlencode($n['path'])); ?>">Redirect it</a>
                <form method="POST" action="redirects.php?tab=notfound" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="action" value="clear_404">
                  <input type="hidden" name="id" value="<?php echo (int) $n['id']; ?>">
                  <button type="submit" class="btn btn-outline btn-sm">Clear</button>
                </form>
<?php endif; ?>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
<?php endif; ?>
    </div>
  </div>

<?php if ($canEdit): ?>
  <aside>
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px">Add a redirect</h2>
      <form method="POST" action="redirects.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label for="from_path">Old address</label>
          <input type="text" id="from_path" name="from_path" class="form-control" required
                 value="<?php echo htmlspecialchars((string) ($_GET['from'] ?? '')); ?>"
                 placeholder="/old-page">
        </div>
        <div class="form-group">
          <label for="to_path">Send it to</label>
          <input type="text" id="to_path" name="to_path" class="form-control" required placeholder="/events.php">
          <span class="form-hint">A path on this site, or a full https address.</span>
        </div>
        <div class="form-group">
          <label for="status_code">Kind</label>
          <select id="status_code" name="status_code" class="form-control">
            <option value="301">Permanent (301)</option>
            <option value="302">Temporary (302)</option>
          </select>
          <span class="form-hint">Permanent tells search engines to update their index. Use temporary if the old
                address will come back.</span>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save redirect</button>
      </form>
    </div>
  </aside>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
