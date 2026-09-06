<?php
/**
 * Service detail: Sustainability Reporting.
 *
 * Rebuilt. See service-pfm.php for why the inline defaults live in the page
 * file rather than in includes/layout/service-detail.php, which holds the
 * shared shape.
 *
 * NOTE ON THE CALENDAR SECTION
 * ----------------------------
 * related_tags below currently matches NO event, and that is correct rather
 * than broken. No school in the 2026 calendar covers sustainability reporting:
 * not in its title, not in its focus tags, and not in any line of any of the
 * four five-day agendas. The section therefore renders events_empty, which says
 * so plainly. Falling back to "show all four schools" under a heading that says
 * "schools covering this pillar" would tell a delegate they can book something
 * that does not exist, on a page selling USD 599 seats. See the comment on
 * pmEventsMatchingTags() in includes/events.php.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';

$pmService = [
    'slug'     => 'service-sustainability',
    'pillar'   => 'sustainability',
    'defaults' => [
        'meta_title'       => 'Sustainability Reporting',
        'meta_description' => 'Climate and sustainability disclosure for public institutions now being asked for it by lenders, auditors and citizens. Scope, data ownership, assurance readiness.',
        'hero_eyebrow'     => 'Services',
        'hero_title'       => 'Sustainability Reporting',
        'hero_promise'     => 'Meet global standards while strengthening transparency.',
        'hero_body'        => 'Climate and sustainability disclosure for public institutions now being asked for it by lenders, auditors and citizens.',
        'context_title'    => 'Why departments send teams',
        'context_body_1'   => 'Sustainability disclosure arrived in the public sector from the outside. Lenders ask for it as a condition of funding, auditors ask for it because it is now in scope, and citizens ask for it because the spending is theirs. Very few institutions were given a budget or a team to answer with.',
        'context_body_2'   => 'The practical questions are the same everywhere. What is in scope, who owns the data, how does the reporting fit the financial calendar, and what will stand up to assurance. This pillar works through those four in order, rather than starting from a framework and hoping the underlying data exists.',
        'outcomes_title'   => 'What a delegate returns with',
        'outcomes'         => [
            'A disclosure scope decision you can defend',
            'Data collection assigned to real owners',
            'Alignment with lender and donor requirements',
            'Reporting integrated with the financial calendar',
        ],
        'curriculum_title' => 'Curriculum coverage',
        'topics'           => [
            'Disclosure frameworks and their public sector fit',
            'Materiality assessment',
            'Emissions and resource data collection',
            'Climate risk in fiscal planning',
            'Assurance readiness',
            'Reporting to oversight and lenders',
        ],
        'audience_title'   => 'Who it is for',
        'audience'         => [
            'Finance directors and reporting managers',
            'Internal auditors',
            'Planning and economic affairs officials',
            'Debt management office staff',
            'Sub-national finance officers',
        ],
        'format_title'     => 'How it is taught',
        'format_body'      => "Five days, residential, with the cohort capped so that faculty stay reachable throughout. Delegates work on their own institution's reporting scope through the week, and day five is spent building the action plan each delegate takes back to their department. Every school carries CPD certification.",
        'events_title'     => 'Schools covering this pillar',
        'events_empty'     => 'No school covering this pillar is in the 2026 calendar yet. Contact the programme office and we will tell you when one is scheduled.',
        'related_tags'     => ['Sustainability', 'Climate', 'Disclosure'],
        'cta_eyebrow'      => 'Next step',
        'cta_title'        => 'Ask for the full course outline',
        'cta_body'         => 'The programme office replies within one working day, and will say plainly if a different pillar fits your team better.',
        'cta_label'        => 'Contact the programme office',
    ],
];

require __DIR__ . '/includes/layout/service-detail.php';
