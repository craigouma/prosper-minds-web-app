-- Migration 2026-08-28-05 (UP): seed page_content for the Phase 2 pages
--
-- The copy the Phase 2 pages need on top of what
-- 2026-08-28-03-seed-page-content.up.sql already holds. That file seeded the
-- copy that exists verbatim in the approved prototype; this one adds the copy
-- for the parts of Phase 2 the prototype did not have to answer, plus three new
-- page slugs for the three service detail pages.
--
-- Same conventions as migration 03, for the same reasons:
--   * INSERT IGNORE, never an upsert. From Phase 5 these rows are what staff
--     edit through the CMS, and a seed that clobbers their work on the next
--     deploy is a trap. Safe to re-run, safe against a diverged database.
--   * content_type set honestly per row: 'text' for words, 'html' only where
--     the value really is markup, 'json' for a repeated block.
--   * No em dashes in any value. Client instruction, and it applies to seeded
--     copy exactly as it applies to copy written in PHP.
--
-- WHAT IS NEW HERE AND WHY
--
-- 1. home.cta_title_template. The prototype's closing band reads "Seats for the
--    October cohort close on 12 September" and "Twenty per cent applies until
--    15 July 2026". Both are invented and neither is true of the `events`
--    table. Migration 03 therefore left the band's title unseeded and
--    PHASE1-FOUNDATION-PROGRESS.md section 8.3 flagged it as needing the real
--    wording.
--
--    Seeding a corrected date would only move the problem: a hardcoded deadline
--    is wrong the day after it passes and nobody notices. So what is seeded is
--    a TEMPLATE with {pct}, {city} and {date} placeholders, filled at render
--    time by pmSoonestEarlyBird() and pmEarlyBirdFill() in includes/events.php
--    from the early_bird_N_pct / early_bird_N_date columns. The client can
--    reword the sentence; the numbers and the date inside it cannot go stale.
--
--    home.cta_title_lapsed is the same band once every tier on every event has
--    passed. That state is not hypothetical: as of 28 August 2026 Cape Town has
--    already lost two of its three tiers.
--
-- 2. Three new page slugs, service-pfm / service-data / service-sustainability,
--    one per service detail page. The brief requires each detail page to be
--    substantial enough to stand alone, so each carries its own narrative,
--    audience list and teaching format rather than reprinting the one-line
--    promise from the services overview.
--
--    Nothing in them is invented marketing. The promises, intros, outcomes and
--    curriculum topics are the approved prototype's PILLARS data. The audience
--    lists are the prototype's per-event audience lists. The five-day format
--    description is the structure stated in the brief (section 3) and visible
--    in every agenda row in the `events` table.
--
-- 3. service-*.related_tags. Which schools cover which pillar, expressed as
--    tags matched against events.focus_tags and events.title rather than as a
--    hardcoded id map in PHP, so the mapping is editable content and survives
--    an event being added or renamed.
--
--    service-sustainability.related_tags is deliberately narrow and currently
--    matches NOTHING. That is correct: no event in the 2026 calendar covers
--    sustainability reporting, in its title, its focus tags, or any line of any
--    of the four agendas. The page renders events_empty instead of pretending
--    otherwise. See the comment on pmEventsMatchingTags().
--
-- 4. notfound.routes holds FOUR routes, not five. The prototype's 404 shows
--    five buttons under copy that says "These four routes cover most of what
--    people arrive looking for", which is a prototype inconsistency. The copy
--    was already seeded by migration 03 and INSERT IGNORE will not rewrite it,
--    so the routes match the sentence rather than the sentence being left
--    wrong. About is reachable from the header on every page.
--
-- Reverse with: 2026-08-28-05-seed-page-content-phase2.down.sql

-- ── Homepage: the computed closing band, and the labels the grid needs ──────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('home', 'cta_eyebrow',             'text', 'Registration', 195),
('home', 'cta_title_template',      'text', 'Save {pct} per cent on the {city} school until {date}', 196),
('home', 'cta_title_lapsed',        'text', 'Registration is open for the 2026 residential schools', 197),
('home', 'cta_body_lapsed',         'text', 'Standard delegate rates apply. Cohorts are capped, so a place is worth confirming early.', 198),
('home', 'early_bird_lapsed_label', 'text', 'Standard rate', 220),
('home', 'event_details_label',     'text', 'Details', 230),
('home', 'event_register_label',    'text', 'Register', 240),
('home', 'events_empty',            'text', 'Dates for the next intake are being confirmed. Contact the programme office and we will tell you first.', 250),
('home', 'pillars_cta_label',       'text', 'Read more', 260),
('home', 'record_cta_label',        'text', 'About Prosperminds', 270);

