<?php
/**
 * PFM Insight Live: the free monthly webinar series.
 *
 * One page, one primary action. The PDF this mirrors leads with "Register
 * once. Get all 13 sessions." and treats the per-session table as proof the
 * series is real and dated, not thirteen separate things to click. The two
 * forms on this page follow that: "Join the series" is the hero action,
 * "register for one session instead" is the quieter, secondary one.
 *
 * Works without JavaScript throughout: both forms are plain POSTs to
 * process-webinar-registration.php, which answers with a redirect back here
 * carrying a status in the query string, read below the same way
 * includes/layout/footer.php reads the newsletter form's result.
 */

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/webinars.php';

$pmSessions = pmWebinarSessionsUpcoming($pdo);

// Which session the "register for one" dropdown should default to: whatever
// the visitor arrived with (from a row's own Register link), or simply the
// soonest upcoming one.
$pmRequestedSession = (int) ($_GET['session'] ?? 0);
$pmSessionIds        = array_column($pmSessions, 'id');
$pmDefaultSessionId  = in_array($pmRequestedSession, $pmSessionIds, true)
    ? $pmRequestedSession
    : (int) ($pmSessions[0]['id'] ?? 0);

// Same return_to computation as footer.php's newsletter form, and for the
// same reason: re-validated at the endpoint, never trusted because this file
// wrote it.
$pmReturnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/webinars.php');
if ($pmReturnTo === '' || $pmReturnTo[0] !== '/' || str_starts_with($pmReturnTo, '//')) {
    $pmReturnTo = '/webinars.php';
}
$pmReturnTo = preg_replace('/([?&])webinar=[^&]*(&|$)/', '$1', $pmReturnTo) ?? '/webinars.php';
$pmReturnTo = preg_replace('/([?&])session=[^&]*(&|$)/', '$1', $pmReturnTo) ?? $pmReturnTo;
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
    'description' => 'PFM Insight Live: 13 free, live, one-hour sessions on public finance analytics and AI for Africa\'s public sector. Last Thursday of every month.',
    'canonical'   => '/webinars.php',
    'og_image'    => $pmPosterUrl !== '' ? $pmPosterUrl : PM_SOCIAL_IMAGE,
]);
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">

    <span class="pm-eyebrow">PFM Insight Live</span>
    <h1 class="pm-h1">Practical intelligence for public finance. Free to join, every month.</h1>

    <p class="pm-lede pm-mt-lg pm-measure">
      13 live, one-hour sessions on public finance analytics and AI for Africa's public sector,
      run by Prosperminds in partnership with CapaBuil Ltd. One hour, once a month. Join from your
      desk or your phone.
    </p>

    <div class="pm-btn-row pm-mt-lg">
      <a class="pm-btn" href="#join">Join the series</a>
      <a class="pm-btn pm-btn--secondary" href="/assets/pdfs/pfm-insight-live-2026-2027.pdf" download>Download the calendar (PDF)</a>
    </div>

    <p class="pm-caption pm-mt-md">Same time, every month: last Thursday, 12:00 to 13:00 EAT.</p>

  </div>
</section>

<?php // ── Next session's poster ─────────────────────────────────────────── ?>
<?php if ($pmPosterUrl !== ''): ?>
<section class="pm-section pm-section--tight" id="next-session">
  <div class="pm-container">

    <span class="pm-eyebrow">Next session</span>

    <?php // Shown whole, never cropped: it carries its own title, date and time.
          // Real dimensions when they can be read, so the page does not jump. ?>
    <img class="pm-mt-md" src="<?php echo pmEsc($pmPosterUrl); ?>"
         alt="Poster: PFM Insight Live, Session <?php echo pmEsc(str_pad((string) $pmNext['session_number'], 2, '0', STR_PAD_LEFT)); ?>, <?php echo pmEsc((string) $pmNext['title']); ?>. <?php echo pmEsc(pmWebinarDateLong($pmNext)); ?>, <?php echo pmEsc((string) $pmNext['time_label']); ?>. Free and online."
<?php if ($pmPosterSize !== null): ?>
         width="<?php echo $pmPosterSize[0]; ?>" height="<?php echo $pmPosterSize[1]; ?>"
<?php endif; ?>
         decoding="async">

    <div class="pm-btn-row pm-mt-md">
      <a class="pm-btn" href="/webinars.php?session=<?php echo (int) $pmNext['id']; ?>#register-one">Register free</a>
      <a class="pm-btn pm-btn--secondary" href="#join">Join the whole series</a>
    </div>

  </div>
</section>
<?php endif; ?>

