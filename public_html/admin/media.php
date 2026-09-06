<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/media.php';
require_once '../includes/trash.php';
requireAdminAuth();
requirePermission('media', 'view');

$pageTitle  = 'Media library';
$activePage = 'media';

ensureMediaSchema($pdo);

$notice = '';
$error  = '';
$view   = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
$type   = (string) ($_GET['type'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$selId  = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'upload') {
            requirePermission('media', 'upload');
            $result = pmMediaStore($pdo, $_FILES['file'] ?? [], (string) ($_SESSION['admin_username'] ?? 'unknown'),
                                   $_POST['alt_text'] ?? null);
            if ($result['ok']) {
                pmAudit($pdo, 'media_upload', 'Uploaded ' . $result['filename'], 'cms_media', $result['id']);
                $notice = 'Uploaded.';
                $selId  = (int) $result['id'];
            } else {
                $error = $result['error'];
            }
        }

        if ($action === 'details') {
            requirePermission('media', 'edit');
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE cms_media SET alt_text = ?, caption = ?, focal_x = ?, focal_y = ? WHERE id = ?')
                ->execute([
                    trim((string) $_POST['alt_text']) !== '' ? mb_substr(trim($_POST['alt_text']), 0, 255) : null,
                    trim((string) $_POST['caption'])  !== '' ? mb_substr(trim($_POST['caption']), 0, 255)  : null,
                    max(0, min(100, (int) $_POST['focal_x'])),
                    max(0, min(100, (int) $_POST['focal_y'])),
                    $id,
                ]);
            pmAudit($pdo, 'media_edit', 'Updated the details of file #' . $id, 'cms_media', $id);
            $notice = 'Details saved.';
            $selId  = $id;
        }

        if ($action === 'delete') {
            requirePermission('media', 'delete');
            $id  = (int) ($_POST['id'] ?? 0);
            $row = pmMediaFind($pdo, $id);
            $use = pmMediaUsage($pdo, $id);

            if ($use && empty($_POST['confirm_in_use'])) {
                $error = 'That file is used in ' . count($use) . ' place(s). Confirm below to delete it anyway.';
                $selId = $id;
            } elseif ($row && pmTrashPut($pdo, 'media', $id, $row['original_name'], $row,
                                         (string) ($_SESSION['admin_username'] ?? 'unknown'), 'Media library')) {
                // The files stay on disk until the 30 days are up, which is
                // what makes restoring possible at all.
                $pdo->prepare('DELETE FROM cms_media WHERE id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM cms_media_usage WHERE media_id = ?')->execute([$id]);
                pmAudit($pdo, 'media_delete', 'Moved ' . $row['original_name'] . ' to the trash', 'cms_media', $id);
                $notice = 'Moved to the trash. It can be restored for the next 30 days.';
                $selId  = 0;
            } else {
                $error = 'That file could not be deleted.';
            }
        }
    }
}

$where = [];
$args  = [];
if ($type === 'image') { $where[] = "mime LIKE 'image/%'"; }
if ($type === 'pdf')   { $where[] = "mime = 'application/pdf'"; }
if ($search !== '') {
    $where[] = '(original_name LIKE ? OR filename LIKE ? OR alt_text LIKE ?)';
    array_push($args, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
}

$rows = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM cms_media'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY id DESC LIMIT 300');
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('media: list failed: ' . $e->getMessage());
    $error = 'The library could not be read. The error has been logged.';
}

$selected = $selId > 0 ? pmMediaFind($pdo, $selId) : null;
$selUsage = $selected ? pmMediaUsage($pdo, (int) $selected['id']) : [];

$csrfToken  = generateCsrfToken();
$canUpload  = hasPermission('media', 'upload');
$canEdit    = hasPermission('media', 'edit');
$canDelete  = hasPermission('media', 'delete');
$limitLabel = pmMediaHumanSize(pmMediaUploadLimitBytes());

