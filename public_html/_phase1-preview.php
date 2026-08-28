<?php
/**
 * TEMPORARY — DELETE BEFORE THIS REACHES PRODUCTION.
 *
 * A specimen page for the Phase 1 foundation. It exists so a human can look at
 * the design system in a browser at desktop and mobile widths and check it
 * against prototype/Prosperminds Site.dc.html, and so local-dev/verify.sh has
 * a page that composes head + header + footer and reads the content layer.
 *
 * It is NOT a page of the site. It is not linked from anywhere, it is not in
 * sitemap.php, it is disallowed in robots.txt, and it sends
 * <meta name="robots" content="noindex, nofollow">. The leading underscore in
 * the filename is a marker that it is scaffolding.
 *
 * Note for whoever removes it: cPanel deploys with `cp -R`, which never
 * deletes. Removing this file from the repo does NOT remove it from the live
 * webroot — it has to be deleted through File Manager as well, the same as
 * admin.zip and the two CPD scripts already flagged in PROJECT.md.
 *
 * It doubles as the worked example for Phase 2. The pattern below is the whole
 * contract: require includes/layout/page.php first, fetch the page's content in
 * one query, call pmPageBegin(), write sections using the pm-* component
 * classes, print every visible string through pmContentSafe() with a real
 * inline default, then call pmPageEnd().
 */

require_once __DIR__ . '/includes/layout/page.php';

// One query for the whole page. $home is only used to show that the bulk
// fetch works; the pmContentSafe() calls below hit the same per-request cache.
$home = pmContentAll($pdo ?? null, 'home');

pmPageBegin([
    'slug'        => 'home',
    'nav'         => 'home',
    'title'       => 'Phase 1 design system preview',
    'description' => 'Temporary specimen page for the Prosperminds rebuild design system. Not part of the site.',
    'noindex'     => true,
    'body_class'  => 'pm-preview',
]);
?>

<!-- ── Temporary marker, so nobody mistakes this for a real page ─────────── -->
<div class="pm-section pm-section--dark pm-section--tight">
  <div class="pm-container">
    <span class="pm-eyebrow">Temporary</span>
    <p class="pm-body">This page is scaffolding for Phase 1 of the rebuild. It is not part of the site, it is
      excluded from the sitemap and from robots.txt, and it is deleted before launch. Everything below is a
      specimen of the shared design system, not real page content.</p>
  </div>
</div>

