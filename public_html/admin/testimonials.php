<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/content.php';
require_once '../includes/trash.php';
require_once '../includes/testimonials.php';
requireAdminAuth();

if (!pmCanManageTestimonials()) {
    $_SESSION['perm_error'] = 'You do not have access to delegate reviews.';
    header('Location: dashboard.php');
    exit;
}

$pageTitle  = 'Delegate reviews';
$activePage = 'testimonials';

ensureTestimonialSchema($pdo);

// Carried over from the JSON row the homepage has always read, so opening this
// screen for the first time changes nothing on the live site.
pmTestimonialsSeedOnce($pdo, pmContentJson($pdo, 'home', 'testimonials', []));

$notice = '';
$error  = '';
$who    = (string) ($_SESSION['admin_username'] ?? 'unknown');
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $id     = (int) ($_POST['id'] ?? 0);
        $quote  = trim((string) ($_POST['quote'] ?? ''));
        $role   = trim((string) ($_POST['role'] ?? ''));
        $org    = trim((string) ($_POST['org'] ?? ''));

        if ($action === 'add' || $action === 'update') {
            if ($quote === '') {
                $error = 'A review needs the words the delegate said.';
            } elseif ($action === 'add') {
                $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_testimonials')->fetchColumn();
                $pdo->prepare('INSERT INTO cms_testimonials (quote, role, org, sort_order, added_by, is_published)
                               VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([mb_substr($quote, 0, 2000), mb_substr($role, 0, 160) ?: null,
                               mb_substr($org, 0, 200) ?: null, $next, $who,
                               isset($_POST['is_published']) ? 1 : 0]);
                pmAudit($pdo, 'review_add', 'Added a delegate review from ' . ($org ?: 'an unnamed organisation'),
                        'cms_testimonials', $pdo->lastInsertId());
                $notice = 'Review added.';
            } else {
                $pdo->prepare('UPDATE cms_testimonials SET quote = ?, role = ?, org = ?, is_published = ? WHERE id = ?')
                    ->execute([mb_substr($quote, 0, 2000), mb_substr($role, 0, 160) ?: null,
                               mb_substr($org, 0, 200) ?: null, isset($_POST['is_published']) ? 1 : 0, $id]);
                pmAudit($pdo, 'review_edit', 'Edited delegate review #' . $id, 'cms_testimonials', $id);
                $notice = 'Review saved.';
                $editId = 0;
            }
        }

        if ($action === 'delete' && $id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM cms_testimonials WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if ($row && pmTrashPut($pdo, 'testimonial', $id,
                    mb_strimwidth((string) $row['quote'], 0, 60, '...'), $row, $who,
                    (string) ($row['org'] ?: 'Delegate review'))) {
                $pdo->prepare('DELETE FROM cms_testimonials WHERE id = ?')->execute([$id]);
                pmAudit($pdo, 'review_delete', 'Moved a delegate review to the trash', 'cms_testimonials', $id);
                $notice = 'Moved to the trash. It can be restored for the next 30 days.';
            } else {
                $error = 'That review could not be removed.';
            }
        }

        if (($action === 'up' || $action === 'down') && $id > 0) {
            $rows  = pmTestimonialsAll($pdo);
            $index = null;
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id) { $index = $i; break; }
            }
            $swap = $action === 'up' ? $index - 1 : $index + 1;

            if ($index !== null && isset($rows[$swap])) {
                $a = $rows[$index];
                $b = $rows[$swap];
                $stmt = $pdo->prepare('UPDATE cms_testimonials SET sort_order = ? WHERE id = ?');
                $stmt->execute([(int) $b['sort_order'] === (int) $a['sort_order']
                    ? (int) $a['sort_order'] + ($action === 'up' ? -1 : 1) : (int) $b['sort_order'], (int) $a['id']]);
                $stmt->execute([(int) $a['sort_order'], (int) $b['id']]);
                pmAudit($pdo, 'review_reorder', 'Moved a delegate review ' . $action, 'cms_testimonials', $id);
            }
            $notice = 'Order updated.';
        }
    }
}

$rows      = pmTestimonialsAll($pdo);
$live      = count(array_filter($rows, static fn (array $r) => (int) $r['is_published'] === 1));
$csrfToken = generateCsrfToken();

$editing = null;
foreach ($rows as $row) {
    if ((int) $row['id'] === $editId) { $editing = $row; }
}

