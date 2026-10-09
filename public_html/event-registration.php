<?php

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/resume.php';

/*
 * FIELD NAMES ARE FIXED BY process-registration.php AND MUST NOT BE RENAMED.
 * Renaming one empties that column on every future registration, silently, and
 * the failure looks like success:
 *
 *   csrf_token, event_id, event_name, tier, first_name, last_name, phone,
 *   email, organization, country, address, consent, and
 *   attendees[first_name][] / [last_name][] / [email][]
 *
 * gender, meal_preference, future_topics and attendees[title][] are no longer
 * asked for: the handler stores '' for them. address is optional.
 *
 * tier is 'regular', 'vip' or 'vvip'. The handler treats anything else, or a
 * tier the event has no price for, as 'regular': it is never trusted to set
 * the price, only to choose which of the event's own prices applies.
 *
 * The handler zips the attendees arrays by index, so a delegate row must
 * contribute all its fields or none. A row whose values are all empty is
 * skipped, which is what lets the undriven form ship five rows and have a
 * visitor fill in only the first.
 */

/** Real rows in the markup, not a template, so the undriven form is completable. */
const PM_REG_ROWS = 5;
const PM_REG_MAX = 20;

const PM_REG_COUNTRIES = [
    'Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'Ethiopia', 'Ghana', 'Nigeria', 'South Africa',
    'Zambia', 'Malawi', 'Botswana', 'Namibia', 'Zimbabwe', 'Algeria', 'Angola', 'Benin',
    'Burkina Faso', 'Burundi', 'Cameroon', 'Cape Verde', 'Central African Republic', 'Chad',
    'Comoros', 'Congo', 'Cote d\'Ivoire', 'Democratic Republic of the Congo', 'Djibouti',
    'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini', 'Gabon', 'Gambia', 'Guinea',
    'Guinea-Bissau', 'Lesotho', 'Liberia', 'Libya', 'Madagascar', 'Mali', 'Mauritania',
    'Mauritius', 'Morocco', 'Mozambique', 'Niger', 'Sao Tome and Principe', 'Senegal',
    'Seychelles', 'Sierra Leone', 'Somalia', 'South Sudan', 'Sudan', 'Togo', 'Tunisia',
    'Other',
];

// Both addresses render the same form; the old ?id= link is not deprecated,
// only joined by a nicer one. See event.php for why this is not a redirect.
$pmSlugParam = trim((string) ($_GET['slug'] ?? ''));
$pmEventId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pmWantsOne  = $pmSlugParam !== '' || $pmEventId > 0;
$pmEvent     = $pmSlugParam !== '' ? pmEventBySlug($pdo, $pmSlugParam) : ($pmEventId > 0 ? pmEventById($pdo, $pmEventId) : null);

// /register on its own: choose a school, or skip the choice when only one is
// open. A link to a school that no longer exists lands here too.
if ($pmEvent === null || (int) ($pmEvent['is_active'] ?? 0) !== 1) {
    $pmOpen = array_values(array_filter(pmActiveEvents($pdo), static function (array $event): bool {
        return !pmEventIsPast($event);
    }));

    if (count($pmOpen) === 1) {
        header('Location: ' . pmEventRegisterUrl($pmOpen[0]));
        exit;
    }

    if ($pmOpen === []) {
        header('Location: /#schools');
        exit;
    }

    pmPageBegin([
        'slug'        => 'register',
        'nav'         => 'schools',
        'title'       => 'Choose a school',
        'description' => 'Choose the school you are registering delegates for.',
        'canonical'   => '/register',
        'hide_cta'    => true,
    ]);
    ?>
<div class="pm-container pm-container--narrow pm-reg pm-reg--single">
  <div class="pm-reg__main">
    <h1 class="pm-h1 pm-h1--step">Choose a school</h1>
    <div class="pm-choices pm-choices--stack">
<?php foreach ($pmOpen as $pmOption): ?>
      <a class="pm-pick" href="<?php echo pmEsc(pmEventRegisterUrl($pmOption)); ?>">
        <span class="pm-pick__title"><?php echo pmEsc(pmEventProse((string) $pmOption['title'])); ?></span>
        <span class="pm-pick__when"><?php echo pmEsc(trim(pmEventDatesLong($pmOption) . ', ' . pmEventPlace($pmOption), ', ')); ?></span>
      </a>
<?php endforeach; ?>
    </div>
  </div>
</div>
    <?php
    pmPageEnd();
    exit;
}

