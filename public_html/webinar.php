<?php
/**
 * One PFM Insight Live session: its own page, address and sign-up.
 *
 * Served at /webinars/{slug} by the rewrite in .htaccess. The page never
 * prints the Zoom link: it is emailed to people after they register, and a
 * public page that carried it would defeat registering.
 *
 * Works without JavaScript: the form is a plain POST to
 * process-webinar-registration.php, which answers with a redirect back here
 * carrying a status in the query string.
 */

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/webinars.php';

$pmSession = pmWebinarSessionBySlug($pdo, (string) ($_GET['slug'] ?? ''));

if ($pmSession === null || (int) ($pmSession['is_active'] ?? 0) !== 1) {
    http_response_code(404);

    pmPageBegin([
        'slug'        => 'webinar',
        'nav'         => 'webinars',
        'title'       => 'Session not found',
        'description' => 'This PFM Insight Live session is not on the calendar.',
        'noindex'     => true,
    ]);
    ?>
<section class="pm-section pm-404">
  <div class="pm-container">
    <span class="pm-eyebrow">Not found</span>
    <h1 class="pm-h1 pm-h1--404 pm-mt-sm">That session is not on the calendar.</h1>
    <a class="pm-btn" href="/webinars.php#sessions">See all sessions</a>
  </div>
</section>
    <?php
    pmPageEnd();
    exit;
}

$pmAll      = pmWebinarSessions($pdo);
$pmTotal    = count($pmAll);
$pmPosition = 0;
foreach ($pmAll as $pmIndex => $pmRow) {
    if ((int) $pmRow['id'] === (int) $pmSession['id']) {
        $pmPosition = $pmIndex;
        break;
    }
}
$pmPrevious = $pmAll[$pmPosition - 1] ?? null;
$pmNextOne  = $pmAll[$pmPosition + 1] ?? null;

$pmTitle    = pmEventProse((string) $pmSession['title']);
$pmAbout    = pmEventProse((string) ($pmSession['description'] ?? ''));
$pmBestFor  = pmEventProse((string) ($pmSession['best_for'] ?? ''));
$pmNumber   = str_pad((string) $pmSession['session_number'], 2, '0', STR_PAD_LEFT);
$pmTimes    = pmWebinarTimes($pmSession);
$pmDateTs   = strtotime((string) $pmSession['session_date']);
$pmDateText = $pmDateTs === false ? '' : date('l j F Y', $pmDateTs);
$pmIsPast   = (string) $pmSession['session_date'] < date('Y-m-d');
$pmUrl      = pmWebinarUrl($pmSession);

$pmPosterUrl  = pmWebinarPosterUrl($pmSession);
$pmPosterSize = $pmPosterUrl !== '' && function_exists('pmEventImageSize')
    ? pmEventImageSize((string) $pmSession['image_path'])
    : null;

$pmStatus = (string) ($_GET['webinar'] ?? '');
$pmNotice = match ($pmStatus) {
    'ok'      => 'You are registered. Check your email for the join link.',
    'invalid' => 'Please check your name and email and try again.',
    'csrf'    => 'That form had expired. Please try once more.',
    'error'   => 'We could not save that just now. Please email info@prosper-minds.com and we will add you directly.',
    default   => '',
};

pmPageBegin([
    'slug'        => 'webinar',
    'nav'         => 'webinars',
    'title'       => $pmTitle . ' | PFM Insight Live',
    'description' => trim($pmAbout . ' Free, live and online, ' . $pmDateText . ', ' . $pmTimes['range'] . '.'),
    'canonical'   => $pmUrl,
    'og_image'    => $pmPosterUrl !== '' ? $pmPosterUrl : PM_SOCIAL_IMAGE,
]);

