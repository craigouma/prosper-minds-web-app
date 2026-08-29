<?php
/**
 * The CPD calendar.
 *
 * New in Phase 3. Until now "Events" was an anchor down the homepage, which is
 * the problem Section 4.1 of the design brief names: nav links that pretend to
 * be pages. This is a real URL, and pmNavItems() now points at it.
 *
 * UPCOMING AND PAST ARE TWO URLS, NOT A TAB
 * -----------------------------------------
 * The filter is a pair of links carrying ?show=, resolved on the server. It is
 * not a JavaScript toggle, for three reasons: the calendar stays usable with
 * scripts off, "the past cohorts" becomes a page somebody can link to and a
 * search engine can index, and the browser's own back button does the right
 * thing. The approved prototype draws it as a segmented control, which
 * .pm-switch--boxed is.
 *
 * PAST COHORTS ARE NEVER HIDDEN
 * -----------------------------
 * The client's onboarding email is explicit that past events must be preserved
 * and never deleted. The way an admin retires a finished school today is to
 * clear is_active, so the archive is built from pmAllEvents() rather than
 * pmActiveEvents(), and pmEventIsListable() is what still keeps an unpublished
 * FUTURE event (a draft) off the page. See includes/events.php.
 *
 * Every visible label comes from page_content slug 'events'. Every fact about a
 * school comes from the `events` table, and the early bird position is computed
 * on each render rather than stored, so it cannot go stale.
 *
 * House style: no em dashes in any user-visible copy. Client instruction, and
 * it applies to the database rows this page prints as much as to the copy in
 * it, which is what pmEventProse() is for.
 */

require_once __DIR__ . '/includes/layout/page.php';

// Two views, and only two. Anything else in the query string is the upcoming
// calendar, so a hand-edited or truncated URL lands on a real page.
$pmShowPast = (($_GET['show'] ?? '') === 'past');

$pmSplit    = pmPartitionEventsByDate(pmAllEvents($pdo));
$pmUpcoming = $pmSplit['upcoming'];
$pmPast     = $pmSplit['past'];
$pmShown    = $pmShowPast ? $pmPast : $pmUpcoming;

// The badge for a school whose every early bird tier has lapsed. Editable copy,
// so it is a content row rather than a literal inside includes/events.php.
$pmLapsedLabel = pmContent($pdo, 'global', 'early_bird_lapsed_label', 'Standard rate');

/** "{n} scheduled schools", with the singular handled rather than fudged. */
$pmCountLabel = static function (int $n, string $one, string $many): string {
    return str_replace('{n}', (string) $n, $n === 1 ? $one : $many);
};

pmPageBegin([
    'slug'        => 'events',
    'nav'         => 'events',
    'title'       => pmContent($pdo, 'events', 'meta_title', 'CPD calendar | Prosperminds'),
    'description' => pmContent($pdo, 'events', 'meta_description', 'Every Prosperminds residential school, with dates, locations and early-bird deadlines confirmed twelve months ahead.'),
    'canonical'   => '/events.php',
    'scripts'     => ['/assets/js/pm-copy-link.js'],
]);
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'events', 'hero_eyebrow',
      'CPD calendar'); ?></span>

    <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'events', 'hero_title',
      'Courses and residential schools'); ?></h1>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'events', 'hero_body',
      'Every course runs five days, carries CPD certification and is capped to keep faculty access high. Dates are confirmed twelve months ahead so departments can budget for them.'); ?></p>

  </div>
</section>


