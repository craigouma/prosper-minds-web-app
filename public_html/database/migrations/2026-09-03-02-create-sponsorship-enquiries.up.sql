-- Migration 2026-09-03-02 (UP): sponsorship_enquiries
--
-- See FINDING-sponsorship-enquiries-not-stored.md. Reverse with the paired .down.sql.

CREATE TABLE IF NOT EXISTS `sponsorship_enquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(120) NOT NULL,
  `last_name` VARCHAR(120) NOT NULL,
  `organisation` VARCHAR(200) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(60) DEFAULT NULL,
  `country` VARCHAR(120) DEFAULT NULL,
  `tier` VARCHAR(64) DEFAULT NULL,
  `events` TEXT DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'new',
  `notified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sponsorship_created` (`created_at`),
  KEY `idx_sponsorship_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
