<?php
/**
 * One click, from the newsletter footer, to stop receiving it.
 *
 * Answers the same page whether or not the token is known, so the link cannot
 * be used to test whether an address is on the list. A GET is enough: some mail
 * clients prefetch links, and a prefetch that unsubscribes somebody is the safe
 * direction to fail in.
 */

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/newsletter.php';
require_once __DIR__ . '/includes/campaigns.php';

pmCampaignUnsubscribe($pdo, $_GET['t'] ?? null);

pmPageBegin([
    'slug'        => 'newsletter-unsubscribe',
    'nav'         => '',
    'title'       => 'Unsubscribed | Prosperminds',
    'description' => 'You have been removed from the Prosperminds mailing list.',
    'canonical'   => '/newsletter-unsubscribe.php',
    'noindex'     => true,
]);
?>

<section class="pm-section">
  <div class="pm-container">

    <span class="pm-eyebrow">Mailing list</span>
    <h1 class="pm-h1">You are unsubscribed</h1>

    <p class="pm-lede pm-mt-lg pm-measure">You will not receive the newsletter again. Course confirmations
      and invoices are separate and are not affected, so anything you have already booked still reaches you.</p>

    <p class="pm-body pm-mt-md pm-measure">If this was a mistake, the form in the footer of any page will
      put you back on the list.</p>

    <div class="pm-btn-row pm-mt-lg">
      <a class="pm-btn" href="/events.php">See the calendar</a>
      <a class="pm-btn--link" href="/contact.php">Talk to somebody</a>
    </div>

  </div>
</section>

<?php pmPageEnd(); ?>
