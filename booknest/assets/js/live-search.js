// Modern Enhancement: live search suggestions on the catalogue, built with Vue 3.
// Vue is loaded from the local file assets/vendor/vue.global.prod.js, so it works offline.
// The suggestions come as JSON from process/search-suggest.php (GET, read only).
// Without JavaScript, or if anything here fails, the header search form still submits to
// catalogue.php exactly as before; this file only adds a dropdown on top of it.
(function () {
  'use strict';

  var input = document.getElementById('site-q');
  var form = input && input.form;
  var me = document.currentScript;
  if (!input || !form || !me || !window.fetch || !window.URL) {
    return;
  }

  var assets = new URL('../', me.src);                               // .../booknest/assets/
  var endpoint = new URL('process/search-suggest.php', form.action);   // next to catalogue.php
  var MIN_CHARS = 2;
  var DELAY_MS = 180;

  // Adds a stylesheet to the page once.
  function loadCss(href) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }

  // Loads a script file and calls done once it has run.
  function loadScript(src, done) {
    var s = document.createElement('script');
    s.src = src;
    s.onload = done;
    document.head.appendChild(s);
  }

  // Splits a text into parts, marking the first place the query appears, for highlighting.
  function highlight(text, query) {
    var at = text.toLowerCase().indexOf(query.toLowerCase());
    if (!query || at < 0) {
      return [{ t: text, hit: false }];
    }
    return [
      { t: text.slice(0, at), hit: false },
      { t: text.slice(at, at + query.length), hit: true },
      { t: text.slice(at + query.length), hit: false }
    ];
  }

  // Template: every value is shown with {{ }} text interpolation, which Vue escapes. No v-html.
  var template =
    '<div v-if="open" class="suggest">' +
    '  <ul v-if="results.length" class="suggest-list" role="listbox" :id="listId" aria-label="Search suggestions">' +
    '    <li v-for="(b, i) in results" :key="b.id" :id="listId + \'-\' + i" role="option" class="suggest-item"' +
    '        :class="{ \'is-active\': i === active }" :aria-selected="i === active ? \'true\' : \'false\'" @mouseenter="active = i">' +
    '      <a :href="b.url" tabindex="-1" @mousedown.prevent>' +
    '        <img :src="b.cover" alt="" width="32" height="48" loading="lazy" decoding="async">' +
    '        <span class="suggest-text">' +
    '          <span class="suggest-title"><template v-for="(p, k) in parts(b.title)" :key="k"><mark v-if="p.hit">{{ p.t }}</mark><template v-else>{{ p.t }}</template></template></span>' +
    '          <span class="suggest-author">{{ b.author }} · {{ b.serial }}</span>' +
    '        </span>' +
    '        <span class="avail" :class="\'is-\' + b.status">{{ b.availability }}</span>' +
    '      </a>' +
    '    </li>' +
    '  </ul>' +
    '  <p v-else class="suggest-empty" :id="listId">No quick matches for "{{ query }}". Press Enter to search the whole catalogue.</p>' +
    '  <a class="suggest-foot" :href="allUrl" tabindex="-1" @mousedown.prevent>See all results for "{{ query }}"</a>' +
    '</div>' +
    '<p class="visually-hidden" aria-live="polite">{{ status }}</p>';

  // Builds the Vue app and connects it to the existing header search box.
  function start() {
    var Vue = window.Vue;
    if (!Vue || !Vue.createApp) {
      return;
    }
    var mount = document.createElement('div');
    mount.style.display = 'contents';   // no box of its own, so the search form's flex layout is unchanged
    form.appendChild(mount);

    Vue.createApp({
      template: template,
      setup: function () {
        var query = Vue.ref('');
        var results = Vue.ref([]);
        var active = Vue.ref(-1);
        var open = Vue.ref(false);
        var status = Vue.ref('');
        var listId = 'site-q-suggest';
        var cache = {};
        var timer = 0;
        var pending = null;

        var allUrl = Vue.computed(function () {
          return form.action + '?q=' + encodeURIComponent(query.value);
        });

        // Shows a set of results for a query and announces how many there are.
        function show(q, list) {
          results.value = list;
          active.value = -1;
          open.value = document.activeElement === input;
          status.value = list.length
            ? list.length + (list.length === 1 ? ' suggestion' : ' suggestions') + '. Use the up and down arrows to choose.'
            : 'No quick matches. Press Enter to search the whole catalogue.';
        }

        // Asks the server for suggestions, cancelling any request still on its way.
        function fetchSuggestions(q) {
          if (cache[q]) {
            show(q, cache[q]);
            return;
          }
          if (pending) {
            pending.abort();
          }
          pending = window.AbortController ? new AbortController() : null;
          var url = new URL(endpoint);
          url.searchParams.set('q', q);
          fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pending ? pending.signal : undefined })
            .then(function (r) {
              var type = r.headers.get('Content-Type') || '';
              if (!r.ok || type.indexOf('application/json') !== 0) {
                throw new Error('Bad response');
              }
              return r.json();
            })
            .then(function (data) {
              var list = Array.isArray(data.results) ? data.results : [];
              cache[q] = list;
              if (input.value.trim() === q) {
                show(q, list);
              }
            })
            .catch(function (err) {
              if (!err || err.name !== 'AbortError') {
                open.value = false;   // the normal search form still works
              }
            });
        }

        // Reacts to typing: waits for a short pause, then fetches suggestions.
        function onInput() {
          var q = input.value.trim();
          query.value = q;
          clearTimeout(timer);
          if (q.length < MIN_CHARS) {
            if (pending) {
              pending.abort();
            }
            results.value = [];
            open.value = false;
            status.value = '';
            return;
          }
          timer = setTimeout(function () { fetchSuggestions(q); }, DELAY_MS);
        }

        // Moves the highlight with the arrow keys, opens a book with Enter, closes with Escape.
        function onKey(e) {
          var n = results.value.length;
          if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (!n) {
              return;
            }
            e.preventDefault();
            open.value = true;
            active.value = e.key === 'ArrowDown'
              ? (active.value + 1) % n
              : (active.value <= 0 ? n - 1 : active.value - 1);
          } else if (e.key === 'Enter' && open.value && active.value >= 0) {
            e.preventDefault();
            window.location.href = results.value[active.value].url;
          } else if (e.key === 'Escape' && open.value) {
            e.preventDefault();
            open.value = false;
          } else if (e.key === 'Tab') {
            open.value = false;
          }
        }

        // Returns the title split for highlighting the typed text.
        function parts(text) {
          return highlight(text, query.value);
        }

        // Keeps the combobox attributes on the input in step with the dropdown.
        Vue.watch([open, active], function () {
          input.setAttribute('aria-expanded', open.value ? 'true' : 'false');
          if (open.value && active.value >= 0) {
            input.setAttribute('aria-activedescendant', listId + '-' + active.value);
          } else {
            input.removeAttribute('aria-activedescendant');
          }
        });

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', listId);
        input.setAttribute('aria-expanded', 'false');
        input.addEventListener('input', onInput);
        input.addEventListener('keydown', onKey);
        input.addEventListener('focus', function () {
          if (query.value.length >= MIN_CHARS && input.value.trim() === query.value) {
            open.value = true;
          }
        });
        input.addEventListener('blur', function () {
          setTimeout(function () { open.value = false; }, 120);
        });

        return { query: query, results: results, active: active, open: open, status: status, listId: listId, allUrl: allUrl, parts: parts };
      }
    }).mount(mount);
  }

  loadCss(new URL('css/live-search.css', assets).href);
  if (window.Vue) {
    start();
  } else {
    loadScript(new URL('vendor/vue.global.prod.js', assets).href, start);
  }
})();
