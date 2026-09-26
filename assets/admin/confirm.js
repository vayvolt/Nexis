(function () {
  'use strict';

  var modal = document.getElementById('admin-confirm-modal');
  if (!modal || typeof modal.showModal !== 'function') {
    return;
  }

  var titleEl = document.getElementById('admin-confirm-title');
  var messageEl = document.getElementById('admin-confirm-message');
  var detailEl = document.getElementById('admin-confirm-detail');
  var okBtn = modal.querySelector('[data-admin-confirm-ok]');
  var cancelBtn = modal.querySelector('[data-admin-confirm-cancel]');
  var pendingForm = null;
  var defaultOkLabel = okBtn ? (okBtn.textContent || '').trim() : '';

  function closeModal() {
    pendingForm = null;
    modal.close();
  }

  function openForForm(form) {
    pendingForm = form;
    var title = (form.getAttribute('data-confirm-title') || '').trim();
    var message = (form.getAttribute('data-confirm') || '').trim();
    var detail = (form.getAttribute('data-confirm-detail') || '').trim();
    var okLabel = (form.getAttribute('data-confirm-ok') || '').trim() || defaultOkLabel;

    if (titleEl) {
      titleEl.textContent = title || message;
      titleEl.hidden = title === '' && message === '';
    }
    if (messageEl) {
      if (title !== '' && message !== '') {
        messageEl.textContent = message;
        messageEl.hidden = false;
      } else {
        messageEl.textContent = '';
        messageEl.hidden = true;
      }
    }
    if (detailEl) {
      detailEl.textContent = detail;
      detailEl.hidden = detail === '';
    }
    if (okBtn) {
      okBtn.textContent = okLabel;
    }

    modal.showModal();
    if (okBtn && typeof okBtn.focus === 'function') {
      okBtn.focus();
    }
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.getAttribute('data-confirm-accepted') === '1') {
        form.removeAttribute('data-confirm-accepted');
        return;
      }
      event.preventDefault();
      openForForm(form);
    });
  });

  if (okBtn) {
    okBtn.addEventListener('click', function () {
      var form = pendingForm;
      if (!form) {
        closeModal();
        return;
      }
      pendingForm = null;
      modal.close();
      form.setAttribute('data-confirm-accepted', '1');
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      closeModal();
    });
  }

  modal.addEventListener('click', function (event) {
    if (event.target === modal) {
      closeModal();
    }
  });

  modal.addEventListener('cancel', function () {
    pendingForm = null;
  });
})();