$pmEventId = (int) $pmEvent['id'];

// Before any output: funnelSessionId() may need to send the pm_funnel_sid
// cookie. Tracking must never be able to stop the form rendering.
try {
    funnelTrackEvent($pdo, 'page_view', [
        'event_id' => (int) $pmEvent['id'],
        'referrer' => funnelSanitiseReferrer($_SERVER['HTTP_REFERER'] ?? null),
        'utm'      => funnelUtmFromQuery($_GET),
    ]);
} catch (Throwable $funnelError) {
    error_log('Funnel page_view failed (ignored): ' . $funnelError->getMessage());
}

// An emailed "finish your registration" link. An unknown, expired or already
// completed token simply opens a blank form: the reminder is a convenience, not
// a credential for anything.
$pmResume = pmResumeByToken($pdo, $_GET['resume'] ?? null);
$pmResume = is_array($pmResume) && (int) $pmResume['event_id'] === (int) $pmEvent['id'] ? $pmResume : null;

/** A step one value to put back in the form, escaped, or ''. */
$pmPrefill = static function (string $field) use ($pmResume): string {
    return $pmResume === null ? '' : pmEsc((string) ($pmResume[$field] ?? ''));
};

$pmTitle    = pmEventProse((string) ($pmEvent['title'] ?? ''));
$pmDates    = pmEventDatesLong($pmEvent);
$pmWhen     = trim($pmDates . ', ' . pmEventPlace($pmEvent), ', ');
$pmContact  = pmContact($pdo);

// Every tier the event actually prices. An event with no vip_price/vvip_price
// set offers only Regular. The handler applies this exact rule independently,
// so a tampered tier can only ever fall back to Regular, never invent a price.
$pmTierOptions = [];
foreach ([
    ['key' => 'regular', 'name' => 'Regular', 'price_text' => (string) ($pmEvent['price'] ?? '')],
    ['key' => 'vip',     'name' => 'VIP',     'price_text' => (string) ($pmEvent['vip_price'] ?? '')],
    ['key' => 'vvip',    'name' => 'VVIP',    'price_text' => (string) ($pmEvent['vvip_price'] ?? '')],
] as $pmTierSpec) {
    if (trim($pmTierSpec['price_text']) === '') {
        continue;
    }
    [$pmTierCurrency, $pmTierAmount] = pmEventParsePrice($pmTierSpec['price_text']);
    $pmTierOptions[] = $pmTierSpec + [
        'currency' => $pmTierCurrency,
        'amount'   => $pmTierAmount,
        'label'    => pmEventMoney($pmTierCurrency, $pmTierAmount),
    ];
}

// "Register as VIP" on the school page pre-selects that tier; anything else,
// including a tier this event does not offer, lands on Regular.
$pmRequestedTier = trim((string) ($_GET['tier'] ?? ''));
$pmSelectedTier  = in_array($pmRequestedTier, array_column($pmTierOptions, 'key'), true)
    ? $pmRequestedTier
    : 'regular';

$pmSelectedTierData = null;
foreach ($pmTierOptions as $pmTierOption) {
    if ($pmTierOption['key'] === $pmSelectedTier) {
        $pmSelectedTierData = $pmTierOption;
        break;
    }
}

