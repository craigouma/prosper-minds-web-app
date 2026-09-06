-- Migration 2026-09-03-01 (UP): cms_audit_log

CREATE TABLE IF NOT EXISTS `cms_audit_log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
