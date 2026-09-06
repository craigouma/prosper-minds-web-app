<?php
/**
 * The body of a service detail page.
 *
 * WHY THIS IS SHARED
 * ------------------
 * service-pfm.php, service-data.php and service-sustainability.php are the same
 * page with different content. Every string on them already lives in
 * page_content under its own slug, so three copies of the markup would differ
 * only in the slug they pass, and would drift the first time one of them gained
 * a section. The three page files stay real files at their real URLs, each with
 * its own title, description, canonical and inline defaults; only the shape is
 * shared.
 *
 * Structure, from the approved prototype's services screen:
 *   hero            eyebrow, title, the pillar's promise, its one-line intro
 *   pillar switch   the three pillars as sibling links, current one marked
 *   context         why departments send teams, plus what a delegate returns
 *                   with, with the curriculum in a side panel
 *   fit             who it is for, and how it is taught
 *   calendar        the schools that cover THIS pillar, matched on tags
 *   accent band     one green closing call to action
 *
 * HOW A PAGE USES IT
 * ------------------
 *     $pmService = [
 *         'slug'     => 'service-pfm',
 *         'pillar'   => 'pfm',              // key in services.pillars
 *         'defaults' => ['hero_title' => '...', ...],
 *     ];
 *     require __DIR__ . '/includes/layout/service-detail.php';
 *
 * The defaults map is the page's own, not this file's, because the content
 * layer's contract is that each call site passes a real default saying what its
 * seeded row says. A shared default would have to be generic, which is the same
 * as having none.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

/** @var PDO|null $pdo */
/** @var array{slug: string, pillar: string, defaults: array<string, mixed>} $pmService */

/** Escaped copy for a key on this service page. */
function pmServiceText(?PDO $pdo, array $svc, string $key): string
{
    return pmContentSafe($pdo, (string) $svc['slug'], $key, (string) ($svc['defaults'][$key] ?? ''));
}

/** Raw copy for a key, for attributes and for values this file escapes itself. */
function pmServiceRaw(?PDO $pdo, array $svc, string $key): string
{
    return pmContent($pdo, (string) $svc['slug'], $key, (string) ($svc['defaults'][$key] ?? ''));
}

/** A json row for a key on this service page. */
function pmServiceList(?PDO $pdo, array $svc, string $key): array
{
    $default = $svc['defaults'][$key] ?? [];

    return pmContentJson($pdo, (string) $svc['slug'], $key, is_array($default) ? $default : []);
}

$pmPillars = pmContentJson($pdo, 'services', 'pillars', pmPillarsDefault());

// Which schools cover this pillar. Matched on tags held in page_content rather
// than on a hardcoded id map, so the mapping survives an event being added or
// renamed and is editable. An empty result is a real answer, not a bug: as of
// August 2026 no event in the calendar covers sustainability reporting, and
// showing all four schools under "schools covering this pillar" would tell a
// delegate they can book something that does not exist. See the comment on
// pmEventsMatchingTags().
$pmServiceEvents = pmEventsMatchingTags(
    pmActiveEvents($pdo),
    pmServiceList($pdo, $pmService, 'related_tags')
);

$pmCardLabels = [
    'details'      => pmContent($pdo, 'global', 'event_details_label', 'Details'),
    'register'     => pmContent($pdo, 'global', 'event_register_label', 'Register'),
    'badge_lapsed' => pmContent($pdo, 'global', 'early_bird_lapsed_label', 'Standard rate'),
];

pmPageBegin([
    'slug'        => $pmService['slug'],
    'nav'         => 'services',
    'title'       => pmServiceRaw($pdo, $pmService, 'meta_title'),
    'description' => pmServiceRaw($pdo, $pmService, 'meta_description'),
    'canonical'   => '/' . $pmService['slug'] . '.php',
]);
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<?php // No neural-node motif here, deliberately. Section 2 of the brief allows
      // it "sparingly and with intent, not as filler", and the approved
      // prototype puts it behind the homepage hero and the 404 and nowhere
      // else. Behind a short hero it also compresses into a much denser web of
      // lines than it does on the homepage, and runs through the heading. ?>
<section class="pm-section">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmServiceText($pdo, $pmService, 'hero_eyebrow'); ?></span>
    <h1 class="pm-h1"><?php echo pmServiceText($pdo, $pmService, 'hero_title'); ?></h1>

    <?php // The pillar's promise, set at sub-heading size rather than lede size
          // so it is visibly the claim and the line under it is visibly the
          // explanation. A type-scale class on a <p> is the same move the
          // system already sanctions in reverse: size is chosen by class, and
          // the element is chosen by what the content actually is. ?>
    <p class="pm-h3 pm-measure pm-mt-lg"><?php echo pmServiceText($pdo, $pmService, 'hero_promise'); ?></p>
    <p class="pm-body pm-mt-md"><?php echo pmServiceText($pdo, $pmService, 'hero_body'); ?></p>

  </div>
