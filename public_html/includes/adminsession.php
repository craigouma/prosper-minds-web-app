<?php

const PM_REMEMBER_COOKIE = 'pm_admin_remember';
const PM_REMEMBER_DAYS   = 7;
const PM_RESET_MINUTES   = 60;

function ensureAdminSessionSchema(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    try {
        // CREATE TABLE implicitly commits in MySQL, so a call inside someone
        // else's transaction would commit their half-written work early.
        if ($pdo->inTransaction()) {
            error_log('admin session tables: skipped schema check, called inside an open transaction');

            return;
        }

        // selector is looked up, validator_hash is compared. Storing only the
        // hash means a copy of this table does not let anyone sign in.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `admin_remember_tokens` (
              `selector` CHAR(24) PRIMARY KEY,
              `user_id` INT NOT NULL,
              `validator_hash` CHAR(64) NOT NULL,
              `expires_at` DATETIME NOT NULL,
              `last_used_at` TIMESTAMP NULL DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_remember_user` (`user_id`),
              KEY `idx_remember_expiry` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `admin_password_resets` (
              `selector` CHAR(24) PRIMARY KEY,
              `user_id` INT NOT NULL,
              `validator_hash` CHAR(64) NOT NULL,
              `expires_at` DATETIME NOT NULL,
              `used_at` TIMESTAMP NULL DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_reset_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    } catch (Throwable $e) {
        error_log('admin session tables: schema check failed: ' . $e->getMessage());
    }
}

function pmCookieSecure(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

/** Fill $_SESSION from an admin_users row. Used by login and by remember-me. */
function pmAdminSignIn(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['admin_logged_in']        = true;
    $_SESSION['admin_id']               = $user['id'];
    $_SESSION['admin_username']         = $user['username'];
    $_SESSION['admin_full_name']        = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['admin_role']             = $user['role'] ?? 'editor';
    $_SESSION['admin_is_administrator'] = (bool) ($user['is_administrator'] ?? ($user['role'] === 'super_admin'));
    $_SESSION['admin_permissions']      = json_decode($user['permissions'] ?? '{}', true) ?? [];
    $_SESSION['admin_profile_image']    = $user['profile_image'] ?? '';
}

function pmRememberIssue(PDO $pdo, int $userId): void
{
    try {
        ensureAdminSessionSchema($pdo);

        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        $pdo->prepare('INSERT INTO admin_remember_tokens (selector, user_id, validator_hash, expires_at)
                       VALUES (?, ?, ?, ?)')
            ->execute([$selector, $userId, hash('sha256', $validator),
                       date('Y-m-d H:i:s', time() + PM_REMEMBER_DAYS * 86400)]);

        setcookie(PM_REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires'  => time() + PM_REMEMBER_DAYS * 86400,
            'path'     => '/',
            'secure'   => pmCookieSecure(),
            'httponly' => true,
            // Lax rather than Strict: a link from an email into the panel must
            // still find the visitor signed in, which Strict would prevent.
            'samesite' => 'Lax',
        ]);
    } catch (Throwable $e) {
        error_log('remember me: could not issue a token: ' . $e->getMessage());
    }
}

function pmRememberForget(PDO $pdo): void
{
    $raw = (string) ($_COOKIE[PM_REMEMBER_COOKIE] ?? '');

    if ($raw !== '' && str_contains($raw, ':')) {
        try {
            [$selector] = explode(':', $raw, 2);
            $pdo->prepare('DELETE FROM admin_remember_tokens WHERE selector = ?')->execute([$selector]);
        } catch (Throwable $e) {
            error_log('remember me: could not clear a token: ' . $e->getMessage());
        }
    }

    setcookie(PM_REMEMBER_COOKIE, '', [
        'expires' => time() - 3600, 'path' => '/',
        'secure' => pmCookieSecure(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/** Drop every remembered session for one account. Used on a password change. */
function pmRememberForgetAll(PDO $pdo, int $userId): void
{
    try {
        $pdo->prepare('DELETE FROM admin_remember_tokens WHERE user_id = ?')->execute([$userId]);
    } catch (Throwable $e) {
        error_log('remember me: could not clear tokens for user ' . $userId . ': ' . $e->getMessage());
    }
}

/**
 * Sign in from the remember cookie if it is valid. Returns the username on
 * success so the caller can audit it.
 *
 * The token is replaced on every use. A stolen cookie is therefore usable at
 * most once before the real owner's next visit invalidates it, and the theft
 * shows up as an unexplained sign-out.
 */
function pmRememberResume(PDO $pdo): ?string
{
    $raw = (string) ($_COOKIE[PM_REMEMBER_COOKIE] ?? '');

    if ($raw === '' || !str_contains($raw, ':')) {
        return null;
    }

    [$selector, $validator] = explode(':', $raw, 2);

    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        return null;
    }

    try {
        ensureAdminSessionSchema($pdo);
        $pdo->prepare('DELETE FROM admin_remember_tokens WHERE expires_at < NOW()')->execute();

        $stmt = $pdo->prepare('SELECT * FROM admin_remember_tokens WHERE selector = ? AND expires_at > NOW()');
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if (!$token || !hash_equals((string) $token['validator_hash'], hash('sha256', $validator))) {
            pmRememberForget($pdo);

            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
        $stmt->execute([(int) $token['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            pmRememberForget($pdo);

            return null;
        }

        $pdo->prepare('DELETE FROM admin_remember_tokens WHERE selector = ?')->execute([$selector]);
        pmAdminSignIn($user);
        pmRememberIssue($pdo, (int) $user['id']);

        return (string) $user['username'];
    } catch (Throwable $e) {
        error_log('remember me: resume failed: ' . $e->getMessage());

        return null;
    }
}

/**
 * Create a reset token. Returns the link, or null if nothing could be stored.
 * The caller must show the same message either way, so that asking for a reset
 * cannot be used to find out which accounts exist.
 */
function pmResetIssue(PDO $pdo, int $userId, string $origin): ?string
{
    try {
        ensureAdminSessionSchema($pdo);

        $pdo->prepare('DELETE FROM admin_password_resets WHERE user_id = ? OR expires_at < NOW()')
            ->execute([$userId]);

        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        $pdo->prepare('INSERT INTO admin_password_resets (selector, user_id, validator_hash, expires_at)
                       VALUES (?, ?, ?, ?)')
            ->execute([$selector, $userId, hash('sha256', $validator),
                       date('Y-m-d H:i:s', time() + PM_RESET_MINUTES * 60)]);

        return $origin . '/admin/reset-password.php?s=' . $selector . '&v=' . $validator;
    } catch (Throwable $e) {
        error_log('password reset: could not issue a token: ' . $e->getMessage());

        return null;
    }
}

/** The account a reset link belongs to, or null when it is not usable. */
function pmResetUser(PDO $pdo, string $selector, string $validator): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        return null;
    }

    try {
        ensureAdminSessionSchema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM admin_password_resets
                                WHERE selector = ? AND used_at IS NULL AND expires_at > NOW()');
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if (!$token || !hash_equals((string) $token['validator_hash'], hash('sha256', $validator))) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
        $stmt->execute([(int) $token['user_id']]);
        $user = $stmt->fetch();

        return $user ?: null;
    } catch (Throwable $e) {
        error_log('password reset: lookup failed: ' . $e->getMessage());

        return null;
    }
}

/**
 * Set a new password and invalidate everything that could still get in with
 * the old one: the reset link itself, and every remembered session.
 */
function pmResetComplete(PDO $pdo, string $selector, int $userId, string $newPassword): bool
{
    try {
        $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        $pdo->prepare('UPDATE admin_password_resets SET used_at = NOW() WHERE selector = ?')->execute([$selector]);
        pmRememberForgetAll($pdo, $userId);

        return true;
    } catch (Throwable $e) {
        error_log('password reset: could not complete: ' . $e->getMessage());

        return false;
    }
}
