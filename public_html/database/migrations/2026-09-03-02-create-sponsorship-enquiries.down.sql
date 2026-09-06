-- Migration 2026-09-03-02 (DOWN): sponsorship_enquiries
-- Destructive: discards sponsorship enquiries that exist nowhere else. Export first.

DROP TABLE IF EXISTS `sponsorship_enquiries`;
