-- Migration 2026-09-03-06 (DOWN): the pages tables
-- Destructive: drops every CMS page, its blocks and its entire revision history.

DROP TABLE IF EXISTS `cms_preview_tokens`;
DROP TABLE IF EXISTS `cms_revisions`;
DROP TABLE IF EXISTS `cms_page_blocks`;
DROP TABLE IF EXISTS `cms_pages`;
