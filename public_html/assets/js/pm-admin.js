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

(function () {
  'use strict';

  function init() {
    var frame = document.querySelector('[data-focal]');
    var dot = document.querySelector('[data-focal-dot]');
    var fx = document.querySelector('[data-focal-x]');
    var fy = document.querySelector('[data-focal-y]');

    if (!frame || !dot || !fx || !fy) {
      return;
    }

    frame.addEventListener('click', function (event) {
      var box = frame.getBoundingClientRect();
      var x = Math.round(((event.clientX - box.left) / box.width) * 100);
      var y = Math.round(((event.clientY - box.top) / box.height) * 100);

      x = Math.max(0, Math.min(100, x));
      y = Math.max(0, Math.min(100, y));

      dot.style.left = x + '%';
      dot.style.top = y + '%';
      fx.value = x;
      fy.value = y;
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());

(function () {
  'use strict';

  // Everything destructive is recoverable for 30 days, so this dialog is a
  // courtesy rather than the safety mechanism. Without JavaScript the form
  // still submits and the item still lands in the trash.
  function init() {
    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement)) {
        return;
      }

      var trigger = form.querySelector('[data-confirm]');
      if (!trigger) {
        return;
      }

      if (!window.confirm(trigger.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());

(function () {
  'use strict';

  function init() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-copy]');
      if (!button) {
        return;
      }

      var value = button.getAttribute('data-copy');
      var done = function () {
        var was = button.textContent;
        button.textContent = 'Copied';
        window.setTimeout(function () { button.textContent = was; }, 1600);
      };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(value).then(done, function () { window.prompt('Copy this link', value); });
      } else {
        // Clipboard access needs a secure context, which a staging host on
        // plain http will not be. Showing the value still gets the job done.
        window.prompt('Copy this link', value);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
