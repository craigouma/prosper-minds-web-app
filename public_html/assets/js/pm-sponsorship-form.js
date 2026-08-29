/**
 * The sponsorship enquiry form (sponsorship.php).
 *
 * PROGRESSIVE ENHANCEMENT, NOT A REQUIREMENT
 * ------------------------------------------
 * The form carries a real action and method and posts to
 * process-sponsorship.php on its own. With scripts off the browser submits it
 * and the handler answers its JSON, which is plain but honest: the enquiry is
 * delivered. This file only upgrades that to an inline notice on the page the
 * visitor is already on, so they keep their place in a long page.
 *
 * The handler is live code and is not modified by this phase. It answers JSON
 * with {success, message} and nothing else, so that is what this reads.
 *
 * WHAT IT MUST NEVER DO
 * ---------------------
 * Report success that did not happen. The August 2026 incident on this site was
 * a form telling people something had worked when it had not, and the rule that
 * came out of it applies here too: success is shown only when the server said
 * success. A network failure, a non-JSON body, or an HTTP error all report the
 * failure and leave the visitor's typing in the form so nothing is lost.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-pm-sponsorship-form]');
  if (!form || !window.fetch) {
    // No fetch: leave the form alone entirely and let the browser post it.
    return;
  }

  var status = document.getElementById('pm-sponsorship-status');
  var submit = form.querySelector('button[type="submit"]');

  function show(message, ok) {
    if (!status) {
      return;
    }

    status.textContent = message;
    // .pm-notice--error is a black rule rather than a red one; the palette has
    // no fourth colour and an error is marked by wording, not by hue.
    status.classList.toggle('pm-notice--error', !ok);
    status.hidden = false;
    status.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  form.addEventListener('submit', function (event) {
    // At least one school. The handler enforces this too and would answer with
    // the same refusal; catching it here saves a round trip and puts the
    // message beside the fieldset it is about.
    var chosen = form.querySelectorAll('input[name="events[]"]:checked');
    if (chosen.length === 0) {
      event.preventDefault();
      show(form.getAttribute('data-pm-events-required') || 'Please choose at least one school.', false);
      return;
    }

    // Let the browser's own required-field validation run first. If it fails,
    // it stops the submit event before this listener sees it.
    event.preventDefault();

    var body = new FormData(form);
    var original = submit ? submit.textContent : '';

    if (submit) {
      submit.disabled = true;
      submit.textContent = submit.getAttribute('data-pm-sending') || 'Sending';
    }

    fetch(form.getAttribute('action'), { method: 'POST', body: body })
      .then(function (response) {
        // A non-2xx answer is a failure even if the body happens to parse.
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.json();
      })
      .then(function (data) {
        if (data && data.success) {
          show(data.message || 'Thank you. We will be in touch within 48 hours.', true);
          form.reset();
          return;
        }

        // The server refused. Its own wording is the accurate one.
        show((data && data.message) || 'We could not send that. Please try again.', false);
      })
      .catch(function () {
        show('We could not reach the server. Please try again, or email info@prosper-minds.com.', false);
      })
      .then(function () {
        if (submit) {
          submit.disabled = false;
          submit.textContent = original;
        }
      });
  });
})();
