(function () {
  'use strict';

  function uid() {
    if (window.crypto && crypto.randomUUID) {
      return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-7xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      var v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  function deepClone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function regenerateIds(node) {
    var clone = deepClone(node);
    function walk(n) {
      n.id = uid();
      var kids = n.children || [];
      for (var i = 0; i < kids.length; i++) {
        if (kids[i] && typeof kids[i] === 'object') {
          walk(kids[i]);
        }
      }
    }
    walk(clone);
    return clone;
  }

  function findCatalog(catalog, type) {
    for (var i = 0; i < catalog.length; i++) {
      if (catalog[i].type === type) {
        return catalog[i];
      }
    }
    return null;
  }

  function createBlock(catalog, type) {
    var meta = findCatalog(catalog, type);
    if (!meta) {
      return null;
    }
    var block = {
      id: uid(),
      type: type,
      props: deepClone(meta.defaultProps || {}),
    };
    if (meta.allowsChildren) {
      block.children = [];
    }
    return block;
  }

  function findParent(root, id, parent) {
    if (root.id === id) {
      return { node: root, parent: parent || null };
    }
    var kids = root.children || [];
    for (var i = 0; i < kids.length; i++) {
      var found = findParent(kids[i], id, root);
      if (found) {
        return found;
      }
    }
    return null;
  }

  function removeNode(root, id) {
    if (!root.children) {
      return false;
    }
    for (var i = 0; i < root.children.length; i++) {
      if (root.children[i].id === id) {
        root.children.splice(i, 1);
        return true;
      }
    }
    for (var j = 0; j < root.children.length; j++) {
      if (removeNode(root.children[j], id)) {
        return true;
      }
    }
    return false;
  }

  function moveNode(root, nodeId, targetParentId, index) {
    var found = findParent(root, nodeId, null);
    if (!found || !found.parent) {
      return false;
    }
    var node = found.node;
    var fromKids = found.parent.children;
    var fromIndex = fromKids.indexOf(node);
    if (fromIndex < 0) {
      return false;
    }
    var target = findParent(root, targetParentId, null);
    if (!target) {
      return false;
    }
    if (!Array.isArray(target.node.children)) {
      target.node.children = [];
    }
    fromKids.splice(fromIndex, 1);
    var insertAt = typeof index === 'number' ? index : target.node.children.length;
    if (target.node.id === found.parent.id && insertAt > fromIndex) {
      insertAt -= 1;
    }
    target.node.children.splice(Math.max(0, insertAt), 0, node);
    return true;
  }

  function listBlocks(node, acc) {
    acc = acc || [];
    acc.push(node);
    var kids = node.children || [];
    for (var i = 0; i < kids.length; i++) {
      listBlocks(kids[i], acc);
    }
    return acc;
  }

  function moveSibling(root, id, delta) {
    var found = findParent(root, id, null);
    if (!found || !found.parent || !Array.isArray(found.parent.children)) {
      return false;
    }
    var kids = found.parent.children;
    var idx = kids.indexOf(found.node);
    var next = idx + delta;
    if (idx < 0 || next < 0 || next >= kids.length) {
      return false;
    }
    kids.splice(idx, 1);
    kids.splice(next, 0, found.node);
    return true;
  }

  function schemaFields(schema) {
    if (!schema || !schema.properties) {
      return [];
    }
    var fields = [];
    Object.keys(schema.properties).forEach(function (key) {
      fields.push({ key: key, def: schema.properties[key] || {} });
    });
    return fields;
  }

  var LONG_TEXT_FIELDS = {
    text: 1,
    message: 1,
    successMessage: 1,
    answer: 1,
    address: 1,
    hours: 1,
    csv: 1,
  };

  function isLongTextField(key) {
    return Object.prototype.hasOwnProperty.call(LONG_TEXT_FIELDS, key);
  }

  function longTextRows(key) {
    if (key === 'text' || key === 'answer') {
      return 12;
    }
    if (key === 'message' || key === 'successMessage') {
      return 6;
    }
    return 5;
  }

  var PROP_LABELS = {
    text: 'Text',
    level: 'Ebene',
    label: 'Beschriftung',
    href: 'Link-URL',
    style: 'Stil',
    width: 'Breite',
    padding: 'Innenabstand',
    assetId: 'Medium',
    assetIds: 'Bilder',
    alt: 'Alternativtext',
    src: 'Bild-URL',
    url: 'URL',
    csv: 'CSV (Komma-getrennt)',
    header: 'Kopfzeile',
    caption: 'Bildunterschrift',
    columns: 'Spalten',
    aspect: 'Seitenverhältnis',
    title: 'Titel',
    heading: 'Überschrift',
    message: 'Nachricht',
    submitLabel: 'Button-Text',
    successMessage: 'Erfolgsmeldung',
  };

  var ENUM_LABELS = {
    narrow: 'schmal',
    wide: 'breit',
    full: 'voll',
    sm: 'klein',
    md: 'mittel',
    lg: 'groß',
    xl: 'sehr groß',
    primary: 'primär',
    secondary: 'sekundär',
    yes: 'ja',
    no: 'nein',
    '16:9': '16:9',
    '4:3': '4:3',
    '1:1': '1:1',
    '2': '2',
    '3': '3',
    '4': '4',
  };

  var UI = {
    selectBlock: 'Block auswählen',
    remove: 'Entfernen',
    moveUp: 'Nach oben',
    moveDown: 'Nach unten',
    dropHere: 'Blöcke hier ablegen',
    dropHint: 'Blöcke hierher ziehen oder links anklicken',
    noProps: 'Keine Eigenschaften.',
    noBlocks: 'Keine Blöcke registriert.',
    addToSection: 'Zum ausgewählten Abschnitt hinzufügen',
    noImage: 'Kein Bild',
    noMedia: 'Keine Medien – unter Medien hochladen.',
    noRoot: 'Kein Wurzelblock – Katalog leer?',
    emptyText: 'Leerer Text',
    image: 'Bild',
    noImageSelected: 'Kein Bild gewählt',
    contactForm: 'Kontaktformular',
    faqItem: 'FAQ-Eintrag',
    faqAccordion: 'FAQ',
    productDetails: 'Produktdaten',
    sectionGap: ':width · Abstand :padding',
    columnsN: ':count Spalten',
    galleryN: ':count Bilder',
    tableRows: ':count Zeilen',
    embedEmpty: 'Keine Video-URL',
    canvasLabel: 'Seitenstruktur',
    patternsSection: 'Patterns',
    savePattern: 'Als Pattern speichern',
    patternName: 'Pattern-Name',
    patternNamePlaceholder: 'Name',
  };

  function parseAssetIds(raw) {
    return String(raw || '')
      .split(/[\s,;]+/)
      .map(function (part) {
        return String(part).trim();
      })
      .filter(function (part, index, all) {
        return part !== '' && all.indexOf(part) === index;
      });
  }

  function serializeAssetIds(ids) {
    return (ids || []).join(',');
  }

  function applyI18n(i18n) {
    if (!i18n || typeof i18n !== 'object') {
      return;
    }
    Object.keys(UI).forEach(function (key) {
      if (i18n[key] != null && i18n[key] !== '') {
        UI[key] = String(i18n[key]);
      }
    });
    if (i18n.props && typeof i18n.props === 'object') {
      Object.keys(i18n.props).forEach(function (key) {
        if (i18n.props[key] != null && i18n.props[key] !== '') {
          PROP_LABELS[key] = String(i18n.props[key]);
        }
      });
    }
    if (i18n.enums && typeof i18n.enums === 'object') {
      Object.keys(i18n.enums).forEach(function (key) {
        if (i18n.enums[key] != null && i18n.enums[key] !== '') {
          ENUM_LABELS[key] = String(i18n.enums[key]);
        }
      });
    }
  }

  function formatUi(template, replace) {
    var out = String(template || '');
    Object.keys(replace || {}).forEach(function (name) {
      out = out.split(':' + name).join(String(replace[name]));
    });
    return out;
  }

  function fieldLabel(key) {
    return PROP_LABELS[key] || key;
  }

  function enumLabel(value) {
    return ENUM_LABELS[value] || String(value);
  }

  function truncate(text, max) {
    var s = String(text || '').replace(/\s+/g, ' ').trim();
    if (s.length <= max) {
      return s;
    }
    return s.slice(0, max - 1) + '…';
  }

  function previewSnippet(node, media) {
    var props = node.props || {};
    if (node.type === 'core/heading') {
      return 'H' + (props.level || 2) + ': ' + truncate(props.text, 48);
    }
    if (node.type === 'core/text') {
      return truncate(props.text, 72) || UI.emptyText;
    }
    if (node.type === 'core/button') {
      return truncate(props.label, 40) + (props.href ? ' → ' + truncate(props.href, 28) : '');
    }
    if (node.type === 'core/image') {
      var name = '';
      var url = props.src || '';
      var id = props.assetId || '';
      (media || []).forEach(function (m) {
        if (m.id === id) {
          name = m.name;
          url = m.url || url;
        }
      });
      if (url) {
        return { kind: 'image', url: url, caption: truncate(name || props.alt || UI.image, 48) };
      }
      return truncate(name || props.alt || UI.noImageSelected, 48);
    }
    if (node.type === 'core/gallery') {
      var ids = parseAssetIds(props.assetIds || '');
      var count = ids.length;
      var firstUrl = '';
      if (count > 0) {
        (media || []).forEach(function (m) {
          if (!firstUrl && m.id === ids[0]) {
            firstUrl = m.thumbUrl || m.url || '';
          }
        });
      }
      var galleryCaption = formatUi(UI.galleryN, { count: count });
      if (props.caption) {
        galleryCaption += ' · ' + truncate(props.caption, 32);
      }
      if (firstUrl) {
        return { kind: 'image', url: firstUrl, caption: galleryCaption };
      }
      return galleryCaption;
    }
    if (node.type === 'core/embed') {
      return truncate(props.title || props.url || UI.embedEmpty, 56);
    }
    if (node.type === 'core/table') {
      var rows = String(props.csv || '').split(/\n/).filter(function (line) {
        return String(line).trim() !== '';
      }).length;
      return formatUi(UI.tableRows, { count: rows });
    }
    if (node.type === 'nexis/faq/item') {
      return truncate(props.question || UI.faqItem, 56);
    }
    if (node.type === 'nexis/faq/accordion') {
      return truncate(props.heading || UI.faqAccordion, 48);
    }
    if (node.type === 'nexis/product/details') {
      var bits = [];
      if (props.name) {
        bits.push(String(props.name));
      }
      if (props.sku) {
        bits.push('SKU ' + String(props.sku));
      }
      if (props.price) {
        bits.push(String(props.price) + (props.currency ? ' ' + String(props.currency) : ''));
      }
      if (props.brand) {
        bits.push(String(props.brand));
      }
      return truncate(bits.join(' · ') || UI.productDetails, 72);
    }
    if (node.type === 'nexis/forms/contact') {
      return truncate(props.heading || UI.contactForm, 48);
    }
    if (node.type === 'core/section') {
      return formatUi(UI.sectionGap, {
        width: enumLabel(props.width || 'wide'),
        padding: enumLabel(props.padding || 'lg'),
      });
    }
    if (node.type === 'core/columns') {
      return formatUi(UI.columnsN, { count: props.columns || 2 });
    }
    if (props.text) {
      return truncate(props.text, 48);
    }
    if (props.label) {
      return truncate(props.label, 48);
    }
    return '';
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setPreviewContent(el, snippet) {
    el.innerHTML = '';
    if (snippet && typeof snippet === 'object' && snippet.kind === 'image') {
      var img = document.createElement('img');
      img.src = snippet.url;
      img.alt = snippet.caption || '';
      img.className = 'nx-block-thumb';
      el.appendChild(img);
      var cap = document.createElement('span');
      cap.textContent = snippet.caption || '';
      el.appendChild(cap);
      return;
    }
    el.textContent = typeof snippet === 'string' ? snippet : '';
  }

  function init(config) {
    applyI18n(config.i18n);
    var catalog = config.catalog || [];
    var patterns = config.patterns || [];
    var media = config.media || [];
    var savePatternUrl = config.savePatternUrl || '';
    var csrfToken = config.csrf || '';
    var documentInput = document.getElementById(config.documentInputId);
    var canvas = document.getElementById(config.canvasId);
    var palette = document.getElementById(config.paletteId);
    var propsPanel = document.getElementById(config.propsId);
    if (!documentInput || !canvas || !palette || !propsPanel) {
      return;
    }

    var state = {
      doc: null,
      selectedId: null,
      drag: null,
    };

    try {
      state.doc = JSON.parse(documentInput.value);
    } catch (e) {
      state.doc = {
        schemaVersion: 1,
        root: createBlock(catalog, 'core/section'),
      };
    }
    if (!state.doc.root) {
      state.doc.root = createBlock(catalog, 'core/section');
    }

    function syncInput() {
      documentInput.value = JSON.stringify(state.doc, null, 2);
    }

    function updateCanvasPreview(nodeId) {
      var found = findParent(state.doc.root, nodeId, null);
      if (!found) {
        return;
      }
      var el = canvas.querySelector('[data-id="' + nodeId + '"] > .nx-block-preview');
      if (el) {
        setPreviewContent(el, previewSnippet(found.node, media));
      }
    }

    function select(id, opts) {
      state.selectedId = id;
      render();
      if (opts && opts.focus) {
        var el = canvas.querySelector('[data-id="' + id + '"]');
        if (el) {
          el.focus({ preventScroll: false });
        }
      }
    }

    function insertParentId() {
      var found = state.selectedId ? findParent(state.doc.root, state.selectedId, null) : null;
      if (!found) {
        return state.doc.root.id;
      }
      var meta = findCatalog(catalog, found.node.type);
      if (meta && meta.allowsChildren) {
        return found.node.id;
      }
      if (found.parent) {
        return found.parent.id;
      }
      return state.doc.root.id;
    }

    function addBlock(type) {
      var block = createBlock(catalog, type);
      if (!block) {
        return;
      }
      insertBlockNode(block);
    }

    function insertBlockNode(block) {
      var parentFound = findParent(state.doc.root, insertParentId(), null);
      if (!parentFound) {
        return;
      }
      if (!Array.isArray(parentFound.node.children)) {
        parentFound.node.children = [];
      }
      parentFound.node.children.push(block);
      state.selectedId = block.id;
      syncInput();
      select(block.id, { focus: true });
    }

    function addPattern(documentNode) {
      if (!documentNode || typeof documentNode !== 'object') {
        return;
      }
      insertBlockNode(regenerateIds(documentNode));
    }

    function renderPalette() {
      palette.innerHTML = '';
      if (catalog.length === 0 && patterns.length === 0) {
        palette.innerHTML = '<p class="muted">' + escapeHtml(UI.noBlocks) + '</p>';
        return;
      }
      catalog.forEach(function (item) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nx-palette-item';
        btn.draggable = true;
        btn.textContent = item.label;
        btn.dataset.type = item.type;
        btn.title = UI.addToSection;
        btn.addEventListener('dragstart', function (ev) {
          state.drag = { kind: 'new', type: item.type };
          ev.dataTransfer.setData('text/plain', item.type);
          ev.dataTransfer.effectAllowed = 'copy';
        });
        btn.addEventListener('click', function () {
          addBlock(item.type);
        });
        palette.appendChild(btn);
      });
      if (patterns.length > 0) {
        var heading = document.createElement('h3');
        heading.className = 'nx-palette-heading';
        heading.textContent = UI.patternsSection;
        palette.appendChild(heading);
        patterns.forEach(function (item) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'nx-palette-item nx-palette-item--pattern';
          btn.draggable = true;
          btn.textContent = item.name || item.id;
          btn.title = item.name || '';
          btn.addEventListener('dragstart', function (ev) {
            state.drag = { kind: 'pattern', document: item.document };
            ev.dataTransfer.setData('text/plain', 'pattern:' + item.id);
            ev.dataTransfer.effectAllowed = 'copy';
          });
          btn.addEventListener('click', function () {
            addPattern(item.document);
          });
          palette.appendChild(btn);
        });
      }
    }

    function bindDropzone(el, parentId) {
      el.addEventListener('dragover', function (ev) {
        ev.preventDefault();
        el.classList.add('is-over');
      });
      el.addEventListener('dragleave', function () {
        el.classList.remove('is-over');
      });
      el.addEventListener('drop', function (ev) {
        ev.preventDefault();
        el.classList.remove('is-over');
        var parent = findParent(state.doc.root, parentId, null);
        if (!parent) {
          return;
        }
        var meta = findCatalog(catalog, parent.node.type);
        if (!meta || !meta.allowsChildren) {
          return;
        }
        if (!Array.isArray(parent.node.children)) {
          parent.node.children = [];
        }
        if (state.drag && state.drag.kind === 'new') {
          var created = createBlock(catalog, state.drag.type);
          if (created) {
            parent.node.children.push(created);
            state.selectedId = created.id;
          }
        } else if (state.drag && state.drag.kind === 'pattern') {
          var patterned = regenerateIds(state.drag.document);
          parent.node.children.push(patterned);
          state.selectedId = patterned.id;
        } else if (state.drag && state.drag.kind === 'move') {
          moveNode(state.doc.root, state.drag.id, parentId, parent.node.children.length);
        }
        state.drag = null;
        syncInput();
        render();
      });
    }

    function renderBlock(node) {
      var meta = findCatalog(catalog, node.type) || { label: node.type, allowsChildren: !!node.children };
      var wrap = document.createElement('div');
      wrap.className = 'nx-block' + (state.selectedId === node.id ? ' is-selected' : '');
      wrap.draggable = node.id !== state.doc.root.id;
      wrap.dataset.id = node.id;
      wrap.tabIndex = 0;
      wrap.setAttribute('role', 'treeitem');
      wrap.setAttribute('aria-selected', state.selectedId === node.id ? 'true' : 'false');
      wrap.setAttribute('aria-label', meta.label);

      var head = document.createElement('div');
      head.className = 'nx-block-head';
      head.innerHTML = '<strong>' + escapeHtml(meta.label) + '</strong>';
      head.addEventListener('click', function (ev) {
        ev.stopPropagation();
        select(node.id, { focus: true });
      });

      var actions = document.createElement('div');
      actions.className = 'nx-block-actions';
      if (node.id !== state.doc.root.id) {
        var up = document.createElement('button');
        up.type = 'button';
        up.textContent = '↑';
        up.title = UI.moveUp;
        up.setAttribute('aria-label', UI.moveUp);
        up.addEventListener('click', function (ev) {
          ev.stopPropagation();
          if (moveSibling(state.doc.root, node.id, -1)) {
            syncInput();
            select(node.id, { focus: true });
          }
        });
        var down = document.createElement('button');
        down.type = 'button';
        down.textContent = '↓';
        down.title = UI.moveDown;
        down.setAttribute('aria-label', UI.moveDown);
        down.addEventListener('click', function (ev) {
          ev.stopPropagation();
          if (moveSibling(state.doc.root, node.id, 1)) {
            syncInput();
            select(node.id, { focus: true });
          }
        });
        var del = document.createElement('button');
        del.type = 'button';
        del.textContent = UI.remove;
        del.addEventListener('click', function (ev) {
          ev.stopPropagation();
          removeNode(state.doc.root, node.id);
          if (state.selectedId === node.id) {
            state.selectedId = state.doc.root.id;
          }
          syncInput();
          select(state.selectedId, { focus: true });
        });
        actions.appendChild(up);
        actions.appendChild(down);
        actions.appendChild(del);
      }
      head.appendChild(actions);
      wrap.appendChild(head);

      var preview = document.createElement('div');
      preview.className = 'nx-block-preview';
      setPreviewContent(preview, previewSnippet(node, media));
      wrap.appendChild(preview);

      wrap.addEventListener('focus', function () {
        if (state.selectedId !== node.id) {
          state.selectedId = node.id;
          renderProps();
          canvas.querySelectorAll('.nx-block').forEach(function (el) {
            var on = el.dataset.id === node.id;
            el.classList.toggle('is-selected', on);
            el.setAttribute('aria-selected', on ? 'true' : 'false');
          });
        }
      });

      wrap.addEventListener('dragstart', function (ev) {
        if (node.id === state.doc.root.id) {
          return;
        }
        state.drag = { kind: 'move', id: node.id };
        ev.dataTransfer.setData('text/plain', node.id);
        ev.dataTransfer.effectAllowed = 'move';
        ev.stopPropagation();
      });

      if (meta.allowsChildren) {
        var zone = document.createElement('div');
        zone.className = 'nx-dropzone';
        zone.setAttribute('aria-label', UI.dropHere);
        bindDropzone(zone, node.id);
        var kids = node.children || [];
        kids.forEach(function (child) {
          zone.appendChild(renderBlock(child));
        });
        if (kids.length === 0) {
          var hint = document.createElement('span');
          hint.className = 'nx-dropzone-hint';
          hint.textContent = UI.dropHint;
          zone.appendChild(hint);
        }
        wrap.appendChild(zone);
      }

      return wrap;
    }

    function applyPropValue(node, field, def, value) {
      if (def.type === 'integer' || def.type === 'number') {
        node.props[field] = value === '' ? null : Number(value);
      } else {
        node.props[field] = value;
      }
      if (field === 'assetId') {
        var match = null;
        media.forEach(function (m) {
          if (m.id === value) {
            match = m;
          }
        });
        if (match) {
          node.props.src = match.url;
          if (!node.props.alt) {
            node.props.alt = match.alt || match.name;
          }
        } else if (value === '') {
          node.props.src = '';
        }
      }
      syncInput();
      updateCanvasPreview(node.id);
    }

    function toggleGalleryAsset(node, mediaId) {
      var ids = parseAssetIds(node.props.assetIds || '');
      var idx = ids.indexOf(mediaId);
      if (idx >= 0) {
        ids.splice(idx, 1);
      } else {
        ids.push(mediaId);
      }
      node.props.assetIds = serializeAssetIds(ids);
      syncInput();
      updateCanvasPreview(node.id);
    }

    function renderProps() {
      propsPanel.innerHTML = '';
      var found = state.selectedId ? findParent(state.doc.root, state.selectedId, null) : null;
      if (!found) {
        propsPanel.innerHTML = '<p class="muted">' + escapeHtml(UI.selectBlock) + '</p>';
        return;
      }
      var node = found.node;
      var meta = findCatalog(catalog, node.type);
      var title = document.createElement('h2');
      title.textContent = (meta && meta.label) || node.type;
      propsPanel.appendChild(title);

      if (node.id !== state.doc.root.id && savePatternUrl) {
        var patternCard = document.createElement('div');
        patternCard.className = 'nx-pattern-save card';
        var patternForm = document.createElement('form');
        patternForm.method = 'post';
        patternForm.action = savePatternUrl;
        if (csrfToken) {
          var csrfInput = document.createElement('input');
          csrfInput.type = 'hidden';
          csrfInput.name = '_csrf';
          csrfInput.value = csrfToken;
          patternForm.appendChild(csrfInput);
        }
        var docInput = document.createElement('input');
        docInput.type = 'hidden';
        docInput.name = 'document';
        docInput.value = JSON.stringify(node);
        patternForm.appendChild(docInput);
        var nameLabel = document.createElement('label');
        nameLabel.textContent = UI.patternName;
        var nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.name = 'name';
        nameInput.maxLength = 190;
        nameInput.placeholder = UI.patternNamePlaceholder;
        nameInput.required = true;
        nameLabel.appendChild(nameInput);
        patternForm.appendChild(nameLabel);
        var submit = document.createElement('button');
        submit.type = 'submit';
        submit.textContent = UI.savePattern;
        patternForm.appendChild(submit);
        patternCard.appendChild(patternForm);
        propsPanel.appendChild(patternCard);
      }

      var fields = schemaFields(meta && meta.propsSchema);
      if (fields.length === 0) {
        if (node.id === state.doc.root.id) {
          propsPanel.appendChild(document.createTextNode(UI.noProps));
        }
        return;
      }
      fields.forEach(function (field) {
        var label = document.createElement('label');
        label.textContent = fieldLabel(field.key);
        var input;
        var def = field.def || {};
        if (field.key === 'assetId') {
          var picker = document.createElement('div');
          picker.className = 'nx-media-picker';
          var clearBtn = document.createElement('button');
          clearBtn.type = 'button';
          clearBtn.className = 'nx-media-pick' + (!node.props.assetId ? ' is-active' : '');
          clearBtn.textContent = UI.noImage;
          clearBtn.addEventListener('click', function () {
            applyPropValue(node, 'assetId', def, '');
            renderProps();
          });
          picker.appendChild(clearBtn);
          media.forEach(function (m) {
            if (m.mime && m.mime.indexOf('image/') !== 0) {
              return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'nx-media-pick' + (String(node.props.assetId || '') === m.id ? ' is-active' : '');
            btn.title = m.name;
            var thumb = document.createElement('img');
            thumb.src = m.thumbUrl || m.url;
            thumb.alt = m.alt || m.name;
            btn.appendChild(thumb);
            var caption = document.createElement('span');
            caption.textContent = m.name;
            btn.appendChild(caption);
            btn.addEventListener('click', function () {
              applyPropValue(node, 'assetId', def, m.id);
              renderProps();
            });
            picker.appendChild(btn);
          });
          if (media.length === 0) {
            var hint = document.createElement('p');
            hint.className = 'muted';
            hint.textContent = UI.noMedia;
            picker.appendChild(hint);
          }
          label.appendChild(picker);
          propsPanel.appendChild(label);
          return;
        } else if (field.key === 'assetIds') {
          var multi = document.createElement('div');
          multi.className = 'nx-media-picker';
          var selectedIds = parseAssetIds(node.props.assetIds || '');
          var clearMulti = document.createElement('button');
          clearMulti.type = 'button';
          clearMulti.className = 'nx-media-pick' + (selectedIds.length === 0 ? ' is-active' : '');
          clearMulti.textContent = UI.noImage;
          clearMulti.addEventListener('click', function () {
            node.props.assetIds = '';
            syncInput();
            updateCanvasPreview(node.id);
            renderProps();
          });
          multi.appendChild(clearMulti);
          media.forEach(function (m) {
            if (m.mime && m.mime.indexOf('image/') !== 0) {
              return;
            }
            var mBtn = document.createElement('button');
            mBtn.type = 'button';
            mBtn.className = 'nx-media-pick' + (selectedIds.indexOf(m.id) >= 0 ? ' is-active' : '');
            mBtn.title = m.name;
            var mThumb = document.createElement('img');
            mThumb.src = m.thumbUrl || m.url;
            mThumb.alt = m.alt || m.name;
            mBtn.appendChild(mThumb);
            var mCaption = document.createElement('span');
            mCaption.textContent = m.name;
            mBtn.appendChild(mCaption);
            mBtn.addEventListener('click', function () {
              toggleGalleryAsset(node, m.id);
              renderProps();
            });
            multi.appendChild(mBtn);
          });
          if (media.length === 0) {
            var multiHint = document.createElement('p');
            multiHint.className = 'muted';
            multiHint.textContent = UI.noMedia;
            multi.appendChild(multiHint);
          }
          label.appendChild(multi);
          propsPanel.appendChild(label);
          return;
        } else if (field.key === 'src' || field.key === 'srcWebp' || field.key === 'focusX' || field.key === 'focusY') {
          return;
        } else if (def.enum) {
          input = document.createElement('select');
          def.enum.forEach(function (opt) {
            var option = document.createElement('option');
            option.value = String(opt);
            option.textContent = enumLabel(opt);
            if (String(node.props[field.key] ?? '') === String(opt)) {
              option.selected = true;
            }
            input.appendChild(option);
          });
        } else if (def.type === 'integer' || def.type === 'number') {
          input = document.createElement('input');
          input.type = 'number';
          if (def.minimum != null) input.min = def.minimum;
          if (def.maximum != null) input.max = def.maximum;
          input.value = node.props[field.key] != null ? node.props[field.key] : '';
        } else if (isLongTextField(field.key)) {
          input = document.createElement('textarea');
          input.rows = longTextRows(field.key);
          if (field.key === 'text' || field.key === 'answer') {
            input.className = 'nx-props__textarea--long';
          }
          input.value = node.props[field.key] != null ? node.props[field.key] : '';
        } else {
          input = document.createElement('input');
          input.type = 'text';
          input.value = node.props[field.key] != null ? node.props[field.key] : '';
        }
        var eventName = input.tagName === 'SELECT' ? 'change' : 'input';
        input.addEventListener(eventName, function () {
          applyPropValue(node, field.key, def, input.value);
        });
        input.dataset.prop = field.key;
        label.appendChild(input);
        propsPanel.appendChild(label);
      });
    }

    function render() {
      canvas.innerHTML = '';
      if (!state.doc.root) {
        canvas.innerHTML = '<p class="error">' + escapeHtml(UI.noRoot) + '</p>';
        return;
      }
      canvas.setAttribute('role', 'tree');
      canvas.setAttribute('aria-label', UI.canvasLabel);
      canvas.appendChild(renderBlock(state.doc.root));
      renderProps();
      syncInput();
    }

    canvas.onkeydown = function (ev) {
      var blockEl = ev.target && ev.target.closest ? ev.target.closest('.nx-block') : null;
      if (!blockEl || !canvas.contains(blockEl)) {
        return;
      }
      if (ev.target.tagName === 'INPUT' || ev.target.tagName === 'TEXTAREA' || ev.target.tagName === 'SELECT') {
        return;
      }
      var list = listBlocks(state.doc.root);
      var idx = -1;
      for (var i = 0; i < list.length; i++) {
        if (list[i].id === state.selectedId) {
          idx = i;
          break;
        }
      }
      if (ev.altKey && ev.key === 'ArrowUp') {
        ev.preventDefault();
        if (state.selectedId !== state.doc.root.id && moveSibling(state.doc.root, state.selectedId, -1)) {
          syncInput();
          select(state.selectedId, { focus: true });
        }
        return;
      }
      if (ev.altKey && ev.key === 'ArrowDown') {
        ev.preventDefault();
        if (state.selectedId !== state.doc.root.id && moveSibling(state.doc.root, state.selectedId, 1)) {
          syncInput();
          select(state.selectedId, { focus: true });
        }
        return;
      }
      if (ev.key === 'ArrowDown') {
        ev.preventDefault();
        if (idx >= 0 && idx < list.length - 1) {
          select(list[idx + 1].id, { focus: true });
        }
        return;
      }
      if (ev.key === 'ArrowUp') {
        ev.preventDefault();
        if (idx > 0) {
          select(list[idx - 1].id, { focus: true });
        }
        return;
      }
      if ((ev.key === 'Delete' || ev.key === 'Backspace') && state.selectedId !== state.doc.root.id && ev.target.tagName !== 'BUTTON') {
        ev.preventDefault();
        removeNode(state.doc.root, state.selectedId);
        state.selectedId = state.doc.root.id;
        syncInput();
        select(state.selectedId, { focus: true });
      }
    };

    renderPalette();
    if (state.doc.root) {
      if (!state.selectedId) {
        state.selectedId = state.doc.root.id;
      }
      render();
    }
  }

  function bindVisibleMirror(config) {
    var hidden = document.getElementById(config.documentInputId);
    var visible = document.getElementById(config.documentVisibleId || 'nx-document-visible');
    var form = document.getElementById(config.formId || 'nx-builder-form');
    if (!hidden || !visible || !form) {
      return;
    }
    form.addEventListener('submit', function () {
      visible.value = hidden.value;
    });
    visible.addEventListener('change', function () {
      hidden.value = visible.value;
      init(config);
    });
  }

  function bootFromDom() {
    var el = document.getElementById('nx-builder-config');
    if (!el) {
      return;
    }
    var config;
    try {
      config = JSON.parse(el.textContent || '{}');
    } catch (e) {
      return;
    }
    init(config);
    bindVisibleMirror(config);
  }

  window.NexisBuilder = { init: init };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootFromDom);
  } else {
    bootFromDom();
  }
})();
