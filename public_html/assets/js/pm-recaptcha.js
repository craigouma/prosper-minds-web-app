/*
 * Google reCAPTCHA v3 for every guarded form on the site.
 *
 * v3 shows the visitor nothing. When a form is submitted this asks Google for a
 * token, puts it in the form's hidden g-recaptcha-response field, and lets the
 * submission carry on, whether that is a plain post or one of the site's own
 * fetch-based forms. The server then asks Google how likely the token is to be
 * a person.
 *
 * A VISITOR IS NEVER BLOCKED HERE
 * -------------------------------
 * The hidden pm_rc field is rendered as "unavailable" and only this file clears
 * it, after a token has been obtained. If Google's script is blocked, slow, or
 * the browser is too old for this file to run at all, the form still submits and
 * says so, and the server falls back to its other checks. This file never
 * decides anything and never stops a submission for its own failure.
 *
 * Google's script loads on the first touch of a form, or at submit, not on every
 * page view: the footer newsletter form is on every page.
 */
(function () {
  'use strict';

  try {
    var tag = document.querySelector('script[data-site-key]');
    var siteKey = tag ? tag.getAttribute('data-site-key') : '';

    if (!siteKey || !window.Promise) {
      return;
    }

    var LIMIT = 6000;
    var loading = null;

    function within(promise, ms) {
      return new Promise(function (resolve, reject) {
        var timer = setTimeout(function () { reject(new Error('timeout')); }, ms);

        promise.then(
          function (value) { clearTimeout(timer); resolve(value); },
          function (error) { clearTimeout(timer); reject(error); }
        );
      });
    }

    function load() {
      if (loading) {
        return loading;
      }

      loading = within(new Promise(function (resolve, reject) {
        var el = document.createElement('script');

        el.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(siteKey);
        el.async = true;
        el.onerror = function () { reject(new Error('blocked')); };
        el.onload = function () {
          if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
            window.grecaptcha.ready(resolve);
          } else {
            reject(new Error('missing'));
          }
        };
        document.head.appendChild(el);
      }), LIMIT);

      // A failure is not remembered, so a later attempt can still succeed.
      loading.catch(function () { loading = null; });

      return loading;
    }

    function token(action) {
      return load().then(function () {
        return within(Promise.resolve(window.grecaptcha.execute(siteKey, { action: action })), LIMIT);
      });
    }

    function guarded(form) {
      return form && form.querySelector ? form.querySelector('input[name="g-recaptcha-response"]') : null;
    }

    // Warm Google's script up as soon as somebody starts on a form.
    function warm(event) {
      if (guarded(event.target && event.target.form)) {
        load().catch(function () { /* nothing to do: the submit will say so */ });
      }
    }
    document.addEventListener('focusin', warm);
    document.addEventListener('pointerdown', function (event) {
      var form = event.target && event.target.closest ? event.target.closest('form') : null;

      if (guarded(form)) {
        load().catch(function () { /* see above */ });
      }
    });

    // Google's terms require this wording in the flow where the badge is hidden.
    function addNotice(form) {
      if (form.querySelector('.pm-recaptcha-note')) {
        return;
      }

      var button = form.querySelector('[type="submit"]');
      var note = document.createElement('p');

      note.className = 'pm-caption pm-recaptcha-note';
      note.innerHTML = 'Protected by reCAPTCHA. The Google ' +
        '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and ' +
        '<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">Terms of Service</a> apply.';

      if (button && button.parentNode) {
        button.parentNode.insertBefore(note, button.nextSibling);
      } else {
        form.appendChild(note);
      }
    }

    Array.prototype.forEach.call(document.querySelectorAll('form'), function (form) {
      if (guarded(form)) {
        addNotice(form);
      }
    });

    function resubmit(form, submitter) {
      // Marked so the listener below lets this one straight through. Cleared
      // afterwards whatever happened: requestSubmit() fires no event at all if the
      // browser's own validation stops it, and a flag left behind would let the
      // visitor's next, real submit skip the token.
      form.__pmRecaptchaPass = true;

      try {
        if (typeof form.requestSubmit === 'function') {
          if (submitter) {
            form.requestSubmit(submitter);
          } else {
            form.requestSubmit();
          }

          return;
        }

        // Older browsers: fire the event so the site's own scripted forms run,
        // and submit natively if nothing cancelled it.
        var again = new Event('submit', { bubbles: true, cancelable: true });

        if (form.dispatchEvent(again)) {
          form.submit();
        }
      } finally {
        form.__pmRecaptchaPass = false;
      }
    }

    // Capture phase on the document: runs before the site's own submit handlers.
    document.addEventListener('submit', function (event) {
      var form = event.target;
      var field = guarded(form);

      if (!field) {
        return;
      }

      if (form.__pmRecaptchaPass) {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();

      var flag = form.querySelector('input[name="pm_rc"]');
      var submitter = event.submitter || null;
      var action = field.getAttribute('data-pm-action') || 'submit';

      token(action).then(
        function (value) {
          field.value = value;
          if (flag) { flag.value = ''; }
        },
        function () {
          field.value = '';
          if (flag) { flag.value = 'unavailable'; }
        }
      ).then(function () {
        resubmit(form, submitter);
      });
    }, true);
  } catch (error) {
    if (window.console && window.console.warn) {
      window.console.warn('pm-recaptcha: unavailable, forms will submit without it', error);
    }
  }
})();
