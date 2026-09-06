-- Migration 2026-09-03-05 (DOWN): cms_menu_items
-- Safe: the public navigation falls back to its built-in list when this is gone.

DROP TABLE IF EXISTS `cms_menu_items`;