$pmCurrency   = $pmSelectedTierData['currency'] ?? 'USD';
$pmUnitAmount = $pmSelectedTierData['amount'] ?? 0.0;
$pmUnitLabel  = pmEventMoney($pmCurrency, $pmUnitAmount);
$pmTierName   = $pmSelectedTierData['name'] ?? 'Regular';
$pmWhatsApp   = pmWhatsAppUrl('Question about my registration for ' . $pmTitle);

pmPageBegin([
    'slug'        => 'register',
    'nav'         => 'schools',
    'title'       => 'Register: ' . $pmTitle,
    'description' => 'Register delegates for ' . $pmTitle . ', ' . $pmWhen
                     . '. Invoiced to your institution, payable by bank transfer or purchase order.',
    'canonical'   => pmEventRegisterUrl($pmEvent),
    'hide_cta'    => true,
    'scripts'     => ['/assets/js/pm-register.js'],
]);
?>

<div class="pm-container pm-container--narrow pm-reg">

  <div class="pm-reg__main">

    <?php // Hidden by attribute, not by class, so it stays hidden with the
          // stylesheet absent. pm-register.js reveals it only from the branch
          // where the server answered success:true. ?>
    <div class="pm-done" id="pm-reg-done" data-pm-done hidden>
      <div class="pm-done__mark" aria-hidden="true">
        <svg width="26" height="20" viewBox="0 0 26 20"><path d="M2 10.5 9 17.5 24 2.5" fill="none" stroke="#000" stroke-width="3"></path></svg>
      </div>
      <h1 class="pm-h1 pm-h1--step pm-mt-md" tabindex="-1">Registration received</h1>

      <div class="pm-done__invoice">
        <div class="pm-caption">Invoice number</div>
        <div class="pm-done__number" data-pm-done-invoice></div>
        <p class="pm-body pm-mt-sm" data-pm-done-message>Your invoice has been emailed to the billing contact.</p>
      </div>

      <h2>What happens next</h2>
      <ol>
        <li><strong>1</strong><span>Pay by bank transfer or purchase order. The payment details are on the invoice.</span></li>
        <li><strong>2</strong><span>Joining instructions follow by email before the school.</span></li>
      </ol>

      <p class="pm-strong pm-mt-lg">Questions about this registration?</p>
      <div class="pm-btn-row">
<?php if ($pmWhatsApp !== ''): ?>
        <a class="pm-btn" href="<?php echo pmEsc($pmWhatsApp); ?>" rel="noopener">WhatsApp us</a>
<?php endif; ?>
<?php if ($pmContact['phones'] !== []): ?>
        <a class="pm-btn <?php echo $pmWhatsApp !== '' ? 'pm-btn--secondary' : ''; ?>"
           href="tel:<?php echo pmEsc($pmContact['phones'][0]['tel']); ?>">Call <?php
          echo pmEsc($pmContact['phones'][0]['label']); ?></a>
<?php endif; ?>
      </div>
      <p class="pm-mt-md"><a class="pm-link" href="/#schools">Back to the schools</a></p>
    </div>

    <?php // One form, one POST. action and method are real so the browser can
          // post it with no script at all. ?>
    <form id="standaloneRegForm"
          action="/process-registration.php"
          method="post"
          data-pm-register
          data-pm-currency="<?php echo pmEsc($pmCurrency); ?>"
          data-pm-unit-amount="<?php echo pmEsc(number_format($pmUnitAmount, 2, '.', '')); ?>"
          data-pm-rows="<?php echo PM_REG_ROWS; ?>"
          data-pm-max="<?php echo PM_REG_MAX; ?>">

      <?php echo formCsrfField(); ?>
      <input type="hidden" name="event_id" value="<?php echo (int) $pmEvent['id']; ?>">
      <?php // The raw title, not the display copy, so the handler's fallback
            // lookup by event_name still matches the row. ?>
      <input type="hidden" name="event_name" value="<?php echo pmEsc((string) $pmEvent['title']); ?>">

      <div class="pm-notice pm-notice--error" id="pm-reg-status" data-pm-status role="alert" hidden></div>


      <section class="pm-reg__panel" id="pm-reg-step-1" data-pm-step="1" data-pm-current
               aria-labelledby="pm-reg-step-1-title">
        <span class="pm-reg__stepcount" data-pm-stepcount role="status">Step 1 of 2</span>
        <h1 class="pm-h1 pm-h1--step pm-mt-sm" id="pm-reg-step-1-title">The school and you</h1>

        <div class="pm-reg__school">
          <div class="pm-reg__school-main">
            <div class="pm-reg__school-title"><?php echo pmEsc($pmTitle); ?></div>
            <div class="pm-reg__school-when"><?php echo pmEsc($pmWhen); ?></div>
          </div>
          <a class="pm-link" href="/register">Change</a>
        </div>

