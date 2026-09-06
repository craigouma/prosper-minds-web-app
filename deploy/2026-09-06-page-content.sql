-- Starter content for the website copy.
--
-- Site health reports "No content rows" because the tables the new system uses
-- are created on demand, but the copy that goes in them ships as migration
-- files, and nothing had run those on the live database.
--
-- Nothing is broken while this is missing. Every page carries its own copy as a
-- fallback, which is why the site reads correctly today. What is missing is the
-- editable version: the SEO screen shows empty search titles, and there is no
-- stored copy for anyone to change.
--
-- This is the five seed migrations from public_html/database/migrations/,
-- concatenated so it can be pasted in one go. Every statement is INSERT IGNORE,
-- so running it twice adds nothing and, importantly, never overwrites copy that
-- somebody has already edited.
--
-- Paste into phpMyAdmin and press Go. It does not matter which database is
-- selected on the left.

USE `kidsmone_Prosperminds_website`;

-- ---------- 2026-08-28-03-seed-page-content.up.sql ----------
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

-- ---------- 2026-08-28-05-seed-page-content-phase2.up.sql ----------
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('global', 'early_bird_lapsed_label', 'text', 'Standard rate', 80),
('global', 'event_details_label',     'text', 'Details', 81),
('global', 'event_register_label',    'text', 'Register', 82);

-- ── Homepage: the computed closing band, and the labels the grid needs ──────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('home', 'cta_eyebrow',             'text', 'Registration', 195),
('home', 'cta_title_template',      'text', 'Save {pct} per cent on the {city} school until {date}', 196),
('home', 'cta_title_lapsed',        'text', 'Registration is open for the 2026 residential schools', 197),
('home', 'cta_body_lapsed',         'text', 'Standard delegate rates apply. Cohorts are capped, so a place is worth confirming early.', 198),
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

-- ---------- 2026-08-29-01-seed-page-content-events.up.sql ----------
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

-- ---------- 2026-08-29-02-seed-page-content-sponsorship.up.sql ----------
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('sponsorship', 'hero_cta_primary',   'text', 'Become a partner', 80),
('sponsorship', 'hero_cta_secondary', 'text', 'View packages', 90),

('sponsorship', 'why_eyebrow', 'text', 'Why this moment matters', 100),
('sponsorship', 'why_title',   'text', 'Traditional marketing will not get you into that room. This event will.', 110),
('sponsorship', 'why_body',    'text', 'Africa''s public sector is transforming faster than ever. The professionals in the room are not looking for service providers. They are looking for trusted partners who can support real implementation.', 120),
('sponsorship', 'why_cards',   'json', '[{"title":"While others advertise","body":"You will be remembered. Your brand is part of the experience of Africa''s most influential finance leaders, not an advertisement beside it."},{"title":"While others pitch","body":"You will be partnering. Direct business to government engagement with the people who implement policy and control budgets."},{"title":"While others wait","body":"You will already be part of Africa''s real system change: shaping the conversation rather than watching it from the outside."}]', 130),

('sponsorship', 'events_body',    'text', 'Each school draws senior public finance officials from across the continent and beyond. Sponsor one, or take the whole 2026 calendar.', 140),
('sponsorship', 'events_cta',     'text', 'Sponsor this event', 150),
('sponsorship', 'events_empty',   'text', 'The 2026 calendar is being confirmed. Send an enquiry and we will come back to you with dates and audience numbers.', 160),

('sponsorship', 'gains_eyebrow',  'text', 'What you gain', 170),
('sponsorship', 'gains_title',    'text', 'More than a sponsorship, a partnership', 180),
('sponsorship', 'gains_body',     'text', 'We do not sell packages. We build partnerships, and we align the platform to what your organisation is actually trying to achieve.', 190),
('sponsorship', 'gains',          'json', '["Strong visibility before, during and after the event","Direct access to public finance practitioners and decision influencers","Business to government engagement opportunities","Thought leadership through sessions and workshops","Brand positioning as a trusted implementation partner","Entry into new African government markets","A platform to show your solutions to the leaders who implement policy","A clear message that you are part of Africa''s transformation"]', 200),

