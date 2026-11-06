(function () {
  'use strict';

  function closeMenu(details, returnFocus) {
    if (!details || !details.hasAttribute('open')) {
      return;
    }
    details.removeAttribute('open');
    if (!returnFocus) {
      return;
    }
    var summary = details.querySelector('summary');
    if (summary && typeof summary.focus === 'function') {
      try {
        summary.focus({ preventScroll: true });
      } catch (err) {
        summary.focus();
      }
    }
  }

  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.theme-locale-menu[open]').forEach(function (details) {
      if (!details.contains(e.target)) {
        closeMenu(details, false);
      }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') {
      return;
    }
    document.querySelectorAll('details.theme-locale-menu[open]').forEach(function (details) {
      if (details.contains(e.target) || e.target === details) {
        e.preventDefault();
        closeMenu(details, true);
      }
    });
  });
})();