<?php if (count($pmTierOptions) > 1): ?>
        <div class="pm-group">
          <div class="pm-group__title" id="pm-reg-tier-label">Tier</div>
          <div class="pm-choices" data-pm-tiers role="radiogroup" aria-labelledby="pm-reg-tier-label">
<?php foreach ($pmTierOptions as $pmTierOption): ?>
            <label class="pm-choice">
              <input type="radio" name="tier" value="<?php echo pmEsc($pmTierOption['key']); ?>"
                     data-pm-tier-radio
                     data-pm-tier-amount="<?php echo pmEsc((string) $pmTierOption['amount']); ?>"
                     data-pm-tier-label="<?php echo pmEsc($pmTierOption['label']); ?>"
                     data-pm-tier-name="<?php echo pmEsc($pmTierOption['name']); ?>"
                     <?php echo $pmTierOption['key'] === $pmSelectedTier ? 'checked' : ''; ?>>
              <span class="pm-choice__face">
                <span class="pm-choice__dot" aria-hidden="true"></span>
                <span class="pm-choice__name"><?php echo pmEsc($pmTierOption['name']); ?></span>
                <span class="pm-choice__price"><?php echo pmEsc($pmTierOption['label']); ?></span>
              </span>
            </label>
<?php endforeach; ?>
          </div>
        </div>
<?php else: ?>
        <input type="hidden" name="tier" value="regular">
<?php endif; ?>

        <div class="pm-group">
          <div class="pm-group__title" id="pm-reg-count-label">Number of delegates</div>
          <div class="pm-stepper" data-pm-stepper role="group" aria-labelledby="pm-reg-count-label">
            <button type="button" class="pm-stepper__btn" data-pm-step-down
                    aria-label="One fewer delegate">&minus;</button>
            <output class="pm-stepper__value" data-pm-count-value aria-live="polite">1</output>
            <button type="button" class="pm-stepper__btn" data-pm-step-up
                    aria-label="One more delegate">+</button>
          </div>
          <p class="pm-group__hint">1 to <?php echo PM_REG_MAX; ?> delegates on one invoice</p>
          <p class="pm-group__hint pm-reg__undriven">Name each delegate in the next section. Leave unused delegate rows blank.</p>
        </div>

        <div class="pm-group">
          <div class="pm-group__title">Billing contact</div>
          <p class="pm-group__hint">The invoice is addressed to this person.</p>

          <div class="pm-form-grid pm-mt-md">
            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-first">First name</label>
              <input class="pm-input" type="text" id="pm-reg-first" name="first_name"
                     autocomplete="given-name" value="<?php echo $pmPrefill('first_name'); ?>" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-last">Last name</label>
              <input class="pm-input" type="text" id="pm-reg-last" name="last_name"
                     autocomplete="family-name" value="<?php echo $pmPrefill('last_name'); ?>" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-email">Work email</label>
              <input class="pm-input" type="email" id="pm-reg-email" name="email"
                     inputmode="email" autocomplete="email"
                     value="<?php echo $pmPrefill('email'); ?>" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-phone">Phone</label>
              <?php // pattern restates the handler's own check so the browser
                    // refuses what the server would. ?>
              <input class="pm-input" type="tel" id="pm-reg-phone" name="phone"
                     inputmode="tel" autocomplete="tel"
                     pattern="[\d\+\-\s\(\)]{8,20}"
                     title="8 to 20 characters, digits and + - ( ) only"
                     value="<?php echo $pmPrefill('phone'); ?>" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-org">Institution</label>
              <input class="pm-input" type="text" id="pm-reg-org" name="organization"
                     autocomplete="organization"
                     value="<?php echo $pmPrefill('organization'); ?>" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-country">Country</label>