<?php // ── The calendar itself ───────────────────────────────────────────── ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">

    <?php // The filter. Real links, resolved server side, marked with
          // aria-current so the state is announced and not merely coloured. ?>
    <div class="pm-listing__bar">
      <nav class="pm-switch pm-switch--boxed" aria-label="<?php echo pmContentSafe($pdo, 'events', 'filter_aria',
        'Filter the calendar'); ?>">
        <a class="pm-switch__link" href="/events.php"
           <?php echo $pmShowPast ? '' : 'aria-current="page"'; ?>><?php
          echo pmContentSafe($pdo, 'events', 'filter_upcoming', 'Upcoming'); ?></a>
        <a class="pm-switch__link" href="/events.php?show=past"
           <?php echo $pmShowPast ? 'aria-current="page"' : ''; ?>><?php
          echo pmContentSafe($pdo, 'events', 'filter_past', 'Past cohorts'); ?></a>
      </nav>

      <p class="pm-caption pm-listing__count"><?php
        echo pmEsc($pmShowPast
          ? $pmCountLabel(
              count($pmPast),
              pmContent($pdo, 'events', 'count_past_one', '1 past cohort'),
              pmContent($pdo, 'events', 'count_past', '{n} past cohorts')
            )
          : $pmCountLabel(
              count($pmUpcoming),
              pmContent($pdo, 'events', 'count_upcoming_one', '1 scheduled school'),
              pmContent($pdo, 'events', 'count_upcoming', '{n} scheduled schools')
            )); ?></p>
    </div>

    <?php // The heading exists for the document outline and for a screen reader
          // announcing which of the two lists is on screen. It is not drawn,
          // because the filter above it already says so visually. ?>
    <h2 class="pm-sr-only"><?php echo $pmShowPast
      ? pmContentSafe($pdo, 'events', 'past_heading', 'Past cohorts')
      : pmContentSafe($pdo, 'events', 'upcoming_heading', 'Upcoming schools'); ?></h2>

<?php if ($pmShown === []): ?>
    <p class="pm-body pm-mt-lg"><?php echo $pmShowPast
      ? pmContentSafe($pdo, 'events', 'past_empty', 'No school has run yet. The first cohort of the 2026 calendar is listed under Upcoming.')
      : pmContentSafe($pdo, 'events', 'upcoming_empty', 'Dates for the next calendar are being confirmed. Join the mailing list below and they will reach you as soon as they are.'); ?></p>
<?php else: ?>
    <ul class="pm-listing pm-mt-md">
<?php foreach ($pmShown as $pmEvent): ?>
<?php
      $pmTitle  = pmEventProse((string) ($pmEvent['title'] ?? ''));
      $pmImage  = pmEventImageUrl($pmEvent);
      $pmDate   = pmEventDateBlock($pmEvent);
      $pmTags   = pmEventFocusTags($pmEvent);
      $pmLength = pmEventLengthLabel($pmEvent);
      $pmPrice  = pmEventProse((string) ($pmEvent['price'] ?? ''));
      $pmPlace  = pmEventProse((string) ($pmEvent['location'] ?? ''));
?>
      <li>
        <div class="pm-listing__row">

          <?php // A row with no banner keeps its column empty rather than
                // rendering a broken image, and the grid holds its shape. ?>
          <div class="pm-banner">
<?php if ($pmImage !== ''): ?>
            <img src="<?php echo pmEsc($pmImage); ?>"
                 alt="Promotional banner for <?php echo pmEsc($pmTitle); ?>"
                 loading="lazy" decoding="async">
<?php endif; ?>
          </div>

          <div class="pm-listing__date">
<?php if ($pmDate['range'] !== ''): ?>
            <span class="pm-listing__range"><?php echo pmEsc($pmDate['range']); ?></span>
<?php endif; ?>
<?php if ($pmDate['stamp'] !== ''): ?>
            <span class="pm-label"><?php echo pmEsc($pmDate['stamp']); ?></span>
<?php endif; ?>
          </div>

          <div>
            <h3 class="pm-h3"><?php echo pmEsc($pmTitle); ?></h3>
            <div class="pm-listing__meta pm-mt-sm">
<?php if ($pmPlace !== ''): ?>
              <span class="pm-caption"><?php echo pmEsc($pmPlace); ?></span>
<?php endif; ?>
<?php if ($pmLength !== ''): ?>
              <span class="pm-caption"><?php echo pmEsc($pmLength); ?></span>
<?php endif; ?>
<?php if (isset($pmTags[0])): ?>
              <span class="pm-caption"><?php echo pmEsc($pmTags[0]); ?></span>
<?php endif; ?>
            </div>
          </div>

          <div class="pm-listing__side">
            <?php // The price prints as stored ("From USD 599 Per Delegate")
                  // rather than through .pm-label, which would set it in caps
                  // and letter-spacing wide enough to wrap the column. ?>
<?php if ($pmPrice !== ''): ?>
            <span class="pm-caption"><?php echo pmEsc($pmPrice); ?></span>
<?php endif; ?>
            <?php // Computed from the three tier columns on every render, never
                  // seeded. A past cohort has no live offer, so the badge is
                  // suppressed rather than printing a lapsed deadline. ?>
