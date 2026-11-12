(function () {
  'use strict';

  function buttonsFor(form) {
    var list = Array.prototype.slice.call(
      form.querySelectorAll('button[type="submit"], button:not([type])')
    );
    var id = form.id;
    if (id) {
      document.querySelectorAll('button[form="' + id + '"]').forEach(function (btn) {
        if (list.indexOf(btn) === -1) {
          list.push(btn);
        }
      });
    }
    return list;
  }

  function markBusy(form) {
    if (form.getAttribute('data-busy-active') === '1') {
      return;
    }
    form.setAttribute('data-busy-active', '1');
    form.setAttribute('aria-busy', 'true');
    form.classList.add('is-busy');

    var label = (form.getAttribute('data-busy-label') || '').trim();
    buttonsFor(form).forEach(function (btn) {
      if (btn.disabled) {
        return;
      }
      btn.disabled = true;
      btn.setAttribute('aria-disabled', 'true');
      if (label !== '' && !btn.getAttribute('data-busy-original')) {
        btn.setAttribute('data-busy-original', btn.textContent || '');
        btn.textContent = label;
      }
    });
  }

  document.querySelectorAll('form[data-busy]').forEach(function (form) {
    form.addEventListener('submit', function () {
      // Confirm dialogs cancel the first submit; only mark busy when accepted or no confirm.
      if (form.hasAttribute('data-confirm') && form.getAttribute('data-confirm-accepted') !== '1') {
        return;
      }
      markBusy(form);
    });
  });
})();
