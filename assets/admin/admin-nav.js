(function () {
  'use strict';

  var shell = document.querySelector('[data-admin-nav]');
  var burger = document.querySelector('[data-admin-nav-toggle]');

  function setNavOpen(open) {
    if (!shell || !burger) {
      return;
    }
    shell.classList.toggle('is-open', open);
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function closeDropdowns(except) {
    document.querySelectorAll('details.admin-nav-drop[open]').forEach(function (el) {
      if (el !== except) {
        el.open = false;
      }
    });
  }

  if (burger && shell) {
    burger.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      setNavOpen(!shell.classList.contains('is-open'));
    });
  }

  document.addEventListener('click', function (ev) {
    var target = ev.target;
    if (!(target instanceof Element)) {
      return;
    }
    var drop = target.closest('details.admin-nav-drop');
    if (drop) {
      closeDropdowns(drop);
      return;
    }
    if (shell && !shell.contains(target)) {
      setNavOpen(false);
      closeDropdowns(null);
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      setNavOpen(false);
      closeDropdowns(null);
    }
  });

  // Preserve page tab hash (#colors, #layout, …) across UI-locale switches.
  // Fragments are never sent to the server in REQUEST_URI / return=.
  document.querySelectorAll('.admin-locale a[href*="ui-locale"]').forEach(function (link) {
    link.addEventListener('click', function () {
      var hash = (location.hash || '').replace(/^#/, '');
      if (!hash || !/^[A-Za-z0-9_-]{1,64}$/.test(hash)) {
        return;
      }
      try {
        var url = new URL(link.getAttribute('href') || '', location.origin);
        var ret = url.searchParams.get('return');
        if (!ret) {
          return;
        }
        var base = ret.split('#')[0];
        url.searchParams.set('return', base + '#' + hash);
        link.setAttribute('href', url.pathname + url.search);
      } catch (err) {
        // ignore malformed href
      }
    });
  });
})();
