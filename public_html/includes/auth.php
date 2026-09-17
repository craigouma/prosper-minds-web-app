<?php
/**
 * Admin authentication, roles and granular permission helpers.
 */

function startAdminSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function requireAdminAuth(): void {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

// ── Roles ──────────────────────────────────────────────────

function isSuper(): bool {
    // Administrator flag takes priority; fall back to legacy role field
    if (isset($_SESSION['admin_is_administrator'])) {
        return (bool)$_SESSION['admin_is_administrator'];
    }
    return ($_SESSION['admin_role'] ?? '') === 'super_admin';
}

// ── Granular permissions ───────────────────────────────────

/**
 * Feature → capability definitions used for the permission matrix.
 */
function getPermissionFeatures(): array {
    return [
        'dashboard'     => ['view'   => 'View Dashboard'],
        'registrations' => ['view'   => 'View', 'delete' => 'Delete', 'export' => 'Export CSV'],
        'accounting'    => ['view'   => 'View', 'edit' => 'Update Collections', 'expenses' => 'Manage Expenses'],
        'events'        => ['view'   => 'View', 'create' => 'Create', 'edit'   => 'Edit',
                            'delete' => 'Delete', 'toggle' => 'Toggle Active'],
        'users'         => ['view'   => 'View', 'create' => 'Create', 'edit'   => 'Edit', 'delete' => 'Delete'],
        'settings'      => ['view'   => 'View', 'edit'   => 'Save Changes'],
    ];
}

/**
 * Check if the current user has a specific feature + capability.
 * Administrators always return true.
 * Pass capability = '' to check if the user has ANY access to a feature.
 */
function hasPermission(string $feature, string $capability = 'view'): bool {
    if (isSuper()) return true;

    $perms = $_SESSION['admin_permissions'] ?? [];
    $key   = strtolower($feature);

    if ($capability === '') {
        return !empty($perms[$key]);
    }
    return in_array($capability, (array)($perms[$key] ?? []), true);
}

function requirePermission(string $feature, string $capability = 'view'): void {
    if (!hasPermission($feature, $capability)) {
        $_SESSION['perm_error'] = "You don't have permission to do that.";
        header('Location: dashboard.php');
        exit;
    }
}

// ── CSRF ───────────────────────────────────────────────────

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(generateCsrfToken()) . '">';
}

// ── Login rate limiting ────────────────────────────────────

function checkRateLimit($pdo, string $ip): bool {
    try {
        $pdo->prepare(
            "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        )->execute();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn() < 5;
    } catch (PDOException $e) {
        return true;
    }
}

function recordFailedAttempt($pdo, string $ip): void {
    try {
        $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)")->execute([$ip]);
    } catch (PDOException $e) {}
}
