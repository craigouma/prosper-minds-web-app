<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/menus.php';
require_once '../includes/trash.php';
require_once '../includes/layout/page.php';
requireAdminAuth();
requirePermission('menus', 'view');

$pageTitle  = 'Menus';
$activePage = 'menus';

ensureMenuSchema($pdo);
pmMenuSeedFromDefaults($pdo, 'header', [
    ['label' => 'Home',        'href' => '/index.php'],
    ['label' => 'Events',      'href' => '/events.php'],
    ['label' => 'Services',    'href' => '/services.php'],
    ['label' => 'About',       'href' => '/about.php'],
    ['label' => 'Sponsorship', 'href' => '/sponsorship.php'],
    ['label' => 'Contact',     'href' => '/contact.php'],
]);

$location = isset($_GET['location']) && isset(PM_MENU_LOCATIONS[$_GET['location']]) ? $_GET['location'] : 'header';
$notice   = '';
$error    = '';
$canEdit  = hasPermission('menus', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        requirePermission('menus', 'edit');
        $action = (string) ($_POST['action'] ?? '');
        $id     = (int) ($_POST['id'] ?? 0);
        $loc    = isset(PM_MENU_LOCATIONS[$_POST['location'] ?? '']) ? $_POST['location'] : 'header';

        if ($action === 'add') {
            $label  = trim((string) ($_POST['label'] ?? ''));
            $type   = isset(PM_MENU_LINK_TYPES[$_POST['link_type'] ?? '']) ? $_POST['link_type'] : 'page';
            $target = trim((string) ($_POST['target'] ?? ''));

            if ($label === '' || $target === '') {
                $error = 'A menu item needs a label and a destination.';
            } elseif (pmMenuHref(['link_type' => $type, 'target' => $target]) === null) {
                $error = 'That destination is not usable. An external address must start with http or https.';
            } else {
                $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_menu_items')->fetchColumn();
                $pdo->prepare('INSERT INTO cms_menu_items (location, label, link_type, target, sort_order) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$loc, mb_substr($label, 0, 120), $type, mb_substr($target, 0, 255), $next]);
                pmAudit($pdo, 'menu_add', 'Added "' . $label . '" to the ' . $loc . ' menu', 'cms_menu_items', $pdo->lastInsertId());
                $notice = 'Added.';
            }
        }

        if ($action === 'update' && $id > 0) {
            $label  = trim((string) ($_POST['label'] ?? ''));
            $type   = isset(PM_MENU_LINK_TYPES[$_POST['link_type'] ?? '']) ? $_POST['link_type'] : 'page';
            $target = trim((string) ($_POST['target'] ?? ''));

            if ($label === '' || $target === '') {
                $error = 'A menu item needs a label and a destination.';
            } elseif (pmMenuHref(['link_type' => $type, 'target' => $target]) === null) {
                $error = 'That destination is not usable. An external address must start with http or https.';
            } else {
                $pdo->prepare('UPDATE cms_menu_items SET label = ?, link_type = ?, target = ?, is_active = ? WHERE id = ?')
                    ->execute([mb_substr($label, 0, 120), $type, mb_substr($target, 0, 255),
                               isset($_POST['is_active']) ? 1 : 0, $id]);
                pmAudit($pdo, 'menu_update', 'Updated menu item "' . $label . '"', 'cms_menu_items', $id);
                $notice = 'Saved.';
            }
        }

        if ($action === 'delete' && $id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM cms_menu_items WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if ($row && pmTrashPut($pdo, 'menu_item', $id, $row['label'], $row,
                                   (string) ($_SESSION['admin_username'] ?? 'unknown'),
                                   ucfirst($row['location']) . ' menu')) {
                $pdo->prepare('DELETE FROM cms_menu_items WHERE id = ?')->execute([$id]);
                pmAudit($pdo, 'menu_delete', 'Moved menu item "' . $row['label'] . '" to the trash', 'cms_menu_items', $id);
                $notice = 'Moved to the trash. It can be restored for the next 30 days.';
            } else {
                $error = 'That item could not be removed.';
            }
        }

        if (($action === 'up' || $action === 'down') && $id > 0) {
            // Swapping sort values with the neighbour keeps the list stable
            // when two items were seeded with the same order.
            $items = pmMenuAll($pdo, $loc);
            $index = null;
            foreach ($items as $i => $row) {
                if ((int) $row['id'] === $id) { $index = $i; break; }
            }
            $swap = $action === 'up' ? ($index - 1) : ($index + 1);

            if ($index !== null && isset($items[$swap])) {
                $a = $items[$index];
                $b = $items[$swap];
                $stmt = $pdo->prepare('UPDATE cms_menu_items SET sort_order = ? WHERE id = ?');
                $stmt->execute([(int) $b['sort_order'] === (int) $a['sort_order'] ? (int) $a['sort_order'] + ($action === 'up' ? -1 : 1) : (int) $b['sort_order'], (int) $a['id']]);
                $stmt->execute([(int) $a['sort_order'], (int) $b['id']]);
                pmAudit($pdo, 'menu_reorder', 'Moved "' . $a['label'] . '" ' . $action, 'cms_menu_items', $id);
            }
            $notice = 'Order updated.';
        }

        $location = $loc;
    }
}

$items      = pmMenuAll($pdo, $location);
$csrfToken  = generateCsrfToken();
$editId     = (int) ($_GET['edit'] ?? 0);
$editing    = null;
foreach ($items as $row) {
    if ((int) $row['id'] === $editId) { $editing = $row; }
}