<?php $pmPickedCountry = $pmResume === null ? '' : (string) ($pmResume['country'] ?? ''); ?>
              <select class="pm-select" id="pm-reg-country" name="country" autocomplete="country-name" required>
                <option value="">Select a country</option>
<?php foreach (PM_REG_COUNTRIES as $pmCountry): ?>
                <option value="<?php echo pmEsc($pmCountry); ?>"<?php
                  echo $pmCountry === $pmPickedCountry ? ' selected' : ''; ?>><?php echo pmEsc($pmCountry); ?></option>
<?php endforeach; ?>
              </select>
            </div>

            <div class="pm-field pm-form-grid--wide">
              <label class="pm-field__label" for="pm-reg-address">Address <span class="pm-field__opt">(optional)</span></label>
              <input class="pm-input" type="text" id="pm-reg-address" name="address" autocomplete="street-address">
            </div>
          </div>
        </div>

        <div class="pm-btn-row pm-btn-row--primary-wide pm-mt-lg pm-reg__inline-cta">
          <button class="pm-btn pm-reg__only-steps" type="button" data-pm-next>Continue</button>
        </div>
      </section>


      <section class="pm-reg__panel" id="pm-reg-step-2" data-pm-step="2"
               aria-labelledby="pm-reg-step-2-title">
        <button type="button" class="pm-link pm-reg__only-steps" data-pm-back>Back to step 1</button>
        <span class="pm-reg__stepcount pm-mt-sm">Step 2 of 2</span>
        <h2 class="pm-h1 pm-h1--step pm-mt-sm" id="pm-reg-step-2-title">Delegates and confirm</h2>

        <div class="pm-delegates" data-pm-delegates>
<?php for ($pmRow = 0; $pmRow < PM_REG_ROWS; $pmRow++): ?>
          <?php // pm-register.js disables rows above the chosen count; hiding
                // alone would still post them. ?>
          <div class="pm-delegate" data-pm-delegate="<?php echo $pmRow; ?>">
            <div class="pm-delegate__head">
              <strong data-pm-delegate-heading>Delegate <?php echo $pmRow + 1; ?></strong>
              <span data-pm-delegate-note><?php echo $pmRow === 0 ? 'Filled from the billing contact' : ''; ?></span>
            </div>

            <div class="pm-form-grid">
              <div class="pm-field">
                <label class="pm-field__label" for="pm-reg-d<?php echo $pmRow; ?>-first">First name</label>
                <input class="pm-input" type="text" autocomplete="given-name"
                       id="pm-reg-d<?php echo $pmRow; ?>-first"
                       name="attendees[first_name][]"
                       <?php echo $pmRow === 0 ? 'required' : ''; ?>>
              </div>

              <div class="pm-field">
                <label class="pm-field__label" for="pm-reg-d<?php echo $pmRow; ?>-last">Last name</label>
                <input class="pm-input" type="text" autocomplete="family-name"
                       id="pm-reg-d<?php echo $pmRow; ?>-last"
                       name="attendees[last_name][]"
                       <?php echo $pmRow === 0 ? 'required' : ''; ?>>
              </div>

              <div class="pm-field pm-form-grid--wide">
                <label class="pm-field__label" for="pm-reg-d<?php echo $pmRow; ?>-email">Email</label>
                <input class="pm-input" type="email" inputmode="email" autocomplete="email"
                       id="pm-reg-d<?php echo $pmRow; ?>-email"
                       name="attendees[email][]"
                       <?php echo $pmRow === 0 ? 'required' : ''; ?>>
              </div>
            </div>
          </div>
