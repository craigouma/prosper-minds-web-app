-- Migration 2026-08-28-03 (UP): seed page_content
--
-- The real copy for the rebuilt site, taken from the approved prototype
-- (prototype/Prosperminds Site.dc.html) and the design brief it was built from
-- (prototype/uploads/WEBSITE-REDESIGN-PROMPT-dfed5000.md, Revisions 3 and 4).
-- Nothing here is invented: every sentence exists in the approved design.
--
-- Requires 2026-08-28-01-create-page-content.up.sql to have been applied, or
-- the table to have been created on demand by ensurePageContentSchema().
--
-- INSERT IGNORE, NOT UPSERT — this is deliberate.
--
-- Re-running this file must never overwrite copy someone has edited. Today
-- there is no CMS and the only editor is a developer, but from Phase 5 these
-- rows are what staff edit through an admin screen, and a seed that clobbers
-- their work on the next deploy is a trap. INSERT IGNORE fills in what is
-- missing and leaves everything else alone, so the file is safe to apply
-- repeatedly and safe to apply to a database that has already diverged.
--
-- WHAT IS NOT SEEDED HERE, ON PURPOSE
--
--   * Event titles, dates, cities and prices. Those live in the `events` table
--     and are read from it. They are also the subject of an open client
--     question — the titles in the client's onboarding email differ from the
--     live database titles for all four 2026 events (PROJECT.md section 4c,
--     REBUILD-PLAN.md section 4.3) — and duplicating either version into a
--     second table would make that worse, not better.
--   * Sponsorship tiers, prices and package matrices. Phase 3 builds that page
--     against the existing live content; seeding a partial copy of a price list
--     that is already published elsewhere is how the two drift apart.
--   * Anything with a delegate's name in it. The testimonials seeded below are
--     the role-and-institution attributions used in the approved prototype
--     ("Chief Accountant, Ministry of Finance, Kenya"), not named individuals.
--   * The prototype's two dated marketing sentences — "Seats for the October
--     cohort close on 12 September" and "Twenty per cent applies until 15 July
--     2026". Those are prototype placeholders, not confirmed deadlines, and a
--     wrong deadline on a page that sells USD 599 seats is worse than no
--     deadline. home.cta_title is therefore intentionally absent; Phase 2 must
--     get the real wording from the client before rendering that band.
--
-- content_type is set per row: 'text' for anything printed as words, 'html'
-- where the value is markup by declaration (only the two address blocks), and
-- 'json' for repeated blocks — a list of statistics is one row, not four
-- numbered keys. includes/content.php escapes 'text' and 'json' on output and
-- passes 'html' through, which is the whole reason the column exists.
--
-- Reverse with: 2026-08-28-03-seed-page-content.down.sql

-- ── Global: used by the shared header and footer on every page ─────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('global', 'tagline',            'text', 'Protecting and growing the mind to achieve prosperity.', 10),
('global', 'newsletter_promise', 'text', 'Course dates and early-bird deadlines, sent when they are confirmed.', 20),
('global', 'address_html',       'html', 'Twiga Towers, Moi Avenue<br>Nairobi, Kenya<br>Mon to Fri, 8am to 5pm', 30),
('global', 'email',              'text', 'info@prosper-minds.com', 40),
('global', 'phone_primary',      'text', '+254 740 582302', 50),
('global', 'phone_secondary',    'text', '+254 741 174909', 60),
('global', 'office_hours',       'text', 'Monday to Friday, 8am to 5pm EAT', 70);

