(function () {
  'use strict';

  var modal = document.getElementById('account-delete-modal');
  if (!modal || typeof modal.showModal !== 'function') {
    return;
  }

  document.querySelectorAll('[data-account-delete-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      modal.showModal();
    });
  });

  var cancel = modal.querySelector('[data-account-delete-cancel]');
  if (cancel) {
    cancel.addEventListener('click', function () {
      modal.close();
    });
  }

  modal.addEventListener('click', function (event) {
    if (event.target === modal) {
      modal.close();
    }
  });
})();