-- ── About ──────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('about', 'stats',            'json', '[{"value":"25","label":"Years collective experience"},{"value":"875","label":"Leaders trained"},{"value":"14","label":"Countries represented"},{"value":"5","label":"Days per school"}]', 115),
('about', 'pillars_cta_label', 'text', 'Open service page', 140);

-- ── Services overview ──────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('services', 'hero_body',         'text', 'Every Prosperminds course sits inside one of three pillars. Each has its own curriculum, its own set of outcomes and its own place in the calendar. Open a pillar for the full outline.', 45),
('services', 'pillar_cta_label',  'text', 'Open the full outline', 55),
('services', 'events_eyebrow',    'text', 'Calendar', 90),
('services', 'events_title',      'text', 'Schools covering these pillars', 100),
('services', 'events_empty',      'text', 'Dates for the next intake are being confirmed. Contact the programme office and we will tell you first.', 110),
('services', 'cta_eyebrow',       'text', 'Not sure which one', 120),
('services', 'cta_title',         'text', 'Tell us what your department is being held to this year', 130),
('services', 'cta_body',          'text', 'The programme office will point you at the right pillar, and will say plainly if none of them is the answer.', 140);

-- ── Service detail: PFM, IPSAS and IFRS Mastery ────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('service-pfm', 'meta_title',       'text', 'PFM, IPSAS and IFRS Mastery', 10),
('service-pfm', 'meta_description', 'text', 'Accrual accounting, disclosure and audit readiness for public institutions that are judged on their financial statements. Five day residential training for government finance teams.', 20),
('service-pfm', 'hero_eyebrow',     'text', 'Services', 30),
('service-pfm', 'hero_title',       'text', 'PFM, IPSAS and IFRS Mastery', 40),
('service-pfm', 'hero_promise',     'text', 'Build the technical foundation your finance teams need.', 50),
('service-pfm', 'hero_body',        'text', 'Accrual accounting, disclosure and audit readiness for institutions that are judged on their financial statements.', 60),
('service-pfm', 'context_title',    'text', 'Why departments send teams', 70),
('service-pfm', 'context_body_1',   'text', 'A clean audit is the clearest public signal that an institution is well run, and the hardest ground is almost always the same. Asset recognition, valuation and a register that reconciles to the ledger are where qualified opinions start. Departments send teams here when they are carrying audit findings they have not been able to close.', 80),
('service-pfm', 'context_body_2',   'text', 'The move from cash to accrual reporting is the other reason. It changes what has to be recognised, when, and on whose authority, and the reporting team cannot deliver it alone. This pillar treats the transition as a sequencing problem with a timetable attached, not as a standard to be memorised.', 90),
('service-pfm', 'outcomes_title',   'text', 'What a delegate returns with', 100),
('service-pfm', 'outcomes',         'json', '["Statements that reconcile to the ledger and survive audit","A defensible position on asset recognition and measurement","A transition plan from cash to accrual reporting","Reporting timetables that hold under pressure"]', 110),
('service-pfm', 'curriculum_title', 'text', 'Curriculum coverage', 120),
('service-pfm', 'topics',           'json', '["IPSAS presentation and disclosure","Revenue and expenditure recognition","Asset registers and componentisation","Consolidation boundaries","Audit file construction","IFRS for state corporations"]', 130),
('service-pfm', 'audience_title',   'text', 'Who it is for', 140),
('service-pfm', 'audience',         'json', '["Financial reporting managers","Auditors General and audit managers","IPSAS transition project leads","Asset and infrastructure accountants","Public entity finance directors"]', 150),
('service-pfm', 'format_title',     'text', 'How it is taught', 160),
('service-pfm', 'format_body',      'text', 'Five days, residential, with the cohort capped so that faculty stay reachable throughout. Day one sets the leadership context, days two to four work through the technical material against real statements and real audit findings, and day five is spent building the action plan each delegate takes back to their department. Every school carries CPD certification.', 170),
('service-pfm', 'events_title',     'text', 'Schools covering this pillar', 180),
('service-pfm', 'events_empty',     'text', 'No school covering this pillar is in the 2026 calendar yet. Contact the programme office and we will tell you when one is scheduled.', 190),
('service-pfm', 'related_tags',     'json', '["IPSAS","Clean Audit","Assets Accounting","PFM Leadership","Mastery School"]', 200),
('service-pfm', 'cta_eyebrow',      'text', 'Next step', 210),
('service-pfm', 'cta_title',        'text', 'Ask for the full course outline', 220),
('service-pfm', 'cta_body',         'text', 'The programme office replies within one working day, and will say plainly if a different pillar fits your team better.', 230),
('service-pfm', 'cta_label',        'text', 'Contact the programme office', 240);

