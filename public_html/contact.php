<?php
/**
 * Contact page.
 *
 * New. "Contact" was an anchor down the homepage on the live site, and the form
 * at the end of that anchor was a set of inputs inside a <form> with no action,
 * no method and no name attributes. It posted nowhere. Every enquiry ever typed
 * into it was discarded by the browser before it left the page. PROJECT.md
 * section 5, Priority 3 records that nobody had confirmed whether it delivered
 * anywhere; it did not.
 *
 * This form posts to contact-submit.php, which stores the message in
 * contact_messages before it attempts to notify anyone, and reports success on
 * the stored row rather than on the email about it. See the header of
 * includes/contact.php for why that distinction is the whole point.
 *
 * THE FLASH
 * ---------
 * contact-submit.php answers a browser with a 303 back to this page and puts
 * the outcome in the session rather than the query string, because a failed
 * submission has to hand the visitor their own paragraph back and a URL is the
 * wrong place for someone's enquiry. It is read once here and cleared
 * immediately, so a refresh does not replay it and a stale message cannot
 * appear on a later visit.
 *
 * WHY THERE IS NO EMBEDDED MAP
 * ----------------------------
 * A mapping provider's iframe is a cross-border third-party request setting its
 * own cookies, on the one page whose entire job is a form covered by the Kenya
 * Data Protection Act 2019 and GDPR. It would need a consent banner to be
 * lawful and would be the only thing on the site that did. The brief also rules
 * out stock imagery in the same breath. So the location is a flat schematic in
 * the brand palette plus written directions, which costs one request less and
 * needs no consent at all.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';

// Read once, clear immediately. Anything still in the session after this line
// would replay on the next visit to a page a visitor may have shared a device
// to reach.
$pmFlash = $_SESSION['pm_contact_flash'] ?? null;
unset($_SESSION['pm_contact_flash']);

$pmFlashOk      = is_array($pmFlash) && !empty($pmFlash['success']);
$pmFlashMessage = is_array($pmFlash) ? (string) ($pmFlash['message'] ?? '') : '';
$pmErrors       = is_array($pmFlash) && is_array($pmFlash['errors'] ?? null) ? $pmFlash['errors'] : [];
$pmValues       = is_array($pmFlash) && is_array($pmFlash['values'] ?? null) ? $pmFlash['values'] : [];

/** The visitor's own words back in the field they typed them into. */
$pmOld = static function (string $field) use ($pmValues): string {
    return pmEsc((string) ($pmValues[$field] ?? ''));
};

/** The error for one field, or '' if it passed. */
$pmError = static function (string $field) use ($pmErrors): string {
    return (string) ($pmErrors[$field] ?? '');
};

pmPageBegin([
    'slug'        => 'contact',
    'nav'         => 'contact',
    'title'       => pmContent($pdo, 'contact', 'meta_title', 'Contact | Prosperminds'),
    'description' => pmContent($pdo, 'contact', 'meta_description', 'Talk to the Prosperminds programme office in Nairobi about course dates, group registrations and consolidated quotes.'),
    'canonical'   => '/contact.php',
]);
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">
    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'contact', 'hero_eyebrow', 'Contact'); ?></span>
    <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'contact', 'hero_title',
      'Talk to the programme office'); ?></h1>
  </div>
</section>


<?php // ── The details themselves ────────────────────────────────────────── ?>
<?php // A light section, per the prototype's treatment note: light surfaces
      // carry information a delegate has to read closely, which is exactly what
      // an address and a phone number are. ?>