$pageChoices = [
    'index.php'       => 'Home',
    'events.php'      => 'Events',
    'services.php'    => 'Services',
    'about.php'       => 'About',
    'sponsorship.php' => 'Sponsorship',
    'contact.php'     => 'Contact',
    'privacy-policy.php' => 'Privacy policy',
];

require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="pma-split">
  <div>
    <div class="table-card" style="margin-bottom:0">
      <div class="table-card-header">
        <div>
          <h2 class="card-title">Navigation</h2>
          <p class="card-subtitle">The header and footer are the two menus this site has, so they are two tabs
             rather than an abstract menu manager</p>
        </div>
      </div>

      <div style="display:flex;border-bottom:1px solid var(--pma-border);padding:0 8px">
<?php foreach (PM_MENU_LOCATIONS as $key => $label): ?>
        <a class="tab-btn <?php echo $key === $location ? 'active' : ''; ?>" style="text-decoration:none"
           href="<?php echo htmlspecialchars('menus.php?location=' . $key); ?>"><?php echo htmlspecialchars($label); ?></a>
<?php endforeach; ?>
      </div>

<?php if (!$items): ?>
      <div class="empty-state">
        <span class="empty-state-title">This menu is empty</span>
        The site falls back to its built-in navigation while there are no items here, so nothing is broken.
        Add an item on the right to take control of it.
      </div>
<?php else: ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr><th>Label</th><th>Goes to</th><th>State</th><?php if ($canEdit): ?><th class="num">Order</th><th></th><?php endif; ?></tr>
          </thead>
          <tbody>
<?php foreach ($items as $i => $row): $href = pmMenuHref($row); ?>
            <tr>
              <td><?php echo htmlspecialchars($row['label']); ?></td>
              <td class="text-muted">
                <?php echo htmlspecialchars(PM_MENU_LINK_TYPES[$row['link_type']] ?? $row['link_type']); ?>:
                <?php echo $href !== null ? htmlspecialchars($href) : '<span class="badge badge-red">Unusable</span>'; ?>
              </td>
              <td>
                <span class="badge <?php echo $row['is_active'] ? 'badge-green' : 'badge-gray'; ?>">
                  <?php echo $row['is_active'] ? 'Shown' : 'Hidden'; ?>
                </span>
              </td>
<?php if ($canEdit): ?>
              <td class="num" style="white-space:nowrap">
                <form method="POST" action="menus.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                  <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
                  <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                  <button type="submit" name="action" value="up" class="btn btn-icon btn-sm"
                          aria-label="Move up" <?php echo $i === 0 ? 'disabled' : ''; ?>>&#8593;</button>
                  <button type="submit" name="action" value="down" class="btn btn-icon btn-sm"
                          aria-label="Move down" <?php echo $i === count($items) - 1 ? 'disabled' : ''; ?>>&#8595;</button>
                </form>
              </td>
              <td class="num" style="white-space:nowrap">
                <a class="btn btn-outline btn-sm"
                   href="<?php echo htmlspecialchars('menus.php?location=' . $location . '&edit=' . (int) $row['id']); ?>">Edit</a>
              </td>
<?php endif; ?>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>

<?php if ($canEdit): ?>
  <aside>
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px"><?php echo $editing ? 'Edit item' : 'Add an item'; ?></h2>
      <form method="POST" action="menus.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'add'; ?>">
<?php if ($editing): ?>
        <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
<?php endif; ?>

        <div class="form-group">
          <label for="label">Label</label>
          <input type="text" id="label" name="label" class="form-control" maxlength="120" required
                 value="<?php echo htmlspecialchars((string) ($editing['label'] ?? '')); ?>">
        </div>

        <div class="form-group">
          <label for="link_type">Kind of link</label>
          <select id="link_type" name="link_type" class="form-control" data-menu-type>
<?php foreach (PM_MENU_LINK_TYPES as $key => $label): ?>
            <option value="<?php echo htmlspecialchars($key); ?>"
              <?php echo ($editing['link_type'] ?? 'page') === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="target">Destination</label>
          <input type="text" id="target" name="target" class="form-control" maxlength="255" required
                 list="pm-menu-pages"
                 value="<?php echo htmlspecialchars((string) ($editing['target'] ?? '')); ?>"
                 placeholder="events.php">
          <datalist id="pm-menu-pages">
<?php foreach ($pageChoices as $file => $label): ?>
            <option value="<?php echo htmlspecialchars($file); ?>"><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
          </datalist>
          <span class="form-hint">A page file such as events.php, an event id, a full https address,
                or a section name for an anchor.</span>
        </div>

<?php if ($editing): ?>
        <label class="check-group-sub" style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
          <input type="checkbox" name="is_active" value="1" <?php echo $editing['is_active'] ? 'checked' : ''; ?>>
          Show this item on the site
        </label>
<?php endif; ?>

        <button type="submit" class="btn btn-primary btn-sm"><?php echo $editing ? 'Save item' : 'Add item'; ?></button>
<?php if ($editing): ?>
        <a class="btn btn-outline btn-sm" href="<?php echo htmlspecialchars('menus.php?location=' . $location); ?>">Cancel</a>
<?php endif; ?>
      </form>

<?php if ($editing): ?>
      <form method="POST" action="menus.php" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--pma-border)">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
        <button type="submit" class="btn btn-danger btn-sm"
                data-confirm="Move this menu item to the trash? You can restore it for 30 days.">Remove this item</button>
      </form>
<?php endif; ?>
    </div>
  </aside>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