-- ── Service detail: Data Analytics and AI Automation ───────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('service-data', 'meta_title',       'text', 'Data Analytics and AI Automation', 10),
('service-data', 'meta_description', 'text', 'Practical analytics and automation for public sector finance functions: reporting cycle automation, forecasting, anomaly detection and a governance position on AI tools before procurement.', 20),
('service-data', 'hero_eyebrow',     'text', 'Services', 30),
('service-data', 'hero_title',       'text', 'Data Analytics and AI Automation', 40),
('service-data', 'hero_promise',     'text', 'Transform reporting from burden to strategic advantage.', 50),
('service-data', 'hero_body',        'text', 'Practical analytics and automation for finance functions that still spend most of the month closing the books.', 60),
('service-data', 'context_title',    'text', 'Why departments send teams', 70),
('service-data', 'context_body_1',   'text', 'Most public finance teams spend the larger part of every month producing figures rather than using them. Reconciliation and consolidation absorb the time, and by the point the numbers are ready the decision they were meant to inform has usually already been taken. This pillar starts there, with the reporting cycle itself.', 80),
('service-data', 'context_body_2',   'text', 'Governance is the second reason. Analytics and AI tools are already being sold into finance ministries, and procurement is moving faster than policy. A department that has decided in advance what it will automate, what it will not, and who answers when a model is wrong, negotiates from a much stronger position.', 90),
('service-data', 'outcomes_title',   'text', 'What a delegate returns with', 100),
('service-data', 'outcomes',         'json', '["Reconciliation and consolidation work reduced to hours","Forecasts leadership is willing to act on","Anomaly detection built into the audit cycle","A governance position on AI tools before procurement"]', 110),
('service-data', 'curriculum_title', 'text', 'Curriculum coverage', 120),
('service-data', 'topics',           'json', '["Reporting cycle automation","Data quality controls","Revenue and expenditure forecasting","Fraud and anomaly analytics","Dashboard design for oversight","AI governance and procurement"]', 130),
('service-data', 'audience_title',   'text', 'Who it is for', 140),
('service-data', 'audience',         'json', '["Chief accountants and finance directors","Budget controllers and analysts","Internal and external auditors","Heads of ICT in finance ministries","Monitoring and evaluation officers"]', 150),
('service-data', 'format_title',     'text', 'How it is taught', 160),
('service-data', 'format_body',      'text', 'Five days, residential, with the cohort capped so that faculty stay reachable throughout. The technical days are worked through on real reporting data rather than on a demonstration dataset, and day five is spent building the action plan each delegate takes back to their department. Every school carries CPD certification.', 170),
('service-data', 'events_title',     'text', 'Schools covering this pillar', 180),
('service-data', 'events_empty',     'text', 'No school covering this pillar is in the 2026 calendar yet. Contact the programme office and we will tell you when one is scheduled.', 190),
('service-data', 'related_tags',     'json', '["Data Analytics","AI & Automation","Smart Finance","Budgeting","Revenue & Funding"]', 200),
('service-data', 'cta_eyebrow',      'text', 'Next step', 210),
('service-data', 'cta_title',        'text', 'Ask for the full course outline', 220),
('service-data', 'cta_body',         'text', 'The programme office replies within one working day, and will say plainly if a different pillar fits your team better.', 230),
('service-data', 'cta_label',        'text', 'Contact the programme office', 240);

