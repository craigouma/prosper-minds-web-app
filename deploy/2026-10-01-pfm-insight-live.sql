-- 2026-10-01: PFM Insight Live, the free monthly webinar series.
--
-- Paste this whole file into phpMyAdmin, SQL tab, on kidsmone_Prosperminds_website.
-- Run it AFTER the code is deployed (webinar_sessions does not exist until
-- ensureWebinarSchema() has run once, which happens the first time anyone
-- visits /webinars.php or the admin screen; this file will fail with "table
-- doesn't exist" if pasted before that).
--
-- Safe to run twice: every session is keyed by its slug, so a second run
-- updates the same 13 rows rather than duplicating them.

USE `kidsmone_Prosperminds_website`;

START TRANSACTION;

INSERT INTO `webinar_sessions`
  (`session_number`, `title`, `topic`, `description`, `best_for`, `slug`,
   `session_date`, `time_label`, `zoom_link`, `is_active`, `sort_order`)
VALUES
  (1,  'Start Here: Data for Public Money',
       'Data for Public Money',
       'Turn budget and spending records into clear facts you can act on.',
       'Everyone new to data',
       'pfm-insight-live-session-01-start-here-data-for-public-money',
       '2026-10-29', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 1),
  (2,  'Your First Budget Dashboard',
       'Your First Budget Dashboard',
       'Build a simple dashboard that shows spending against budget every month.',
       'Budget and finance officers',
       'pfm-insight-live-session-02-your-first-budget-dashboard',
       '2026-11-26', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 2),
  (3,  'AI Made Simple for Finance Teams',
       'AI Made Simple for Finance Teams',
       'Use AI tools safely to draft, check and summarise finance work.',
       'All finance staff',
       'pfm-insight-live-session-03-ai-made-simple-for-finance-teams',
       '2027-01-28', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 3),
  (4,  'Forecast the Budget with Data',
       'Forecast the Budget with Data',
       'Use past numbers to predict revenue and spending with more confidence.',
       'Budget and planning teams',
       'pfm-insight-live-session-04-forecast-the-budget-with-data',
       '2027-02-25', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 4),
  (5,  'Find the Missing Revenue',
       'Find the Missing Revenue',
       'Use data to spot tax gaps and collect more of what is owed.',
       'Revenue and tax officers',
       'pfm-insight-live-session-05-find-the-missing-revenue',
       '2027-03-25', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 5),
  (6,  'Spot Fraud in Procurement',
       'Spot Fraud in Procurement',
       'Learn the data red flags that show a tender may be rigged.',
       'Procurement staff and auditors',
       'pfm-insight-live-session-06-spot-fraud-in-procurement',
       '2027-04-29', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 6),
  (7,  'Know Your Cash Every Day',
       'Know Your Cash Every Day',
       'Track cash and debt in one place so bills are paid on time.',
       'Treasury and debt teams',
       'pfm-insight-live-session-07-know-your-cash-every-day',
       '2027-05-27', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 7),
  (8,  'Audit 100% of Transactions',
       'Audit 100% of Transactions',
       'Test every payment, not just a sample, with simple data tools.',
       'Internal and external auditors',
       'pfm-insight-live-session-08-audit-100-percent-of-transactions',
       '2027-06-24', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 8),
  (9,  'Faster IPSAS Year-End with Data',
       'Faster IPSAS Year-End with Data',
       'Clean your data early so financial statements are right and on time.',
       'Accountants and reporting teams',
       'pfm-insight-live-session-09-faster-ipsas-year-end-with-data',
       '2027-07-29', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 9),
  (10, 'Track Climate and Gender Spending',
       'Track Climate and Gender Spending',
       'Tag the budget to show how money helps climate and gender goals.',
       'Budget, planning and policy teams',
       'pfm-insight-live-session-10-track-climate-and-gender-spending',
       '2027-08-26', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 10),
  (11, 'Clean, Safe Data for AI',
       'Clean, Safe Data for AI',
       'Keep public data accurate, private and ready for AI.',
       'Finance, ICT and data teams',
       'pfm-insight-live-session-11-clean-safe-data-for-ai',
       '2027-09-30', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 11),
  (12, 'Let AI Do the Routine Work',
       'Let AI Do the Routine Work',
       'Automate monthly reports and checks so your team can focus on decisions.',
       'Finance managers and teams',
       'pfm-insight-live-session-12-let-ai-do-the-routine-work',
       '2027-10-28', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 12),
  (13, 'Show Results Citizens Can Trust',
       'Show Results Citizens Can Trust',
       'Present public finance data in clear charts for leaders and citizens.',
       'Leaders, reporting and communication teams',
       'pfm-insight-live-session-13-show-results-citizens-can-trust',
       '2027-11-25', '12:00 EAT', 'https://us02web.zoom.us/j/84468006463?pwd=LaMYkz9HLRVYz8iOBHuQaC4iuvKQ6Y.1', 1, 13)
ON DUPLICATE KEY UPDATE
  `session_number` = VALUES(`session_number`),
  `title`          = VALUES(`title`),
  `topic`          = VALUES(`topic`),
  `description`    = VALUES(`description`),
  `best_for`       = VALUES(`best_for`),
  `session_date`   = VALUES(`session_date`),
  `time_label`     = VALUES(`time_label`),
  `zoom_link`      = VALUES(`zoom_link`),
  `sort_order`     = VALUES(`sort_order`);

-- Add "Webinars" to the public header navigation, placed after whatever is
-- currently last (the admin Menus screen can reorder it any time). Only if
-- the header menu is already managed in the CMS; if it is still running on
-- the code default, pmNavItems()'s own default list already includes it, so
-- there is nothing to insert.
INSERT INTO `cms_menu_items` (`location`, `label`, `link_type`, `target`, `sort_order`, `is_active`)
SELECT 'header', 'Webinars', 'page', 'webinars.php', IFNULL(MAX(`sort_order`), 0) + 1, 1
  FROM `cms_menu_items`
 WHERE `location` = 'header'
   AND NOT EXISTS (
       SELECT 1 FROM `cms_menu_items` WHERE `location` = 'header' AND `target` = 'webinars.php'
   )
HAVING COUNT(*) > 0;

COMMIT;

-- Check it worked. Expect 13 sessions and (if the header menu is CMS-managed)
-- one new "Webinars" row.
SELECT COUNT(*) AS sessions_should_be_13 FROM `webinar_sessions`;
SELECT * FROM `cms_menu_items` WHERE `location` = 'header' ORDER BY `sort_order`;
