<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
requireAdminAuth();

$pageTitle  = 'Settings';
$activePage = 'settings';

$success = '';
$error   = '';

// ── Save site settings ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $keys = ['admin_email', 'company_name', 'company_color',
                 'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_secure', 'smtp_from_email'];

        $stmt = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        foreach ($keys as $key) {
            $val = trim($_POST[$key] ?? '');
            if ($key === 'smtp_pass' && $val === '') {
                continue; // Keep existing password if left blank
            }
            $stmt->execute([$key, $val]);
        }
        $success = 'Settings saved successfully.';
    }
}

// ── Change admin password ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd     = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($currentPwd, $admin['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPwd) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPwd !== $confirmPwd) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($newPwd, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?")
                ->execute([$hash, $_SESSION['admin_id']]);
            $success = 'Password changed successfully.';
        }
    }
}

// ── Reload settings ────────────────────────────────────────
$settings = [];
try {
    foreach ($pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {}

function sv(array $s, string $k, string $d = ''): string {
    return htmlspecialchars($s[$k] ?? $d);
}

$csrfToken = generateCsrfToken();

include 'header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

    <!-- Left column: Company + SMTP -->
    <form method="POST" action="settings.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="save_settings" value="1">

        <div class="card" style="margin-bottom:24px;">
            <div class="card-title" style="margin-bottom:4px;">
                <i class="fas fa-building" style="color:var(--primary);margin-right:6px;"></i>Company Settings
            </div>
            <div class="card-subtitle" style="margin-bottom:20px;">Displayed in emails and on the website</div>

            <div class="form-group">
                <label>Company Name</label>
                <input type="text" name="company_name" class="form-control"
                       value="<?php echo sv($settings, 'company_name', 'ProsperMinds'); ?>">
            </div>
            <div class="form-group">
                <label>Admin Email <span class="text-muted">(receives registration notifications)</span></label>
                <input type="email" name="admin_email" class="form-control"
                       value="<?php echo sv($settings, 'admin_email', 'info@prosper-minds.com'); ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Brand Color</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="color" id="colorPicker" name="company_color"
                           style="width:50px;padding:2px;height:38px;cursor:pointer;border:1.5px solid var(--gray-200);border-radius:6px;"
                           value="<?php echo sv($settings, 'company_color', '#00B140'); ?>">
                    <input type="text" id="colorHex" class="form-control" style="flex:1;"
                           value="<?php echo sv($settings, 'company_color', '#00B140'); ?>"
                           placeholder="#00B140" readonly>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title" style="margin-bottom:4px;">
                <i class="fas fa-envelope" style="color:var(--primary);margin-right:6px;"></i>SMTP / Email Settings
            </div>
            <div class="card-subtitle" style="margin-bottom:20px;">Used to send confirmation emails to registrants</div>

            <div class="form-group">
                <label>SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control"
                       value="<?php echo sv($settings, 'smtp_host', 'mail.prosper-minds.com'); ?>">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>SMTP Username</label>
                    <input type="text" name="smtp_user" class="form-control"
                           value="<?php echo sv($settings, 'smtp_user', ''); ?>">
                </div>
                <div class="form-group">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_pass" class="form-control"
                           placeholder="Leave blank to keep current">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="number" name="smtp_port" class="form-control"
                           value="<?php echo sv($settings, 'smtp_port', '587'); ?>">
                </div>
                <div class="form-group">
                    <label>Encryption</label>
                    <select name="smtp_secure" class="form-control">
                        <option value="tls"  <?php echo (($settings['smtp_secure'] ?? 'tls') === 'tls')  ? 'selected' : ''; ?>>TLS (STARTTLS) – Port 587</option>
                        <option value="ssl"  <?php echo (($settings['smtp_secure'] ?? '') === 'ssl')  ? 'selected' : ''; ?>>SSL – Port 465</option>
                        <option value="none" <?php echo (($settings['smtp_secure'] ?? '') === 'none') ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>From Email (must be a verified sender with your SMTP provider)</label>
                <input type="text" name="smtp_from_email" class="form-control"
                       value="<?php echo sv($settings, 'smtp_from_email', ''); ?>"
                       placeholder="e.g. notify@prosper-minds.com">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:4px;">
                <i class="fas fa-save"></i> Save Settings
            </button>
            <button type="button" id="testEmailBtn" class="btn btn-outline" style="margin-top:4px;margin-left:8px;"
                    title="Send a test email to the Admin Email address to verify SMTP is working">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </div>
    </form>

    <!-- Right column: Change password + System info -->
    <form method="POST" action="settings.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="change_password" value="1">

        <div class="card">
            <div class="card-title" style="margin-bottom:4px;">
                <i class="fas fa-lock" style="color:var(--primary);margin-right:6px;"></i>Change Password
            </div>
            <div class="card-subtitle" style="margin-bottom:20px;">Update your admin login password</div>

            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control"
                       required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control"
                       required autocomplete="new-password" minlength="8">
                <div class="form-hint">Minimum 8 characters</div>
            </div>
            <div class="form-group" style="margin-bottom:24px;">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control"
                       required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-key"></i> Update Password
            </button>
        </div>

        <div class="card" style="margin-top:24px;">
            <div class="card-title" style="margin-bottom:16px;">
                <i class="fas fa-info-circle" style="color:var(--primary);margin-right:6px;"></i>System Info
            </div>
            <table style="width:100%;font-size:13px;">
                <tr>
                    <td style="padding:6px 0;color:#94a3b8;border:none;">PHP Version</td>
                    <td style="padding:6px 0;font-weight:600;border:none;"><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#94a3b8;border:none;">Database</td>
                    <td style="padding:6px 0;font-weight:600;border:none;"><?php echo DB_NAME; ?> @ <?php echo DB_HOST; ?></td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#94a3b8;border:none;">Logged in as</td>
                    <td style="padding:6px 0;font-weight:600;border:none;">
                        <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                        <span class="badge <?php echo isSuper() ? 'badge-green' : 'badge-gray'; ?>" style="margin-left:6px;">
                            <?php echo isSuper() ? 'Super Admin' : 'Editor'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 0;color:#94a3b8;border:none;">Server Time</td>
                    <td style="padding:6px 0;font-weight:600;border:none;"><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
            </table>
        </div>
    </form>

</div>

<script>
const colorPicker = document.getElementById('colorPicker');
const colorHex    = document.getElementById('colorHex');
colorPicker.addEventListener('input', () => { colorHex.value = colorPicker.value; });

// ── Test email button ──────────────────────────────────────────────
function showSettingsToast(msg, ok) {
    let t = document.getElementById('settingsToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'settingsToast';
        t.style.cssText = 'position:fixed;bottom:28px;right:28px;z-index:9999;padding:14px 22px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 4px 18px rgba(0,0,0,.18);transition:opacity .3s;max-width:420px;';
        document.body.appendChild(t);
    }
    t.style.background = ok ? '#00B140' : '#dc2626';
    t.style.color = '#fff';
    t.style.opacity = '1';
    t.textContent = msg;
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.opacity = '0'; }, 5000);
}

document.getElementById('testEmailBtn').addEventListener('click', function() {
    const btn = this;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';

    const fd = new FormData();
    fd.append('csrf_token', <?php echo json_encode($csrfToken); ?>);

    fetch('send-test-email.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => showSettingsToast(data.message, data.success))
        .catch(() => showSettingsToast('Network error – please try again.', false))
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
});
</script>

<?php include 'footer.php'; ?>
