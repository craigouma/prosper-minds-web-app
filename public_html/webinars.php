<?php
/**
 * PFM Insight Live: the free monthly webinar series.
 *
 * One page, one form. "Join the series" is the default; a session row's own
 * Register link reloads the page with ?session=ID, which turns the same form
 * into a single-session sign-up. Both are plain POSTs to
 * process-webinar-registration.php, which answers with a redirect back here
 * carrying a status in the query string, read below the same way
 * includes/layout/footer.php reads the newsletter form's result.
 */

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/webinars.php';

$pmSessions = pmWebinarSessionsUpcoming($pdo);

// A row's own Register link arrives with ?session=ID, which turns the one form
// below from "join the series" into "register for this session".
$pmRequestedSession = (int) ($_GET['session'] ?? 0);
$pmSessionIds        = array_column($pmSessions, 'id');
$pmOne = null;
foreach ($pmSessions as $pmCandidate) {
    if ((int) $pmCandidate['id'] === $pmRequestedSession) {
        $pmOne = $pmCandidate;
        break;
    }
}

// Same return_to computation as footer.php's newsletter form, and for the
// same reason: re-validated at the endpoint, never trusted because this file
// wrote it.
$pmReturnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/webinars.php');
if ($pmReturnTo === '' || $pmReturnTo[0] !== '/' || str_starts_with($pmReturnTo, '//')) {
    $pmReturnTo = '/webinars.php';
}
$pmReturnTo = preg_replace('/([?&])webinar=[^&]*(&|$)/', '$1', $pmReturnTo) ?? '/webinars.php';
$pmReturnTo = rtrim($pmReturnTo, '?&');

$pmStatus = (string) ($_GET['webinar'] ?? '');
$pmNotice = match ($pmStatus) {
    'ok'      => 'You are registered. Check your email for the join link.',
    'invalid' => 'Please check your name and email and try again.',
    'csrf'    => 'That form had expired. Please try once more.',
    'error'   => 'We could not save that just now. Please email info@prosper-minds.com and we will add you directly.',
    default   => '',
};
$pmNoticeFailed = $pmStatus !== '' && $pmStatus !== 'ok';

// The next session's own designed poster, if it has one. Only the very next
// session counts: showing a later session's poster as "next" because the
// nearer one has none would be wrong, not just incomplete. The poster is also
// the share image, so a link to this page posted on LinkedIn shows what the
// session actually is, instead of the logo.
$pmNext      = $pmSessions[0] ?? null;
$pmPosterUrl = $pmNext !== null ? pmWebinarPosterUrl($pmNext) : '';
$pmPosterSize = $pmPosterUrl !== '' && function_exists('pmEventImageSize')
    ? pmEventImageSize((string) $pmNext['image_path'])
    : null;

pmPageBegin([
    'slug'        => 'webinars',
    'nav'         => 'webinars',
    'title'       => 'PFM Insight Live | Free monthly webinars | Prosperminds',
    'description' => 'PFM Insight Live: free, live, one-hour sessions on public finance analytics and AI for Africa\'s public sector. Last Thursday of every month.',
    'canonical'   => '/webinars.php',
    'og_image'    => $pmPosterUrl !== '' ? $pmPosterUrl : PM_SOCIAL_IMAGE,
]);

$pmTotal    = count($pmSessions);
$pmFormId   = $pmOne !== null ? (int) $pmOne['id'] : 0;
$pmTimeNext = $pmNext !== null ? trim((string) ($pmNext['time_label'] ?: '12:00 to 13:00 EAT')) : '';
?>

<div class="pm-container pm-page-head">

  <div class="pm-split">
    <div>
      <h1 class="pm-h1">PFM Insight Live</h1>
      <p class="pm-lede pm-mt-md">Free, live, one hour, last Thursday of every month, 12:00 to 13:00 EAT.
        By Prosperminds in partnership with CapaBuil Ltd. Each attendee leaves with a tool or template
        and a certificate of attendance.</p>

<?php if ($pmNext !== null): ?>
      <div class="pm-next-session">
        <span class="pm-live pm-live--tag">Next session</span>
        <p class="pm-next-session__title"><?php echo pmEsc((string) $pmNext['title']); ?></p>
        <p class="pm-next-session__when"><?php echo pmEsc(pmWebinarDateLong($pmNext)); ?>, <?php
          echo pmEsc($pmTimeNext); ?>, online</p>
