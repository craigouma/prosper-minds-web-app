<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/events.php'; // For pmSlugify().
require_once '../includes/webinars.php';
requireAdminAuth();
requirePermission('events', 'view');

$pageTitle  = 'Webinars';
$activePage = 'webinars';

ensureWebinarSchema($pdo);

$msg   = '';
$error = '';

// ── Add / Edit session ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    requirePermission('events', 'edit');

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $editId         = (int) ($_POST['edit_id'] ?? 0);
        $sessionNumber  = (int) ($_POST['session_number'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $topic          = trim($_POST['topic'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $bestFor        = trim($_POST['best_for'] ?? '');
        $sessionDate    = $_POST['session_date'] ?: null;
        $timeLabel      = trim($_POST['time_label'] ?? '') ?: '12:00 EAT';
        $zoomLink       = trim($_POST['zoom_link'] ?? '');
        $isActive       = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder      = $sessionNumber;

        if (!$title || !$sessionDate) {
            $error = 'Title and session date are required.';
        } else {
            $slug = pmSlugify($title !== '' ? 'pfm-insight-live-session-' . $sessionNumber . '-' . $title : $title);

            if ($editId > 0) {
                $pdo->prepare(
                    'UPDATE webinar_sessions SET session_number=?, title=?, topic=?, description=?, best_for=?,
                       session_date=?, time_label=?, zoom_link=?, is_active=?, sort_order=?
                     WHERE id=?'
                )->execute([
                    $sessionNumber, $title, $topic, $description, $bestFor,
                    $sessionDate, $timeLabel, $zoomLink, $isActive, $sortOrder,
                    $editId,
                ]);
                pmAudit($pdo, 'webinar_session_update', 'Updated webinar session "' . $title . '"', 'webinar_sessions', $editId);
                header('Location: webinars.php?msg=updated');
            } else {
                // A slug is set once, at creation, the same rule events use: an
                // edited title never moves an address someone already has.
                $stmt = $pdo->prepare(
                    'INSERT INTO webinar_sessions
                       (session_number, title, topic, description, best_for, slug,
                        session_date, time_label, zoom_link, is_active, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $sessionNumber, $title, $topic, $description, $bestFor, $slug,
                    $sessionDate, $timeLabel, $zoomLink, $isActive, $sortOrder,
                ]);
                pmAudit($pdo, 'webinar_session_create', 'Added webinar session "' . $title . '"', 'webinar_sessions', (int) $pdo->lastInsertId());
                header('Location: webinars.php?msg=added');
            }
            exit;
        }
    }
}

// ── Toggle active ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    requirePermission('events', 'edit');
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $toggleId = (int) $_POST['toggle_id'];
        $pdo->prepare('UPDATE webinar_sessions SET is_active = 1 - is_active WHERE id = ?')->execute([$toggleId]);
    }
    header('Location: webinars.php');
    exit;
}

// ── Load for editing ─────────────────────────────────────────
$editSession = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM webinar_sessions WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editSession = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (isset($_GET['msg'])) {
    $msg = ['added' => 'Session added.', 'updated' => 'Session updated.'][$_GET['msg']] ?? '';
}

$sessions = pmWebinarSessions($pdo, false);
$registrations = pmWebinarRegistrations($pdo);
$sessionsById = [];
foreach ($sessions as $s) {
    $sessionsById[(int) $s['id']] = $s['title'];
}

$csrfToken = generateCsrfToken();

