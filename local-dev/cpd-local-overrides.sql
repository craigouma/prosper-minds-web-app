-- Local-only overrides applied AFTER the production dump is imported into the
-- throwaway Docker database. Runs only inside the local container; the
-- production dump file itself is never modified.
--
-- Unlike the main site, CPD reads its SMTP settings from the environment
-- (config.php -> cpd_env), so there are no mail rows to rewrite here — the
-- gitignored cpd.prosper-minds.com/.env already points at the local Mailpit
-- container.
--
-- What this does do is scrub every real registrant email address out of the
-- local copy, so that even a mistyped SMTP host on a dev machine cannot reach
-- a real person. Names and organisations are kept so the data stays realistic.

UPDATE `registrations`
   SET `email` = CONCAT('cpdreg', `id`, '@example.test')
 WHERE `email` IS NOT NULL;
