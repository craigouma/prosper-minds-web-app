<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
requireAdminAuth();

$pageTitle  = 'Events';
$activePage = 'events';

$msg   = '';
$error = '';

// ── Toggle active ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $toggleId = (int)$_POST['toggle_id'];
        $pdo->prepare("UPDATE events SET is_active = 1 - is_active WHERE id = ?")->execute([$toggleId]);
    }
    header('Location: events.php');
    exit;
}

// ── Delete event (super_admin only) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!isSuper()) {
        $_SESSION['perm_error'] = "Only Super Admins can delete events.";
        header('Location: events.php');
        exit;
    }
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        require_once '../includes/trash.php';
        $doomedId = (int) $_POST['delete_id'];
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$doomedId]);
        $doomed = $stmt->fetch();

        if ($doomed && pmTrashPut($pdo, 'event', $doomedId, (string) $doomed['title'], $doomed,
                                  (string) ($_SESSION['admin_username'] ?? 'unknown'), 'Programme')) {
            $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$doomedId]);
        }
    }
    header('Location: events.php?msg=deleted');
    exit;
}

// ── Add / Edit event ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $editId     = (int)($_POST['edit_id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $tagline    = trim($_POST['tagline'] ?? '');
        $dateDisp   = trim($_POST['date_display'] ?? '');
        $startDate  = $_POST['event_start_date'] ?: null;
        $location   = trim($_POST['location'] ?? '');
        $price      = trim($_POST['price'] ?? 'USD 599 Per Delegate');
        $eb1pct     = (int)($_POST['eb1_pct'] ?? 20);
        $eb1date    = $_POST['eb1_date'] ?: null;
        $eb2pct     = (int)($_POST['eb2_pct'] ?? 15);
        $eb2date    = $_POST['eb2_date'] ?: null;
        $eb3pct     = (int)($_POST['eb3_pct'] ?? 10);
        $eb3date    = $_POST['eb3_date'] ?: null;
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);
        $isActive   = isset($_POST['is_active']) ? 1 : 0;

        $focusTags    = trim($_POST['focus_tags'] ?? '');
        $whyIntro     = trim($_POST['why_intro'] ?? '');
        $masterPoints = trim($_POST['master_points'] ?? '');
        $audience     = trim($_POST['audience'] ?? '');
        $vvipPrice    = trim($_POST['vvip_price'] ?? '');
        $vvipSeats    = trim($_POST['vvip_seats_note'] ?? '');
        $vvipPerks    = trim($_POST['vvip_perks'] ?? '');
        $vipPrice     = trim($_POST['vip_price'] ?? '');
        $vipPerks     = trim($_POST['vip_perks'] ?? '');
        $regularPrice = trim($_POST['regular_price'] ?? '');
        $regularPerks = trim($_POST['regular_perks'] ?? '');

        $agendaDays = [];
        for ($d = 1; $d <= 5; $d++) {
            $dTitle = trim($_POST["agenda_title_$d"] ?? '');
            $dDesc  = trim($_POST["agenda_desc_$d"] ?? '');
            if ($dTitle || $dDesc) {
                $agendaDays[] = ['day' => $d, 'title' => $dTitle, 'desc' => $dDesc];
            }
        }
        $agendaJson = $agendaDays ? json_encode($agendaDays, JSON_UNESCAPED_UNICODE) : null;

        if (!$title || !$dateDisp || !$location) {
            $error = 'Title, date display, and location are required.';
        } else {
            // Handle image upload
            $imagePath = trim($_POST['existing_image'] ?? '');
            
            // Handle PDF upload
            $pdfPath = trim($_POST['existing_pdf'] ?? '');
            if (!empty($_FILES['event_pdf']['name'])) {
                $uploadPdfDir = '../assets/pdfs/';
                if (!is_dir($uploadPdfDir)) {
                    mkdir($uploadPdfDir, 0755, true);
                }
                $extPdf = strtolower(pathinfo($_FILES['event_pdf']['name'], PATHINFO_EXTENSION));
                if ($extPdf !== 'pdf') {
                    $error = 'Only PDF files are allowed for brochures.';
                } else {
                    $fnamePdf = 'brochure_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    if (move_uploaded_file($_FILES['event_pdf']['tmp_name'], $uploadPdfDir . $fnamePdf)) {
                        $pdfPath = 'assets/pdfs/' . $fnamePdf;
                    }
                }
            }
            if (!empty($_FILES['event_image']['name'])) {
                $uploadDir = '../assets/images/events/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext      = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
                $allowed  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (!in_array($ext, $allowed)) {
                    $error = 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.';
                } elseif ($_FILES['event_image']['size'] > 5 * 1024 * 1024) {
                    $error = 'Image must be under 5 MB.';
                } else {
                    $fname     = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['event_image']['tmp_name'], $uploadDir . $fname)) {
                        $imagePath = 'assets/images/events/' . $fname;
                    }
                }
            }

            if (!$error) {
                if ($editId > 0) {
                    $pdo->prepare(
                        "UPDATE events SET title=?, tagline=?, date_display=?, event_start_date=?, location=?,
                         price=?, early_bird_1_pct=?, early_bird_1_date=?, early_bird_2_pct=?, early_bird_2_date=?,
                         early_bird_3_pct=?, early_bird_3_date=?, image_path=?, pdf_file=?, is_active=?, sort_order=?,
                         focus_tags=?, why_intro=?, master_points=?, agenda=?, audience=?,
                         vvip_price=?, vvip_seats_note=?, vvip_perks=?, vip_price=?, vip_perks=?,
                         regular_price=?, regular_perks=?
                         WHERE id=?"
                    )->execute([
                        $title, $tagline, $dateDisp, $startDate, $location,
                        $price, $eb1pct, $eb1date, $eb2pct, $eb2date,
                        $eb3pct, $eb3date, $imagePath, $pdfPath, $isActive, $sortOrder,
                        $focusTags, $whyIntro, $masterPoints, $agendaJson, $audience,
                        $vvipPrice, $vvipSeats, $vvipPerks, $vipPrice, $vipPerks,
                        $regularPrice, $regularPerks,
                        $editId,
                    ]);
                    header('Location: events.php?msg=updated');
                } else {
                    $pdo->prepare(
                        "INSERT INTO events
                         (title, tagline, date_display, event_start_date, location, price,
                          early_bird_1_pct, early_bird_1_date, early_bird_2_pct, early_bird_2_date,
                          early_bird_3_pct, early_bird_3_date, image_path, pdf_file, is_active, sort_order,
                          focus_tags, why_intro, master_points, agenda, audience,
                          vvip_price, vvip_seats_note, vvip_perks, vip_price, vip_perks,
                          regular_price, regular_perks)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                    )->execute([
                        $title, $tagline, $dateDisp, $startDate, $location,
                        $price, $eb1pct, $eb1date, $eb2pct, $eb2date,
                        $eb3pct, $eb3date, $imagePath, $pdfPath, $isActive, $sortOrder,
                        $focusTags, $whyIntro, $masterPoints, $agendaJson, $audience,
                        $vvipPrice, $vvipSeats, $vvipPerks, $vipPrice, $vipPerks,
                        $regularPrice, $regularPerks,
                    ]);
                    header('Location: events.php?msg=added');
                }
                exit;
            }
        }
    }
}

