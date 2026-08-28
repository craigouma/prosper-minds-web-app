<?php
/**
 * Event data for the rebuilt public pages.
 *
 * WHY THIS EXISTS
 * ---------------
 * Phase 2 pages (homepage, services overview, the three service detail pages)
 * all need the same three things from the `events` table: the list of active
 * events, a city label, and the early bird position. Before this file each of
 * those was open-coded in index.php, which is how the live homepage ended up
 * printing every early bird tier including the ones that had already lapsed.
 *
 * THE EARLY BIRD RULE, AND WHY IT IS COMPUTED
 * -------------------------------------------
 * The approved prototype contains an invented deadline ("Seats for the October
 * cohort close on 12 September", "Twenty per cent applies until 15 July 2026").
 * Neither is true of the real data, and a wrong deadline on a page selling USD
 * 599 seats is worse than no deadline. PHASE1-FOUNDATION-PROGRESS.md section 8.3
 * therefore left home.cta_title deliberately unseeded.
 *
 * The fix is not to seed a corrected date, because a seeded date goes stale the
 * moment it passes and nobody notices. It is to compute the position from the
 * three tier columns the `events` table already carries, every time the page
 * renders. pmEventNextEarlyBird() below is the whole of that rule, and
 * local-dev/verify.sh section 10b pins it against fixed dates, including the
 * case where every tier has lapsed.
 *
 * SAFETY CONTRACT
 * ---------------
 * Same discipline as includes/content.php and includes/funnel.php. A public
 * marketing page whose real job is to render must not be taken down by the
 * events query, so:
 *
 *   1. pmActiveEvents() catches Throwable, not Exception, and returns [] on any
 *      failure. Callers render an empty state, not a 500.
 *   2. Nothing here throws, echoes, or sets an HTTP status.
 *   3. pmEventNextEarlyBird() is pure: it takes an already-fetched row and a
 *      date string, touches no database and no globals, which is what makes it
 *      testable against fixed dates rather than against "today".
 *
 * This file is loaded defensively by includes/layout/page.php, which substitutes
 * no-op stand-ins with the same signatures if it is missing or fails to parse.
 * Call sites need no guard of their own.
 */

/**
 * Every active event, in the order the site displays them.
 *
 * Cached per request: the homepage asks for the list once, but a service page
 * asks for it and then filters it, and the sitemap may ask again.
 *
 * @return array<int, array<string, mixed>> Empty on any failure.
 */
function pmActiveEvents(?PDO $pdo): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    // Cache the empty result FIRST, so a failure that somehow escapes the catch
    // below still cannot be re-run on a second call.
    $cache = [];

    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        $rows = $pdo->query(
            'SELECT id, title, tagline, focus_tags, date_display, event_start_date,
                    location, price, image_path,
                    early_bird_1_pct, early_bird_1_date,
                    early_bird_2_pct, early_bird_2_date,
                    early_bird_3_pct, early_bird_3_date
               FROM events
              WHERE is_active = 1
              ORDER BY sort_order, event_start_date, id'
        )->fetchAll(PDO::FETCH_ASSOC);

        return $cache = is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        // Quiet on purpose. A visitor must not learn that a query failed; the
        // page shows its empty state instead.
        error_log('events: could not load the active list, showing the empty state instead: ' . $e->getMessage());

        return [];
    }
}

/**
 * The next early bird tier that has NOT yet lapsed, or null if all have.
 *
 * CONTRACT
 * --------
 * Input is one row from pmActiveEvents() (or anything with the six
 * early_bird_N_pct / early_bird_N_date keys) and a date to compare against.
 *
 * Returns, for the winning tier:
 *   ['pct' => int, 'date' => 'YYYY-MM-DD', 'date_display' => '19 September 2026']
 * or null.
 *
 * The rules, each of which exists because the data really contains the case:
 *
 *   * Tiers are considered in DATE order, not in column order. The live data
 *     happens to run 1 -> 2 -> 3 earliest to latest, but nothing in the schema
 *     enforces that and an admin edit could invert two of them. Sorting by date
 *     means the answer stays "the deadline a delegate meets next" either way.
 *   * A deadline is INCLUSIVE. "10% runs to 2026-09-19" means a registration on
 *     the 19th still gets it, so the comparison is >= today, not > today.
 *   * A tier with no date, an unparseable date, or a percentage of zero or less
 *     is skipped rather than treated as an offer. All three are what a
 *     half-filled admin form produces.
 *   * All tiers lapsed returns null, which is a real state the site has to
 *     render: as of 2026-08-28 Cape Town has already lost its 20% and 15%
 *     tiers, and every event will reach the all-lapsed state eventually.
 *
 * $today defaults to the server's date. It is a parameter so the test suite can
 * pin the answer to a fixed date rather than depending on when it runs.
 *
 * @param array<string, mixed> $event
 * @return array{pct: int, date: string, date_display: string}|null
 */
function pmEventNextEarlyBird(array $event, ?string $today = null): ?array
{
    $todayTs = strtotime(($today ?? date('Y-m-d')) . ' 00:00:00');

    if ($todayTs === false) {
        return null;
    }

    $tiers = [];

    for ($n = 1; $n <= 3; $n++) {
        $pct = (int) ($event['early_bird_' . $n . '_pct'] ?? 0);
        $raw = trim((string) ($event['early_bird_' . $n . '_date'] ?? ''));

        if ($pct <= 0 || $raw === '' || $raw === '0000-00-00') {
            continue;
        }

        $ts = strtotime($raw . ' 00:00:00');

        if ($ts === false || $ts < $todayTs) {
            continue;
        }

        $tiers[] = [
            'pct'          => $pct,
            'date'         => date('Y-m-d', $ts),
            'date_display' => date('j F Y', $ts),
            'ts'           => $ts,
        ];
    }

    if ($tiers === []) {
        return null;
    }

    // Soonest deadline wins. On a tie, the larger saving wins, because that is
    // the one a delegate would actually take.
    usort($tiers, static function (array $a, array $b): int {
        return $a['ts'] <=> $b['ts'] ?: $b['pct'] <=> $a['pct'];
    });

    return [
        'pct'          => $tiers[0]['pct'],
        'date'         => $tiers[0]['date'],
        'date_display' => $tiers[0]['date_display'],
    ];
}

