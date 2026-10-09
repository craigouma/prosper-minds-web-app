<?php
require_once __DIR__ . '/includes/layout/page.php';

// ── The offer, from page_content, with the live page's own copy as the
//    fallback so an unreachable table still renders a complete offer ─────────
$pmTiers = pmContentJson($pdo, 'sponsorship', 'tiers', [
    ['key' => 'platinum', 'name' => 'Platinum', 'price' => '$15,000', 'slots' => '3 slots remaining', 'benefits' => [
        'Keynote and plenary speaking slot',
        'Branding across every platform: digital, print and press',
        'Sponsor video aired daily',
        'Five VIP passes',
        'Logo on delegate lanyards and bags',
        'VIP roundtable with government leaders',
        'Full page advertisement in the programme and the post event report',
        'Prime exhibition space',
    ]],
    ['key' => 'gold', 'name' => 'Gold', 'price' => '$7,500', 'slots' => '5 slots remaining', 'benefits' => [
        'Host and brand a data or IPSAS theme session',
        'Three VIP passes',
        'Branding on the event app and session screens',
        'Mid tier exhibition space',
        'Half page advertisement in the programme',
        'Joint press feature with Prosperminds',
        'Speaking role',
        'Social media spotlight campaign',
    ]],
    ['key' => 'silver', 'name' => 'Silver', 'price' => '$4,000', 'slots' => '10 slots remaining', 'benefits' => [
        'Speaking role',
        'Three delegate passes',
        'Logo on key branding points',
        'Half page advertisement in the programme',
        'Featured in Prosperminds publications',
        'Website branding',
        'Social media spotlight',
        'Exhibition space',
    ]],
    ['key' => 'bronze', 'name' => 'Supporting Sponsor', 'price' => '$2,000', 'slots' => '10 slots remaining', 'benefits' => [
        'Speaker or moderator role',
        'Three delegate passes',
        'Logo in the programme',
        'Website branding',
        'Social media spotlight',
        'Exhibition space',
    ]],
]);

$pmPackages = pmContentJson($pdo, 'sponsorship', 'packages', [
    ['key' => 'gala-dinner', 'name' => 'Gala Dinner', 'price' => '$1,000', 'slots' => '2 slots remaining', 'benefits' => [
        'Exclusive gala dinner branding',
        'Address guests at the dinner',
        'Logo in the entertainment zones',
        'Two passes and website branding',
    ]],
    ['key' => 'digital-experience', 'name' => 'Digital Experience', 'price' => '$1,000', 'slots' => '2 slots remaining', 'benefits' => [
        'Sponsored push notifications',
        'Logo on the session screens',
        'One pass and website branding',
        'Exhibition space',
    ]],
    ['key' => 'exhibitor', 'name' => 'Exhibitor', 'price' => '$1,000', 'slots' => '10 slots remaining', 'benefits' => [
        'Named in the event materials',
        'One pass and website branding',
        'Exhibition space',
    ]],
]);

$pmAddons = pmContentJson($pdo, 'sponsorship', 'addons', [
    ['name' => 'Lanyard sponsorship', 'price' => '$500', 'slots' => '3 slots remaining', 'benefit' => 'Your mark on every delegate lanyard for the full five days.'],
    ['name' => 'Delegate bag',        'price' => '$500', 'slots' => '4 slots remaining', 'benefit' => 'Branding on the bag issued to each delegate at registration.'],
    ['name' => 'Conference Wi-Fi',    'price' => '$500', 'slots' => '2 slots remaining', 'benefit' => 'Named on the network, with branding on the splash page and the daily access card.'],
    ['name' => 'Mobile app',          'price' => '$500', 'slots' => '2 slots remaining', 'benefit' => 'Exclusive branding on the agenda app splash and the session reminders.'],
    ['name' => 'Water station',       'price' => '$500', 'slots' => '2 slots remaining', 'benefit' => 'Branding at the refreshment points across the venue.'],
]);

// ── The tier dropdown, and the deep link that pre-selects it ────────────────
//
// Each tier card carries an Enquire button pointing at
// ?tier=<key>#enquire. Without it, somebody who clicked Platinum has to say so
// again in the form, and if they do not bother the single most useful
// qualifying signal on a fifteen thousand dollar product is lost.
//
// Resolved SERVER SIDE, so it works with scripts off, the same as the calendar
// filter on events.php.
//
// The option VALUE is what process-sponsorship.php puts in the email, so it is
// the human sentence. The KEY is what the URL carries, and an incoming ?tier=
// is matched against the known keys and otherwise ignored, so a hand edited
// query string can never reach the markup.
$pmTierOptions = [];

