<?php
require_once __DIR__ . '/includes/layout/page.php';

$pmEvent = pmEventById($pdo, (int) ($_GET['id'] ?? 0));

if ($pmEvent === null || !pmEventIsListable($pmEvent)) {
    http_response_code(404);

    pmPageBegin([
        'slug'        => 'event',
        'nav'         => 'events',
        'title'       => pmContent($pdo, 'event', 'missing_title', 'Course not found'),
        'description' => pmContent($pdo, 'event', 'missing_description', 'This course is not in the Prosperminds calendar. The full calendar lists every scheduled school and every past cohort.'),
        // No canonical: this URL is not a page anyone should be sent back to.
        'noindex'     => true,
    ]);
    ?>
    <section class="pm-section">
      <div class="pm-container">
        <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'event', 'missing_eyebrow',
          'Not found'); ?></span>
        <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'event', 'missing_heading',
          'That course is not in the calendar'); ?></h1>
        <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'event', 'missing_body',
          'The link may be old, or the course may not have been published yet. The calendar lists every scheduled school, and every cohort that has already run.'); ?></p>
        <p class="pm-mt-lg">
          <a class="pm-btn" href="/events.php"><?php echo pmContentSafe($pdo, 'event', 'missing_cta',
            'Open the calendar'); ?></a>
        </p>
      </div>
    </section>
    <?php
    pmPageEnd();
    exit;
}

// ── The row, unpacked once ──────────────────────────────────────────────────
$pmTitle     = pmEventProse((string) ($pmEvent['title'] ?? ''));
$pmTagline   = pmEventProse((string) ($pmEvent['tagline'] ?? ''));
$pmLocation  = pmEventProse((string) ($pmEvent['location'] ?? ''));
$pmDates     = pmEventDatesLong($pmEvent);
$pmImage     = pmEventImageUrl($pmEvent);
$pmAgenda    = pmEventAgenda($pmEvent);
$pmAudience  = pmEventLines($pmEvent['audience'] ?? '');
$pmOutcomes  = pmEventLines($pmEvent['master_points'] ?? '');
$pmWhy       = pmEventLines($pmEvent['why_intro'] ?? '');
$pmIsPast    = pmEventIsPast($pmEvent);
$pmEarlyBird = $pmIsPast ? null : pmEventNextEarlyBird($pmEvent);

// The three tiers, cheapest first, which is the order the approved prototype
// shows and the order a delegate reads. A tier with no price and no perks is
// omitted rather than rendered as an empty card.
$pmTiers = [];
foreach ([
    ['key' => 'regular', 'name' => 'Regular', 'price' => 'regular_price', 'perks' => 'regular_perks', 'note' => ''],
    ['key' => 'vip',     'name' => 'VIP',     'price' => 'vip_price',     'perks' => 'vip_perks',     'note' => ''],
    ['key' => 'vvip',    'name' => 'VVIP',    'price' => 'vvip_price',    'perks' => 'vvip_perks',    'note' => 'vvip_seats_note'],
] as $pmSpec) {
    $pmPrice = pmEventProse((string) ($pmEvent[$pmSpec['price']] ?? ''));
    $pmPerks = pmEventLines($pmEvent[$pmSpec['perks']] ?? '');

    if (trim($pmPrice) === '' && $pmPerks === []) {
        continue;
    }

    $pmTiers[] = [
        'key'   => $pmSpec['key'],
        'name'  => $pmSpec['name'],
        'price' => $pmPrice,
        'perks' => $pmPerks,
        'note'  => $pmSpec['note'] === '' ? '' : pmEventProse((string) ($pmEvent[$pmSpec['note']] ?? '')),
    ];
}

$pmRegisterUrl = pmEventRegisterUrl($pmEvent);

pmPageBegin([
    'slug'        => 'event',
    'nav'         => 'events',
    // The course title IS the page title. It is the only honest one, and it is
    // what a delegate pasted a link to expects to see in their tab.
    'title'       => $pmTitle,
    'description' => $pmTagline !== '' ? $pmTagline : $pmTitle . '. ' . $pmDates . ', ' . $pmLocation . '.',
    'canonical'   => '/event.php?id=' . (int) $pmEvent['id'],
    // The school's own designed banner is the right social card for it. Falls
    // back to the brand mark through pmPageConfig() when the row has no image.
    'og_image'    => $pmImage !== '' ? $pmImage : PM_SOCIAL_IMAGE,
]);
?>
<?php
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/invoice.php';