require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="pma-split">
  <div>
    <div class="table-card" style="margin-bottom:0">
      <div class="table-card-header">
        <div>
          <h2 class="card-title">Delegate reviews</h2>
          <p class="card-subtitle"><?php echo count($rows); ?> held, <?php echo $live; ?> shown on the homepage.
             Every staff member can add and remove these.</p>
        </div>
      </div>

<?php if (!$rows): ?>
      <div class="empty-state">
        <span class="empty-state-title">No reviews yet</span>
        Add the first one on the right. Until there is one here, the homepage falls back to the reviews it
        was built with, so nothing looks broken.
      </div>
<?php else: ?>
      <div class="table-responsive">
        <table>
          <thead><tr><th>What they said</th><th>Who</th><th>Shown</th><th class="num">Order</th><th></th></tr></thead>
          <tbody>
<?php foreach ($rows as $i => $row): ?>
            <tr>
              <td style="max-width:420px"><?php echo htmlspecialchars(mb_strimwidth((string) $row['quote'], 0, 150, '...')); ?></td>
              <td class="text-muted">
                <?php echo htmlspecialchars((string) $row['role']); ?>
<?php if (!empty($row['org'])): ?><br><?php echo htmlspecialchars((string) $row['org']); ?><?php endif; ?>
              </td>
              <td>
                <span class="badge <?php echo $row['is_published'] ? 'badge-green' : 'badge-gray'; ?>">
                  <?php echo $row['is_published'] ? 'On the site' : 'Hidden'; ?>
                </span>
              </td>
              <td class="num" style="white-space:nowrap">
                <form method="POST" action="testimonials.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                  <button type="submit" name="action" value="up" class="btn btn-icon btn-sm"
                          aria-label="Move up" <?php echo $i === 0 ? 'disabled' : ''; ?>>&#8593;</button>
                  <button type="submit" name="action" value="down" class="btn btn-icon btn-sm"
                          aria-label="Move down" <?php echo $i === count($rows) - 1 ? 'disabled' : ''; ?>>&#8595;</button>
                </form>
              </td>
              <td class="num" style="white-space:nowrap">
                <a class="btn btn-outline btn-sm"
                   href="testimonials.php?edit=<?php echo (int) $row['id']; ?>">Edit</a>
                <form method="POST" action="testimonials.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Move this review to the trash? You can restore it for 30 days.">Delete</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px"><?php echo $editing ? 'Edit review' : 'Add a review'; ?></h2>
      <form method="POST" action="testimonials.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'add'; ?>">
<?php if ($editing): ?>
        <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
<?php endif; ?>

        <div class="form-group">
          <label for="quote">What they said</label>
          <textarea id="quote" name="quote" class="form-control" rows="5" maxlength="2000"
                    required><?php echo htmlspecialchars((string) ($editing['quote'] ?? '')); ?></textarea>
          <span class="form-hint">Their words, not a summary. Quote marks are added by the page.</span>
        </div>

        <div class="form-group">
          <label for="role">Their role</label>
          <input type="text" id="role" name="role" class="form-control" maxlength="160"
                 placeholder="Chief Accountant"
                 value="<?php echo htmlspecialchars((string) ($editing['role'] ?? '')); ?>">
        </div>

        <div class="form-group">
          <label for="org">Their organisation</label>
          <input type="text" id="org" name="org" class="form-control" maxlength="200"
                 placeholder="Ministry of Finance, Kenya"
                 value="<?php echo htmlspecialchars((string) ($editing['org'] ?? '')); ?>">
          <span class="form-hint">Name a person only if they have agreed to be named.</span>
        </div>

        <label class="check-group">
          <input type="checkbox" name="is_published" value="1"
                 <?php echo (!$editing || (int) $editing['is_published'] === 1) ? 'checked' : ''; ?>>
          <div>
            <div class="check-group-label">Show on the site</div>
            <div class="check-group-sub">Turn this off to hold a review back without deleting it.</div>
          </div>
        </label>

        <button type="submit" class="btn btn-primary btn-sm"><?php echo $editing ? 'Save review' : 'Add review'; ?></button>
<?php if ($editing): ?>
        <a class="btn btn-outline btn-sm" href="testimonials.php">Cancel</a>
<?php endif; ?>
      </form>
    </div>
  </aside>
</div>

<?php require_once 'footer.php'; ?>
