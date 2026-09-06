-- Migration 2026-08-28-01 (UP): page_content
--
-- The content layer for the rebuilt site. One row per editable block of copy,
-- addressed by (page_slug, section_key):
--
--   ('home',  'hero_title')   -> "Strong systems start with strong people"
--   ('home',  'hero_body')    -> "Prosperminds trains senior government..."
--   ('home',  'stats')        -> '[{"value":"25","label":"Years..."}, ...]'
--   ('global','tagline')      -> "Protecting and growing the mind..."
--
-- WHY IT EXISTS NOW, BEFORE THERE IS ANY CMS
--
-- REBUILD-PLAN.md section 1: if the rebuilt pages ship with their copy
-- hardcoded in PHP, adding the planned CMS in Phase 5 means refactoring every
-- page to read from the database instead. Building the pages database-driven
-- from the first commit avoids that rework entirely — the CMS then becomes an
-- admin screen over a schema that is already live and already proven, rather
-- than a rewrite. Until it ships, content changes are a SQL update, which is
-- exactly the situation today and therefore not a regression.
--
-- SCHEMA NOTES
--
-- content_type is VARCHAR rather than ENUM, matching how funnel_events.
-- event_type and event_registrations.payment_status are already handled here:
-- adding a fifth type later would otherwise need an ALTER TABLE. The accepted
-- values ('text','html','image','json') are enforced in PHP by
-- PM_CONTENT_TYPES in includes/content.php.
--
-- The UNIQUE index on (page_slug, section_key) is doing two jobs. It makes an
-- upsert safe — INSERT ... ON DUPLICATE KEY UPDATE, which is what
-- pmContentSet() and the Phase 5 CMS will use — and it is the index every
-- lookup actually reads, since pmContentAll() fetches a whole page in one
-- query rather than one query per key.
--
-- content_value is LONGTEXT and nullable: an image row holds a path, a text row
-- holds a sentence, an html row can hold a section's worth of markup, and a
-- row that has been deliberately emptied should fall back to the page's own
-- inline default rather than render a hole.
--
-- No foreign keys, matching event_registrations.event_id: a content row must
-- never be able to block a delete elsewhere.
--
-- The application also creates this table on demand via
-- ensurePageContentSchema() in includes/content.php, so applying this file is
-- optional — it exists so the change can be applied and reviewed explicitly,
-- per PROJECT.md section 9. Note that on-demand creation makes an EMPTY table;
-- the copy itself comes from 2026-08-28-03-seed-page-content.up.sql.
--
-- Reverse with: 2026-08-28-01-create-page-content.down.sql

CREATE TABLE IF NOT EXISTS `page_content` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_slug` VARCHAR(64) NOT NULL,
  `section_key` VARCHAR(96) NOT NULL,
  `content_type` VARCHAR(16) NOT NULL DEFAULT 'text',
  `content_value` LONGTEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_page_content_slug_key` (`page_slug`, `section_key`),
  KEY `idx_page_content_slug` (`page_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