<?php   if ($pmPosterUrl !== ''): ?>
        <?php // Shown whole, never cropped: it carries its own title, date and
              // time. Real dimensions when they can be read, so the page does
              // not jump. ?>
        <div class="pm-next-session__poster">
          <img src="<?php echo pmEsc($pmPosterUrl); ?>"
               alt="Poster: PFM Insight Live, Session <?php echo pmEsc(str_pad((string) $pmNext['session_number'], 2, '0', STR_PAD_LEFT)); ?>, <?php echo pmEsc((string) $pmNext['title']); ?>. <?php echo pmEsc(pmWebinarDateLong($pmNext)); ?>, <?php echo pmEsc((string) $pmNext['time_label']); ?>. Free and online."
<?php     if ($pmPosterSize !== null): ?>
               width="<?php echo $pmPosterSize[0]; ?>" height="<?php echo $pmPosterSize[1]; ?>"
<?php     endif; ?>
               decoding="async">
        </div>
<?php   endif; ?>
      </div>
<?php endif; ?>
    </div>

    <div class="pm-box" id="register">
<?php if ($pmStatus === 'ok'): ?>
      <h2 class="pm-h2 pm-h2--md">You are registered</h2>
      <p class="pm-body pm-mt-sm"><?php echo pmEsc($pmNotice); ?></p>
      <p class="pm-mt-sm"><a class="pm-link" href="/webinars.php#register">Register someone else</a></p>
<?php else: ?>
      <h2 class="pm-h2 pm-h2--md"><?php echo $pmOne !== null ? 'Register for one session' : 'Join the series'; ?></h2>
      <p class="pm-body pm-mt-sm">
<?php   if ($pmOne !== null): ?>
        <?php echo pmEsc((string) $pmOne['title'] . ', ' . pmWebinarDateLong($pmOne)); ?>.
        Prefer every session? <a href="/webinars.php#register">Join the series instead</a>.
<?php   else: ?>
        One sign-up covers all <?php echo (int) $pmTotal; ?> sessions. A reminder arrives before each one.
<?php   endif; ?>
      </p>

<?php   if ($pmNotice !== ''): ?>
      <p class="pm-notice pm-notice--error pm-mt-md" role="alert"><?php echo pmEsc($pmNotice); ?></p>
<?php   endif; ?>

      <form class="pm-stack pm-stack--form pm-mt-md" action="/process-webinar-registration.php" method="post">
        <?php echo formCsrfField(); ?>
        <input type="hidden" name="session_id" value="<?php echo $pmFormId; ?>">
        <input type="hidden" name="return_to" value="<?php echo pmEsc($pmReturnTo); ?>">

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
          <span>Email me the joining link and a reminder before <?php echo $pmOne !== null ? 'this session' : 'each session'; ?>.</span>
        </label>

        <button class="pm-btn" type="submit"><?php echo $pmOne !== null ? 'Register for this session' : 'Join the series'; ?></button>
      </form>
<?php endif; ?>
    </div>
  </div>


  <div class="pm-mt-xl" id="sessions">
    <div class="pm-rule-head pm-rule-head--thin">
      <h2 class="pm-h2 pm-h2--md">All <?php echo (int) $pmTotal; ?> sessions</h2>
      <a class="pm-link" href="/assets/pdfs/pfm-insight-live-2026-2027.pdf" download>Download the calendar (PDF)</a>
    </div>

<?php if ($pmSessions === []): ?>
    <p class="pm-body pm-mt-md">The next session is being confirmed. Join the series above and it will reach you as soon as it is.</p>
<?php else: ?>
    <ol class="pm-sessions">
<?php   foreach ($pmSessions as $pmSession): ?>
      <li>
        <span class="pm-sessions__date"><?php echo pmEsc(pmWebinarDateLong($pmSession)); ?></span>
        <span class="pm-sessions__title"><?php echo pmEsc((string) $pmSession['title']); ?></span>
        <a class="pm-link" href="/webinars.php?session=<?php echo (int) $pmSession['id']; ?>#register">Register<span class="pm-sr-only"> for <?php
          echo pmEsc((string) $pmSession['title']); ?></span></a>
      </li>
<?php   endforeach; ?>
    </ol>
<?php endif; ?>
  </div>

</div>

<div class="pm-section"></div>

<?php pmPageEnd(); ?>
