-- 2026-09-23: clean, name-based URLs for events.
--
-- Paste this whole file into phpMyAdmin, SQL tab, on kidsmone_Prosperminds_website.
-- Run it AFTER the code is deployed (the code adds the /school/{slug} routes
-- this depends on).
--
-- Safe to run twice: the ALTER is guarded with IF NOT EXISTS and the UPDATEs
-- are idempotent.

USE `kidsmone_Prosperminds_website`;

START TRANSACTION;

-- A clean, permanent address for each event's own pages. Existing events
-- without a slug (Kuala Lumpur, Bali) are untouched and keep working on
-- their old /event.php?id=N links; pmEventDetailUrl() falls back to that
-- automatically. New events get a slug the moment they are created (see
-- admin/events.php); an edited title never changes an existing slug, so a
-- link already shared or indexed keeps working.
ALTER TABLE `events`
  ADD COLUMN IF NOT EXISTS `slug` VARCHAR(160) NULL UNIQUE AFTER `location`;

UPDATE `events`
   SET `slug` = 'future-ready-pfm-leaders-cape-town-2026'
 WHERE `id` = 1;

UPDATE `events`
   SET `slug` = 'christmas-pfm-mastery-school-mombasa-2026'
 WHERE `id` = 5;

COMMIT;

-- Check it worked. Expect both rows to show a slug.
SELECT `id`, `title`, `slug` FROM `events` WHERE `id` IN (1, 5);
