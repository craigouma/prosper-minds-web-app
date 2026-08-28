<?php
/**
 * Site footer for every rebuilt page, including the newsletter signup.
 *
 * Included by pmPageEnd() in includes/layout/page.php, which is the only
 * supported way to reach it — it expects $pmPage and $pdo to be in scope.
 *
 * Structure, taken from the approved prototype's footer:
 *   top band   newsletter: one line of copy, an email field, a green submit
 *   columns    brand + tagline | Site | Nairobi HQ | Contact
 *   bottom     rule and copyright
 *
 * NEWSLETTER
 * ----------
 * Footer-only and deliberately not a popup or modal. Revision 4 of the design
 * brief is explicit about why: a modal "conflicts with the brand's 'never
 * playful, strategic not sales-heavy' tone".
 *
 * It is a real form posting to a real endpoint, which is new — the current
 * live footer's field has no action and no method and silently discards
 * everything typed into it (PROJECT.md section 5, Priority 3).
 *
 * The result comes back as ?newsletter=<status> on the same page, so the
 * response works with JavaScript off. The status values are the ones
 * newsletter-subscribe.php redirects with, and anything unrecognised shows
 * nothing rather than guessing.
 */

/** @var array<string, mixed> $pmPage */
/** @var PDO|null $pdo */

// Where newsletter-subscribe.php should send the visitor back to. Path only,
// and re-validated at the endpoint — never trust it just because this file
// wrote it.
$pmReturnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/');
if ($pmReturnTo === '' || $pmReturnTo[0] !== '/' || str_starts_with($pmReturnTo, '//')) {
    $pmReturnTo = '/';
}
// Drop any newsletter status already on the URL so repeat submits do not stack
// the parameter up.
$pmReturnTo = preg_replace('/([?&])newsletter=[^&]*(&|$)/', '$1', $pmReturnTo) ?? '/';
$pmReturnTo = rtrim($pmReturnTo, '?&');

$pmNewsletterStatus = (string) ($_GET['newsletter'] ?? '');
$pmNewsletterNotice = match ($pmNewsletterStatus) {
    'ok'      => 'Confirmed. A note goes out whenever new dates are published.',
    'invalid' => 'That does not look like a valid email address. Please check it and try again.',
    'csrf'    => 'That form had expired. Please try once more.',
    'error'   => 'We could not save that just now. Please try again shortly.',
    default   => '',
};
$pmNewsletterFailed = $pmNewsletterStatus !== '' && $pmNewsletterStatus !== 'ok';
?>
<footer class="pm-footer" id="newsletter">
  <div class="pm-container">

    <div class="pm-footer__newsletter">
      <div class="pm-footer__newsletter-copy">
        <span class="pm-footer__col-head">Newsletter</span>
        <p><?php echo pmContentSafe($pdo, 'global', 'newsletter_promise',
              'Course dates and early-bird deadlines, sent when they are confirmed.'); ?></p>
      </div>

      <form class="pm-form-inline" action="/newsletter-subscribe.php" method="post">
        <?php echo formCsrfField(); ?>
        <input type="hidden" name="return_to" value="<?php echo pmEsc($pmReturnTo); ?>">
        <input type="hidden" name="source" value="footer">

        <?php // Honeypot. A real visitor never sees or fills this; a naive bot
              // fills every field it finds. Left unnamed in the UI on purpose. ?>
        <div class="pm-sr-only" aria-hidden="true">
          <label for="pm-newsletter-company">Company</label>
          <input type="text" id="pm-newsletter-company" name="company" tabindex="-1" autocomplete="off">
        </div>

        <div class="pm-field">
          <label class="pm-field__label" for="pm-newsletter-email">Email address</label>
          <input
            class="pm-input"
            type="email"
            id="pm-newsletter-email"
            name="email"
            placeholder="name@institution.go.ke"
            autocomplete="email"
            required
            <?php echo $pmNewsletterFailed ? 'aria-invalid="true"' : ''; ?>
          >
        </div>

        <button class="pm-btn" type="submit">Subscribe</button>

<?php if ($pmNewsletterNotice !== ''): ?>
        <p class="pm-notice<?php echo $pmNewsletterFailed ? ' pm-notice--error' : ''; ?>" role="status">
          <?php echo pmEsc($pmNewsletterNotice); ?>
        </p>
<?php endif; ?>
      </form>
    </div>

    <div class="pm-footer__cols">

      <div>
        <div class="pm-brand">
          <svg class="pm-brand__mark" width="22" height="26" viewBox="0 0 26 30" role="img" aria-label="Prosperminds">
            <path d="M13 1.6 24.4 5.6v9.6c0 7-4.9 11.6-11.4 13.1C6.5 26.8 1.6 22.2 1.6 15.2V5.6L13 1.6Z" fill="none" stroke="#00BF63" stroke-width="1.5"></path>
            <circle cx="13" cy="12.2" r="4.1" fill="none" stroke="#fff" stroke-width="1.1"></circle>
            <path d="M13 16.3v4.2M8.9 12.2H4.6M21.4 12.2h-4.3" stroke="#fff" stroke-width="1.1" stroke-linecap="round"></path>
            <circle cx="13" cy="12.2" r="1.2" fill="#00BF63"></circle>
          </svg>
          <span class="pm-brand__word">Prosperminds</span>
        </div>
        <p class="pm-footer__tagline"><?php echo pmContentSafe($pdo, 'global', 'tagline',
              'Protecting and growing the mind to achieve prosperity.'); ?></p>
      </div>

      <div>
        <span class="pm-footer__col-head">Site</span>
        <div class="pm-footer__links">
<?php foreach (pmNavItems() as $pmFootKey => $pmFootItem): ?>
          <a href="<?php echo pmEsc($pmFootItem['href']); ?>"><?php echo pmEsc($pmFootItem['label']); ?></a>
<?php endforeach; ?>
        </div>
      </div>

      <div>
        <span class="pm-footer__col-head">Nairobi HQ</span>
        <p class="pm-footer__detail"><?php echo pmContentSafe($pdo, 'global', 'address_html',
              'Twiga Towers, Moi Avenue<br>Nairobi, Kenya<br>Mon to Fri, 8am to 5pm', true); ?></p>
      </div>

      <div>
        <span class="pm-footer__col-head">Contact</span>
        <p class="pm-footer__detail">
          <a href="mailto:info@prosper-minds.com">info@prosper-minds.com</a><br>
          <a href="tel:+254740582302">+254 740 582302</a><br>
          <a href="tel:+254741174909">+254 741 174909</a>
        </p>
      </div>

    </div>

    <div class="pm-footer__bottom">
      <span>&copy; <?php echo date('Y'); ?> Prosperminds. All rights reserved.</span>
    </div>

  </div>
</footer>

<script src="/assets/js/pm-layout.js" defer></script>
</body>
</html>
