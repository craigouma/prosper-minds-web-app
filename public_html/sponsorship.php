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
    ['key' => 'bronze', 'name' => 'Bronze', 'price' => '$2,000', 'slots' => '10 slots remaining', 'benefits' => [
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
// Each tier and package card carries an Enquire button pointing at
// ?tier=<key>#apply. Without it, somebody who clicked Platinum has to say so
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

// ── The schools a sponsor can choose, from the calendar rather than from a
//    hardcoded list that has already fallen out of date once ────────────────
$pmEvents = pmActiveEvents($pdo);

pmPageBegin([
    'slug'        => 'sponsorship',
    'nav'         => 'sponsorship',
    'title'       => pmContent($pdo, 'sponsorship', 'meta_title', 'Sponsorship | Prosperminds'),
    'description' => pmContent($pdo, 'sponsorship', 'meta_description', 'A business-to-government partnership placing sponsors in the room with accountants general, auditors general, treasury leaders and budget controllers.'),
    'canonical'   => '/sponsorship.php',
    'scripts'     => ['/assets/js/pm-sponsorship-form.js'],
]);
?>

<?php // ── Hero ──────────────────────────────────────────────────────────── ?>
<section class="pm-section pm-relative pm-clip">
  <?php include __DIR__ . '/includes/layout/motif.php'; ?>
  <div class="pm-container pm-relative">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'sponsorship', 'hero_eyebrow',
      'Sponsorship'); ?></span>

    <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'sponsorship', 'hero_title',
      'Co-Author Africa\'s Public Finance Future'); ?></h1>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'sponsorship', 'hero_body',
      'This is a business-to-government partnership, not advertising space. Sponsors sit in the room with accountants general, auditors general, treasury leaders and budget controllers for five days, as contributors to the programme rather than names on a banner.'); ?></p>

    <div class="pm-btn-row pm-mt-lg">
      <a class="pm-btn" href="#apply"><?php echo pmContentSafe($pdo, 'sponsorship', 'hero_cta_primary',
        'Become a partner'); ?></a>
      <a class="pm-btn pm-btn--secondary" href="#packages"><?php echo pmContentSafe($pdo, 'sponsorship', 'hero_cta_secondary',
        'View packages'); ?></a>
    </div>

  </div>
</section>


<?php // ── Why this moment matters ───────────────────────────────────────── ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'sponsorship', 'why_eyebrow',
      'Why this moment matters'); ?></span>

    <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'sponsorship', 'why_title',
      'Traditional marketing will not get you into that room. This event will.'); ?></h2>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'sponsorship', 'why_body',
      'Africa\'s public sector is transforming faster than ever. The professionals in the room are not looking for service providers. They are looking for trusted partners who can support real implementation.'); ?></p>

    <div class="pm-grid pm-grid--ruled pm-grid--3 pm-mt-lg">
<?php foreach (pmContentJson($pdo, 'sponsorship', 'why_cards', [
        ['title' => 'While others advertise', 'body' => 'You will be remembered. Your brand is part of the experience of Africa\'s most influential finance leaders, not an advertisement beside it.'],
        ['title' => 'While others pitch',     'body' => 'You will be partnering. Direct business to government engagement with the people who implement policy and control budgets.'],
        ['title' => 'While others wait',      'body' => 'You will already be part of Africa\'s real system change: shaping the conversation rather than watching it from the outside.'],
      ]) as $pmIndex => $pmCard): ?>
      <div class="pm-cell">
        <span class="pm-ordinal"><?php echo pmEsc(str_pad((string) ($pmIndex + 1), 2, '0', STR_PAD_LEFT)); ?></span>
        <h3 class="pm-h3 pm-h3--caps"><?php echo pmEsc((string) ($pmCard['title'] ?? '')); ?></h3>
        <p class="pm-body"><?php echo pmEsc((string) ($pmCard['body'] ?? '')); ?></p>
      </div>
<?php endforeach; ?>
    </div>

  </div>
</section>


<?php // ── The eligible events, read from the calendar ───────────────────── ?>
<section class="pm-section" id="events">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'sponsorship', 'events_eyebrow',
      'Eligible events'); ?></span>

    <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'sponsorship', 'events_title',
      'Four flagship schools in 2026'); ?></h2>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'sponsorship', 'events_body',
      'Each school draws senior public finance officials from across the continent and beyond. Sponsor one, or take the whole 2026 calendar.'); ?></p>

