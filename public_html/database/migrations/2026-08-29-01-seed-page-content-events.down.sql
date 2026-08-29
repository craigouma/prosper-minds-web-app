-- Migration 2026-08-29-01 (DOWN): unseed the Phase 3 calendar page_content rows
--
-- Removes exactly what 2026-08-29-01-seed-page-content-events.up.sql inserted
-- and nothing else. It does not drop the table, and it does not touch rows that
-- migrations 03 or 05 own.
--
-- 'event' is the only slug this file introduced on its own, so it is the only
-- one deleted whole. 'events' and 'home' are shared with migration 03 and
-- migration 05, and deleting those slugs wholesale here would silently take
-- their rows with them and leave the calendar running on nothing but its inline
-- defaults. They are unseeded by exact (page_slug, section_key) pair instead.
--
-- READ THIS BEFORE RUNNING IT. From Phase 5 onwards these same rows are what
-- staff edit through the CMS, and this deletes them regardless of who last
-- changed them. Export first.
--
-- Deleting them does not break either page: every call site passes its own
-- inline default, so an unseeded page renders the copy the developer wrote.
-- local-dev/verify.sh section 11 proves that against the real pages.
--
-- THE ROUTE UPDATE IS NOT REVERSED, DELIBERATELY. The up migration repointed
-- the 404 page's calendar route from /index.php#events at /events.php. Putting
-- a fragment back would be repairing nothing and would leave the 404 page
-- pointing away from a real page that exists.

-- The slug this file introduced on its own.
DELETE FROM `page_content` WHERE `page_slug` = 'event';

-- Rows added to slugs the earlier migrations already own, by exact key.
DELETE FROM `page_content`
 WHERE `page_slug` = 'events'
   AND `section_key` IN (
        'filter_aria', 'filter_upcoming', 'filter_past',
        'count_upcoming', 'count_upcoming_one', 'count_past', 'count_past_one',
        'upcoming_heading', 'past_heading', 'upcoming_empty', 'past_empty',
        'row_cta', 'banner_download', 'banner_copy', 'banner_copied'
   );

DELETE FROM `page_content`
 WHERE `page_slug` = 'home'
   AND `section_key` = 'events_cta_label';