('sponsorship', 'audience_title', 'text', 'Who will be in the room', 210),
('sponsorship', 'audience_body',  'text', 'Hundreds of the public sector leaders who set, spend and account for public money.', 220),
('sponsorship', 'audience_tags',  'json', '["Finance officers","Accountants","Auditors","Budget controllers","Treasury leaders","Revenue managers","Strategy directors","Decision makers","Policy implementers"]', 230),
('sponsorship', 'promise_label',  'text', 'Our promise to you', 240),
('sponsorship', 'promise_text',   'text', 'Relevant. Reliable. Convenient.', 250);

-- ── The four tiers ─────────────────────────────────────────────────────────
-- `key` is a route, not copy: sponsorship.php matches ?tier= against it and
-- falls back to the neutral option when it does not match, so a hand edited
-- query string cannot put anything into the markup. Renaming a tier in the CMS
-- is safe; changing its key breaks the deep link from its own Enquire button
-- and nothing else.
--
-- PRICES. Silver and Bronze here are 4,000 and 2,000, which is what the
-- approved prototype shows and what the phase brief specifies. The live page
-- shows 5,000 and 2,500. That is a real difference on a live commercial page
-- and PHASE3-PROGRESS.md flags it for the client to confirm before cutover.
-- Slot counts are the live page's own numbers, which are the operational ones.
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('sponsorship', 'tiers_eyebrow', 'text', 'Sponsorship packages', 260),
('sponsorship', 'tiers_title',   'text', 'Four partnership tiers', 270),
('sponsorship', 'tiers_body',    'text', 'Investment levels to match your goals and your budget. Every tier is available across every school in the calendar.', 280),
('sponsorship', 'tiers_cta',     'text', 'Enquire', 290),
('sponsorship', 'tiers',         'json', '[{"key":"platinum","name":"Platinum","price":"$15,000","slots":"3 slots remaining","benefits":["Keynote and plenary speaking slot","Branding across every platform: digital, print and press","Sponsor video aired daily","Five VIP passes","Logo on delegate lanyards and bags","VIP roundtable with government leaders","Full page advertisement in the programme and the post event report","Prime exhibition space"]},{"key":"gold","name":"Gold","price":"$7,500","slots":"5 slots remaining","benefits":["Host and brand a data or IPSAS theme session","Three VIP passes","Branding on the event app and session screens","Mid tier exhibition space","Half page advertisement in the programme","Joint press feature with Prosperminds","Speaking role","Social media spotlight campaign"]},{"key":"silver","name":"Silver","price":"$5,000","slots":"10 slots remaining","benefits":["Speaking role","Three delegate passes","Logo on key branding points","Half page advertisement in the programme","Featured in Prosperminds publications","Website branding","Social media spotlight","Exhibition space"]},{"key":"bronze","name":"Bronze","price":"$2,500","slots":"10 slots remaining","benefits":["Speaker or moderator role","Three delegate passes","Logo in the programme","Website branding","Social media spotlight","Exhibition space"]}]', 300);

-- ── Specialised packages and add-ons ───────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('sponsorship', 'packages_eyebrow', 'text', 'Entry packages', 310),
('sponsorship', 'packages_title',   'text', 'Specialised, $1,000 each', 320),
('sponsorship', 'packages',         'json', '[{"key":"gala-dinner","name":"Gala Dinner","price":"$1,000","slots":"2 slots remaining","benefits":["Exclusive gala dinner branding","Address guests at the dinner","Logo in the entertainment zones","Two passes and website branding"]},{"key":"digital-experience","name":"Digital Experience","price":"$1,000","slots":"2 slots remaining","benefits":["Sponsored push notifications","Logo on the session screens","One pass and website branding","Exhibition space"]},{"key":"exhibitor","name":"Exhibitor","price":"$1,000","slots":"10 slots remaining","benefits":["Named in the event materials","One pass and website branding","Exhibition space"]}]', 330),

