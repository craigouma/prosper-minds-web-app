<?php
/**
 * Event data for the rebuilt pages.
 *
 * pmEventNextEarlyBird() returns the first early-bird tier whose date has not
 * passed, or null when all three have. Deliberately computed rather than
 * seeded: a stored date would go stale silently.
 *
 * Reads never throw. A failed query returns an empty set so a page still
 * renders, matching the contract in includes/content.php.
 */

/** The columns every list view needs. One definition, so the active list and
 *  the full list can never drift into selecting different shapes. */
const PM_EVENT_LIST_COLUMNS =
    'id, title, tagline, focus_tags, date_display, event_start_date,
     location, price, image_path, agenda, is_active,
     early_bird_1_pct, early_bird_1_date,
     early_bird_2_pct, early_bird_2_date,
     early_bird_3_pct, early_bird_3_date';

/**
 * The shared list query behind pmActiveEvents() and pmAllEvents().
 *
 * Phase 3's calendar needs both: the upcoming tab shows published events, and
 * the past cohorts tab has to show schools that have already run, including
 * ones an admin has since unpublished. Writing that as a second query would put
 * two copies of the column list and the ordering in the file, which is the
 * drift this module was created to stop.
 *
 * Each variant caches independently, including caching its own failure, for the
 * reason given on pmActiveEvents(): a broken database costs one failed query
 * per variant per request rather than one per call site.
 *
 * @return array<int, array<string, mixed>> Empty on any failure.
 */
function pmFetchEventList(?PDO $pdo, bool $activeOnly): array
{
    static $cache = [];

    $key = $activeOnly ? 'active' : 'all';

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    // Cache the empty result FIRST, so a failure that somehow escapes the catch
    // below still cannot be re-run on a second call.
    $cache[$key] = [];

    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        $rows = $pdo->query(
            'SELECT ' . PM_EVENT_LIST_COLUMNS . '
               FROM events'
            . ($activeOnly ? ' WHERE is_active = 1' : '')
            . ' ORDER BY sort_order, event_start_date, id'
        )->fetchAll(PDO::FETCH_ASSOC);

        return $cache[$key] = is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        // Quiet on purpose. A visitor must not learn that a query failed; the
        // page shows its empty state instead.
        error_log('events: could not load the ' . $key . ' list, showing the empty state instead: ' . $e->getMessage());

        return [];
    }
}

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
    return pmFetchEventList($pdo, true);
}

/**
 * Every event, published or not.
 *
 * ONLY the past cohorts view should use this. The client's onboarding email is
 * explicit that past events are never deleted and never hidden, and the way an
 * admin "retires" a finished school today is to clear is_active, which is why
 * the archive cannot be built from pmActiveEvents(). An unpublished event whose
 * date is still in the future is a draft, though, and pmEventIsListable() below
 * is what keeps drafts out of the upcoming tab.
 *
 * @return array<int, array<string, mixed>> Empty on any failure.
 */
function pmAllEvents(?PDO $pdo): array
{
    return pmFetchEventList($pdo, false);
}

/**
 * One event by id, every column, published or not.
 *
 * Returns null for an unknown id and for any failure, so the caller answers 404
 * rather than a fatal. The is_active decision is deliberately NOT made here:
 * see pmEventIsListable(), which the detail page applies after the fetch so
 * that a finished school stays reachable while an unpublished future draft does
 * not.
 *
 * @return array<string, mixed>|null
 */
function pmEventById(?PDO $pdo, int $id): ?array
{
    if (!$pdo instanceof PDO || $id <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        error_log('events: could not load event ' . $id . ': ' . $e->getMessage());

        return null;
    }
}

/**
 * Has this school already started?
 *
 * The comparison is against the START date, not the end date, because
 * event_start_date is the only date column the schema has that is sortable;
 * date_display holds the human range and is free text. A school in its second
 * day is no longer something a delegate can book, so counting it as past on the
 * calendar is the honest answer as well as the only computable one.
 *
 * A row with no usable start date counts as NOT past: an event with a missing
 * date is far more likely to be a half-filled admin form than a finished
 * school, and hiding a real upcoming school in the archive is the worse error.
 */
