<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
requireAdminAuth();
requirePermission('audit', 'view');

if (!isSuper()) {
    $_SESSION['perm_error'] = 'The audit log is limited to super admins.';
    header('Location: dashboard.php');
    exit;
}

$pageTitle  = 'Audit log';
$activePage = 'audit';

pmAuditEnsureSchema($pdo);

$actor  = trim((string) ($_GET['actor'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
$days   = (int) ($_GET['days'] ?? 30);
$days   = in_array($days, [1, 7, 30, 90, 0], true) ? $days : 30;

$where = [];
$args  = [];
if ($actor !== '')  { $where[] = 'actor_username = ?'; $args[] = $actor; }
if ($action !== '') { $where[] = 'action = ?';         $args[] = $action; }
if ($days > 0)      { $where[] = 'created_at > DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)'; }

$rows = $actors = $actions = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM cms_audit_log'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY id DESC LIMIT 400');
    $stmt->execute($args);
    $rows = $stmt->fetchAll() ?: [];

    $actors  = $pdo->query('SELECT DISTINCT actor_username FROM cms_audit_log ORDER BY actor_username')->fetchAll();
    $actions = $pdo->query('SELECT DISTINCT action FROM cms_audit_log ORDER BY action')->fetchAll();
} catch (Throwable $e) {
    error_log('audit view: ' . $e->getMessage());
}

require_once 'header.php';
?>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2 class="card-title">Audit log</h2>
      <p class="card-subtitle">Append only. Nothing in this panel edits or deletes a row here, which is the
         only reason it is worth reading.</p>
    </div>
  </div>

  <form method="GET" action="audit.php" class="pma-toolbar">
    <label>
      <span class="pma-vh">Account</span>
      <select name="actor">
        <option value="">Everyone</option>
<?php foreach ($actors as $a): ?>
        <option value="<?php echo htmlspecialchars($a['actor_username']); ?>"
          <?php echo $actor === $a['actor_username'] ? 'selected' : ''; ?>><?php
          echo htmlspecialchars($a['actor_username']); ?></option>
<?php endforeach; ?>
      </select>
    </label>
    <label>
      <span class="pma-vh">Action</span>
      <select name="action">
        <option value="">Every action</option>
<?php foreach ($actions as $a): ?>
        <option value="<?php echo htmlspecialchars($a['action']); ?>"
          <?php echo $action === $a['action'] ? 'selected' : ''; ?>><?php
          echo htmlspecialchars($a['action']); ?></option>
<?php endforeach; ?>
      </select>
    </label>
    <label>
      <span class="pma-vh">Period</span>
      <select name="days">
<?php foreach ([1 => 'Last 24 hours', 7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 0 => 'Everything'] as $d => $label): ?>
        <option value="<?php echo $d; ?>" <?php echo $days === $d ? 'selected' : ''; ?>><?php
          echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn btn-outline btn-sm">Apply</button>
<?php if ($actor !== '' || $action !== '' || $days !== 30): ?>
    <a class="btn btn-outline btn-sm" href="audit.php">Clear</a>
<?php endif; ?>
  </form>

<?php if (!$rows): ?>
  <div class="empty-state">
    <span class="empty-state-title">Nothing recorded in this period</span>
    Widen the period, or clear the filters.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table>
      <thead><tr><th>When</th><th>Who</th><th>Action</th><th>What happened</th><th>From</th></tr></thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td class="text-muted" style="white-space:nowrap"><?php
            echo htmlspecialchars(date('j M Y, H:i', strtotime($row['created_at']))); ?></td>
          <td>
            <?php echo htmlspecialchars($row['actor_username']); ?>
<?php if ($row['actor_username'] === 'admin'): ?>
            <br><span class="badge badge-orange">Shared account</span>
<?php endif; ?>
          </td>
          <td class="text-muted"><?php echo htmlspecialchars($row['action']); ?></td>
          <td><?php echo htmlspecialchars($row['summary']); ?></td>
          <td class="text-muted" style="font-family:var(--pma-mono);font-size:11px"><?php
            echo htmlspecialchars((string) $row['ip_address']); ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