// ── Load event for editing ─────────────────────────────────
$editEvent = null;
if (isset($_GET['edit'])) {
    $stmt2 = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt2->execute([(int)$_GET['edit']]);
    $editEvent = $stmt2->fetch() ?: null;
}

// Flash messages
if (isset($_GET['msg'])) {
    $msgs = ['added' => 'Event created.', 'updated' => 'Event updated.', 'deleted' => 'Event deleted.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// ── Load all events ────────────────────────────────────────
$events = $pdo->query("SELECT * FROM events ORDER BY sort_order, id")->fetchAll();

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

    <!-- Events list -->
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <h2 class="card-title">All events</h2>
                <div class="card-subtitle"><?php echo count($events); ?> total</div>
            </div>
            <a href="events.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add New
            </a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date / Location</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($events): ?>
                    <?php foreach ($events as $ev): ?>
                    <tr>
                        <td>
                            <?php if ($ev['image_path']): ?>
                                <img src="../<?php echo htmlspecialchars($ev['image_path']); ?>"
                                     style="width:50px;height:36px;object-fit:cover;border-radius:2px;margin-right:10px;vertical-align:middle;">
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($ev['title']); ?></strong><br>
                            <span style="font-size:12px;color:#6b6b6b;"><?php echo htmlspecialchars($ev['tagline']); ?></span>
                        </td>
                        <td>
                            <span style="font-size:13px;"><?php echo htmlspecialchars($ev['date_display']); ?></span><br>
                            <span style="font-size:12px;color:#6b6b6b;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ev['location']); ?></span>
                        </td>
                        <td style="white-space:nowrap;"><?php echo htmlspecialchars($ev['price']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="toggle_id" value="<?php echo $ev['id']; ?>">
                                <button type="submit" class="badge <?php echo $ev['is_active'] ? 'badge-green' : 'badge-gray'; ?>"
                                        style="cursor:pointer;border:none;font-size:12px;padding:4px 10px;">
                                    <?php echo $ev['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <a href="events.php?edit=<?php echo $ev['id']; ?>"
                               class="btn btn-outline btn-sm">Edit</a>
                            <?php if (isSuper()): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Move this event to the trash? Registrations are kept, and you can restore it for 30 days.');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="delete_id" value="<?php echo $ev['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">
                        <div class="empty-state"><i class="fas fa-calendar-times"></i>No events yet. Create one →</div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add / Edit form -->
    <div class="card">
        <div class="card-title" style="margin-bottom:4px;">
            <?php echo $editEvent ? 'Edit event' : 'New event'; ?>
        </div>
        <div class="card-subtitle" style="margin-bottom:20px;">
            <?php echo $editEvent ? 'Update the event details below' : 'Fill in the details below'; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" action="events.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="save_event" value="1">
            <input type="hidden" name="edit_id" value="<?php echo $editEvent ? $editEvent['id'] : 0; ?>">
            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($editEvent['image_path'] ?? ''); ?>">
            <input type="hidden" name="existing_pdf" value="<?php echo htmlspecialchars($editEvent['pdf_file'] ?? ''); ?>">

            <div class="form-group">
                <label>Event Title *</label>
                <input type="text" name="title" class="form-control" required
                       value="<?php echo htmlspecialchars($editEvent['title'] ?? $_POST['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Tagline</label>
                <input type="text" name="tagline" class="form-control"
                       value="<?php echo htmlspecialchars($editEvent['tagline'] ?? $_POST['tagline'] ?? ''); ?>"
                       placeholder="Short event description">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Date Display *</label>
                    <input type="text" name="date_display" class="form-control" required
                           placeholder="e.g. 19–23 October 2026"
                           value="<?php echo htmlspecialchars($editEvent['date_display'] ?? $_POST['date_display'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Start Date <span class="text-muted">(for sorting)</span></label>
                    <input type="date" name="event_start_date" class="form-control"
                           value="<?php echo htmlspecialchars($editEvent['event_start_date'] ?? $_POST['event_start_date'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Location *</label>
                <input type="text" name="location" class="form-control" required
                       placeholder="City, Country"
                       value="<?php echo htmlspecialchars($editEvent['location'] ?? $_POST['location'] ?? ''); ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Price</label>
                    <input type="text" name="price" class="form-control"
                           value="<?php echo htmlspecialchars($editEvent['price'] ?? $_POST['price'] ?? 'USD 599 Per Delegate'); ?>">
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" min="0"
                           value="<?php echo (int)($editEvent['sort_order'] ?? $_POST['sort_order'] ?? 0); ?>">
                </div>
            </div>

            <hr class="divider">
            <div class="card-subtitle" style="margin-bottom:12px;font-weight:600;color:var(--gray-800);">
                <i class="fas fa-align-left" style="color:var(--primary);margin-right:6px;"></i>Brochure Content <span class="text-muted" style="font-weight:400;">(optional — shown on the event page when filled in)</span>
            </div>

            <div class="form-group">
                <label>Focus Tags</label>
                <input type="text" name="focus_tags" class="form-control"
                       placeholder="e.g. AI & Automation · PFM Leadership · IPSAS"
                       value="<?php echo htmlspecialchars($editEvent['focus_tags'] ?? $_POST['focus_tags'] ?? ''); ?>">
                <div class="form-hint">Separate tags with a middle dot (·)</div>
            </div>

            <div class="form-group">
                <label>Why This Programme, Why Now</label>
                <textarea name="why_intro" class="form-control" rows="5"
                          placeholder="One paragraph per line"><?php echo htmlspecialchars($editEvent['why_intro'] ?? $_POST['why_intro'] ?? ''); ?></textarea>
                <div class="form-hint">One paragraph per line</div>
            </div>

            <div class="form-group">
                <label>What You Will Master</label>
                <textarea name="master_points" class="form-control" rows="5"
                          placeholder="One point per line"><?php echo htmlspecialchars($editEvent['master_points'] ?? $_POST['master_points'] ?? ''); ?></textarea>
                <div class="form-hint">One point per line</div>
            </div>

            <div class="form-group">
                <label>Who Should Attend</label>
                <textarea name="audience" class="form-control" rows="3"
                          placeholder="One role per line"><?php echo htmlspecialchars($editEvent['audience'] ?? $_POST['audience'] ?? ''); ?></textarea>
                <div class="form-hint">One role per line</div>
            </div>

            <div class="card-subtitle" style="margin:16px 0 10px;font-weight:600;color:var(--gray-800);">
                <i class="fas fa-calendar-week" style="color:var(--primary);margin-right:6px;"></i>Five-Day Programme
            </div>
            <?php
            $agendaEdit = [];
            if (!empty($editEvent['agenda'])) {
                $agendaEdit = json_decode($editEvent['agenda'], true) ?: [];
            }
            for ($d = 1; $d <= 5; $d++):
                $dayData = null;
                foreach ($agendaEdit as $ad) {
                    if ((int)($ad['day'] ?? 0) === $d) { $dayData = $ad; break; }
                }
            ?>
            <div class="form-grid" style="margin-bottom:10px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Day <?php echo $d; ?> Title</label>
                    <input type="text" name="agenda_title_<?php echo $d; ?>" class="form-control"
                           value="<?php echo htmlspecialchars($dayData['title'] ?? ''); ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Day <?php echo $d; ?> Description</label>
                    <input type="text" name="agenda_desc_<?php echo $d; ?>" class="form-control"
                           value="<?php echo htmlspecialchars($dayData['desc'] ?? ''); ?>">
                </div>
            </div>
            <?php endfor; ?>

            <div class="card-subtitle" style="margin:16px 0 10px;font-weight:600;color:var(--gray-800);">
                <i class="fas fa-crown" style="color:var(--primary);margin-right:6px;"></i>Delegate Tiers
            </div>
            <div class="form-grid" style="margin-bottom:10px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>VVIP Price</label>
                    <input type="text" name="vvip_price" class="form-control"
                           value="<?php echo htmlspecialchars($editEvent['vvip_price'] ?? $_POST['vvip_price'] ?? 'USD 2,899'); ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>VVIP Seats Note</label>
                    <input type="text" name="vvip_seats_note" class="form-control"
                           value="<?php echo htmlspecialchars($editEvent['vvip_seats_note'] ?? $_POST['vvip_seats_note'] ?? 'Limited to 15 seats'); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>VVIP Perks</label>
                <textarea name="vvip_perks" class="form-control" rows="3"
                          placeholder="One perk per line"><?php echo htmlspecialchars($editEvent['vvip_perks'] ?? $_POST['vvip_perks'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>VIP Price</label>
                <input type="text" name="vip_price" class="form-control"
                       value="<?php echo htmlspecialchars($editEvent['vip_price'] ?? $_POST['vip_price'] ?? 'USD 1,999'); ?>">
            </div>
            <div class="form-group">
                <label>VIP Perks</label>
                <textarea name="vip_perks" class="form-control" rows="3"
                          placeholder="One perk per line"><?php echo htmlspecialchars($editEvent['vip_perks'] ?? $_POST['vip_perks'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Regular Price</label>
                <input type="text" name="regular_price" class="form-control"
                       value="<?php echo htmlspecialchars($editEvent['regular_price'] ?? $_POST['regular_price'] ?? 'USD 599'); ?>">
            </div>
            <div class="form-group">
                <label>Regular Perks</label>
                <textarea name="regular_perks" class="form-control" rows="3"
                          placeholder="One perk per line"><?php echo htmlspecialchars($editEvent['regular_perks'] ?? $_POST['regular_perks'] ?? ''); ?></textarea>
            </div>

            <hr class="divider">
            <div class="card-subtitle" style="margin-bottom:12px;font-weight:600;color:var(--gray-800);">
                <i class="fas fa-tags" style="color:var(--primary);margin-right:6px;"></i>Early Bird Discounts
            </div>

            <?php
            $ebs = [
                [1, $editEvent['early_bird_1_pct'] ?? 20, $editEvent['early_bird_1_date'] ?? ''],
                [2, $editEvent['early_bird_2_pct'] ?? 15, $editEvent['early_bird_2_date'] ?? ''],
                [3, $editEvent['early_bird_3_pct'] ?? 10, $editEvent['early_bird_3_date'] ?? ''],
            ];
            foreach ($ebs as [$n, $pct, $date]): ?>
            <div class="form-grid" style="margin-bottom:10px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Tier <?php echo $n; ?> Discount %</label>
                    <input type="number" name="eb<?php echo $n; ?>_pct" class="form-control"
                           min="0" max="100" value="<?php echo (int)($pct); ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Tier <?php echo $n; ?> Deadline</label>
                    <input type="date" name="eb<?php echo $n; ?>_date" class="form-control"
                           value="<?php echo htmlspecialchars($date ?? ''); ?>">
                </div>
            </div>
            <?php endforeach; ?>

            <hr class="divider">

            <div class="form-group">
                <label>Event Image</label>
                <input type="file" name="event_image" class="form-control" accept="image/*"
                       onchange="previewImg(this)">
                <div class="form-hint">JPG, PNG, WEBP – max 5 MB</div>
                <?php if (!empty($editEvent['image_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($editEvent['image_path']); ?>"
                         style="width:100%;max-height:120px;object-fit:cover;border-radius:2px;margin-top:10px;border:1.5px solid var(--gray-200);">
                    <div class="form-hint">Current image — upload new to replace</div>
                <?php endif; ?>
                <img id="imgPreview" class="img-preview">
            </div>

            <div class="form-group">
                <label>Event Brochure (PDF)</label>
                <input type="file" name="event_pdf" class="form-control" accept="application/pdf">
                <div class="form-hint">Upload a detailed PDF for this event.</div>
                <?php if (!empty($editEvent['pdf_file'])): ?>
                    <div style="margin-top: 10px;">
                        <a href="../<?php echo htmlspecialchars($editEvent['pdf_file']); ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-file-pdf"></i> View Current PDF</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="display:flex;align-items:center;gap:8px;margin-bottom:20px;">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       <?php echo (!$editEvent || $editEvent['is_active']) ? 'checked' : ''; ?>>
                <label for="is_active" style="margin:0;cursor:pointer;">Show on website (active)</label>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">
                    <i class="fas fa-save"></i> <?php echo $editEvent ? 'Save Changes' : 'Create Event'; ?>
                </button>
                <?php if ($editEvent): ?>
                    <a href="events.php" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

</div>

<script>
function previewImg(input) {
    const preview = document.getElementById('imgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include 'footer.php'; ?>