// Emitted in the body rather than the head deliberately: pmPageBegin has
// already written the head by this point, and search engines read JSON-LD
// anywhere in the document.
try {
    $pmOrigin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'prosper-minds.com');
    echo pmEventSchema($pmEvent, $pmOrigin);
} catch (Throwable $pmSchemaError) {
    error_log('event schema failed: ' . $pmSchemaError->getMessage());
}
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<section class="pm-section pm-relative pm-clip">
  <?php include __DIR__ . '/includes/layout/motif.php'; ?>
  <div class="pm-container pm-relative">

    <p><a class="pm-btn--link" href="/events.php"><?php echo pmContentSafe($pdo, 'event', 'back_label',
      'Back to calendar'); ?></a></p>

    <?php // "5 day residential school", counted from the agenda rather than
          // asserted, so a school with a four day agenda does not claim five.
          // A row with no agenda falls back to the undated wording rather than
          // printing "0 day". ?>
    <?php $pmDayCount = count($pmAgenda); ?>
    <span class="pm-eyebrow pm-mt-lg"><?php
      echo pmEsc($pmDayCount === 0
        ? pmContent($pdo, 'event', 'hero_eyebrow', 'Residential school')
        : str_replace('{n}', (string) $pmDayCount,
            pmContent($pdo, 'event', 'hero_eyebrow_template', '{n} day residential school'))); ?></span>

    <h1 class="pm-h1"><?php echo pmEsc($pmTitle); ?></h1>

<?php if ($pmTagline !== ''): ?>
    <p class="pm-lede pm-mt-lg"><?php echo pmEsc($pmTagline); ?></p>
<?php endif; ?>

    <?php // ── The fact strip ───────────────────────────────────────────── ?>
    <div class="pm-grid pm-grid--ruled pm-grid--4 pm-mt-lg">

<?php if ($pmLocation !== ''): ?>
      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'event', 'fact_location', 'Location'); ?></span>
        <span class="pm-body"><?php echo pmEsc($pmLocation); ?></span>
      </div>
<?php endif; ?>

<?php if ($pmDates !== ''): ?>
      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'event', 'fact_dates', 'Dates'); ?></span>
        <span class="pm-body"><?php echo pmEsc($pmDates); ?></span>
      </div>
<?php endif; ?>

<?php $pmFromPrice = pmEventProse((string) ($pmEvent['regular_price'] ?? $pmEvent['price'] ?? '')); ?>
<?php if (trim($pmFromPrice) !== ''): ?>
      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'event', 'fact_from', 'From'); ?></span>
        <span class="pm-body"><?php echo pmEsc($pmFromPrice); ?></span>
        <span class="pm-caption"><?php echo pmContentSafe($pdo, 'event', 'fact_from_note', 'per delegate'); ?></span>
      </div>
<?php endif; ?>

      <?php // The one green cell on the page, and only while there is a live
            // offer to put in it. A lapsed or finished school gets the same
            // cell in plain white, because a green panel saying "standard rate"
            // would be an offer that does not exist. ?>
<?php if ($pmIsPast): ?>
      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'event', 'fact_status', 'Status'); ?></span>
        <span class="pm-body"><?php echo pmContentSafe($pdo, 'event', 'fact_status_past',
          'This cohort has already run'); ?></span>
      </div>
<?php elseif ($pmEarlyBird !== null): ?>
      <div class="pm-cell pm-cell--accent">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'event', 'fact_early_bird', 'Early bird'); ?></span>
        <span class="pm-body"><?php echo pmEsc(
          str_replace(
            ['{pct}', '{date}'],
            [(string) $pmEarlyBird['pct'], $pmEarlyBird['date_display']],
            pmContent($pdo, 'event', 'fact_early_bird_value', '{pct} per cent until {date}')
          )
        ); ?></span>
      </div>
<?php else: ?>
      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'event', 'fact_rate', 'Rate'); ?></span>
        <span class="pm-body"><?php echo pmContentSafe($pdo, 'global', 'early_bird_lapsed_label',
          'Standard rate'); ?></span>
      </div>
