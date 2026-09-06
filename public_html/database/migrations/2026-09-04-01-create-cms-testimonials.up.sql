-- Migration 2026-09-04-01 (UP): cms_testimonials

CREATE TABLE IF NOT EXISTS `cms_testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quote` TEXT NOT NULL,
  `role` VARCHAR(160) DEFAULT NULL,
  `org` VARCHAR(200) DEFAULT NULL,
  `event_id` INT DEFAULT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `added_by` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_cms_testimonials_live` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