</section>


<?php // ── The three pillars, as siblings ────────────────────────────────── ?>
<?php // A real nav between three real URLs, not a client-side tab strip. The
      // prototype showed these as tabs because it was one screen; they are
      // separate pages here, so the current one is marked with aria-current
      // and the other two are ordinary links. ?>
<section class="pm-section pm-section--tight">
  <nav class="pm-container pm-switch" aria-label="Service pillars">
<?php foreach ($pmPillars as $pmPillar): ?>
<?php   $pmIsCurrent = ((string) ($pmPillar['key'] ?? '')) === $pmService['pillar']; ?>
    <a class="pm-switch__link"
       href="<?php echo pmEsc(pmServiceHref((string) ($pmPillar['key'] ?? ''))); ?>"
       <?php echo $pmIsCurrent ? 'aria-current="page"' : ''; ?>
    ><?php echo pmEsc((string) ($pmPillar['name'] ?? '')); ?></a>
<?php endforeach; ?>
  </nav>
</section>


<?php // ── Why departments send teams, and the curriculum ────────────────── ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container pm-row">

    <div class="pm-row__main">
      <?php // An h2 at sub-heading size, which the type scale exists to allow:
            // at display size this wraps to three lines in a half-width column
            // and outweighs the h1 it sits under. ?>
      <h2 class="pm-h3 pm-h3--caps"><?php echo pmServiceText($pdo, $pmService, 'context_title'); ?></h2>
      <p class="pm-body pm-mt-lg"><?php echo pmServiceText($pdo, $pmService, 'context_body_1'); ?></p>
      <p class="pm-body pm-mt-md"><?php echo pmServiceText($pdo, $pmService, 'context_body_2'); ?></p>

<?php $pmOutcomes = pmServiceList($pdo, $pmService, 'outcomes'); ?>
<?php if ($pmOutcomes !== []): ?>
      <h3 class="pm-h3 pm-mt-xl"><?php echo pmServiceText($pdo, $pmService, 'outcomes_title'); ?></h3>
      <ul class="pm-list">
<?php   foreach ($pmOutcomes as $pmOutcome): ?>
        <li><?php echo pmEsc((string) $pmOutcome); ?></li>
<?php   endforeach; ?>
      </ul>
<?php endif; ?>
    </div>

<?php $pmTopics = pmServiceList($pdo, $pmService, 'topics'); ?>
<?php if ($pmTopics !== []): ?>
    <div class="pm-row__side">
      <div class="pm-card pm-card--panel">
        <h3 class="pm-label"><?php echo pmServiceText($pdo, $pmService, 'curriculum_title'); ?></h3>
        <ul class="pm-list pm-mt-0">
<?php   foreach ($pmTopics as $pmTopic): ?>
          <li><?php echo pmEsc((string) $pmTopic); ?></li>
<?php   endforeach; ?>
        </ul>
      </div>
    </div>
<?php endif; ?>

  </div>
</section>


<?php // ── Who it is for, and how it is taught ───────────────────────────── ?>
<section class="pm-section">
  <div class="pm-container">
    <div class="pm-grid pm-grid--ruled pm-grid--2">

      <div class="pm-cell">
        <h2 class="pm-h3 pm-h3--caps"><?php echo pmServiceText($pdo, $pmService, 'audience_title'); ?></h2>
        <ul class="pm-list pm-mt-0">
<?php foreach (pmServiceList($pdo, $pmService, 'audience') as $pmWho): ?>
          <li><?php echo pmEsc((string) $pmWho); ?></li>
<?php endforeach; ?>
        </ul>
      </div>

      <div class="pm-cell">
        <h2 class="pm-h3 pm-h3--caps"><?php echo pmServiceText($pdo, $pmService, 'format_title'); ?></h2>
        <p class="pm-body"><?php echo pmServiceText($pdo, $pmService, 'format_body'); ?></p>
      </div>

    </div>
  </div>
</section>


<?php // ── The schools that cover this pillar ────────────────────────────── ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container">

    <div class="pm-section-head">
      <div>
        <h2 class="pm-h2"><?php echo pmServiceText($pdo, $pmService, 'events_title'); ?></h2>
      </div>
    </div>

    <?php pmRenderEventGrid(
        $pmServiceEvents,
        $pmCardLabels,
        pmServiceRaw($pdo, $pmService, 'events_empty'),
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
      <span class="pm-label"><?php echo pmServiceText($pdo, $pmService, 'cta_eyebrow'); ?></span>
      <h2 class="pm-h2"><?php echo pmServiceText($pdo, $pmService, 'cta_title'); ?></h2>
      <p class="pm-body"><?php echo pmServiceText($pdo, $pmService, 'cta_body'); ?></p>
    </div>

    <a class="pm-btn pm-btn--invert" href="/contact.php"><?php
      echo pmServiceText($pdo, $pmService, 'cta_label'); ?></a>

  </div>
</section>

<?php pmPageEnd(); ?>
