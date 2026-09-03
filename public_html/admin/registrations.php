<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/invoice.php';
requireAdminAuth();

ensureRegistrationInvoiceSchema($pdo);

$pageTitle  = 'Registrations';
$activePage = 'registrations';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrations_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID', 'Date', 'First Name', 'Last Name', 'Email', 'Phone',
        'Organization', 'Country', 'Address', 'Attendee Count', 'Invoice Number',
        'Invoice Total', 'Gender', 'Meal Preference', 'Future Topics', 'Event', 'Consent'
    ]);
    $rows = $pdo->query("SELECT * FROM event_registrations ORDER BY id DESC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['created_at'],
            $r['first_name'],
            $r['last_name'],
            $r['email'],
            $r['phone'],
            $r['organization'],
            $r['country'] ?? '',
            $r['address'] ?? '',
            $r['attendee_count'] ?? 1,
            $r['invoice_number'] ?? '',
            trim(($r['currency_code'] ?? 'USD') . ' ' . number_format((float) ($r['total_amount'] ?? 0), 2)),
            $r['gender'],
            $r['meal_preference'],
            $r['future_topics'],
            $r['event_name'],
            $r['consent'] ? 'Yes' : 'No',
        ]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!hasPermission('registrations', 'delete')) {
        $_SESSION['perm_error'] = "You don't have permission to delete registrations.";
        header('Location: registrations.php');
        exit;
    }
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $pdo->prepare("DELETE FROM event_registrations WHERE id = ?")
            ->execute([(int) $_POST['delete_id']]);
    }
    header('Location: registrations.php');
    exit;
}

$where  = '1=1';
$params = [];

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR organization LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$filterEvent = trim($_GET['event'] ?? '');
if ($filterEvent !== '') {
    $where .= " AND event_name = ?";
    $params[] = $filterEvent;
}

$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE $where");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM event_registrations WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$registrations = $stmt->fetchAll();

$uniqueEvents = $pdo->query(
    "SELECT DISTINCT event_name FROM event_registrations ORDER BY event_name"
)->fetchAll(PDO::FETCH_COLUMN);

$csrfToken = generateCsrfToken();

include 'header.php';
?>

