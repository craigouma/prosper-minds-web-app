-- 2026-10-08: each PFM Insight Live session gets its own Zoom meeting,
-- on the Prosperminds Zoom account, replacing the single shared link from the
-- old account.
--
-- Paste into phpMyAdmin, SQL tab, on kidsmone_Prosperminds_website. Safe to run
-- twice. The confirmation and reminder emails read zoom_link at the moment they
-- are sent, so a registration made after this runs carries the new link.

USE `kidsmone_Prosperminds_website`;

START TRANSACTION;

UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/89779621384?pwd=qPboI3gDubcvg6hvw82YWtYLrR5Pgg.1' WHERE `slug` = 'pfm-insight-live-session-01-start-here-data-for-public-money';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/81709412649?pwd=drehadKN6XukU8Z2KhNcl2GoFEfzfS.1' WHERE `slug` = 'pfm-insight-live-session-02-your-first-budget-dashboard';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/89960866995?pwd=O6J0FcLW2ZPsMH2pO527xZj9sn0t14.1' WHERE `slug` = 'pfm-insight-live-session-03-ai-made-simple-for-finance-teams';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/81161396137?pwd=6dFE8rgkODxaMmWblrTWnnkiKCiTdP.1' WHERE `slug` = 'pfm-insight-live-session-04-forecast-the-budget-with-data';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/86284288766?pwd=1QSc5GCb2KzCwX2KWZPkzViwnIQpht.1' WHERE `slug` = 'pfm-insight-live-session-05-find-the-missing-revenue';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/85740530106?pwd=hhxeFHNhOgvYZZbiK8QjclFvqUKqkZ.1' WHERE `slug` = 'pfm-insight-live-session-06-spot-fraud-in-procurement';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/81110106720?pwd=O43jRJS3O8KuAo9ldRr4Wyb1NaQE7o.1' WHERE `slug` = 'pfm-insight-live-session-07-know-your-cash-every-day';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/82715013331?pwd=TEY6B8NRzAOD6tiUOFXflxFaJT9a6I.1' WHERE `slug` = 'pfm-insight-live-session-08-audit-100-percent-of-transactions';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/85696432095?pwd=SNC0ChhfnPeulNVew1NWhH3uDVIiMR.1' WHERE `slug` = 'pfm-insight-live-session-09-faster-ipsas-year-end-with-data';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/88407352530?pwd=0qIpphuwREUb318i5hglbFYoMbxwZ5.1' WHERE `slug` = 'pfm-insight-live-session-10-track-climate-and-gender-spending';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/83008862375?pwd=cNdEsjueHC8cHgPJ7PFNIbWrNIge0z.1' WHERE `slug` = 'pfm-insight-live-session-11-clean-safe-data-for-ai';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/86161098210?pwd=4cnMLcQz7Rhv103Rf9wGhA2cFYTviW.1' WHERE `slug` = 'pfm-insight-live-session-12-let-ai-do-the-routine-work';
UPDATE `webinar_sessions` SET `zoom_link` = 'https://us06web.zoom.us/j/83010670183?pwd=dQzxbQIaRqDoxaad8FDfOzVkbbuM3k.1' WHERE `slug` = 'pfm-insight-live-session-13-show-results-citizens-can-trust';

COMMIT;

-- Expect 13 rows, each a different link.
SELECT `session_number`, `session_date`, `zoom_link` FROM `webinar_sessions` ORDER BY `session_number`;
