(function () {
  'use strict';

  function b64urlToBuffer(value) {
    var s = String(value).replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) {
      s += '=';
    }
    var bin = atob(s);
    var bytes = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) {
      bytes[i] = bin.charCodeAt(i);
    }
    return bytes.buffer;
  }

  function bufferToB64url(buffer) {
    var bytes = new Uint8Array(buffer);
    var bin = '';
    for (var i = 0; i < bytes.length; i++) {
      bin += String.fromCharCode(bytes[i]);
    }
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function bufferToB64(buffer) {
    var bytes = new Uint8Array(buffer);
    var bin = '';
    for (var i = 0; i < bytes.length; i++) {
      bin += String.fromCharCode(bytes[i]);
    }
    return btoa(bin);
  }

  function csrfToken() {
    var el = document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function publicKeyFromJson(publicKey) {
    publicKey.challenge = b64urlToBuffer(publicKey.challenge);
    if (publicKey.user && publicKey.user.id) {
      publicKey.user.id = b64urlToBuffer(publicKey.user.id);
    }
    if (Array.isArray(publicKey.excludeCredentials)) {
      publicKey.excludeCredentials = publicKey.excludeCredentials.map(function (c) {
        c.id = b64urlToBuffer(c.id);
        return c;
      });
    }
    if (Array.isArray(publicKey.allowCredentials)) {
      publicKey.allowCredentials = publicKey.allowCredentials.map(function (c) {
        c.id = b64urlToBuffer(c.id);
        return c;
      });
    }
    return publicKey;
  }

  async function registerPasskey(basePath, optionsPath, registerPath) {
    if (!window.PublicKeyCredential) {
      throw new Error('unsupported');
    }
    var optionsUrl = optionsPath || (basePath + '/account/passkeys/options');
    var registerUrl = registerPath || (basePath + '/account/passkeys/register');
    var optionsRes = await fetch(optionsUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-Token': csrfToken(),
        Accept: 'application/json',
      },
    });
    if (!optionsRes.ok) {
      throw new Error('options');
    }
    var options = await optionsRes.json();
    var cred = await navigator.credentials.create({
      publicKey: publicKeyFromJson(options.publicKey || options),
    });
    if (!cred) {
      throw new Error('cancelled');
    }
    var body = new URLSearchParams();
    body.set('_csrf', csrfToken());
    body.set('clientDataJSON', bufferToB64(cred.response.clientDataJSON));
    body.set('attestationObject', bufferToB64(cred.response.attestationObject));
    body.set('name', 'Passkey');
    var saveRes = await fetch(registerUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken(),
        Accept: 'application/json',
      },
      body: body.toString(),
    });
    if (!saveRes.ok) {
      throw new Error('register');
    }
    location.reload();
  }

  async function assertPasskey(basePath) {
    if (!window.PublicKeyCredential) {
      throw new Error('unsupported');
    }
    var optionsRes = await fetch(basePath + '/admin/login/passkey/options', {
      method: 'POST',
      headers: {
        'X-CSRF-Token': csrfToken(),
        Accept: 'application/json',
      },
    });
    if (!optionsRes.ok) {
      throw new Error('options');
    }
    var options = await optionsRes.json();
    var assertion = await navigator.credentials.get({
      publicKey: publicKeyFromJson(options.publicKey || options),
    });
    if (!assertion) {
      throw new Error('cancelled');
    }
    var body = new URLSearchParams();
    body.set('_csrf', csrfToken());
    body.set('id', bufferToB64(assertion.rawId));
    body.set('clientDataJSON', bufferToB64(assertion.response.clientDataJSON));
    body.set('authenticatorData', bufferToB64(assertion.response.authenticatorData));
    body.set('signature', bufferToB64(assertion.response.signature));
    var verifyRes = await fetch(basePath + '/admin/login/passkey/verify', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken(),
        Accept: 'application/json',
      },
      body: body.toString(),
    });
    var payload = await verifyRes.json();
    if (!verifyRes.ok || !payload.redirect) {
      throw new Error('verify');
    }
    location.href = payload.redirect;
  }

  document.querySelectorAll('[data-passkey-register]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var basePath = btn.getAttribute('data-base-path') || '';
      var optionsPath = btn.getAttribute('data-options-url') || '';
      var registerPath = btn.getAttribute('data-register-url') || '';
      registerPasskey(basePath, optionsPath, registerPath).catch(function () {
        alert(btn.getAttribute('data-error') || 'Passkey failed');
      });
    });
  });

  document.querySelectorAll('[data-passkey-assert]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var basePath = btn.getAttribute('data-base-path') || '';
      assertPasskey(basePath).catch(function () {
        alert(btn.getAttribute('data-error') || 'Passkey failed');
      });
    });
  });
})();
