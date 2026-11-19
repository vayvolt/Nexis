(function () {
  'use strict';

  function boot() {
    var el = document.querySelector('[data-marketplace-refresh]');
    if (!el) {
      return;
    }
    if (el.getAttribute('data-marketplace-refresh') !== '1') {
      return;
    }
    var url = el.getAttribute('data-marketplace-refresh-url') || '';
    var token = el.getAttribute('data-csrf') || '';
    if (!url || !token) {
      return;
    }

    var status = el.querySelector('[data-marketplace-refresh-status]');
    if (status) {
      status.hidden = false;
    }

    var body = new URLSearchParams();
    body.set('_csrf', token);

    fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': token,
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
      },
      body: body.toString(),
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data || {} };
        });
      })
      .then(function (result) {
        if (status) {
          status.hidden = true;
        }
        if (result.data.reload) {
          window.location.reload();
        }
      })
      .catch(function () {
        if (status) {
          status.hidden = true;
        }
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
