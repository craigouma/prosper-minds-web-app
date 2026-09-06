-- Migration 2026-09-03-03 (DOWN): newsletter_subscribers.unsubscribed_at
-- Destructive: discards who opted out, so a re-import would mail them again.

ALTER TABLE `newsletter_subscribers` DROP COLUMN `unsubscribed_at`;