<!-- ── 1. Hero: white, node motif, display type, hairline fact grid ──────── -->
<section class="pm-section pm-relative pm-clip">
  <svg class="pm-motif" viewBox="0 0 800 480" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <g stroke="#00BF63" stroke-width="0.7" fill="none" opacity="0.5">
      <path d="M60 90 150 190 90 310 210 70 260 250 330 150 400 340 470 120 520 260 600 70 640 210 700 350 760 130"></path>
      <path d="M150 190 260 250 330 150 470 120 520 260 640 210"></path>
      <path d="M90 310 360 430 400 340 700 350"></path>
      <path d="M120 430 90 310M360 430 520 260M600 70 760 130"></path>
    </g>
    <g fill="#00BF63" opacity="0.75">
      <circle cx="60" cy="90" r="2.6"></circle><circle cx="150" cy="190" r="3.4"></circle>
      <circle cx="90" cy="310" r="2.4"></circle><circle cx="210" cy="70" r="2.2"></circle>
      <circle cx="260" cy="250" r="3.6"></circle><circle cx="330" cy="150" r="2.4"></circle>
      <circle cx="400" cy="340" r="3"></circle><circle cx="470" cy="120" r="3.4"></circle>
      <circle cx="520" cy="260" r="2.6"></circle><circle cx="600" cy="70" r="2.2"></circle>
      <circle cx="640" cy="210" r="3.2"></circle><circle cx="700" cy="350" r="2.4"></circle>
      <circle cx="760" cy="130" r="2.6"></circle><circle cx="360" cy="430" r="3"></circle>
      <circle cx="120" cy="430" r="2.2"></circle>
    </g>
  </svg>

  <div class="pm-container pm-relative">
    <span class="pm-eyebrow pm-eyebrow--dash"><?php
      echo pmContentSafe($pdo ?? null, 'home', 'hero_eyebrow', 'Executive PFM training, 2026 calendar'); ?></span>

    <h1 class="pm-display"><?php
      echo pmContentSafe($pdo ?? null, 'home', 'hero_title', 'Strong systems start with strong people'); ?></h1>

    <p class="pm-lede pm-mt-lg"><?php
      echo pmContentSafe($pdo ?? null, 'home', 'hero_body',
        'Prosperminds trains senior government finance officials across Africa in public finance management, IPSAS and IFRS reporting, data analytics, AI automation and sustainability disclosure. Five-day residential courses, delivered by practitioners.'); ?></p>

    <div class="pm-btn-row pm-mt-lg">
      <a class="pm-btn" href="/index.php#events"><?php
        echo pmContentSafe($pdo ?? null, 'home', 'hero_cta_primary', 'View the 2026 calendar'); ?></a>
      <a class="pm-btn pm-btn--secondary" href="<?php echo pmEsc(pmRegisterHref()); ?>"><?php
        echo pmContentSafe($pdo ?? null, 'home', 'hero_cta_secondary', 'Register a delegate'); ?></a>
    </div>

    <?php
    // A 'json' row: one row for a repeated block, rather than eight numbered
    // keys. A malformed value falls back to this array, not to a blank grid.
    $heroFacts = pmContentJson($pdo ?? null, 'home', 'hero_facts', [
        ['value' => '25',  'label' => 'Years collective experience'],
        ['value' => '875', 'label' => 'Leaders trained'],
        ['value' => '4',   'label' => 'Schools in 2026'],
        ['value' => '5',   'label' => 'Day residential format'],
    ]);
    ?>
    <div class="pm-grid pm-grid--ruled pm-grid--4 pm-mt-lg">
<?php foreach ($heroFacts as $fact): ?>
      <div class="pm-cell pm-stack pm-stack--xs">
        <span class="pm-stat__value"><?php echo pmEsc($fact['value'] ?? ''); ?></span>
        <span class="pm-stat__label"><?php echo pmEsc($fact['label'] ?? ''); ?></span>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── 2. Section head + ruled card grid (the event-card pattern) ────────── -->
<section class="pm-section">
  <div class="pm-container">
    <div class="pm-section-head">
      <div>
        <span class="pm-eyebrow"><?php
          echo pmContentSafe($pdo ?? null, 'home', 'events_eyebrow', 'Upcoming courses'); ?></span>
        <h2 class="pm-h2"><?php
          echo pmContentSafe($pdo ?? null, 'home', 'events_title', 'Four flagship events'); ?></h2>
      </div>
      <a class="pm-btn pm-btn--secondary pm-btn--sm pm-section-head__action" href="/index.php#events">Full calendar</a>
    </div>

    <div class="pm-grid pm-grid--ruled pm-grid--auto" style="--pm-col-min:290px">
<?php
// Specimen cards only. Real event data comes from the `events` table in Phase 3.
$specimenCards = [
    ['index' => '01', 'early' => '20% until 15 Jul', 'title' => 'Future-Ready PFM Leaders in the Age of AI and Automation', 'city' => 'Cape Town, South Africa', 'dates' => '19 to 23 October 2026'],
    ['index' => '02', 'early' => '15% until 20 Aug', 'title' => 'IPSAS Clean-Audit Mastery and Intelligent Assets Accounting', 'city' => 'Kuala Lumpur, Malaysia', 'dates' => '16 to 20 November 2026'],
    ['index' => '03', 'early' => '10% until 30 Sep', 'title' => 'Data-Driven Budget Control, Revenue Growth and Funding Breakthroughs', 'city' => 'Bali, Indonesia', 'dates' => '7 to 11 December 2026'],
];
foreach ($specimenCards as $card):
?>
      <article class="pm-stack">
        <div class="pm-banner"></div>
        <div class="pm-cell">
          <div class="pm-cell__meta">
            <span class="pm-ordinal"><?php echo pmEsc($card['index']); ?></span>
            <span class="pm-label pm-label--wide"><?php echo pmEsc($card['early']); ?></span>
          </div>
          <h3 class="pm-h3"><?php echo pmEsc($card['title']); ?></h3>
          <div class="pm-stack pm-stack--xs pm-caption">
            <span><?php echo pmEsc($card['city']); ?></span>
            <span><?php echo pmEsc($card['dates']); ?></span>
          </div>
          <div class="pm-card__foot pm-card__foot--split">
            <span>From USD 599</span>
            <a class="pm-btn--link" href="/index.php#events">Details</a>
          </div>
        </div>
      </article>
