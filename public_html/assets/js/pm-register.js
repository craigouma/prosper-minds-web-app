/*
 * Steps are a UI affordance over ONE form and ONE final POST. Nothing here
 * talks to the server before submit and no partial registration is persisted.
 * Step validation is a convenience; the server revalidates and is the only gate.
 */
(function () {
  'use strict';

  try {
    var root = document.documentElement;
    var form = document.querySelector('[data-pm-register]');

    if (!form || !window.fetch || !window.FormData) {
      return;
    }

    // Read, never re-parsed here: PHP does the one parse, matching the handler.
    var unitAmount = parseFloat(form.getAttribute('data-pm-unit-amount'));
    var currency = form.getAttribute('data-pm-currency') || 'USD';
    var maxDelegates = parseInt(form.getAttribute('data-pm-max'), 10);

    if (!isFinite(unitAmount)) { unitAmount = 0; }
    if (!isFinite(maxDelegates) || maxDelegates < 1) { maxDelegates = 20; }

    var STEP_COUNT = 2;
    var panels = form.querySelectorAll('[data-pm-step]');
    var backBtn = form.querySelector('[data-pm-back]');
    var nextBtn = form.querySelector('[data-pm-next]');
    var submitBtn = form.querySelector('[data-pm-submit]');
    var status = form.querySelector('[data-pm-status]');
    var delegateHolder = form.querySelector('[data-pm-delegates]');
    var upBtn = form.querySelector('[data-pm-step-up]');
    var downBtn = form.querySelector('[data-pm-step-down]');
    var countValue = form.querySelector('[data-pm-count-value]');
    var reviewCount = form.querySelector('[data-pm-review-count]');
    var reviewTotal = form.querySelector('[data-pm-review-total]');
    var summaryTier = form.querySelector('[data-pm-summary-tier]');
    var invoiceTotal = document.querySelector('[data-pm-invoice-total]');
    var barTotal = document.querySelector('[data-pm-bar-total]');
    var totalLines = document.querySelectorAll('[data-pm-total-line]');
    var primaryBtns = document.querySelectorAll('[data-pm-primary]');
    var done = document.querySelector('[data-pm-done]');
    var tierRadios = form.querySelectorAll('[data-pm-tier-radio]');
    var unitLabels = document.querySelectorAll('[data-pm-unit-label]');

    if (!panels.length || !nextBtn || !delegateHolder) {
      return;
    }

    var currentStep = 1;
    var delegateCount = 1;
    var checkedTier = form.querySelector('[data-pm-tier-radio]:checked');
    var tierName = checkedTier ? checkedTier.getAttribute('data-pm-tier-name') || 'Regular' : 'Regular';

    function reducedMotion() {
      return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    // Same shape as pmEventMoney() in PHP: cents only when there are cents.
    function money(amount, code) {
      var value = parseFloat(amount);
      if (!isFinite(value)) { value = 0; }

      value = Math.round(value * 100) / 100;
      var whole = Math.abs(value - Math.round(value)) < 0.005;
      var parts = value.toFixed(whole ? 0 : 2).split('.');
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');

      return (code || currency) + ' ' + parts.join('.');
    }

    function delegateRows() {
      return delegateHolder.querySelectorAll('[data-pm-delegate]');
    }

    function rowFields(row) {
      return row.querySelectorAll('input, select, textarea');
    }

    function setText(nodes, text) {
      for (var i = 0; i < nodes.length; i++) { nodes[i].textContent = text; }
    }

    // MUST equal what the handler charges: unit x count, no discount. unitAmount
    // already reflects the selected tier and delegateCount is the number of
    // enabled rows, which is exactly what the POST carries.
    function renderTotal() {
      var total = unitAmount * delegateCount;
      var formatted = money(total);
      var line = tierName + ', ' + delegateCount + (delegateCount === 1 ? ' delegate' : ' delegates');

      if (countValue) { countValue.textContent = String(delegateCount); }
      if (reviewCount) { reviewCount.textContent = String(delegateCount); }
      if (reviewTotal) { reviewTotal.textContent = formatted; }
      if (summaryTier) { summaryTier.textContent = tierName; }
      if (barTotal) { barTotal.textContent = formatted; }
      setText(totalLines, line);

      if (invoiceTotal) {
        invoiceTotal.textContent = formatted;
        // verify.sh compares this against total_amount in the database.
        invoiceTotal.setAttribute('data-pm-total-amount', (Math.round(total * 100) / 100).toFixed(2));
      }

      if (upBtn) { upBtn.disabled = delegateCount >= maxDelegates; }
      if (downBtn) { downBtn.disabled = delegateCount <= 1; }
    }

    // Rows above the count are DISABLED, not just hidden: a hidden input still
    // posts, and a row a visitor had typed into would be invoiced. Disabling
    // also exempts it from native validation.
    function syncRows() {
      var rows = delegateRows();

      for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var active = i < delegateCount;
        var fields = rowFields(row);

        row.hidden = !active;

        for (var f = 0; f < fields.length; f++) {
          var field = fields[f];
          field.disabled = !active;

          if (active) {
            field.setAttribute('required', 'required');
          } else {
            field.removeAttribute('required');
          }
        }

        var heading = row.querySelector('[data-pm-delegate-heading]');
        if (heading) { heading.textContent = 'Delegate ' + (i + 1); }

        var note = row.querySelector('[data-pm-delegate-note]');
        if (note) { note.textContent = i === 0 ? 'Filled from the billing contact' : ''; }
      }
    }

    function addRow() {
      var rows = delegateRows();
      var last = rows[rows.length - 1];
      if (!last) { return; }

      var clone = last.cloneNode(true);
      var index = rows.length;
      var fields = rowFields(clone);

      clone.setAttribute('data-pm-delegate', String(index));

      for (var f = 0; f < fields.length; f++) {
        var field = fields[f];
        field.value = '';
        field.removeAttribute('data-pm-dirty');
        field.removeAttribute('aria-invalid');

        if (field.id) {
          var newId = field.id.replace(/^pm-reg-d\d+-/, 'pm-reg-d' + index + '-');
          var label = clone.querySelector('label[for="' + field.id + '"]');
          field.id = newId;
          if (label) { label.setAttribute('for', newId); }
        }
      }

      delegateHolder.appendChild(clone);
    }

    function setDelegateCount(next) {
      if (next < 1) { next = 1; }
      if (next > maxDelegates) { next = maxDelegates; }

      while (delegateRows().length < next) {
        addRow();
      }

      delegateCount = next;
      syncRows();
      renderTotal();
    }

    function showStatus(message) {
      if (!status) { return; }

      status.textContent = message;
      status.hidden = false;
    }

    function clearStatus() {
      if (status) {
        status.hidden = true;
        status.textContent = '';
      }
    }

    function panelFor(step) {
      for (var i = 0; i < panels.length; i++) {
        if (panels[i].getAttribute('data-pm-step') === String(step)) {
          return panels[i];
        }
      }

      return null;
    }

    function showStep(step, focus) {
      if (step < 1) { step = 1; }
      if (step > STEP_COUNT) { step = STEP_COUNT; }

      currentStep = step;

      for (var i = 0; i < panels.length; i++) {
        if (panels[i].getAttribute('data-pm-step') === String(step)) {
          panels[i].setAttribute('data-pm-current', '');
        } else {
          panels[i].removeAttribute('data-pm-current');
        }
      }

      var label = step === STEP_COUNT ? 'Submit registration' : 'Continue';
      for (var b = 0; b < primaryBtns.length; b++) {
        primaryBtns[b].textContent = label;
      }

      var panel = panelFor(step);
      if (panel && focus) {
        var heading = panel.querySelector('h1, h2');
        if (heading) {
          heading.setAttribute('tabindex', '-1');
          heading.focus({ preventScroll: true });
        }
        window.scrollTo({ top: 0, behavior: reducedMotion() ? 'auto' : 'smooth' });
      }
    }

    function stepIsValid(step) {
      var panel = panelFor(step);
      if (!panel) { return true; }

      var fields = panel.querySelectorAll('input, select, textarea');
      var ok = true;
      var firstBad = null;

      for (var i = 0; i < fields.length; i++) {
        var field = fields[i];

        if (field.disabled || typeof field.checkValidity !== 'function') { continue; }

        if (field.checkValidity()) {
          field.removeAttribute('aria-invalid');
        } else {
          field.setAttribute('aria-invalid', 'true');
          ok = false;
          if (!firstBad) { firstBad = field; }
        }
      }

      if (!ok && firstBad) {
        showStatus(firstBad.validationMessage || 'Please check the highlighted fields.');
        // A field in a collapsed step is not focusable, so show the step first.
        if (step !== currentStep) { showStep(step, false); }
        try { firstBad.focus(); } catch (focusError) { /* not focusable, no matter */ }
      }

      return ok;
    }

    nextBtn.addEventListener('click', function () {
      clearStatus();
      if (!stepIsValid(currentStep)) { return; }
      showStep(currentStep + 1, true);
    });

    if (backBtn) {
      backBtn.addEventListener('click', function () {
        clearStatus();
        showStep(currentStep - 1, true);
      });
    }

    // The sticky bar and the desktop panel carry the one primary button. It
    // acts as whichever in-form button belongs to the current step.
    for (var pb = 0; pb < primaryBtns.length; pb++) {
      primaryBtns[pb].addEventListener('click', function () {
        if (currentStep < STEP_COUNT) {
          nextBtn.click();
        } else if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(submitBtn || undefined);
        } else {
          form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
      });
    }

    if (upBtn) {
      upBtn.addEventListener('click', function () {
        setDelegateCount(delegateCount + 1);
      });
    }

    if (downBtn) {
      downBtn.addEventListener('click', function () {
        setDelegateCount(delegateCount - 1);
      });
    }

    // The radio's own data carries the amount and its formatted label, both
    // rendered server-side from the event's real price columns: nothing here
    // computes or guesses a price, it only reflects the one already chosen.
    for (var t = 0; t < tierRadios.length; t++) {
      tierRadios[t].addEventListener('change', function (event) {
        var picked = event.target;
        var amount = parseFloat(picked.getAttribute('data-pm-tier-amount'));
        if (!isFinite(amount)) { return; }

        unitAmount = amount;
        tierName = picked.getAttribute('data-pm-tier-name') || tierName;
        setText(unitLabels, picked.getAttribute('data-pm-tier-label') || '');
        renderTotal();
      });
    }

    // Copies the billing contact into delegate 1, but only while that field is
    // still untouched, so it can never overwrite something a visitor typed.
    (function prefill() {
      var pairs = [
        ['first_name', 'attendees[first_name][]'],
        ['last_name', 'attendees[last_name][]'],
        ['email', 'attendees[email][]']
      ];

      var firstRow = delegateRows()[0];
      if (!firstRow) { return; }

      for (var i = 0; i < pairs.length; i++) {
        (function (billingName, delegateName) {
          var source = form.querySelector('[name="' + billingName + '"]');
          var target = firstRow.querySelector('[name="' + delegateName + '"]');

          if (!source || !target) { return; }

          target.addEventListener('input', function () {
            target.setAttribute('data-pm-dirty', 'true');
          });

          function copy() {
            if (target.getAttribute('data-pm-dirty') === 'true') { return; }
            target.value = source.value;
          }

          source.addEventListener('input', copy);
          // Browser autofill can fill without an input event on some phones.
          source.addEventListener('change', copy);
          copy();
        })(pairs[i][0], pairs[i][1]);
      }
    })();

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      clearStatus();

      for (var s = 1; s <= STEP_COUNT; s++) {
        if (!stepIsValid(s)) { return; }
      }

      var body = new FormData(form);
      var submittedCount = delegateCount;
      var original = submitBtn ? submitBtn.textContent : '';

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = submitBtn.getAttribute('data-pm-sending') || 'Submitting';
      }

      for (var d = 0; d < primaryBtns.length; d++) { primaryBtns[d].disabled = true; }

      fetch(form.getAttribute('action'), { method: 'POST', body: body })
        .then(function (response) {
          // A non-2xx answer is a failure even if the body happens to parse.
          if (!response.ok) { throw new Error('HTTP ' + response.status); }

          return response.json();
        })
        .then(function (data) {
          // THE ONLY BRANCH THAT MAY REPORT SUCCESS. data.success is set by the
          // handler after the transaction committed. Nothing is inferred here.
          if (!data || data.success !== true) {
            showStatus(
              (data && data.message) ||
                'We could not complete the registration. Please try again, or email info@prosper-minds.com.'
            );

            return;
          }

          confirmSuccess(data, submittedCount);
        })
        .catch(function () {
          showStatus(
            'We could not reach the server. Nothing has been submitted. Please try again, or email info@prosper-minds.com.'
          );
        })
        .then(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = original;
          }

          for (var e = 0; e < primaryBtns.length; e++) { primaryBtns[e].disabled = false; }
        });
    });

    /** Every figure shown comes from the response, never re-derived here. */
    function confirmSuccess(data, submittedCount) {
      // Isolated, and ahead of the confirmation, so a broken tag cannot cost a
      // delegate the confirmation they just earned.
      try {
        if (typeof window.gtag === 'function') {
          window.gtag('event', 'purchase', {
            transaction_id: data.invoice_number,
            value: parseFloat(data.total_amount),
            currency: data.currency_code,
            items: [{
              // item_id and price are required for GA4's ecommerce schema to be
              // valid, and for Google Ads' import validation.
              item_id: 'event-' + (form.querySelector('[name="event_id"]') || {}).value,
              item_name: (form.querySelector('[name="event_name"]') || {}).value,
              price: parseFloat(data.unit_price_amount),
              quantity: submittedCount
            }]
          });

          // The Google Ads conversion action, sent as its own hit rather than
          // left to the GA4 import. Fires ONLY when includes/google-tag.php
          // defines the label; removing that one line turns this off, which is
          // what stops it double counting against the GA4-imported action.
          if (window.pmAdsPurchaseConversion) {
            window.gtag('event', 'conversion', {
              send_to: window.pmAdsPurchaseConversion,
              value: parseFloat(data.total_amount),
              currency: data.currency_code,
              // Never blank. An empty transaction_id makes Google count a
              // second conversion if the visitor reloads the confirmation.
              transaction_id: data.invoice_number
            });
          }
        }
      } catch (gtagError) {
        if (window.console && window.console.error) {
          window.console.error('Conversion tracking failed (ignored):', gtagError);
        }
      }

      if (done) {
        var invoiceEl = done.querySelector('[data-pm-done-invoice]');
        var messageEl = done.querySelector('[data-pm-done-message]');
        var emailField = form.querySelector('[name="email"]');

        if (invoiceEl) { invoiceEl.textContent = data.invoice_number || ''; }
        // The handler's wording: only it knows whether the emails went out.
        if (messageEl) {
          messageEl.textContent = data.message ||
            ('Your invoice has been emailed to ' + (emailField ? emailField.value : 'the billing contact') + '.');
        }

        done.hidden = false;
      }

      form.hidden = true;

      var aside = document.querySelector('.pm-reg__aside');
      var bar = document.querySelector('[data-pm-bar]');
      if (aside) { aside.hidden = true; }
      if (bar) { bar.hidden = true; }

      window.scrollTo({ top: 0, behavior: reducedMotion() ? 'auto' : 'smooth' });

      var doneHeading = done ? done.querySelector('h1') : null;
      if (doneHeading) { doneHeading.focus({ preventScroll: true }); }
    }

    // Only now: a browser refusing to submit over a required field inside a
    // collapsed step would look like a dead button.
    form.noValidate = true;

    setDelegateCount(1);
    showStep(1, false);

    // LAST, after every listener is attached. Until this is set the stylesheet
    // leaves the page as one long form that posts on its own, so a throw
    // anywhere above costs the step navigation and not the registration.
    root.setAttribute('data-pm-steps', 'on');
  } catch (error) {
    if (window.console && window.console.warn) {
      window.console.warn('pm-register: step flow unavailable, form left in its plain state', error);
    }
  }
})();
