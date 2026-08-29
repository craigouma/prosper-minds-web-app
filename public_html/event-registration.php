<?php

require_once __DIR__ . '/includes/layout/page.php';

/*
 * FIELD NAMES ARE FIXED BY process-registration.php AND MUST NOT BE RENAMED.
 * Renaming one empties that column on every future registration, silently, and
 * the failure looks like success:
 *
 *   csrf_token, event_id, event_name, first_name, last_name, phone, email,
 *   organization, country, address, gender, meal_preference, future_topics,
 *   consent, and attendees[first_name][] / [last_name][] / [email][] / [title][]
 *
 * The handler zips the four attendees arrays by index, so a delegate row must
 * contribute all four fields or none. A row whose four values are all empty is
 * skipped, which is what lets the undriven form below ship five rows and have a
 * visitor fill in only the first.
 */

/**
 * Mirrors parseEventPrice() in includes/invoice.php, both patterns exactly.
 * Restated rather than reused because invoice.php requires vendor/autoload.php,
 * and this page must still render when the vendor tree is incomplete.
 * verify.sh section 12 asserts the two have not drifted.
 *
 * @return array{0: string, 1: float}
 */
function pmRegisterUnitPrice(string $priceText): array
{
    $priceText = trim($priceText);
    $currency = 'USD';
    $amount = 0.0;

    if (preg_match('/(?<![A-Za-z])([A-Z]{3})(?![A-Za-z])/', $priceText, $currencyMatch)) {
        $currency = $currencyMatch[1];
    }

    if (preg_match('/(\d[\d,]*(?:\.\d{1,2})?)/', $priceText, $amountMatch)) {
        $amount = (float) str_replace(',', '', $amountMatch[1]);
    }

    return [$currency, $amount];
}

function pmRegisterMoney(string $currency, float $amount): string
{
    return $currency . ' ' . number_format($amount, 2);
}

/** Real rows in the markup, not a template, so the undriven form is completable. */
const PM_REG_ROWS = 5;
const PM_REG_MAX = 20;

$pmEventId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pmEvent   = pmEventById($pdo, $pmEventId);

// is_active mirrors the handler's own WHERE clause. A form the handler will
// refuse is a form that fails after everything has been typed in.
if ($pmEvent === null || (int) ($pmEvent['is_active'] ?? 0) !== 1) {
    header('Location: /events.php');
    exit;
}

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

$pmTitle    = pmEventProse((string) ($pmEvent['title'] ?? ''));
$pmLocation = pmEventProse((string) ($pmEvent['location'] ?? ''));
$pmDates    = pmEventDatesLong($pmEvent);

[$pmCurrency, $pmUnitAmount] = pmRegisterUnitPrice((string) ($pmEvent['price'] ?? ''));
$pmUnitLabel  = pmRegisterMoney($pmCurrency, $pmUnitAmount);
$pmTotalLabel = pmRegisterMoney($pmCurrency, $pmUnitAmount);

$pmSteps = pmContentJson($pdo, 'register', 'steps', [
    ['num' => '01', 'label' => 'Event and tickets'],
    ['num' => '02', 'label' => 'Contact and billing'],
    ['num' => '03', 'label' => 'Delegates'],
    ['num' => '04', 'label' => 'Review and consent'],
    ['num' => '05', 'label' => 'Confirmation'],
]);

pmPageBegin([
    'slug'        => 'register',
    'nav'         => 'events',
    'title'       => 'Register a delegate: ' . $pmTitle,
    'description' => 'Register delegates for ' . $pmTitle . ', ' . $pmDates . ', ' . $pmLocation
                     . '. Invoiced to your institution, payable by bank transfer or purchase order.',
    'canonical'   => '/event-registration.php?id=' . (int) $pmEvent['id'],
    'scripts'     => ['/assets/js/pm-register.js'],
]);
?>

<section class="pm-section pm-section--tight">
  <div class="pm-container">

    <div class="pm-reg__head">
      <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'register', 'eyebrow',
        'Delegate registration'); ?></span>
      <span class="pm-reg__stepcount" data-pm-stepcount role="status">Step 1 of <?php
        echo count($pmSteps); ?></span>
    </div>

    <h1 class="pm-h1"><?php echo pmEsc($pmTitle); ?></h1>

    <?php // Real anchors, so undriven they are a table of contents into a form
          // whose sections are all on screen. ?>
    <ol class="pm-reg__progress pm-mt-lg">
