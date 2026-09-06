-- Migration 2026-08-28-01 (DOWN): page_content
--
-- Drops the table created by 2026-08-28-01-create-page-content.up.sql.
--
-- READ THIS BEFORE RUNNING IT. The table holds no registration, billing or
-- personal data — it is the site's own marketing copy — but once the Phase 5
-- CMS exists it will also hold every edit staff have made through it, and
-- those edits exist nowhere else. Export it first.
--
-- Dropping it does not break the site. includes/content.php falls back to the
-- inline default every page passes to pmContent()/pmContentSafe(), so pages
-- keep rendering with the copy the developer wrote; and the table is recreated
-- empty on the next page view by ensurePageContentSchema(). To actually stop
-- using the content layer you would have to remove those call sites too, which
-- is not something a migration can do.

DROP TABLE IF EXISTS `page_content`;
