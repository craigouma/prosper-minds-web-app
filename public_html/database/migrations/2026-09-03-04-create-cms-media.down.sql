-- Migration 2026-09-03-04 (DOWN): cms_media and cms_media_usage
-- Destructive: drops the record of every uploaded file. The files on disk in
-- assets/uploads are left alone and become orphans.

DROP TABLE IF EXISTS `cms_media_usage`;
DROP TABLE IF EXISTS `cms_media`;
