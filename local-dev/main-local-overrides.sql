-- Local-only overrides applied AFTER the production dump is imported into the
-- throwaway Docker database. Runs only inside the local container; the
-- production dump file itself is never modified.
--
-- Why: the main site reads its SMTP settings from the `site_settings` table
-- (public_html/includes/config.php -> getSetting()). Left as-is, a local test
-- registration would try to authenticate against the REAL ProsperMinds mail
-- server and send real email to real registrants. These UPDATEs repoint every
-- mail setting at the local Mailpit container instead.
--
-- Mailpit runs plaintext with "accept any credentials", so smtp_secure is
-- blanked (config.php's else-branch sets SMTPSecure=false + SMTPAutoTLS=false).

-- page_content and cms_menu_items are NOT in the production dump: both are
-- created on demand, the first time a real request needs them
-- (ensureContentSchema(), ensureMenuSchema()), the same self-healing pattern
-- webinar_sessions uses further down. This file runs once at container init,
-- before any such request has ever happened, so the UPDATEs and INSERTs below
-- that touch either table need them to exist here first.
CREATE TABLE IF NOT EXISTS page_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(64) NOT NULL,
    section_key VARCHAR(96) NOT NULL,
    content_type VARCHAR(16) NOT NULL DEFAULT 'text',
    content_value LONGTEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_page_content_slug_key (page_slug, section_key),
    KEY idx_page_content_slug (page_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cms_menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `location` VARCHAR(16) NOT NULL,
    `parent_id` INT DEFAULT NULL,
    `label` VARCHAR(120) NOT NULL,
    `link_type` VARCHAR(16) NOT NULL DEFAULT 'page',
    `target` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_cms_menu_location` (`location`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE `site_settings` SET `setting_value` = '127.0.0.1'          WHERE `setting_key` = 'smtp_host';
UPDATE `site_settings` SET `setting_value` = '1025'               WHERE `setting_key` = 'smtp_port';
UPDATE `site_settings` SET `setting_value` = ''                   WHERE `setting_key` = 'smtp_secure';
UPDATE `site_settings` SET `setting_value` = 'local@example.test' WHERE `setting_key` = 'smtp_user';
UPDATE `site_settings` SET `setting_value` = 'local-not-a-real-password' WHERE `setting_key` = 'smtp_pass';
UPDATE `site_settings` SET `setting_value` = 'admin@example.test' WHERE `setting_key` = 'admin_email';

-- Belt and braces: scrub every real registrant email address in the local copy
-- so that even a mistyped SMTP host can never reach a real person from a dev
-- machine. Production data is untouched; this only rewrites the container's
-- copy. Names/orgs are kept so the data still looks realistic for testing.
UPDATE `event_registrations`
   SET `email` = CONCAT('reg', `id`, '@example.test')
 WHERE `email` IS NOT NULL;

-- A local-only admin account, so the acceptance suite can log into the admin
-- panel over HTTP and assert what admin/analytics.php actually renders. The
-- production password hashes in the dump are unknown (correctly so), and
-- faking a $_SESSION would test the page without testing the auth path it
-- depends on.
--
-- LOCAL CONTAINER ONLY. This is not a credential: the throwaway database is
-- rebuilt from the dumps on every verify.sh run and is reachable on loopback
-- only. It must never be inserted into a production database.
-- It is named Craig so the panel and the audit log read as a person rather than
-- a fixture while the work is being reviewed. That makes the warning above more
-- important, not less: a row carrying a real name is the one somebody is most
-- likely to copy into production by mistake. The password below is published in
-- this repository and is therefore not a secret.
--   username: Craig
--   password: localtest-analytics-pw
INSERT INTO `admin_users`
  (`username`, `password`, `role`, `first_name`, `last_name`, `email`,
   `department`, `is_administrator`, `is_staff`, `permissions`)
VALUES
  ('Craig',
   '$2y$12$giO77eJa0QkaVtgtZmQMReV/wzHhY/8DeY5yo9XNMDshpMf5R7aZW',
   'super_admin', 'Craig', 'Ouma', 'craig@example.test',
   'QA', 1, 1, NULL);

-- Real site identity, so a local rebuild does not leave the footer and the
-- Settings screen looking empty. These are public details, not credentials.
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
  ('site_title',      'Prosperminds'),
  ('site_tagline',    'Public finance training for the people who sign the accounts'),
  ('contact_email',   'info@prosper-minds.com'),
  ('contact_phone',   '+254 740 582302'),
  ('contact_address', 'Nairobi, Kenya'),
  ('social_linkedin', 'https://www.linkedin.com/company/prosper-minds-technologies/'),
  ('social_facebook', 'https://www.facebook.com/share/1EvKA1GF5w/?mibextid=wwXIfr')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Mirrors deploy/2026-09-23-mombasa-update-and-slugs.sql, so the local
-- acceptance suite and a manual click-through exercise the same schema and
-- content change that is about to go to production, not just the routing
-- code that reads it.
ALTER TABLE `events`
  ADD COLUMN IF NOT EXISTS `slug` VARCHAR(160) NULL UNIQUE AFTER `location`;

-- NOTE: production took Kuala Lumpur and Bali off the active calendar
-- (deploy/2026-09-06-go-live.sql, is_active = 0 for events 2 and 3), and that
-- was verified directly against the live site rather than here: verify.sh's
-- own fixtures register against event 3, so flipping it inactive locally
-- breaks the suite's assumptions rather than the site.

UPDATE `events` SET `slug` = 'future-ready-pfm-leaders-cape-town-2026' WHERE `id` = 1;
UPDATE `events`
   SET `slug`       = 'christmas-pfm-mastery-school-mombasa-2026',
       `location`   = 'Sarova Whitesands Beach Resort & Spa, Mombasa, Kenya',
       `image_path` = 'assets/images/Christmas PFM Mastery School banner.jpg'
 WHERE `id` = 5;

UPDATE `page_content`
   SET `content_value` = '[{"value":"25","label":"Years collective experience"},{"value":"875","label":"Leaders trained"},{"value":"2","label":"Schools in 2026"},{"value":"5","label":"Day residential format"}]'
 WHERE `page_slug` = 'home' AND `section_key` = 'hero_facts';

INSERT INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
  ('global', 'phone_secondary', 'text', '+254 722 998105', 60)
ON DUPLICATE KEY UPDATE `content_value` = VALUES(`content_value`);

INSERT INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
  ('register', 'done_help', 'text', 'Need help right away? Call +254 740 582302 or +254 722 998105, or email info@prosper-minds.com.', 170)
ON DUPLICATE KEY UPDATE `content_value` = VALUES(`content_value`);

-- PFM Insight Live. Mirrors deploy/2026-10-01-pfm-insight-live.sql, but the
-- tables are created here too (not left to ensureWebinarSchema()): this file
-- runs once at container init, before any PHP request has happened, so
-- nothing has created them yet.
CREATE TABLE IF NOT EXISTS webinar_sessions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    session_number INT          NOT NULL,
    title          VARCHAR(200) NOT NULL,
    topic          VARCHAR(200) NOT NULL DEFAULT "",
    description    TEXT         NULL,
    best_for       VARCHAR(200) NOT NULL DEFAULT "",
    slug           VARCHAR(160) NOT NULL UNIQUE,
    session_date   DATE         NOT NULL,
    time_label     VARCHAR(100) NOT NULL DEFAULT "12:00 EAT",
    zoom_link      VARCHAR(500) NOT NULL DEFAULT "",
    image_path     VARCHAR(300) NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order     INT          NOT NULL DEFAULT 0,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webinar_registrations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    session_id        INT          NOT NULL DEFAULT 0,
    name              VARCHAR(150) NOT NULL,
    email             VARCHAR(190) NOT NULL,
    organization      VARCHAR(150) NULL,
    country           VARCHAR(100) NULL,
    unsubscribe_token CHAR(32)     NOT NULL,
    unsubscribed_at   TIMESTAMP    NULL DEFAULT NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_webinar_reg (email, session_id),
    UNIQUE KEY uq_webinar_unsub_token (unsubscribe_token),
    KEY idx_webinar_reg_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webinar_reminders_sent (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    session_id      INT       NOT NULL,
    registration_id INT       NOT NULL,
    sent_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_webinar_reminder (session_id, registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `webinar_sessions`
  (`session_number`, `title`, `topic`, `description`, `best_for`, `slug`,
   `session_date`, `time_label`, `zoom_link`, `is_active`, `sort_order`)
VALUES
  (1,  'Start Here: Data for Public Money', 'Data for Public Money',
       'Turn budget and spending records into clear facts you can act on.', 'Everyone new to data',
       'pfm-insight-live-session-01-start-here-data-for-public-money',
       '2026-10-29', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 1),
  (2,  'Your First Budget Dashboard', 'Your First Budget Dashboard',
       'Build a simple dashboard that shows spending against budget every month.', 'Budget and finance officers',
       'pfm-insight-live-session-02-your-first-budget-dashboard',
       '2026-11-26', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 2),
  (3,  'AI Made Simple for Finance Teams', 'AI Made Simple for Finance Teams',
       'Use AI tools safely to draft, check and summarise finance work.', 'All finance staff',
       'pfm-insight-live-session-03-ai-made-simple-for-finance-teams',
       '2027-01-28', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 3),
  (4,  'Forecast the Budget with Data', 'Forecast the Budget with Data',
       'Use past numbers to predict revenue and spending with more confidence.', 'Budget and planning teams',
       'pfm-insight-live-session-04-forecast-the-budget-with-data',
       '2027-02-25', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 4),
  (5,  'Find the Missing Revenue', 'Find the Missing Revenue',
       'Use data to spot tax gaps and collect more of what is owed.', 'Revenue and tax officers',
       'pfm-insight-live-session-05-find-the-missing-revenue',
       '2027-03-25', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 5),
  (6,  'Spot Fraud in Procurement', 'Spot Fraud in Procurement',
       'Learn the data red flags that show a tender may be rigged.', 'Procurement staff and auditors',
       'pfm-insight-live-session-06-spot-fraud-in-procurement',
       '2027-04-29', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 6),
  (7,  'Know Your Cash Every Day', 'Know Your Cash Every Day',
       'Track cash and debt in one place so bills are paid on time.', 'Treasury and debt teams',
       'pfm-insight-live-session-07-know-your-cash-every-day',
       '2027-05-27', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 7),
  (8,  'Audit 100% of Transactions', 'Audit 100% of Transactions',
       'Test every payment, not just a sample, with simple data tools.', 'Internal and external auditors',
       'pfm-insight-live-session-08-audit-100-percent-of-transactions',
       '2027-06-24', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 8),
  (9,  'Faster IPSAS Year-End with Data', 'Faster IPSAS Year-End with Data',
       'Clean your data early so financial statements are right and on time.', 'Accountants and reporting teams',
       'pfm-insight-live-session-09-faster-ipsas-year-end-with-data',
       '2027-07-29', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 9),
  (10, 'Track Climate and Gender Spending', 'Track Climate and Gender Spending',
       'Tag the budget to show how money helps climate and gender goals.', 'Budget, planning and policy teams',
       'pfm-insight-live-session-10-track-climate-and-gender-spending',
       '2027-08-26', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 10),
  (11, 'Clean, Safe Data for AI', 'Clean, Safe Data for AI',
       'Keep public data accurate, private and ready for AI.', 'Finance, ICT and data teams',
       'pfm-insight-live-session-11-clean-safe-data-for-ai',
       '2027-09-30', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 11),
  (12, 'Let AI Do the Routine Work', 'Let AI Do the Routine Work',
       'Automate monthly reports and checks so your team can focus on decisions.', 'Finance managers and teams',
       'pfm-insight-live-session-12-let-ai-do-the-routine-work',
       '2027-10-28', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 12),
  (13, 'Show Results Citizens Can Trust', 'Show Results Citizens Can Trust',
       'Present public finance data in clear charts for leaders and citizens.', 'Leaders, reporting and communication teams',
       'pfm-insight-live-session-13-show-results-citizens-can-trust',
       '2027-11-25', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 13)
ON DUPLICATE KEY UPDATE `session_number` = VALUES(`session_number`);

UPDATE `webinar_sessions`
   SET `image_path` = 'assets/images/pfm-insight-live-session-01.jpg'
 WHERE `slug` = 'pfm-insight-live-session-01-start-here-data-for-public-money';

INSERT INTO `cms_menu_items` (`location`, `label`, `link_type`, `target`, `sort_order`, `is_active`)
SELECT 'header', 'Webinars', 'page', 'webinars.php', IFNULL(MAX(`sort_order`), 0) + 1, 1
  FROM `cms_menu_items`
 WHERE `location` = 'header'
   AND NOT EXISTS (
       SELECT 1 FROM `cms_menu_items` WHERE `location` = 'header' AND `target` = 'webinars.php'
   )
HAVING COUNT(*) > 0;
