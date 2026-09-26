(function () {
  var root = document.getElementById('nx-consent');
  if (!root) {
    return;
  }

  var cookieName = root.getAttribute('data-cookie') || 'nexis_consent';
  var hasAnalytics = root.getAttribute('data-has-analytics') === '1';
  var hasMarketing = root.getAttribute('data-has-marketing') === '1';

  function readCookie(name) {
    var parts = (document.cookie || '').split(';');
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i].trim();
      if (p.indexOf(name + '=') === 0) {
        return decodeURIComponent(p.slice(name.length + 1));
      }
    }
    return '';
  }

  function writeCookie(name, value) {
    var maxAge = 60 * 60 * 24 * 365;
    document.cookie = name + '=' + encodeURIComponent(value)
      + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
  }

  function parseConsent(raw) {
    if (!raw) {
      return null;
    }
    if (raw === '1') {
      return { v: 1, necessary: true, analytics: true, marketing: true };
    }
    try {
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') {
        return null;
      }
      return {
        v: 1,
        necessary: true,
        analytics: !!data.analytics,
        marketing: !!data.marketing
      };
    } catch (e) {
      return null;
    }
  }

  function applyGtagConsent(state) {
    if (typeof window.gtag !== 'function') {
      return;
    }
    window.gtag('consent', 'update', {
      analytics_storage: state.analytics ? 'granted' : 'denied',
      ad_storage: state.marketing ? 'granted' : 'denied',
      ad_user_data: state.marketing ? 'granted' : 'denied',
      ad_personalization: state.marketing ? 'granted' : 'denied'
    });
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'nexis_consent_update',
      nexis_consent: {
        analytics: !!state.analytics,
        marketing: !!state.marketing
      }
    });
  }

  function syncCheckboxes(state) {
    var analyticsBox = root.querySelector('[data-nx-cat="analytics"]');
    var marketingBox = root.querySelector('[data-nx-cat="marketing"]');
    if (analyticsBox) {
      analyticsBox.checked = !!(state && state.analytics);
    }
    if (marketingBox) {
      marketingBox.checked = !!(state && state.marketing);
    }
  }

  function openSettings() {
    var current = parseConsent(readCookie(cookieName)) || {
      analytics: hasAnalytics,
      marketing: false
    };
    syncCheckboxes(current);
    root.hidden = false;
    root.setAttribute('tabindex', '-1');
    try {
      root.focus({ preventScroll: false });
    } catch (e) {}
    root.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }

  function persist(state) {
    var payload = JSON.stringify({
      v: 1,
      necessary: true,
      analytics: !!state.analytics,
      marketing: !!state.marketing,
      ts: Date.now()
    });
    writeCookie(cookieName, payload);
    applyGtagConsent(state);
    root.hidden = true;
  }

  function readUiState(forceAll) {
    if (forceAll === true) {
      return {
        analytics: hasAnalytics,
        marketing: hasMarketing
      };
    }
    if (forceAll === false) {
      return { analytics: false, marketing: false };
    }
    var analyticsBox = root.querySelector('[data-nx-cat="analytics"]');
    var marketingBox = root.querySelector('[data-nx-cat="marketing"]');
    return {
      analytics: hasAnalytics && analyticsBox ? !!analyticsBox.checked : false,
      marketing: hasMarketing && marketingBox ? !!marketingBox.checked : false
    };
  }

  var existing = parseConsent(readCookie(cookieName));
  if (existing) {
    applyGtagConsent(existing);
    syncCheckboxes(existing);
    root.hidden = true;
  } else {
    syncCheckboxes({ analytics: hasAnalytics, marketing: false });
    root.hidden = false;
  }

  var acceptBtn = root.querySelector('[data-nx-consent-accept]');
  var rejectBtn = root.querySelector('[data-nx-consent-reject]');
  var saveBtn = root.querySelector('[data-nx-consent-save]');

  if (acceptBtn) {
    acceptBtn.addEventListener('click', function () {
      persist(readUiState(true));
    });
  }
  if (rejectBtn) {
    rejectBtn.addEventListener('click', function () {
      persist(readUiState(false));
    });
  }
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      persist(readUiState(null));
    });
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) {
      return;
    }
    var opener = target.closest('[data-nx-consent-open]');
    if (!opener) {
      return;
    }
    event.preventDefault();
    openSettings();
  });

  if (window.location.hash === '#cookies' || /[?&]cookies=1(?:&|$)/.test(window.location.search)) {
    openSettings();
  }

  window.nexisOpenConsentSettings = openSettings;
})();
