-- Migration 2026-09-04-01 (DOWN): cms_testimonials
-- Safe: the homepage falls back to the reviews it was built with.

DROP TABLE IF EXISTS `cms_testimonials`;
