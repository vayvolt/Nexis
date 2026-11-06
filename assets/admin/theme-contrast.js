(function () {
  'use strict';

  var root = document.querySelector('[data-theme-contrast]');
  if (!root) {
    return;
  }

  var ids = {
    primary: 'token_color_brand_primary',
    accent: 'token_color_brand_accent',
    surface: 'token_color_surface',
    text: 'token_color_text',
  };

  function parseColor(raw) {
    if (!raw || typeof raw !== 'string') {
      return null;
    }
    var s = raw.trim();
    var m = /^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.exec(s);
    if (m) {
      var h = m[1];
      if (h.length === 3 || h.length === 4) {
        h = h
          .split('')
          .map(function (c) {
            return c + c;
          })
          .join('');
      }
      return {
        r: parseInt(h.slice(0, 2), 16),
        g: parseInt(h.slice(2, 4), 16),
        b: parseInt(h.slice(4, 6), 16),
      };
    }
    m = /^rgba?\(\s*([0-9.]+)\s*,\s*([0-9.]+)\s*,\s*([0-9.]+)/i.exec(s);
    if (m) {
      return {
        r: Math.max(0, Math.min(255, Math.round(Number(m[1])))),
        g: Math.max(0, Math.min(255, Math.round(Number(m[2])))),
        b: Math.max(0, Math.min(255, Math.round(Number(m[3])))),
      };
    }
    return null;
  }

  function channel(c) {
    var s = c / 255;
    return s <= 0.04045 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  }

  function luminance(rgb) {
    return 0.2126 * channel(rgb.r) + 0.7152 * channel(rgb.g) + 0.0722 * channel(rgb.b);
  }

  function ratio(fg, bg) {
    var L1 = luminance(fg);
    var L2 = luminance(bg);
    var light = Math.max(L1, L2);
    var dark = Math.min(L1, L2);
    return (light + 0.05) / (dark + 0.05);
  }

  function fmt(r) {
    return (Math.round(r * 10) / 10).toFixed(1) + ':1';
  }

  function toCss(rgb) {
    return 'rgb(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ')';
  }

  function passLabel(ok) {
    return ok ? root.getAttribute('data-pass') || 'OK' : root.getAttribute('data-fail') || 'niedrig';
  }

  function setRow(key, valueText, swatchFg, swatchBg) {
    var row = root.querySelector('[data-contrast-row="' + key + '"]');
    if (!row) {
      return;
    }
    var value = row.querySelector('[data-contrast-value]');
    if (value) {
      value.textContent = valueText;
    }
    var swatch = row.querySelector('[data-contrast-swatch]');
    if (swatch && swatchFg && swatchBg) {
      swatch.style.background = toCss(swatchBg);
      swatch.style.color = toCss(swatchFg);
      swatch.hidden = false;
    } else if (swatch) {
      swatch.hidden = true;
    }
  }

  function update() {
    var surface = parseColor((document.getElementById(ids.surface) || {}).value);
    var text = parseColor((document.getElementById(ids.text) || {}).value);
    var primary = parseColor((document.getElementById(ids.primary) || {}).value);
    var accent = parseColor((document.getElementById(ids.accent) || {}).value);
    var na = root.getAttribute('data-na') || '—';

    function pair(key, fg, bg, aaNormal, aaLarge) {
      if (!fg || !bg) {
        setRow(key, na, null, null);
        return;
      }
      var r = ratio(fg, bg);
      var aa = r >= aaNormal;
      var large = r >= aaLarge;
      var bits = [fmt(r)];
      bits.push('AA ' + passLabel(aa));
      if (aaLarge !== aaNormal) {
        bits.push((root.getAttribute('data-large') || 'large') + ' ' + passLabel(large));
      }
      setRow(key, bits.join(' · '), fg, bg);
    }

    pair('text', text, surface, 4.5, 3);
    pair('primary', primary, surface, 3, 3);
    pair('accent', accent, surface, 3, 3);
  }

  Object.keys(ids).forEach(function (key) {
    var el = document.getElementById(ids[key]);
    if (!el) {
      return;
    }
    el.addEventListener('input', update);
    el.addEventListener('change', update);
  });

  update();
})();