if ($pmTimes['start'] !== '') {
    $pmData = [
        '@context'            => 'https://schema.org',
        '@type'               => 'Event',
        'name'                => $pmTitle,
        'description'         => $pmAbout,
        'startDate'           => $pmTimes['start'],
        'endDate'             => $pmTimes['end'],
        'eventStatus'         => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
        'isAccessibleForFree' => true,
        'location'            => ['@type' => 'VirtualLocation', 'url' => PM_SITE_ORIGIN . $pmUrl],
        'organizer'           => ['@type' => 'Organization', 'name' => 'Prosperminds', 'url' => PM_SITE_ORIGIN],
        'offers'              => [
            '@type'         => 'Offer',
            'price'         => '0',
            'priceCurrency' => 'USD',
            'availability'  => 'https://schema.org/InStock',
            'url'           => PM_SITE_ORIGIN . $pmUrl,
        ],
    ];
    if ($pmPosterUrl !== '') {
        $pmData['image'] = [PM_SITE_ORIGIN . $pmPosterUrl];
    }
    echo '<script type="application/ld+json">'
        . json_encode($pmData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
        . "</script>\n";
}
?>

<div class="pm-container pm-page-head">

  <a class="pm-back" href="/webinars.php#sessions">All sessions</a>

  <div class="pm-split">
    <div>
<?php if ($pmIsPast): ?>
      <span class="pm-eyebrow">PFM Insight Live, session <?php echo pmEsc($pmNumber); ?> of <?php echo (int) $pmTotal; ?>, finished</span>
<?php else: ?>
      <span class="pm-live pm-live--tag">PFM Insight Live, session <?php echo pmEsc($pmNumber); ?> of <?php echo (int) $pmTotal; ?></span>
<?php endif; ?>
      <h1 class="pm-h1 pm-h1--long pm-mt-md"><?php echo pmEsc($pmTitle); ?></h1>

<?php if ($pmAbout !== ''): ?>
      <p class="pm-lede pm-mt-md"><?php echo pmEsc($pmAbout); ?></p>
<?php endif; ?>

      <dl class="pm-facts">
        <dt>Date</dt>
        <dd><?php echo pmEsc($pmDateText); ?></dd>
        <dt>Time</dt>
        <dd><?php echo pmEsc($pmTimes['range']); ?><?php
          if ($pmTimes['zones'] !== ''): ?><br><span class="pm-caption"><?php echo pmEsc($pmTimes['zones']); ?></span><?php endif; ?></dd>
        <dt>Where</dt>
        <dd>Online, live, one hour</dd>
        <dt>Cost</dt>
        <dd>Free</dd>
<?php if ($pmBestFor !== ''): ?>
        <dt>Best for</dt>
        <dd><?php echo pmEsc($pmBestFor); ?></dd>
<?php endif; ?>
        <dt>You take home</dt>
        <dd>A tool or template, and a certificate of attendance</dd>
      </dl>

<?php if ($pmPosterUrl !== ''): ?>
      <?php // Shown whole, never cropped: it carries its own title, date and
            // time. Real dimensions when they can be read, so the page does
            // not jump. ?>
      <div class="pm-next-session__poster">
        <img src="<?php echo pmEsc($pmPosterUrl); ?>"
             alt="Poster: PFM Insight Live, Session <?php echo pmEsc($pmNumber); ?>, <?php echo pmEsc($pmTitle); ?>. <?php echo pmEsc($pmDateText); ?>, <?php echo pmEsc($pmTimes['range']); ?>. Free and online."
<?php   if ($pmPosterSize !== null): ?>
             width="<?php echo $pmPosterSize[0]; ?>" height="<?php echo $pmPosterSize[1]; ?>"
<?php   endif; ?>
             decoding="async">
      </div>
<?php endif; ?>
    </div>

    <div class="pm-box" id="register">
<?php if ($pmIsPast): ?>
      <h2 class="pm-h2 pm-h2--md">This session has taken place</h2>
      <p class="pm-body pm-mt-sm">Registration for it is closed. The next sessions are open.</p>
      <p class="pm-mt-md"><a class="pm-btn" href="/webinars.php#sessions">See the upcoming sessions</a></p>
<?php elseif ($pmStatus === 'ok'): ?>
      <h2 class="pm-h2 pm-h2--md">You are registered</h2>
      <p class="pm-body pm-mt-sm"><?php echo pmEsc($pmNotice); ?></p>
      <p class="pm-mt-sm"><a class="pm-link" href="/webinars.php#sessions">See the other sessions</a></p>
<?php else: ?>
      <h2 class="pm-h2 pm-h2--md">Register for this session</h2>
      <p class="pm-body pm-mt-sm">We email you the join link now and a reminder the day before.
        Prefer every session? <a href="/webinars.php#register">Join the whole series instead</a>.</p>

<?php   if ($pmNotice !== ''): ?>
      <p class="pm-notice pm-notice--error pm-mt-md" role="alert"><?php echo pmEsc($pmNotice); ?></p>
<?php   endif; ?>

      <form class="pm-stack pm-stack--form pm-mt-md" action="/process-webinar-registration.php" method="post">
        <?php echo formCsrfField(); ?>
        <input type="hidden" name="session_id" value="<?php echo (int) $pmSession['id']; ?>">
        <input type="hidden" name="return_to" value="<?php echo pmEsc($pmUrl); ?>">

        <div class="pm-honeypot" aria-hidden="true">
          <label for="pm-webinar-company">Company</label>
          <input type="text" id="pm-webinar-company" name="company" tabindex="-1" autocomplete="off">
        </div>

        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-name">Full name</label>
          <input class="pm-input" type="text" id="pm-webinar-name" name="name" autocomplete="name" required>
        </div>

        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-email">Work email</label>
          <input class="pm-input" type="email" id="pm-webinar-email" name="email"
                 inputmode="email" autocomplete="email" required>
        </div>

        <label class="pm-check" for="pm-webinar-consent">
          <input type="checkbox" id="pm-webinar-consent" name="consent" value="yes" required>
          <span>Email me the joining link and a reminder before this session.</span>
        </label>

        <button class="pm-btn" type="submit">Register for this session</button>
      </form>
<?php endif; ?>
    </div>
  </div>

<?php if ($pmPrevious !== null || $pmNextOne !== null): ?>
  <nav class="pm-grid pm-grid--2 pm-grid--ruled pm-mt-xl" aria-label="Other sessions">
<?php   if ($pmPrevious !== null): ?>
    <div class="pm-cell">
      <span class="pm-caption">Previous session</span>
      <a class="pm-link" href="<?php echo pmEsc(pmWebinarUrl($pmPrevious)); ?>"><?php
        echo pmEsc((string) $pmPrevious['title']); ?></a>
    </div>
<?php   endif; ?>
<?php   if ($pmNextOne !== null): ?>
    <div class="pm-cell">
      <span class="pm-caption">Next session</span>
      <a class="pm-link" href="<?php echo pmEsc(pmWebinarUrl($pmNextOne)); ?>"><?php
        echo pmEsc((string) $pmNextOne['title']); ?></a>
    </div>
<?php   endif; ?>
  </nav>
<?php endif; ?>

</div>

<div class="pm-section"></div>

<?php pmPageEnd(); ?>
