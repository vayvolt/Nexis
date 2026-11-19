(function () {
  'use strict';

  function activate(map) {
    if (!map || map.getAttribute('data-nx-map-loaded') === '1') {
      return;
    }
    var src = map.getAttribute('data-src');
    if (!src) {
      return;
    }
    var title = map.getAttribute('data-title') || 'Map';
    var iframe = document.createElement('iframe');
    iframe.setAttribute('title', title);
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
    iframe.src = src;
    map.innerHTML = '';
    map.appendChild(iframe);
    map.setAttribute('data-nx-map-loaded', '1');
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) {
      return;
    }
    var button = target.closest('[data-nx-map-load]');
    if (!button) {
      return;
    }
    event.preventDefault();
    var map = button.closest('.nx-location__map');
    activate(map);
  });
})();
