-- Migration 2026-09-15-01 (UP): the vision and mission statements
--
-- The client supplied these on 15 September 2026. Their em dashes are replaced
-- with commas here and in about.php, because the standing instruction is that
-- no user-visible copy carries one.
--
-- Same conventions as the earlier seed migrations: INSERT IGNORE, never an
-- upsert, so a second run changes nothing and staff edits are never clobbered.
-- about.php carries the same wording as its inline fallback, so the section
-- reads correctly whether or not this has been applied.

INSERT IGNORE INTO `page_content` (`page_slug`, `section_key`, `content_type`, `content_value`, `sort_order`) VALUES
('about', 'vision_eyebrow',  'text', 'Vision',  12),
('about', 'vision_body',     'text',
 'An Africa where every public institution is trusted with its money, led by world-class finance minds.', 13),
('about', 'mission_eyebrow', 'text', 'Mission', 14),
('about', 'mission_body',    'text',
 'We prepare Africa''s senior finance leaders, practitioner to practitioner, to master the standards, put AI to work, and disclose with integrity, so their institutions earn trust at home and respect worldwide.', 15);
