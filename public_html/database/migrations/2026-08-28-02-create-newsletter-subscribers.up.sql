-- Migration 2026-08-28-02 (UP): newsletter_subscribers
--
-- Storage for the footer newsletter signup on the rebuilt pages.
--
-- This closes a documented gap rather than adding a feature. The newsletter
-- field in the current live footer (index.php, and the same markup copied into
-- all three service-*.php pages) is a <form> with no action and no method. It
-- posts nowhere, and every address typed into it since the site launched has
-- been discarded. PROJECT.md section 5, Priority 3: "Confirm the contact form
-- and newsletter signup are wired to a real destination rather than silently
-- discarding submissions."
--
-- WHAT IS STORED, AND WHAT IS DELIBERATELY NOT
--
-- The address, which form collected it, and when. Nothing else. No IP address,
-- no user agent, no referrer — the same data-minimisation position taken for
-- funnel_events, and for the same reason: the audience includes EU and Kenyan
-- public-sector delegates (GDPR / Kenya DPA 2019) and a mailing list is not
-- worth taking on personal data it does not need.
--
-- An email address IS personal data, so unlike funnel_events this table is
-- within scope of a subject access or erasure request. It is a single table
-- keyed by the address itself, which makes both trivial to service.
--
-- SCHEMA NOTES
--
-- The UNIQUE index on email is what makes a repeat submission idempotent, at
-- the database level rather than through a read-then-write race in PHP:
-- includes/newsletter.php inserts with ON DUPLICATE KEY UPDATE id = id, a
-- deliberate no-op that leaves created_at showing when the address was FIRST
-- given.
--
-- VARCHAR(190), not 255: utf8mb4 stores up to four bytes per character, and a
-- unique index over VARCHAR(255) utf8mb4 needs 1020 bytes, over the 767-byte
-- index limit on older InnoDB row formats this database may still use. 190 is
-- the long-standing safe maximum and is far longer than any real address.
--
-- Addresses are lower-cased before insert (pmNewsletterNormaliseEmail), so the
-- uniqueness guarantee does not depend on the collation being case-insensitive.
--
-- The application also creates this table on demand via
-- ensureNewsletterSubscriberSchema() in includes/newsletter.php, so applying
-- this file is optional — it exists so the change can be applied and reviewed
-- explicitly, per PROJECT.md section 9.
--
-- Reverse with: 2026-08-28-02-create-newsletter-subscribers.down.sql

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(190) NOT NULL,
  `source` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_newsletter_subscribers_email` (`email`),
  KEY `idx_newsletter_subscribers_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