<?php foreach ($pmSteps as $pmIndex => $pmStep): ?>
      <li>
        <a class="pm-reg__progress-item"
           href="#pm-reg-step-<?php echo (int) $pmIndex + 1; ?>"
           data-pm-progress="<?php echo (int) $pmIndex + 1; ?>"
           <?php echo $pmIndex === 0 ? 'data-pm-state="current"' : ''; ?>>
          <span class="pm-reg__progress-num"><?php echo pmEsc((string) ($pmStep['num'] ?? '')); ?></span>
          <span class="pm-reg__progress-name"><?php echo pmEsc((string) ($pmStep['label'] ?? '')); ?></span>
        </a>
      </li>
<?php endforeach; ?>
    </ol>

  </div>
</section>


<section class="pm-section pm-section--tight">
  <div class="pm-container pm-row">

    <div class="pm-row__main">

      <?php // Hidden by attribute, not by class, so it stays hidden with the
            // stylesheet absent. pm-register.js reveals it only from the branch
            // where the server answered success:true. ?>
      <div class="pm-card pm-card--dark" id="pm-reg-step-5" data-pm-done hidden>
        <div class="pm-reg__done-head">
          <span class="pm-reg__done-mark" aria-hidden="true">&#10003;</span>
          <span class="pm-label"><?php echo pmContentSafe($pdo, 'register', 'done_eyebrow',
            'Registration received'); ?></span>
        </div>

        <h2 class="pm-h2" data-pm-done-title><?php echo pmContentSafe($pdo, 'register', 'done_title',
          'Your place is confirmed'); ?></h2>

        <p class="pm-body" data-pm-done-message><?php echo pmContentSafe($pdo, 'register', 'done_body',
          'Your invoice has been generated and emailed to the billing contact, together with the joining instructions.'); ?></p>

        <div class="pm-reg__done-grid">
          <div class="pm-reg__done-cell">
            <span class="pm-reg__done-label"><?php echo pmContentSafe($pdo, 'register', 'done_label_invoice',
              'Invoice number'); ?></span>
            <span class="pm-reg__done-value pm-reg__done-value--green" data-pm-done-invoice></span>
          </div>
          <div class="pm-reg__done-cell">
            <span class="pm-reg__done-label"><?php echo pmContentSafe($pdo, 'register', 'done_label_amount',
              'Amount due'); ?></span>
            <span class="pm-reg__done-value" data-pm-done-total></span>
          </div>
          <div class="pm-reg__done-cell">
            <span class="pm-reg__done-label"><?php echo pmContentSafe($pdo, 'register', 'done_label_count',
              'Delegates'); ?></span>
            <span class="pm-reg__done-value" data-pm-done-count></span>
          </div>
        </div>

        <?php // No "Download invoice" button, unlike the prototype:
              // assets/invoices/ is served publicly and invoice numbers are
              // guessable, so a link here would advertise a path to every other
              // delegate's invoice. The PDF is emailed instead. ?>
        <p class="pm-caption"><?php echo pmContentSafe($pdo, 'register', 'done_help',
          'Need help right away? Call +254 740 582302 or +254 741 174909, or email info@prosper-minds.com.'); ?></p>

        <div class="pm-btn-row">
          <a class="pm-btn" href="/events.php"><?php echo pmContentSafe($pdo, 'register', 'done_cta',
            'Back to the calendar'); ?></a>
        </div>
      </div>

      <?php // One form, one POST. action and method are real so the browser can
            // post it with no script at all. ?>
      <form id="standaloneRegForm"
            class="pm-stack pm-stack--lg"
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

        <div class="pm-notice" id="pm-reg-status" data-pm-status role="status" hidden></div>

        <p class="pm-caption pm-reg__undriven"><?php echo pmContentSafe($pdo, 'register', 'undriven_note',
          'All four sections are on this page. Fill them in and submit once at the bottom.'); ?></p>


        <section class="pm-reg__panel" id="pm-reg-step-1" data-pm-step="1" data-pm-current
                 aria-labelledby="pm-reg-step-1-title">
          <h2 class="pm-h3 pm-h3--caps" id="pm-reg-step-1-title"><?php
            echo pmContentSafe($pdo, 'register', 'step1_title', 'Confirm event and tickets'); ?></h2>
          <p class="pm-body pm-measure pm-mt-sm"><?php echo pmContentSafe($pdo, 'register', 'step1_body',
            'Check the school and the dates, then set the number of delegates. The invoice summary updates as you go.'); ?></p>

          <div class="pm-card pm-mt-md">
            <span class="pm-label"><?php echo pmContentSafe($pdo, 'register', 'school_label',
              'Selected school'); ?></span>
            <h3 class="pm-h4"><?php echo pmEsc($pmTitle); ?></h3>
            <p class="pm-body">
              <?php echo pmEsc($pmLocation); ?><br>
              <?php echo pmEsc($pmDates); ?>
            </p>
            <p class="pm-body"><?php echo pmEsc($pmUnitLabel); ?> <?php
              echo pmContentSafe($pdo, 'register', 'per_delegate', 'per delegate'); ?></p>
            <p>
              <a class="pm-btn--link" href="/events.php"><?php echo pmContentSafe($pdo, 'register', 'change_school',
                'Change school'); ?></a>
            </p>
          </div>

          <?php // No tier selector, unlike the prototype. The handler charges one
                // flat unit price and pricing is deferred to Phase 5, so a
                // selector that changed the displayed price would not change the
                // invoice. ?>
          <p class="pm-caption pm-mt-md"><?php echo pmContentSafe($pdo, 'register', 'tier_note',
            'Every place is invoiced at the standard delegate rate shown above. For VIP or VVIP arrangements, contact info@prosper-minds.com before registering.'); ?></p>

          <p class="pm-label pm-mt-lg" id="pm-reg-count-label"><?php
            echo pmContentSafe($pdo, 'register', 'count_label', 'Number of delegates'); ?></p>

          <div class="pm-reg__stepper pm-mt-sm" data-pm-stepper
               role="group" aria-labelledby="pm-reg-count-label">
            <button type="button" class="pm-reg__stepper-btn" data-pm-step-down
                    aria-label="One delegate fewer">&minus;</button>
            <span class="pm-reg__stepper-value" data-pm-count-value aria-live="polite">1</span>
            <button type="button" class="pm-reg__stepper-btn" data-pm-step-up
                    aria-label="One delegate more">+</button>
          </div>

          <p class="pm-caption pm-mt-sm pm-reg__undriven"><?php echo pmContentSafe($pdo, 'register', 'count_undriven',
            'The number of delegates is however many you name in section 03 below. Leave the rest blank.'); ?></p>
        </section>


        <section class="pm-reg__panel" id="pm-reg-step-2" data-pm-step="2"
                 aria-labelledby="pm-reg-step-2-title">
          <h2 class="pm-h3 pm-h3--caps" id="pm-reg-step-2-title"><?php
            echo pmContentSafe($pdo, 'register', 'step2_title', 'Contact and billing'); ?></h2>
          <p class="pm-body pm-measure pm-mt-sm"><?php echo pmContentSafe($pdo, 'register', 'step2_body',
            'The invoice is issued to the institution named here. This is also the address the confirmation and joining instructions are sent to.'); ?></p>

          <?php // Only fields the handler stores. The prototype also shows
                // Department, Job title and a PO reference; nothing reads them,
                // and a field whose contents are discarded is worse than none. ?>
          <div class="pm-grid pm-grid--2 pm-mt-md">
            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-first"><?php
                echo pmContentSafe($pdo, 'register', 'label_first', 'Billing contact first name'); ?></label>
              <input class="pm-input" type="text" id="pm-reg-first" name="first_name"
                     autocomplete="given-name" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-last"><?php
                echo pmContentSafe($pdo, 'register', 'label_last', 'Billing contact last name'); ?></label>
              <input class="pm-input" type="text" id="pm-reg-last" name="last_name"
                     autocomplete="family-name" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-org"><?php
                echo pmContentSafe($pdo, 'register', 'label_org', 'Institution'); ?></label>
              <input class="pm-input" type="text" id="pm-reg-org" name="organization"
                     autocomplete="organization"
                     placeholder="Ministry, county or authority" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-email"><?php
                echo pmContentSafe($pdo, 'register', 'label_email', 'Email'); ?></label>
              <input class="pm-input" type="email" id="pm-reg-email" name="email"
                     autocomplete="email" placeholder="name@institution.go.ke" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-phone"><?php
                echo pmContentSafe($pdo, 'register', 'label_phone', 'Phone'); ?></label>
              <?php // pattern restates the handler's own check so the browser
                    // refuses what the server would. ?>
              <input class="pm-input" type="tel" id="pm-reg-phone" name="phone"
                     autocomplete="tel" placeholder="+254 700 000000"
                     pattern="[\d\+\-\s\(\)]{8,20}"
                     title="8 to 20 characters, digits and + - ( ) only" required>
            </div>

            <div class="pm-field">
              <label class="pm-field__label" for="pm-reg-country"><?php
                echo pmContentSafe($pdo, 'register', 'label_country', 'Country'); ?></label>
              <input class="pm-input" type="text" id="pm-reg-country" name="country"
                     autocomplete="country-name" placeholder="Kenya" required>
            </div>
          </div>

          <div class="pm-field pm-mt-md">
            <label class="pm-field__label" for="pm-reg-address"><?php
              echo pmContentSafe($pdo, 'register', 'label_address', 'Billing address'); ?></label>
            <span class="pm-field__hint" id="pm-reg-address-hint"><?php
              echo pmContentSafe($pdo, 'register', 'hint_address',
                'The address that should appear on the invoice.'); ?></span>
            <textarea class="pm-textarea" id="pm-reg-address" name="address" rows="3"
                      aria-describedby="pm-reg-address-hint" required></textarea>
          </div>

          <div class="pm-field pm-mt-md">
            <label class="pm-field__label" for="pm-reg-gender"><?php
              echo pmContentSafe($pdo, 'register', 'label_gender', 'Gender (optional)'); ?></label>
            <select class="pm-select" id="pm-reg-gender" name="gender">
              <option value="">Prefer not to say</option>
              <option value="Female">Female</option>
              <option value="Male">Male</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </section>


        <section class="pm-reg__panel" id="pm-reg-step-3" data-pm-step="3"
                 aria-labelledby="pm-reg-step-3-title">
          <h2 class="pm-h3 pm-h3--caps" id="pm-reg-step-3-title"><?php
            echo pmContentSafe($pdo, 'register', 'step3_title', 'Delegate details'); ?></h2>
          <p class="pm-body pm-measure pm-mt-sm"><?php echo pmContentSafe($pdo, 'register', 'step3_body',
            'Names are printed on certificates and used for visa support letters, so enter them as they appear on each passport.'); ?></p>

          <div class="pm-stack pm-stack--md pm-mt-md" data-pm-delegates>