foreach ($pmTiers as $pmTier) {
    $pmKey = trim((string) ($pmTier['key'] ?? ''));
    if ($pmKey !== '') {
        $pmTierOptions[$pmKey] = trim((string) ($pmTier['name'] ?? '')) . ', ' . trim((string) ($pmTier['price'] ?? ''));
    }
}

foreach ($pmPackages as $pmPackage) {
    $pmKey = trim((string) ($pmPackage['key'] ?? ''));
    if ($pmKey !== '') {
        $pmTierOptions[$pmKey] = trim((string) ($pmPackage['name'] ?? '')) . ', ' . trim((string) ($pmPackage['price'] ?? ''));
    }
}

// The add-ons are one option rather than five: they attach to a package rather
// than being bought alone, and process-sponsorship.php has a single `tier`
// field, so five options would be five ways to say the same thing. The add-on
// cards therefore carry no Enquire button of their own, because a button that
// pre-selected nothing specific would be pretending to do something.
$pmTierOptions['add-on'] = pmContent($pdo, 'sponsorship', 'addons_option', 'Add ons only, $500');

$pmSelectedTier = (string) ($_GET['tier'] ?? '');
if (!array_key_exists($pmSelectedTier, $pmTierOptions)) {
    $pmSelectedTier = '';
}

pmPageBegin([
    'slug'        => 'sponsorship',
    'nav'         => 'sponsorship',
    'title'       => pmContent($pdo, 'sponsorship', 'meta_title', 'Sponsorship | Prosperminds'),
    'description' => pmContent($pdo, 'sponsorship', 'meta_description', 'A business-to-government partnership placing sponsors in the room with accountants general, auditors general, treasury leaders and budget controllers.'),
    'canonical'   => '/sponsorship.php',
    'scripts'     => ['/assets/js/pm-sponsorship-form.js'],
]);

$pmExtras = [];
foreach ($pmPackages as $pmPackage) {
    $pmExtras[] = [
        'name'   => (string) ($pmPackage['name'] ?? ''),
        'price'  => (string) ($pmPackage['price'] ?? ''),
        'slots'  => (string) ($pmPackage['slots'] ?? ''),
        'detail' => implode('. ', array_map('strval', (array) ($pmPackage['benefits'] ?? []))),
    ];
}
foreach ($pmAddons as $pmAddon) {
    $pmExtras[] = [
        'name'   => (string) ($pmAddon['name'] ?? ''),
        'price'  => (string) ($pmAddon['price'] ?? ''),
        'slots'  => (string) ($pmAddon['slots'] ?? ''),
        'detail' => (string) ($pmAddon['benefit'] ?? ''),
    ];
}
?>

<div class="pm-container pm-page-head pm-section">

  <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'sponsorship', 'v2_hero_title', 'Partner with us'); ?></h1>
  <p class="pm-lede pm-mt-md"><?php echo pmContentSafe($pdo, 'sponsorship', 'v2_hero_body',
    'Five days in a room of accountants general, auditors general and treasury leaders.'); ?></p>

  <div class="pm-packages">
<?php foreach ($pmTiers as $pmTier): ?>
    <div class="pm-package">
      <div class="pm-package__name"><?php echo pmEsc((string) ($pmTier['name'] ?? '')); ?></div>
      <div class="pm-package__price"><?php echo pmEsc((string) ($pmTier['price'] ?? '')); ?></div>
<?php   if (trim((string) ($pmTier['slots'] ?? '')) !== ''): ?>
      <div class="pm-live"><?php echo pmEsc((string) $pmTier['slots']); ?></div>
<?php   endif; ?>
<?php   if (!empty($pmTier['benefits'])): ?>
      <ul class="pm-list">
<?php     foreach ((array) $pmTier['benefits'] as $pmBenefit): ?>
        <li><?php echo pmEsc((string) $pmBenefit); ?></li>
<?php     endforeach; ?>
      </ul>
<?php   endif; ?>
      <a class="pm-btn pm-btn--secondary pm-btn--sm"
         href="?tier=<?php echo pmEsc(rawurlencode((string) ($pmTier['key'] ?? ''))); ?>#enquire">Enquire about <?php
        echo pmEsc((string) ($pmTier['name'] ?? '')); ?></a>
    </div>