<?php // ── What you take home ───────────────────────────────────────────── ?>
<section class="pm-section pm-section--ruled">
  <div class="pm-container">
    <div class="pm-grid pm-grid--ruled pm-grid--3">
      <div class="pm-cell">
        <span class="pm-stat__value">1</span>
        <span class="pm-stat__label">Tool or template to use at your desk</span>
      </div>
      <div class="pm-cell">
        <span class="pm-stat__value">1</span>
        <span class="pm-stat__label">Certificate of attendance for your CPD records</span>
      </div>
      <div class="pm-cell">
        <span class="pm-stat__value">0</span>
        <span class="pm-stat__label">Fees. Every session is free, no hidden costs</span>
      </div>
    </div>
  </div>
</section>

<?php // ── Upcoming sessions ─────────────────────────────────────────────── ?>
<section class="pm-section pm-section--tight" id="sessions">
  <div class="pm-container">

    <h2 class="pm-h2">Upcoming sessions</h2>

<?php if ($pmSessions === []): ?>
    <p class="pm-body pm-mt-lg">The next session is being confirmed. Join the series below and it will reach you as soon as it is.</p>
<?php else: ?>
    <ul class="pm-listing pm-mt-md">
<?php foreach ($pmSessions as $pmSession): ?>
      <li>
        <div class="pm-listing__row pm-listing__row--no-banner">

          <div class="pm-listing__date">
            <span class="pm-listing__range"><?php echo pmEsc(date('j M', strtotime((string) $pmSession['session_date']))); ?></span>
            <span class="pm-label"><?php echo pmEsc(strtoupper(date('Y', strtotime((string) $pmSession['session_date'])))); ?></span>
          </div>

          <div>
            <span class="pm-caption">Session <?php echo pmEsc(str_pad((string) $pmSession['session_number'], 2, '0', STR_PAD_LEFT)); ?></span>
            <h3 class="pm-h3"><?php echo pmEsc((string) $pmSession['title']); ?></h3>
<?php if ((string) $pmSession['description'] !== ''): ?>
            <p class="pm-body pm-mt-sm"><?php echo pmEsc((string) $pmSession['description']); ?></p>
<?php endif; ?>
<?php if ((string) $pmSession['best_for'] !== ''): ?>
            <div class="pm-listing__meta pm-mt-sm">
              <span class="pm-caption">Best for: <?php echo pmEsc((string) $pmSession['best_for']); ?></span>
            </div>
<?php endif; ?>
          </div>

          <div class="pm-listing__side">
            <span class="pm-label pm-label--green">Free &middot; Online</span>
            <a class="pm-btn--link pm-mt-sm" href="/webinars.php?session=<?php echo (int) $pmSession['id']; ?>#register-one">
              Register
              <span class="pm-sr-only"> for <?php echo pmEsc((string) $pmSession['title']); ?></span>
            </a>
          </div>

        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>

  </div>
</section>

<?php // ── Who should join ───────────────────────────────────────────────── ?>
<section class="pm-section pm-section--ruled">
  <div class="pm-container">
    <h2 class="pm-h2">Who should join?</h2>
    <p class="pm-body pm-mt-md pm-measure">Anyone who plans, spends, records, checks or reports on public money.
      You do not need to be good with computers. We start simple.</p>

    <div class="pm-grid pm-grid--2 pm-mt-lg">
      <ul class="pm-list">
        <li>Budget and planning officers</li>
        <li>Revenue and tax officers</li>
        <li>Internal and external auditors</li>
        <li>ICT and data staff in government</li>
      </ul>
      <ul class="pm-list">
        <li>Accountants and finance officers</li>
        <li>Procurement and supply staff</li>
        <li>Treasury, cash and debt teams</li>
        <li>Leaders, board and committee members</li>
      </ul>
    </div>
  </div>
</section>

<?php // ── Join the series (primary) ─────────────────────────────────────── ?>
<section class="pm-section pm-section--tight" id="join">
  <div class="pm-container">

    <h2 class="pm-h2">Register once. Get all 13 sessions.</h2>
    <p class="pm-body pm-mt-md pm-measure">Sign up one time and we will send you the join link now, and a
      reminder before every session. You can leave the series at any time.</p>

<?php if ($pmNotice !== ''): ?>
    <p class="pm-notice<?php echo $pmNoticeFailed ? ' pm-notice--error' : ''; ?> pm-mt-md" role="status">
      <?php echo pmEsc($pmNotice); ?>
    </p>