<?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── 3. Surface section: the three pillars, numbered ───────────────────── -->
<section class="pm-section pm-section--surface pm-section--ruled">
  <div class="pm-container">
    <span class="pm-eyebrow"><?php
      echo pmContentSafe($pdo ?? null, 'home', 'pillars_eyebrow', 'What we teach'); ?></span>
    <h2 class="pm-h2 pm-measure"><?php
      echo pmContentSafe($pdo ?? null, 'home', 'pillars_title', 'Three pillars of public finance capability'); ?></h2>

    <?php
    $pillars = pmContentJson($pdo ?? null, 'services', 'pillars', [
        ['num' => '01', 'name' => 'PFM, IPSAS and IFRS Mastery', 'promise' => 'Build the technical foundation your finance teams need.'],
        ['num' => '02', 'name' => 'Data Analytics and AI Automation', 'promise' => 'Transform reporting from burden to strategic advantage.'],
        ['num' => '03', 'name' => 'Sustainability Reporting', 'promise' => 'Meet global standards while strengthening transparency.'],
    ]);
    ?>
    <div class="pm-grid pm-grid--ruled pm-grid--3 pm-mt-lg">
<?php foreach ($pillars as $pillar): ?>
      <div class="pm-cell">
        <span class="pm-ordinal"><?php echo pmEsc($pillar['num'] ?? ''); ?></span>
        <h3 class="pm-h3 pm-h3--caps"><?php echo pmEsc($pillar['name'] ?? ''); ?></h3>
        <p class="pm-body"><?php echo pmEsc($pillar['promise'] ?? ''); ?></p>
        <a class="pm-btn--link pm-cell__action" href="/service-pfm.php">Read more</a>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── 4. Track record: two-column row with a stat grid ──────────────────── -->
<section class="pm-section">
  <div class="pm-container pm-row">
    <div class="pm-row__main">
      <span class="pm-eyebrow"><?php
        echo pmContentSafe($pdo ?? null, 'home', 'record_eyebrow', 'Track record'); ?></span>
      <h2 class="pm-h2"><?php
        echo pmContentSafe($pdo ?? null, 'home', 'record_title', 'Twenty-five years in the room'); ?></h2>
      <p class="pm-body pm-mt-md"><?php
        echo pmContentSafe($pdo ?? null, 'home', 'record_body',
          'Our faculty has spent a quarter of a century inside treasuries, audit offices and accountant-general departments.'); ?></p>
      <a class="pm-btn pm-btn--secondary pm-btn--sm pm-mt-lg" href="/index.php#about">About Prosperminds</a>
    </div>

    <?php
    $stats = pmContentJson($pdo ?? null, 'home', 'stats', [
        ['value' => '25',  'label' => 'Years collective experience'],
        ['value' => '875', 'label' => 'Leaders trained'],
        ['value' => '14',  'label' => 'Countries represented'],
        ['value' => '5',   'label' => 'Days per school'],
    ]);
    ?>
    <div class="pm-row__side pm-grid pm-grid--ruled pm-grid--4">
<?php foreach ($stats as $stat): ?>
      <div class="pm-cell pm-stack pm-stack--xs">
        <span class="pm-stat__value"><?php echo pmEsc($stat['value'] ?? ''); ?></span>
        <span class="pm-stat__label"><?php echo pmEsc($stat['label'] ?? ''); ?></span>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── 5. Testimonials: bordered cards, green quote mark ─────────────────── -->
