(function () {
  'use strict';

  var root = document.querySelector('[data-logo-picker]');
  var input = document.getElementById('token_brand_logoUrl');
  if (!root || !input) {
    return;
  }

  function setActive(url) {
    Array.prototype.forEach.call(root.querySelectorAll('[data-logo-url]'), function (btn) {
      btn.classList.toggle('is-active', (btn.getAttribute('data-logo-url') || '') === url);
    });
    var clearBtn = root.querySelector('[data-logo-clear]');
    if (clearBtn) {
      clearBtn.classList.toggle('is-active', url === '');
    }
  }

  root.addEventListener('click', function (ev) {
    var target = ev.target;
    if (!(target instanceof Element)) {
      return;
    }
    var pick = target.closest('[data-logo-url]');
    if (pick) {
      ev.preventDefault();
      var url = pick.getAttribute('data-logo-url') || '';
      input.value = url;
      setActive(url);
      return;
    }
    if (target.closest('[data-logo-clear]')) {
      ev.preventDefault();
      input.value = '';
      setActive('');
    }
  });

  input.addEventListener('input', function () {
    setActive(input.value.trim());
  });

  setActive(input.value.trim());
})();
