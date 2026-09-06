-- Did the utf8mb4 conversion actually run?
--
-- Paste this into phpMyAdmin and press Go. It changes nothing, it only reports.
-- The USE line means it does not matter which database is selected on the left.

USE `kidsmone_Prosperminds_website`;

-- Expect 0. Any other number names the tables still waiting.
SELECT COUNT(*) AS still_latin1_should_be_0
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'kidsmone_Prosperminds_website'
   AND TABLE_COLLATION LIKE 'latin1%';

-- If the number above is not 0, this lists which ones.
SELECT TABLE_NAME, TABLE_COLLATION
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'kidsmone_Prosperminds_website'
   AND TABLE_COLLATION LIKE 'latin1%'
 ORDER BY TABLE_NAME;

-- The text that mattered. The dates should read normally, for example
-- 19-23 October 2026 with a proper en dash between the numbers.
SELECT `id`, `title`, `date_display`
  FROM `kidsmone_Prosperminds_website`.`events`
 ORDER BY `id`;

-- Nothing lost: 21 registrations, 15 delegate addresses, 9584.00 invoiced.
SELECT COUNT(*)                AS registrations_should_be_21,
       COUNT(DISTINCT `email`) AS delegates_should_be_15,
       SUM(`total_amount`)     AS invoiced_should_be_9584
  FROM `kidsmone_Prosperminds_website`.`event_registrations`;