('sponsorship', 'addons_eyebrow', 'text', 'Add ons', 340),
('sponsorship', 'addons_title',   'text', '$500 each', 350),
('sponsorship', 'addons_body',    'text', 'Targeted visibility that can be added to any package above.', 360),
('sponsorship', 'addons',         'json', '[{"name":"Lanyard sponsorship","price":"$500","slots":"3 slots remaining","benefit":"Your mark on every delegate lanyard for the full five days."},{"name":"Delegate bag","price":"$500","slots":"4 slots remaining","benefit":"Branding on the bag issued to each delegate at registration."},{"name":"Conference Wi-Fi","price":"$500","slots":"2 slots remaining","benefit":"Named on the network, with branding on the splash page and the daily access card."},{"name":"Mobile app","price":"$500","slots":"2 slots remaining","benefit":"Exclusive branding on the agenda app splash and the session reminders."},{"name":"Water station","price":"$500","slots":"2 slots remaining","benefit":"Branding at the refreshment points across the venue."}]', 370),
('sponsorship', 'addons_option',  'text', 'Add ons only, $500', 380);

-- ── Closing band and the enquiry form ──────────────────────────────────────
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('sponsorship', 'cta_title', 'text', 'Ready to become Africa''s partner in transformation?', 390),
('sponsorship', 'cta_body',  'text', 'A short call is enough to work out whether this is a fit. Every event that passes is a room you were not in.', 400),
('sponsorship', 'cta_label', 'text', 'Send a sponsorship enquiry', 410),

('sponsorship', 'form_eyebrow',       'text', 'Enquiry', 420),
('sponsorship', 'form_title',         'text', 'We do not sell packages. We build partnerships.', 430),
('sponsorship', 'form_body',          'text', 'Tell us which schools matter to you and what you want out of the room. A partnership lead replies within 48 hours with a proposal built around those goals.', 440),
('sponsorship', 'form_label_first',   'text', 'First name', 450),
('sponsorship', 'form_label_last',    'text', 'Last name', 460),
('sponsorship', 'form_label_org',     'text', 'Organisation', 470),
('sponsorship', 'form_label_email',   'text', 'Email', 480),
('sponsorship', 'form_label_phone',   'text', 'Phone', 490),
('sponsorship', 'form_label_country', 'text', 'Country', 500),
('sponsorship', 'form_label_events',  'text', 'Which schools are you interested in?', 510),
('sponsorship', 'form_hint_events',   'text', 'Choose at least one.', 520),
('sponsorship', 'form_label_tier',    'text', 'Tier of interest', 530),
('sponsorship', 'form_tier_none',     'text', 'Not sure yet, please advise', 540),
('sponsorship', 'form_label_message', 'text', 'Goals', 550),
('sponsorship', 'form_hint_message',  'text', 'What would make this partnership worthwhile for your organisation?', 560),
('sponsorship', 'form_submit',        'text', 'Send enquiry', 570),
('sponsorship', 'form_note',          'text', 'Replies within 48 hours, Monday to Friday.', 580),
('sponsorship', 'form_consent_html',  'html', 'We use these details only to answer your enquiry. See our <a href="/privacy-policy.php">privacy policy</a>.', 590),
('sponsorship', 'form_required_note', 'text', 'First name, last name, organisation, email and at least one school are required.', 600);