<?php if (!$pmShowPast): ?>
            <span class="pm-label pm-label--green"><?php
              echo pmEsc(pmEarlyBirdBadge($pmEvent, $pmLapsedLabel)); ?></span>
<?php endif; ?>
            <a class="pm-btn pm-btn--invert pm-btn--sm" href="<?php echo pmEsc(pmEventDetailUrl($pmEvent)); ?>">
              <?php echo pmContentSafe($pdo, 'events', 'row_cta', 'View event'); ?>
              <span class="pm-sr-only"> <?php echo pmEsc($pmTitle); ?></span>
            </a>
          </div>

        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($pmShowPast): ?>
    <p class="pm-caption pm-mt-lg pm-measure"><?php echo pmContentSafe($pdo, 'events', 'past_note',
      'Past cohorts are listed for reference. Delegate materials remain available through the alumni portal for twelve months after each school.'); ?></p>
<?php endif; ?>

  </div>
</section>


<?php // ── Banner library ────────────────────────────────────────────────── ?>
<?php // Revision 2 of the brief asked for this: the client's partner network
      // keeps circulating out-of-date banners because there is nowhere to get
      // the current one. Only schools that still have a banner file appear, so
      // the section can never show an empty frame captioned "download".
      $pmBanners = array_values(array_filter($pmUpcoming, static function (array $e): bool {
          return pmEventImageUrl($e) !== '';
      })); ?>
<?php if ($pmBanners !== []): ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'events', 'banners_eyebrow',
      'Banner library'); ?></span>

    <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'events', 'banners_title',
      'Current promotional banners'); ?></h2>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'events', 'banners_body',
      'Every live event banner in one place, at the size it is published. Pull from here for LinkedIn and partner mailings so the version in circulation is always the current one.'); ?></p>

    <?php // --pm-col-min is .pm-grid--auto's documented knob for where a column
          // drops. 300px holds the library to three across at desktop, the way
          // the approved prototype lays it out, rather than the four that the
          // fixed --3 breakpoint would fit into this container and squeeze the
          // course titles into. ?>
    <div class="pm-grid pm-grid--auto pm-mt-lg" style="--pm-col-min: 300px;">
<?php foreach ($pmBanners as $pmEvent): ?>
<?php
      $pmTitle = pmEventProse((string) ($pmEvent['title'] ?? ''));
      $pmImage = pmEventImageUrl($pmEvent);
      $pmStamp = pmEventDateBlock($pmEvent)['stamp'];
?>
      <article class="pm-card pm-card--flush pm-tile">
        <div class="pm-banner">
          <img src="<?php echo pmEsc($pmImage); ?>"
               alt="Promotional banner for <?php echo pmEsc($pmTitle); ?>"
               loading="lazy" decoding="async">
        </div>
        <div class="pm-cell">
<?php if ($pmStamp !== ''): ?>
          <span class="pm-label"><?php echo pmEsc($pmStamp); ?></span>
<?php endif; ?>
          <h3 class="pm-h4"><?php echo pmEsc($pmTitle); ?></h3>
          <div class="pm-card__foot">
            <div class="pm-btn-row">
              <?php // A plain link with the download attribute. Needs no
                    // script, so it is always present. ?>
              <a class="pm-btn--link" href="<?php echo pmEsc($pmImage); ?>" download>
                <?php echo pmContentSafe($pdo, 'events', 'banner_download', 'Download'); ?>
                <span class="pm-sr-only"> the banner for <?php echo pmEsc($pmTitle); ?></span>
              </a>
              <?php // Needs the clipboard API, so .pm-js-only keeps it out of
                    // the page entirely until head.php has confirmed scripts
                    // run. Never show a button that will do nothing. ?>
              <button class="pm-btn--link pm-js-only" type="button"
                      data-pm-copy="<?php echo pmEsc(PM_SITE_ORIGIN . $pmImage); ?>"
                      data-pm-copy-done="<?php echo pmContentSafe($pdo, 'events', 'banner_copied', 'Link copied'); ?>">
                <?php echo pmContentSafe($pdo, 'events', 'banner_copy', 'Copy link'); ?>
                <span class="pm-sr-only"> to the banner for <?php echo pmEsc($pmTitle); ?></span>
              </button>
            </div>
          </div>
        </div>
      </article>
<?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>

<?php pmPageEnd(); ?>