<?php endfor; ?>
        </div>

        <?php // THE FIGURES HERE MUST EQUAL WHAT THE HANDLER CHARGES: unit
              // price x delegates, matching
              // $totalAmount = $unitPriceAmount * $attendeeCount. The handler
              // applies no early-bird deduction, so none is shown here. ?>
        <div class="pm-summary">
          <div class="pm-summary__title">Invoice summary</div>
          <dl>
            <dt>School</dt>
            <dd><?php echo pmEsc(trim(pmEventPlace($pmEvent) . ', ' . $pmDates, ', ')); ?></dd>
            <dt>Tier</dt>
            <dd data-pm-summary-tier><?php echo pmEsc($pmTierName); ?></dd>
            <dt>Unit price</dt>
            <dd data-pm-unit-label><?php echo pmEsc($pmUnitLabel); ?></dd>
            <dt>Delegates</dt>
            <dd data-pm-review-count>1</dd>
            <dt class="pm-summary__total">Total</dt>
            <dd class="pm-summary__total" data-pm-review-total><?php echo pmEsc($pmUnitLabel); ?></dd>
          </dl>
        </div>

        <div class="pm-consent">
          <label class="pm-check" for="pm-reg-consent">
            <input type="checkbox" id="pm-reg-consent" name="consent" value="yes" required>
            <span><?php echo pmContentSafe($pdo, 'register', 'v2_consent_text',
              'I confirm these details are correct and agree that Prosperminds may use them to issue the invoice, joining instructions and certificates.'); ?></span>
          </label>
          <p class="pm-caption pm-mt-sm"><?php echo pmContentSafe($pdo, 'register', 'v2_consent_note_html',
            'See our <a href="/privacy-policy.php">privacy policy</a>.', true); ?></p>
        </div>

        <div class="pm-btn-row pm-btn-row--primary-wide pm-mt-lg pm-reg__inline-cta">
          <button class="pm-btn" type="submit" data-pm-submit
                  data-pm-sending="Submitting">Submit registration</button>
        </div>
      </section>

    </form>
  </div>

  <aside class="pm-reg__aside" aria-labelledby="pm-reg-summary-head">
    <div class="pm-reg__aside-label" id="pm-reg-summary-head">Invoice total</div>
    <div class="pm-reg__aside-total"
         data-pm-invoice-total
         data-pm-total-amount="<?php echo pmEsc(number_format($pmUnitAmount, 2, '.', '')); ?>"
         data-pm-currency="<?php echo pmEsc($pmCurrency); ?>"><?php echo pmEsc($pmUnitLabel); ?></div>
    <div class="pm-reg__aside-line" data-pm-total-line><?php echo pmEsc($pmTierName); ?>, 1 delegate</div>
    <button class="pm-btn pm-reg__cta" type="button" data-pm-primary>Continue</button>
<?php if ($pmContact['phones'] !== []): ?>
    <div class="pm-reg__aside-help">Questions? <a href="tel:<?php echo pmEsc($pmContact['phones'][0]['tel']); ?>"><?php
      echo pmEsc($pmContact['phones'][0]['label']); ?></a></div>
<?php endif; ?>
  </aside>

</div>

<div class="pm-stickybar pm-stickybar--mobile pm-reg__bar" data-pm-bar>
  <div class="pm-stickybar__fig"><span data-pm-total-line><?php echo pmEsc($pmTierName); ?>, 1 delegate</span><strong data-pm-bar-total><?php
    echo pmEsc($pmUnitLabel); ?></strong></div>
  <button class="pm-btn" type="button" data-pm-primary>Continue</button>
</div>

