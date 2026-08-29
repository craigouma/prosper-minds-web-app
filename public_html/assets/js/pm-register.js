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

    var STEP_COUNT = 4;              // 5 is the confirmation, reached by submitting
    var panels = form.querySelectorAll('[data-pm-step]');
    var progressItems = document.querySelectorAll('[data-pm-progress]');
    var stepCount = document.querySelector('[data-pm-stepcount]');
    var nav = form.querySelector('[data-pm-nav]');
    var backBtn = form.querySelector('[data-pm-back]');
    var nextBtn = form.querySelector('[data-pm-next]');
    var submitBtn = form.querySelector('[data-pm-submit]');
    var status = form.querySelector('[data-pm-status]');
    var delegateHolder = form.querySelector('[data-pm-delegates]');
    var upBtn = form.querySelector('[data-pm-step-up]');
    var downBtn = form.querySelector('[data-pm-step-down]');
    var countValue = form.querySelector('[data-pm-count-value]');
    var lineCount = document.querySelector('[data-pm-line-count]');
    var lineValue = document.querySelector('[data-pm-line-value]');
    var invoiceTotal = document.querySelector('[data-pm-invoice-total]');
    var reviewCount = form.querySelector('[data-pm-review-count]');
    var reviewTotal = form.querySelector('[data-pm-review-total]');
    var done = document.querySelector('[data-pm-done]');

    if (!panels.length || !nav || !nextBtn || !delegateHolder) {
      return;
    }

    var currentStep = 1;
    var delegateCount = 1;

    function reducedMotion() {
      return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function money(amount, code) {
      var value = parseFloat(amount);
      if (!isFinite(value)) { value = 0; }

      var parts = (Math.round(value * 100) / 100).toFixed(2).split('.');
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');

      return (code || currency) + ' ' + parts[0] + '.' + parts[1];
    }

    function delegateRows() {
      return delegateHolder.querySelectorAll('[data-pm-delegate]');
    }

    function rowFields(row) {
      return row.querySelectorAll('input, select, textarea');
    }

    // MUST equal what the handler charges: unit x count, no discount, no tier
    // multiplier. delegateCount is the number of enabled rows, which is exactly
    // what the POST carries.
    function renderTotal() {
      var total = unitAmount * delegateCount;
      var formatted = money(total);

      if (countValue) { countValue.textContent = String(delegateCount); }
      if (lineCount) { lineCount.textContent = String(delegateCount); }
      if (lineValue) { lineValue.textContent = formatted; }
      if (reviewCount) { reviewCount.textContent = String(delegateCount); }
      if (reviewTotal) { reviewTotal.textContent = formatted; }

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
          // Whole rows toggle together: the handler zips the four attendees
          // arrays by index, so a row contributes all four values or none.
          field.disabled = !active;

          if (field.name === 'attendees[first_name][]' || field.name === 'attendees[last_name][]') {
            if (active) {
              field.setAttribute('required', 'required');
            } else {
              field.removeAttribute('required');
            }
          }
        }

        var heading = row.querySelector('[data-pm-delegate-heading]');
        if (heading) { heading.textContent = 'Delegate ' + (i + 1); }

        var note = row.querySelector('[data-pm-delegate-note]');
        if (note) { note.textContent = 'Required'; }
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

    function showStatus(message, ok) {
      if (!status) { return; }

      status.textContent = message;
      if (ok) {
        status.classList.remove('pm-notice--error');
      } else {
        status.classList.add('pm-notice--error');
      }
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

      for (var p = 0; p < progressItems.length; p++) {
        var itemStep = parseInt(progressItems[p].getAttribute('data-pm-progress'), 10);
        progressItems[p].removeAttribute('data-pm-state');

        if (itemStep === step) {
          progressItems[p].setAttribute('data-pm-state', 'current');
        } else if (itemStep < step) {
          progressItems[p].setAttribute('data-pm-state', 'done');
        }
      }

      if (stepCount) {
        stepCount.textContent = 'Step ' + step + ' of ' + (STEP_COUNT + 1);
      }

      if (backBtn) { backBtn.hidden = step === 1; }
      // Step 4 carries the real submit button.
      nextBtn.hidden = step === STEP_COUNT;

      var panel = panelFor(step);
      if (panel && focus) {
        var heading = panel.querySelector('h2');
        if (heading) {
          heading.setAttribute('tabindex', '-1');
          heading.focus();
        }
        panel.scrollIntoView({ block: 'start', behavior: reducedMotion() ? 'auto' : 'smooth' });
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
        showStatus(
          firstBad.validationMessage || 'Please check the highlighted fields.',
          false
        );
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

    for (var p = 0; p < progressItems.length; p++) {
      progressItems[p].addEventListener('click', function (event) {
        var target = parseInt(this.getAttribute('data-pm-progress'), 10);

        // Step 5 is reachable by registering and by nothing else.
        if (!isFinite(target) || target > STEP_COUNT) { return; }

        event.preventDefault();
        clearStatus();
        showStep(target, true);
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

          source.addEventListener('input', function () {
            if (target.getAttribute('data-pm-dirty') === 'true') { return; }
            target.value = source.value;
          });
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
                'We could not complete the registration. Please try again, or email info@prosper-minds.com.',
              false
            );

            return;
          }

          confirmSuccess(data, submittedCount);
        })
        .catch(function () {
          showStatus(
            'We could not reach the server. Nothing has been submitted. Please try again, or email info@prosper-minds.com.',
            false
          );
        })
        .then(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = original;
          }
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
        }
      } catch (gtagError) {
        if (window.console && window.console.error) {
          window.console.error('GA4 purchase event failed (ignored):', gtagError);
        }
      }

      if (done) {
        var invoiceEl = done.querySelector('[data-pm-done-invoice]');
        var totalEl = done.querySelector('[data-pm-done-total]');
        var countEl = done.querySelector('[data-pm-done-count]');
        var messageEl = done.querySelector('[data-pm-done-message]');

        if (invoiceEl) { invoiceEl.textContent = data.invoice_number || ''; }
        if (totalEl) { totalEl.textContent = money(data.total_amount, data.currency_code); }
        if (countEl) { countEl.textContent = String(submittedCount); }
        // The handler's wording: only it knows whether the emails went out.
        if (messageEl && data.message) { messageEl.textContent = data.message; }

        done.hidden = false;
      }

      form.hidden = true;

      for (var p = 0; p < progressItems.length; p++) {
        var itemStep = parseInt(progressItems[p].getAttribute('data-pm-progress'), 10);
        progressItems[p].removeAttribute('data-pm-state');
        progressItems[p].setAttribute('data-pm-state', itemStep === STEP_COUNT + 1 ? 'current' : 'done');
      }

      if (stepCount) {
        stepCount.textContent = 'Step ' + (STEP_COUNT + 1) + ' of ' + (STEP_COUNT + 1);
      }

      if (done && typeof done.scrollIntoView === 'function') {
        done.scrollIntoView({ block: 'start', behavior: reducedMotion() ? 'auto' : 'smooth' });
      }
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
