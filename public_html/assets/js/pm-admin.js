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

(function () {
  'use strict';

  function init() {
    var root = document.getElementById('pm-palette');
    if (!root) {
      return;
    }

    var input = root.querySelector('[data-palette-input]');
    var list = root.querySelector('[data-palette-results]');
    var status = root.querySelector('[data-palette-status]');
    var index = -1;
    var timer = null;
    var seq = 0;

    function open() {
      root.hidden = false;
      input.value = '';
      list.innerHTML = '';
      status.textContent = 'Type at least two characters.';
      index = -1;
      input.focus();
    }

    function close() {
      root.hidden = true;
      index = -1;
    }

    function rows() {
      return Array.prototype.slice.call(list.querySelectorAll('a'));
    }

    function highlight(next) {
      var items = rows();
      if (!items.length) {
        return;
      }
      index = (next + items.length) % items.length;
      items.forEach(function (el, i) { el.classList.toggle('is-active', i === index); });
      items[index].scrollIntoView({ block: 'nearest' });
    }

    function render(results) {
      list.innerHTML = '';
      if (!results.length) {
        status.textContent = 'Nothing matched.';
        return;
      }
      status.textContent = results.length + ' result' + (results.length === 1 ? '' : 's');
      results.forEach(function (r) {
        var a = document.createElement('a');
        a.href = r.href;
        a.className = 'pma-palette-row';
        var kind = document.createElement('span');
        kind.className = 'pma-palette-kind';
        kind.textContent = r.kind;
        var title = document.createElement('span');
        title.className = 'pma-palette-title';
        title.textContent = r.title;
        var detail = document.createElement('span');
        detail.className = 'pma-palette-detail';
        detail.textContent = r.detail || '';
        a.appendChild(kind);
        a.appendChild(title);
        a.appendChild(detail);
        list.appendChild(a);
      });
    }

    function search() {
      var q = input.value.trim();
      if (q.length < 2) {
        list.innerHTML = '';
        status.textContent = 'Type at least two characters.';
        return;
      }

      // Only the newest response is rendered, so a slow earlier request cannot
      // overwrite the results for what is currently typed.
      var mine = ++seq;
      status.textContent = 'Searching';

      fetch('search.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { results: [] }; })
        .then(function (data) {
          if (mine !== seq) {
            return;
          }
          render(data.results || []);
        })
        .catch(function () {
          if (mine === seq) {
            status.textContent = 'Search is unavailable just now.';
          }
        });
    }

    // Capture phase, because a search input in Chromium consumes Escape for its
    // own clear behaviour before a bubbling listener ever sees it.
    document.addEventListener('keydown', function (event) {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        root.hidden ? open() : close();
        return;
      }

      if (root.hidden) {
        return;
      }

      if (event.key === 'Escape') { close(); }
      if (event.key === 'ArrowDown') { event.preventDefault(); highlight(index + 1); }
      if (event.key === 'ArrowUp') { event.preventDefault(); highlight(index - 1); }
      if (event.key === 'Enter' && index >= 0) {
        var items = rows();
        if (items[index]) { event.preventDefault(); window.location.href = items[index].href; }
      }
    }, true);

    document.addEventListener('click', function (event) {
      if (event.target.closest('[data-palette-open]')) {
        event.preventDefault();
        open();
        return;
      }
      if (!root.hidden && event.target === root) {
        close();
      }
    });

    input.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 180);
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
    var buttons = document.querySelectorAll('[data-funnel-view]');
    if (!buttons.length) {
      return;
    }

    function show(view) {
      document.querySelectorAll('[data-funnel-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-funnel-panel') !== view;
      });
      buttons.forEach(function (b) {
        b.classList.toggle('is-on', b.getAttribute('data-funnel-view') === view);
      });
      try {
        window.localStorage.setItem('pmFunnelView', view);
      } catch (error) {
        // A browser with site data blocked still gets a working toggle.
      }
    }

    buttons.forEach(function (b) {
      b.addEventListener('click', function () { show(b.getAttribute('data-funnel-view')); });
    });

    var stored = null;
    try {
      stored = window.localStorage.getItem('pmFunnelView');
    } catch (error) {
      stored = null;
    }
    if (stored === 'flow') {
      show('flow');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
