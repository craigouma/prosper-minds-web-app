-- Migration 2026-08-28-05 (DOWN): unseed the Phase 2 page_content rows
--
-- Removes exactly what 2026-08-28-05-seed-page-content-phase2.up.sql inserted
-- and nothing else. It does not drop the table, and it does not touch the rows
-- migration 03 owns.
--
-- WHY THIS ONE IS NOT SCOPED BY page_slug ALONE
--
-- Migration 03's down migration deletes by page_slug, because it owns every row
-- under the slugs it seeds. This file does not: it adds rows to 'home',
-- 'about', 'services', 'contact' and 'notfound', which migration 03 also seeds.
-- Deleting those slugs wholesale here would silently take migration 03's rows
-- with them and leave a site running on nothing but its inline defaults.
--
-- So the five shared slugs are unseeded by explicit (page_slug, section_key)
-- pair, and only the three slugs this file introduced on its own are deleted
-- whole.
--
-- READ THIS BEFORE RUNNING IT. From Phase 5 onwards these same rows are what
-- staff edit through the CMS, and this deletes them regardless of who last
-- changed them. On a database where anyone has edited site copy this is
-- destructive in a way the up migration is not. Export first.
--
-- Deleting them does not break the site: every Phase 2 page passes its own
-- inline default to pmContentSafe(), so an unseeded page renders the copy the
-- developer wrote rather than blanks. That is the same behaviour the content
-- layer produces when the whole table is missing, and local-dev/verify.sh
-- section 10f proves it against the real pages.

-- The three slugs this file introduced on its own.
DELETE FROM `page_content`
 WHERE `page_slug` IN ('service-pfm', 'service-data', 'service-sustainability');

-- Rows added to slugs migration 03 already owns, by exact key.
DELETE FROM `page_content`
 WHERE `page_slug` = 'home'
   AND `section_key` IN (
        'cta_eyebrow', 'cta_title_template', 'cta_title_lapsed', 'cta_body_lapsed',
        'early_bird_lapsed_label', 'event_details_label', 'event_register_label',
        'events_empty', 'pillars_cta_label', 'record_cta_label'
   );

DELETE FROM `page_content`
 WHERE `page_slug` = 'about'
   AND `section_key` IN ('stats', 'pillars_cta_label');

DELETE FROM `page_content`
 WHERE `page_slug` = 'services'
   AND `section_key` IN (
        'hero_body', 'pillar_cta_label', 'events_eyebrow', 'events_title',
        'events_empty', 'cta_eyebrow', 'cta_title', 'cta_body'
   );

DELETE FROM `page_content`
 WHERE `page_slug` = 'contact'
   AND `section_key` IN (
        'office_title', 'phone_title', 'email_title', 'hours_title', 'hours_value_html',
        'form_label_name', 'form_label_organisation', 'form_label_email',
        'form_label_phone', 'form_label_message', 'form_hint_message',
        'form_optional_note', 'form_submit_label', 'form_consent_html', 'map_label'
   );

DELETE FROM `page_content`
 WHERE `page_slug` = 'notfound'
   AND `section_key` IN ('meta_title', 'meta_description', 'eyebrow', 'routes');