include 'header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="pma-split">

    <div class="table-card">
        <div class="table-card-header">
            <div>
                <h2 class="card-title">PFM Insight Live sessions</h2>
                <div class="card-subtitle"><?php echo count($sessions); ?> total</div>
            </div>
            <a href="webinars.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add New</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Date</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($sessions): ?>
                    <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td>
                            <strong>Session <?php echo str_pad((string) $s['session_number'], 2, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($s['title']); ?></strong><br>
                            <span style="font-size:12px;color:#6b6b6b;"><?php echo htmlspecialchars($s['topic']); ?></span>
                        </td>
                        <td style="white-space:nowrap;"><?php echo htmlspecialchars(date('j M Y', strtotime($s['session_date']))); ?></td>
                        <td><?php echo pmWebinarRegistrationCount($pdo, (int) $s['id']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="toggle_id" value="<?php echo (int) $s['id']; ?>">
                                <button type="submit" class="badge <?php echo $s['is_active'] ? 'badge-green' : 'badge-gray'; ?>"
                                        style="cursor:pointer;border:none;font-size:12px;padding:4px 10px;">
                                    <?php echo $s['is_active'] ? 'Live' : 'Hidden'; ?>
                                </button>
                            </form>
                        </td>
                        <td><a href="webinars.php?edit=<?php echo (int) $s['id']; ?>" class="btn btn-outline btn-sm">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-video"></i>No sessions yet. Add one &rarr;</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-title" style="margin-bottom:4px;"><?php echo $editSession ? 'Edit session' : 'New session'; ?></div>
        <div class="card-subtitle" style="margin-bottom:20px;">Zoom link can be the same one every session uses, or its own.</div>

        <form method="POST" action="webinars.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="save_session" value="1">
            <input type="hidden" name="edit_id" value="<?php echo $editSession['id'] ?? 0; ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>Session number *</label>
                    <input type="number" name="session_number" class="form-control" required min="1"
                           value="<?php echo htmlspecialchars((string) ($editSession['session_number'] ?? '')); ?>">
                </div>
                <div class="form-group">
                    <label>Session date *</label>
                    <input type="date" name="session_date" class="form-control" required
                           value="<?php echo htmlspecialchars((string) ($editSession['session_date'] ?? '')); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required
                       value="<?php echo htmlspecialchars((string) ($editSession['title'] ?? '')); ?>">
            </div>

            <div class="form-group">
                <label>Topic</label>
                <input type="text" name="topic" class="form-control"
                       value="<?php echo htmlspecialchars((string) ($editSession['topic'] ?? '')); ?>">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($editSession['description'] ?? '')); ?></textarea>
            </div>

            <div class="form-group">
                <label>Best for</label>
                <input type="text" name="best_for" class="form-control" placeholder="e.g. Budget and finance officers"
                       value="<?php echo htmlspecialchars((string) ($editSession['best_for'] ?? '')); ?>">
            </div>

            <div class="form-group">
                <label>Time label</label>
                <input type="text" name="time_label" class="form-control" placeholder="12:00 EAT"
                       value="<?php echo htmlspecialchars((string) ($editSession['time_label'] ?? '12:00 EAT')); ?>">
            </div>

            <div class="form-group">
                <label>Zoom link</label>
                <input type="url" name="zoom_link" class="form-control" placeholder="https://us02web.zoom.us/j/..."
                       value="<?php echo htmlspecialchars((string) ($editSession['zoom_link'] ?? '')); ?>">
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($editSession['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    Live on the public page
                </label>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo $editSession ? 'Save changes' : 'Add session'; ?></button>
            <?php if ($editSession): ?><a href="webinars.php" class="btn btn-outline">Cancel</a><?php endif; ?>
        </form>
    </div>

</div>

<div class="table-card" style="margin-top:24px;">
    <div class="table-card-header">
        <div>
            <h2 class="card-title">Registrations</h2>
            <div class="card-subtitle"><?php echo count($registrations); ?> total</div>
        </div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Institution</th>
                    <th>Session</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($registrations): ?>
                <?php foreach ($registrations as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                    <td><?php echo htmlspecialchars($r['email']); ?></td>
                    <td><?php echo htmlspecialchars($r['organization'] ?: '—'); ?></td>
                    <td><?php echo (int) $r['session_id'] === 0 ? '<span class="badge badge-green">Whole series</span>' : htmlspecialchars($sessionsById[(int) $r['session_id']] ?? 'Session ' . $r['session_id']); ?></td>
                    <td style="white-space:nowrap;font-size:12px;color:#6b6b6b;">
                        <?php echo htmlspecialchars(date('j M Y', strtotime($r['created_at']))); ?>
                        <?php if ($r['unsubscribed_at']): ?><br><span class="badge badge-gray">Unsubscribed</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5"><div class="empty-state"><i class="fas fa-user-check"></i>No registrations yet.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
