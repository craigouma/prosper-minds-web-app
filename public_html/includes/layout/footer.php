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
<?php
$pmFootContact = pmContact($pdo ?? null);
$pmWhatsApp = pmWhatsAppUrl();

// Read from site_settings so Settings can change them. A link with no address
// set is not drawn at all, rather than pointing at nothing.
$pmSocials = [
    'social_linkedin' => ['label' => 'LinkedIn',
        'default' => 'https://www.linkedin.com/company/prosper-minds-technologies/'],
    'social_facebook' => ['label' => 'Facebook',
        'default' => 'https://www.facebook.com/share/1EvKA1GF5w/?mibextid=wwXIfr'],
    'social_x' => ['label' => 'X'],
];

$pmSocialLinks = [];
foreach ($pmSocials as $pmKey => $pmMeta) {
    $pmHref = trim((string) getSetting($pmKey, $pmMeta['default'] ?? ''));
    if ($pmHref !== '' && preg_match('#^https?://#i', $pmHref) === 1) {
        $pmSocialLinks[$pmKey] = $pmMeta + ['href' => $pmHref];
    }
}
?>
<footer class="pm-footer" id="newsletter">
  <div class="pm-container">

    <div class="pm-footer__cols">

      <div>
        <img class="pm-footer__brand-logo" src="/assets/images/fisrt-logo.png"
             alt="Prosperminds" width="713" height="183">
        <p class="pm-footer__detail"><?php echo pmContentSafe($pdo, 'global', 'v2_address_html',
              'Twiga Towers, Moi Avenue<br>Nairobi, Kenya', true); ?><br><?php
              echo pmEsc($pmFootContact['hours']); ?></p>
      </div>

      <div>
        <span class="pm-footer__head">Talk to us</span>
        <div class="pm-footer__links">
<?php foreach ($pmFootContact['phones'] as $pmFootPhone): ?>
          <a href="tel:<?php echo pmEsc($pmFootPhone['tel']); ?>"><?php echo pmEsc($pmFootPhone['label']); ?></a>
<?php endforeach; ?>
          <a href="mailto:<?php echo pmEsc($pmFootContact['email']); ?>"><?php echo pmEsc($pmFootContact['email']); ?></a>
<?php if ($pmWhatsApp !== ''): ?>
          <a href="<?php echo pmEsc($pmWhatsApp); ?>" rel="noopener">WhatsApp</a>
<?php endif; ?>
        </div>
      </div>

      <div>
        <form class="pm-footer__news" action="/newsletter-subscribe.php" method="post">
          <?php echo formCsrfField(); ?>
          <input type="hidden" name="return_to" value="<?php echo pmEsc($pmReturnTo); ?>">
          <input type="hidden" name="source" value="footer">

          <div class="pm-honeypot" aria-hidden="true">
            <label for="pm-newsletter-company">Company</label>
            <input type="text" id="pm-newsletter-company" name="company" tabindex="-1" autocomplete="off">
          </div>

          <label class="pm-footer__head" for="pm-newsletter-email">Monthly newsletter</label>
          <input
            class="pm-input"
            type="email"
            id="pm-newsletter-email"
            name="email"
            inputmode="email"
            placeholder="Work email"
            autocomplete="email"
            required
            <?php echo $pmNewsletterFailed ? 'aria-invalid="true"' : ''; ?>
          >
          <button class="pm-btn" type="submit">Subscribe</button>

          <?php // Consent wording sits with the field: the newsletter's lawful
                // basis is consent, so the visitor is told what they agree to,
                // and how to stop, at the point of giving it. ?>
          <p class="pm-footer__consent">
            Course dates and early-bird deadlines only. Unsubscribe from any email.
            <a href="/privacy-policy.php">Privacy policy</a>.
          </p>

<?php if ($pmNewsletterNotice !== ''): ?>
          <p class="pm-notice<?php echo $pmNewsletterFailed ? ' pm-notice--error' : ''; ?>" role="status">
            <?php echo pmEsc($pmNewsletterNotice); ?>
          </p>
<?php endif; ?>
        </form>
      </div>

      <div>
        <span class="pm-footer__head">Prosperminds</span>
        <div class="pm-footer__links">
          <a href="/sponsorship.php">Partner with us</a>
          <a href="/about.php">About</a>
<?php foreach ($pmSocialLinks as $pmLink): ?>
          <a href="<?php echo pmEsc($pmLink['href']); ?>" target="_blank" rel="noopener noreferrer"><?php
            echo pmEsc($pmLink['label']); ?></a>
<?php endforeach; ?>
        </div>
      </div>

    </div>

    <div class="pm-footer__bottom">
      <span>&copy; <?php echo date('Y'); ?> Prosperminds. In partnership with CapaBuil Ltd.</span>
      <a href="/privacy-policy.php">Privacy policy</a>
    </div>

  </div>
</footer>

<script src="<?php echo pmAssetUrl('/assets/js/pm-layout.js'); ?>" defer></script>
<?php foreach ((array) ($pmPage['scripts'] ?? []) as $pmScript): ?>
<script src="<?php echo pmEsc(pmAssetUrl((string) $pmScript)); ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
