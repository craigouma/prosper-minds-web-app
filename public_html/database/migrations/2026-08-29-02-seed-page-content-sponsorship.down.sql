-- Migration 2026-08-29-02 (DOWN): unseed the sponsorship page_content rows
--
-- Removes exactly what 2026-08-29-02-seed-page-content-sponsorship.up.sql
-- inserted and nothing else. Migration 03 also seeds the 'sponsorship' slug
-- (the meta tags, the hero and the eligible events heading), so this cannot
-- delete the slug wholesale: doing that would take migration 03's rows with it
-- and leave the page with no hero at all.
--
-- READ THIS BEFORE RUNNING IT. From Phase 5 onwards these rows are the
-- sponsorship offer itself: prices, slot counts and every benefit line. They
-- are exactly the rows most likely to have been edited by staff after launch,
-- and this deletes them regardless of who last changed them. Export first.
--
-- Deleting them does not break the page. Every call site in sponsorship.php
-- passes the same words inline as its default, including the four tiers, the
-- three packages and the five add-ons, so an unseeded page renders the offer as
-- the developer wrote it rather than showing empty sections.

DELETE FROM `page_content`
 WHERE `page_slug` = 'sponsorship'
   AND `section_key` IN (
        'hero_cta_primary', 'hero_cta_secondary',
        'why_eyebrow', 'why_title', 'why_body', 'why_cards',
        'events_body', 'events_cta', 'events_empty',
        'gains_eyebrow', 'gains_title', 'gains_body', 'gains',
        'audience_title', 'audience_body', 'audience_tags',
        'promise_label', 'promise_text',
        'tiers_eyebrow', 'tiers_title', 'tiers_body', 'tiers_cta', 'tiers',
        'packages_eyebrow', 'packages_title', 'packages',
        'addons_eyebrow', 'addons_title', 'addons_body', 'addons', 'addons_option',
        'cta_title', 'cta_body', 'cta_label',
        'form_eyebrow', 'form_title', 'form_body',
        'form_label_first', 'form_label_last', 'form_label_org',
        'form_label_email', 'form_label_phone', 'form_label_country',
        'form_label_events', 'form_hint_events',
        'form_label_tier', 'form_tier_none',
        'form_label_message', 'form_hint_message',
        'form_submit', 'form_note', 'form_consent_html', 'form_required_note'
   );