function pmEventIsPast(array $event, ?string $today = null): bool
{
    $start = strtotime(trim((string) ($event['event_start_date'] ?? '')) . ' 00:00:00');
    $now   = strtotime(($today ?? date('Y-m-d')) . ' 00:00:00');

    if ($start === false || $now === false) {
        return false;
    }

    return $start < $now;
}

/**
 * May this event appear on a public page at all?
 *
 * Published events always may. An unpublished event may only if it has already
 * run, which is the archive case above. An unpublished future event is a draft
 * and stays invisible.
 */
function pmEventIsListable(array $event, ?string $today = null): bool
{
    if ((int) ($event['is_active'] ?? 0) === 1) {
        return true;
    }

    return pmEventIsPast($event, $today);
}

/**
 * Split a list of events into upcoming and past.
 *
 * @param array<int, array<string, mixed>> $events
 * @return array{upcoming: array<int, array<string, mixed>>, past: array<int, array<string, mixed>>}
 */
function pmPartitionEventsByDate(array $events, ?string $today = null): array
{
    $upcoming = [];
    $past     = [];

    foreach ($events as $event) {
        if (!is_array($event) || !pmEventIsListable($event, $today)) {
            continue;
        }

        if (pmEventIsPast($event, $today)) {
            $past[] = $event;
        } else {
            $upcoming[] = $event;
        }
    }

    // Most recent first in the archive: the cohort somebody is most likely to be
    // asking about is the one that just finished.
    usort($past, static function (array $a, array $b): int {
        return strcmp((string) ($b['event_start_date'] ?? ''), (string) ($a['event_start_date'] ?? ''));
    });

    return ['upcoming' => $upcoming, 'past' => $past];
}

/**
 * Event prose with em dashes removed, for printing.
 *
 * WHY THIS EXISTS RATHER THAN A ONE-OFF DATA FIX
 * ---------------------------------------------
 * The client's house rule is that no user-visible copy carries an em dash, and
 * the `events` table currently breaks it in nine places (why_intro on all four
 * schools, two agenda descriptions, two master_points lists and one tagline).
 *
 * A migration rewriting those columns would fix today and only today. Unlike
 * page_content, the events table is edited through the existing admin panel by
 * the client themselves, so the next edit can put an em dash straight back and
 * nothing would notice. Rewriting copy the client owns without asking is also
 * not this phase's call to make.
 *
 * So the rule is enforced where it is actually observable: on the way out. A
 * comma is the substitution because it is the one that stays grammatical in
 * every case in the current data, including the paired-parenthetical uses where
 * a colon would not. It is a safety net and not a substitute for the client
 * rewording the sentences properly; PHASE3-PROGRESS.md records that as an open
 * item for them.
 *
 * Whitespace around the dash is absorbed so "apart — PFM" becomes "apart, PFM"
 * rather than "apart , PFM".
 */
function pmEventProse(?string $text): string
{
    $text = (string) $text;

    if (!str_contains($text, "\xE2\x80\x94")) {
        return $text;
    }

    return (string) preg_replace('/\s*\x{2014}\s*/u', ', ', $text);
}

/**
 * A newline-separated column (audience, master_points, the three perk lists)
 * as a list of non-empty lines, em dashes already removed.
 *
 * @return array<int, string>
 */
function pmEventLines(?string $text): array
{
    $lines = preg_split('/\R/u', pmEventProse($text)) ?: [];

    return array_values(array_filter(array_map('trim', $lines), static function (string $line): bool {
        return $line !== '';
    }));
}

/**
 * The agenda as a clean list of days, or [] when there is not a usable one.
 *
 * The column carries a json_valid CHECK constraint, so in theory this cannot
 * fail. It is decoded defensively anyway: the constraint was added after the
 * rows were, it does not apply to a database restored from a dump on a server
 * that ignores CHECK, and "the schema says it cannot happen" is exactly the
 * assumption that turns a bad row into a white screen on a page selling seats.
 * A malformed agenda costs the page its agenda section, nothing more.
 *
 * @return array<int, array{day: string, title: string, desc: string}>
 */
