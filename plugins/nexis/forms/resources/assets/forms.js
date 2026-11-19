(function () {
  function parseRule(raw) {
    if (!raw) {
      return null;
    }
    try {
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object' || !data.field) {
        return null;
      }
      return {
        field: String(data.field),
        op: String(data.op || 'eq'),
        value: data.value != null ? String(data.value) : ''
      };
    } catch (e) {
      return null;
    }
  }

  function fieldValue(form, key) {
    var el = form.querySelector('[name="' + CSS.escape(key) + '"]');
    if (!el) {
      return '';
    }
    if (el.type === 'checkbox') {
      return el.checked ? '1' : '';
    }
    return String(el.value || '');
  }

  function isVisible(rule, form) {
    if (!rule) {
      return true;
    }
    var actual = fieldValue(form, rule.field);
    if (rule.op === 'empty') {
      return actual.trim() === '';
    }
    if (rule.op === 'not_empty') {
      return actual.trim() !== '';
    }
    if (rule.op === 'neq') {
      return actual !== rule.value;
    }
    return actual === rule.value;
  }

  function apply(form) {
    var fields = form.querySelectorAll('[data-nx-form-field]');
    for (var i = 0; i < fields.length; i++) {
      var wrap = fields[i];
      var rule = parseRule(wrap.getAttribute('data-visible-when'));
      var show = isVisible(rule, form);
      wrap.hidden = !show;
      var inputs = wrap.querySelectorAll('input, select, textarea');
      for (var j = 0; j < inputs.length; j++) {
        inputs[j].disabled = !show;
        if (!show) {
          inputs[j].removeAttribute('required');
        } else if (wrap.getAttribute('data-required') === '1') {
          inputs[j].setAttribute('required', 'required');
        }
      }
    }
  }

  function bind(form) {
    form.addEventListener('input', function () { apply(form); });
    form.addEventListener('change', function () { apply(form); });
    apply(form);
  }

  function boot() {
    var forms = document.querySelectorAll('form[data-nx-form]');
    for (var i = 0; i < forms.length; i++) {
      bind(forms[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
