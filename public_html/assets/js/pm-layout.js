/**
 * Shared layout behaviour for the rebuilt site.
 *
 * Currently one job: the mobile navigation panel.
 *
 * PROGRESSIVE ENHANCEMENT
 * -----------------------
 * The navigation is a plain list of links in the document and works with this
 * file absent, blocked, or failed. head.php sets data-pm-js="on" on <html>
 * before first paint, and the stylesheet only collapses the nav (and only
 * reveals the toggle) when that attribute is present — so a browser that
 * cannot run script never gets a menu it cannot open.
 *
 * STATE
 * -----
 * One source of truth: data-pm-nav-open on <html>. CSS reads it; this file
 * writes it; aria-expanded on the toggle is kept in step so the control
 * announces its state. Nothing else here holds state.
 *
 * Everything below is wrapped so a failure is inert: a thrown error must cost
 * the site its mobile menu, not its page. Same discipline as the rest of this
 * codebase — see the safety contract in includes/funnel.php.
 */
(function () {
  'use strict';

  try {
    var root = document.documentElement;
    var toggle = document.getElementById('pm-nav-toggle');
    var closeBtn = document.getElementById('pm-nav-close');
    var nav = document.getElementById('pm-nav');

    if (!toggle || !nav) {
      return;
    }

    var DESKTOP_QUERY = '(min-width: 901px)';

    function isOpen() {
      return root.getAttribute('data-pm-nav-open') === 'true';
    }

    function setOpen(open) {
      root.setAttribute('data-pm-nav-open', open ? 'true' : 'false');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

      if (open) {
        // Move focus into the panel so a keyboard user is not left behind the
        // overlay. The close button is the first control in the panel.
        if (closeBtn && typeof closeBtn.focus === 'function') {
          closeBtn.focus();
        }
      } else if (typeof toggle.focus === 'function') {
        toggle.focus();
      }
    }

    setOpen(false);

    toggle.addEventListener('click', function () {
      setOpen(!isOpen());
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        setOpen(false);
      });
    }

    // Following a link should close the panel. Anchor links to a section on the
    // current page do not reload, so without this the panel would stay over the
    // content the visitor just asked to see.
    nav.addEventListener('click', function (event) {
      var target = event.target;
      while (target && target !== nav) {
        if (target.tagName === 'A') {
          setOpen(false);
          return;
        }
        target = target.parentNode;
      }
    });

    document.addEventListener('keydown', function (event) {
      if ((event.key === 'Escape' || event.key === 'Esc') && isOpen()) {
        setOpen(false);
      }
    });

    // Rotating a phone into landscape, or resizing a desktop window down and
    // back, must not leave the page scroll-locked behind an invisible panel.
    if (window.matchMedia) {
      var desktop = window.matchMedia(DESKTOP_QUERY);
      var onChange = function (event) {
        if (event.matches && isOpen()) {
          setOpen(false);
        }
      };

      if (typeof desktop.addEventListener === 'function') {
        desktop.addEventListener('change', onChange);
      } else if (typeof desktop.addListener === 'function') {
        // Safari before 14.
        desktop.addListener(onChange);
      }
    }
  } catch (error) {
    // Inert on purpose. A broken menu script must never stop a page working.
    if (window.console && window.console.warn) {
      window.console.warn('pm-layout: navigation unavailable', error);
    }
  }
})();