<script>
    // ── Funnel analytics: "form_started" ────────────────────────────────
    // KEPT INLINE, AND ON THIS FORM ID. local-dev/check-beacon-js.js extracts
    // this IIFE from the rendered page by the marker comment above and runs it
    // against a stub supplying only getElementById, FormData, navigator, fetch
    // and console. Moving it to an external file, renaming the form, or using
    // another browser API breaks verify.sh section 8i.
    (function () {
        var form = document.getElementById('standaloneRegForm');
        if (!form) { return; }

        var sent = false;

        function trackFormStarted() {
            if (sent) { return; }
            sent = true;
            form.removeEventListener('focusin', trackFormStarted);
            form.removeEventListener('input', trackFormStarted);

            try {
                var payload = new FormData();
                payload.append('event_type', 'form_started');
                payload.append('event_id', '<?php echo (int) $pmEvent['id']; ?>');
                var token = form.querySelector('input[name="csrf_token"]');
                if (token) { payload.append('csrf_token', token.value); }

                if (navigator.sendBeacon && navigator.sendBeacon('track-funnel-event.php', payload)) {
                    return;
                }

                // keepalive lets the request outlive the page.
                fetch('track-funnel-event.php', {
                    method: 'POST',
                    body: payload,
                    keepalive: true
                }).catch(function () { /* analytics only */ });
            } catch (e) { /* analytics only */ }
        }

        form.addEventListener('focusin', trackFormStarted);
        form.addEventListener('input', trackFormStarted);
    })();

    // ── Unfinished registrations ────────────────────────────────────────
    // Sends the step two identity fields once they are complete, so somebody
    // who leaves before the end can be sent a link back. Separate IIFE from the
    // funnel beacon above on purpose: check-beacon-js.js extracts that one by
    // its marker comment and runs it in isolation, and it must keep working
    // whether or not this exists.
    (function () {
        var form = document.getElementById('standaloneRegForm');
        if (!form) { return; }

        var FIELDS = ['first_name', 'last_name', 'organization', 'email', 'phone', 'country'];
        var lastSent = '';
        var timer = null;

        function value(name) {
            var field = form.querySelector('[name="' + name + '"]');
            return field ? String(field.value || '').trim() : '';
        }

        function capture() {
            var email = value('email');
            // The browser's own idea of a valid address. Nothing is stored
            // while somebody is still halfway through typing one.
            var field = form.querySelector('[name="email"]');
            if (!email || (field && field.checkValidity && !field.checkValidity())) { return; }

            var payload = new FormData();
            payload.append('event_id', '<?php echo (int) $pmEvent['id']; ?>');
            FIELDS.forEach(function (name) { payload.append(name, value(name)); });

            var current = document.querySelector('[data-pm-step][data-pm-current]');
            payload.append('last_step', current ? current.getAttribute('data-pm-step') : '2');

            var token = form.querySelector('input[name="csrf_token"]');
            if (token) { payload.append('csrf_token', token.value); }

            // One row per person per course, so resending an unchanged set is
            // only load. The step is included: moving forward is worth recording.
            var stamp = FIELDS.map(value).join('\u0001') + '\u0001' + payload.get('last_step');
            if (stamp === lastSent) { return; }
            lastSent = stamp;

            try {
                if (navigator.sendBeacon && navigator.sendBeacon('track-registration-resume.php', payload)) {
                    return;
                }
                fetch('track-registration-resume.php', {
                    method: 'POST', body: payload, keepalive: true
                }).catch(function () { /* best effort */ });
            } catch (e) { /* best effort */ }
        }

        function captureSoon() {
            if (timer) { clearTimeout(timer); }
            timer = setTimeout(capture, 800);
        }

        form.addEventListener('change', captureSoon);
        form.addEventListener('click', function (event) {
            if (event.target.closest('[data-pm-next], [data-pm-back]')) { captureSoon(); }
        });

        // The last chance to hear from somebody who is leaving.
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') { capture(); }
        });
    })();
</script>

<?php
pmPageEnd();
