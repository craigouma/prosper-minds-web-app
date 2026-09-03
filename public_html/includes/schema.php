<?php

/**
 * schema.org/Event markup for a course.
 *
 * PROJECT.md flags this as a real missed opportunity: an events business with
 * no event structured data is invisible to the rich results that put dates,
 * places and prices straight into a search listing.
 */
function pmEventSchema(array $event, string $origin): string
{
    $start = pmSchemaStartDate($event);

    if ($start === null) {
        return '';
    }

    [$currency, $price] = function_exists('parseEventPrice')
        ? parseEventPrice((string) ($event['price'] ?? ''))
        : ['USD', 0.0];

    $location = trim((string) ($event['location'] ?? ''));
    $parts    = array_map('trim', explode(',', $location));

    $data = [
        '@context'            => 'https://schema.org',
        '@type'               => 'EducationEvent',
        // pmEventProse strips the em dashes the client asked never to appear.
        // The page already does this; structured data is page content too.
        'name'                => pmEventProse((string) ($event['title'] ?? '')),
        'description'         => mb_substr(pmEventProse(trim(strip_tags((string) ($event['tagline'] ?? '')))), 0, 300),
        'startDate'           => $start,
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'organizer'           => [
            '@type' => 'Organization',
            'name'  => 'Prosperminds',
            'url'   => $origin,
        ],
        'url' => $origin . '/event.php?id=' . (int) ($event['id'] ?? 0),
    ];

    if ($location !== '') {
        $data['location'] = [
            '@type'   => 'Place',
            'name'    => $location,
            'address' => [
                '@type'           => 'PostalAddress',
                'addressLocality' => $parts[0] ?? $location,
                'addressCountry'  => $parts[1] ?? '',
            ],
        ];
    }

    // A cohort that has already run, or one taken off the site, must not be
    // advertised as bookable. Structured data is read by machines that will
    // happily offer seats nobody can buy.
    $isOpen = (int) ($event['is_active'] ?? 0) === 1 && strtotime($start) >= strtotime(date('Y-m-d'));

    if ($price > 0 && $isOpen) {
        $data['offers'] = [
            '@type'         => 'Offer',
            'price'         => number_format($price, 2, '.', ''),
            'priceCurrency' => $currency,
            'availability'  => 'https://schema.org/InStock',
            'url'           => $origin . '/event-registration.php?id=' . (int) ($event['id'] ?? 0),
        ];
    }

    if (!empty($event['image_path'])) {
        $data['image'] = $origin . '/' . ltrim((string) $event['image_path'], '/');
    }

    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return '';
    }

    // The closing tag is escaped so a title containing one cannot end the
    // script element early and turn structured data into markup injection.
    return '<script type="application/ld+json">' . str_replace('</', '<\/', $json) . '</script>';
}

/**
 * An ISO date for the event, or null when there is nothing trustworthy.
 *
 * Structured data with a wrong date is worse than none: search engines show it
 * to people, who then arrive on the wrong week.
 */
function pmSchemaStartDate(array $event): ?string
{
    $explicit = trim((string) ($event['event_start_date'] ?? ''));

    if ($explicit !== '' && $explicit !== '0000-00-00') {
        $ts = strtotime($explicit);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
    }

    return null;
}
