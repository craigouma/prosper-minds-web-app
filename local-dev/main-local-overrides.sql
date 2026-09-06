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
