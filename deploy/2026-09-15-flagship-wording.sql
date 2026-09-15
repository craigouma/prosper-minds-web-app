-- Drop "four" from the flagship headings.
--
-- The count was baked into two headings, and the calendar no longer has exactly
-- four events: seven past cohorts were added on 6 September and more will
-- follow. A heading that counts is a heading that goes stale.
--
-- The live site reads these from page_content, so editing the PHP alone would
-- change nothing here. Both statements are scoped to the exact old wording, so
-- running this twice does nothing the second time, and an edit somebody has
-- already made by hand is never overwritten.

USE `kidsmone_Prosperminds_website`;

UPDATE `page_content`
   SET `content_value` = 'Flagship events'
 WHERE `page_slug` = 'home'
   AND `section_key` = 'events_title'
   AND `content_value` = 'Four flagship events';

UPDATE `page_content`
   SET `content_value` = 'Flagship schools in 2026'
 WHERE `page_slug` = 'sponsorship'
   AND `section_key` = 'events_title'
   AND `content_value` = 'Four flagship schools in 2026';

-- Check it worked. Expect 0 and 2.
SELECT (SELECT COUNT(*) FROM `kidsmone_Prosperminds_website`.`page_content`
         WHERE `content_value` LIKE '%our flagship%')            AS remaining_should_be_0,
       (SELECT COUNT(*) FROM `kidsmone_Prosperminds_website`.`page_content`
         WHERE `section_key` = 'events_title'
           AND `content_value` LIKE 'Flagship%')                 AS corrected_should_be_2;
