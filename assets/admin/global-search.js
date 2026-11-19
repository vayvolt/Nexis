(function () {
  'use strict';

  var root = document.getElementById('admin-global-search');
  var dialog = document.getElementById('admin-global-search-dialog');
  var input = document.getElementById('admin-global-search-input');
  var results = document.getElementById('admin-global-search-results');
  var openBtn = document.querySelector('[data-admin-search-open]');
  if (!root || !dialog || !input || !results || typeof dialog.showModal !== 'function') {
    return;
  }

  var endpoint = root.getAttribute('data-endpoint') || '';
  var i18n = {
    empty: root.getAttribute('data-i18n-empty') || '',
    hint: root.getAttribute('data-i18n-hint') || '',
    pages: root.getAttribute('data-i18n-pages') || 'Pages',
    media: root.getAttribute('data-i18n-media') || 'Media',
    users: root.getAttribute('data-i18n-users') || 'Users',
    loading: root.getAttribute('data-i18n-loading') || '',
  };

  var debounceTimer = null;
  var abortController = null;
  var activeIndex = -1;
  var flatItems = [];

  function openSearch() {
    if (!dialog.open) {
      dialog.showModal();
    }
    input.value = '';
    activeIndex = -1;
    flatItems = [];
    renderHint();
    window.setTimeout(function () {
      input.focus();
      input.select();
    }, 0);
  }

  function closeSearch() {
    if (abortController) {
      abortController.abort();
      abortController = null;
    }
    if (dialog.open) {
      dialog.close();
    }
  }

  function renderHint() {
    results.innerHTML = '<p class="admin-search__hint">' + escapeHtml(i18n.hint) + '</p>';
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setActive(index) {
    activeIndex = index;
    var links = results.querySelectorAll('[data-admin-search-item]');
    for (var i = 0; i < links.length; i++) {
      if (i === activeIndex) {
        links[i].classList.add('is-active');
        links[i].setAttribute('aria-selected', 'true');
        if (typeof links[i].scrollIntoView === 'function') {
          links[i].scrollIntoView({ block: 'nearest' });
        }
      } else {
        links[i].classList.remove('is-active');
        links[i].setAttribute('aria-selected', 'false');
      }
    }
  }

  function renderGroups(payload) {
    flatItems = [];
    var groups = [
      { key: 'pages', label: i18n.pages, items: payload.pages || [] },
      { key: 'media', label: i18n.media, items: payload.media || [] },
      { key: 'users', label: i18n.users, items: payload.users || [] },
    ];
    var html = '';
    var total = 0;
    for (var g = 0; g < groups.length; g++) {
      var group = groups[g];
      if (!group.items.length) {
        continue;
      }
      html += '<section class="admin-search__group">';
      html += '<h3 class="admin-search__group-title">' + escapeHtml(group.label) + '</h3>';
      html += '<ul class="admin-search__list" role="listbox">';
      for (var i = 0; i < group.items.length; i++) {
        var item = group.items[i];
        flatItems.push(item);
        html += '<li>';
        html +=
          '<a href="' +
          escapeHtml(item.url || '#') +
          '" class="admin-search__item" data-admin-search-item role="option" aria-selected="false">';
        html += '<span class="admin-search__label">' + escapeHtml(item.label || '') + '</span>';
        if (item.meta) {
          html += '<span class="admin-search__meta">' + escapeHtml(item.meta) + '</span>';
        }
        html += '</a></li>';
        total += 1;
      }
      html += '</ul></section>';
    }
    if (total === 0) {
      results.innerHTML = '<p class="admin-search__hint">' + escapeHtml(i18n.empty) + '</p>';
      activeIndex = -1;
      return;
    }
    results.innerHTML = html;
    setActive(0);
  }

  function runSearch(query) {
    if (abortController) {
      abortController.abort();
    }
    if (query.length < 2) {
      renderHint();
      return;
    }
    results.innerHTML = '<p class="admin-search__hint">' + escapeHtml(i18n.loading) + '</p>';
    abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query) + '&limit=5';
    var opts = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (abortController) {
      opts.signal = abortController.signal;
    }
    fetch(url, opts)
      .then(function (res) {
        if (!res.ok) {
          throw new Error('search failed');
        }
        return res.json();
      })
      .then(function (body) {
        var data = body && body.data ? body.data : body;
        renderGroups(data || {});
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') {
          return;
        }
        results.innerHTML = '<p class="admin-search__hint">' + escapeHtml(i18n.empty) + '</p>';
      });
  }

  function scheduleSearch() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(function () {
      runSearch((input.value || '').trim());
    }, 200);
  }

  if (openBtn) {
    openBtn.addEventListener('click', function () {
      openSearch();
    });
  }

  input.addEventListener('input', scheduleSearch);

  input.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowDown') {
      ev.preventDefault();
      if (flatItems.length === 0) {
        return;
      }
      setActive(activeIndex < flatItems.length - 1 ? activeIndex + 1 : 0);
      return;
    }
    if (ev.key === 'ArrowUp') {
      ev.preventDefault();
      if (flatItems.length === 0) {
        return;
      }
      setActive(activeIndex > 0 ? activeIndex - 1 : flatItems.length - 1);
      return;
    }
    if (ev.key === 'Enter') {
      if (activeIndex >= 0 && flatItems[activeIndex] && flatItems[activeIndex].url) {
        ev.preventDefault();
        window.location.href = flatItems[activeIndex].url;
      }
      return;
    }
    if (ev.key === 'Escape') {
      ev.preventDefault();
      closeSearch();
    }
  });

  dialog.addEventListener('click', function (ev) {
    if (ev.target === dialog) {
      closeSearch();
    }
  });

  dialog.addEventListener('cancel', function () {
    if (abortController) {
      abortController.abort();
      abortController = null;
    }
  });

  document.addEventListener('keydown', function (ev) {
    var tag = (ev.target && ev.target.tagName) || '';
    var typing =
      tag === 'INPUT' ||
      tag === 'TEXTAREA' ||
      tag === 'SELECT' ||
      (ev.target && ev.target.isContentEditable);
    if ((ev.ctrlKey || ev.metaKey) && String(ev.key || '').toLowerCase() === 'k') {
      ev.preventDefault();
      if (dialog.open) {
        closeSearch();
      } else {
        openSearch();
      }
      return;
    }
    if (!typing && !ev.ctrlKey && !ev.metaKey && !ev.altKey && ev.key === '/') {
      ev.preventDefault();
      openSearch();
    }
  });
})();
