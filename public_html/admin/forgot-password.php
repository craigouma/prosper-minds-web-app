<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/audit.php';
require_once '../includes/adminsession.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$done  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'That form had expired. Please try again.';
    } elseif (!checkRateLimit($pdo, $_SERVER['REMOTE_ADDR'] ?? '')) {
        $error = 'Too many attempts from this address. Please wait fifteen minutes.';
    } else {
        $who = trim((string) ($_POST['account'] ?? ''));
        recordFailedAttempt($pdo, $_SERVER['REMOTE_ADDR'] ?? '');

        // Always the same outcome, whether or not the account exists. A form
        // that answers differently is a way to find out who has an account.
        $done = true;

        if ($who !== '') {
            try {
                $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ? OR email = ? LIMIT 1');
                $stmt->execute([$who, $who]);
                $user = $stmt->fetch();

                if ($user) {
                    $origin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                            . '://' . ($_SERVER['HTTP_HOST'] ?? 'prosper-minds.com');
                    $link   = pmResetIssue($pdo, (int) $user['id'], $origin);
                    $email  = trim((string) ($user['email'] ?? ''));

                    if ($link !== null && $email !== '') {
                        $subject = 'Reset your Prosperminds admin password';
                        $body    = '<p>Someone asked to reset the password for the Prosperminds admin account <strong>'
                                 . htmlspecialchars($user['username']) . '</strong>.</p>'
                                 . '<p><a href="' . htmlspecialchars($link) . '">Choose a new password</a></p>'
                                 . '<p>This link works once and stops working in ' . PM_RESET_MINUTES . ' minutes. '
                                 . 'If this was not you, nothing has changed and you can ignore this message.</p>';

                        try {
                            $sent = sendEmailMessages([['to' => $email, 'subject' => $subject, 'message' => $body]]);
                            if (empty($sent[0]['success'])) {
                                // Recorded rather than shown, so the page still
                                // says nothing about whether the account exists.
                                recordFailedNotification($pdo, null, $email, $subject,
                                    (string) ($sent[0]['error'] ?? 'send reported failure'));
                            }
                        } catch (Throwable $e) {
                            recordFailedNotification($pdo, null, $email, $subject, $e->getMessage());
                        }
                    }

                    pmAudit($pdo, 'password_reset_requested',
                            'A password reset was requested for ' . $user['username'], 'admin_user', $user['id']);
                }
            } catch (Throwable $e) {
                error_log('forgot password: ' . $e->getMessage());
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
    <title>Reset password | Prosperminds Admin</title>
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
            <h1>Reset your password</h1>
            <p>A link goes to the address the account was created with. It works once and expires in
               <?php echo PM_RESET_MINUTES; ?> minutes.</p>
        </div>
        <dl class="pma-login-facts">
            <div><dt>Link life</dt><dd><?php echo PM_RESET_MINUTES; ?> minutes</dd></div>
            <div><dt>Uses</dt><dd>One</dd></div>
        </dl>
    </div>

    <div class="pma-login-form">
        <form method="POST" action="forgot-password.php">
            <div>
                <h2>Reset password</h2>
                <p>Enter your username or the email address on the account.</p>
            </div>

<?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="margin:0"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($done): ?>
            <div class="alert alert-success" style="margin:0">
              If that account exists, a reset link is on its way to the address it was created with.
              It has not arrived instantly in the past, so give it a few minutes before trying again.
            </div>
            <p class="form-hint">Still nothing after ten minutes? A super admin can set a new password for you
               directly from Users, which does not depend on email at all.</p>
            <a class="btn btn-outline btn-sm" href="login.php">Back to sign in</a>
<?php else: ?>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <div class="form-group">
                <label for="account">Username or email</label>
                <input type="text" id="account" name="account" class="form-control" required autofocus
                       autocomplete="username">
            </div>
            <button type="submit" class="btn btn-primary">Send the link</button>
            <a href="login.php" style="font-size:13px">Back to sign in</a>

            <div class="pma-login-note">
                <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 2.5 13 4.3v4.2c0 3-2.2 5-5 6-2.8-1-5-3-5-6V4.3z" fill="none"
                          stroke="#000" stroke-width="1.3"></path>
                </svg>
                <p>This page answers the same way whether or not the account exists, so it cannot be used to
                   find out who has one.</p>
            </div>
<?php endif; ?>
        </form>
    </div>

</div>
</body>
</html>