<?php for ($pmRow = 0; $pmRow < PM_REG_ROWS; $pmRow++): ?>
            <?php // Rows after the first are optional here so the undriven form
                  // can be submitted with one delegate. pm-register.js disables
                  // rows above the chosen count; hiding alone would still post
                  // them. ?>
            <div class="pm-card" data-pm-delegate="<?php echo $pmRow; ?>">
              <div class="pm-reg__delegate-head">
                <span class="pm-label" data-pm-delegate-heading>Delegate <?php echo $pmRow + 1; ?></span>
                <span class="pm-reg__delegate-note" data-pm-delegate-note><?php
                  echo $pmRow === 0 ? 'Required' : 'Optional'; ?></span>
              </div>

              <div class="pm-grid pm-grid--2">
                <div class="pm-field">
                  <label class="pm-field__label" for="pm-reg-d<?php echo $pmRow; ?>-first"><?php
                    echo pmContentSafe($pdo, 'register', 'label_d_first', 'First name'); ?></label>
                  <input class="pm-input" type="text"
                         id="pm-reg-d<?php echo $pmRow; ?>-first"
                         name="attendees[first_name][]"
                         <?php echo $pmRow === 0 ? 'required' : ''; ?>>
                </div>

                <div class="pm-field">
                  <label class="pm-field__label" for="pm-reg-d<?php echo $pmRow; ?>-last"><?php
                    echo pmContentSafe($pdo, 'register', 'label_d_last', 'Last name'); ?></label>
                  <input class="pm-input" type="text"
                         id="pm-reg-d<?php echo $pmRow; ?>-last"
                         name="attendees[last_name][]"
                         <?php echo $pmRow === 0 ? 'required' : ''; ?>>
                </div>

                <div class="pm-field">
                  <label class="pm-field__label" for="pm-reg-d<?php echo $pmRow; ?>-email"><?php
                    echo pmContentSafe($pdo, 'register', 'label_d_email', 'Email'); ?></label>
                  <input class="pm-input" type="email"
                         id="pm-reg-d<?php echo $pmRow; ?>-email"
                         name="attendees[email][]">
                </div>

                <div class="pm-field">
                  <label class="pm-field__label" for="pm-reg-d<?php echo $pmRow; ?>-title"><?php
                    echo pmContentSafe($pdo, 'register', 'label_d_title', 'Job title'); ?></label>
                  <input class="pm-input" type="text"
                         id="pm-reg-d<?php echo $pmRow; ?>-title"
                         name="attendees[title][]"
                         placeholder="e.g. Budget Controller">
                </div>
              </div>
            </div>
