<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';

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
    <title>Admin Login – Prosperminds</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <img src="../assets/images/fisrt-logo.png" alt="Prosperminds">
        </div>
        <h2 class="login-title">Admin Portal</h2>
        <p class="login-sub">Sign in to manage events and registrations</p>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label for="username"><i class="fas fa-user" style="margin-right:6px;color:#94a3b8;"></i>Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       autocomplete="username" required autofocus>
            </div>

            <div class="form-group" style="margin-bottom:24px;">
                <label for="password"><i class="fas fa-lock" style="margin-right:6px;color:#94a3b8;"></i>Password</label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password" class="form-control"
                           autocomplete="current-password" required style="padding-right:42px;">
                    <button type="button" id="togglePwd"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:15px;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px;">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <p style="text-align:center;margin-top:24px;font-size:12px;color:#94a3b8;">
            <i class="fas fa-shield-alt"></i> Secured with CSRF protection &amp; rate limiting
        </p>
    </div>
</div>

<script>
document.getElementById('togglePwd').addEventListener('click', function() {
    const pwd = document.getElementById('password');
    const icon = this.querySelector('i');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>
</body>
</html>
