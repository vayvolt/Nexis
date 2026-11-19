(function () {
  function activate(root, code, syncHash) {
    var tabs = root.querySelectorAll('[data-locale-tab]');
    var panels = root.querySelectorAll('[data-locale-panel]');
    tabs.forEach(function (tab) {
      var selected = tab.getAttribute('data-locale-tab') === code;
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
    });
    panels.forEach(function (panel) {
      var match = panel.getAttribute('data-locale-panel') === code;
      if (match) {
        panel.removeAttribute('hidden');
      } else {
        panel.setAttribute('hidden', 'hidden');
      }
    });
    root.setAttribute('data-active', code);
    root.querySelectorAll('[data-tabs-return]').forEach(function (input) {
      var allowed = (input.getAttribute('data-tabs-return') || '')
        .split(',')
        .map(function (part) { return part.trim(); })
        .filter(Boolean);
      if (allowed.length && allowed.indexOf(code) === -1) {
        return;
      }
      input.value = code;
    });

    if (syncHash && root.hasAttribute('data-tabs-hash')) {
      var next = '#' + code;
      if (location.hash !== next) {
        if (history.replaceState) {
          history.replaceState(null, '', next);
        } else {
          location.hash = code;
        }
      }
    }
  }

  function tabCodes(root) {
    return Array.prototype.slice.call(root.querySelectorAll('[data-locale-tab]')).map(function (tab) {
      return tab.getAttribute('data-locale-tab');
    });
  }

  function initialCode(root, tabs) {
    if (root.hasAttribute('data-tabs-hash')) {
      var hash = (location.hash || '').replace(/^#/, '');
      if (hash && tabCodes(root).indexOf(hash) !== -1) {
        return hash;
      }
    }
    return tabs[0].getAttribute('data-locale-tab');
  }

  document.querySelectorAll('[data-locale-tabs]').forEach(function (root) {
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-locale-tab]'));
    if (tabs.length === 0) {
      return;
    }

    activate(root, initialCode(root, tabs), false);

    root.addEventListener('click', function (event) {
      var tab = event.target && event.target.closest ? event.target.closest('[data-locale-tab]') : null;
      if (!tab || !root.contains(tab)) {
        return;
      }
      event.preventDefault();
      activate(root, tab.getAttribute('data-locale-tab'), true);
      tab.focus();
    });

    root.addEventListener('keydown', function (event) {
      var tab = event.target && event.target.closest ? event.target.closest('[data-locale-tab]') : null;
      if (!tab || !root.contains(tab)) {
        return;
      }
      var index = tabs.indexOf(tab);
      if (index < 0) {
        return;
      }
      var next = index;
      if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        next = (index + 1) % tabs.length;
      } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        next = (index - 1 + tabs.length) % tabs.length;
      } else if (event.key === 'Home') {
        next = 0;
      } else if (event.key === 'End') {
        next = tabs.length - 1;
      } else {
        return;
      }
      event.preventDefault();
      activate(root, tabs[next].getAttribute('data-locale-tab'), true);
      tabs[next].focus();
    });

    if (root.hasAttribute('data-tabs-hash')) {
      window.addEventListener('hashchange', function () {
        var hash = (location.hash || '').replace(/^#/, '');
        if (hash && tabCodes(root).indexOf(hash) !== -1) {
          activate(root, hash, false);
        }
      });
    }
  });
})();
