<?php
/**
 * Service detail: PFM, IPSAS and IFRS Mastery.
 *
 * Rebuilt. The file it replaces was a 7 KB page on assets/css/style.css with
 * its own copy of the header and footer and its copy written into the markup.
 *
 * The page's shape is shared with the other two pillars
 * (includes/layout/service-detail.php); everything specific to this pillar is
 * either a page_content row under slug 'service-pfm' or one of the inline
 * defaults below.
 *
 * WHY THE DEFAULTS ARE HERE AND NOT IN THE SHARED PARTIAL
 * ------------------------------------------------------
 * The content layer's contract (includes/content.php, point 3) is that every
 * call site passes a real default saying the same thing its seeded row says,
 * so that a missing or unreachable page_content table produces this page rather
 * than a blank one. A default shared across the three pillars would have to be
 * generic, which is the same as having none. So each pillar carries its own,
 * and it is the copy in
 * database/migrations/2026-08-28-05-seed-page-content-phase2.up.sql.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';

$pmService = [
    'slug'     => 'service-pfm',
    'pillar'   => 'pfm',
    'defaults' => [
        'meta_title'       => 'PFM, IPSAS and IFRS Mastery',
        'meta_description' => 'Accrual accounting, disclosure and audit readiness for public institutions that are judged on their financial statements. Five day residential training for government finance teams.',
        'hero_eyebrow'     => 'Services',
        'hero_title'       => 'PFM, IPSAS and IFRS Mastery',
        'hero_promise'     => 'Build the technical foundation your finance teams need.',
        'hero_body'        => 'Accrual accounting, disclosure and audit readiness for institutions that are judged on their financial statements.',
        'context_title'    => 'Why departments send teams',
        'context_body_1'   => 'A clean audit is the clearest public signal that an institution is well run, and the hardest ground is almost always the same. Asset recognition, valuation and a register that reconciles to the ledger are where qualified opinions start. Departments send teams here when they are carrying audit findings they have not been able to close.',
        'context_body_2'   => 'The move from cash to accrual reporting is the other reason. It changes what has to be recognised, when, and on whose authority, and the reporting team cannot deliver it alone. This pillar treats the transition as a sequencing problem with a timetable attached, not as a standard to be memorised.',
        'outcomes_title'   => 'What a delegate returns with',
        'outcomes'         => [
            'Statements that reconcile to the ledger and survive audit',
            'A defensible position on asset recognition and measurement',
            'A transition plan from cash to accrual reporting',
            'Reporting timetables that hold under pressure',
        ],
        'curriculum_title' => 'Curriculum coverage',
        'topics'           => [
            'IPSAS presentation and disclosure',
            'Revenue and expenditure recognition',
            'Asset registers and componentisation',
            'Consolidation boundaries',
            'Audit file construction',
            'IFRS for state corporations',
        ],
        'audience_title'   => 'Who it is for',
        'audience'         => [
            'Financial reporting managers',
            'Auditors General and audit managers',
            'IPSAS transition project leads',
            'Asset and infrastructure accountants',
            'Public entity finance directors',
        ],
        'format_title'     => 'How it is taught',
        'format_body'      => 'Five days, residential, with the cohort capped so that faculty stay reachable throughout. Day one sets the leadership context, days two to four work through the technical material against real statements and real audit findings, and day five is spent building the action plan each delegate takes back to their department. Every school carries CPD certification.',
        'events_title'     => 'Schools covering this pillar',
        'events_empty'     => 'No school covering this pillar is in the 2026 calendar yet. Contact the programme office and we will tell you when one is scheduled.',
        'related_tags'     => ['IPSAS', 'Clean Audit', 'Assets Accounting', 'PFM Leadership', 'Mastery School'],
        'cta_eyebrow'      => 'Next step',
        'cta_title'        => 'Ask for the full course outline',
        'cta_body'         => 'The programme office replies within one working day, and will say plainly if a different pillar fits your team better.',
        'cta_label'        => 'Contact the programme office',
    ],
];

require __DIR__ . '/includes/layout/service-detail.php';