-- ---------- 2026-08-29-03-seed-page-content-register.up.sql ----------
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('register', 'eyebrow', 'text', 'Delegate registration', 100),
('register', 'done_eyebrow', 'text', 'Registration received', 110),
('register', 'done_title', 'text', 'Your place is confirmed', 120),
('register', 'done_body', 'text', 'Your invoice has been generated and emailed to the billing contact, together with the joining instructions.', 130),
('register', 'done_label_invoice', 'text', 'Invoice number', 140),
('register', 'done_label_amount', 'text', 'Amount due', 150),
('register', 'done_label_count', 'text', 'Delegates', 160),
('register', 'done_help', 'text', 'Need help right away? Call +254 740 582302 or +254 741 174909, or email info@prosper-minds.com.', 170),
('register', 'done_cta', 'text', 'Back to the calendar', 180),
('register', 'undriven_note', 'text', 'All four sections are on this page. Fill them in and submit once at the bottom.', 190),
('register', 'step1_title', 'text', 'Confirm event and tickets', 200),
('register', 'step1_body', 'text', 'Check the school and the dates, then set the number of delegates. The invoice summary updates as you go.', 210),
('register', 'school_label', 'text', 'Selected school', 220),
('register', 'per_delegate', 'text', 'per delegate', 230),
('register', 'change_school', 'text', 'Change school', 240),
('register', 'tier_note', 'text', 'Every place is invoiced at the standard delegate rate shown above. For VIP or VVIP arrangements, contact info@prosper-minds.com before registering.', 250),
('register', 'count_label', 'text', 'Number of delegates', 260),
('register', 'count_undriven', 'text', 'The number of delegates is however many you name in section 03 below. Leave the rest blank.', 270),
('register', 'step2_title', 'text', 'Contact and billing', 280),
('register', 'step2_body', 'text', 'The invoice is issued to the institution named here. This is also the address the confirmation and joining instructions are sent to.', 290),
('register', 'label_first', 'text', 'Billing contact first name', 300),
('register', 'label_last', 'text', 'Billing contact last name', 310),
('register', 'label_org', 'text', 'Institution', 320),
('register', 'label_email', 'text', 'Email', 330),
('register', 'label_phone', 'text', 'Phone', 340),
('register', 'label_country', 'text', 'Country', 350),
('register', 'label_address', 'text', 'Billing address', 360),
('register', 'hint_address', 'text', 'The address that should appear on the invoice.', 370),
('register', 'label_gender', 'text', 'Gender (optional)', 380),
('register', 'step3_title', 'text', 'Delegate details', 390),
('register', 'step3_body', 'text', 'Names are printed on certificates and used for visa support letters, so enter them as they appear on each passport.', 400),
('register', 'label_d_first', 'text', 'First name', 410),
('register', 'label_d_last', 'text', 'Last name', 420),
('register', 'label_d_email', 'text', 'Email', 430),
('register', 'label_d_title', 'text', 'Job title', 440),
('register', 'label_meal', 'text', 'Meal preference for the group', 450),
('register', 'hint_meal', 'text', 'One preference is recorded per registration. Tell us about individual requirements in the box below and we will arrange them.', 460),
('register', 'step4_title', 'text', 'Review and consent', 470),
('register', 'step4_body', 'text', 'Submitting generates a numbered invoice and emails it to the billing contact with the joining instructions.', 480),
('register', 'review_school', 'text', 'School', 490),
('register', 'review_dates', 'text', 'Dates', 500),
('register', 'review_delegates', 'text', 'Delegates', 510),
('register', 'review_payable', 'text', 'Payable', 520),
('register', 'label_topics', 'text', 'Topics you would like to see in future', 530),
('register', 'consent_text', 'text', 'I confirm the institution authorises this registration, and I consent to Prosperminds processing these details for invoicing, certification, visa support letters and course administration.', 540),
('register', 'consent_note_html', 'html', 'We use these details only to run this registration and the course. See our <a href="/privacy-policy.php">privacy policy</a>.', 550),
('register', 'submit_sending', 'text', 'Submitting', 560),
('register', 'submit', 'text', 'Complete registration', 570),
('register', 'nav_back', 'text', 'Back', 580),
('register', 'nav_next', 'text', 'Continue', 590),
('register', 'summary_head', 'text', 'Invoice summary', 600),
('register', 'summary_line', 'text', 'Delegate place', 610),
('register', 'summary_unit', 'text', 'Unit price, per delegate', 620),
('register', 'summary_total', 'text', 'Total', 630),
('register', 'summary_note', 'text', 'Payment by bank transfer or institutional purchase order. No card details are collected on this site.', 640)
;

-- The five step labels, as one json row so adding or renaming a step is a
-- content edit rather than a deploy. The register page reads the count from
-- this row, so "Step 1 of 5" cannot disagree with the number of segments.
INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('register', 'steps', 'json', '[{"num":"01","label":"Event and tickets"},{"num":"02","label":"Contact and billing"},{"num":"03","label":"Delegates"},{"num":"04","label":"Review and consent"},{"num":"05","label":"Confirmation"}]', 90);


-- Expect 349 rows across thirteen pages.
SELECT COUNT(*) AS content_rows_should_be_349 FROM `kidsmone_Prosperminds_website`.`page_content`;

SELECT `page_slug`, COUNT(*) AS rows_on_this_page
  FROM `kidsmone_Prosperminds_website`.`page_content`
 GROUP BY `page_slug` ORDER BY `page_slug`;
