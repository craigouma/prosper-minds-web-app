-- Migration 2026-09-03-01 (DOWN): cms_audit_log
-- Destructive: discards the record of who did what. Export before running.

DROP TABLE IF EXISTS `cms_audit_log`;
