<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/pages.php';
requireAdminAuth();
requirePermission('content', 'view');

$pageTitle  = 'Pages';
$activePage = 'pages';

ensurePagesSchema($pdo);

$notice = '';
$error  = '';
$tab    = ($_GET['tab'] ?? '') === 'trash' ? 'trash' : 'live';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $id     = (int) ($_POST['id'] ?? 0);
        $who    = (string) ($_SESSION['admin_username'] ?? 'unknown');

        if ($action === 'create') {
            requirePermission('content', 'create');
            $title = trim((string) ($_POST['title'] ?? ''));
            $slug  = pmPageSlugify(trim((string) ($_POST['slug'] ?? '')) ?: $title);

            if ($title === '') {
                $error = 'A page needs a title.';
            } elseif (pmPageFindBySlug($pdo, $slug) !== null) {
                $error = 'A page with that address already exists.';
            } elseif (is_file(__DIR__ . '/../' . $slug . '.php')) {
                // A CMS slug that matches a real file would never be reached,
                // because the rewrite only runs when no file matches.
                $error = 'That address is already used by a built-in page.';
            } else {
                $pdo->prepare('INSERT INTO cms_pages (title, slug, updated_by) VALUES (?, ?, ?)')
                    ->execute([mb_substr($title, 0, 180), $slug, $who]);
                $newId = (int) $pdo->lastInsertId();
                pmRevisionStore($pdo, 'page', $newId, pmPageSnapshot($pdo, $newId), 'Created', $who);
                pmAudit($pdo, 'page_create', 'Created the page "' . $title . '"', 'cms_pages', $newId);
                header('Location: page-editor.php?id=' . $newId);
                exit;
            }
        }

        if ($action === 'trash' && $id > 0) {
            requirePermission('content', 'delete');
            $pdo->prepare('UPDATE cms_pages SET trashed_at = NOW() WHERE id = ?')->execute([$id]);
            pmAudit($pdo, 'page_trash', 'Moved page #' . $id . ' to the trash', 'cms_pages', $id);
            $notice = 'Moved to the trash. It can be restored for 30 days.';
        }

        if ($action === 'restore' && $id > 0) {
            requirePermission('content', 'edit');
            $pdo->prepare('UPDATE cms_pages SET trashed_at = NULL WHERE id = ?')->execute([$id]);
            pmAudit($pdo, 'page_restore', 'Restored page #' . $id . ' from the trash', 'cms_pages', $id);
            $notice = 'Restored.';
        }

        if ($action === 'purge' && $id > 0 && isSuper()) {
            $pdo->prepare('DELETE FROM cms_page_blocks WHERE page_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM cms_pages WHERE id = ?')->execute([$id]);
            pmAudit($pdo, 'page_purge', 'Permanently deleted page #' . $id, 'cms_pages', $id);
            $notice = 'Deleted permanently.';
        }
    }
}

$rows      = pmPagesList($pdo, $tab === 'trash');
$liveCount = count(pmPagesList($pdo, false));
$binCount  = count(pmPagesList($pdo, true));
$csrfToken = generateCsrfToken();
$canCreate = hasPermission('content', 'create');
$canDelete = hasPermission('content', 'delete');

require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="pma-split">
  <div>
    <div class="table-card" style="margin-bottom:0">
      <div class="table-card-header">
        <div>
          <h2 class="card-title">Pages</h2>
          <p class="card-subtitle">Pages built from blocks. The nine original pages keep their own templates
             and are edited field by field.</p>
        </div>
      </div>

      <div style="display:flex;border-bottom:1px solid var(--pma-border);padding:0 8px">
        <a class="tab-btn <?php echo $tab === 'live' ? 'active' : ''; ?>" style="text-decoration:none"
           href="pages.php">Pages <span style="font-family:var(--pma-mono);margin-left:6px"><?php echo $liveCount; ?></span></a>
        <a class="tab-btn <?php echo $tab === 'trash' ? 'active' : ''; ?>" style="text-decoration:none"
           href="pages.php?tab=trash">Trash <span style="font-family:var(--pma-mono);margin-left:6px"><?php echo $binCount; ?></span></a>
      </div>

<?php if (!$rows): ?>
      <div class="empty-state">
        <span class="empty-state-title"><?php echo $tab === 'trash' ? 'The trash is empty' : 'No pages yet'; ?></span>
<?php if ($tab === 'trash'): ?>
        Anything you delete lands here first and can be restored.
<?php else: ?>
        Create one on the right. A new page starts as a draft, so nothing is public until you say so.
<?php endif; ?>
      </div>
<?php else: ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr><th>Title</th><th>Address</th><th>Status</th><th>Last edited</th><th></th></tr>
          </thead>
          <tbody>
<?php foreach ($rows as $row):
        $live = pmPageIsLive($row);
?>
            <tr>
              <td><a href="page-editor.php?id=<?php echo (int) $row['id']; ?>"><?php
                  echo htmlspecialchars($row['title']); ?></a></td>
              <td class="text-muted">/<?php echo htmlspecialchars($row['slug']); ?></td>
              <td>
                <span class="badge <?php echo $live ? 'badge-green' : ($row['status'] === 'scheduled' ? 'badge-orange' : 'badge-gray'); ?>">
                  <?php echo htmlspecialchars(PM_PAGE_STATUSES[$row['status']] ?? $row['status']); ?>
                </span>
<?php if ($row['status'] === 'scheduled' && $row['publish_at']): ?>
                <span class="text-muted" style="font-size:11px"><?php
                  echo htmlspecialchars(date('j M Y, H:i', strtotime($row['publish_at']))); ?></span>
<?php endif; ?>
              </td>
              <td class="text-muted">
                <?php echo htmlspecialchars(date('j M Y', strtotime($row['updated_at']))); ?>
                <?php echo $row['updated_by'] ? 'by ' . htmlspecialchars($row['updated_by']) : ''; ?>
              </td>
              <td class="num" style="white-space:nowrap">
<?php if ($tab === 'trash'): ?>
                <form method="POST" action="pages.php?tab=trash" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                  <button type="submit" name="action" value="restore" class="btn btn-outline btn-sm">Restore</button>
<?php   if (isSuper()): ?>
                  <button type="submit" name="action" value="purge" class="btn btn-danger btn-sm">Delete for good</button>
<?php   endif; ?>
                </form>
<?php else: ?>
                <a class="btn btn-outline btn-sm" href="page-editor.php?id=<?php echo (int) $row['id']; ?>">Edit</a>
<?php   if ($live): ?>
                <a class="btn btn-outline btn-sm" target="_blank" rel="noopener"
                   href="/<?php echo htmlspecialchars($row['slug']); ?>">View</a>
<?php   endif; ?>
<?php   if ($canDelete): ?>
                <form method="POST" action="pages.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="action" value="trash">
                  <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                  <button type="submit" class="btn btn-outline btn-sm">Trash</button>
                </form>
<?php   endif; ?>
<?php endif; ?>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>

<?php if ($canCreate): ?>
  <aside>
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px">New page</h2>
      <form method="POST" action="pages.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
          <label for="title">Title</label>
          <input type="text" id="title" name="title" class="form-control" maxlength="180" required>
        </div>
        <div class="form-group">
          <label for="slug">Address</label>
          <input type="text" id="slug" name="slug" class="form-control" maxlength="180"
                 placeholder="Left empty, this comes from the title">
          <span class="form-hint">The page will live at /your-address. Letters, numbers and hyphens.</span>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Create as a draft</button>
      </form>
    </div>
  </aside>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