function pmEventAgenda(array $event): array
{
    $raw = trim((string) ($event['agenda'] ?? ''));

    if ($raw === '') {
        return [];
    }

    try {
        $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log('events: agenda for event ' . (int) ($event['id'] ?? 0) . ' is not valid JSON, section omitted: ' . $e->getMessage());

        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $days = [];

    foreach ($decoded as $i => $day) {
        if (!is_array($day)) {
            continue;
        }

        $title = trim(pmEventProse((string) ($day['title'] ?? '')));
        $desc  = trim(pmEventProse((string) ($day['desc'] ?? '')));

        if ($title === '' && $desc === '') {
            continue;
        }

        $days[] = [
            // Fall back to position when 'day' is missing, so a row that lost
            // one key still numbers correctly instead of printing "Day 0".
            'day'   => trim((string) ($day['day'] ?? ($i + 1))),
            'title' => $title,
            'desc'  => $desc,
        ];
    }

    return $days;
}

/**
 * The two halves of the calendar's date block: "19 to 23" over "OCT 2026".
 *
 * date_display holds the human range ("19-23 October 2026", with an en dash in
 * the real rows) and event_start_date holds the sortable date. The day range is
 * taken from the first whitespace-delimited token of date_display and any dash
 * in it is spelled out as the word "to", which both matches the approved design
 * and sidesteps the dash question entirely.
 *
 * A date_display that does not start with a day range degrades to the start
 * date's own day number, and an unusable start date leaves the month stamp
 * empty for the caller to omit.
 *
 * @return array{range: string, stamp: string}
 */
function pmEventDateBlock(array $event): array
{
    $display = trim((string) ($event['date_display'] ?? ''));
    $ts      = strtotime(trim((string) ($event['event_start_date'] ?? '')));

    $range = '';
    $first = (string) strtok($display, " \t");

    // A leading token made only of digits and dashes is a day range ("19-23",
    // "7"). Anything else (a month name, a weekday) is not, and is ignored.
    if ($first !== '' && preg_match('/^\d{1,2}([\x{2010}-\x{2015}\-]\d{1,2})?$/u', $first) === 1) {
        $range = (string) preg_replace('/[\x{2010}-\x{2015}\-]/u', ' to ', $first);
    } elseif ($ts !== false) {
        $range = date('j', $ts);
    }

    return [
        'range' => $range,
        'stamp' => $ts === false ? '' : strtoupper(date('M Y', $ts)),
    ];
}

/**
 * The full human date range, with any dash between two day numbers spelled out:
 * "19-23 October 2026" becomes "19 to 23 October 2026".
 *
 * date_display is free text an admin types, and the four live rows use an en
 * dash. Spelling it out matches the approved design, matches the short date
 * block above, and reads correctly aloud, which "19 to 23" does and a dash
 * between two numerals does not. Nothing else about the stored string is
 * touched, so a row typed as "Week of 19 October" prints exactly that.
 */
function pmEventDatesLong(array $event): string
{
    $display = pmEventProse((string) ($event['date_display'] ?? ''));

    return (string) preg_replace('/(\d)\s*[\x{2010}-\x{2015}\-]\s*(\d)/u', '$1 to $2', $display);
}

/**
 * "5 days" for an event, counted from its agenda, or '' when there is none.
 *
 * Counted rather than stored because the schema has no duration column and
 * every school is described by its agenda. A row with no agenda simply omits
 * the meta item rather than asserting a length nothing in the data supports.
 */
function pmEventLengthLabel(array $event, string $singular = 'day', string $plural = 'days'): string
{
    $count = count(pmEventAgenda($event));

    return $count === 0 ? '' : $count . ' ' . ($count === 1 ? $singular : $plural);
}

/**
 * The first focus tag, for the calendar row's meta line.
 *
 * focus_tags is stored as a middle-dot separated string ("AI & Automation ·
 * PFM Leadership · IPSAS"). The row has space for one; the detail page shows
 * the lot.
 *
 * @return array<int, string>
 */
function pmEventFocusTags(array $event): array
{
    $raw = pmEventProse((string) ($event['focus_tags'] ?? ''));

    $parts = preg_split('/\s*(?:\x{00B7}|\||,)\s*/u', $raw) ?: [];

    return array_values(array_filter(array_map('trim', $parts), static function (string $tag): bool {
        return $tag !== '';
    }));
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
