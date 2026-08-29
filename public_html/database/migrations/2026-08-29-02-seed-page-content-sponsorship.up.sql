-- Migration 2026-08-29-02 (UP): seed page_content for the sponsorship page
--
-- Migration 03 seeded the sponsorship hero only, with a note that "the tier
-- matrix stays where it is until Phase 3". This is Phase 3, and this file is
-- that matrix plus the rest of the page.
--
-- WHY THIS MIGRATION IS LARGE
--
-- Every word of the sponsorship offer was hardcoded in sponsorship.php: four
-- tiers with thirty benefit lines between them, three specialised packages,
-- five add-ons, eight partnership benefits and nine audience labels. It is the
-- most commercially volatile copy on the site (prices, slot counts and what a
-- sponsor actually gets all move between events) and it was the copy least
-- possible to change without a developer. Moving it into page_content now is
-- what makes the Phase 5 CMS an admin screen over an existing model rather than
-- a refactor of this page.
--
-- Same conventions as migrations 03, 05 and 2026-08-29-01:
--   * INSERT IGNORE, never an upsert.
--   * content_type honest per row. The repeated blocks are 'json', because one
--     row holding a list is more truthful than fifteen numbered keys, and
--     because a tier is a record with fields rather than a sentence.
--   * No em dashes in any value. The live page used them in four places and
--     each is rewritten here with a full stop or a colon, never with an en dash
--     or a double hyphen:
--        "not looking for service providers - they are looking for trusted
--         partners"            becomes two sentences
--        "Africa's real system change - shaping the conversation"
--                              becomes a colon
--        "More Than a Sponsorship - a Partnership"
--                              becomes a comma, as instructed
--        "We do not sell packages - we build partnerships"
--                              becomes two sentences
--   * Every key is also an inline default at its call site saying the same
--     words, so an unreachable table costs the page nothing.
--
-- WHAT IS DELIBERATELY NOT SEEDED
--
-- The four events. The live page hardcoded three of them and so had never
-- listed Mombasa at all, seven months after it was added to the calendar. The
-- rebuilt page reads the `events` table, so it lists whatever is scheduled and
-- cannot fall behind again. The tier `key` values below ARE seeded, because
-- sponsorship.php validates ?tier= against them before pre-selecting the form.

-- ── Hero and section headings ──────────────────────────────────────────────
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
('sponsorship', 'tiers',         'json', '[{"key":"platinum","name":"Platinum","price":"$15,000","slots":"3 slots remaining","benefits":["Keynote and plenary speaking slot","Branding across every platform: digital, print and press","Sponsor video aired daily","Five VIP passes","Logo on delegate lanyards and bags","VIP roundtable with government leaders","Full page advertisement in the programme and the post event report","Prime exhibition space"]},{"key":"gold","name":"Gold","price":"$7,500","slots":"5 slots remaining","benefits":["Host and brand a data or IPSAS theme session","Three VIP passes","Branding on the event app and session screens","Mid tier exhibition space","Half page advertisement in the programme","Joint press feature with Prosperminds","Speaking role","Social media spotlight campaign"]},{"key":"silver","name":"Silver","price":"$4,000","slots":"10 slots remaining","benefits":["Speaking role","Three delegate passes","Logo on key branding points","Half page advertisement in the programme","Featured in Prosperminds publications","Website branding","Social media spotlight","Exhibition space"]},{"key":"bronze","name":"Bronze","price":"$2,000","slots":"10 slots remaining","benefits":["Speaker or moderator role","Three delegate passes","Logo in the programme","Website branding","Social media spotlight","Exhibition space"]}]', 300);

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