<section class="pm-section pm-section--surface">
  <div class="pm-container">
    <span class="pm-eyebrow"><?php
      echo pmContentSafe($pdo ?? null, 'home', 'testimonials_eyebrow', 'Delegate feedback'); ?></span>
    <h2 class="pm-h2"><?php
      echo pmContentSafe($pdo ?? null, 'home', 'testimonials_title', 'In their words'); ?></h2>

    <?php
    $testimonials = pmContentJson($pdo ?? null, 'home', 'testimonials', [
        ['quote' => 'The faculty had done the job. That mattered.', 'role' => 'Treasury Leader', 'org' => 'Federal Ministry of Finance, Nigeria'],
    ]);
    ?>
    <div class="pm-grid pm-grid--auto pm-mt-lg" style="--pm-col-min:280px">
<?php foreach (array_slice($testimonials, 0, 3) as $quote): ?>
      <figure class="pm-card">
        <span class="pm-quote__mark" aria-hidden="true">&ldquo;</span>
        <blockquote class="pm-quote__text"><?php echo pmEsc($quote['quote'] ?? ''); ?></blockquote>
        <figcaption class="pm-card__foot pm-caption">
          <?php echo pmEsc($quote['role'] ?? ''); ?><br><?php echo pmEsc($quote['org'] ?? ''); ?>
        </figcaption>
      </figure>
<?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── 6. Table specimen: the pricing-tier / agenda pattern ──────────────── -->
<section class="pm-section">
  <div class="pm-container">
    <span class="pm-eyebrow">Component specimen</span>
    <h2 class="pm-h2">Tables</h2>
    <div class="pm-table-scroll pm-mt-lg">
      <table class="pm-table">
        <caption class="pm-sr-only">Specimen pricing tiers</caption>
        <thead>
          <tr><th scope="col">Tier</th><th scope="col">Per delegate</th><th scope="col">Includes</th></tr>
        </thead>
        <tbody>
          <tr><td>Regular</td><td class="pm-table__num">USD 599</td><td>Five days, all sessions, CPD certification, course materials.</td></tr>
          <tr><td>VIP</td><td class="pm-table__num">USD 1,999</td><td>Everything in Regular, plus the executive roundtable and priority seating.</td></tr>
          <tr><td>VVIP</td><td class="pm-table__num">USD 2,899</td><td>Everything in VIP, plus the gala dinner and a faculty advisory session.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- ── 7. Form specimen, light ───────────────────────────────────────────── -->
<section class="pm-section pm-section--surface">
  <div class="pm-container">
    <span class="pm-eyebrow">Component specimen</span>
    <h2 class="pm-h2">Forms</h2>
    <p class="pm-body pm-mt-md">Specimen controls only. This form does not submit anywhere.</p>

    <form class="pm-mt-lg" onsubmit="return false">
      <div class="pm-grid pm-grid--auto" style="--pm-col-min:240px">
        <div class="pm-field">
          <label class="pm-field__label" for="pv-institution">Institution</label>
          <input class="pm-input" type="text" id="pv-institution" placeholder="Ministry, county or authority">
        </div>
        <div class="pm-field">
          <label class="pm-field__label" for="pv-role">Job title</label>
          <input class="pm-input" type="text" id="pv-role" placeholder="e.g. Chief Accountant">
        </div>
        <div class="pm-field">
          <label class="pm-field__label" for="pv-event">Event of interest</label>
          <select class="pm-select" id="pv-event">
            <option>All four 2026 schools</option>
            <option>Cape Town, October</option>
            <option>Kuala Lumpur, November</option>
            <option>Bali, December</option>
            <option>Mombasa, December</option>
          </select>
        </div>
      </div>

      <div class="pm-field pm-mt-md">
        <label class="pm-field__label" for="pv-message">Enquiry</label>
        <textarea class="pm-textarea" id="pv-message" placeholder="What would you like to know?"></textarea>
      </div>

      <label class="pm-check pm-mt-md">
        <input type="checkbox">
        <span>I agree that Prosperminds may contact me about this enquiry and about course dates.</span>
      </label>

      <p class="pm-notice pm-mt-md">A notice. Green rule, flat surface, no colour outside the palette.</p>
      <p class="pm-notice pm-notice--error pm-mt-sm">An error notice. Marked with a black rule and plain wording rather than a fourth colour.</p>

      <div class="pm-btn-row pm-mt-lg">
        <button class="pm-btn" type="submit">Send enquiry</button>
        <button class="pm-btn pm-btn--secondary" type="button">Cancel</button>
      </div>
    </form>
  </div>
