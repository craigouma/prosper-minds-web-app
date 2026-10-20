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
require_once __DIR__ . '/includes/formguard.php';

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
<section class="pm-section">
  <div class="pm-container">
    <span class="pm-eyebrow">Not found</span>
    <h1 class="pm-h1">That session is not on the calendar</h1>
    <p class="pm-lede pm-mt-lg">The link may be old. Every PFM Insight Live session is listed on the webinars page.</p>
    <p class="pm-mt-lg"><a class="pm-btn" href="/webinars.php#sessions">See all sessions</a></p>
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
    'fast'    => 'That was very quick. Please check your details and submit again.',
    'stale'   => 'This form has expired. Please reload the page and try again.',
    'rate'    => 'There have been a lot of submissions from your connection. Please wait a little and try again, or email info@prosper-minds.com.',
    'csrf'    => 'That form had expired. Please try once more.',
    'error'   => 'We could not save that just now. Please email info@prosper-minds.com and we will add you directly.',
    default   => '',
};
$pmNoticeFailed = $pmStatus !== '' && $pmStatus !== 'ok';

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

<section class="pm-section pm-section--tight">
  <div class="pm-container">

    <p><a class="pm-btn--link" href="/webinars.php#sessions">All sessions</a></p>

    <span class="pm-eyebrow pm-mt-md">PFM Insight Live &middot; Session <?php echo pmEsc($pmNumber); ?> of <?php echo (int) $pmTotal; ?></span>
    <h1 class="pm-h1"><?php echo pmEsc($pmTitle); ?></h1>

<?php if ($pmAbout !== ''): ?>
    <p class="pm-lede pm-mt-lg pm-measure"><?php echo pmEsc($pmAbout); ?></p>
<?php endif; ?>

    <div class="pm-grid pm-grid--ruled pm-grid--3 pm-mt-lg">
      <div class="pm-cell">
        <span class="pm-label">Date</span>
        <span class="pm-body"><?php echo pmEsc($pmDateText); ?></span>
      </div>
      <div class="pm-cell">
        <span class="pm-label">Time</span>
        <span class="pm-body"><?php echo pmEsc($pmTimes['range']); ?></span>
<?php if ($pmTimes['zones'] !== ''): ?>
        <span class="pm-caption"><?php echo pmEsc($pmTimes['zones']); ?></span>
<?php endif; ?>
      </div>
      <div class="pm-cell">
        <span class="pm-label">Where and cost</span>
        <span class="pm-body">Online, live, one hour</span>
        <span class="pm-caption">Free to join</span>
      </div>
    </div>

<?php if ($pmBestFor !== ''): ?>
    <p class="pm-body pm-mt-md"><strong>Best for:</strong> <?php echo pmEsc($pmBestFor); ?></p>
<?php endif; ?>

    <div class="pm-btn-row pm-mt-lg">
<?php if ($pmIsPast): ?>
      <a class="pm-btn" href="/webinars.php#sessions">See the upcoming sessions</a>
<?php else: ?>
      <a class="pm-btn" href="#join">Register free</a>
      <a class="pm-btn pm-btn--secondary" href="/webinars.php#join">Join the whole series</a>
<?php endif; ?>
    </div>

  </div>
</section>

<?php if ($pmPosterUrl !== ''): ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">
    <?php // Shown whole, never cropped: it carries its own title, date and
          // time. Real dimensions when they can be read, so the page does not
          // jump. ?>
    <img src="<?php echo pmEsc($pmPosterUrl); ?>"
         alt="Poster: PFM Insight Live, Session <?php echo pmEsc($pmNumber); ?>, <?php echo pmEsc($pmTitle); ?>. <?php echo pmEsc($pmDateText); ?>, <?php echo pmEsc($pmTimes['range']); ?>. Free and online."
<?php if ($pmPosterSize !== null): ?>
         width="<?php echo $pmPosterSize[0]; ?>" height="<?php echo $pmPosterSize[1]; ?>"