<?php endforeach; ?>
  </div>

<?php if ($pmExtras !== []): ?>
  <details class="pm-disclosure pm-mt-xl">
    <summary><?php echo pmContentSafe($pdo, 'sponsorship', 'v2_extras_title', 'Additional sponsorship items'); ?></summary>
    <ul class="pm-extras">
<?php   foreach ($pmExtras as $pmExtra): ?>
      <li>
        <div class="pm-extras__head">
          <strong><?php echo pmEsc($pmExtra['name']); ?></strong>
          <strong><?php echo pmEsc($pmExtra['price']); ?></strong>
        </div>
<?php     if ($pmExtra['slots'] !== ''): ?>
        <span class="pm-live"><?php echo pmEsc($pmExtra['slots']); ?></span>
<?php     endif; ?>
<?php     if ($pmExtra['detail'] !== ''): ?>
        <p class="pm-caption"><?php echo pmEsc($pmExtra['detail']); ?></p>
<?php     endif; ?>
      </li>
<?php   endforeach; ?>
    </ul>
  </details>
<?php endif; ?>

  <div class="pm-split pm-mt-xl" id="enquire">
    <div>
      <h2 class="pm-h2 pm-h2--md"><?php echo pmContentSafe($pdo, 'sponsorship', 'v2_form_title', 'Send an enquiry'); ?></h2>
      <p class="pm-body pm-mt-sm"><?php echo pmContentSafe($pdo, 'sponsorship', 'v2_form_body',
        'We reply within 48 hours, Monday to Friday, with the prospectus and open slots.'); ?></p>
    </div>

    <?php // Field names are fixed by process-sponsorship.php and must not be
          // renamed here: name, organisation, email, phone, tier, message.
          // Renaming one would empty that field in every enquiry email,
          // silently. ?>
    <form action="/process-sponsorship.php" method="post" data-pm-sponsorship-form>
      <div id="pm-sponsorship-status" class="pm-notice" role="status" hidden></div>

      <div class="pm-form-grid pm-mt-sm">
        <div class="pm-field">
          <label class="pm-field__label" for="pm-sp-name">Full name</label>
          <input class="pm-input" type="text" id="pm-sp-name" name="name" autocomplete="name" required>
        </div>

        <div class="pm-field">
          <label class="pm-field__label" for="pm-sp-org">Institution</label>
          <input class="pm-input" type="text" id="pm-sp-org" name="organisation" autocomplete="organization" required>
        </div>

        <div class="pm-field">
          <label class="pm-field__label" for="pm-sp-email">Work email</label>
          <input class="pm-input" type="email" id="pm-sp-email" name="email" inputmode="email" autocomplete="email" required>
        </div>

        <div class="pm-field">
          <label class="pm-field__label" for="pm-sp-phone">Phone</label>
          <input class="pm-input" type="tel" id="pm-sp-phone" name="phone" inputmode="tel" autocomplete="tel">
        </div>

        <div class="pm-field pm-form-grid--wide">
          <label class="pm-field__label" for="pm-sp-tier">Package of interest</label>
          <select class="pm-select" id="pm-sp-tier" name="tier">
            <option value="">Not sure yet</option>
<?php foreach ($pmTierOptions as $pmKey => $pmOptionLabel): ?>
            <option value="<?php echo pmEsc($pmOptionLabel); ?>"<?php
              echo $pmSelectedTier === $pmKey ? ' selected' : ''; ?>><?php echo pmEsc($pmOptionLabel); ?></option>
<?php endforeach; ?>
          </select>
        </div>

        <div class="pm-field pm-form-grid--wide">
          <label class="pm-field__label" for="pm-sp-message">Message</label>
          <textarea class="pm-textarea" id="pm-sp-message" name="message" rows="4"></textarea>
        </div>

        <div class="pm-form-grid--wide">
          <button class="pm-btn pm-btn--block" type="submit" data-pm-sending="Sending">Send enquiry</button>
          <p class="pm-caption pm-mt-sm"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_consent_html',
            'We use these details only to answer your enquiry. See our <a href="/privacy-policy.php">privacy policy</a>.', true); ?></p>
        </div>
      </div>
    </form>
  </div>

</div>

<?php pmPageEnd(); ?>
