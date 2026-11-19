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

  var inviteModal = document.getElementById('user-invite-modal');
  bindDialog(
    inviteModal,
    Array.prototype.slice.call(document.querySelectorAll('[data-user-invite-open]')),
    '[data-user-invite-cancel]',
    !!(inviteModal && inviteModal.hasAttribute('data-user-invite-autoopen'))
      || (location.hash || '') === '#invite-user'
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

  var revokeModal = document.getElementById('invite-revoke-modal');
  if (revokeModal && typeof revokeModal.showModal === 'function') {
    var revokeIdInput = document.getElementById('invite-revoke-id');
    var revokeLabelEl = document.getElementById('invite-revoke-label');

    document.querySelectorAll('[data-invite-revoke]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!revokeIdInput || !revokeLabelEl) {
          return;
        }
        revokeIdInput.value = btn.getAttribute('data-invite-id') || '';
        revokeLabelEl.textContent = btn.getAttribute('data-invite-label') || '';
        revokeModal.showModal();
      });
    });

    var revokeCancel = revokeModal.querySelector('[data-invite-revoke-cancel]');
    if (revokeCancel) {
      revokeCancel.addEventListener('click', function () {
        revokeModal.close();
      });
    }

    revokeModal.addEventListener('click', function (event) {
      if (event.target === revokeModal) {
        revokeModal.close();
      }
    });
  }
})();
