-- Migration 2026-08-29-01 (UP): seed page_content for the Phase 3 calendar
--
-- The labels events.php and event.php print around the data they read from the
-- `events` table. Migration 03 already seeded the 'events' hero and the banner
-- library headings, which were the parts that existed verbatim in the approved
-- prototype; this adds the rest of that page and the whole of the event detail
-- page, which is a new slug.
--
-- Same conventions as migrations 03 and 05, for the same reasons:
--   * INSERT IGNORE, never an upsert. From Phase 5 these rows are what staff
--     edit through the CMS, and a seed that clobbers their work on the next
--     deploy is a trap. Safe to re-run, safe against a diverged database.
--   * content_type set honestly per row.
--   * No em dashes in any value. Client instruction, and it applies to seeded
--     copy exactly as it applies to copy written in PHP.
--   * Every key here is also an inline default at its call site, saying the
--     same words, so an unseeded or unreachable table costs the page nothing.
--
-- WHAT IS DELIBERATELY NOT SEEDED
--
-- No date, no percentage and no deadline appears in any value below. The early
-- bird position on both pages is computed on every render from the
-- early_bird_N_pct / early_bird_N_date columns by includes/events.php. What is
-- seeded is the SENTENCE, with {pct} and {date} placeholders, so the client can
-- reword it without a deploy while the numbers inside it cannot go stale. The
-- same reasoning as home.cta_title_template in migration 05.
--
-- Counts in {n} placeholders are the same idea: "{n} scheduled schools" and
-- "{n} days, one arc" are filled from the real number of rows and the real
-- number of agenda days, so a four day school does not advertise five.

-- ── Events calendar: the filter, the counts and the two empty states ───────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('events', 'filter_aria',         'text', 'Filter the calendar', 100),
('events', 'filter_upcoming',     'text', 'Upcoming', 110),
('events', 'filter_past',         'text', 'Past cohorts', 120),
('events', 'count_upcoming',      'text', '{n} scheduled schools', 130),
('events', 'count_upcoming_one',  'text', '1 scheduled school', 140),
('events', 'count_past',          'text', '{n} past cohorts', 150),
('events', 'count_past_one',      'text', '1 past cohort', 160),
('events', 'upcoming_heading',    'text', 'Upcoming schools', 170),
('events', 'past_heading',        'text', 'Past cohorts', 180),
('events', 'upcoming_empty',      'text', 'Dates for the next calendar are being confirmed. Join the mailing list below and they will reach you as soon as they are.', 190),
('events', 'past_empty',          'text', 'No school has run yet. The first cohort of the 2026 calendar is listed under Upcoming.', 200),
('events', 'row_cta',             'text', 'View event', 210),
('events', 'banner_download',     'text', 'Download', 220),
('events', 'banner_copy',         'text', 'Copy link', 230),
('events', 'banner_copied',       'text', 'Link copied', 240);

-- ── Event detail. A new slug: the old page had no editable copy at all. ────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('event', 'back_label',            'text', 'Back to calendar', 10),
('event', 'hero_eyebrow',          'text', 'Residential school', 20),
('event', 'hero_eyebrow_template', 'text', '{n} day residential school', 30),

('event', 'fact_location',         'text', 'Location', 40),
('event', 'fact_dates',            'text', 'Dates', 50),
('event', 'fact_from',             'text', 'From', 60),
('event', 'fact_from_note',        'text', 'per delegate', 70),
('event', 'fact_early_bird',       'text', 'Early bird', 80),
-- Computed, not stored. {pct} and {date} are filled at render time.
('event', 'fact_early_bird_value', 'text', '{pct} per cent until {date}', 90),
('event', 'fact_rate',             'text', 'Rate', 100),
('event', 'fact_status',           'text', 'Status', 110),
('event', 'fact_status_past',      'text', 'This cohort has already run', 120),

('event', 'register_cta',          'text', 'Register for this school', 130),
('event', 'quote_cta',             'text', 'Request a group quote', 140),
('event', 'past_cta',              'text', 'See the current calendar', 150),

('event', 'course_title',          'text', 'The course', 160),
('event', 'audience_title',        'text', 'Who it is for', 170),
('event', 'outcomes_title',        'text', 'What you leave with', 180),

('event', 'agenda_eyebrow',        'text', 'Agenda', 190),
('event', 'agenda_title',          'text', '{n} days, one arc', 200),
('event', 'agenda_day_label',      'text', 'Day {n}', 210),

('event', 'pricing_eyebrow',       'text', 'Pricing', 220),
('event', 'pricing_title',         'text', 'Three delegate tiers', 230),
('event', 'tier_per_delegate',     'text', 'per delegate', 240),
('event', 'tier_cta',              'text', 'Select', 250),
('event', 'pricing_note',          'text', 'Early bird discounts apply by registration date and are calculated when you register. Group registrations of four or more delegates are invoiced together.', 260),

-- The closing band. Template again, for the same reason.
('event', 'cta_title_template',    'text', 'Save {pct} per cent until {date}', 270),
('event', 'cta_title_lapsed',      'text', 'Seats are open at the standard rate', 280),
('event', 'cta_body',              'text', 'Registration takes about four minutes and an invoice is issued immediately.', 290),
('event', 'cta_label',             'text', 'Register a delegate', 300),

-- The 404 branch. An unknown or unpublished id renders these rather than
-- redirecting to the homepage, which is what the page this replaced did.
('event', 'missing_title',         'text', 'Course not found', 310),
('event', 'missing_description',   'text', 'This course is not in the Prosperminds calendar. The full calendar lists every scheduled school and every past cohort.', 320),
('event', 'missing_eyebrow',       'text', 'Not found', 330),
('event', 'missing_heading',       'text', 'That course is not in the calendar', 340),
('event', 'missing_body',          'text', 'The link may be old, or the course may not have been published yet. The calendar lists every scheduled school, and every cohort that has already run.', 350),
('event', 'missing_cta',           'text', 'Open the calendar', 360);

-- ── Homepage: the action that now opens the real calendar ──────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('home', 'events_cta_label', 'text', 'Full calendar', 115);

-- ── One stale URL, corrected in place ──────────────────────────────────────
-- Migration 05 seeded the 404 page's suggested routes while the calendar was
-- still a homepage fragment. events.php now exists, so /index.php#events is a
-- worse destination than the page itself.
--
-- This is the one UPDATE in any seed migration, and it is deliberately narrow:
-- it is a ROUTE, not editorial copy, and the WHERE clause means it only fires
-- while the row still contains the exact string migration 05 wrote. Anyone who
-- has edited that row in the CMS keeps their edit untouched.
UPDATE `page_content`
   SET `content_value` = REPLACE(`content_value`, '/index.php#events', '/events.php')
 WHERE `page_slug` = 'notfound'
   AND `section_key` = 'routes'
   AND `content_value` LIKE '%/index.php#events%';
