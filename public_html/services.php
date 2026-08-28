<?php
/**
 * Services overview: the three pillars, side by side.
 *
 * New. "Services" was an anchor down the homepage on the live site. This page
 * answers one question, "what are the three things Prosperminds teaches", in a
 * form a delegate can compare across, and hands off to a detail page per pillar
 * for the narrative, the audience, the teaching format and the calendar.
 *
 * The pillar data is one json row, page_content services.pillars, shared with
 * the homepage and the About page. The URL each pillar maps to is a route
 * rather than content and lives in pmServiceHref(); see the comment there.
 *
 * The events grid shows EVERY active school, not a filtered subset, because
 * the three pillars between them cover the whole calendar. The per-pillar
 * filtering happens on the detail pages, where the question is narrower.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';

$events = pmActiveEvents($pdo);

$cardLabels = [
    'details'      => pmContent($pdo, 'global', 'event_details_label', 'Details'),
    'register'     => pmContent($pdo, 'global', 'event_register_label', 'Register'),
    'badge_lapsed' => pmContent($pdo, 'global', 'early_bird_lapsed_label', 'Standard rate'),
];

pmPageBegin([
    'slug'        => 'services',
    'nav'         => 'services',
    'title'       => pmContent($pdo, 'services', 'meta_title', 'Services | Prosperminds'),
    'description' => pmContent($pdo, 'services', 'meta_description', 'Three pillars of public finance capability: PFM, IPSAS and IFRS mastery; data analytics and AI automation; sustainability reporting.'),
    'canonical'   => '/services.php',
]);
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<section class="pm-section">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'services', 'hero_eyebrow',
      'Services'); ?></span>

    <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'services', 'hero_title',
      'Three pillars of public finance capability'); ?></h1>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'services', 'hero_body',
      'Every Prosperminds course sits inside one of three pillars. Each has its own curriculum, its own set of outcomes and its own place in the calendar. Open a pillar for the full outline.'); ?></p>

  </div>
</section>


<?php // ── The three pillars ─────────────────────────────────────────────── ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container">

    <h2 class="pm-sr-only">The three pillars</h2>

<?php
$outcomesTitle   = pmContent($pdo, 'services', 'outcomes_title', 'Why departments send teams');
$curriculumTitle = pmContent($pdo, 'services', 'curriculum_title', 'Curriculum coverage');
$pillarCta       = pmContent($pdo, 'services', 'pillar_cta_label', 'Open the full outline');
?>
    <div class="pm-grid pm-grid--ruled pm-grid--3">
<?php foreach (pmContentJson($pdo, 'services', 'pillars', pmPillarsDefault()) as $pillar): ?>
<?php   $pillarName = (string) ($pillar['name'] ?? ''); ?>
      <div class="pm-cell">
        <span class="pm-ordinal"><?php echo pmEsc((string) ($pillar['num'] ?? '')); ?></span>
        <h3 class="pm-h3 pm-h3--caps"><?php echo pmEsc($pillarName); ?></h3>

        <?php // The pillar's one-line promise. Sized as a sub-heading but kept
              // a <p>: it is a sentence, not a section title, and promoting it
              // to an h4 would add a level to the document outline that carries
              // no structure. Size comes from the class, the element from what
              // the content is. ?>
        <p class="pm-h4"><?php echo pmEsc((string) ($pillar['promise'] ?? '')); ?></p>
        <p class="pm-body"><?php echo pmEsc((string) ($pillar['intro'] ?? '')); ?></p>

<?php   $outcomes = $pillar['outcomes'] ?? []; ?>
<?php   if (is_array($outcomes) && $outcomes !== []): ?>
        <div>
          <span class="pm-label"><?php echo pmEsc($outcomesTitle); ?></span>
          <ul class="pm-list">
<?php     foreach ($outcomes as $outcome): ?>
            <li><?php echo pmEsc((string) $outcome); ?></li>
<?php     endforeach; ?>
          </ul>
        </div>
<?php   endif; ?>

<?php   $topics = $pillar['topics'] ?? []; ?>
<?php   if (is_array($topics) && $topics !== []): ?>
        <div>
          <span class="pm-label"><?php echo pmEsc($curriculumTitle); ?></span>
          <ul class="pm-list">
<?php     foreach ($topics as $topic): ?>
            <li><?php echo pmEsc((string) $topic); ?></li>
<?php     endforeach; ?>
          </ul>
        </div>
<?php   endif; ?>

        <a class="pm-btn pm-btn--secondary pm-cell__action" href="<?php echo pmEsc(pmServiceHref((string) ($pillar['key'] ?? ''))); ?>">
          <?php echo pmEsc($pillarCta); ?>
          <span class="pm-sr-only"> for <?php echo pmEsc($pillarName); ?></span>
        </a>
      </div>
<?php endforeach; ?>
    </div>

  </div>
</section>


<?php // ── The calendar ──────────────────────────────────────────────────── ?>
<section class="pm-section">
  <div class="pm-container">

    <div class="pm-section-head">
      <div>
        <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'services', 'events_eyebrow',
          'Calendar'); ?></span>
        <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'services', 'events_title',
          'Schools covering these pillars'); ?></h2>
      </div>
    </div>

    <?php pmRenderEventGrid(
        $events,
        $cardLabels,
        pmContent($pdo, 'services', 'events_empty',
            'Dates for the next intake are being confirmed. Contact the programme office and we will tell you first.'),
        'compact'
    ); ?>

  </div>
</section>


<?php // ── The one accent band on the page ───────────────────────────────── ?>
<section class="pm-section pm-section--accent">
  <div class="pm-container pm-band">

    <div class="pm-row__main pm-stack pm-stack--sm">
      <?php // .pm-label, not .pm-eyebrow: the eyebrow's marker is a green rule,
            // which is invisible on the green band. ?>
      <span class="pm-label"><?php echo pmContentSafe($pdo, 'services', 'cta_eyebrow',
        'Not sure which one'); ?></span>
      <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'services', 'cta_title',
        'Tell us what your department is being held to this year'); ?></h2>
      <p class="pm-body"><?php echo pmContentSafe($pdo, 'services', 'cta_body',
        'The programme office will point you at the right pillar, and will say plainly if none of them is the answer.'); ?></p>
    </div>

    <a class="pm-btn pm-btn--invert" href="/contact.php"><?php echo pmContentSafe($pdo, 'services', 'cta_label',
      'Request the full outline'); ?></a>

  </div>
</section>

<?php pmPageEnd(); ?>
