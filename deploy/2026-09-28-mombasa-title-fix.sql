-- 2026-09-28: correct the Christmas PFM School's title. Missing "Public":
-- was "...for the Digital Finance Era", the official name is
-- "...for the Digital Public Finance Era".
--
-- Paste into phpMyAdmin, SQL tab, on kidsmone_Prosperminds_website. No code
-- deploy needed either way: every page, the invoice and the confirmation
-- email all read this one column.

USE `kidsmone_Prosperminds_website`;

UPDATE `events`
   SET `title` = 'The Christmas PFM Mastery School for the Digital Public Finance Era'
 WHERE `id` = 5;

SELECT `id`, `title` FROM `events` WHERE `id` = 5;