function pmMediaUrlFor(array $overrides = []): string
{
    $base = [
        'view' => $_GET['view'] ?? 'grid',
        'q'    => $_GET['q'] ?? '',
        'type' => $_GET['type'] ?? '',
        'id'   => $_GET['id'] ?? '',
    ];
    $q = array_filter(array_merge($base, $overrides), static function ($v) { return $v !== '' && $v !== null; });

    return 'media.php' . ($q ? '?' . http_build_query($q) : '');
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
          <h2 class="card-title">Media library</h2>
          <p class="card-subtitle"><?php echo count($rows); ?> file<?php echo count($rows) === 1 ? '' : 's'; ?>,
             largest accepted upload is <?php echo htmlspecialchars($limitLabel); ?></p>
        </div>
        <div style="margin-left:auto;display:flex;gap:6px">
          <a class="btn btn-outline btn-sm <?php echo $view === 'grid' ? 'is-on' : ''; ?>"
             href="<?php echo htmlspecialchars(pmMediaUrlFor(['view' => 'grid'])); ?>">Grid</a>
          <a class="btn btn-outline btn-sm <?php echo $view === 'list' ? 'is-on' : ''; ?>"
             href="<?php echo htmlspecialchars(pmMediaUrlFor(['view' => 'list'])); ?>">List</a>
        </div>
      </div>

      <form method="GET" action="media.php" class="pma-toolbar">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
        <label class="pma-search">
          <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M7.2 3a4.2 4.2 0 110 8.4 4.2 4.2 0 010-8.4zM10.4 10.4 13.5 13.5"
                  fill="none" stroke="#6b6b6b" stroke-width="1.3"></path>
          </svg>
          <span class="pma-vh">Search the library</span>
          <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>"
                 placeholder="File name or alt text">
        </label>
        <label>
          <span class="pma-vh">File type</span>
          <select name="type">
            <option value="">All types</option>
            <option value="image" <?php echo $type === 'image' ? 'selected' : ''; ?>>Images</option>
            <option value="pdf"   <?php echo $type === 'pdf'   ? 'selected' : ''; ?>>PDFs</option>
          </select>
        </label>
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
<?php if ($search !== '' || $type !== ''): ?>
        <a class="btn btn-outline btn-sm" href="media.php">Clear</a>
<?php endif; ?>
      </form>

<?php if (!$rows): ?>
      <div class="empty-state">
        <span class="empty-state-title">Nothing in the library yet</span>
<?php if ($search !== '' || $type !== ''): ?>
        No files match that filter.
<?php else: ?>
        Upload an image or a PDF using the panel on the right. Anything you add here can be
        used on a page, on an event, or as the site logo.
<?php endif; ?>
      </div>
<?php elseif ($view === 'grid'): ?>
      <div class="pma-media-grid">
<?php foreach ($rows as $r): ?>
        <a class="pma-media-tile <?php echo $selId === (int) $r['id'] ? 'is-selected' : ''; ?>"
           href="<?php echo htmlspecialchars(pmMediaUrlFor(['id' => (int) $r['id']])); ?>">
          <span class="pma-media-thumb">
<?php if (pmMediaIsImage($r['mime'])): ?>
            <img src="<?php echo htmlspecialchars(pmMediaUrl($r['filename'], 'thumb')); ?>"
                 alt="<?php echo htmlspecialchars((string) $r['alt_text']); ?>" loading="lazy">
<?php else: ?>
            <span class="pma-media-doc">PDF</span>
<?php endif; ?>
          </span>
          <span class="pma-media-name"><?php echo htmlspecialchars($r['original_name']); ?></span>
          <span class="pma-media-meta">
<?php if (pmMediaIsImage($r['mime']) && empty($r['alt_text'])): ?>
            <span class="badge badge-orange">No alt text</span>
<?php else: ?>
            <?php echo htmlspecialchars(pmMediaHumanSize((int) $r['bytes'])); ?>
<?php endif; ?>
          </span>
        </a>
<?php endforeach; ?>
      </div>
<?php else: ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr><th>File</th><th>Type</th><th class="num">Size</th><th>Dimensions</th><th>Alt text</th><th>Added</th></tr>
          </thead>
          <tbody>
<?php foreach ($rows as $r): ?>
            <tr>
              <td><a href="<?php echo htmlspecialchars(pmMediaUrlFor(['id' => (int) $r['id']])); ?>"><?php
                  echo htmlspecialchars($r['original_name']); ?></a></td>
              <td class="text-muted"><?php echo htmlspecialchars(PM_MEDIA_TYPES[$r['mime']] ?? $r['mime']); ?></td>
              <td class="num"><?php echo htmlspecialchars(pmMediaHumanSize((int) $r['bytes'])); ?></td>
              <td class="text-muted"><?php echo $r['width'] ? (int) $r['width'] . ' x ' . (int) $r['height'] : 'n/a'; ?></td>
              <td>
<?php if (pmMediaIsImage($r['mime']) && empty($r['alt_text'])): ?>
                <span class="badge badge-orange">Missing</span>
<?php else: ?>
                <span class="text-muted"><?php echo htmlspecialchars(mb_strimwidth((string) $r['alt_text'], 0, 48, '...')); ?></span>
<?php endif; ?>
              </td>
              <td class="text-muted"><?php echo htmlspecialchars(date('j M Y', strtotime($r['created_at']))); ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </div>

  <aside>
<?php if ($canUpload): ?>
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px">Add a file</h2>
      <form method="POST" action="media.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="upload">
        <div class="form-group">
          <label for="file">Image or PDF</label>
          <input type="file" id="file" name="file" class="form-control"
                 accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" required>
          <span class="form-hint">JPG, PNG, WEBP, GIF or PDF, up to <?php echo htmlspecialchars($limitLabel); ?>.</span>
        </div>
        <div class="form-group">
          <label for="alt_text">Alt text</label>
          <input type="text" id="alt_text" name="alt_text" class="form-control" maxlength="255"
                 placeholder="What the image shows, for someone who cannot see it">
          <span class="form-hint">Asked for now rather than later. Leave empty only for a decorative image.</span>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Upload</button>
      </form>
    </div>
<?php endif; ?>

<?php if ($selected): ?>
    <div class="card">
      <h2 class="card-title" style="margin-bottom:12px">File details</h2>

<?php if (pmMediaIsImage($selected['mime'])): ?>
      <div class="pma-focal" data-focal
           style="background-image:url('<?php echo htmlspecialchars(pmMediaUrl($selected['filename'], 'medium')); ?>')">
        <span class="pma-focal-dot" data-focal-dot
              style="left:<?php echo (int) $selected['focal_x']; ?>%;top:<?php echo (int) $selected['focal_y']; ?>%"></span>
      </div>
      <p class="form-hint" style="margin:8px 0 14px">
        Click the image to set the focal point. Smaller sizes are cropped around it, which is what
        stops a banner cropping through somebody's face.
      </p>
<?php endif; ?>

      <dl class="pma-facts">
        <div><dt>File name</dt><dd><?php echo htmlspecialchars($selected['original_name']); ?></dd></div>
        <div><dt>Type</dt><dd><?php echo htmlspecialchars($selected['mime']); ?></dd></div>
        <div><dt>Size</dt><dd><?php echo htmlspecialchars(pmMediaHumanSize((int) $selected['bytes'])); ?></dd></div>
<?php if ($selected['width']): ?>
        <div><dt>Dimensions</dt><dd><?php echo (int) $selected['width'] . ' x ' . (int) $selected['height']; ?></dd></div>
<?php endif; ?>
        <div><dt>Added by</dt><dd><?php echo htmlspecialchars((string) $selected['uploaded_by']); ?></dd></div>
      </dl>

      <div class="form-group" style="margin-top:14px">
        <label>Address</label>
        <input type="text" class="form-control" readonly
               value="<?php echo htmlspecialchars(pmMediaUrl($selected['filename'])); ?>"
               onfocus="this.select()">
      </div>

      <div class="form-group">
        <label>Where it is used</label>
<?php if (!$selUsage): ?>
        <span class="form-hint">Not used anywhere yet. This is the question to ask before deleting anything.</span>
<?php else: ?>
        <ul style="margin:0;padding-left:18px;font-size:12.5px;line-height:1.7">
<?php foreach ($selUsage as $u): ?>
          <li><?php echo htmlspecialchars($u['label'] ?: ($u['entity_type'] . ' ' . $u['entity_id'])); ?></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </div>

<?php if ($canEdit): ?>
      <form method="POST" action="media.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="details">
        <input type="hidden" name="id" value="<?php echo (int) $selected['id']; ?>">
        <input type="hidden" name="focal_x" data-focal-x value="<?php echo (int) $selected['focal_x']; ?>">
        <input type="hidden" name="focal_y" data-focal-y value="<?php echo (int) $selected['focal_y']; ?>">
        <div class="form-group">
          <label for="d_alt">Alt text</label>
          <input type="text" id="d_alt" name="alt_text" class="form-control" maxlength="255"
                 value="<?php echo htmlspecialchars((string) $selected['alt_text']); ?>">
        </div>
        <div class="form-group">
          <label for="d_cap">Caption</label>
          <input type="text" id="d_cap" name="caption" class="form-control" maxlength="255"
                 value="<?php echo htmlspecialchars((string) $selected['caption']); ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save details</button>
      </form>
<?php endif; ?>

<?php if ($canDelete): ?>
      <form method="POST" action="media.php" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--pma-border)">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?php echo (int) $selected['id']; ?>">
<?php if ($selUsage): ?>
        <label class="check-group-sub" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:10px">
          <input type="checkbox" name="confirm_in_use" value="1" required>
          <span>This file is used in <?php echo count($selUsage); ?> place(s). Delete it anyway and those
                places will show nothing.</span>
        </label>
<?php endif; ?>
        <button type="submit" class="btn btn-danger btn-sm"
                data-confirm="Move this file to the trash? You can restore it for 30 days.">Delete this file</button>
      </form>
<?php endif; ?>
    </div>
<?php endif; ?>
  </aside>
</div>

<?php require_once 'footer.php'; ?>
