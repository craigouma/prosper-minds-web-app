-- 2026-10-09: the header menu for the Site v2 redesign.
--
-- Paste into phpMyAdmin, SQL tab, on kidsmone_Prosperminds_website. Run it
-- AFTER the code is deployed. A header menu stored in this table REPLACES the
-- menu built into the code, so without this the live header would keep showing
-- the old seven items (Home, Events, Services, About, Sponsorship, Contact,
-- Webinars). Events, Services and Contact now redirect to their new homes, so
-- nothing breaks in the meantime, but the new four-item header needs this.
--
-- Safe to run twice: it empties the header location and writes the four rows
-- again. To go back to the old header, run deploy/2026-09-06-go-live.sql's menu
-- section and deploy/2026-10-01-pfm-insight-live.sql's menu insert again, or
-- edit the rows under Menus in the admin panel. The footer location is not
-- touched.

USE `kidsmone_Prosperminds_website`;

DELETE FROM `cms_menu_items` WHERE `location` = 'header';

INSERT INTO `cms_menu_items`
  (`location`, `parent_id`, `label`,           `link_type`, `target`,         `sort_order`, `is_active`)
VALUES
  ('header',   NULL,        'Schools',         'page',      '#schools',       0,            1),
  ('header',   NULL,        'Webinars',        'page',      'webinars.php',   10,           1),
  ('header',   NULL,        'Partner with us', 'page',      'sponsorship.php', 20,          1),
  ('header',   NULL,        'About',           'page',      'about.php',      30,           1);

-- Expect four rows, in this order.
SELECT `label`, `target`, `sort_order` FROM `cms_menu_items` WHERE `location` = 'header' ORDER BY `sort_order`;
