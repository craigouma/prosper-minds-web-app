<?php
/**
 * Event card and event grid rendering, shared by every Phase 2 page that shows
 * courses.
 *
 * WHY THIS IS A PARTIAL
 * ---------------------
 * Five Phase 2 pages print an event grid: the homepage, the services overview,
 * and each of the three service detail pages. Phase 3's events listing will be
 * the sixth. Open-coding the card five times is how the live index.php ended up
 * printing early bird tiers that had already lapsed: the rule existed in one
 * place and the markup in another, and they drifted.
 *
 * So the card lives here, the early bird rule lives in includes/events.php, and
 * a page decides only which events to show and what to label the actions.
 *
 * TWO VARIANTS, AND WHY THERE ARE EXACTLY TWO
 * -------------------------------------------
 *   'full'     The homepage grid. The event's own designed banner image is the
 *              visual anchor (Section 3 of the brief is explicit that these are
 *              a real asset and must not be swapped for stock photography),
 *              then the early bird position, title, place, dates, price and the
 *              two actions.
 *   'compact'  "Schools covering this pillar" on the services pages. No banner
 *              and no price: the delegate is on a curriculum page, not a
 *              shopping page, and the question the section answers is "when and
 *              where", not "how much". This is what the approved prototype's
 *              services screen shows.
 *
 * A third variant should be a reason to check whether the section really needs
 * a different card or just different copy.
 *
 * LABELS ARE PASSED IN, RAW
 * -------------------------
 * Every visible string a page can edit comes from page_content, so the caller
 * passes the values it read with pmContent() (raw) and this file escapes them
 * with pmEsc(). Passing pmContentSafe() output here would double-escape.
 *
 * NO TAGLINE IS PRINTED, DELIBERATELY
 * -----------------------------------
 * events.tagline is real copy and it is tempting to show it. It is not printed
 * because the tagline on event 5 contains an em dash, and the client's
 * instruction is that no user-visible copy carries one. The prototype's cards
 * do not show a tagline either, so nothing is lost visually. See
 * PHASE2-PROGRESS.md: the tagline needs rewording by the client before Phase 3
 * builds an event detail page around it.
 */

/** The public detail page for one event. Phase 3 redesigns it; the URL holds. */
function pmEventDetailUrl(array $event): string
{
    return '/event.php?id=' . (int) ($event['id'] ?? 0);
}

/** The registration entry point for one event. Phase 4 redesigns the flow. */
function pmEventRegisterUrl(array $event): string
{
    return '/event-registration.php?id=' . (int) ($event['id'] ?? 0);
}

/**
 * Root-relative URL for an event's designed banner, or '' when there is none.
 *
 * events.image_path is stored without a leading slash ("assets/images/x.jpg")
 * and several of the real filenames contain spaces, so each path segment is
 * encoded. A row pointing at nothing returns '' and the caller omits the
 * banner rather than rendering a broken image icon.
 */
function pmEventImageUrl(array $event): string
{
    $path = trim((string) ($event['image_path'] ?? ''));

    if ($path === '') {
        return '';
    }

    $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));

    return '/' . implode('/', $segments);
}

/**
 * "Oct 2026" for an event, from the sortable date rather than date_display.
 *
 * date_display holds the full human range ("19-23 October 2026"); this is the
 * short stamp the compact card leads with. Returns '' if the date is unusable,
 * and the caller simply omits the stamp.
 */
function pmEventMonthLabel(array $event): string
{
    $ts = strtotime((string) ($event['event_start_date'] ?? ''));

    return $ts === false ? '' : date('M Y', $ts);
}

/**
 * One event card.
 *
 * @param array<string, mixed>  $event  A row from pmActiveEvents().
 * @param array<string, string> $labels details / register / badge_lapsed, raw.
 * @param string                $variant 'full' or 'compact'.
 * @param int                   $index  Zero-based position, for the 01/02 ordinal.
 * @param int                   $level  Heading level for the card title.
 */
