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
  var pendingSubmitter = null;
  var defaultOkLabel = okBtn ? (okBtn.textContent || '').trim() : '';

  function closeModal() {
    pendingForm = null;
    pendingSubmitter = null;
    modal.close();
  }

  function readConfirmSource(source) {
    return {
      title: (source.getAttribute('data-confirm-title') || '').trim(),
      message: (source.getAttribute('data-confirm') || '').trim(),
      detail: (source.getAttribute('data-confirm-detail') || '').trim(),
      okLabel: (source.getAttribute('data-confirm-ok') || '').trim() || defaultOkLabel,
    };
  }

  function openFor(form, source, submitter) {
    pendingForm = form;
    pendingSubmitter = submitter || null;
    var conf = readConfirmSource(source);

    if (titleEl) {
      titleEl.textContent = conf.title || conf.message;
      titleEl.hidden = conf.title === '' && conf.message === '';
    }
    if (messageEl) {
      if (conf.title !== '' && conf.message !== '') {
        messageEl.textContent = conf.message;
        messageEl.hidden = false;
      } else {
        messageEl.textContent = '';
        messageEl.hidden = true;
      }
    }
    if (detailEl) {
      detailEl.textContent = conf.detail;
      detailEl.hidden = conf.detail === '';
    }
    if (okBtn) {
      okBtn.textContent = conf.okLabel;
    }

    modal.showModal();
    if (okBtn && typeof okBtn.focus === 'function') {
      okBtn.focus();
    }
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (form.getAttribute('data-confirm-accepted') === '1') {
      form.removeAttribute('data-confirm-accepted');
      return;
    }

    var submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    var source = null;
    if (submitter && submitter.hasAttribute('data-confirm')) {
      source = submitter;
    } else if (form.hasAttribute('data-confirm')) {
      source = form;
    }
    if (!source) {
      return;
    }

    event.preventDefault();
    openFor(form, source, submitter);
  });

  if (okBtn) {
    okBtn.addEventListener('click', function () {
      var form = pendingForm;
      var submitter = pendingSubmitter;
      if (!form) {
        closeModal();
        return;
      }
      pendingForm = null;
      pendingSubmitter = null;
      modal.close();
      form.setAttribute('data-confirm-accepted', '1');
      if (typeof form.requestSubmit === 'function') {
        if (submitter) {
          form.requestSubmit(submitter);
        } else {
          form.requestSubmit();
        }
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
    pendingSubmitter = null;
  });
})();
