-- Migration 2026-08-22-01 (DOWN): funnel_events
--
-- Drops the table created by 2026-08-22-01-create-funnel-events.up.sql.
-- The table holds only aggregate funnel counters (no registration, billing or
-- personal data), so dropping it loses nothing but historical conversion-rate
-- reporting in admin/analytics.php. Export it first if that history matters.
--
-- Note: includes/funnel.php recreates the table on demand on the next page
-- view, so dropping it does not permanently disable tracking. To actually stop
-- collecting, remove the funnelTrackEvent() calls (or the funnel.php include
-- from includes/config.php) as well.

DROP TABLE IF EXISTS `funnel_events`;
