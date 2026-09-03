(function () {
  'use strict';

  var KEY = 'pmAdminSidebar';

  function init() {
    var shell = document.querySelector('.pma-shell');
    var toggle = document.querySelector('.pma-side-toggle');

    if (!shell || !toggle) {
      return;
    }

    function apply(collapsed) {
      shell.classList.toggle('is-collapsed', collapsed);
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    var stored = null;
    try {
      stored = window.localStorage.getItem(KEY);
    } catch (error) {
      stored = null;
    }

    apply(stored === 'collapsed');

    toggle.addEventListener('click', function () {
      var next = !shell.classList.contains('is-collapsed');
      apply(next);
      try {
        window.localStorage.setItem(KEY, next ? 'collapsed' : 'open');
      } catch (error) {
        // A browser with site data blocked still gets a working toggle for
        // this page view; only the memory of the choice is lost.
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
