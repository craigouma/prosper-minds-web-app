-- Migration 2026-09-03-04 (UP): cms_media and cms_media_usage

CREATE TABLE IF NOT EXISTS `cms_media` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(180) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(64) NOT NULL,
  `bytes` INT NOT NULL DEFAULT 0,
  `width` INT DEFAULT NULL,
  `height` INT DEFAULT NULL,
  `alt_text` VARCHAR(255) DEFAULT NULL,
  `caption` VARCHAR(255) DEFAULT NULL,
  `focal_x` TINYINT UNSIGNED NOT NULL DEFAULT 50,
  `focal_y` TINYINT UNSIGNED NOT NULL DEFAULT 50,
  `uploaded_by` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cms_media_filename` (`filename`),
  KEY `idx_cms_media_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cms_media_usage` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `media_id` INT NOT NULL,
  `entity_type` VARCHAR(48) NOT NULL,
  `entity_id` VARCHAR(64) NOT NULL,
  `label` VARCHAR(160) DEFAULT NULL,
  UNIQUE KEY `uq_cms_media_usage` (`media_id`, `entity_type`, `entity_id`),
  KEY `idx_cms_media_usage_media` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
