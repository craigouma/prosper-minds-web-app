-- Migration 2026-08-19-01 (DOWN): failed_notifications
--
-- Drops the table created by 2026-08-19-01-create-failed-notifications.up.sql.
-- The table only holds diagnostic records of undelivered email, so dropping it
-- loses no registration or billing data. Export it first if the unsent-email
-- backlog still matters.

DROP TABLE IF EXISTS `failed_notifications`;