<section class="pm-section pm-section--tight">
  <div class="pm-container">
    <h2 class="pm-sr-only">How to reach us</h2>
    <div class="pm-grid pm-grid--ruled pm-grid--4">

      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'contact', 'office_title', 'Head office'); ?></span>
        <p class="pm-body"><?php echo pmContentSafe($pdo, 'contact', 'address_html',
          'Twiga Towers, Moi Avenue<br>Nairobi, Kenya', true); ?></p>
      </div>

      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'contact', 'phone_title', 'Telephone'); ?></span>
        <p class="pm-body">
          <a href="tel:+254740582302"><?php echo pmContentSafe($pdo, 'global', 'phone_primary', '+254 740 582302'); ?></a><br>
          <a href="tel:+254741174909"><?php echo pmContentSafe($pdo, 'global', 'phone_secondary', '+254 741 174909'); ?></a>
        </p>
      </div>

      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'contact', 'email_title', 'Email'); ?></span>
        <p class="pm-body">
          <?php $pmEmail = pmContent($pdo, 'global', 'email', 'info@prosper-minds.com'); ?>
          <a href="mailto:<?php echo pmEsc($pmEmail); ?>"><?php echo pmEsc($pmEmail); ?></a>
        </p>
      </div>

      <div class="pm-cell">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'contact', 'hours_title', 'Office hours'); ?></span>
        <p class="pm-body"><?php echo pmContentSafe($pdo, 'contact', 'hours_value_html',
          'Monday to Friday<br>8am to 5pm EAT', true); ?></p>
      </div>

    </div>
  </div>
</section>


<?php // ── The enquiry form ──────────────────────────────────────────────── ?>
<section class="pm-section" id="enquiry">
  <div class="pm-container pm-row">

    <div class="pm-row__main">
      <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'contact', 'form_title', 'Send an enquiry'); ?></h2>
      <p class="pm-body pm-mt-md"><?php echo pmContentSafe($pdo, 'contact', 'form_intro',
        'The programme office replies within one working day. For group registrations of four or more delegates, mention the number and we will issue a consolidated quote.'); ?></p>

<?php if ($pmFlashMessage !== ''): ?>
      <?php // role="status" rather than role="alert": the visitor has just
            // pressed a button and is looking at the page, so this needs to be
            // announced without interrupting whatever else is being read. ?>
      <p class="pm-notice<?php echo $pmFlashOk ? '' : ' pm-notice--error'; ?> pm-mt-md" role="status">
        <?php echo pmEsc($pmFlashMessage); ?>
      </p>
<?php endif; ?>

      <form class="pm-mt-lg" action="/contact-submit.php" method="post" novalidate>
        <?php echo formCsrfField(); ?>

        <?php // Honeypot. Out of the tab order and out of the accessibility
              // tree, so a person never meets it; a bot that fills every input
              // it finds does. Same treatment as the newsletter form. ?>
        <div class="pm-sr-only" aria-hidden="true">
          <label for="pm-contact-website">Website</label>
          <input type="text" id="pm-contact-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="pm-grid pm-grid--3">

          <div class="pm-field">
            <label class="pm-field__label" for="pm-contact-name"><?php
              echo pmContentSafe($pdo, 'contact', 'form_label_name', 'Full name'); ?></label>
            <input class="pm-input" type="text" id="pm-contact-name" name="name"
                   value="<?php echo $pmOld('name'); ?>" autocomplete="name" required
                   <?php if ($pmError('name') !== ''): ?>aria-invalid="true" aria-describedby="pm-contact-name-error"<?php endif; ?>>
<?php if ($pmError('name') !== ''): ?>
            <span class="pm-field__hint" id="pm-contact-name-error"><?php echo pmEsc($pmError('name')); ?></span>
<?php endif; ?>
          </div>

          <div class="pm-field">
            <label class="pm-field__label" for="pm-contact-organisation"><?php
              echo pmContentSafe($pdo, 'contact', 'form_label_organisation', 'Institution'); ?></label>
            <input class="pm-input" type="text" id="pm-contact-organisation" name="organisation"
                   value="<?php echo $pmOld('organisation'); ?>" autocomplete="organization">
          </div>

          <div class="pm-field">
            <label class="pm-field__label" for="pm-contact-email"><?php
              echo pmContentSafe($pdo, 'contact', 'form_label_email', 'Email'); ?></label>
            <input class="pm-input" type="email" id="pm-contact-email" name="email"
                   value="<?php echo $pmOld('email'); ?>" autocomplete="email" required
                   <?php if ($pmError('email') !== ''): ?>aria-invalid="true" aria-describedby="pm-contact-email-error"<?php endif; ?>>
<?php if ($pmError('email') !== ''): ?>
            <span class="pm-field__hint" id="pm-contact-email-error"><?php echo pmEsc($pmError('email')); ?></span>
