-- Migration 2026-09-15-01 (DOWN): remove the vision and mission rows
--
-- about.php keeps the same wording as its inline fallback, so removing these
-- rows changes nothing a visitor sees. It only makes the copy uneditable again.

DELETE FROM `page_content`
 WHERE `page_slug` = 'about'
   AND `section_key` IN ('vision_eyebrow', 'vision_body', 'mission_eyebrow', 'mission_body');
