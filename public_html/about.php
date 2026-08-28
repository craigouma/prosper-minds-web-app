<?php
/**
 * About page.
 *
 * New. Until now "About" was an anchor down the homepage, which Section 4.1 of
 * the design brief names as the core problem with the live site: nav links that
 * pretend to be pages. This is a real URL with its own title, description and
 * canonical, and pmNavItems() already points at it.
 *
 * Every visible string comes from page_content slug 'about', except the three
 * pillars, which come from slug 'services'. There is one definition of what the
 * three pillars are and three pages render it (here, the homepage, and the
 * services overview), so an editor renaming a pillar renames it everywhere
 * rather than in one place out of three.
 *
 * No closing accent band on this page, deliberately. The approved prototype's
 * About screen ends on the pillars and hands over to the footer, and the design
 * system allows at most one green band per page rather than requiring one. The
 * next step from here is a service page, which the pillar links already are.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';

pmPageBegin([
    'slug'        => 'about',
    'nav'         => 'about',
    'title'       => pmContent($pdo, 'about', 'meta_title', 'About Prosperminds'),
    'description' => pmContent($pdo, 'about', 'meta_description', 'A training institution for the public sector, working with ministries of finance, audit offices, revenue authorities and state corporations across Africa.'),
    'canonical'   => '/about.php',
]);
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<section class="pm-section">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'about', 'hero_eyebrow',
      'About Prosperminds'); ?></span>

    <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'about', 'hero_title',
      'Protecting and growing the mind to achieve prosperity'); ?></h1>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'about', 'hero_body',
      'Prosperminds is a training institution for the public sector. We work with ministries of finance, audit offices, revenue authorities and state corporations across Africa, and increasingly with international delegations attending our residential schools.'); ?></p>

  </div>
</section>


<?php // ── How we work, and what a delegate leaves with ──────────────────── ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container pm-row">

    <div class="pm-row__main">
      <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'about', 'work_title',
        'How we work'); ?></h2>

      <p class="pm-body pm-mt-lg"><?php echo pmContentSafe($pdo, 'about', 'work_body_1',
        'Our faculty is drawn from practice. Between them they carry twenty-five years of collective experience inside treasuries, accountant-general departments and supreme audit institutions. Courses are written from that work rather than from a syllabus, then revised each year against the standards delegates are actually held to.'); ?></p>

      <p class="pm-body pm-mt-md"><?php echo pmContentSafe($pdo, 'about', 'work_body_2',
        'Every school runs for five days in a residential format. Day one establishes leadership context, days two to four go deep on technical material, and day five is spent building the action plan each delegate takes back to their department. Cohorts are capped so that faculty remain reachable throughout.'); ?></p>

      <p class="pm-body pm-mt-md"><?php echo pmContentSafe($pdo, 'about', 'work_body_3',
        'Eight hundred and seventy-five leaders have completed a Prosperminds school. Many return with colleagues, and a growing number return as contributors.'); ?></p>
    </div>

    <div class="pm-row__side">
      <span class="pm-label"><?php echo pmContentSafe($pdo, 'about', 'outcomes_title',
        'What delegates leave with'); ?></span>
      <ul class="pm-list">
<?php foreach (pmContentJson($pdo, 'about', 'outcomes', [
        'A departmental action plan reviewed by faculty',
        'CPD certification recognised by professional bodies',
        'Working templates, not slide decks',
        'A peer network across finance functions in the region',
      ]) as $outcome): ?>
        <li><?php echo pmEsc((string) $outcome); ?></li>
<?php endforeach; ?>
      </ul>
    </div>

  </div>
</section>


<?php // ── The numbers ───────────────────────────────────────────────────── ?>
<?php // Section 5 of the brief rules out invented social proof. These are the
      // client's own figures, carried over from the live site unchanged. ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">
    <h2 class="pm-sr-only">Prosperminds in numbers</h2>
    <div class="pm-grid pm-grid--ruled pm-grid--4">
<?php foreach (pmContentJson($pdo, 'about', 'stats', [
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
</section>


<?php // ── The three pillars in depth ────────────────────────────────────── ?>
<section class="pm-section">
  <div class="pm-container">

    <div class="pm-section-head">
      <div>
        <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'about', 'pillars_eyebrow',
          'Practice areas'); ?></span>
        <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'about', 'pillars_title',
          'The three pillars in depth'); ?></h2>
      </div>
    </div>

    <?php $pillarCta = pmContent($pdo, 'about', 'pillars_cta_label', 'Open service page'); ?>
    <?php // A single-column ruled grid: .pm-grid--ruled with no column modifier
          // is one column whose 1px gap is the hairline between rows, which is
          // the printed table-of-contents treatment the prototype uses here. ?>
    <ul class="pm-grid pm-grid--ruled pm-mt-lg">
<?php foreach (pmContentJson($pdo, 'services', 'pillars', pmPillarsDefault()) as $pillar): ?>
      <li class="pm-cell">
        <div class="pm-row">
          <span class="pm-ordinal"><?php echo pmEsc((string) ($pillar['num'] ?? '')); ?></span>
          <div class="pm-row__main">
            <h3 class="pm-h3 pm-h3--caps"><?php echo pmEsc((string) ($pillar['name'] ?? '')); ?></h3>
            <p class="pm-body pm-mt-sm"><?php echo pmEsc((string) ($pillar['intro'] ?? '')); ?></p>
            <p class="pm-mt-md">
              <a class="pm-btn--link" href="<?php echo pmEsc(pmServiceHref((string) ($pillar['key'] ?? ''))); ?>">
                <?php echo pmEsc($pillarCta); ?>
                <span class="pm-sr-only"> for <?php echo pmEsc((string) ($pillar['name'] ?? '')); ?></span>
              </a>
            </p>
          </div>
        </div>
      </li>
<?php endforeach; ?>
    </ul>

  </div>
</section>

<?php pmPageEnd(); ?>
