-- Migration 2026-09-03-05 (UP): cms_menu_items

CREATE TABLE IF NOT EXISTS `cms_menu_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `location` VARCHAR(16) NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `label` VARCHAR(120) NOT NULL,
  `link_type` VARCHAR(16) NOT NULL DEFAULT 'page',
  `target` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_cms_menu_location` (`location`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
