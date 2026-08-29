-- Migration 2026-08-29-03 (DOWN): unseed the Phase 4 register page_content rows
--
-- 'register' is a slug this migration introduced on its own, so it is deleted
-- whole. No other migration owns a row under it.
--
-- READ THIS BEFORE RUNNING IT. From Phase 5 onwards these rows are what staff
-- edit through the CMS, and this deletes them regardless of who last changed
-- them. Export first.
--
-- Deleting them does not break the page: every call site passes its own inline
-- default, so an unseeded register page renders the copy the developer wrote,
-- and the invoice figures were never seeded in the first place.
-- local-dev/verify.sh section 12 proves that against the real page.

DELETE FROM `page_content` WHERE `page_slug` = 'register';