<?php if ($pmEvents === []): ?>
    <p class="pm-body pm-mt-lg"><?php echo pmContentSafe($pdo, 'sponsorship', 'events_empty',
      'The 2026 calendar is being confirmed. Send an enquiry and we will come back to you with dates and audience numbers.'); ?></p>
<?php else: ?>
    <div class="pm-grid pm-grid--ruled pm-grid--4 pm-mt-lg">
<?php foreach ($pmEvents as $pmEvent): ?>
      <article class="pm-cell">
        <?php $pmStamp = pmEventDateBlock($pmEvent)['stamp']; ?>
<?php if ($pmStamp !== ''): ?>
        <span class="pm-label pm-label--green"><?php echo pmEsc($pmStamp); ?></span>
<?php endif; ?>
        <h3 class="pm-h4"><?php echo pmEsc(pmEventProse((string) ($pmEvent['title'] ?? ''))); ?></h3>
        <span class="pm-caption"><?php echo pmEsc(pmEventProse((string) ($pmEvent['location'] ?? ''))); ?></span>
        <a class="pm-btn--link pm-cell__action" href="#apply">
          <?php echo pmContentSafe($pdo, 'sponsorship', 'events_cta', 'Sponsor this event'); ?>
          <span class="pm-sr-only"> in <?php echo pmEsc(pmEventCity($pmEvent)); ?></span>
        </a>
      </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>

  </div>
</section>


<?php // ── What a partner gains, and who is in the room ──────────────────── ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container pm-row">

    <div class="pm-row__main">
      <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'sponsorship', 'gains_eyebrow',
        'What you gain'); ?></span>

      <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'sponsorship', 'gains_title',
        'More than a sponsorship, a partnership'); ?></h2>

      <p class="pm-body pm-mt-md"><?php echo pmContentSafe($pdo, 'sponsorship', 'gains_body',
        'We do not sell packages. We build partnerships, and we align the platform to what your organisation is actually trying to achieve.'); ?></p>

      <ul class="pm-list">
<?php foreach (pmContentJson($pdo, 'sponsorship', 'gains', [
          'Strong visibility before, during and after the event',
          'Direct access to public finance practitioners and decision influencers',
          'Business to government engagement opportunities',
          'Thought leadership through sessions and workshops',
          'Brand positioning as a trusted implementation partner',
          'Entry into new African government markets',
          'A platform to show your solutions to the leaders who implement policy',
          'A clear message that you are part of Africa\'s transformation',
        ]) as $pmGain): ?>
        <li><?php echo pmEsc((string) $pmGain); ?></li>
<?php endforeach; ?>
      </ul>
    </div>

    <?php // The one dark panel on the page. Proof, in the treatment notes'
          // sense: this is who a sponsor is actually buying access to. ?>
    <div class="pm-row__side">
      <div class="pm-card pm-card--dark">
        <span class="pm-label"><?php echo pmContentSafe($pdo, 'sponsorship', 'audience_title',
          'Who will be in the room'); ?></span>

        <p class="pm-body"><?php echo pmContentSafe($pdo, 'sponsorship', 'audience_body',
          'Hundreds of the public sector leaders who set, spend and account for public money.'); ?></p>

        <ul class="pm-tags">
<?php foreach (pmContentJson($pdo, 'sponsorship', 'audience_tags', [
            'Finance officers', 'Accountants', 'Auditors', 'Budget controllers',
            'Treasury leaders', 'Revenue managers', 'Strategy directors',
            'Decision makers', 'Policy implementers',
          ]) as $pmRole): ?>
          <li class="pm-tag"><?php echo pmEsc((string) $pmRole); ?></li>
<?php endforeach; ?>
        </ul>

        <div class="pm-card__foot">
          <span class="pm-label"><?php echo pmContentSafe($pdo, 'sponsorship', 'promise_label',
            'Our promise to you'); ?></span>
          <p class="pm-quote__text pm-mt-sm">
            <span class="pm-quote__mark" aria-hidden="true">&ldquo;</span><?php
            echo pmContentSafe($pdo, 'sponsorship', 'promise_text',
              'Relevant. Reliable. Convenient.'); ?>
          </p>
        </div>
      </div>
    </div>

  </div>
</section>


<?php // ── The four tiers ────────────────────────────────────────────────── ?>
<section class="pm-section" id="packages">
  <div class="pm-container">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'sponsorship', 'tiers_eyebrow',
      'Sponsorship packages'); ?></span>

    <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'sponsorship', 'tiers_title',
      'Four partnership tiers'); ?></h2>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'sponsorship', 'tiers_body',
      'Investment levels to match your goals and your budget. Every tier is available across every school in the calendar.'); ?></p>

    <?php $pmTierCta = pmContent($pdo, 'sponsorship', 'tiers_cta', 'Enquire'); ?>
    <div class="pm-grid pm-grid--4 pm-mt-lg">
