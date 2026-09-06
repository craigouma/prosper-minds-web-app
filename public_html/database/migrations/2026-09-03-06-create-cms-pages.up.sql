-- Migration 2026-09-03-06 (UP): cms_pages, cms_page_blocks, cms_revisions, cms_preview_tokens

CREATE TABLE IF NOT EXISTS `cms_pages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `template` VARCHAR(32) NOT NULL DEFAULT 'standard',
  `page_type` VARCHAR(16) NOT NULL DEFAULT 'flexible',
  `parent_id` INT DEFAULT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'draft',
  `publish_at` DATETIME DEFAULT NULL,
  `seo_title` VARCHAR(255) DEFAULT NULL,
  `seo_description` VARCHAR(320) DEFAULT NULL,
  `noindex` TINYINT(1) NOT NULL DEFAULT 0,
  `trashed_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_by` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cms_pages_slug` (`slug`),
  KEY `idx_cms_pages_status` (`status`, `trashed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cms_page_blocks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT NOT NULL,
  `block_type` VARCHAR(24) NOT NULL,
  `appearance` VARCHAR(8) NOT NULL DEFAULT 'light',
  `sort_order` INT NOT NULL DEFAULT 0,
  `payload` LONGTEXT DEFAULT NULL,
  KEY `idx_cms_page_blocks_page` (`page_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cms_revisions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `entity_type` VARCHAR(32) NOT NULL,
  `entity_id` INT NOT NULL,
  `snapshot` LONGTEXT NOT NULL,
  `note` VARCHAR(180) DEFAULT NULL,
  `author` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_cms_revisions_entity` (`entity_type`, `entity_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cms_preview_tokens` (
  `token` CHAR(48) PRIMARY KEY,
  `page_id` INT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_by` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
