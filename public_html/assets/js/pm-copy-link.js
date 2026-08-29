/**
 * "Copy link" on the banner library (events.php).
 *
 * Progressive enhancement, the same contract as assets/js/pm-layout.js and the
 * nav toggle: the button is display:none until head.php has stamped
 * data-pm-js="on" on <html>, so with scripts off a visitor never sees a control
 * that would do nothing. The "Download" link beside it is a plain <a href> and
 * needs none of this.
 *
 * The clipboard API is not available on an insecure origin, and the permission
 * can be refused, so a failure falls back to selecting the URL in a temporary
 * field. If even that fails the label says so rather than claiming success:
 * telling somebody a link is on their clipboard when it is not is the small
 * version of telling a delegate their registration succeeded when it did not.
 */
(function () {
  'use strict';

  var RESET_MS = 2200;

  function selectFallback(text) {
    var field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', 'readonly');
    // Off-screen rather than display:none: a hidden field cannot be selected.
    field.style.position = 'fixed';
    field.style.top = '-1000px';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();

    var ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {
      ok = false;
    }

    document.body.removeChild(field);
    return ok;
  }

  function announce(button, message) {
    // The visible words change; the screen-reader suffix inside the button
    // ("to the banner for X") stays, so the button keeps naming its target.
    var label = button.firstChild;
    if (!label || label.nodeType !== 3) {
      return;
    }

    if (button.dataset.pmCopyLabel === undefined) {
      button.dataset.pmCopyLabel = label.nodeValue;
    }

    label.nodeValue = message;
    window.setTimeout(function () {
      label.nodeValue = button.dataset.pmCopyLabel;
    }, RESET_MS);
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-pm-copy]') : null;
    if (!button) {
      return;
    }

    var url = button.getAttribute('data-pm-copy') || '';
    if (url === '') {
      return;
    }

    var done = button.getAttribute('data-pm-copy-done') || 'Link copied';
    var failed = button.getAttribute('data-pm-copy-failed') || 'Press to select';

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(url).then(function () {
        announce(button, done);
      }, function () {
        announce(button, selectFallback(url) ? done : failed);
      });
      return;
    }

    announce(button, selectFallback(url) ? done : failed);
  });
})();