<?php foreach ($pmTiers as $pmTier): ?>
<?php   $pmKey = trim((string) ($pmTier['key'] ?? '')); ?>
      <article class="pm-card">
        <div class="pm-stack pm-stack--xs">
          <span class="pm-label"><?php echo pmEsc((string) ($pmTier['name'] ?? '')); ?></span>
          <span class="pm-price"><?php echo pmEsc((string) ($pmTier['price'] ?? '')); ?></span>
          <span class="pm-price__note pm-green"><?php echo pmEsc((string) ($pmTier['slots'] ?? '')); ?></span>
        </div>
        <ul class="pm-list">
<?php foreach ((array) ($pmTier['benefits'] ?? []) as $pmBenefit): ?>
          <li><?php echo pmEsc((string) $pmBenefit); ?></li>
<?php endforeach; ?>
        </ul>
        <div class="pm-card__foot">
          <?php // Carries its own tier into the form. Server side, so it works
                // with scripts off; validated against the known keys, so a
                // hand edited value falls back to the neutral option. ?>
          <a class="pm-btn pm-btn--secondary pm-btn--block pm-btn--sm"
             href="/sponsorship.php?tier=<?php echo pmEsc(rawurlencode($pmKey)); ?>#apply">
            <?php echo pmEsc($pmTierCta); ?>
            <span class="pm-sr-only"> about the <?php echo pmEsc((string) ($pmTier['name'] ?? '')); ?> tier</span>
          </a>
        </div>
      </article>
<?php endforeach; ?>
    </div>

  </div>
</section>


<?php // ── Specialised packages and add-ons ──────────────────────────────── ?>
<section class="pm-section pm-section--surface">
  <div class="pm-container pm-row">

    <div class="pm-row__main">
      <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'sponsorship', 'packages_eyebrow',
        'Entry packages'); ?></span>

      <h2 class="pm-h3 pm-h3--caps"><?php echo pmContentSafe($pdo, 'sponsorship', 'packages_title',
        'Specialised, $1,000 each'); ?></h2>

      <ul class="pm-grid pm-grid--ruled pm-mt-lg">
<?php foreach ($pmPackages as $pmPackage): ?>
<?php     $pmKey = trim((string) ($pmPackage['key'] ?? '')); ?>
        <li class="pm-cell">
          <div class="pm-card__foot--split">
            <h3 class="pm-h4"><?php echo pmEsc((string) ($pmPackage['name'] ?? '')); ?></h3>
            <span class="pm-label"><?php echo pmEsc((string) ($pmPackage['price'] ?? '')); ?></span>
          </div>
          <span class="pm-caption pm-green"><?php echo pmEsc((string) ($pmPackage['slots'] ?? '')); ?></span>
          <ul class="pm-list">
<?php foreach ((array) ($pmPackage['benefits'] ?? []) as $pmBenefit): ?>
            <li><?php echo pmEsc((string) $pmBenefit); ?></li>
<?php endforeach; ?>
          </ul>
          <a class="pm-btn--link pm-cell__action"
             href="/sponsorship.php?tier=<?php echo pmEsc(rawurlencode($pmKey)); ?>#apply">
            <?php echo pmEsc($pmTierCta); ?>
            <span class="pm-sr-only"> about the <?php echo pmEsc((string) ($pmPackage['name'] ?? '')); ?> package</span>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
    </div>

    <div class="pm-row__side">
      <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'sponsorship', 'addons_eyebrow',
        'Add ons'); ?></span>

      <h2 class="pm-h3 pm-h3--caps"><?php echo pmContentSafe($pdo, 'sponsorship', 'addons_title',
        '$500 each'); ?></h2>

      <p class="pm-body pm-mt-md"><?php echo pmContentSafe($pdo, 'sponsorship', 'addons_body',
        'Targeted visibility that can be added to any package above.'); ?></p>

      <?php // No Enquire button per add-on, deliberately. The handler has one
            // `tier` field, so five buttons would all pre-select the same
            // single "Add ons only" option and each would be pretending to
            // carry a choice it cannot. The form's dropdown holds that option. ?>
      <ul class="pm-grid pm-grid--ruled pm-mt-lg">
