<?php
/**
 * One click, from the reminder email, to never be reminded again.
 *
 * The token identifies the row, and the address on that row is what gets
 * suppressed. It answers the same page whether or not the token is known, so
 * the link cannot be used to test whether an address is on file.
 *
 * A GET is enough here. Some mail clients prefetch links, and a prefetch that
 * silently opts somebody out is the safe direction to fail in.
 */

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/resume.php';

$pmRow = pmResumeByToken($pdo, $_GET['t'] ?? null);

if (is_array($pmRow)) {
    pmResumeOptOut($pdo, (string) $pmRow['email']);
}

pmPageBegin([
    'slug'        => 'reminder-optout',
    'nav'         => '',
    'title'       => 'Reminder stopped | Prosperminds',
    'description' => 'You will not receive another reminder about an unfinished registration.',
    'canonical'   => '/registration-reminder-optout.php',
    'noindex'     => true,
]);
?>

<section class="pm-section">
  <div class="pm-container">

    <span class="pm-eyebrow">Reminders</span>
    <h1 class="pm-h1">That is stopped</h1>

    <p class="pm-lede pm-mt-lg pm-measure">We will not send you another reminder about an unfinished
      registration. Nothing else changes, and no booking was made.</p>

    <p class="pm-body pm-mt-md pm-measure">If you did want a place on a course, the calendar is still
      open and you are welcome to register whenever suits you.</p>

    <div class="pm-btn-row pm-mt-lg">
      <a class="pm-btn" href="/events.php">See the calendar</a>
      <a class="pm-btn--link" href="/contact.php">Talk to somebody</a>
    </div>

  </div>
</section>

<?php pmPageEnd(); ?>
