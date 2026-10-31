// Modern Enhancement loader, shared by every Vue page (Browse, My Account, Study Rooms).
//
// What it does
// - Loads Vue 3 from the LOCAL file assets/vendor/vue.global.prod.js (no CDN, works offline) and the
//   enhancement stylesheet assets/css/vue.css, once per page.
// - Gives each page script BookNestVue.ready(fn): fn(Vue) runs only after Vue has loaded. If Vue
//   cannot load, fn never runs and the page stays the plain PHP/HTML Base Version.
// - BookNestVue.enhance() swaps a Base section for a Vue app only after the app reports it has its
//   data ('ready'). If the app fails first, it is removed and the Base section was never hidden.
// - BookNestVue.requestJson() is the one place that talks to the api/ endpoints.
// No Vue concepts are used in this file; it is plain JavaScript that prepares the page for Vue.
(function () {
  'use strict';

  var me = document.currentScript;
  if (!me || !window.fetch || !window.URL || !window.Promise) {
    return;   // very old browser: keep the Base Version
  }
  var assets = new URL('../../', me.src);   // .../booknest/assets/
  var site = new URL('../', assets);         // .../booknest/
  var waiting = [];
  var state = 'loading';

  // Runs fn(Vue) once Vue is available; never runs it if Vue failed to load.
  function ready(fn) {
    if (state === 'loaded') {
      fn(window.Vue);
    } else if (state === 'loading') {
      waiting.push(fn);
    }
  }

  // Builds an absolute path inside the site, for example url('api/books.php').
  function url(path) {
    return new URL(path, site).pathname;
  }

  // Returns the page's CSRF token from any form that PHP printed, or ''.
  function csrfToken() {
    var field = document.querySelector('input[name="csrf"]');
    return field ? field.value : '';
  }

  // Calls an api/ endpoint. Resolves to { ok, status, data } when the server answered with JSON
  // (including error answers such as 401, 403 or 422); rejects on network errors or non-JSON replies.
  function requestJson(path, options) {
    options = options || {};
    var init = { method: options.method || 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (options.signal) { init.signal = options.signal; }
    var target = new URL(url(path), window.location.href);
    if (options.query) {
      Object.keys(options.query).forEach(function (k) {
        if (options.query[k] !== '' && options.query[k] !== null && options.query[k] !== undefined) {
          target.searchParams.set(k, options.query[k]);
        }
      });
    }
    if (options.form) {
      var body = new URLSearchParams();
      Object.keys(options.form).forEach(function (k) { body.set(k, options.form[k]); });
      body.set('csrf', csrfToken());
      init.body = body;
    }
    return fetch(target.href, init).then(function (r) {
      var type = r.headers.get('Content-Type') || '';
      if (type.indexOf('application/json') !== 0) {
        throw new Error('The server did not answer with JSON (HTTP ' + r.status + ').');
      }
      return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
    });
  }

  // Mounts a Vue app in place of some Base elements. The app emits 'ready' after its first
  // successful load (then the Base elements are hidden) or 'failed' (then the app is removed).
  // If the app breaks later, the Base elements come back exactly as PHP printed them.
  function enhance(Vue, component, props, baseElements, insertBefore) {
    var host = document.createElement('div');
    host.className = 'vue-host';
    host.hidden = true;
    var anchor = insertBefore || baseElements[0];
    anchor.parentNode.insertBefore(host, anchor);
    var wasHidden = baseElements.map(function (el) { return el.hidden; });
    var app;
    var phase = 'starting';   // starting -> live -> gone

    // Takes the Vue app away and shows the Base elements again.
    function restore() {
      if (phase === 'gone') { return; }
      phase = 'gone';
      baseElements.forEach(function (el, i) { el.hidden = wasHidden[i]; el.removeAttribute('data-vue-replaced'); });
      setTimeout(function () { try { app.unmount(); } catch (e) { /* already stopped */ } host.remove(); }, 0);
    }

    var handlers = {
      onReady: function () {
        if (phase !== 'starting') { return; }
        phase = 'live';
        baseElements.forEach(function (el) { el.setAttribute('data-vue-replaced', ''); el.hidden = true; });
        host.hidden = false;
        document.documentElement.classList.add('vue-on');
      },
      onFailed: restore
    };
    app = Vue.createApp(component, Object.assign({}, props, handlers));
    app.config.errorHandler = function (err) {
      if (window.console) { console.warn('[BookNest] Vue enhancement stopped, the page keeps working without it:', err); }
      restore();
    };
    app.mount(host);
    return app;
  }

  window.BookNestVue = { ready: ready, url: url, csrfToken: csrfToken, requestJson: requestJson, enhance: enhance, components: {} };

  var css = document.createElement('link');
  css.rel = 'stylesheet';
  css.href = new URL('css/vue.css', assets).href;
  document.head.appendChild(css);

  // Runs the queued page scripts once Vue has loaded.
  function loaded() {
    state = window.Vue && window.Vue.createApp ? 'loaded' : 'failed';
    var list = waiting;
    waiting = [];
    if (state === 'loaded') {
      list.forEach(function (fn) { fn(window.Vue); });
    }
  }

  if (window.Vue) {
    loaded();
  } else {
    var s = document.createElement('script');
    s.src = new URL('vendor/vue.global.prod.js', assets).href;
    s.onload = loaded;
    s.onerror = function () { state = 'failed'; waiting = []; };
    document.head.appendChild(s);
  }
})();