<?php endfor; ?>
          </div>

          <div class="pm-field pm-mt-md">
            <label class="pm-field__label" for="pm-reg-meal"><?php
              echo pmContentSafe($pdo, 'register', 'label_meal', 'Meal preference for the group'); ?></label>
            <span class="pm-field__hint" id="pm-reg-meal-hint"><?php
              echo pmContentSafe($pdo, 'register', 'hint_meal',
                'One preference is recorded per registration. Tell us about individual requirements in the box below and we will arrange them.'); ?></span>
            <select class="pm-select" id="pm-reg-meal" name="meal_preference"
                    aria-describedby="pm-reg-meal-hint">
              <option value="">No preference</option>
              <option value="Standard">Standard</option>
              <option value="Vegetarian">Vegetarian</option>
              <option value="Vegan">Vegan</option>
              <option value="Halal">Halal</option>
              <option value="Gluten-Free">Gluten free</option>
            </select>
          </div>
        </section>


        <section class="pm-reg__panel" id="pm-reg-step-4" data-pm-step="4"
                 aria-labelledby="pm-reg-step-4-title">
          <h2 class="pm-h3 pm-h3--caps" id="pm-reg-step-4-title"><?php
            echo pmContentSafe($pdo, 'register', 'step4_title', 'Review and consent'); ?></h2>
          <p class="pm-body pm-measure pm-mt-sm"><?php echo pmContentSafe($pdo, 'register', 'step4_body',
            'Submitting generates a numbered invoice and emails it to the billing contact with the joining instructions.'); ?></p>

          <div class="pm-reg__review pm-mt-md">
            <div class="pm-reg__review-row">
              <span class="pm-reg__review-label"><?php echo pmContentSafe($pdo, 'register', 'review_school',
                'School'); ?></span>
              <span class="pm-reg__review-value"><?php echo pmEsc($pmTitle); ?></span>
            </div>
            <div class="pm-reg__review-row">
              <span class="pm-reg__review-label"><?php echo pmContentSafe($pdo, 'register', 'review_dates',
                'Dates'); ?></span>
              <span class="pm-reg__review-value"><?php echo pmEsc($pmDates); ?>, <?php
                echo pmEsc($pmLocation); ?></span>
            </div>
            <div class="pm-reg__review-row">
              <span class="pm-reg__review-label"><?php echo pmContentSafe($pdo, 'register', 'review_delegates',
                'Delegates'); ?></span>
              <span class="pm-reg__review-value" data-pm-review-count>1</span>
            </div>
            <div class="pm-reg__review-row">
              <span class="pm-reg__review-label"><?php echo pmContentSafe($pdo, 'register', 'review_payable',
                'Payable'); ?></span>
              <span class="pm-reg__review-value" data-pm-review-total><?php
                echo pmEsc($pmTotalLabel); ?></span>
            </div>
          </div>

          <div class="pm-field pm-mt-md">
            <label class="pm-field__label" for="pm-reg-topics"><?php
              echo pmContentSafe($pdo, 'register', 'label_topics', 'Topics you would like to see in future'); ?></label>
            <textarea class="pm-textarea" id="pm-reg-topics" name="future_topics" rows="3"
                      placeholder="Optional"></textarea>
          </div>

          <?php // One checkbox, because `consent` is the one thing the handler
                // stores. A second box would post a field nothing reads. ?>
          <div class="pm-mt-md">
            <label class="pm-check" for="pm-reg-consent">
              <input type="checkbox" id="pm-reg-consent" name="consent" value="yes" required>
              <span><?php echo pmContentSafe($pdo, 'register', 'consent_text',
                'I confirm the institution authorises this registration, and I consent to Prosperminds processing these details for invoicing, certification, visa support letters and course administration.'); ?></span>
            </label>
          </div>

          <p class="pm-caption pm-mt-sm"><?php echo pmContentSafe($pdo, 'register', 'consent_note_html',
            'We use these details only to run this registration and the course. See our <a href="/privacy-policy.php">privacy policy</a>.', true); ?></p>

          <div class="pm-btn-row pm-mt-lg">
            <button class="pm-btn" type="submit" data-pm-submit
                    data-pm-sending="<?php echo pmContentSafe($pdo, 'register', 'submit_sending',
                      'Submitting'); ?>"><?php echo pmContentSafe($pdo, 'register', 'submit',
              'Complete registration'); ?></button>
          </div>
        </section>


        <?php // Absent unless pm-register.js is driving, because Back and
              // Continue do nothing without it. ?>
        <div class="pm-reg__nav" data-pm-nav>
          <button type="button" class="pm-btn pm-btn--secondary" data-pm-back><?php
            echo pmContentSafe($pdo, 'register', 'nav_back', 'Back'); ?></button>
          <button type="button" class="pm-btn pm-reg__nav-next" data-pm-next><?php
            echo pmContentSafe($pdo, 'register', 'nav_next', 'Continue'); ?></button>
        </div>

      </form>
    </div>

    <?php // THE FIGURES HERE MUST EQUAL WHAT THE HANDLER CHARGES: unit price x
          // delegates, no deduction, matching
          // $totalAmount = $unitPriceAmount * $attendeeCount. The prototype's
          // "Early bird, 20 per cent" line is deliberately absent because the
          // handler applies no discount. data-pm-total-amount is the
          // machine-readable twin verify.sh compares against the stored total. ?>
    <aside class="pm-reg__aside" aria-labelledby="pm-reg-summary-head">
      <div class="pm-reg__sticky">
        <div class="pm-reg__summary">
          <span class="pm-reg__summary-head" id="pm-reg-summary-head"><?php
            echo pmContentSafe($pdo, 'register', 'summary_head', 'Invoice summary'); ?></span>

          <div class="pm-reg__line">
            <span class="pm-reg__line-label" data-pm-line-label><?php
              echo pmContentSafe($pdo, 'register', 'summary_line', 'Delegate place'); ?> x <span
              data-pm-line-count>1</span></span>
            <span class="pm-reg__line-value" data-pm-line-value><?php echo pmEsc($pmTotalLabel); ?></span>
          </div>

          <div class="pm-reg__line">
            <span class="pm-reg__line-label"><?php echo pmContentSafe($pdo, 'register', 'summary_unit',
              'Unit price, per delegate'); ?></span>
            <span class="pm-reg__line-value"><?php echo pmEsc($pmUnitLabel); ?></span>
          </div>

          <div class="pm-reg__total">
            <span class="pm-reg__total-label"><?php echo pmContentSafe($pdo, 'register', 'summary_total',
              'Total'); ?></span>
            <span class="pm-reg__total-value"
                  data-pm-invoice-total
                  data-pm-total-amount="<?php echo pmEsc(number_format($pmUnitAmount, 2, '.', '')); ?>"
                  data-pm-currency="<?php echo pmEsc($pmCurrency); ?>"><?php
              echo pmEsc($pmTotalLabel); ?></span>
          </div>
        </div>

        <p class="pm-caption pm-mt-md"><?php echo pmContentSafe($pdo, 'register', 'summary_note',
          'Payment by bank transfer or institutional purchase order. No card details are collected on this site.'); ?></p>
      </div>
    </aside>

  </div>
</section>

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
</script>

<?php
pmPageEnd();
