<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';

// Already logged in – go to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $ip       = $_SERVER['REMOTE_ADDR'];

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (!checkRateLimit($pdo, $ip)) {
            $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID on login to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['admin_logged_in']      = true;
                $_SESSION['admin_id']             = $user['id'];
                $_SESSION['admin_username']       = $user['username'];
                $_SESSION['admin_full_name']      = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $_SESSION['admin_role']           = $user['role'] ?? 'editor';
                $_SESSION['admin_is_administrator'] = (bool)($user['is_administrator'] ?? ($user['role'] === 'super_admin'));
                $_SESSION['admin_permissions']    = json_decode($user['permissions'] ?? '{}', true) ?? [];
                $_SESSION['admin_profile_image']  = $user['profile_image'] ?? '';
                pmAudit($pdo, 'login', 'Signed in to the admin panel', 'admin_user', $user['id']);
                header('Location: dashboard.php');
                exit;
            } else {
                recordFailedAttempt($pdo, $ip);
                // Intentionally vague message – don't reveal which field was wrong
                $error = 'Invalid credentials. Please try again.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in | Prosperminds Admin</title>
    <link rel="icon" href="../assets/images/favicon-32.png" sizes="32x32">
    <link rel="stylesheet" href="../assets/css/pm-admin.css">
</head>
<body class="pma">
<div class="pma-login">

    <div class="pma-login-aside">
        <div class="pma-login-brand">
            <img src="../assets/images/favicon-512.png" alt="" width="26" height="27">
            <span>Prosperminds</span>
        </div>

        <div>
            <h1>Programme and content administration</h1>
            <p>Private. Every action is recorded in the audit log with your account name, the time and the address you signed in from.</p>
        </div>

        <dl class="pma-login-facts">
            <div>
                <dt>Access</dt>
                <dd>By invitation</dd>
            </div>
            <div>
                <dt>Session</dt>
                <dd>Ends on browser close</dd>
            </div>
        </dl>
    </div>

    <div class="pma-login-form">
        <form method="POST" action="login.php" autocomplete="off">
            <div>
                <h2>Sign in</h2>
                <p>Use the username the account was created with.</p>
            </div>

<?php if ($error): ?>
            <div class="alert alert-danger" style="margin:0"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       autocomplete="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       autocomplete="current-password" required>
            </div>

            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--pma-body)">
                <input type="checkbox" id="showPwd" style="width:15px;height:15px">
                Show password
            </label>

            <button type="submit" class="btn btn-primary">Sign in</button>

            <div class="pma-login-note">
                <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 2.5 13 4.3v4.2c0 3-2.2 5-5 6-2.8-1-5-3-5-6V4.3z" fill="none"
                          stroke="#000" stroke-width="1.3"></path>
                </svg>
                <p>This panel holds delegate records and invoices. Sign-in attempts are rate limited, and a locked account clears after fifteen minutes.</p>
            </div>
        </form>
    </div>

</div>

<script>
document.getElementById('showPwd').addEventListener('change', function () {
    document.getElementById('password').type = this.checked ? 'text' : 'password';
});
</script>
</body>
</html>
