(function () {
  'use strict';

  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var value = btn.getAttribute('data-copy') || '';
      var restore = btn.textContent || 'Kopieren';
      var done = function () {
        btn.textContent = 'Kopiert';
        setTimeout(function () {
          btn.textContent = restore;
        }, 1200);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(function () {
          fallbackCopy(btn, value);
          done();
        });
        return;
      }
      fallbackCopy(btn, value);
      done();
    });
  });

  document.querySelectorAll('input[data-select-on-focus]').forEach(function (input) {
    input.addEventListener('focus', function () {
      input.select();
    });
    input.addEventListener('click', function () {
      input.select();
    });
  });

  document.querySelectorAll('.media-thumb--focus').forEach(function (thumb) {
    var form = thumb.parentElement && thumb.parentElement.querySelector('.media-edit-form');
    if (!form) {
      return;
    }
    var fx = form.querySelector('input[name="focus_x"]');
    var fy = form.querySelector('input[name="focus_y"]');
    var marker = thumb.querySelector('.media-focus-marker');
    var img = thumb.querySelector('img');
    thumb.addEventListener('click', function (event) {
      var rect = thumb.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) {
        return;
      }
      var x = ((event.clientX - rect.left) / rect.width) * 100;
      var y = ((event.clientY - rect.top) / rect.height) * 100;
      x = Math.max(0, Math.min(100, Math.round(x * 10) / 10));
      y = Math.max(0, Math.min(100, Math.round(y * 10) / 10));
      if (fx) fx.value = String(x);
      if (fy) fy.value = String(y);
      if (marker) {
        marker.style.left = x + '%';
        marker.style.top = y + '%';
      }
      if (img) {
        img.style.objectPosition = x + '% ' + y + '%';
      }
    });
  });

  function fallbackCopy(btn, value) {
    var input = btn.parentElement && btn.parentElement.querySelector('input');
    if (input) {
      input.focus();
      input.select();
      try {
        document.execCommand('copy');
      } catch (e) {
        /* ignore */
      }
      return;
    }
    var ta = document.createElement('textarea');
    ta.value = value;
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
    } catch (e) {
      /* ignore */
    }
    document.body.removeChild(ta);
  }
})();
