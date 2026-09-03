<?php
function pmAuditEnsureSchema(PDO $pdo): void
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
            error_log('cms_audit_log: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `cms_audit_log` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `actor_id` INT DEFAULT NULL,
              `actor_username` VARCHAR(64) NOT NULL,
              `action` VARCHAR(48) NOT NULL,
              `entity_type` VARCHAR(48) DEFAULT NULL,
              `entity_id` VARCHAR(64) DEFAULT NULL,
              `summary` VARCHAR(255) NOT NULL,
              `ip_address` VARCHAR(45) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_cms_audit_created` (`created_at`),
              KEY `idx_cms_audit_actor` (`actor_username`),
              KEY `idx_cms_audit_entity` (`entity_type`, `entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    } catch (Throwable $e) {
        error_log('cms_audit_log: schema check failed: ' . $e->getMessage());
    }
}

function pmAudit(
    PDO $pdo,
    string $action,
    string $summary,
    ?string $entityType = null,
    $entityId = null
): void {
    try {
        pmAuditEnsureSchema($pdo);

        $pdo->prepare(
            'INSERT INTO cms_audit_log
                (actor_id, actor_username, action, entity_type, entity_id, summary, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $_SESSION['admin_id'] ?? null,
            substr((string) ($_SESSION['admin_username'] ?? 'unknown'), 0, 64),
            substr($action, 0, 48),
            $entityType !== null ? substr($entityType, 0, 48) : null,
            $entityId !== null ? substr((string) $entityId, 0, 64) : null,
            substr($summary, 0, 255),
            pmAuditClientIp(),
        ]);
    } catch (Throwable $e) {
        error_log('cms_audit_log: write failed: ' . $e->getMessage());
    }
}

function pmAuditClientIp(): ?string
{
    $candidates = [];

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $candidates[] = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';

    foreach ($candidates as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return null;
}
