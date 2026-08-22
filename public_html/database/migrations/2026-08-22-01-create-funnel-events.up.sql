-- Migration 2026-08-22-01 (UP): funnel_events
--
-- Internal registration-funnel analytics for the site owner. One row per stage
-- a visitor reaches on the main site:
--
--   page_view      -> event-registration.php?id=N was rendered
--   form_started   -> the visitor touched the form (client-side beacon)
--   submit_attempt -> process-registration.php began handling a POST
--   submit_success -> the registration was COMMITTED (invoice generated)
--   submit_fail    -> a genuine validation/database failure
--
-- Deliberately NOT stored: IP address, user agent, or anything else that could
-- identify a person. `session_id` is a random UUID from a single first-party
-- 24-hour cookie whose only job is to join those five rows together. There is
-- no cross-site tracking, no fingerprinting and no persistent identity here —
-- see includes/funnel.php for the reasoning in full.
--
-- event_type is VARCHAR rather than ENUM on purpose: adding a sixth stage later
-- would otherwise need an ALTER TABLE on what will be the largest table on the
-- site. The allowed values are enforced in PHP (FUNNEL_EVENT_TYPES), which is
-- also how event_registrations.payment_status is already handled.
--
-- registration_id / event_id are unconstrained integers, matching the existing
-- event_registrations.event_id: no FK, so an analytics row can never block a
-- delete of the registration or event it refers to.
--
-- The application also creates this table on demand via
-- ensureFunnelEventsSchema() in includes/funnel.php, so applying this file is
-- optional — it exists so the change can be applied and reviewed explicitly,
-- per PROJECT.md section 9.
--
-- Reverse with: 2026-08-22-01-create-funnel-events.down.sql

CREATE TABLE IF NOT EXISTS `funnel_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(20) NOT NULL,
  `event_id` INT DEFAULT NULL,
  `registration_id` INT DEFAULT NULL,
  `referrer` VARCHAR(255) DEFAULT NULL,
  `utm_source` VARCHAR(100) DEFAULT NULL,
  `utm_medium` VARCHAR(100) DEFAULT NULL,
  `utm_campaign` VARCHAR(150) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_funnel_events_session` (`session_id`),
  KEY `idx_funnel_events_type` (`event_type`),
  KEY `idx_funnel_events_created` (`created_at`),
  KEY `idx_funnel_events_event` (`event_id`),
  KEY `idx_funnel_events_registration` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
