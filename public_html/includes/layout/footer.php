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

<?php
// Read from site_settings so Settings can change them. A link with no address
// set is not drawn at all, rather than pointing at nothing.
// Same contract as the content layer: a real default, so the link is right
// before anyone opens Settings and survives an empty settings table.
$pmSocials = [
    'social_linkedin' => ['label' => 'LinkedIn',
        'default' => 'https://www.linkedin.com/company/prosper-minds-technologies/',
        'path' => 'M4.98 3.5A2.5 2.5 0 1 1 0 3.5a2.5 2.5 0 0 1 4.98 0zM.22 8.02h4.53V24H.22zM8.34 8.02h4.34v2.18h.06c.6-1.14 2.08-2.34 4.28-2.34 4.58 0 5.42 3.01 5.42 6.92V24h-4.52v-7.31c0-1.74-.03-3.98-2.43-3.98-2.43 0-2.8 1.9-2.8 3.86V24H8.34z'],
    'social_x' => ['label' => 'X',
        'path' => 'M18.9 2h3.68l-8.04 9.19L24 22h-7.4l-5.8-7.58L4.16 22H.47l8.6-9.83L0 2h7.59l5.24 6.93zm-1.29 17.8h2.04L6.48 4.09H4.29z'],
    'social_facebook' => ['label' => 'Facebook',
        'default' => 'https://www.facebook.com/share/1EvKA1GF5w/?mibextid=wwXIfr',
        'path' => 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.96h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z'],
];

$pmSocialLinks = [];
foreach ($pmSocials as $pmKey => $pmMeta) {
    $pmHref = trim((string) getSetting($pmKey, $pmMeta['default'] ?? ''));
    if ($pmHref !== '' && preg_match('#^https?://#i', $pmHref) === 1) {
        $pmSocialLinks[$pmKey] = $pmMeta + ['href' => $pmHref];
    }
}

if ($pmSocialLinks): ?>
        <div class="pm-footer__social">
<?php   foreach ($pmSocialLinks as $pmLink): ?>
          <a href="<?php echo pmEsc($pmLink['href']); ?>" target="_blank" rel="noopener noreferrer"
             aria-label="Prosperminds on <?php echo pmEsc($pmLink['label']); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="<?php echo $pmLink['path']; ?>" fill="currentColor"></path>
            </svg>
          </a>
<?php   endforeach; ?>
        </div>
<?php endif; ?>
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
