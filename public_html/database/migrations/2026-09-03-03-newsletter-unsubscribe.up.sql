-- Migration 2026-09-03-03 (UP): newsletter_subscribers.unsubscribed_at
--
-- NULL means subscribed. A timestamp records when the address opted out, and
-- the row is kept rather than deleted so a later re-import cannot resubscribe
-- someone who asked to leave.

ALTER TABLE `newsletter_subscribers`
  ADD COLUMN `unsubscribed_at` TIMESTAMP NULL DEFAULT NULL AFTER `source`;