<?php foreach ($pmAddons as $pmAddon): ?>
        <li class="pm-cell">
          <div class="pm-card__foot--split">
            <h3 class="pm-h4"><?php echo pmEsc((string) ($pmAddon['name'] ?? '')); ?></h3>
            <span class="pm-label"><?php echo pmEsc((string) ($pmAddon['price'] ?? '')); ?></span>
          </div>
          <span class="pm-caption pm-green"><?php echo pmEsc((string) ($pmAddon['slots'] ?? '')); ?></span>
          <p class="pm-caption"><?php echo pmEsc((string) ($pmAddon['benefit'] ?? '')); ?></p>
        </li>
<?php endforeach; ?>
      </ul>
    </div>

  </div>
</section>


<?php // ── Closing band. The one green section on this page. ─────────────── ?>
<section class="pm-section pm-section--accent pm-section--tight">
  <div class="pm-container pm-band">
    <div class="pm-row__main">
      <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'sponsorship', 'cta_title',
        'Ready to become Africa\'s partner in transformation?'); ?></h2>
      <p class="pm-body pm-mt-sm"><?php echo pmContentSafe($pdo, 'sponsorship', 'cta_body',
        'A short call is enough to work out whether this is a fit. Every event that passes is a room you were not in.'); ?></p>
    </div>
    <div class="pm-section-head__action">
      <a class="pm-btn pm-btn--invert" href="#apply"><?php echo pmContentSafe($pdo, 'sponsorship', 'cta_label',
        'Send a sponsorship enquiry'); ?></a>
    </div>
  </div>
</section>


<?php // ── The enquiry form ──────────────────────────────────────────────── ?>
<section class="pm-section pm-section--dark" id="apply">
  <div class="pm-container pm-row">

    <div class="pm-row__side">
      <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_eyebrow',
        'Enquiry'); ?></span>

      <h2 class="pm-h2"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_title',
        'We do not sell packages. We build partnerships.'); ?></h2>

      <p class="pm-body pm-mt-md"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_body',
        'Tell us which schools matter to you and what you want out of the room. A partnership lead replies within 48 hours with a proposal built around those goals.'); ?></p>

      <p class="pm-caption pm-mt-md"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_required_note',
        'First name, last name, organisation, email and at least one school are required.'); ?></p>
    </div>

    <div class="pm-row__main">
      <?php // Field names are fixed by process-sponsorship.php and must not be
            // renamed here: first_name, last_name, organisation, email, phone,
            // country, events[], tier, message. Renaming one would empty that
            // field in every enquiry email, silently. ?>
      <form class="pm-stack pm-stack--md"
            action="/process-sponsorship.php"
            method="post"
            data-pm-sponsorship-form>

        <div id="pm-sponsorship-status" class="pm-notice" role="status" hidden></div>

        <?php // Six fields in one grid rather than three grids of two: the
              // column this form sits in fits two controls across, and one
              // grid keeps them on a single rhythm at every width. ?>
        <div class="pm-grid pm-grid--3">
          <div class="pm-field">
            <label class="pm-field__label" for="pm-sp-first"><?php
              echo pmContentSafe($pdo, 'sponsorship', 'form_label_first', 'First name'); ?></label>
            <input class="pm-input" type="text" id="pm-sp-first" name="first_name"
                   autocomplete="given-name" required>
          </div>

          <div class="pm-field">
            <label class="pm-field__label" for="pm-sp-last"><?php
              echo pmContentSafe($pdo, 'sponsorship', 'form_label_last', 'Last name'); ?></label>
            <input class="pm-input" type="text" id="pm-sp-last" name="last_name"
                   autocomplete="family-name" required>
          </div>

          <div class="pm-field">
            <label class="pm-field__label" for="pm-sp-org"><?php
              echo pmContentSafe($pdo, 'sponsorship', 'form_label_org', 'Organisation'); ?></label>
            <input class="pm-input" type="text" id="pm-sp-org" name="organisation"
                   autocomplete="organization" required>
          </div>

          <div class="pm-field">
            <label class="pm-field__label" for="pm-sp-email"><?php
              echo pmContentSafe($pdo, 'sponsorship', 'form_label_email', 'Email'); ?></label>
            <input class="pm-input" type="email" id="pm-sp-email" name="email"
                   autocomplete="email" required>
          </div>

          <div class="pm-field">
            <label class="pm-field__label" for="pm-sp-phone"><?php
              echo pmContentSafe($pdo, 'sponsorship', 'form_label_phone', 'Phone'); ?></label>
            <input class="pm-input" type="tel" id="pm-sp-phone" name="phone" autocomplete="tel">
          </div>

          <div class="pm-field">
            <label class="pm-field__label" for="pm-sp-country"><?php
              echo pmContentSafe($pdo, 'sponsorship', 'form_label_country', 'Country'); ?></label>
            <input class="pm-input" type="text" id="pm-sp-country" name="country"
                   autocomplete="country-name">
          </div>
        </div>

        <?php // A real fieldset with a real legend: four checkboxes with only a
              // paragraph above them announce as four unrelated controls. ?>
        <fieldset class="pm-fieldset">
          <legend class="pm-field__label"><?php
            echo pmContentSafe($pdo, 'sponsorship', 'form_label_events',
              'Which schools are you interested in?'); ?></legend>
          <span class="pm-field__hint"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_hint_events',
            'Choose at least one.'); ?></span>

          <div class="pm-stack pm-stack--sm pm-mt-sm">
