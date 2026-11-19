(function () {
  'use strict';

  function boot() {
    var root = document.querySelector('[data-cms-upgrade]');
    if (!root) {
      return;
    }

    var statusUrl = root.getAttribute('data-status-url') || '';
    var runUrl = root.getAttribute('data-run-url') || '';
    var aboutUrl = root.getAttribute('data-about-url') || '';
    var csrf = root.getAttribute('data-csrf') || '';
    if (!statusUrl) {
      return;
    }

    var list = root.querySelector('[data-cms-upgrade-steps]');
    var statusEl = root.querySelector('[data-cms-upgrade-status]');
    var detailEl = root.querySelector('[data-cms-upgrade-detail]');
    var errorEl = root.querySelector('[data-cms-upgrade-error]');
    var successEl = root.querySelector('[data-cms-upgrade-success]');
    var started = Date.now();
    var runRequested = false;
    var finished = false;

    function setStatus(text) {
      if (statusEl) {
        statusEl.textContent = text || '';
      }
    }

    function setDetail(text) {
      if (detailEl) {
        detailEl.textContent = text || '';
        detailEl.hidden = !text;
      }
    }

    function renderSteps(steps) {
      if (!list || !Array.isArray(steps)) {
        return;
      }
      list.innerHTML = '';
      steps.forEach(function (step) {
        var li = document.createElement('li');
        li.className = 'cms-upgrade__step cms-upgrade__step--' + (step.state || 'pending');
        li.setAttribute('data-state', step.state || 'pending');
        var mark = document.createElement('span');
        mark.className = 'cms-upgrade__mark';
        mark.setAttribute('aria-hidden', 'true');
        if (step.state === 'done') {
          mark.textContent = '✓';
        } else if (step.state === 'failed') {
          mark.textContent = '!';
        } else if (step.state === 'active') {
          mark.textContent = '…';
        } else {
          mark.textContent = '·';
        }
        var label = document.createElement('span');
        label.className = 'cms-upgrade__label';
        label.textContent = step.label || step.key || '';
        li.appendChild(mark);
        li.appendChild(label);
        list.appendChild(li);
      });
    }

    function applyProgress(progress) {
      if (!progress) {
        return;
      }
      root.hidden = false;
      renderSteps(progress.steps || []);
      setStatus(progress.currentLabel || progress.current || '');
      setDetail(progress.message || '');

      if (errorEl) {
        if (progress.status === 'failed' && progress.error) {
          errorEl.hidden = false;
          errorEl.textContent = progress.error;
        } else {
          errorEl.hidden = true;
          errorEl.textContent = '';
        }
      }
      if (successEl) {
        if (progress.status === 'done' && progress.doneMessage) {
          successEl.hidden = false;
          successEl.textContent = progress.doneMessage;
        } else {
          successEl.hidden = true;
        }
      }

      if (progress.status === 'done' || progress.status === 'failed') {
        finished = true;
        root.classList.toggle('is-done', progress.status === 'done');
        root.classList.toggle('is-failed', progress.status === 'failed');
        if (progress.status === 'done' && aboutUrl) {
          window.setTimeout(function () {
            var msg = progress.doneMessage || '';
            window.location.href = aboutUrl + (msg !== '' ? ('?upgraded=' + encodeURIComponent(msg)) : '');
          }, 1200);
        }
      }
    }

    function maybeRunFallback(progress) {
      if (runRequested || finished || !runUrl || !csrf || !progress) {
        return;
      }
      // CLI already working.
      if (progress.status === 'running' && progress.spawned) {
        return;
      }
      if (progress.status !== 'queued') {
        return;
      }
      // Give detached worker a short head start; then run in this request as fallback.
      var waitMs = progress.spawned ? 2500 : 1200;
      if (Date.now() - started < waitMs) {
        return;
      }
      runRequested = true;
      var body = new URLSearchParams();
      body.set('_csrf', csrf);
      fetch(runUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-Token': csrf,
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        },
        body: body.toString(),
        credentials: 'same-origin',
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return data || {};
          });
        })
        .then(function (data) {
          if (data.progress) {
            applyProgress(data.progress);
          }
        })
        .catch(function () {
          runRequested = false;
        });
    }

    function poll() {
      if (finished) {
        return;
      }
      fetch(statusUrl, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return data || {};
          });
        })
        .then(function (data) {
          var progress = data.progress || null;
          if (progress) {
            applyProgress(progress);
            maybeRunFallback(progress);
          }
          if (!finished) {
            window.setTimeout(poll, 1000);
          }
        })
        .catch(function () {
          if (!finished) {
            window.setTimeout(poll, 2000);
          }
        });
    }

    var initial = root.getAttribute('data-initial');
    if (initial) {
      try {
        applyProgress(JSON.parse(initial));
      } catch (e) {
        // ignore
      }
    }
    root.hidden = false;
    poll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