<?php endif; ?>
         decoding="async">
  </div>
</section>
<?php endif; ?>

<section class="pm-section pm-section--ruled">
  <div class="pm-container">
    <div class="pm-grid pm-grid--ruled pm-grid--2">
      <div class="pm-cell">
        <span class="pm-label">You take home</span>
        <span class="pm-body">A tool or template to use at your desk.</span>
      </div>
      <div class="pm-cell">
        <span class="pm-label">And a certificate</span>
        <span class="pm-body">A certificate of attendance for your CPD records.</span>
      </div>
    </div>
  </div>
</section>

<?php if (!$pmIsPast): ?>
<section class="pm-section pm-section--tight" id="join">
  <div class="pm-container">

    <h2 class="pm-h2">Register for this session</h2>
    <p class="pm-body pm-mt-md pm-measure">We will email you the join link now and a reminder the day before.
      Prefer every session? <a href="/webinars.php#join">Join the whole series instead</a>.</p>

<?php if ($pmNotice !== ''): ?>
    <p class="pm-notice<?php echo $pmNoticeFailed ? ' pm-notice--error' : ''; ?> pm-mt-md" role="status">
      <?php echo pmEsc($pmNotice); ?>
    </p>
<?php endif; ?>

    <form class="pm-mt-lg" action="/process-webinar-registration.php" method="post" novalidate>
      <?php echo formCsrfField(); ?>
      <?php echo pmGuardFields('webinar'); ?>
      <input type="hidden" name="session_id" value="<?php echo (int) $pmSession['id']; ?>">
      <input type="hidden" name="return_to" value="<?php echo pmEsc($pmUrl); ?>">

      <div class="pm-sr-only" aria-hidden="true">
        <label for="pm-webinar-company">Company</label>
        <input type="text" id="pm-webinar-company" name="company" tabindex="-1" autocomplete="off">
      </div>

      <div class="pm-grid pm-grid--3">
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-name">Full name</label>
          <input class="pm-input" type="text" id="pm-webinar-name" name="name" autocomplete="name" required>
        </div>
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-email">Email</label>
          <input class="pm-input" type="email" id="pm-webinar-email" name="email" autocomplete="email" required>
        </div>
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-organization">Institution</label>
          <input class="pm-input" type="text" id="pm-webinar-organization" name="organization" autocomplete="organization">
        </div>
      </div>

      <label class="pm-check pm-mt-md" for="pm-webinar-consent">
        <input type="checkbox" id="pm-webinar-consent" name="consent" value="yes" required>
        <span>I am happy to be emailed the join link and a reminder for this session.</span>
      </label>

      <button class="pm-btn pm-mt-md" type="submit">Register for this session</button>
    </form>

  </div>
</section>
<?php endif; ?>

<?php if ($pmPrevious !== null || $pmNextOne !== null): ?>
<section class="pm-section pm-section--ruled pm-section--tight">
  <div class="pm-container">
    <nav class="pm-grid pm-grid--2" aria-label="Other sessions">
<?php if ($pmPrevious !== null): ?>
      <div class="pm-cell">
        <span class="pm-label">Previous session</span>
        <a class="pm-body pm-link" href="<?php echo pmEsc(pmWebinarUrl($pmPrevious)); ?>"><?php
          echo pmEsc((string) $pmPrevious['title']); ?></a>
      </div>
<?php endif; ?>
<?php if ($pmNextOne !== null): ?>
      <div class="pm-cell">
        <span class="pm-label">Next session</span>
        <a class="pm-body pm-link" href="<?php echo pmEsc(pmWebinarUrl($pmNextOne)); ?>"><?php
          echo pmEsc((string) $pmNextOne['title']); ?></a>
      </div>
<?php endif; ?>
    </nav>
  </div>
</section>
<?php endif; ?>

<?php pmPageEnd(); ?>
