-- Migration 2026-09-03-08 (DOWN): admin session tables
-- Signs out every remembered browser and voids any outstanding reset link.

DROP TABLE IF EXISTS `admin_password_resets`;
DROP TABLE IF EXISTS `admin_remember_tokens`;
