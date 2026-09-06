-- Migration 2026-09-03-07 (UP): cms_trash

CREATE TABLE IF NOT EXISTS `cms_trash` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `entity_type` VARCHAR(32) NOT NULL,
  `entity_id` VARCHAR(64) NOT NULL,
  `label` VARCHAR(200) NOT NULL,
  `context` VARCHAR(200) DEFAULT NULL,
  `snapshot` LONGTEXT NOT NULL,
  `deleted_by` VARCHAR(64) DEFAULT NULL,
  `deleted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `restored_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_cms_trash_open` (`restored_at`, `deleted_at`),
  KEY `idx_cms_trash_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
