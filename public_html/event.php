<?php
require_once __DIR__ . '/includes/layout/page.php';

// Both addresses render the same page. Deliberately not a 301 from ?id= to the
// slug: the old link is not being deprecated, only a nicer one is being added
// alongside it, so anything still holding an ?id= link (an old email, a
// bookmark, the admin's own audit log) keeps working exactly as it does today.
// The slug is still the one search engines are told to prefer -- see
// 'canonical' below.
$pmSlugParam = trim((string) ($_GET['slug'] ?? ''));
$pmEvent     = $pmSlugParam !== ''
    ? pmEventBySlug($pdo, $pmSlugParam)
    : pmEventById($pdo, (int) ($_GET['id'] ?? 0));

if ($pmEvent === null || !pmEventIsListable($pmEvent)) {
    http_response_code(404);

    pmPageBegin([
        'slug'        => 'event',
        'nav'         => 'schools',
        'title'       => pmContent($pdo, 'event', 'missing_title', 'Course not found'),
        'description' => pmContent($pdo, 'event', 'missing_description', 'This course is not in the Prosperminds calendar. The full calendar lists every scheduled school and every past cohort.'),
        // No canonical: this URL is not a page anyone should be sent back to.
        'noindex'     => true,
    ]);
    ?>
    <section class="pm-section pm-404">
      <div class="pm-container">
        <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'event', 'missing_eyebrow',
          'Not found'); ?></span>
        <h1 class="pm-h1 pm-mt-sm"><?php echo pmContentSafe($pdo, 'event', 'missing_heading',
          'That school is not in the calendar'); ?></h1>
        <a class="pm-btn" href="/#schools"><?php echo pmContentSafe($pdo, 'event', 'v2_missing_cta',
          'See the schools'); ?></a>
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
    'nav'         => 'schools',
    // The course title IS the page title. It is the only honest one, and it is
    // what a delegate pasted a link to expects to see in their tab.
    'title'       => $pmTitle,
    'description' => $pmTagline !== '' ? $pmTagline : $pmTitle . '. ' . $pmDates . ', ' . $pmLocation . '.',
    'canonical'   => pmEventDetailUrl($pmEvent),
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

<?php
$pmDayCount = count($pmAgenda);
$pmFormat   = $pmDayCount > 0 ? ucfirst(pmNumberWord($pmDayCount)) . ' days, residential' : 'Residential';
$pmFrom     = pmEventFromPrice($pmEvent);

$pmFacts = array_values(array_filter([
    ['Venue', $pmLocation],
    ['Dates', $pmDates],
    ['Format', $pmFormat],
    ['Certificate', 'CPD certificate'],
], static function (array $fact): bool {
    return trim($fact[1]) !== '';
}));

$pmImageSize = $pmImage !== '' ? pmEventImageSize((string) ($pmEvent['image_path'] ?? '')) : null;
?>

<div class="pm-container pm-school-page">

  <div class="pm-school-page__poster">
<?php if ($pmImage !== ''): ?>
    <div class="pm-poster">
      <img src="<?php echo pmEsc($pmImage); ?>" alt="Poster for <?php echo pmEsc($pmTitle); ?>"
<?php if ($pmImageSize !== null): ?>
           width="<?php echo $pmImageSize[0]; ?>" height="<?php echo $pmImageSize[1]; ?>"
<?php endif; ?>
           decoding="async">
    </div>
<?php endif; ?>
  </div>

  <div class="pm-school-page__body">

    <div>
      <a class="pm-back" href="/#schools"><?php echo pmContentSafe($pdo, 'event', 'v2_back_label', 'All schools'); ?></a>
      <h1 class="pm-h1 pm-h1--long pm-mt-sm"><?php echo pmEsc($pmTitle); ?></h1>

      <dl class="pm-facts">
<?php foreach ($pmFacts as $pmFact): ?>
        <dt><?php echo pmEsc($pmFact[0]); ?></dt>
        <dd><?php echo pmEsc($pmFact[1]); ?></dd>
<?php endforeach; ?>
      </dl>

<?php if ($pmIsPast): ?>
      <p class="pm-caption pm-mt-md"><?php echo pmContentSafe($pdo, 'event', 'fact_status_past',
        'This cohort has already run'); ?></p>
<?php elseif ($pmEarlyBird !== null): ?>
      <p class="pm-live pm-live--top pm-mt-md"><span><?php echo pmEsc(
        str_replace(
          ['{pct}', '{date}'],
          [(string) $pmEarlyBird['pct'], $pmEarlyBird['date_display']],
          pmContent($pdo, 'event', 'v2_early_bird_line', '{pct}% early-bird discount until {date}.')
        )
      ); ?></span></p>
<?php else: ?>
      <p class="pm-caption pm-mt-md"><?php echo pmContentSafe($pdo, 'event', 'v2_standard_rate',
        'Standard rate. The early-bird rate has closed.'); ?></p>
