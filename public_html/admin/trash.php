<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/media.php';
require_once '../includes/trash.php';
requireAdminAuth();
requirePermission('content', 'view');

$pageTitle  = 'Trash';
$activePage = 'trash';

ensureTrashSchema($pdo);

$notice = '';
$error  = '';
$type   = isset(PM_TRASH_TYPES[$_GET['type'] ?? '']) ? (string) $_GET['type'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);

        if (($_POST['action'] ?? '') === 'restore' && $id > 0) {
            requirePermission('content', 'edit');
            $row    = pmTrashFind($pdo, $id);
            $result = pmTrashRestore($pdo, $id);

            if ($result['ok']) {
                pmAudit($pdo, 'trash_restore', 'Restored ' . ($row['label'] ?? ('#' . $id)) . ' from the trash',
                        'cms_trash', $id);
                $notice = $result['message'];
            } else {
                $error = $result['message'];
            }
        }

        if (($_POST['action'] ?? '') === 'purge' && $id > 0 && isSuper()) {
            $row = pmTrashFind($pdo, $id);
            if ($row) {
                if ($row['entity_type'] === 'media') {
                    $data = json_decode((string) $row['snapshot'], true);
                    if (is_array($data) && !empty($data['filename'])) {
                        pmMediaUnlinkFiles((string) $data['filename']);
                    }
                }
                if ($row['entity_type'] === 'page') {
                    $pdo->prepare('DELETE FROM cms_page_blocks WHERE page_id = ?')->execute([(int) $row['entity_id']]);
                    $pdo->prepare('DELETE FROM cms_pages WHERE id = ?')->execute([(int) $row['entity_id']]);
                }
                $pdo->prepare('DELETE FROM cms_trash WHERE id = ?')->execute([$id]);
                pmAudit($pdo, 'trash_purge', 'Permanently deleted ' . $row['label'], 'cms_trash', $id);
                $notice = 'Deleted permanently.';
            }
        }
    }
}

// There is no scheduler on this hosting, so expiry happens when somebody looks.
$purged = pmTrashPurgeExpired($pdo);
if ($purged > 0) {
    $notice = trim($notice . ' ' . $purged . ' item(s) past 30 days were removed for good.');
}

$rows      = pmTrashList($pdo, $type);
$total     = pmTrashCount($pdo);
$csrfToken = generateCsrfToken();
$canEdit   = hasPermission('content', 'edit');

require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2 class="card-title">Trash</h2>
      <p class="card-subtitle">Everything deleted anywhere in the panel lands here and stays recoverable
         for <?php echo PM_TRASH_DAYS; ?> days. After that it is removed for good.</p>
    </div>
  </div>

  <form method="GET" action="trash.php" class="pma-toolbar">
    <label>
      <span class="pma-vh">Kind of item</span>
      <select name="type">
        <option value="">Everything<?php echo $total > 0 ? ' (' . $total . ')' : ''; ?></option>
<?php foreach (PM_TRASH_TYPES as $key => $label): ?>
        <option value="<?php echo htmlspecialchars($key); ?>"
          <?php echo $type === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn btn-outline btn-sm">Apply</button>
<?php if ($type !== ''): ?>
    <a class="btn btn-outline btn-sm" href="trash.php">Clear</a>
<?php endif; ?>
  </form>

<?php if (!$rows): ?>
  <div class="empty-state">
    <span class="empty-state-title">The trash is empty</span>
    Nothing has been deleted, or everything deleted has already been restored.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>Item</th><th>Kind</th><th>Where it was</th><th>Deleted</th><th class="num">Days left</th><th></th></tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): $left = pmTrashDaysLeft($row); ?>
        <tr>
          <td><?php echo htmlspecialchars($row['label']); ?></td>
          <td class="text-muted"><?php echo htmlspecialchars(PM_TRASH_TYPES[$row['entity_type']] ?? $row['entity_type']); ?></td>
          <td class="text-muted"><?php echo htmlspecialchars((string) $row['context']); ?></td>
          <td class="text-muted">
            <?php echo htmlspecialchars(date('j M Y', strtotime($row['deleted_at']))); ?>
            <?php echo $row['deleted_by'] ? 'by ' . htmlspecialchars($row['deleted_by']) : ''; ?>
          </td>
          <td class="num">
            <span class="badge <?php echo $left <= 5 ? 'badge-orange' : 'badge-gray'; ?>"><?php echo $left; ?></span>
          </td>
          <td class="num" style="white-space:nowrap">
<?php if ($canEdit): ?>
            <form method="POST" action="trash.php" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
              <button type="submit" class="btn btn-outline btn-sm">Restore</button>
            </form>
<?php endif; ?>
<?php if (isSuper()): ?>
            <form method="POST" action="trash.php" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
              <input type="hidden" name="action" value="purge">
              <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="Delete this permanently? This one cannot be undone.">Delete for good</button>
            </form>
<?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