function pmRenderEventCard(array $event, array $labels, string $variant, int $index, int $level): void
{
    $title    = (string) ($event['title'] ?? '');
    $location = (string) ($event['location'] ?? '');
    $dates    = (string) ($event['date_display'] ?? '');
    $price    = (string) ($event['price'] ?? '');
    $h        = 'h' . max(2, min(6, $level));
    $detail   = pmEventDetailUrl($event);

    // Computed on every render from the three tier columns, never seeded. Once
    // every tier has lapsed this is the caller's own lapsed label.
    $badge = pmEarlyBirdBadge($event, (string) ($labels['badge_lapsed'] ?? 'Standard rate'));

    if ($variant === 'compact') {
        $month = pmEventMonthLabel($event);
        ?>
      <article class="pm-cell">
<?php if ($month !== ''): ?>
        <span class="pm-label pm-label--green"><?php echo pmEsc($month); ?></span>
<?php endif; ?>
        <<?php echo $h; ?> class="pm-h4"><?php echo pmEsc($title); ?></<?php echo $h; ?>>
<?php if ($location !== ''): ?>
        <span class="pm-caption"><?php echo pmEsc($location); ?></span>
<?php endif; ?>
        <a class="pm-btn--link pm-cell__action" href="<?php echo pmEsc($detail); ?>">
          <?php echo pmEsc((string) ($labels['details'] ?? 'Details')); ?>
          <span class="pm-sr-only"> about <?php echo pmEsc($title); ?></span>
        </a>
      </article>
<?php
        return;
    }

    $image = pmEventImageUrl($event);
    ?>
      <article class="pm-tile">
<?php if ($image !== ''): ?>
        <div class="pm-banner">
          <?php // The client's own designed promotional banner. Alt names the
                // course because that is the information the image carries. ?>
          <img src="<?php echo pmEsc($image); ?>"
               alt="Promotional banner for <?php echo pmEsc($title); ?>"
               loading="lazy" decoding="async">
        </div>
<?php endif; ?>
        <div class="pm-cell">
          <div class="pm-cell__meta">
            <span class="pm-ordinal"><?php echo pmEsc(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
            <span class="pm-label pm-label--green"><?php echo pmEsc($badge); ?></span>
          </div>

          <<?php echo $h; ?> class="pm-h4"><?php echo pmEsc($title); ?></<?php echo $h; ?>>

          <div class="pm-stack pm-stack--xs">
<?php if ($location !== ''): ?>
            <span class="pm-caption"><?php echo pmEsc($location); ?></span>
<?php endif; ?>
<?php if ($dates !== ''): ?>
            <span class="pm-caption"><?php echo pmEsc($dates); ?></span>
<?php endif; ?>
          </div>

          <div class="pm-card__foot">
<?php if ($price !== ''): ?>
            <span class="pm-label"><?php echo pmEsc($price); ?></span>
<?php endif; ?>
            <div class="pm-btn-row pm-mt-sm">
              <a class="pm-btn--link" href="<?php echo pmEsc($detail); ?>">
                <?php echo pmEsc((string) ($labels['details'] ?? 'Details')); ?>
                <span class="pm-sr-only"> about <?php echo pmEsc($title); ?></span>
              </a>
              <a class="pm-btn--link" href="<?php echo pmEsc(pmEventRegisterUrl($event)); ?>">
                <?php echo pmEsc((string) ($labels['register'] ?? 'Register')); ?>
                <span class="pm-sr-only"> for <?php echo pmEsc($title); ?></span>
              </a>
            </div>
          </div>
        </div>
      </article>
<?php
}

/**
 * A grid of event cards, or the page's own empty message when there are none.
 *
 * The empty message is required rather than optional. An empty grid is a real
 * state on every one of these pages (pmActiveEvents() returns [] on any
 * database failure, and service-sustainability matches no event in the 2026
 * calendar at all), and a silent gap where a calendar should be reads as a
 * broken page. Each page passes its own wording because the honest sentence
 * differs: "dates are being confirmed" on the homepage, "no school covering
 * this pillar is scheduled yet" on a service page.
 *
 * @param array<int, array<string, mixed>> $events
 * @param array<string, string>            $labels
 */
function pmRenderEventGrid(
    array $events,
    array $labels,
    string $emptyMessage,
    string $variant = 'full',
    int $level = 3
): void {
    if ($events === []) {
        ?>
      <p class="pm-body pm-mt-lg"><?php echo pmEsc($emptyMessage); ?></p>
<?php
        return;
    }

    $columns = $variant === 'compact' ? 'pm-grid--3' : 'pm-grid--4';
    ?>
    <div class="pm-grid pm-grid--ruled <?php echo $columns; ?> pm-mt-lg">
<?php foreach (array_values($events) as $i => $event): ?>
<?php     pmRenderEventCard($event, $labels, $variant, (int) $i, $level); ?>
<?php endforeach; ?>
    </div>
<?php
}
