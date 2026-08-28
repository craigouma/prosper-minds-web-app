<?php
/**
 * Homepage, rebuilt on the Phase 1 design system.
 *
 * Replaces the 30 KB single-file homepage that carried its own inline CSS, its
 * own copy of the header and footer, and hardcoded event details. Section 4 of
 * the design brief names the core problem it had: it was "one long single-page
 * scroll pretending to be a multi-page site", with About, Services and Contact
 * as anchors down this page rather than real URLs. Those are now real pages and
 * this file is a homepage again.
 *
 * WHERE EVERYTHING ON THIS PAGE COMES FROM
 * ----------------------------------------
 *   Prose        page_content, slug 'home' (and 'global' for shared labels),
 *                through pmContentSafe() with a real inline default. Phase 5's
 *                CMS edits those rows; nothing readable here is written in PHP.
 *   Events       the `events` table, through pmActiveEvents(). No event title,
 *                date, city or price is written down anywhere in this file.
 *   The closing  computed every render by pmSoonestEarlyBird() from the three
 *   CTA band     early_bird_N_date columns, never seeded. The approved
 *                prototype's "Seats for the October cohort close on 12
 *                September" is an invented date; a wrong deadline on a page
 *                selling USD 599 seats is worse than no deadline. When every
 *                tier on every event has lapsed the band degrades to seeded,
 *                undated wording.
 *
 * The three pillars are read from the 'services' slug rather than copied under
 * 'home'. There is one definition of what the three pillars are, and the
 * homepage, the About page and the services overview all render it, so an
 * editor renaming a pillar renames it everywhere it appears.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';

$events = pmActiveEvents($pdo);

// Shared event-card labels, read raw because the card partial escapes them.
$cardLabels = [
    'details'      => pmContent($pdo, 'global', 'event_details_label', 'Details'),
    'register'     => pmContent($pdo, 'global', 'event_register_label', 'Register'),
    'badge_lapsed' => pmContent($pdo, 'global', 'early_bird_lapsed_label', 'Standard rate'),
];

// The closing band. Computed, not seeded: see the header comment.
$soonest = pmSoonestEarlyBird($events);

if ($soonest !== null) {
    $ctaTitle = pmEarlyBirdFill(
        pmContent($pdo, 'home', 'cta_title_template', 'Save {pct} per cent on the {city} school until {date}'),
        $soonest['early_bird'],
        $soonest['event']
    );
    $ctaBody = pmContent($pdo, 'home', 'cta_body', 'Early-bird pricing is tiered by registration date.');
    // Straight to the event whose deadline the band is about, because that is
    // the one thing the sentence just promised.
    $ctaHref = pmEventRegisterUrl($soonest['event']);
} else {
    $ctaTitle = pmContent($pdo, 'home', 'cta_title_lapsed', 'Registration is open for the 2026 residential schools');
    $ctaBody  = pmContent($pdo, 'home', 'cta_body_lapsed', 'Standard delegate rates apply. Cohorts are capped, so a place is worth confirming early.');
    // No single event is privileged once every deadline has passed, so the
    // delegate picks one first.
    $ctaHref = pmRegisterHref();
}

pmPageBegin([
    'slug'        => 'home',
    'nav'         => 'home',
    'title'       => pmContent($pdo, 'home', 'meta_title', 'Prosperminds | Public Finance, IPSAS, AI and Sustainability Training'),
    'description' => pmContent($pdo, 'home', 'meta_description', 'Prosperminds trains senior government finance officials across Africa in public finance management, IPSAS and IFRS reporting, data analytics, AI automation and sustainability disclosure.'),
    'canonical'   => '/index.php',
]);
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<section class="pm-section pm-relative pm-clip">
  <?php include __DIR__ . '/includes/layout/motif.php'; ?>
  <div class="pm-container pm-relative">

    <span class="pm-eyebrow pm-eyebrow--dash"><?php echo pmContentSafe($pdo, 'home', 'hero_eyebrow',
      'Executive PFM training, 2026 calendar'); ?></span>

    <h1 class="pm-display"><?php echo pmContentSafe($pdo, 'home', 'hero_title',
      'Strong systems start with strong people'); ?></h1>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'home', 'hero_body',
      'Prosperminds trains senior government finance officials across Africa in public finance management, IPSAS and IFRS reporting, data analytics, AI automation and sustainability disclosure. Five-day residential courses, delivered by practitioners.'); ?></p>

    <div class="pm-btn-row pm-mt-lg">
      <a class="pm-btn" href="#events"><?php echo pmContentSafe($pdo, 'home', 'hero_cta_primary',
        'View the 2026 calendar'); ?></a>
      <a class="pm-btn pm-btn--secondary" href="<?php echo pmEsc(pmRegisterHref()); ?>"><?php
        echo pmContentSafe($pdo, 'home', 'hero_cta_secondary', 'Register a delegate'); ?></a>
    </div>

    <?php // Real figures from the brief, held as one json row rather than eight
          // numbered keys, so adding a fifth is a content edit. ?>
    <div class="pm-grid pm-grid--ruled pm-grid--4 pm-mt-lg">
<?php foreach (pmContentJson($pdo, 'home', 'hero_facts', [
        ['value' => '25',  'label' => 'Years collective experience'],
        ['value' => '875', 'label' => 'Leaders trained'],
        ['value' => '4',   'label' => 'Schools in 2026'],
        ['value' => '5',   'label' => 'Day residential format'],
      ]) as $fact): ?>
      <div class="pm-cell">
        <span class="pm-stat__value"><?php echo pmEsc((string) ($fact['value'] ?? '')); ?></span>
        <span class="pm-stat__label"><?php echo pmEsc((string) ($fact['label'] ?? '')); ?></span>
      </div>
<?php endforeach; ?>
    </div>

  </div>
</section>


<?php // ── The 2026 calendar ─────────────────────────────────────────────── ?>
<section class="pm-section pm-section--ruled" id="events">
  <div class="pm-container">

    <div class="pm-section-head">
      <div>
        <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'home', 'events_eyebrow',
          'Upcoming courses'); ?></span>
        <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'home', 'events_title',
          'Four flagship events'); ?></h2>
      </div>
    </div>

    <?php pmRenderEventGrid(
        $events,
        $cardLabels,
        pmContent($pdo, 'home', 'events_empty',
            'Dates for the next intake are being confirmed. Contact the programme office and we will tell you first.'),
        'full'
    ); ?>

  </div>
</section>


<?php // ── The three pillars ─────────────────────────────────────────────── ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container">

    <div class="pm-section-head">
      <div>
        <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'home', 'pillars_eyebrow',
          'What we teach'); ?></span>
        <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'home', 'pillars_title',
          'Three pillars of public finance capability'); ?></h2>
      </div>
    </div>

    <?php $pillarCta = pmContent($pdo, 'home', 'pillars_cta_label', 'Read more'); ?>
    <div class="pm-grid pm-grid--ruled pm-grid--3 pm-mt-lg">
<?php foreach (pmContentJson($pdo, 'services', 'pillars', pmPillarsDefault()) as $pillar): ?>
      <div class="pm-cell">
        <span class="pm-ordinal"><?php echo pmEsc((string) ($pillar['num'] ?? '')); ?></span>
        <h3 class="pm-h3 pm-h3--caps"><?php echo pmEsc((string) ($pillar['name'] ?? '')); ?></h3>
        <p class="pm-body"><?php echo pmEsc((string) ($pillar['intro'] ?? '')); ?></p>
        <a class="pm-btn--link pm-cell__action" href="<?php echo pmEsc(pmServiceHref((string) ($pillar['key'] ?? ''))); ?>">
          <?php echo pmEsc($pillarCta); ?>
          <span class="pm-sr-only"> about <?php echo pmEsc((string) ($pillar['name'] ?? '')); ?></span>
        </a>
      </div>
<?php endforeach; ?>
    </div>

  </div>
</section>


<?php // ── Track record ──────────────────────────────────────────────────── ?>
<section class="pm-section">
  <div class="pm-container pm-row">

    <div class="pm-row__main">
      <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'home', 'record_eyebrow',
        'Track record'); ?></span>
      <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'home', 'record_title',
        'Twenty-five years in the room'); ?></h2>
      <p class="pm-body pm-mt-lg"><?php echo pmContentSafe($pdo, 'home', 'record_body',
        'Our faculty has spent a quarter of a century inside treasuries, audit offices and accountant-general departments. Every course is built from that work, then tested against the standards delegates are held to when they return.'); ?></p>
      <p class="pm-mt-lg">
        <a class="pm-btn pm-btn--secondary" href="/about.php"><?php echo pmContentSafe($pdo, 'home', 'record_cta_label',
          'About Prosperminds'); ?></a>
      </p>
    </div>

    <div class="pm-row__side">
      <?php // --4 is a minimum column width, not a column count: at the width
            // this side column gets it resolves to the prototype's 2x2 block. ?>
      <div class="pm-grid pm-grid--ruled pm-grid--4">
<?php foreach (pmContentJson($pdo, 'home', 'stats', [
          ['value' => '25',  'label' => 'Years collective experience'],
          ['value' => '875', 'label' => 'Leaders trained'],
          ['value' => '14',  'label' => 'Countries represented'],
          ['value' => '5',   'label' => 'Days per school'],
        ]) as $stat): ?>
        <div class="pm-cell">
          <span class="pm-stat__value"><?php echo pmEsc((string) ($stat['value'] ?? '')); ?></span>
          <span class="pm-stat__label"><?php echo pmEsc((string) ($stat['label'] ?? '')); ?></span>
        </div>
<?php endforeach; ?>
      </div>
    </div>

  </div>
</section>


<?php // ── Testimonials ──────────────────────────────────────────────────── ?>
<?php // Section 4.4 of the brief: the live site's carousel truncates quotes at
      // the viewport edge with no way to advance them, on the one section whose
      // job is credibility. There is no carousel here. Every quote is on the
      // page, in full, in a static grid that cannot break. ?>
<?php
// A real inline default, not [], per the content layer's contract: what a
// delegate sees if page_content is unreachable should be the same sentences the
// seeded row holds, not an empty credibility section.
$pmTestimonials = pmContentJson($pdo, 'home', 'testimonials', [
    [
        'quote' => 'The reconciliation workflow we built during the automation module cut our monthly reporting time by nine days. It is still running two years later.',
        'role'  => 'Chief Accountant',
        'org'   => 'Ministry of Finance, Kenya',
    ],
    [
        'quote' => 'We arrived with three unresolved audit queries on asset recognition. We left with a documented position on all three and the working papers to support it.',
        'role'  => 'Auditor General office',
        'org'   => 'Ghana',
    ],
    [
        'quote' => 'The faculty had done the job. That mattered. Nobody was explaining accrual accounting to us from a textbook.',
        'role'  => 'Treasury Leader',
        'org'   => 'Federal Ministry of Finance, Nigeria',
    ],
    [
        'quote' => 'Our budget monitoring pack went from a backward-looking report to something the cabinet secretary reads before decisions. That change started in Bali.',
        'role'  => 'Strategy Director',
        'org'   => 'Ministry of Finance, Rwanda',
    ],
]);
?>
<?php if ($pmTestimonials !== []): ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container">

    <div class="pm-section-head">
      <div>
        <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'home', 'testimonials_eyebrow',
          'Delegate feedback'); ?></span>
        <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'home', 'testimonials_title',
          'In their words'); ?></h2>
      </div>
    </div>

    <?php // --2 (a 320px minimum) rather than --3: a quote needs a readable
          // measure, and at 1180px this gives the prototype's three across with
          // the fourth wrapping, instead of four narrow columns. ?>
    <div class="pm-grid pm-grid--2 pm-mt-lg">
<?php foreach ($pmTestimonials as $quote): ?>
      <figure class="pm-card">
        <span class="pm-quote__mark" aria-hidden="true">&ldquo;</span>
        <blockquote class="pm-quote__text"><?php echo pmEsc((string) ($quote['quote'] ?? '')); ?></blockquote>
        <figcaption class="pm-card__foot">
          <span class="pm-label"><?php echo pmEsc((string) ($quote['role'] ?? '')); ?></span>
          <p class="pm-caption pm-mt-sm"><?php echo pmEsc((string) ($quote['org'] ?? '')); ?></p>
        </figcaption>
      </figure>
<?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>


<?php // ── The one accent band on the page ───────────────────────────────── ?>
<section class="pm-section pm-section--accent">
  <div class="pm-container pm-band">

    <div class="pm-row__main pm-stack pm-stack--sm">
      <?php // .pm-label, not .pm-eyebrow: the eyebrow's marker is a green rule,
            // which is invisible on the green band. Same wording, visible mark. ?>
      <span class="pm-label"><?php echo pmContentSafe($pdo, 'home', 'cta_eyebrow', 'Registration'); ?></span>
      <h2 class="pm-h2"><?php echo pmEsc($ctaTitle); ?></h2>
      <p class="pm-body"><?php echo pmEsc($ctaBody); ?></p>
    </div>

    <a class="pm-btn pm-btn--invert" href="<?php echo pmEsc($ctaHref); ?>"><?php
      echo pmContentSafe($pdo, 'home', 'cta_label', 'Start registration'); ?></a>

  </div>
</section>

<?php pmPageEnd(); ?>
