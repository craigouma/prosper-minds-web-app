-- Go live: paste this whole file into phpMyAdmin, SQL tab, on
-- kidsmone_Prosperminds_website. Run it AFTER the code is deployed.
--
-- It does four things and nothing else. It creates no tables: the new system
-- creates those itself the first time a screen needs them.
--
-- Safe to run twice. Every statement is either idempotent or scoped so a second
-- run changes nothing.

-- Run everything against this database, whatever is selected on the left.
USE `kidsmone_Prosperminds_website`;

START TRANSACTION;

-- 1. The last trace of the currency parsing bug.
--    Registration 9's PDF was regenerated in August, the row was not.
UPDATE `event_registrations`
   SET `currency_code` = 'USD'
 WHERE `id` = 9 AND `currency_code` = 'FRO';

-- 2. Give Shillah and Lydia the content screens.
--    Their existing rights are unchanged and the CMS modules are added. Accounting,
--    the audit log and redirects stay with super admins.
UPDATE `admin_users`
   SET `permissions` = '{"dashboard":["view"],"registrations":["view","export"],"events":["view","create","edit","toggle"],"users":["view","create","edit"],"settings":["view"],"content":["view","create","edit","publish","delete"],"media":["view","upload","edit","delete"],"menus":["view","edit"],"submissions":["view","handle","export"],"seo":["view","edit"]}'
 WHERE `username` = 'shillah';

UPDATE `admin_users`
   SET `permissions` = '{"dashboard":["view"],"registrations":["view","export"],"events":["view","create","edit","toggle"],"users":["view"],"settings":["view"],"content":["view","create","edit","publish","delete"],"media":["view","upload","edit","delete"],"menus":["view","edit"],"submissions":["view","handle","export"],"seo":["view","edit"]}'
 WHERE `username` = 'lydia';

-- 3. The brand details.
--    The same values the Settings button writes. Doing it here as well means the
--    site is correct the moment it is deployed, before anyone signs in.
--    SMTP settings are deliberately absent: they hold the live mail password.
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
  ('site_title',      'Prosperminds'),
  ('site_tagline',    'Public finance training for the people who sign the accounts'),
  ('company_name',    'Prosperminds'),
  ('contact_email',   'info@prosper-minds.com'),
  ('admin_email',     'info@prosper-minds.com'),
  ('contact_phone',   '+254 740 582302'),
  ('contact_address', 'Nairobi, Kenya'),
  ('social_linkedin', 'https://www.linkedin.com/company/prosper-minds-technologies/'),
  ('social_facebook', 'https://www.facebook.com/share/1EvKA1GF5w/?mibextid=wwXIfr'),
  ('company_color',   '#00BF63')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 4. Only Cape Town and Mombasa run for now.
--
--    READ THIS BEFORE RUNNING. Taking an event off the site also makes its page
--    return 404, because a page stays reachable only while the event is active
--    or its date has already passed. Both of these are still in the future.
--
--    Bali has no registrations, so nothing is affected.
--
--    Kuala Lumpur has THREE registrations totalling USD 2,396. Those delegates
--    would find a dead page if they returned to the course they paid for. If
--    that is not what you want, delete the line for event 2 below and it stays
--    live; everything else in this file is unaffected.
--
--    Nothing is deleted either way. The rows, the registrations and the
--    invoices all remain, and an event returns by setting is_active back to 1.
UPDATE `events` SET `is_active` = 1 WHERE `id` IN (1, 5);
UPDATE `events` SET `is_active` = 0 WHERE `id` IN (2, 3);

-- Deliberately NOT done: normalising event_registrations.event_name.
--    Older rows record wordings such as "Smart PFM & IPSAS Future Ready Finance
--    Leaders Course" against an event now titled differently. That column is a
--    record of what the delegate actually booked, and rewriting it would edit
--    history to match a later marketing decision.
--
-- Deliberately NOT done: touching the early bird tiers on events 1, 2, 3 and 5.
--    The existing courses keep the dates they were advertised with. Early bird
--    applies to new events from here on.

COMMIT;

-- Check it worked. Expect exactly 0, 2 and 15. Fifteen rather than eighteen
-- because admin_email, company_name and company_color already existed.
SELECT (SELECT COUNT(*) FROM `event_registrations` WHERE `currency_code` = 'FRO') AS fro_rows_should_be_0,
       (SELECT COUNT(*) FROM `admin_users` WHERE `permissions` LIKE '%"content"%')  AS editors_with_cms_should_be_2,
       (SELECT COUNT(*) FROM `kidsmone_Prosperminds_website`.`site_settings`)                                       AS settings_should_be_15,
       (SELECT COUNT(*) FROM `events` WHERE `is_active` = 1)                        AS live_events_should_be_2;

-- The two that stay live, for a final look.
SELECT `id`, `title`, `location`, `is_active`
  FROM `kidsmone_Prosperminds_website`.`events` ORDER BY `sort_order`, `id`;