<?php endif; ?>

<?php if (!$pmIsPast): ?>
      <div class="pm-desktop-only">
        <a class="pm-btn" href="<?php echo pmEsc($pmRegisterUrl); ?>"><?php
          echo $pmFrom !== '' ? 'Register from ' . pmEsc($pmFrom) : 'Register'; ?></a>
      </div>
<?php endif; ?>
    </div>


<?php if ($pmTiers !== []): ?>
    <section>
      <h2 class="pm-h2 pm-h2--md"><?php echo pmContentSafe($pdo, 'event', 'v2_pricing_title', 'Price per delegate'); ?></h2>
<?php foreach ($pmTiers as $pmTier): ?>
      <div class="pm-tier">
        <div class="pm-tier__head">
          <span class="pm-tier__name"><?php echo pmEsc($pmTier['name']); ?></span>
          <span class="pm-tier__price"><?php echo pmEsc($pmTier['price']); ?></span>
        </div>
<?php if ($pmTier['perks'] !== []): ?>
        <ul class="pm-list">
<?php   foreach ($pmTier['perks'] as $pmPerk): ?>
          <li><?php echo pmEsc($pmPerk); ?></li>
<?php   endforeach; ?>
        </ul>
<?php endif; ?>
<?php if ($pmTier['note'] !== ''): ?>
        <span class="pm-tier__note"><?php echo pmEsc($pmTier['note']); ?></span>
<?php endif; ?>
<?php if (!$pmIsPast): ?>
        <div>
          <a class="pm-btn pm-btn--secondary pm-btn--sm"
             href="<?php echo pmEsc($pmRegisterUrl . (str_contains($pmRegisterUrl, '?') ? '&' : '?') . 'tier=' . $pmTier['key']); ?>">Register as <?php
            echo pmEsc($pmTier['name']); ?></a>
        </div>
<?php endif; ?>
      </div>
<?php endforeach; ?>
    </section>
<?php endif; ?>


<?php if ($pmOutcomes !== []): ?>
    <section>
      <h2 class="pm-h2 pm-h2--md"><?php echo pmContentSafe($pdo, 'event', 'outcomes_title', 'What you leave with'); ?></h2>
      <ol class="pm-ordered pm-mt-md">
<?php foreach ($pmOutcomes as $pmI => $pmOutcome): ?>
        <li><span><?php echo str_pad((string) ($pmI + 1), 2, '0', STR_PAD_LEFT); ?></span><span><?php echo pmEsc($pmOutcome); ?></span></li>
<?php endforeach; ?>
      </ol>
    </section>
<?php endif; ?>


<?php if ($pmAudience !== []): ?>
    <section>
      <h2 class="pm-h2 pm-h2--md"><?php echo pmContentSafe($pdo, 'event', 'audience_title', 'Who it is for'); ?></h2>
      <ul class="pm-list pm-measure pm-mt-md">
<?php foreach ($pmAudience as $pmRole): ?>
        <li><?php echo pmEsc($pmRole); ?></li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endif; ?>


<?php if ($pmAgenda !== []): ?>
    <details class="pm-disclosure">
      <summary><?php echo pmEsc(str_replace('{n}', pmNumberWord(count($pmAgenda)),
        pmContent($pdo, 'event', 'v2_agenda_title', 'The {n} days'))); ?></summary>
      <ol class="pm-days">
<?php foreach ($pmAgenda as $pmDay): ?>
        <li>
          <span><?php echo pmEsc(str_replace('{n}', $pmDay['day'], pmContent($pdo, 'event', 'agenda_day_label', 'Day {n}'))); ?></span>
          <span>
            <?php echo pmEsc($pmDay['title']); ?>
<?php   $pmTopics = array_filter(array_map('trim', explode(';', (string) $pmDay['desc']))); ?>
<?php   if ($pmTopics !== []): ?>
            <ul class="pm-list">
<?php     foreach ($pmTopics as $pmTopic): ?>
              <li><?php echo pmEsc($pmTopic); ?></li>
<?php     endforeach; ?>
            </ul>
<?php   endif; ?>
          </span>
        </li>
<?php endforeach; ?>
      </ol>
    </details>
<?php endif; ?>

  </div>
</div>

<?php if (!$pmIsPast): ?>
<div class="pm-stickybar pm-stickybar--mobile">
<?php if ($pmFrom !== ''): ?>
  <div class="pm-stickybar__fig"><span>From</span><strong><?php echo pmEsc($pmFrom); ?></strong></div>
<?php endif; ?>
  <a class="pm-btn" href="<?php echo pmEsc($pmRegisterUrl); ?>">Register</a>
</div>
<?php endif; ?>

<?php pmPageEnd(); ?>