-- ── Service detail: Sustainability Reporting ───────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('service-sustainability', 'meta_title',       'text', 'Sustainability Reporting', 10),
('service-sustainability', 'meta_description', 'text', 'Climate and sustainability disclosure for public institutions now being asked for it by lenders, auditors and citizens. Scope, data ownership, assurance readiness.', 20),
('service-sustainability', 'hero_eyebrow',     'text', 'Services', 30),
('service-sustainability', 'hero_title',       'text', 'Sustainability Reporting', 40),
('service-sustainability', 'hero_promise',     'text', 'Meet global standards while strengthening transparency.', 50),
('service-sustainability', 'hero_body',        'text', 'Climate and sustainability disclosure for public institutions now being asked for it by lenders, auditors and citizens.', 60),
('service-sustainability', 'context_title',    'text', 'Why departments send teams', 70),
('service-sustainability', 'context_body_1',   'text', 'Sustainability disclosure arrived in the public sector from the outside. Lenders ask for it as a condition of funding, auditors ask for it because it is now in scope, and citizens ask for it because the spending is theirs. Very few institutions were given a budget or a team to answer with.', 80),
('service-sustainability', 'context_body_2',   'text', 'The practical questions are the same everywhere. What is in scope, who owns the data, how does the reporting fit the financial calendar, and what will stand up to assurance. This pillar works through those four in order, rather than starting from a framework and hoping the underlying data exists.', 90),
('service-sustainability', 'outcomes_title',   'text', 'What a delegate returns with', 100),
('service-sustainability', 'outcomes',         'json', '["A disclosure scope decision you can defend","Data collection assigned to real owners","Alignment with lender and donor requirements","Reporting integrated with the financial calendar"]', 110),
('service-sustainability', 'curriculum_title', 'text', 'Curriculum coverage', 120),
('service-sustainability', 'topics',           'json', '["Disclosure frameworks and their public sector fit","Materiality assessment","Emissions and resource data collection","Climate risk in fiscal planning","Assurance readiness","Reporting to oversight and lenders"]', 130),
('service-sustainability', 'audience_title',   'text', 'Who it is for', 140),
('service-sustainability', 'audience',         'json', '["Finance directors and reporting managers","Internal auditors","Planning and economic affairs officials","Debt management office staff","Sub-national finance officers"]', 150),
('service-sustainability', 'format_title',     'text', 'How it is taught', 160),
('service-sustainability', 'format_body',      'text', 'Five days, residential, with the cohort capped so that faculty stay reachable throughout. Delegates work on their own institution''s reporting scope through the week, and day five is spent building the action plan each delegate takes back to their department. Every school carries CPD certification.', 170),
('service-sustainability', 'events_title',     'text', 'Schools covering this pillar', 180),
('service-sustainability', 'events_empty',     'text', 'No school covering this pillar is in the 2026 calendar yet. Contact the programme office and we will tell you when one is scheduled.', 190),
('service-sustainability', 'related_tags',     'json', '["Sustainability","Climate","Disclosure"]', 200),
('service-sustainability', 'cta_eyebrow',      'text', 'Next step', 210),
('service-sustainability', 'cta_title',        'text', 'Ask for the full course outline', 220),
('service-sustainability', 'cta_body',         'text', 'The programme office replies within one working day, and will say plainly if a different pillar fits your team better.', 230),
('service-sustainability', 'cta_label',        'text', 'Contact the programme office', 240);

-- ── Contact ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('contact', 'office_title',           'text', 'Head office', 100),
('contact', 'phone_title',            'text', 'Telephone', 110),
('contact', 'email_title',            'text', 'Email', 120),
('contact', 'hours_title',            'text', 'Office hours', 130),
('contact', 'hours_value_html',       'html', 'Monday to Friday<br>8am to 5pm EAT', 140),
('contact', 'form_label_name',        'text', 'Full name', 150),
('contact', 'form_label_organisation','text', 'Institution', 160),
('contact', 'form_label_email',       'text', 'Email', 170),
('contact', 'form_label_phone',       'text', 'Phone', 180),
('contact', 'form_label_message',     'text', 'Enquiry', 190),
('contact', 'form_hint_message',      'text', 'Which school or pillar are you asking about?', 200),
('contact', 'form_optional_note',     'text', 'Institution and phone are optional.', 210),
('contact', 'form_submit_label',      'text', 'Send enquiry', 220),
('contact', 'form_consent_html',      'html', 'We use these details only to answer your enquiry. See our <a href="/privacy-policy.php">privacy policy</a>.', 230),
('contact', 'map_label',              'text', 'Moi Avenue, Nairobi', 240);

-- ── 404 ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('notfound', 'meta_title',       'text', 'Page not found', 5),
('notfound', 'meta_description', 'text', 'The page you asked for is not on this site. These are the routes most visitors are looking for.', 6),
('notfound', 'eyebrow',          'text', 'Not found', 15),
('notfound', 'routes',           'json', '[{"eyebrow":"Calendar","label":"The 2026 schools","href":"/index.php#events"},{"eyebrow":"Services","label":"The three pillars","href":"/services.php"},{"eyebrow":"Sponsorship","label":"Partner with a school","href":"/sponsorship.php"},{"eyebrow":"Contact","label":"Programme office","href":"/contact.php"}]', 40);
