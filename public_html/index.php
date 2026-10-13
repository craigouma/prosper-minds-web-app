<?php
/**
 * Home: one line, one button, then the schools.
 *
 * Schools come from the events table and the next webinar from
 * webinar_sessions; no title, date or price is written in this file. The prose
 * is page_content under 'home' with real inline defaults, so a missing
 * page_content table renders the same page. Keys prefixed v2_ belong to this
 * layout, which keeps rows seeded for the previous design from leaking into it.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/testimonials.php';

$events = pmActiveEvents($pdo);

$pmNextWebinar = null;
try {
    require_once __DIR__ . '/includes/webinars.php';
    $pmNextWebinar = pmWebinarSessionsUpcoming($pdo)[0] ?? null;
} catch (Throwable $pmWebinarError) {
    error_log('home: next webinar unavailable: ' . $pmWebinarError->getMessage());
}

$pmStats = array_slice(pmContentJson($pdo, 'home', 'stats', [
    ['value' => '25',  'label' => 'Years collective experience'],
    ['value' => '875', 'label' => 'Leaders trained'],
]), 0, 2);

// Attributed by role and country only, as supplied.
$pmQuotes = array_slice(pmTestimonials($pdo, pmContentJson($pdo, 'home', 'testimonials', [
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
])), 0, 3);

// The nearest school's own poster is the share image when the page is linked.
$pmHomeShareImage = $events !== [] ? pmEventImageUrl($events[0]) : '';

pmPageBegin([
    'slug'        => 'home',
    'nav'         => 'home',
    'title'       => pmContent($pdo, 'home', 'meta_title', 'Prosperminds | Public Finance, IPSAS, AI and Sustainability Training'),
    'description' => pmContent($pdo, 'home', 'meta_description', 'Prosperminds trains senior government finance officials across Africa in public finance management, IPSAS and IFRS reporting, data analytics, AI automation and sustainability disclosure.'),
    'canonical'   => '/',
    'og_image'    => $pmHomeShareImage !== '' ? $pmHomeShareImage : PM_SOCIAL_IMAGE,
    'treatment'   => pmHomeTreatment() === 'dark' ? 'dark' : '',
]);
?>

<section class="pm-hero">
  <div class="pm-container">
    <h1 class="pm-display"><?php echo pmContentSafe($pdo, 'home', 'v2_hero_title',
      'Public finance training for the people who sign the accounts.'); ?></h1>
    <a class="pm-btn" href="#schools"><?php echo pmContentSafe($pdo, 'home', 'v2_hero_cta',
      'See the 2026 schools'); ?></a>
  </div>
</section>


<section class="pm-section pm-section--flush-top" id="schools">
  <div class="pm-container">

    <div class="pm-rule-head">
      <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'home', 'v2_schools_title', '2026 schools'); ?></h2>
      <span><?php echo pmContentSafe($pdo, 'home', 'v2_schools_note',
        'Five days, residential, CPD certified'); ?></span>
    </div>

<?php if ($events !== []): ?>
    <div class="pm-schools">
<?php foreach ($events as $pmIndex => $pmEvent): ?>
<?php   pmRenderSchoolCard($pmEvent, (int) $pmIndex); ?>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="pm-body pm-mt-lg"><?php echo pmContentSafe($pdo, 'home', 'events_empty',
      'Dates for the next intake are being confirmed. Contact the programme office and we will tell you first.'); ?></p>
<?php endif; ?>

  </div>
</section>


<section class="pm-band">
  <div class="pm-container pm-live-band">
    <div>
      <span class="pm-label"><?php echo pmContentSafe($pdo, 'home', 'v2_live_eyebrow', 'PFM Insight Live'); ?></span>
      <h2 class="pm-h2 pm-mt-md"><?php echo pmContentSafe($pdo, 'home', 'v2_live_title',
        'Free. Monthly. One hour. Online.'); ?></h2>
      <p class="pm-body pm-mt-md pm-measure"><?php echo pmContentSafe($pdo, 'home', 'v2_live_body',
        'Last Thursday of every month, 12:00 to 13:00 EAT. In partnership with CapaBuil Ltd.'); ?></p>
    </div>

    <div class="pm-live-band__next">
<?php if ($pmNextWebinar !== null): ?>
      <span class="pm-live pm-live--tag">Next session</span>
      <p class="pm-h3 pm-mt-md"><?php echo pmEsc((string) $pmNextWebinar['title']); ?></p>
      <p class="pm-mt-sm"><?php echo pmEsc(pmWebinarDateLong($pmNextWebinar)); ?>, 12:00 EAT</p>
<?php endif; ?>
      <a class="pm-btn" href="/webinars.php"><?php echo pmContentSafe($pdo, 'home', 'v2_live_cta',
        'Join the series'); ?></a>
    </div>
  </div>
</section>


<section class="pm-section">
  <div class="pm-container">

<?php if ($pmStats !== []): ?>
    <div class="pm-bigstats">
<?php foreach ($pmStats as $pmStat): ?>
      <div>
        <div class="pm-bigstat__value" data-pm-count><?php echo pmEsc((string) ($pmStat['value'] ?? '')); ?></div>
        <div class="pm-bigstat__label"><?php echo pmEsc((string) ($pmStat['label'] ?? '')); ?></div>
      </div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($pmQuotes !== []): ?>
    <div class="pm-quotes">
<?php foreach ($pmQuotes as $pmQuote): ?>
      <figure class="pm-quote">
        <blockquote>&ldquo;<?php echo pmEsc((string) ($pmQuote['quote'] ?? '')); ?>&rdquo;</blockquote>
        <figcaption><?php echo pmEsc(trim((string) ($pmQuote['role'] ?? '') . ', ' . (string) ($pmQuote['org'] ?? ''), ', ')); ?></figcaption>
      </figure>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <p class="pm-body pm-muted pm-partner pm-mt-xl">In partnership with <strong>CapaBuil Ltd.</strong></p>

  </div>
</section>

<?php pmPageEnd(); ?>