-- ── Homepage ───────────────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('home', 'meta_title',         'text', 'Prosperminds | Public Finance, IPSAS, AI and Sustainability Training', 10),
('home', 'meta_description',   'text', 'Prosperminds trains senior government finance officials across Africa in public finance management, IPSAS and IFRS reporting, data analytics, AI automation and sustainability disclosure.', 20),
('home', 'hero_eyebrow',       'text', 'Executive PFM training, 2026 calendar', 30),
('home', 'hero_title',         'text', 'Strong systems start with strong people', 40),
('home', 'hero_body',          'text', 'Prosperminds trains senior government finance officials across Africa in public finance management, IPSAS and IFRS reporting, data analytics, AI automation and sustainability disclosure. Five-day residential courses, delivered by practitioners.', 50),
('home', 'hero_cta_primary',   'text', 'View the 2026 calendar', 60),
('home', 'hero_cta_secondary', 'text', 'Register a delegate', 70),
('home', 'hero_facts',         'json', '[{"value":"25","label":"Years collective experience"},{"value":"875","label":"Leaders trained"},{"value":"4","label":"Schools in 2026"},{"value":"5","label":"Day residential format"}]', 80),
('home', 'events_eyebrow',     'text', 'Upcoming courses', 90),
('home', 'events_title',       'text', 'Four flagship events', 100),
('home', 'pillars_eyebrow',    'text', 'What we teach', 110),
('home', 'pillars_title',      'text', 'Three pillars of public finance capability', 120),
('home', 'record_eyebrow',     'text', 'Track record', 130),
('home', 'record_title',       'text', 'Twenty-five years in the room', 140),
('home', 'record_body',        'text', 'Our faculty has spent a quarter of a century inside treasuries, audit offices and accountant-general departments. Every course is built from that work, then tested against the standards delegates are held to when they return.', 150),
('home', 'stats',              'json', '[{"value":"25","label":"Years collective experience"},{"value":"875","label":"Leaders trained"},{"value":"14","label":"Countries represented"},{"value":"5","label":"Days per school"}]', 160),
('home', 'testimonials_eyebrow', 'text', 'Delegate feedback', 170),
('home', 'testimonials_title',   'text', 'In their words', 180),
('home', 'testimonials',         'json', '[{"quote":"The reconciliation workflow we built during the automation module cut our monthly reporting time by nine days. It is still running two years later.","role":"Chief Accountant","org":"Ministry of Finance, Kenya"},{"quote":"We arrived with three unresolved audit queries on asset recognition. We left with a documented position on all three and the working papers to support it.","role":"Auditor General office","org":"Ghana"},{"quote":"The faculty had done the job. That mattered. Nobody was explaining accrual accounting to us from a textbook.","role":"Treasury Leader","org":"Federal Ministry of Finance, Nigeria"},{"quote":"Our budget monitoring pack went from a backward-looking report to something the cabinet secretary reads before decisions. That change started in Bali.","role":"Strategy Director","org":"Ministry of Finance, Rwanda"}]', 190),
('home', 'cta_body',           'text', 'Early-bird pricing is tiered by registration date.', 200),
('home', 'cta_label',          'text', 'Start registration', 210);

-- ── Events calendar ────────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('events', 'meta_title',       'text', 'CPD calendar | Prosperminds', 10),
('events', 'meta_description', 'text', 'Every Prosperminds residential school, with dates, locations and early-bird deadlines confirmed twelve months ahead.', 20),
('events', 'hero_eyebrow',     'text', 'CPD calendar', 30),
('events', 'hero_title',       'text', 'Courses and residential schools', 40),
('events', 'hero_body',        'text', 'Every course runs five days, carries CPD certification and is capped to keep faculty access high. Dates are confirmed twelve months ahead so departments can budget for them.', 50),
('events', 'past_note',        'text', 'Past cohorts are listed for reference. Delegate materials remain available through the alumni portal for twelve months after each school.', 60),
('events', 'banners_eyebrow',  'text', 'Banner library', 70),
('events', 'banners_title',    'text', 'Current promotional banners', 80),
('events', 'banners_body',     'text', 'Every live event banner in one place, at the size it is published. Pull from here for LinkedIn and partner mailings so the version in circulation is always the current one.', 90);

-- ── About ──────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('about', 'meta_title',        'text', 'About Prosperminds', 10),
('about', 'meta_description',  'text', 'A training institution for the public sector, working with ministries of finance, audit offices, revenue authorities and state corporations across Africa.', 20),
('about', 'hero_eyebrow',      'text', 'About Prosperminds', 30),
('about', 'hero_title',        'text', 'Protecting and growing the mind to achieve prosperity', 40),
('about', 'hero_body',         'text', 'Prosperminds is a training institution for the public sector. We work with ministries of finance, audit offices, revenue authorities and state corporations across Africa, and increasingly with international delegations attending our residential schools.', 50),
('about', 'work_title',        'text', 'How we work', 60),
('about', 'work_body_1',       'text', 'Our faculty is drawn from practice. Between them they carry twenty-five years of collective experience inside treasuries, accountant-general departments and supreme audit institutions. Courses are written from that work rather than from a syllabus, then revised each year against the standards delegates are actually held to.', 70),
('about', 'work_body_2',       'text', 'Every school runs for five days in a residential format. Day one establishes leadership context, days two to four go deep on technical material, and day five is spent building the action plan each delegate takes back to their department. Cohorts are capped so that faculty remain reachable throughout.', 80),
('about', 'work_body_3',       'text', 'Eight hundred and seventy-five leaders have completed a Prosperminds school. Many return with colleagues, and a growing number return as contributors.', 90),
('about', 'outcomes_title',    'text', 'What delegates leave with', 100),
('about', 'outcomes',          'json', '["A departmental action plan reviewed by faculty","CPD certification recognised by professional bodies","Working templates, not slide decks","A peer network across finance functions in the region"]', 110),
('about', 'pillars_eyebrow',   'text', 'Practice areas', 120),
('about', 'pillars_title',     'text', 'The three pillars in depth', 130);

