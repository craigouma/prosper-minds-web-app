-- 2026-09-23: the Christmas PFM School's venue and banner (Lydia's email of
-- 2026-09-22), the sitewide secondary phone number, and the homepage's
-- flagship-count stat.
--
-- Paste this whole file into phpMyAdmin, SQL tab, on kidsmone_Prosperminds_website.
-- Run it AFTER the code is deployed (the code adds the new banner image).
--
-- Safe to run twice: every statement is a plain UPDATE or an upsert.

USE `kidsmone_Prosperminds_website`;

START TRANSACTION;

-- The Christmas PFM School: venue and banner. Dates were already correct
-- (14-18 December 2026) and are left alone, as is every price and
-- course-content field.
UPDATE `events`
   SET `location`   = 'Sarova Whitesands Beach Resort & Spa, Mombasa, Kenya',
       `image_path` = 'assets/images/Christmas PFM Mastery School banner.jpg'
 WHERE `id` = 5;

-- The homepage's "Schools in 2026" stat: 4 -> 2, now that only Cape Town and
-- Mombasa run. (Kuala Lumpur and Bali were already taken off the active
-- calendar in the 2026-09-06 go-live migration; is_active is unchanged here.)
UPDATE `page_content`
   SET `content_value` = '[{"value":"25","label":"Years collective experience"},{"value":"875","label":"Leaders trained"},{"value":"2","label":"Schools in 2026"},{"value":"5","label":"Day residential format"}]'
 WHERE `page_slug` = 'home' AND `section_key` = 'hero_facts';

-- The sitewide secondary phone number changed to +254 722 998105 (it appears
-- on the new Mombasa banner itself, alongside the unchanged first number).
-- This is one shared value used in the footer, the contact page, invoices,
-- the sponsorship reply and the registration help text, so it is updated
-- everywhere rather than only for Mombasa; see the deploy notes for why.
-- INSERT ... ON DUPLICATE KEY UPDATE so this still works even if a row is
-- somehow missing.
INSERT INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
  ('global', 'phone_secondary', 'text', '+254 722 998105', 60)
ON DUPLICATE KEY UPDATE `content_value` = VALUES(`content_value`);

INSERT INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
  ('register', 'done_help', 'text', 'Need help right away? Call +254 740 582302 or +254 722 998105, or email info@prosper-minds.com.', 170)
ON DUPLICATE KEY UPDATE `content_value` = VALUES(`content_value`);

COMMIT;

-- Check it worked. Expect Mombasa to show the new venue, and the two content
-- rows and hero_facts to show the new values.
SELECT `id`, `title`, `location`, `image_path` FROM `events` WHERE `id` = 5;
SELECT `page_slug`, `section_key`, `content_value` FROM `page_content`
 WHERE (`page_slug`, `section_key`) IN (('global','phone_secondary'), ('register','done_help'), ('home','hero_facts'));