<?php endif; ?>
          </div>

          <div class="pm-field">
            <label class="pm-field__label" for="pm-contact-phone"><?php
              echo pmContentSafe($pdo, 'contact', 'form_label_phone', 'Phone'); ?></label>
            <input class="pm-input" type="tel" id="pm-contact-phone" name="phone"
                   value="<?php echo $pmOld('phone'); ?>" autocomplete="tel">
          </div>

        </div>

        <div class="pm-field pm-mt-md">
          <label class="pm-field__label" for="pm-contact-message"><?php
            echo pmContentSafe($pdo, 'contact', 'form_label_message', 'Enquiry'); ?></label>
          <span class="pm-field__hint" id="pm-contact-message-hint"><?php
            echo pmContentSafe($pdo, 'contact', 'form_hint_message',
              'Which school or pillar are you asking about?'); ?></span>
          <textarea class="pm-textarea" id="pm-contact-message" name="message" rows="6" required
                    aria-describedby="pm-contact-message-hint<?php echo $pmError('message') !== '' ? ' pm-contact-message-error' : ''; ?>"
                    <?php if ($pmError('message') !== ''): ?>aria-invalid="true"<?php endif; ?>><?php echo $pmOld('message'); ?></textarea>
<?php if ($pmError('message') !== ''): ?>
          <span class="pm-field__hint" id="pm-contact-message-error"><?php echo pmEsc($pmError('message')); ?></span>
<?php endif; ?>
        </div>

        <p class="pm-caption pm-mt-sm"><?php echo pmContentSafe($pdo, 'contact', 'form_optional_note',
          'Institution and phone are optional.'); ?></p>

        <?php // The lawful basis for holding an enquiry is answering it, and
              // saying so at the point of collection is what makes that honest
              // rather than something a visitor has to go and look up. ?>
        <p class="pm-caption pm-mt-sm"><?php echo pmContentSafe($pdo, 'contact', 'form_consent_html',
          'We use these details only to answer your enquiry. See our <a href="/privacy-policy.php">privacy policy</a>.', true); ?></p>

        <div class="pm-btn-row pm-mt-lg">
          <button class="pm-btn" type="submit"><?php echo pmContentSafe($pdo, 'contact', 'form_submit_label',
            'Send enquiry'); ?></button>
        </div>
      </form>
    </div>


    <?php // ── Where the office is ─────────────────────────────────────── ?>
    <div class="pm-row__side">
      <div class="pm-card pm-card--flush">

        <div class="pm-figure">
          <?php // A flat schematic, not a map tile and not a photograph. See
                // the header comment for why there is no embedded map. It
                // carries no information the written directions below do not,
                // so it is hidden from assistive technology rather than given
                // an alt text that would only repeat them. ?>
          <svg viewBox="0 0 320 220" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid slice">
            <g stroke="#dcdcdc" stroke-width="1" fill="none">
              <path d="M0 55h320M0 110h320M0 165h320M70 0v220M150 0v220M230 0v220"></path>
            </g>
            <path d="M60 110h90v50h170" stroke="#00BF63" stroke-width="2" fill="none"></path>
            <rect x="138" y="98" width="24" height="24" stroke="#00BF63" stroke-width="2" fill="none"></rect>
            <circle cx="150" cy="110" r="4" fill="#00BF63"></circle>
          </svg>
          <span class="pm-figure__tag"><?php echo pmContentSafe($pdo, 'contact', 'map_label',
            'Moi Avenue, Nairobi'); ?></span>
        </div>

        <div class="pm-cell">
          <h2 class="pm-h3 pm-h3--caps"><?php echo pmContentSafe($pdo, 'contact', 'directions_title',
            'Getting here'); ?></h2>
          <p class="pm-body"><?php echo pmContentSafe($pdo, 'contact', 'directions_body',
            'Twiga Towers sits on Moi Avenue in the central business district, a ten minute walk from the railway station and served by matatu routes along Tom Mboya Street. Visitor parking is available on the lower level.'); ?></p>
        </div>

      </div>
    </div>

  </div>
</section>

<?php pmPageEnd(); ?>
