-- Migration 2026-08-28-02 (DOWN): newsletter_subscribers
--
-- Drops the table created by 2026-08-28-02-create-newsletter-subscribers.up.sql.
--
-- READ THIS BEFORE RUNNING IT. Unlike the other tables in this directory, this
-- one holds PERSONAL DATA — email addresses given by real people who asked to
-- hear about course dates. Dropping it destroys the list and any record that
-- consent was given, and there is no other copy. Export it first unless the
-- intention is erasure.
--
-- Dropping it does not break the site. includes/newsletter.php recreates the
-- table on demand on the next submission, and a submission made while the table
-- is absent is answered with a calm "we could not save that just now" rather
-- than an error page. To actually stop collecting addresses, remove the form
-- from includes/layout/footer.php as well.

DROP TABLE IF EXISTS `newsletter_subscribers`;