<?php endif; ?>

    </div>

    <div class="pm-btn-row pm-mt-lg">
<?php if ($pmIsPast): ?>
      <?php // No registration link on a school that has already run. A live
            // "Register" button pointing at a finished cohort is the kind of
            // thing that produces a paid registration nobody can honour. ?>
      <a class="pm-btn" href="/events.php"><?php echo pmContentSafe($pdo, 'event', 'past_cta',
        'See the current calendar'); ?></a>
<?php else: ?>
      <a class="pm-btn" href="<?php echo pmEsc($pmRegisterUrl); ?>"><?php
        echo pmContentSafe($pdo, 'event', 'register_cta', 'Register for this school'); ?></a>
<?php endif; ?>
      <a class="pm-btn pm-btn--secondary" href="/contact.php"><?php
        echo pmContentSafe($pdo, 'event', 'quote_cta', 'Request a group quote'); ?></a>
    </div>

  </div>
</section>


<?php // ── The course, and who it is for ─────────────────────────────────── ?>
<?php if ($pmWhy !== [] || $pmAudience !== []): ?>
<section class="pm-section pm-section--ruled">
  <div class="pm-container pm-row">

<?php if ($pmWhy !== []): ?>
    <div class="pm-row__main">
      <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'event', 'course_title', 'The course'); ?></span>
      <?php // why_intro is stored as paragraphs separated by blank lines. Each
            // becomes its own <p> rather than one block with <br>s in it. ?>
<?php foreach ($pmWhy as $pmIndex => $pmParagraph): ?>
      <p class="pm-body pm-mt-md"><?php echo pmEsc($pmParagraph); ?></p>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($pmAudience !== []): ?>
    <div class="pm-row__side">
      <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'event', 'audience_title', 'Who it is for'); ?></span>
      <ul class="pm-list">
<?php foreach ($pmAudience as $pmRole): ?>
        <li><?php echo pmEsc($pmRole); ?></li>
<?php endforeach; ?>
      </ul>
    </div>
<?php endif; ?>

  </div>
</section>
<?php endif; ?>


<?php // ── What a delegate leaves with ───────────────────────────────────── ?>
<?php if ($pmOutcomes !== []): ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">
    <h2 class="pm-h3 pm-h3--caps"><?php echo pmContentSafe($pdo, 'event', 'outcomes_title',
      'What you leave with'); ?></h2>
    <ul class="pm-list pm-measure">
<?php foreach ($pmOutcomes as $pmOutcome): ?>
      <li><?php echo pmEsc($pmOutcome); ?></li>
<?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>


<?php // ── The agenda ────────────────────────────────────────────────────── ?>
<?php // Omitted entirely when the agenda column is empty or malformed. A
      // malformed row costs this section, not the page. ?>
<?php if ($pmAgenda !== []): ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'event', 'agenda_eyebrow', 'Agenda'); ?></span>

    <h2 class="pm-h2"><?php echo pmEsc(
      str_replace('{n}', (string) count($pmAgenda),
        pmContent($pdo, 'event', 'agenda_title', '{n} days, one arc'))); ?></h2>

    <ul class="pm-grid pm-grid--ruled pm-mt-lg">
<?php foreach ($pmAgenda as $pmIndex => $pmDay): ?>
      <li class="pm-cell">
        <div class="pm-row">
          <div class="pm-row__index">
            <span class="pm-h3"><?php echo pmEsc(str_pad((string) ($pmIndex + 1), 2, '0', STR_PAD_LEFT)); ?></span>
            <span class="pm-label pm-muted"><?php echo pmEsc(
              str_replace('{n}', $pmDay['day'], pmContent($pdo, 'event', 'agenda_day_label', 'Day {n}'))); ?></span>
          </div>
          <div class="pm-row__main">
<?php if ($pmDay['title'] !== ''): ?>
            <h3 class="pm-h4"><?php echo pmEsc($pmDay['title']); ?></h3>
<?php endif; ?>
<?php if ($pmDay['desc'] !== ''): ?>
            <?php // The desc column is a semicolon-separated set of topics, so
                  // it is printed as the list it already is rather than as one
                  // long sentence a delegate has to parse. ?>
            <ul class="pm-list">