<div class="card" style="padding:16px 20px;margin-bottom:20px;">
    <form method="GET" class="filter-bar" action="registrations.php">
        <div>
            <label>Search</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Name, email, organization" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div>
            <label>Event</label>
            <select name="event" class="form-control">
                <option value="">All Events</option>
                <?php foreach ($uniqueEvents as $ev): ?>
                    <option value="<?php echo htmlspecialchars($ev); ?>" <?php echo $filterEvent === $ev ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ev); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="align-self:flex-end;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="registrations.php" class="btn btn-outline">Reset</a>
        </div>
        <div style="margin-left:auto;align-self:flex-end;">
            <a href="registrations.php?export=csv" class="btn btn-outline">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-card-header">
        <div>
            <div class="card-title">
                <?php echo $total; ?> Registration<?php echo $total !== 1 ? 's' : ''; ?>
            </div>
            <?php if ($search || $filterEvent): ?>
                <div class="card-subtitle">Filtered results</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Billing Contact</th>
                    <th>Email / Phone</th>
                    <th>Company / Country</th>
                    <th>Invoice</th>
                    <th>Event</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($registrations): ?>
                <?php foreach ($registrations as $r): ?>
                <tr>
                    <td style="color:#6b6b6b;"><?php echo $r['id']; ?></td>
                    <td style="white-space:nowrap;color:#6b6b6b;font-size:12.5px;">
                        <?php echo date('M d, Y', strtotime($r['created_at'])); ?><br>
                        <?php echo date('H:i', strtotime($r['created_at'])); ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></strong>
                        <?php if (!empty($r['gender'])): ?>
                            <span class="badge badge-gray" style="margin-left:4px;"><?php echo htmlspecialchars($r['gender']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($r['email']); ?><br>
                        <span style="color:#6b6b6b;font-size:12px;"><?php echo htmlspecialchars($r['phone']); ?></span>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($r['organization'] ?: '-'); ?><br>
                        <span style="color:#6b6b6b;font-size:12px;"><?php echo htmlspecialchars($r['country'] ?: '-'); ?></span>
                    </td>
                    <td style="white-space:nowrap;">
                        <strong><?php echo htmlspecialchars($r['invoice_number'] ?: 'Pending'); ?></strong><br>
                        <span style="color:#6b6b6b;font-size:12px;">
                            <?php echo htmlspecialchars(($r['currency_code'] ?? 'USD') . ' ' . number_format((float) ($r['total_amount'] ?? 0), 2)); ?>
                        </span><br>
                        <span class="badge badge-gray" style="margin-top:4px;">
                            <?php echo (int) ($r['attendee_count'] ?? 1); ?> delegate<?php echo ((int) ($r['attendee_count'] ?? 1)) !== 1 ? 's' : ''; ?>
                        </span>
                    </td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                        title="<?php echo htmlspecialchars($r['event_name']); ?>">
                        <?php echo htmlspecialchars($r['event_name']); ?>
                    </td>
                    <td>
                        <button class="btn btn-outline btn-sm btn-icon view-btn"
                                data-id="<?php echo $r['id']; ?>"
                                data-fn="<?php echo htmlspecialchars($r['first_name']); ?>"
                                data-ln="<?php echo htmlspecialchars($r['last_name']); ?>"
                                data-email="<?php echo htmlspecialchars($r['email']); ?>"
                                data-phone="<?php echo htmlspecialchars($r['phone']); ?>"
                                data-org="<?php echo htmlspecialchars($r['organization']); ?>"
                                data-country="<?php echo htmlspecialchars($r['country'] ?? ''); ?>"
                                data-address="<?php echo htmlspecialchars($r['address'] ?? ''); ?>"
                                data-gender="<?php echo htmlspecialchars($r['gender']); ?>"
                                data-meal="<?php echo htmlspecialchars($r['meal_preference']); ?>"
                                data-topics="<?php echo htmlspecialchars($r['future_topics']); ?>"
                                data-event="<?php echo htmlspecialchars($r['event_name']); ?>"
                                data-date="<?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?>"
                                data-consent="<?php echo $r['consent'] ? 'Yes' : 'No'; ?>"
                                data-attendee-count="<?php echo (int) ($r['attendee_count'] ?? 1); ?>"
                                data-invoice-number="<?php echo htmlspecialchars($r['invoice_number'] ?? ''); ?>"
                                data-total="<?php echo htmlspecialchars(($r['currency_code'] ?? 'USD') . ' ' . number_format((float) ($r['total_amount'] ?? 0), 2)); ?>"
                                data-attendees="<?php echo htmlspecialchars($r['attendee_details'] ?? '[]', ENT_QUOTES); ?>"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline btn-sm btn-icon resend-btn"
                                data-id="<?php echo $r['id']; ?>"
                                data-email="<?php echo htmlspecialchars($r['email']); ?>"
                                title="Resend Email to <?php echo htmlspecialchars($r['email']); ?>">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        <?php
                            $invoiceFile = !empty($r['invoice_path']) ? __DIR__ . '/../' . ltrim($r['invoice_path'], '/\\') : '';
                        ?>
                        <?php if ($invoiceFile !== '' && is_file($invoiceFile)): ?>
                        <a class="btn btn-outline btn-sm btn-icon"
                           href="download-invoice.php?id=<?php echo $r['id']; ?>"
                           title="Download Invoice PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <?php else: ?>
                        <button class="btn btn-outline btn-sm btn-icon" disabled
                                title="Invoice PDF not available">
                            <i class="fas fa-file-pdf" style="opacity:.35;"></i>
                        </button>
                        <?php endif; ?>
                        <?php if (hasPermission('registrations', 'delete')): ?>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Delete this registration? This cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $r['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            No registrations found.
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div style="padding:16px 20px;display:flex;gap:6px;justify-content:center;border-top:1px solid var(--gray-200);">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event=<?php echo urlencode($filterEvent); ?>"
               class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?> btn-sm"
               style="min-width:36px;justify-content:center;">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div class="modal-backdrop" id="detailsModal">
    <div class="modal-box modal-box-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user" style="color:var(--primary);margin-right:8px;"></i>Registration Details</h3>
            <button class="modal-close-btn" id="closeModal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="detail-grid" id="detailGrid"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" id="closeModal2">Close</button>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const fields = [
            ['Registration ID', '#' + this.dataset.id],
            ['Full Name', this.dataset.fn + ' ' + this.dataset.ln],
            ['Email', this.dataset.email],
            ['Phone', this.dataset.phone],
            ['Organization', this.dataset.org || '-'],
            ['Country', this.dataset.country || '-'],
            ['Billing Address', this.dataset.address || '-'],
            ['Gender', this.dataset.gender || '-'],
            ['Meal Preference', this.dataset.meal || '-'],
            ['Event', this.dataset.event],
            ['Attendee Count', this.dataset.attendeeCount || '1'],
            ['Invoice Number', this.dataset.invoiceNumber || '-'],
            ['Invoice Total', this.dataset.total || '-'],
            ['Registered On', this.dataset.date],
            ['Consent Given', this.dataset.consent],
            ['Future Topics', this.dataset.topics || '-'],
        ];

        let attendeeSummary = '-';
        try {
            const attendees = JSON.parse(this.dataset.attendees || '[]');
            if (Array.isArray(attendees) && attendees.length) {
                attendeeSummary = attendees.map((item, index) => {
                    const parts = [
                        [item.first_name || '', item.last_name || ''].join(' ').trim(),
                        item.title || '',
                        item.email || ''
                    ].filter(Boolean);
                    return (index + 1) + '. ' + parts.join(' | ');
                }).join('\n');
            }
        } catch (error) {
            attendeeSummary = '-';
        }

        fields.push(['Delegates', attendeeSummary]);

        const grid = document.getElementById('detailGrid');
        grid.innerHTML = fields.map(([label, val]) =>
            `<div class="detail-item">
                <label>${label}</label>
                <p>${escapeHtml(String(val)).replace(/\n/g, '<br>')}</p>
             </div>`
        ).join('');

        document.getElementById('detailsModal').classList.add('open');
    });
});

