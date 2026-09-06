-- The seven 2026 CPD cohorts that have already run, added to the main site so
-- the Past cohorts tab is not empty. Copied from cpd.prosper-minds.com, which
-- is the record of what was actually published for each school.
--
-- is_active = 0 with a past start date is how this site models a finished
-- school: it stays out of Upcoming, appears under Past cohorts, and its own
-- page keeps working for anyone who attended.
--
-- Prices, tiers, early bird tiers and the agenda are written as explicit NULLs
-- rather than left out. The events table defaults those columns to the current
-- course's figures (USD 599 / 1,999 / 2,899 and 20/15/10 per cent), so omitting
-- them would quietly advertise this year's prices against a school that ran in
-- January. The CPD listings carried no per-day agenda and no tiered pricing,
-- and each of those sections omits itself when its column is empty, so nothing
-- is invented and nothing renders half-filled.
--
-- Safe to run twice: INSERT IGNORE on fixed ids.

USE `kidsmone_Prosperminds_website`;

START TRANSACTION;

INSERT IGNORE INTO `events`
  (`id`, `title`, `tagline`, `focus_tags`, `why_intro`, `master_points`, `audience`,
   `date_display`, `event_start_date`, `location`, `is_active`, `sort_order`,
   `price`, `regular_price`, `vip_price`, `vvip_price`, `vvip_seats_note`,
   `early_bird_1_pct`, `early_bird_2_pct`, `early_bird_3_pct`)
VALUES
  (20, 'Foundations of Foresight', 'Build the bedrock for strategic public finance.', 'PFM · IPSAS · IFRS', 'Step into a room where your mind wakes up. In January, you learn how to sense financial trouble before it even forms. Through fast, hands-on sprints, your thinking sharpens until you become the person people look at and whisper, “How did you see that coming?”\n\nHands-on sprints; peer-to-peer coaching; zero lectures.', 'Master PFM, IPSAS & IFRS in real-world African contexts.\nBe the one who spots problems before anyone else does.', 'Government Accountants\nAuditors\nBudget Managers\nLegislators\nPlanners', 'Monday 26 – Friday 30 January 2026', '2026-01-26', 'Nairobi, Kenya', 0, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (21, 'Data Awakening', 'Turn numbers into foresight.', 'Data Analytics · Risk Detection · PFM', 'This is the month where numbers start behaving like clues. You discover how data hides danger, reveals opportunity, and shows you what others can’t see. By the end, every spreadsheet feels like a map and you’re the one who knows how to read it.\n\nLive analytics labs with real government data.', 'Extract insights, detect risks, make data-driven decisions.\nTurn boring numbers into decisions people rely on.', 'Government Accountants\nAuditors\nBudget Managers\nLegislators\nPlanners', 'Monday 23 – Friday 27 February 2026', '2026-02-23', 'Dubai, UAE', 0, 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (22, 'Strategic Compliance', 'Compliance is the floor, strategy is the ceiling.', 'IPSAS · IFRS · Strategy', 'Here, rules stop feeling heavy. They start feeling powerful. You learn how to turn IPSAS and IFRS into strategic tools that make you stand out. When others follow the rules, you’ll use them to shape smarter decisions and bigger wins.\n\nScenario simulations; real-world reporting challenges.', 'Transform IPSAS & IFRS application into strategic action.\nMake rules work for you, and stand out as a strategic thinker.', 'Government Accountants\nAuditors\nBudget Managers\nLegislators\nPlanners', 'Monday 23 – Friday 27 March 2026', '2026-03-23', 'Singapore', 0, 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (23, 'Automation Ascendancy', 'Free teams to think, not compile.', 'AI & Automation · PFM Reporting', 'April is where your workload finally bows to you. AI and automation take over the boring tasks, leaving you free to think big and lead boldly. You stop being buried in reports, and start being the one who changes how things get done.\n\nAI-driven labs; hands-on automation exercises.', 'Automate PFM reporting; save time, reduce errors.\nFree yourself from busywork and shine on high-impact tasks.', 'Government Accountants\nAuditors\nBudget Managers\nLegislators\nPlanners', 'Monday 27 April – Friday 1 May 2026', '2026-04-27', 'Nairobi, Kenya', 0, 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (24, 'Decision Dynamics', 'Every financial choice counts.', 'Decision-Making · Simulations · PFM', 'Imagine stepping into a crisis room and feeling calm. In May, you train your mind to think fast, clear, and strong. With cabinet-style drills and wild simulations, you become the person others turn to when everything is on fire, because you make the decisions that save the day.\n\nCabinet-style simulations; live crises; team problem-solving; Moonshot Thinking.', 'Strengthen PFM decision-making under extreme constraints.\nMake decisions others wish they could make, and make them fast.', 'Government Accountants\nAuditors\nBudget Managers\nLegislators\nPlanners', 'Monday 25 – Friday 29 May 2026', '2026-05-25', 'Dubai, UAE', 0, 24, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (25, 'Audit Reimagined', 'From checklists to insight.', 'Audit · Governance · Anomaly Detection', 'Auditing becomes detective work. You sharpen your instincts, spot the strange patterns, and catch the things many miss. In June, you gain the kind of insight that makes leaders trust your judgment instantly.\n\nLive audit simulations; anomaly detection exercises.', 'Turn audits into predictive governance tools: effective audit committees and internal auditors.\nFind what others miss and earn trust instantly.', 'Government Accountants\nAuditors\nBudget Managers\nLegislators\nPlanners', 'Monday 22 – Friday 26 June 2026', '2026-06-22', 'Singapore', 0, 25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (26, 'Transparency Engine', 'Build public trust through insight.', 'Transparency · Dashboards · Data Analytics', 'Here, you learn how to turn complex financial stories into simple truths people believe. Dashboards, visuals, and real-world labs teach you how to build trust you can feel in the room. By the end, your work won’t just inform, it will inspire confidence.\n\nDashboards, communications, real-world transparency labs.', 'Link reporting to citizen confidence: stakeholder-facing data analytics and automation.\nBuild confidence and respect that others can’t ignore.', 'Government Accountants\nAuditors\nBudget Managers\nLegislators\nPlanners', 'Monday 27 – Friday 31 July 2026', '2026-07-27', 'Nairobi, Kenya', 0, 26, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

COMMIT;

-- Check it worked. Expect 7 and 11.
SELECT (SELECT COUNT(*) FROM `kidsmone_Prosperminds_website`.`events`
         WHERE `event_start_date` < CURDATE())                AS past_cohorts_should_be_7,
       (SELECT COUNT(*) FROM `kidsmone_Prosperminds_website`.`events`) AS events_should_be_11;

SELECT `id`, `date_display`, `location`, `is_active`, `title`
  FROM `kidsmone_Prosperminds_website`.`events`
 ORDER BY `event_start_date`;