<?php foreach (array_filter(array_map('trim', explode(';', $pmDay['desc']))) as $pmTopic): ?>
              <li><?php echo pmEsc($pmTopic); ?></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
          </div>
        </div>
      </li>
<?php endforeach; ?>
    </ul>

  </div>
</section>
<?php endif; ?>


<?php // ── Pricing ───────────────────────────────────────────────────────── ?>
<?php if ($pmTiers !== []): ?>
<section class="pm-section">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'event', 'pricing_eyebrow', 'Pricing'); ?></span>

    <h2 class="pm-h2"><?php echo pmEsc(
      str_replace('{n}', (string) count($pmTiers),
        pmContent($pdo, 'event', 'pricing_title', 'Three delegate tiers'))); ?></h2>

    <div class="pm-grid pm-grid--3 pm-mt-lg">
<?php foreach ($pmTiers as $pmTier): ?>
      <article class="pm-card">
        <div class="pm-stack pm-stack--xs">
          <span class="pm-label"><?php echo pmEsc($pmTier['name']); ?></span>
          <span class="pm-price"><?php echo pmEsc($pmTier['price']); ?></span>
          <span class="pm-price__note"><?php echo pmContentSafe($pdo, 'event', 'tier_per_delegate',
            'per delegate'); ?></span>
        </div>
<?php if ($pmTier['note'] !== ''): ?>
        <p class="pm-caption"><?php echo pmEsc($pmTier['note']); ?></p>
<?php endif; ?>
<?php if ($pmTier['perks'] !== []): ?>
        <ul class="pm-list">
<?php foreach ($pmTier['perks'] as $pmPerk): ?>
          <li><?php echo pmEsc($pmPerk); ?></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
<?php if (!$pmIsPast): ?>
        <div class="pm-card__foot">
          <?php // Every tier links to the same registration entry point,
                // because that is the only parameter event-registration.php
                // reads. It takes an id and nothing else, so a &tier= appended
                // here would look like it carried the choice and would in fact
                // be discarded on arrival. The tier is chosen inside the flow.
                // Phase 4 rebuilds that flow and can carry it properly. ?>
          <a class="pm-btn pm-btn--secondary pm-btn--block pm-btn--sm" href="<?php echo pmEsc($pmRegisterUrl); ?>">
            <?php echo pmContentSafe($pdo, 'event', 'tier_cta', 'Select'); ?>
            <span class="pm-sr-only"> the <?php echo pmEsc($pmTier['name']); ?> tier and register</span>
          </a>
        </div>
<?php endif; ?>
      </article>
<?php endforeach; ?>
    </div>

    <p class="pm-caption pm-mt-lg pm-measure"><?php echo pmContentSafe($pdo, 'event', 'pricing_note',
      'Early bird discounts apply by registration date and are calculated when you register. Group registrations of four or more delegates are invoiced together.'); ?></p>

  </div>
</section>
<?php endif; ?>


<?php // ── Closing band ──────────────────────────────────────────────────── ?>
<?php // The one dark band on the page, and the early bird line inside it is
      // computed, never seeded. Suppressed entirely for a past cohort: there is
      // nothing to close. ?>
<?php if (!$pmIsPast): ?>
<section class="pm-section pm-section--dark pm-section--tight">
  <div class="pm-container pm-band">
    <div class="pm-row__main">
      <h2 class="pm-h2"><?php echo pmEsc($pmEarlyBird === null
        ? pmContent($pdo, 'event', 'cta_title_lapsed', 'Seats are open at the standard rate')
        : pmEarlyBirdFill(
            pmContent($pdo, 'event', 'cta_title_template', 'Save {pct} per cent until {date}'),
            $pmEarlyBird,
            $pmEvent
          )); ?></h2>
      <p class="pm-body pm-mt-sm"><?php echo pmContentSafe($pdo, 'event', 'cta_body',
        'Registration takes about four minutes and an invoice is issued immediately.'); ?></p>
    </div>
    <div class="pm-section-head__action">
      <a class="pm-btn" href="<?php echo pmEsc($pmRegisterUrl); ?>"><?php
        echo pmContentSafe($pdo, 'event', 'cta_label', 'Register a delegate'); ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php pmPageEnd(); ?>
