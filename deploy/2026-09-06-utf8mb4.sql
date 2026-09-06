-- Convert the older tables from latin1 to utf8mb4.
--
-- Run this in phpMyAdmin AFTER 2026-09-06-go-live.sql, and only after taking a
-- backup. It is a separate file on purpose: it rewrites every row in place, and
-- it is the one step here that could damage text if the diagnosis were wrong.
--
-- The diagnosis, checked against your dump of 4 September rather than assumed:
--
--   SELECT HEX(date_display) FROM events WHERE id = 1;
--
-- returned a single byte 0x96 where the en dash sits, with CHAR_LENGTH equal to
-- LENGTH. In MySQL's latin1 that byte IS an en dash, so the columns hold genuine
-- latin1 text, not UTF-8 bytes mislabelled as latin1. That means a plain CONVERT
-- is correct here and the VARBINARY two-step often recommended online would be
-- the wrong tool and would corrupt the text.
--
-- Rehearsed on a full copy of your data: every row of events, registrations,
-- accounts and settings was read back through a utf8mb4 connection before and
-- after, and the text was byte for byte identical. Only the storage changed, the
-- en dash going from one latin1 byte to its three UTF-8 bytes.
--
-- Why bother: new tables are already utf8mb4. Leaving the old ones on latin1
-- means a delegate whose name contains a character latin1 cannot hold silently
-- loses it on the way in.

-- Run everything against this database, whatever is selected on the left in
-- phpMyAdmin. Without this line the verification query at the bottom can
-- resolve `events` to information_schema.EVENTS, which is a real table with
-- entirely different columns, and the script ends on a confusing error.
USE `kidsmone_Prosperminds_website`;

ALTER TABLE `admin_users`           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `events`                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `event_registrations`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `expenses`              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `finance_credit_notes`  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `finance_customers`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `finance_invoices`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `finance_invoice_lines` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `finance_vendors`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `login_attempts`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `site_settings`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `vendor_bills`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `vendor_bill_lines`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Check it worked. Expect 0 tables left on latin1, and the date to read
-- 19-23 October 2026 with a proper en dash.
SELECT COUNT(*) AS latin1_tables_should_be_0
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'kidsmone_Prosperminds_website' AND TABLE_COLLATION LIKE 'latin1%';

-- Fully qualified on purpose. `events` on its own is ambiguous the moment
-- information_schema is the selected database.
SELECT `id`, `date_display` FROM `kidsmone_Prosperminds_website`.`events` ORDER BY `id`;