</section>

<!-- ── 8. Dark section: brand voice, inverted controls ───────────────────── -->
<section class="pm-section pm-section--dark">
  <div class="pm-container pm-row">
    <div class="pm-row__main">
      <span class="pm-eyebrow pm-eyebrow--dash">Dark section</span>
      <h2 class="pm-h2">When pressure hits, leaders rise</h2>
      <p class="pm-lede pm-mt-md">Dark sections carry brand voice. Light sections carry the information a delegate
        reads closely: agendas, pricing, forms, contact details.</p>
      <div class="pm-btn-row pm-mt-lg">
        <a class="pm-btn" href="/index.php#events">Primary</a>
        <a class="pm-btn pm-btn--secondary" href="/index.php#about">Secondary</a>
      </div>
    </div>
    <div class="pm-row__side pm-stack pm-stack--md">
      <div class="pm-field">
        <label class="pm-field__label" for="pv-dark-email">Email address</label>
        <input class="pm-input" type="email" id="pv-dark-email" placeholder="name@institution.go.ke">
      </div>
      <div class="pm-table-scroll">
        <table class="pm-table">
          <caption class="pm-sr-only">Specimen table on a dark section</caption>
          <thead><tr><th scope="col">Day</th><th scope="col">Focus</th></tr></thead>
          <tbody>
            <tr><td>Day 1</td><td>Leadership context</td></tr>
            <tr><td>Day 5</td><td>Synthesis and certification</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- ── 9. The one green band per page ────────────────────────────────────── -->
<section class="pm-section pm-section--accent pm-section--tight">
  <div class="pm-container pm-band">
    <div class="pm-row__main">
      <h2 class="pm-h2 pm-measure--sm">Registration for the 2026 schools is open</h2>
      <p class="pm-lede pm-mt-sm"><?php
        echo pmContentSafe($pdo ?? null, 'home', 'cta_body', 'Early-bird pricing is tiered by registration date.'); ?></p>
    </div>
    <a class="pm-btn pm-btn--invert" href="<?php echo pmEsc(pmRegisterHref()); ?>"><?php
      echo pmContentSafe($pdo ?? null, 'home', 'cta_label', 'Start registration'); ?></a>
  </div>
</section>

<!-- ── 10. Content-layer proof, for verify.sh and for a human reviewer ───── -->
<section class="pm-section pm-section--tight">
  <div class="pm-container">
    <span class="pm-eyebrow">Content layer</span>
    <h2 class="pm-h3">What the database is and is not supplying</h2>
    <div class="pm-table-scroll pm-mt-md">
      <table class="pm-table">
        <caption class="pm-sr-only">Content layer status</caption>
        <thead><tr><th scope="col">Check</th><th scope="col">Value</th></tr></thead>
        <tbody>
          <tr>
            <td>Seeded key <code>home.hero_title</code></td>
            <td data-pm-check="seeded"><?php
              echo pmContentSafe($pdo ?? null, 'home', 'hero_title', 'FALLBACK-DEFAULT-USED'); ?></td>
          </tr>
          <tr>
            <td>Missing key <code>home.no_such_key</code></td>
            <td data-pm-check="default"><?php
              echo pmContentSafe($pdo ?? null, 'home', 'no_such_key', 'default-was-returned'); ?></td>
          </tr>
          <tr>
            <td>Keys loaded for <code>home</code> in one query</td>
            <td data-pm-check="count"><?php echo count($home); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php
pmPageEnd();
