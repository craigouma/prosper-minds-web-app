-- Migration 2026-08-29-03 (UP): seed page_content for the Phase 4 register page
--
-- Every visible string on event-registration.php, under the new 'register'
-- slug. Same conventions as migrations 03, 05, 2026-08-29-01 and -02:
--   * INSERT IGNORE, never an upsert, so a re-run cannot clobber a CMS edit.
--   * content_type set honestly per row.
--   * No em dashes in any value.
--   * Every key here is also an inline default at its call site, saying the
--     same words, so an unseeded or unreachable table costs the page nothing.
--
-- NO PRICE, NO CURRENCY, NO DISCOUNT AND NO TIER NAME IS SEEDED. The unit
-- price and the total are computed on every render from events.price by the
-- same patterns parseEventPrice() uses in the handler, so the invoice summary
-- cannot go stale or disagree with what process-registration.php charges. A
-- seeded figure here would be a number a CMS user could edit without the
-- invoice following it.
--
-- register.tier_note is the one row that talks about tiers. It says places are
-- invoiced at the standard delegate rate, which is what the handler does. It is
-- deliberately not a price list, and there is no early bird row: the handler
-- applies no discount, and Phase 5 owns that change.

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
