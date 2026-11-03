// Modern Enhancement 6: live serial number lookup on Add a book (add-book.php).
//   <SerialLookup>  the "Serial number lookup" panel: looks the number up as you type, no Look up button
//                   and no reload; when the number is free it offers to use it in the form below.
//   <SerialStatus>  a line under the form's Serial number field: free, or already in the library.
//
// Vue concepts used in this file
// - Composable: useSerialCheck(source) holds the shared logic of both components: it watches a ref,
//   waits 300 ms after the last change (debounce), checks the format, asks api/serial.php through
//   useFetch() (use-fetch.js) and gives back a computed state. Each component calls it once and gets
//   its own independent copy, the same way <BookSearch> and <LoansPanel> each call useFetch().
// - watch() with a debounce, and computed() for the state ('typing', 'bad', 'checking', 'free', 'taken').
// - v-model on the lookup box; a watch() keeps it in capitals, like forms.js does for the Base box.
// - emits: <SerialLookup> emits 'use' with the free number; the page script copies it into the Base
//   form field, so the form that is posted is still the PHP form.
// - Lifecycle hooks: <SerialStatus> listens to the Base input with addEventListener in onMounted() and
//   removes the listener in onBeforeUnmount(), because that input belongs to the PHP page, not to Vue.
// Server rules: this only looks numbers up. process/add-book.php still checks the format and that the
// number is unused before it saves a book, so a number taken in the meantime is still refused there.
(function () {
  'use strict';

  var FORMAT = /^BNT-\d{6}$/;
  var FORMAT_MSG = 'Serial numbers look like BNT-000123: the letters BNT, a hyphen and six digits.';
  // True while the text could still become a valid serial (B, BN, BNT-, BNT-00...).
  function couldBecome(text) {
    return 'BNT-'.indexOf(text.slice(0, 4)) === 0 && /^\d{0,6}$/.test(text.slice(4));
  }

  // The composable: checks a serial number ref against the format and the server, 300 ms after it settles.
  function useSerialCheck(source) {
    var Vue = window.Vue;
    var request = window.BookNestVue.useFetch();
    var settled = Vue.ref('');
    var reply = Vue.ref(null);
    var timer = 0;

    // Looks the settled number up (or not, if the format is wrong).
    function check() {
      window.clearTimeout(timer);
      var text = String(source.value || '').trim().toUpperCase();
      settled.value = text;
      reply.value = null;
      if (!FORMAT.test(text)) { request.cancel(); request.loading.value = false; return; }
      request.run('api/serial.php', { query: { serial: text } }).then(function (res) {
        if (res && settled.value === text) { reply.value = res; }
      }).catch(function () { /* useFetch() already set the error message */ });
    }

    Vue.watch(source, function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(check, 300);
    });
    Vue.onBeforeUnmount(function () { window.clearTimeout(timer); });

    var state = Vue.computed(function () {
      var text = settled.value;
      if (!text) { return 'empty'; }
      if (!FORMAT.test(text)) { return text.length < 10 && couldBecome(text) ? 'typing' : 'bad'; }
      if (request.loading.value) { return 'checking'; }
      if (request.error.value && !reply.value) { return 'error'; }
      if (!reply.value) { return 'checking'; }
      if (!reply.value.ok) { return 'error'; }
      return reply.value.data.free ? 'free' : 'taken';
    });
    var data = Vue.computed(function () { return reply.value ? reply.value.data : null; });
    var problem = Vue.computed(function () {
      if (state.value === 'bad') { return FORMAT_MSG; }
      if (reply.value && !reply.value.ok) { return reply.value.data.error || 'The lookup was refused.'; }
      return request.error.value;
    });
    var signin = Vue.computed(function () { return reply.value && reply.value.status === 401 ? reply.value.data.signin : ''; });

    return { state: state, data: data, problem: problem, signin: signin, settled: settled, checkNow: check };
  }

  var SerialLookup = {
    name: 'SerialLookup',
    props: { initialSerial: { type: String, default: '' } },
    emits: ['ready', 'failed', 'use'],

    setup: function (props, ctx) {
      var Vue = window.Vue;
      var serial = Vue.ref(props.initialSerial);
      var lookup = useSerialCheck(serial);

      // Keeps the box in capitals, like the Base box (forms.js setupUppercase).
      Vue.watch(serial, function (v) { if (v !== v.toUpperCase()) { serial.value = v.toUpperCase(); } });

      // Keeps ?serial= in the address in step, so a reload shows the same lookup (as the Base GET form does).
      Vue.watch(lookup.state, function (s) {
        if (s !== 'free' && s !== 'taken') { return; }
        var url = new URL(window.location.href);
        url.searchParams.set('serial', lookup.settled.value);
        window.history.replaceState(null, '', url.pathname + url.search + url.hash);
      });

      // Hands the free number to the page, which puts it into the form below.
      function use() { ctx.emit('use', lookup.settled.value); }

      Vue.onMounted(function () {
        if (serial.value) { lookup.checkNow(); }
        ctx.emit('ready');
      });

      return { serial: serial, lookup: lookup, use: use };
    },

    template:
      '<div class="vsl">' +
      '  <div class="lookup-form">' +
      '    <div class="field">' +
      '      <label for="vsl-serial">Serial number</label>' +
      '      <input type="text" id="vsl-serial" v-model.trim="serial" maxlength="10" placeholder="BNT-000123" autocomplete="off" spellcheck="false"' +
      '             aria-describedby="vsl-msg" @keydown.enter.prevent="lookup.checkNow()">' +
      '    </div>' +
      '  </div>' +
      '  <div id="vsl-msg" aria-live="polite">' +
      '    <p v-if="lookup.state.value === \'typing\'" class="hint vsl-hint">Keep typing: BNT, a hyphen and six digits. It looks the number up by itself.</p>' +
      '    <p v-else-if="lookup.state.value === \'checking\'" class="hint vsl-hint">Looking up {{ lookup.settled.value }}...</p>' +
      '    <p v-else-if="lookup.state.value === \'bad\' || lookup.state.value === \'error\'" class="notice" role="status">{{ lookup.problem.value }}' +
      '      <a v-if="lookup.signin.value" :href="lookup.signin.value">Sign in</a></p>' +
      '    <div v-else-if="lookup.state.value === \'free\'" class="notice notice-ok vsl-free" role="status">' +
      '      <span>{{ lookup.data.value.message }}</span>' +
      '      <button type="button" class="btn btn-secondary btn-sm" @click="use">Use {{ lookup.settled.value }} for the new book</button>' +
      '    </div>' +
      '    <div v-else-if="lookup.state.value === \'taken\'" class="serial-panel" role="status">' +
      '      <img class="serial-cover" :src="lookup.data.value.book.cover" :alt="lookup.data.value.book.coverAlt" width="120" height="180" decoding="async">' +
      '      <div>' +
      '        <h3>{{ lookup.data.value.book.title }}</h3>' +
      '        <p class="muted">{{ lookup.data.value.book.author }} · {{ lookup.data.value.book.category }}</p>' +
      '        <dl class="spec-list">' +
      '          <div><dt>Serial</dt><dd>{{ lookup.data.value.serial }}</dd></div>' +
      '          <div><dt>ISBN</dt><dd>{{ lookup.data.value.book.isbn }}</dd></div>' +
      '          <div><dt>Publisher</dt><dd>{{ lookup.data.value.book.publisher }}</dd></div>' +
      '          <div><dt>Status</dt><dd><span class="pill" :class="\'is-\' + lookup.data.value.book.status">{{ lookup.data.value.book.statusLabel }}</span></dd></div>' +
      '          <div><dt>Copies</dt><dd>{{ lookup.data.value.book.copies }}</dd></div>' +
      '          <div><dt>Published</dt><dd>{{ lookup.data.value.book.published }}</dd></div>' +
      '          <div><dt>Pages</dt><dd>{{ lookup.data.value.book.pages }}</dd></div>' +
      '          <div><dt>Added by</dt><dd>{{ lookup.data.value.book.addedBy }}</dd></div>' +
      '          <div><dt>Date added</dt><dd>{{ lookup.data.value.book.added }}</dd></div>' +
      '        </dl>' +
      '        <a v-if="lookup.data.value.book.url" class="btn btn-secondary btn-sm" :href="lookup.data.value.book.url">Open book page</a>' +
      '      </div>' +
      '    </div>' +
      '  </div>' +
      '</div>'
  };

  var SerialStatus = {
    name: 'SerialStatus',
    props: { inputId: { type: String, required: true } },

    setup: function (props) {
      var Vue = window.Vue;
      var value = Vue.ref('');
      var check = useSerialCheck(value);
      var input = null;

      // Copies the Base input's value into Vue's ref.
      function onInput() { value.value = input.value; }

      Vue.onMounted(function () {
        input = document.getElementById(props.inputId);
        if (!input) { return; }
        input.addEventListener('input', onInput);
        value.value = input.value;
        check.checkNow();
      });
      Vue.onBeforeUnmount(function () { if (input) { input.removeEventListener('input', onInput); } });

      return { check: check };
    },

    template:
      '<p class="hint vss" id="ab-serial-status" aria-live="polite" :class="\'is-\' + check.state.value">' +
      '  <template v-if="check.state.value === \'free\'"><span aria-hidden="true">✓</span> {{ check.settled.value }} is free to use.</template>' +
      '  <template v-else-if="check.state.value === \'taken\'"><span aria-hidden="true">✗</span> {{ check.settled.value }} is already in the library' +
      '    ({{ check.data.value.book.title }}). Choose another number, for example {{ check.data.value.suggested }}.</template>' +
      '  <template v-else-if="check.state.value === \'checking\'">Checking {{ check.settled.value }}...</template>' +
      '</p>'
  };

  if (!window.BookNestVue) { return; }
  window.BookNestVue.useSerialCheck = useSerialCheck;
  window.BookNestVue.components.SerialLookup = SerialLookup;
  window.BookNestVue.components.SerialStatus = SerialStatus;

  window.BookNestVue.ready(function (Vue) {
    // 1. The lookup panel: replaces the Base GET form and its result, keeps the panel heading.
    var form = document.querySelector('.lookup .lookup-form');
    if (form) {
      var base = [form];
      var result = form.nextElementSibling;
      if (result) { base.push(result); }
      var initial = (new URLSearchParams(window.location.search).get('serial') || '').toUpperCase().slice(0, 10);
      window.BookNestVue.enhance(Vue, SerialLookup, {
        initialSerial: initial,
        // Puts the free number into the Base form field, tells forms.js about it, and moves on to the title.
        onUse: function (serial) {
          var field = document.getElementById('ab-serial');
          if (!field) { return; }
          field.value = serial;
          field.dispatchEvent(new Event('input', { bubbles: true }));
          var title = document.getElementById('ab-title');
          if (title) { title.scrollIntoView({ block: 'center' }); title.focus({ preventScroll: true }); }
        }
      }, base, form);
    }

    // 2. The status line under the form's Serial number field. Nothing is hidden, so it is mounted directly.
    var hint = document.getElementById('ab-serial-hint');
    var field = document.getElementById('ab-serial');
    if (hint && field) {
      var host = document.createElement('div');
      hint.insertAdjacentElement('afterend', host);
      var app = Vue.createApp(SerialStatus, { inputId: 'ab-serial' });
      app.config.errorHandler = function (err) {
        if (window.console) { console.warn('[BookNest] serial status stopped, the form keeps working without it:', err); }
        setTimeout(function () { try { app.unmount(); } catch (e) { /* already stopped */ } host.remove(); }, 0);
      };
      app.mount(host);
      field.setAttribute('aria-describedby', ((field.getAttribute('aria-describedby') || '') + ' ab-serial-status').trim());
    }
  });
})();
