(function () {
  'use strict';

  function bindDialog(modal, openers, cancelSelector, autoOpen) {
    if (!modal || typeof modal.showModal !== 'function') {
      return;
    }

    function open() {
      modal.showModal();
      var focus = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
      if (focus && typeof focus.focus === 'function') {
        focus.focus();
      }
    }

    openers.forEach(function (btn) {
      btn.addEventListener('click', function () {
        open();
      });
    });

    var cancel = modal.querySelector(cancelSelector);
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

    if (autoOpen) {
      open();
    }
  }

  var createModal = document.getElementById('user-create-modal');
  bindDialog(
    createModal,
    Array.prototype.slice.call(document.querySelectorAll('[data-user-create-open]')),
    '[data-user-create-cancel]',
    !!(createModal && createModal.hasAttribute('data-user-create-autoopen'))
      || (location.hash || '') === '#new-user'
  );

  var deleteModal = document.getElementById('user-delete-modal');
  if (deleteModal && typeof deleteModal.showModal === 'function') {
    var idInput = document.getElementById('user-delete-id');
    var labelEl = document.getElementById('user-delete-label');

    document.querySelectorAll('[data-user-delete]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!idInput || !labelEl) {
          return;
        }
        idInput.value = btn.getAttribute('data-user-id') || '';
        labelEl.textContent = btn.getAttribute('data-user-label') || '';
        deleteModal.showModal();
      });
    });

    var deleteCancel = deleteModal.querySelector('[data-user-delete-cancel]');
    if (deleteCancel) {
      deleteCancel.addEventListener('click', function () {
        deleteModal.close();
      });
    }

    deleteModal.addEventListener('click', function (event) {
      if (event.target === deleteModal) {
        deleteModal.close();
      }
    });
  }
})();
