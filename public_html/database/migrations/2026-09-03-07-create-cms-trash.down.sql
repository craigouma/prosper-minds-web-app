-- Migration 2026-09-03-07 (DOWN): cms_trash
-- Destructive: discards every snapshot, so nothing already deleted can be restored.

DROP TABLE IF EXISTS `cms_trash`;
