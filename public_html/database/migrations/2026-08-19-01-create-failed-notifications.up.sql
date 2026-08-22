-- Migration 2026-08-19-01 (UP): failed_notifications
--
-- Records any registration email that could not be delivered, so that a failed
-- send is visible to an admin and can be retried, instead of only flipping the
-- delegate's response to "failed" (which is what used to happen) or vanishing.
--
-- The application also creates this table on demand via
-- ensureFailedNotificationSchema() in includes/config.php, so applying this
-- file is optional — it exists so the change can be applied and reviewed
-- explicitly, per PROJECT.md section 9.
--
-- Reverse with: 2026-08-19-01-create-failed-notifications.down.sql

CREATE TABLE IF NOT EXISTS `failed_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT DEFAULT NULL,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `error_message` TEXT DEFAULT NULL,
  `resolved` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_failed_notifications_registration` (`registration_id`),
  KEY `idx_failed_notifications_resolved` (`resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