/**
 * The event whose early bird deadline falls soonest, across a list.
 *
 * This is what the homepage's single closing call to action is phrased from:
 * one band, one real deadline, and it is always the next one anybody can still
 * meet. Returns null when no event in the list has an un-lapsed tier, which is
 * the state the caller must degrade to a non-dated message for.
 *
 * @param array<int, array<string, mixed>> $events
 * @return array{event: array<string, mixed>, early_bird: array{pct: int, date: string, date_display: string}}|null
 */
function pmSoonestEarlyBird(array $events, ?string $today = null): ?array
{
    $best = null;

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $eb = pmEventNextEarlyBird($event, $today);

        if ($eb === null) {
            continue;
        }

        if ($best === null
            || $eb['date'] < $best['early_bird']['date']
            || ($eb['date'] === $best['early_bird']['date'] && $eb['pct'] > $best['early_bird']['pct'])
        ) {
            $best = ['event' => $event, 'early_bird' => $eb];
        }
    }

    return $best;
}

/**
 * The city out of a `location` value, for copy that reads "the Bali school".
 *
 * `location` is stored as "City, Country" ("Cape Town, South Africa"). A row
 * without a comma is returned whole rather than truncated, so a future
 * single-word location still reads correctly.
 */
function pmEventCity(array $event): string
{
    $location = trim((string) ($event['location'] ?? ''));

    if ($location === '') {
        return '';
    }

    $city = trim((string) strtok($location, ','));

    return $city !== '' ? $city : $location;
}

/**
 * A short early bird badge for an event card: "20% until 7 Sep 2026".
 *
 * $lapsedLabel is what to show once every tier has passed. It is a parameter
 * rather than a literal because it is editable copy and belongs in
 * page_content, the same as every other visible string.
 */
function pmEarlyBirdBadge(array $event, string $lapsedLabel, ?string $today = null): string
{
    $eb = pmEventNextEarlyBird($event, $today);

    if ($eb === null) {
        return $lapsedLabel;
    }

    return $eb['pct'] . '% until ' . date('j M Y', (int) strtotime($eb['date']));
}

/**
 * Fill a copy template with a computed early bird position.
 *
 * The template is a page_content row so the client can reword the sentence
 * without a deploy, while the numbers and the date inside it stay computed and
 * therefore cannot go stale. Recognised placeholders:
 *
 *   {pct}    20
 *   {city}   Bali
 *   {date}   7 September 2026
 *   {title}  Data-Driven Budget Control, Revenue Growth & Funding Breakthroughs
 *
 * An unknown placeholder is left in place rather than blanked, so a typo in the
 * CMS is visible to whoever made it instead of silently eating a word.
 *
 * @param array{pct: int, date: string, date_display: string} $earlyBird
 * @param array<string, mixed> $event
 */
function pmEarlyBirdFill(string $template, array $earlyBird, array $event): string
{
    return strtr($template, [
        '{pct}'   => (string) $earlyBird['pct'],
        '{city}'  => pmEventCity($event),
        '{date}'  => $earlyBird['date_display'],
        '{title}' => (string) ($event['title'] ?? ''),
    ]);
}

/**
 * The subset of a list of events whose focus tags or title match any of $tags.
 *
 * Used by the three service detail pages to answer "which schools cover this
 * pillar" without a hardcoded id-to-pillar map in PHP. The tags themselves are
 * a page_content row per service page, so the mapping is editable content and
 * survives an event being added or renamed.
 *
 * NO MATCH RETURNS AN EMPTY LIST, DELIBERATELY.
 *
 * The tempting fallback is to return the whole list, on the grounds that an
 * empty grid looks broken. It is the wrong trade here. As of August 2026 no
 * event in the calendar covers sustainability reporting: not the title, not the
 * focus tags, and not a single line of any of the four five-day agendas. A
 * fallback that showed all four schools under "schools covering this pillar"
 * would be telling a delegate they can book something that does not exist, on
 * a page selling USD 599 seats.
 *
 * So the honest answer is nothing, and the caller renders a seeded line saying
 * no school is scheduled for that pillar yet. The cost of this choice is that a
 * typo in a related_tags row empties the section silently, the same failure
 * mode every page_content key already has.
 *
 * An empty $tags list means "no filter was configured" rather than "nothing
 * matched", so that case does return everything.
 *
 * @param array<int, array<string, mixed>> $events
 * @param array<int, string> $tags
 * @return array<int, array<string, mixed>>
 */
function pmEventsMatchingTags(array $events, array $tags): array
{
    $needles = [];

    foreach ($tags as $tag) {
        $tag = strtolower(trim((string) $tag));
        if ($tag !== '') {
            $needles[] = $tag;
        }
    }

    if ($needles === [] || $events === []) {
        return $events;
    }

    $matched = [];

    foreach ($events as $event) {
        $haystack = strtolower(
            (string) ($event['focus_tags'] ?? '') . ' ' . (string) ($event['title'] ?? '')
        );

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $matched[] = $event;
                break;
            }
        }
    }

    return $matched;
}
