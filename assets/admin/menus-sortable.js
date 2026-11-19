(function () {
  'use strict';

  var form = document.querySelector('form[data-menu-editor]');
  if (!form) {
    return;
  }

  var bodies = Array.prototype.slice.call(form.querySelectorAll('tbody[data-menu-sortable]'));
  if (bodies.length === 0) {
    return;
  }

  var isPrimary = form.getAttribute('data-menu-primary') === '1';
  var defaultLocale = form.getAttribute('data-menu-default-locale') || '';
  var i18n = {
    parentNone: form.getAttribute('data-i18n-parent-none') || '',
    parentFallback: form.getAttribute('data-i18n-parent-fallback') || 'Row :n',
    drag: form.getAttribute('data-i18n-drag') || '',
    moveUp: form.getAttribute('data-i18n-move-up') || 'Up',
    moveDown: form.getAttribute('data-i18n-move-down') || 'Down',
    remove: form.getAttribute('data-i18n-remove') || 'Remove',
    labelPh: form.getAttribute('data-i18n-label-ph') || '',
    external: form.getAttribute('data-i18n-external') || '',
  };

  var dragIndex = null;

  function rowsOf(tbody) {
    return Array.prototype.filter.call(tbody.children, function (el) {
      return el.tagName === 'TR';
    });
  }

  function primaryBody() {
    for (var i = 0; i < bodies.length; i++) {
      if ((bodies[i].getAttribute('data-menu-locale') || '') === defaultLocale) {
        return bodies[i];
      }
    }
    return bodies[0];
  }

  function rowLabel(row) {
    var input = row.querySelector('[data-menu-label]');
    return input ? String(input.value || '').trim() : '';
  }

  function parentFallback(index) {
    return String(i18n.parentFallback).replace(':n', String(index));
  }

  function refreshParents() {
    if (!isPrimary) {
      return;
    }
    var primary = primaryBody();
    var primaryRows = rowsOf(primary);
    var labels = primaryRows.map(function (row, index) {
      var label = rowLabel(row);
      return label !== '' ? label : parentFallback(index);
    });
    var parents = primaryRows.map(function (row) {
      var select = row.querySelector('select[data-menu-parent]');
      return select ? String(select.value || '').trim() : '';
    });

    primaryRows.forEach(function (row, index) {
      var select = row.querySelector('select[data-menu-parent]');
      if (!select) {
        return;
      }
      var current = String(select.value || '');
      var html = '<option value="">' + escapeHtml(i18n.parentNone) + '</option>';
      primaryRows.forEach(function (candidate, candIndex) {
        if (candIndex === index) {
          return;
        }
        if (parents[candIndex] !== '') {
          return;
        }
        if (rowLabel(candidate) === '') {
          return;
        }
        html +=
          '<option value="' +
          candIndex +
          '"' +
          (current === String(candIndex) ? ' selected' : '') +
          '>' +
          escapeHtml(labels[candIndex]) +
          '</option>';
      });
      select.innerHTML = html;
      if (current !== '' && select.value !== current) {
        select.value = '';
      }
    });

    // Re-read after option rebuild may have cleared invalid parents
    parents = primaryRows.map(function (row) {
      var select = row.querySelector('select[data-menu-parent]');
      return select ? String(select.value || '').trim() : '';
    });
    var displayLabels = primaryRows.map(function (row, index) {
      var parent = parents[index];
      if (parent === '') {
        return '—';
      }
      var p = parseInt(parent, 10);
      if (isNaN(p) || p < 0 || p >= labels.length) {
        return parentFallback(parent);
      }
      return labels[p];
    });

    bodies.forEach(function (tbody) {
      rowsOf(tbody).forEach(function (row, index) {
        var display = row.querySelector('[data-menu-parent-display]');
        if (display) {
          display.textContent = displayLabels[index] || '—';
        }
      });
    });
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function reindex() {
    var mapOldToNew = {};
    var primary = primaryBody();
    rowsOf(primary).forEach(function (row, newIndex) {
      var old = parseInt(row.getAttribute('data-menu-index') || String(newIndex), 10);
      mapOldToNew[old] = newIndex;
    });
    rowsOf(primary).forEach(function (row) {
      var parentEl = row.querySelector('select[data-menu-parent], input[data-menu-parent]');
      var raw = parentEl ? String(parentEl.value || '').trim() : '';
      var mapped = '';
      if (raw !== '') {
        var oldParent = parseInt(raw, 10);
        if (!isNaN(oldParent) && Object.prototype.hasOwnProperty.call(mapOldToNew, oldParent)) {
          mapped = String(mapOldToNew[oldParent]);
        }
      }
      row.setAttribute('data-mapped-parent', mapped);
    });

    bodies.forEach(function (tbody) {
      rowsOf(tbody).forEach(function (row, index) {
        row.setAttribute('data-menu-index', String(index));
        var num = row.querySelector('[data-menu-index-label]');
        if (num) {
          num.textContent = String(index);
        }
        Array.prototype.forEach.call(row.querySelectorAll('[name]'), function (el) {
          var name = el.getAttribute('name') || '';
          el.setAttribute(
            'name',
            name
              .replace(/^label\[([^\]]+)\]\[\d+\]$/, 'label[$1][' + index + ']')
              .replace(/^page_id\[([^\]]+)\]\[\d+\]$/, 'page_id[$1][' + index + ']')
              .replace(/^url\[([^\]]+)\]\[\d+\]$/, 'url[$1][' + index + ']')
              .replace(/^parent\[\d+\]$/, 'parent[' + index + ']')
          );
        });
      });
    });

    refreshParents();

    rowsOf(primary).forEach(function (row) {
      var parentEl = row.querySelector('select[data-menu-parent], input[data-menu-parent]');
      var mapped = row.getAttribute('data-mapped-parent') || '';
      row.removeAttribute('data-mapped-parent');
      if (parentEl) {
        parentEl.value = mapped;
      }
    });
    refreshParents();
  }

  function moveAll(from, to) {
    if (from === to || from < 0 || to < 0) {
      return;
    }
    bodies.forEach(function (tbody) {
      var rows = rowsOf(tbody);
      if (to >= rows.length) {
        return;
      }
      var row = rows[from];
      var target = rows[to];
      if (!row || !target) {
        return;
      }
      if (from < to) {
        tbody.insertBefore(row, target.nextSibling);
      } else {
        tbody.insertBefore(row, target);
      }
    });
    reindex();
  }

  function removeAll(index) {
    var primaryRows = rowsOf(primaryBody());
    if (primaryRows.length <= 1) {
      // Keep one empty row
      bodies.forEach(function (tbody) {
        var row = rowsOf(tbody)[0];
        if (!row) {
          return;
        }
        var label = row.querySelector('[data-menu-label]');
        var page = row.querySelector('[data-menu-page]');
        var url = row.querySelector('[data-menu-url]');
        var parent = row.querySelector('[data-menu-parent]');
        if (label) label.value = '';
        if (url) url.value = '';
        if (page) page.value = '';
        if (parent) parent.value = '';
      });
      refreshParents();
      return;
    }
    bodies.forEach(function (tbody) {
      var row = rowsOf(tbody)[index];
      if (row) {
        row.parentNode.removeChild(row);
      }
    });
    reindex();
  }

  function buildRow(tbody, index) {
    var locale = tbody.getAttribute('data-menu-locale') || '';
    var sample = rowsOf(tbody)[0];
    var pageOptions = '';
    if (sample) {
      var samplePage = sample.querySelector('[data-menu-page]');
      if (samplePage) {
        pageOptions = samplePage.innerHTML;
      }
    }
    if (pageOptions === '') {
      pageOptions = '<option value="">' + escapeHtml(i18n.external) + '</option>';
    }

    var tr = document.createElement('tr');
    tr.setAttribute('data-menu-index', String(index));

    var parentCell = '';
    if (isPrimary) {
      if (locale === defaultLocale) {
        parentCell =
          '<td><select name="parent[' +
          index +
          ']" data-menu-parent><option value="">' +
          escapeHtml(i18n.parentNone) +
          '</option></select></td>';
      } else {
        parentCell = '<td><span class="muted" data-menu-parent-display>—</span></td>';
      }
    } else if (locale === defaultLocale) {
      parentCell = '<input type="hidden" name="parent[' + index + ']" value="" data-menu-parent>';
    }

    tr.innerHTML =
      '<td><span class="menu-sort-handle" data-menu-drag draggable="true" role="button" tabindex="0" aria-label="' +
      escapeHtml(i18n.drag) +
      '" title="' +
      escapeHtml(i18n.drag) +
      '">⋮⋮</span></td>' +
      '<td class="muted" data-menu-index-label>' +
      index +
      '</td>' +
      '<td><input name="label[' +
      escapeHtml(locale) +
      '][' +
      index +
      ']" value="" placeholder="' +
      escapeHtml(i18n.labelPh) +
      '" data-menu-label></td>' +
      parentCell +
      '<td><select name="page_id[' +
      escapeHtml(locale) +
      '][' +
      index +
      ']" data-menu-page>' +
      pageOptions +
      '</select></td>' +
      '<td><input name="url[' +
      escapeHtml(locale) +
      '][' +
      index +
      ']" value="" placeholder="/de oder https://…" data-menu-url></td>' +
      '<td style="white-space:nowrap"><div class="menu-row-actions">' +
      '<button type="button" class="btn btn-small btn-muted" data-menu-move-up title="' +
      escapeHtml(i18n.moveUp) +
      '" aria-label="' +
      escapeHtml(i18n.moveUp) +
      '">↑</button>' +
      '<button type="button" class="btn btn-small btn-muted" data-menu-move-down title="' +
      escapeHtml(i18n.moveDown) +
      '" aria-label="' +
      escapeHtml(i18n.moveDown) +
      '">↓</button>' +
      '<button type="button" class="btn-danger btn-small" data-menu-remove title="' +
      escapeHtml(i18n.remove) +
      '" aria-label="' +
      escapeHtml(i18n.remove) +
      '">×</button>' +
      '</div></td>';

    var pageSelect = tr.querySelector('[data-menu-page]');
    if (pageSelect) {
      pageSelect.value = '';
    }
    return tr;
  }

  function addRow() {
    var index = rowsOf(primaryBody()).length;
    bodies.forEach(function (tbody) {
      tbody.appendChild(buildRow(tbody, index));
    });
    reindex();
    var focusInput = primaryBody().querySelector('tr:last-child [data-menu-label]');
    if (focusInput) {
      focusInput.focus();
    }
  }

  form.addEventListener('click', function (ev) {
    var target = ev.target;
    if (!(target instanceof Element)) {
      return;
    }
    if (target.closest('[data-menu-add]')) {
      ev.preventDefault();
      addRow();
      return;
    }
    var row = target.closest('tr[data-menu-index]');
    if (!row) {
      return;
    }
    var tbody = row.parentElement;
    if (!tbody) {
      return;
    }
    var index = rowsOf(tbody).indexOf(row);
    if (index < 0) {
      return;
    }
    if (target.closest('[data-menu-remove]')) {
      ev.preventDefault();
      removeAll(index);
      return;
    }
    if (target.closest('[data-menu-move-up]')) {
      ev.preventDefault();
      if (index > 0) {
        moveAll(index, index - 1);
      }
      return;
    }
    if (target.closest('[data-menu-move-down]')) {
      ev.preventDefault();
      if (index < rowsOf(tbody).length - 1) {
        moveAll(index, index + 1);
      }
    }
  });

  form.addEventListener('input', function (ev) {
    var target = ev.target;
    if (!(target instanceof Element)) {
      return;
    }
    if (target.matches('[data-menu-label]') || target.matches('[data-menu-parent]')) {
      refreshParents();
    }
  });

  form.addEventListener('change', function (ev) {
    var target = ev.target;
    if (!(target instanceof Element)) {
      return;
    }
    if (target.matches('[data-menu-parent]')) {
      refreshParents();
    }
  });

  form.addEventListener('dragstart', function (ev) {
    var handle = ev.target instanceof Element ? ev.target.closest('[data-menu-drag]') : null;
    if (!handle) {
      return;
    }
    var row = handle.closest('tr[data-menu-index]');
    var tbody = row && row.parentElement;
    if (!row || !tbody) {
      return;
    }
    dragIndex = rowsOf(tbody).indexOf(row);
    row.classList.add('is-dragging');
    if (ev.dataTransfer) {
      ev.dataTransfer.effectAllowed = 'move';
      ev.dataTransfer.setData('text/plain', String(dragIndex));
    }
  });

  form.addEventListener('dragend', function (ev) {
    var handle = ev.target instanceof Element ? ev.target.closest('[data-menu-drag]') : null;
    if (!handle) {
      return;
    }
    var row = handle.closest('tr');
    if (row) {
      row.classList.remove('is-dragging');
    }
    bodies.forEach(function (b) {
      rowsOf(b).forEach(function (r) {
        r.classList.remove('is-drag-over');
      });
    });
    dragIndex = null;
  });

  form.addEventListener('dragover', function (ev) {
    var row = ev.target instanceof Element ? ev.target.closest('tr[data-menu-index]') : null;
    if (!row || !form.contains(row)) {
      return;
    }
    ev.preventDefault();
    if (ev.dataTransfer) {
      ev.dataTransfer.dropEffect = 'move';
    }
    row.classList.add('is-drag-over');
  });

  form.addEventListener('dragleave', function (ev) {
    var row = ev.target instanceof Element ? ev.target.closest('tr[data-menu-index]') : null;
    if (row) {
      row.classList.remove('is-drag-over');
    }
  });

  form.addEventListener('drop', function (ev) {
    var row = ev.target instanceof Element ? ev.target.closest('tr[data-menu-index]') : null;
    if (!row || !form.contains(row)) {
      return;
    }
    ev.preventDefault();
    row.classList.remove('is-drag-over');
    var tbody = row.parentElement;
    if (!tbody || dragIndex === null) {
      return;
    }
    var to = rowsOf(tbody).indexOf(row);
    if (to < 0) {
      return;
    }
    moveAll(dragIndex, to);
    dragIndex = null;
  });

  form.addEventListener('keydown', function (ev) {
    var handle = ev.target instanceof Element ? ev.target.closest('[data-menu-drag]') : null;
    if (!handle) {
      return;
    }
    // Plain arrows when the handle is focused (Alt+arrows are unreliable on Windows).
    if (ev.key !== 'ArrowUp' && ev.key !== 'ArrowDown') {
      return;
    }
    if (ev.altKey || ev.ctrlKey || ev.metaKey || ev.shiftKey) {
      return;
    }
    var row = handle.closest('tr[data-menu-index]');
    var tbody = row && row.parentElement;
    if (!row || !tbody) {
      return;
    }
    var index = rowsOf(tbody).indexOf(row);
    ev.preventDefault();
    if (ev.key === 'ArrowUp' && index > 0) {
      moveAll(index, index - 1);
      var next = rowsOf(tbody)[index - 1];
      var nextHandle = next && next.querySelector('[data-menu-drag]');
      if (nextHandle) nextHandle.focus();
    }
    if (ev.key === 'ArrowDown' && index < rowsOf(tbody).length - 1) {
      moveAll(index, index + 1);
      var moved = rowsOf(tbody)[index + 1];
      var movedHandle = moved && moved.querySelector('[data-menu-drag]');
      if (movedHandle) movedHandle.focus();
    }
  });

  refreshParents();
})();