<?php if ($pmEvents === []): ?>
            <?php // No calendar, no checkboxes, and the handler requires at
                  // least one. Saying so is better than showing a form that
                  // cannot be submitted. ?>
            <p class="pm-body"><?php echo pmContentSafe($pdo, 'sponsorship', 'events_empty',
              'The 2026 calendar is being confirmed. Send an enquiry and we will come back to you with dates and audience numbers.'); ?></p>
<?php else: ?>
<?php   foreach ($pmEvents as $pmIndex => $pmEvent): ?>
<?php
        // The value is what lands in the enquiry email, so it is the shortest
        // string that identifies a school unambiguously. Two of the four run in
        // December, so the city has to be in it as well as the month.
        $pmValue = trim(pmEventCity($pmEvent) . ', ' . pmEventMonthLabel($pmEvent), ', ');
        $pmId    = 'pm-sp-event-' . (int) ($pmEvent['id'] ?? $pmIndex);
?>
            <label class="pm-check" for="<?php echo pmEsc($pmId); ?>">
              <input type="checkbox" id="<?php echo pmEsc($pmId); ?>" name="events[]"
                     value="<?php echo pmEsc($pmValue); ?>">
              <span><?php echo pmEsc(pmEventProse((string) ($pmEvent['location'] ?? ''))); ?>,
                <?php echo pmEsc(pmEventMonthLabel($pmEvent)); ?>.
                <?php echo pmEsc(pmEventProse((string) ($pmEvent['title'] ?? ''))); ?></span>
            </label>
<?php   endforeach; ?>
<?php endif; ?>
          </div>
        </fieldset>

        <div class="pm-field">
          <label class="pm-field__label" for="pm-sp-tier"><?php
            echo pmContentSafe($pdo, 'sponsorship', 'form_label_tier', 'Tier of interest'); ?></label>
          <select class="pm-select" id="pm-sp-tier" name="tier">
            <option value=""><?php echo pmContentSafe($pdo, 'sponsorship', 'form_tier_none',
              'Not sure yet, please advise'); ?></option>
<?php foreach ($pmTierOptions as $pmKey => $pmOptionLabel): ?>
            <option value="<?php echo pmEsc($pmOptionLabel); ?>"<?php
              echo $pmSelectedTier === $pmKey ? ' selected' : ''; ?>><?php
              echo pmEsc($pmOptionLabel); ?></option>
<?php endforeach; ?>
          </select>
        </div>

        <div class="pm-field">
          <label class="pm-field__label" for="pm-sp-message"><?php
            echo pmContentSafe($pdo, 'sponsorship', 'form_label_message', 'Goals'); ?></label>
          <span class="pm-field__hint" id="pm-sp-message-hint"><?php
            echo pmContentSafe($pdo, 'sponsorship', 'form_hint_message',
              'What would make this partnership worthwhile for your organisation?'); ?></span>
          <textarea class="pm-textarea" id="pm-sp-message" name="message" rows="5"
                    aria-describedby="pm-sp-message-hint"></textarea>
        </div>

        <p class="pm-caption"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_consent_html',
          'We use these details only to answer your enquiry. See our <a href="/privacy-policy.php">privacy policy</a>.', true); ?></p>

        <div class="pm-btn-row">
          <button class="pm-btn" type="submit"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_submit',
            'Send enquiry'); ?></button>
        </div>

        <p class="pm-caption"><?php echo pmContentSafe($pdo, 'sponsorship', 'form_note',
          'Replies within 48 hours, Monday to Friday.'); ?></p>
      </form>
    </div>

  </div>
</section>

<?php pmPageEnd(); ?>
