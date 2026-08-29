<?php
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

        <!-- Consent wording sits with the field, not buried in the policy. The
             newsletter's lawful basis is consent (Kenya DPA 2019 / GDPR), so the
             visitor has to be told what they are agreeing to, and how to stop,
             at the point of giving it. -->
        <p class="pm-form-inline__consent">
          We will use your address only to send course dates and early bird
          deadlines. You can unsubscribe from any email. See our
          <a href="/privacy-policy.php">privacy policy</a>.
        </p>

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
          <!-- Same real logo file as the header. It already carries an alpha
               channel, and the wordmark and shield are both green, so it reads
               correctly on this black surface without a separate dark variant. -->
          <img class="pm-brand__logo" src="/assets/images/fisrt-logo.png"
               alt="Prosperminds" width="713" height="183">
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
      <a href="/privacy-policy.php">Privacy policy</a>
    </div>

  </div>
</footer>

<script src="/assets/js/pm-layout.js" defer></script>
<?php // Per-page scripts, all deferred, in the order the page listed them.
      // defer preserves execution order between external scripts, which is what
      // lets contact.php list a library and then the file that uses it.
      foreach ((array) ($pmPage['scripts'] ?? []) as $pmScript): ?>
<script src="<?php echo pmEsc((string) $pmScript); ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
