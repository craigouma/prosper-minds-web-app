-- Migration 2026-08-28-03 (DOWN): unseed page_content
--
-- Removes the rows inserted by 2026-08-28-03-seed-page-content.up.sql, by the
-- page slugs that file owns. It does not drop the table — that is
-- 2026-08-28-01-create-page-content.down.sql.
--
-- READ THIS BEFORE RUNNING IT. From Phase 5 onwards these same rows are what
-- staff edit through the CMS, and this deletes them by slug regardless of who
-- last changed them. On a database where anyone has edited site copy, this is
-- destructive in a way the up migration is not. Export first.
--
-- Deleting them does not break the site: every page passes its own inline
-- default to pmContent()/pmContentSafe(), so an unseeded page renders the copy
-- the developer wrote rather than blanks. That is the same behaviour the
-- content layer produces when the whole table is missing, and it is tested in
-- local-dev/verify.sh section 9d.
--
-- Scoped by page_slug rather than by a "seeded" flag column on purpose: an
-- extra column to support only the down path would be schema carried forever
-- for a script that should almost never run.

DELETE FROM `page_content`
 WHERE `page_slug` IN ('global', 'home', 'events', 'about', 'services', 'contact', 'sponsorship', 'notfound');
