-- Migration 2026-08-28-04 (DOWN): drop contact_messages
--
-- READ THIS BEFORE RUNNING IT.
--
-- This table is the ONLY copy of every enquiry sent through contact.php. The
-- alert email to the programme office is a notification, not a record, and it
-- may never have been sent at all: contact-submit.php deliberately reports
-- success on a committed row rather than on a delivered message, so a row whose
-- `notified` is 0 exists nowhere else.
--
-- Dropping this table therefore destroys correspondence from real people who
-- are waiting on a reply, and it destroys personal data this business is
-- accountable for under the Kenya Data Protection Act 2019 and GDPR. Export
-- first, and be sure the export is readable, before running this.
--
-- Dropping it does not break the site. contact.php renders its form either way
-- and ensureContactMessageSchema() recreates the table on the next submission,
-- so the visible consequence is losing the history, not losing the page.

DROP TABLE IF EXISTS `contact_messages`;
