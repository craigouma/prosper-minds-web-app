<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/media.php';
requireAdminAuth();
requirePermission('media', 'view');

$pageTitle  = 'Banner library';
$activePage = 'banners';

ensureMediaSchema($pdo);

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } else {
        requirePermission('media', 'upload');
        $eventId = (int) ($_POST['event_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT title FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $title = (string) ($stmt->fetchColumn() ?: '');

        if ($title === '') {
            $error = 'That event no longer exists.';
        } else {
            $up = pmMediaStore($pdo, $_FILES['banner'] ?? [], (string) ($_SESSION['admin_username'] ?? 'unknown'),
                               'Promotional banner for ' . $title);

            if ($up['ok']) {
                $file = pmMediaFind($pdo, (int) $up['id']);
                $pdo->prepare('UPDATE events SET image_path = ? WHERE id = ?')
                    ->execute([ltrim(pmMediaUrl($file['filename']), '/'), $eventId]);
                pmMediaRecordUsage($pdo, (int) $up['id'], 'event', (string) $eventId, 'Banner for ' . $title);
                pmAudit($pdo, 'banner_replace', 'Replaced the banner for "' . $title . '"', 'events', $eventId);
                $notice = 'Banner replaced. It is live on the site now.';
            } else {
                $error = $up['error'];
            }
        }
    }
}

$events = [];
try {
    $events = $pdo->query('SELECT id, title, date_display, location, image_path, is_active
                             FROM events ORDER BY is_active DESC, sort_order, id')->fetchAll();
} catch (Throwable $e) {
    $error = 'The events could not be read.';
}

$csrfToken = generateCsrfToken();
$canUpload = hasPermission('media', 'upload');
$origin    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
           . '://' . ($_SERVER['HTTP_HOST'] ?? 'prosper-minds.com');

require_once 'header.php';
?>

<?php if ($notice !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error !== ''):  ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2 class="card-title">Banner library</h2>
      <p class="card-subtitle">One banner per event, used on the site and for LinkedIn and partner mailings.
         Replacing one here changes it everywhere at once.</p>
    </div>
  </div>

<?php if (!$events): ?>
  <div class="empty-state">
    <span class="empty-state-title">No events yet</span>
    Banners belong to events, so create an event first.
  </div>
<?php else: ?>
  <div class="pma-banners">
<?php foreach ($events as $ev):
        $path = trim((string) $ev['image_path']);
        $url  = $path !== '' ? '/' . ltrim($path, '/') : '';
?>
    <div class="pma-banner">
      <div class="pma-banner-frame">
<?php if ($url !== ''): ?>
        <img src="<?php echo htmlspecialchars($url); ?>" alt="" loading="lazy">
<?php else: ?>
        <span class="text-muted">No banner set</span>
<?php endif; ?>
      </div>

      <div class="pma-banner-body">
        <h3 class="card-title" style="font-size:12px"><?php echo htmlspecialchars($ev['title']); ?></h3>
        <p class="card-subtitle"><?php echo htmlspecialchars((string) $ev['date_display']); ?>,
           <?php echo htmlspecialchars((string) $ev['location']); ?></p>
        <p style="margin:8px 0 0">
          <span class="badge <?php echo $ev['is_active'] ? 'badge-green' : 'badge-gray'; ?>">
            <?php echo $ev['is_active'] ? 'On the site' : 'Retired'; ?>
          </span>
        </p>

<?php if ($url !== ''): ?>
        <div class="pma-toolbar" style="border:0;padding:10px 0 0;gap:6px">
          <a class="btn btn-outline btn-sm" href="<?php echo htmlspecialchars($url); ?>" download>Download</a>
          <button type="button" class="btn btn-outline btn-sm"
                  data-copy="<?php echo htmlspecialchars($origin . $url); ?>">Copy link</button>
        </div>
<?php endif; ?>

<?php if ($canUpload): ?>
        <form method="POST" action="banners.php" enctype="multipart/form-data" style="margin-top:10px">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="event_id" value="<?php echo (int) $ev['id']; ?>">
          <div class="form-group" style="margin-bottom:8px">
            <label class="pma-vh" for="b<?php echo (int) $ev['id']; ?>">Replace this banner</label>
            <input type="file" id="b<?php echo (int) $ev['id']; ?>" name="banner" class="form-control"
                   accept="image/jpeg,image/png,image/webp" required>
          </div>
          <button type="submit" class="btn btn-outline btn-sm"><?php
            echo $url !== '' ? 'Replace banner' : 'Add a banner'; ?></button>
        </form>
<?php endif; ?>
      </div>
    </div>
<?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
