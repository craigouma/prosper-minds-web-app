<?php
/**
 * Service detail: Data Analytics and AI Automation.
 *
 * Rebuilt. See service-pfm.php for why the inline defaults live in the page
 * file rather than in includes/layout/service-detail.php, which holds the
 * shared shape.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';

$pmService = [
    'slug'     => 'service-data',
    'pillar'   => 'data',
    'defaults' => [
        'meta_title'       => 'Data Analytics and AI Automation',
        'meta_description' => 'Practical analytics and automation for public sector finance functions: reporting cycle automation, forecasting, anomaly detection and a governance position on AI tools before procurement.',
        'hero_eyebrow'     => 'Services',
        'hero_title'       => 'Data Analytics and AI Automation',
        'hero_promise'     => 'Transform reporting from burden to strategic advantage.',
        'hero_body'        => 'Practical analytics and automation for finance functions that still spend most of the month closing the books.',
        'context_title'    => 'Why departments send teams',
        'context_body_1'   => 'Most public finance teams spend the larger part of every month producing figures rather than using them. Reconciliation and consolidation absorb the time, and by the point the numbers are ready the decision they were meant to inform has usually already been taken. This pillar starts there, with the reporting cycle itself.',
        'context_body_2'   => 'Governance is the second reason. Analytics and AI tools are already being sold into finance ministries, and procurement is moving faster than policy. A department that has decided in advance what it will automate, what it will not, and who answers when a model is wrong, negotiates from a much stronger position.',
        'outcomes_title'   => 'What a delegate returns with',
        'outcomes'         => [
            'Reconciliation and consolidation work reduced to hours',
            'Forecasts leadership is willing to act on',
            'Anomaly detection built into the audit cycle',
            'A governance position on AI tools before procurement',
        ],
        'curriculum_title' => 'Curriculum coverage',
        'topics'           => [
            'Reporting cycle automation',
            'Data quality controls',
            'Revenue and expenditure forecasting',
            'Fraud and anomaly analytics',
            'Dashboard design for oversight',
            'AI governance and procurement',
        ],
        'audience_title'   => 'Who it is for',
        'audience'         => [
            'Chief accountants and finance directors',
            'Budget controllers and analysts',
            'Internal and external auditors',
            'Heads of ICT in finance ministries',
            'Monitoring and evaluation officers',
        ],
        'format_title'     => 'How it is taught',
        'format_body'      => 'Five days, residential, with the cohort capped so that faculty stay reachable throughout. The technical days are worked through on real reporting data rather than on a demonstration dataset, and day five is spent building the action plan each delegate takes back to their department. Every school carries CPD certification.',
        'events_title'     => 'Schools covering this pillar',
        'events_empty'     => 'No school covering this pillar is in the 2026 calendar yet. Contact the programme office and we will tell you when one is scheduled.',
        'related_tags'     => ['Data Analytics', 'AI & Automation', 'Smart Finance', 'Budgeting', 'Revenue & Funding'],
        'cta_eyebrow'      => 'Next step',
        'cta_title'        => 'Ask for the full course outline',
        'cta_body'         => 'The programme office replies within one working day, and will say plainly if a different pillar fits your team better.',
        'cta_label'        => 'Contact the programme office',
    ],
];

require __DIR__ . '/includes/layout/service-detail.php';
