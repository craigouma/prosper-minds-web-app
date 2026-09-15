-- Vision and mission, for a database that has already had
-- 2026-09-06-page-content.sql run against it.
--
-- Paste into phpMyAdmin, SQL tab, and press Go. Safe to run twice.
-- The About page shows this copy either way: about.php carries the same
-- wording as its built-in fallback. Running this only makes it editable.

USE `kidsmone_Prosperminds_website`;

INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('about', 'vision_eyebrow',  'text', 'Vision',  12),
('about', 'vision_body',     'text',
 'An Africa where every public institution is trusted with its money, led by world-class finance minds.', 13),
('about', 'mission_eyebrow', 'text', 'Mission', 14),
('about', 'mission_body',    'text',
 'We prepare Africa''s senior finance leaders, practitioner to practitioner, to master the standards, put AI to work, and disclose with integrity, so their institutions earn trust at home and respect worldwide.', 15);

-- Check it worked. Expect 4.
SELECT COUNT(*) AS vision_mission_rows_should_be_4
  FROM `kidsmone_Prosperminds_website`.`page_content`
 WHERE `page_slug` = 'about'
   AND `section_key` IN ('vision_eyebrow','vision_body','mission_eyebrow','mission_body');
