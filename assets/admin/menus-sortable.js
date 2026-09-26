(function () {
  'use strict';

  var form = document.querySelector('form[action$="/admin/menus"]');
  if (!form) {
    return;
  }

  var bodies = Array.prototype.slice.call(form.querySelectorAll('tbody[data-menu-sortable]'));
  if (bodies.length === 0) {
    return;
  }

  var dragIndex = null;

  function rowsOf(tbody) {
    return Array.prototype.slice.call(tbody.children);
  }

  function reindex() {
    var mapOldToNew = {};
    var primary = bodies[0];
    var rows = rowsOf(primary);
    rows.forEach(function (row, newIndex) {
      var old = parseInt(row.getAttribute('data-menu-index') || String(newIndex), 10);
      mapOldToNew[old] = newIndex;
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

    bodies.forEach(function (tbody) {
      rowsOf(tbody).forEach(function (row) {
        var parentInput = row.querySelector('input[name^="parent["]');
        if (!parentInput) {
          return;
        }
        var raw = parentInput.value.trim();
        if (raw === '') {
          return;
        }
        var oldParent = parseInt(raw, 10);
        if (!isNaN(oldParent) && Object.prototype.hasOwnProperty.call(mapOldToNew, oldParent)) {
          parentInput.value = String(mapOldToNew[oldParent]);
        }
      });
    });

    var parentValues = rowsOf(primary).map(function (row) {
      var input = row.querySelector('input[name^="parent["]');
      return input ? input.value.trim() : '';
    });
    bodies.forEach(function (tbody) {
      rowsOf(tbody).forEach(function (row, index) {
        var display = row.querySelector('[data-menu-parent-display]');
        if (!display) {
          return;
        }
        var value = parentValues[index] || '';
        display.textContent = value !== '' ? value : '—';
      });
    });
  }

  function moveAll(from, to) {
    if (from === to || from < 0 || to < 0) {
      return;
    }
    bodies.forEach(function (tbody) {
      var rows = rowsOf(tbody);
      var row = rows[from];
      if (!row) {
        return;
      }
      var target = rows[to];
      if (!target) {
        tbody.appendChild(row);
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

  bodies.forEach(function (tbody) {
    rowsOf(tbody).forEach(function (row) {
      var handle = row.querySelector('[data-menu-drag]');
      if (!handle) {
        return;
      }
      handle.addEventListener('dragstart', function (ev) {
        dragIndex = rowsOf(tbody).indexOf(row);
        row.classList.add('is-dragging');
        if (ev.dataTransfer) {
          ev.dataTransfer.effectAllowed = 'move';
          ev.dataTransfer.setData('text/plain', String(dragIndex));
        }
      });
      handle.addEventListener('dragend', function () {
        row.classList.remove('is-dragging');
        bodies.forEach(function (b) {
          rowsOf(b).forEach(function (r) {
            r.classList.remove('is-drag-over');
          });
        });
        dragIndex = null;
      });

      row.addEventListener('dragover', function (ev) {
        ev.preventDefault();
        if (ev.dataTransfer) {
          ev.dataTransfer.dropEffect = 'move';
        }
        row.classList.add('is-drag-over');
      });
      row.addEventListener('dragleave', function () {
        row.classList.remove('is-drag-over');
      });
      row.addEventListener('drop', function (ev) {
        ev.preventDefault();
        row.classList.remove('is-drag-over');
        var to = rowsOf(tbody).indexOf(row);
        if (dragIndex === null || to < 0) {
          return;
        }
        moveAll(dragIndex, to);
        dragIndex = null;
      });
    });
  });
})();
