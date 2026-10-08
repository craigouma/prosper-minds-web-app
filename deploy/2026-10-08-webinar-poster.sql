-- 2026-10-08: the poster for PFM Insight Live Session 01 (29 October 2026).
--
-- Paste into phpMyAdmin, SQL tab, on kidsmone_Prosperminds_website. Run it
-- AFTER the code is deployed, because that is what puts the image file on the
-- server. Safe to run twice, and safe to run before anyone has visited the
-- webinars page: it adds the new column itself if it is not there yet.

USE `kidsmone_Prosperminds_website`;

ALTER TABLE `webinar_sessions`
  ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(300) NULL AFTER `zoom_link`;

UPDATE `webinar_sessions`
   SET `image_path` = 'assets/images/pfm-insight-live-session-01.jpg'
 WHERE `slug` = 'pfm-insight-live-session-01-start-here-data-for-public-money';

-- Expect session 1 to show the poster path and the other twelve to show NULL.
SELECT `session_number`, `title`, `image_path` FROM `webinar_sessions` ORDER BY `session_number`;