// ── Resend email buttons ───────────────────────────────────────────
const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;

function showToast(msg, isSuccess) {
    let toast = document.getElementById('adminToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'adminToast';
        toast.style.cssText = 'position:fixed;bottom:28px;right:28px;z-index:9999;padding:14px 22px;border-radius:2px;font-size:14px;font-weight:600;box-shadow:0 4px 18px rgba(0,0,0,.18);transition:opacity .3s;max-width:380px;';
        document.body.appendChild(toast);
    }
    toast.style.background = isSuccess ? '#00BF63' : '#B02A17';
    toast.style.color = '#fff';
    toast.style.opacity = '1';
    toast.textContent = msg;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 4000);
}

document.querySelectorAll('.resend-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id    = this.dataset.id;
        const email = this.dataset.email;
        const orig  = this.innerHTML;

        if (!confirm('Resend welcome email + invoice to ' + email + '?')) return;

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const fd = new FormData();
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('registration_id', id);

        fetch('resend-email.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success);
            })
            .catch(() => showToast('Network error – please try again.', false))
            .finally(() => {
                this.disabled = false;
                this.innerHTML = orig;
            });
    });
});

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function closeModal() {
    document.getElementById('detailsModal').classList.remove('open');
}

document.getElementById('closeModal').addEventListener('click', closeModal);
document.getElementById('closeModal2').addEventListener('click', closeModal);
document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include 'footer.php'; ?>
