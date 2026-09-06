-- Migration 2026-08-28-04 (UP): contact_messages
--
-- Storage for the contact form on the rebuilt contact.php.
--
-- WHY A TABLE AND NOT JUST AN EMAIL
--
-- PROJECT.md section 5, Priority 3 flags that it was never confirmed whether
-- the existing contact form delivers anywhere. It does not: the form on the
-- live index.php has no action and no method, exactly like the newsletter field
-- beside it, so every enquiry ever typed into it was discarded by the browser.
--
-- The obvious fix, "make it send an email", would replace a silent loss with a
-- quieter one. Mail from this host already fails in real conditions: the August
-- 2026 outage was an SMTP path that stopped working, and failed_notifications
-- exists precisely because 36 registrations sent no email. An enquiry that only
-- ever existed as an outbound SMTP conversation is gone the moment that
-- conversation fails, and nobody finds out.
--
-- So the row is the record and the email is the notification. contact-submit.php
-- reports success on a committed row and never on a sent message, which is the
-- same rule process-registration.php was fixed to follow in commit 2d05cc1.
--
-- THIS TABLE HOLDS PERSONAL DATA
--
-- Name, organisation, email, phone and whatever the enquirer writes. That is
-- unavoidable: an enquiry you cannot reply to is not an enquiry. It is the
-- second table in this project to hold personal data, after
-- newsletter_subscribers.
--
-- What it deliberately does NOT hold: IP address, user agent and referrer. Same
-- data-minimisation position as funnel_events and newsletter_subscribers. None
-- of the three is needed to answer someone's question, and each is one more
-- thing to justify under the Kenya Data Protection Act 2019 and GDPR.
--
-- privacy-policy.php already tells visitors that a message sent through a
-- contact form is kept so we can answer it, and states a two-year retention for
-- general enquiries. There is no automatic deletion job for that yet; see
-- PHASE2-PROGRESS.md.
--
-- SCHEMA NOTES
--
-- No UNIQUE index anywhere. A newsletter address is a subscription and a repeat
-- is a no-op; an enquiry is an event and someone legitimately writes twice.
--
-- `status` is VARCHAR with an application-side vocabulary ('new', 'read',
-- 'replied'), matching how funnel_events.event_type, page_content.content_type
-- and event_registrations.payment_status are handled here: a fourth value later
-- must not need an ALTER TABLE. Nothing writes anything but 'new' today; the
-- column exists so an admin screen has somewhere to record that a message was
-- dealt with, rather than that screen having to add a column to a table that by
-- then has rows in it.
--
-- `notified` records whether the alert email to the programme office actually
-- went out. It is nullable-free and defaults to 0, so a message whose email
-- failed is visibly distinguishable from one whose email succeeded, and can be
-- chased. This is the same shape as failed_notifications: the fact that a
-- notification failed is itself worth storing.
--
-- VARCHAR(190) on email for the same InnoDB index-width reason as
-- newsletter_subscribers, kept consistent even though this column is not
-- indexed, so the two tables agree on what a storable address is.
--
-- The application also creates this table on demand via
-- ensureContactMessageSchema() in includes/contact.php, so applying this file
-- is optional. It exists so the change can be applied and reviewed explicitly,
-- per PROJECT.md section 9.
--
-- Reverse with: 2026-08-28-04-create-contact-messages.down.sql

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(160) NOT NULL,
  `organisation` VARCHAR(200) DEFAULT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(60) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `source` VARCHAR(64) DEFAULT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'new',
  `notified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_contact_messages_created` (`created_at`),
  KEY `idx_contact_messages_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
