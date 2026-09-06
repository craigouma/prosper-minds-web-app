<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/adminsession.php';

$selector  = (string) ($_GET['s'] ?? $_POST['s'] ?? '');
$validator = (string) ($_GET['v'] ?? $_POST['v'] ?? '');
$user      = pmResetUser($pdo, $selector, $validator);

$error = '';
$done  = false;

if ($user !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Open the link again.';
    } else {
        $new     = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');

        if (mb_strlen($new) < 10) {
            $error = 'Use at least ten characters. This account can read delegate records and invoices.';
        } elseif ($new !== $confirm) {
            $error = 'Those two passwords are not the same.';
        } elseif (pmResetComplete($pdo, $selector, (int) $user['id'], $new)) {
            pmAudit($pdo, 'password_reset', 'Password reset for ' . $user['username'], 'admin_user', $user['id']);
            $done = true;
        } else {
            $error = 'The password could not be changed. The error has been logged.';
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
    <title>Choose a new password | Prosperminds Admin</title>
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
            <h1>Choose a new password</h1>
            <p>Setting a new password signs out every browser that was remembering this account.</p>
        </div>
    </div>

    <div class="pma-login-form">
<?php if ($done): ?>
        <form>
            <div><h2>Password changed</h2></div>
            <div class="alert alert-success" style="margin:0">
              You can sign in with the new password now. Any browser that was keeping this account signed in
              has been signed out.
            </div>
            <a class="btn btn-primary" href="login.php">Sign in</a>
        </form>
<?php elseif ($user === null): ?>
        <form>
            <div><h2>This link cannot be used</h2></div>
            <div class="alert alert-danger" style="margin:0">
              A reset link works once and lasts <?php echo PM_RESET_MINUTES; ?> minutes. This one has been used,
              has expired, or was not complete.
            </div>
            <a class="btn btn-outline btn-sm" href="forgot-password.php">Ask for a new link</a>
        </form>
<?php else: ?>
        <form method="POST" action="reset-password.php">
            <div>
                <h2>New password</h2>
                <p>For <strong><?php echo htmlspecialchars($user['username']); ?></strong>.</p>
            </div>

<?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="margin:0"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="s" value="<?php echo htmlspecialchars($selector); ?>">
            <input type="hidden" name="v" value="<?php echo htmlspecialchars($validator); ?>">

            <div class="form-group">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" class="form-control"
                       autocomplete="new-password" minlength="10" required autofocus>
                <span class="form-hint">At least ten characters.</span>
            </div>
            <div class="form-group">
                <label for="confirm">Type it again</label>
                <input type="password" id="confirm" name="confirm" class="form-control"
                       autocomplete="new-password" minlength="10" required>
            </div>

            <button type="submit" class="btn btn-primary">Set the password</button>
        </form>
<?php endif; ?>
    </div>

</div>
</body>
</html>
