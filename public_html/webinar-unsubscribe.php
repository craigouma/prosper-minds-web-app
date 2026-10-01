<?php
/**
 * One click, from a confirmation or reminder email, to leave PFM Insight
 * Live. Mirrors newsletter-unsubscribe.php: same page whether or not the
 * token is real, and a GET is enough, for the same reasons.
 */

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/webinars.php';

pmWebinarUnsubscribeByToken($pdo, (string) ($_GET['token'] ?? ''));

pmPageBegin([
    'slug'        => 'webinar-unsubscribe',
    'nav'         => '',
    'title'       => 'Unsubscribed | Prosperminds',
    'description' => 'You have been removed from PFM Insight Live.',
    'canonical'   => '/webinar-unsubscribe.php',
    'noindex'     => true,
]);
?>

<section class="pm-section">
  <div class="pm-container">

    <span class="pm-eyebrow">PFM Insight Live</span>
    <h1 class="pm-h1">You are unsubscribed</h1>

    <p class="pm-lede pm-mt-lg pm-measure">You will not receive further reminders for PFM Insight Live.
      Anything else you have registered for on the site is separate and is not affected.</p>

    <p class="pm-body pm-mt-md pm-measure">If this was a mistake, you can register again from the PFM
      Insight Live page at any time.</p>

    <div class="pm-btn-row pm-mt-lg">
      <a class="pm-btn" href="/webinars.php">Back to PFM Insight Live</a>
      <a class="pm-btn--link" href="/contact.php">Talk to somebody</a>
    </div>

  </div>
</section>

<?php pmPageEnd(); ?>