<?php endif; ?>

    <form class="pm-mt-lg" action="/process-webinar-registration.php" method="post" novalidate>
      <?php echo formCsrfField(); ?>
      <input type="hidden" name="session_id" value="0">
      <input type="hidden" name="return_to" value="<?php echo pmEsc($pmReturnTo); ?>">

      <div class="pm-sr-only" aria-hidden="true">
        <label for="pm-webinar-join-company">Company</label>
        <input type="text" id="pm-webinar-join-company" name="company" tabindex="-1" autocomplete="off">
      </div>

      <div class="pm-grid pm-grid--3">
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-join-name">Full name</label>
          <input class="pm-input" type="text" id="pm-webinar-join-name" name="name" autocomplete="name" required>
        </div>
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-join-email">Email</label>
          <input class="pm-input" type="email" id="pm-webinar-join-email" name="email" autocomplete="email" required>
        </div>
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-join-organization">Institution</label>
          <input class="pm-input" type="text" id="pm-webinar-join-organization" name="organization" autocomplete="organization">
        </div>
      </div>

      <label class="pm-check pm-mt-md" for="pm-webinar-join-consent">
        <input type="checkbox" id="pm-webinar-join-consent" name="consent" value="yes" required>
        <span>I am happy to be emailed the join link and a reminder before each PFM Insight Live session.</span>
      </label>

      <button class="pm-btn pm-mt-md" type="submit">Join the series</button>
    </form>

  </div>
</section>

<?php // ── Register for one session instead (secondary) ─────────────────── ?>
<?php if ($pmSessions !== []): ?>
<section class="pm-section pm-section--ruled" id="register-one">
  <div class="pm-container">

    <h2 class="pm-h3 pm-h3--caps">Or register for one session only</h2>

    <form class="pm-mt-md" action="/process-webinar-registration.php" method="post" novalidate>
      <?php echo formCsrfField(); ?>
      <input type="hidden" name="return_to" value="<?php echo pmEsc($pmReturnTo); ?>">

      <div class="pm-sr-only" aria-hidden="true">
        <label for="pm-webinar-session-company">Company</label>
        <input type="text" id="pm-webinar-session-company" name="company" tabindex="-1" autocomplete="off">
      </div>

      <div class="pm-field">
        <label class="pm-field__label" for="pm-webinar-session-select">Session</label>
        <select class="pm-select" id="pm-webinar-session-select" name="session_id">
<?php foreach ($pmSessions as $pmSession): ?>
          <option value="<?php echo (int) $pmSession['id']; ?>" <?php echo (int) $pmSession['id'] === $pmDefaultSessionId ? 'selected' : ''; ?>>
            <?php echo pmEsc(date('j F Y', strtotime((string) $pmSession['session_date'])) . ': ' . (string) $pmSession['title']); ?>
          </option>
<?php endforeach; ?>
        </select>
      </div>

      <div class="pm-grid pm-grid--3 pm-mt-md">
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-session-name">Full name</label>
          <input class="pm-input" type="text" id="pm-webinar-session-name" name="name" autocomplete="name" required>
        </div>
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-session-email">Email</label>
          <input class="pm-input" type="email" id="pm-webinar-session-email" name="email" autocomplete="email" required>
        </div>
        <div class="pm-field">
          <label class="pm-field__label" for="pm-webinar-session-organization">Institution</label>
          <input class="pm-input" type="text" id="pm-webinar-session-organization" name="organization" autocomplete="organization">
        </div>
      </div>

      <label class="pm-check pm-mt-md" for="pm-webinar-session-consent">
        <input type="checkbox" id="pm-webinar-session-consent" name="consent" value="yes" required>
        <span>I am happy to be emailed the join link and a reminder for this session.</span>
      </label>

      <button class="pm-btn pm-btn--secondary pm-mt-md" type="submit">Register for this session</button>
    </form>

  </div>
</section>
<?php endif; ?>

<?php // ── Our ask to public sector leaders ──────────────────────────────── ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">
    <h2 class="pm-h2">Our ask to public sector leaders</h2>
    <p class="pm-body pm-mt-md pm-measure">To Accounting Officers, CFOs, Heads of Audit and Heads of
      Department: two small steps, repeated each month, build a team that uses data with confidence.</p>
    <ol class="pm-list pm-mt-lg">
      <li><strong>Send a team to every session.</strong> Pick three to five staff. Ask them to join each month and attend together.</li>
      <li><strong>Hold a 30 minute learn and share the next week.</strong> Let the team show one thing they learned and one way to use it in your office.</li>
    </ol>
  </div>
</section>

<?php // ── Become a Champion ─────────────────────────────────────────────── ?>
<section class="pm-section pm-section--ruled">
  <div class="pm-container">
    <span class="pm-eyebrow">Volunteer</span>
    <h2 class="pm-h2">Become a PFM Insight Champion</h2>
    <p class="pm-body pm-mt-md pm-measure">Do you care about how public money is managed? Help others in
      your country learn with us. Champions share PFM Insight Live with their networks, invite colleagues
      to watch together, and tell us which topics matter most in their country.</p>
    <p class="pm-body pm-mt-md">To volunteer, email
      <a class="pm-btn--link" href="mailto:Lydia@prosper-minds.com?subject=Champion">Lydia@prosper-minds.com</a>
      with your name, country and role, subject line "Champion".</p>
  </div>
</section>

<?php pmPageEnd(); ?>