-- ── Services. One row holds all three pillars, because they are one list. ──
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('services', 'meta_title',       'text', 'Services | Prosperminds', 10),
('services', 'meta_description', 'text', 'Three pillars of public finance capability: PFM, IPSAS and IFRS mastery; data analytics and AI automation; sustainability reporting.', 20),
('services', 'hero_eyebrow',     'text', 'Services', 30),
('services', 'hero_title',       'text', 'Three pillars of public finance capability', 40),
('services', 'pillars',          'json', '[{"key":"pfm","num":"01","name":"PFM, IPSAS and IFRS Mastery","promise":"Build the technical foundation your finance teams need.","intro":"Accrual accounting, disclosure and audit readiness for institutions that are judged on their financial statements.","outcomes":["Statements that reconcile to the ledger and survive audit","A defensible position on asset recognition and measurement","A transition plan from cash to accrual reporting","Reporting timetables that hold under pressure"],"topics":["IPSAS presentation and disclosure","Revenue and expenditure recognition","Asset registers and componentisation","Consolidation boundaries","Audit file construction","IFRS for state corporations"]},{"key":"data","num":"02","name":"Data Analytics and AI Automation","promise":"Transform reporting from burden to strategic advantage.","intro":"Practical analytics and automation for finance functions that still spend most of the month closing the books.","outcomes":["Reconciliation and consolidation work reduced to hours","Forecasts leadership is willing to act on","Anomaly detection built into the audit cycle","A governance position on AI tools before procurement"],"topics":["Reporting cycle automation","Data quality controls","Revenue and expenditure forecasting","Fraud and anomaly analytics","Dashboard design for oversight","AI governance and procurement"]},{"key":"sustainability","num":"03","name":"Sustainability Reporting","promise":"Meet global standards while strengthening transparency.","intro":"Climate and sustainability disclosure for public institutions now being asked for it by lenders, auditors and citizens.","outcomes":["A disclosure scope decision you can defend","Data collection assigned to real owners","Alignment with lender and donor requirements","Reporting integrated with the financial calendar"],"topics":["Disclosure frameworks and their public sector fit","Materiality assessment","Emissions and resource data collection","Climate risk in fiscal planning","Assurance readiness","Reporting to oversight and lenders"]}]', 50),
('services', 'outcomes_title',   'text', 'Why departments send teams', 60),
('services', 'curriculum_title', 'text', 'Curriculum coverage', 70),
('services', 'cta_label',        'text', 'Request the full outline', 80);

-- ── Contact ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('contact', 'meta_title',        'text', 'Contact | Prosperminds', 10),
('contact', 'meta_description',  'text', 'Talk to the Prosperminds programme office in Nairobi about course dates, group registrations and consolidated quotes.', 20),
('contact', 'hero_eyebrow',      'text', 'Contact', 30),
('contact', 'hero_title',        'text', 'Talk to the programme office', 40),
('contact', 'form_title',        'text', 'Send an enquiry', 50),
('contact', 'form_intro',        'text', 'The programme office replies within one working day. For group registrations of four or more delegates, mention the number and we will issue a consolidated quote.', 60),
('contact', 'address_html',      'html', 'Twiga Towers, Moi Avenue<br>Nairobi, Kenya', 70),
('contact', 'directions_title',  'text', 'Getting here', 80),
('contact', 'directions_body',   'text', 'Twiga Towers sits on Moi Avenue in the central business district, a ten minute walk from the railway station and served by matatu routes along Tom Mboya Street. Visitor parking is available on the lower level.', 90);

-- ── Sponsorship. Hero only; the tier matrix stays where it is until Phase 3. ─
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('sponsorship', 'meta_title',       'text', 'Sponsorship | Prosperminds', 10),
('sponsorship', 'meta_description', 'text', 'A business-to-government partnership placing sponsors in the room with accountants general, auditors general, treasury leaders and budget controllers.', 20),
('sponsorship', 'hero_eyebrow',     'text', 'Sponsorship', 30),
('sponsorship', 'hero_title',       'text', 'Co-Author Africa''s Public Finance Future', 40),
('sponsorship', 'hero_body',        'text', 'This is a business-to-government partnership, not advertising space. Sponsors sit in the room with accountants general, auditors general, treasury leaders and budget controllers for five days, as contributors to the programme rather than names on a banner.', 50),
('sponsorship', 'events_eyebrow',   'text', 'Eligible events', 60),
('sponsorship', 'events_title',     'text', 'Four flagship schools in 2026', 70);

-- ── 404 ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('notfound', 'code',    'text', '404', 10),
('notfound', 'title',   'text', 'This page is not on the site', 20),
('notfound', 'body',    'text', 'The address may have changed, or the course it referred to has already run. These four routes cover most of what people arrive looking for.', 30);
