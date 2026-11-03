// Modern Enhancement 5: <BorrowBox>, borrow, queue and shelf actions on the book page (book.php) without reloading.
//
// Vue concepts used in this file
// - props: bookId and the "Read a sample" link come from the PHP page once, through createApp().
// - emits: 'ready' after the first load (boot.js then hides the Base box), 'failed' if it fails.
// - ref(): the server's data for this book, the chosen collection date, whether the date picker is
//   open, and the last message.
// - computed(): mode works out which single action applies (sign in, see your loan, borrow, borrow
//   a held copy, leave the queue, join the queue, or nothing) from the data, in the same order as the
//   if/elseif chain in book.php. chosen and canBorrow are derived the same way, so after any action
//   the whole box changes by itself from one new copy of the data.
// - v-model: the collection date select. The "due back" line next to it is computed from it.
// - v-if / v-else-if: one branch per mode, like the PHP template.
// - watch(): when the number of open loans changes, the header badge follows.
// - nextTick(): moves keyboard focus to the date picker or the message after Vue has drawn it.
// - Composable: useFetch() (use-fetch.js), one slot for loading the box and one for actions.
// Server rules: every button POSTs to api/book.php with the page's CSRF token, which calls the same
// borrow_book(), join_queue() and leave_queue() as process/loan.php. PHP decides; this file shows
// what PHP answered, word for word. With JavaScript off, book.php and borrow.php work as before.
(function () {
  'use strict';

  // The same icons that includes/views.php prints (aria-hidden, so screen readers skip them).
  function svg(paths) {
    return '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"' +
      ' stroke-linejoin="round" aria-hidden="true" focusable="false">' + paths + '</svg>';
  }
  var ICON = {
    books: svg('<path d="M4 4h4v16H4zM10 4h4v16h-4z"/><path d="m16 5 3.8-1 2.2 15.4-3.8 1z"/>'),
    queue: svg('<circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="7" r="2.5"/><path d="M2.5 19a4.5 4.5 0 0 1 9 0M12.5 19a4.5 4.5 0 0 1 9 0"/>'),
    bookmark: svg('<path d="M6 3h12v18l-6-4-6 4z"/>'),
    check: svg('<path d="m5 12 5 5 9-10"/>'),
    lock: svg('<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'),
    book: svg('<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/><path d="M8 7h7"/>')
  };

  var BorrowBox = {
    name: 'BorrowBox',
    props: {
      bookId: { type: Number, required: true },
      readHref: { type: String, default: '' },
      readText: { type: String, default: '' }
    },
    emits: ['ready', 'failed', 'changed'],

    setup: function (props, ctx) {
      var Vue = window.Vue;
      var api = window.BookNestVue;

      var data = Vue.ref(null);
      var picking = Vue.ref(false);
      var date = Vue.ref('');
      var message = Vue.ref({ type: '', text: '', link: '', linkText: '' });
      var messageEl = Vue.ref(null);
      var dateEl = Vue.ref(null);
      var started = false;
      var list = api.useFetch();
      var action = api.useFetch();

      // Copies the server's answer in and keeps the chosen date valid.
      function apply(d) {
        if (!d || !d.book) { return; }
        data.value = d;
        var values = d.dates.map(function (x) { return x.value; });
        if (values.indexOf(date.value) < 0) { date.value = values[0] || ''; }
      }

      // Shows a message and moves focus to it, so keyboard and screen reader users hear the result.
      function say(type, text, link, linkText) {
        message.value = { type: type, text: text, link: link || '', linkText: linkText || '' };
        Vue.nextTick(function () { if (messageEl.value) { messageEl.value.focus(); } });
      }

      // Loads the box the first time.
      function load() {
        list.run('api/book.php', { query: { id: props.bookId } }).then(function (res) {
          if (!res) { return; }
          if (!res.ok) { throw new Error(res.data.error || 'Could not load the book.'); }
          apply(res.data);
          started = true;
          ctx.emit('ready');
        }).catch(function () { if (!started) { ctx.emit('failed'); } });
      }

      // Sends one action to api/book.php and shows PHP's answer, whatever it is.
      function send(kind, extra) {
        var form = Object.assign({ action: kind, id: props.bookId }, extra || {});
        return action.run('api/book.php', { method: 'POST', form: form }).then(function (res) {
          if (!res) { return; }
          apply(res.data);
          if (res.ok) {
            picking.value = false;
            var d = res.data;
            if (kind === 'borrow' && d.loan) {
              say('success', d.message, d.loan.url, 'See your loan');
            } else {
              say('success', d.message);
            }
            ctx.emit('changed', { action: kind, message: d.message });
          } else {
            say('error', res.data.error || 'That did not work. Please reload the page.',
              res.status === 401 ? res.data.signin : '', res.status === 401 ? 'Sign in' : '');
          }
        }).catch(function () {
          say('error', 'Could not reach the library. Check your connection and try again, or reload the page.');
        });
      }

      // Opens the collection date picker (the same choice as borrow.php) and focuses it.
      function openPicker() {
        message.value = { type: '', text: '', link: '', linkText: '' };
        picking.value = true;
        Vue.nextTick(function () { if (dateEl.value) { dateEl.value.focus(); } });
      }
      // Confirms the loan for the chosen date.
      function confirmBorrow() { send('borrow', { collection_date: date.value }); }
      // Joins the queue, leaves it, or toggles the shelf.
      function joinQueue() { send('queue'); }
      function leaveQueue() { send('leave'); }
      function toggleShelf() { send(data.value.onShelf ? 'unsave' : 'save'); }

      // Which one main action applies, in the same order as the if/elseif chain in book.php.
      var mode = Vue.computed(function () {
        var d = data.value;
        if (!d) { return 'none'; }
        if (!d.signedIn) { return 'visitor'; }
        if (d.loan) { return 'loan'; }
        if (d.queue && d.queue.status === 'offered') { return 'offered'; }
        if (d.queue) { return 'waiting'; }
        if (d.book.available > 0) { return 'borrow'; }
        if (d.book.stock > 0) { return 'queue'; }
        return 'none';
      });
      var canBorrow = Vue.computed(function () {
        return (mode.value === 'borrow' || mode.value === 'offered') && data.value.blocks.length === 0;
      });
      var chosen = Vue.computed(function () {
        var list = data.value ? data.value.dates : [];
        for (var i = 0; i < list.length; i++) { if (list[i].value === date.value) { return list[i]; } }
        return null;
      });
      var copiesLine = Vue.computed(function () {
        var b = data.value.book;
        return b.available + ' of ' + b.stock + ' ' + (b.stock === 1 ? 'copy' : 'copies') + ' on the shelf · ' +
          data.value.rules.loanDays + ' day loans, free for members';
      });
      var openLoans = Vue.computed(function () { return data.value ? data.value.openLoans : 0; });

      // Keeps the header "My loans" badge in step after a borrow.
      Vue.watch(openLoans, function (n) {
        var badge = document.querySelector('.loans-link .badge');
        if (!badge) { return; }
        badge.textContent = n;
        badge.classList.toggle('is-empty', n === 0);
        var spoken = badge.nextElementSibling;
        if (spoken && spoken.classList.contains('visually-hidden')) { spoken.textContent = ' ' + (n === 1 ? 'book' : 'books') + ' on loan'; }
      });

      Vue.onMounted(load);

      return {
        data: data, picking: picking, date: date, message: message, messageEl: messageEl, dateEl: dateEl,
        acting: action.loading, mode: mode, canBorrow: canBorrow, chosen: chosen, copiesLine: copiesLine,
        openPicker: openPicker, confirmBorrow: confirmBorrow, joinQueue: joinQueue, leaveQueue: leaveQueue, toggleShelf: toggleShelf
      };
    },

    template:
      '<div class="vb" v-if="data">' +
      '  <div class="avail-box">' +
      '    <p class="avail-line avail-lg"><span class="avail" :class="data.book.availability.class">{{ data.book.availability.text }}</span>' +
      '      <span v-if="data.book.availability.note" class="avail-note">{{ data.book.availability.note }}</span></p>' +
      '    <p class="small muted">{{ copiesLine }}</p>' +
      '  </div>' +
      '  <p v-if="message.text" ref="messageEl" tabindex="-1" class="notice vb-message" :class="message.type === \'error\' ? \'notice-alert\' : \'notice-ok\'"' +
      '     :role="message.type === \'error\' ? \'alert\' : \'status\'">{{ message.text }} <a v-if="message.link" :href="message.link">{{ message.linkText }}</a></p>' +
      '  <div class="book-actions">' +
      '    <a v-if="mode === \'visitor\'" class="btn btn-primary" :href="data.links.signin">' + ICON.books + ' Sign in to borrow</a>' +
      '    <a v-else-if="mode === \'loan\'" class="btn btn-primary" :href="data.loan.url">' + ICON.books + ' See your loan</a>' +
      '    <button v-else-if="canBorrow && !picking" type="button" class="btn btn-primary" @click="openPicker">' + ICON.books +
      '      {{ mode === \'offered\' ? \'Borrow your held copy\' : \'Borrow\' }}</button>' +
      '    <button v-else-if="mode === \'waiting\'" type="button" class="btn btn-secondary" :disabled="acting" @click="leaveQueue">Leave the queue</button>' +
      '    <button v-else-if="mode === \'queue\'" type="button" class="btn btn-primary" :disabled="acting" @click="joinQueue">' + ICON.queue + ' Join the queue</button>' +
      '    <a v-if="readHref" class="btn btn-ghost" :href="readHref">' + ICON.book + ' {{ readText }}</a>' +
      '    <button v-if="data.signedIn" type="button" class="btn btn-ghost" :aria-pressed="data.onShelf ? \'true\' : \'false\'" :disabled="acting" @click="toggleShelf">' +
      '      <span v-if="data.onShelf">' + ICON.check + '</span><span v-else>' + ICON.bookmark + '</span> {{ data.onShelf ? \'On your shelf\' : \'Save to shelf\' }}</button>' +
      '    <a v-else class="btn btn-ghost" :href="data.links.signin">' + ICON.bookmark + ' Sign in to save</a>' +
      '  </div>' +
      '  <form v-if="picking && canBorrow" class="panel form vb-picker" @submit.prevent="confirmBorrow" aria-labelledby="vb-picker-title">' +
      '    <h2 id="vb-picker-title" class="panel-title">{{ mode === \'offered\' ? \'Your copy is waiting\' : \'When will you collect it?\' }}</h2>' +
      '    <p v-if="mode === \'offered\'" class="notice">' + ICON.check + ' We are holding a copy for you until {{ data.queue.holdUntilLong }}.</p>' +
      '    <div class="field">' +
      '      <label for="vb-date">Collection date</label>' +
      '      <select id="vb-date" ref="dateEl" v-model="date" aria-describedby="vb-due">' +
      '        <option v-for="d in data.dates" :key="d.value" :value="d.value">{{ d.label }}</option>' +
      '      </select>' +
      '      <p class="hint" id="vb-due" aria-live="polite" v-if="chosen">Due back on <strong>{{ chosen.dueLong }}</strong>.' +
      '        Collect it at the front desk between {{ data.rules.hours }}.</p>' +
      '    </div>' +
      '    <div class="cluster">' +
      '      <button type="submit" class="btn btn-primary" :disabled="acting || !chosen">{{ acting ? \'Borrowing...\' : \'Confirm loan\' }}</button>' +
      '      <button type="button" class="btn btn-ghost" @click="picking = false">Not now</button>' +
      '    </div>' +
      '  </form>' +
      '  <div v-if="data.blocks.length" class="notice notice-alert" role="status">' + ICON.lock +
      '    <p><strong>New loans are paused.</strong> {{ data.blocks.join(\' \') }} <a :href="data.links.loans">See your loans</a></p></div>' +
      '  <p v-else-if="mode === \'waiting\'" class="notice">' + ICON.queue + ' We will email you as soon as a copy is yours, then hold it for' +
      '    {{ data.rules.holdDays }} days. Meanwhile you can read the {{ data.book.sampleWord }}.</p>' +
      '  <p v-else-if="mode === \'queue\'" class="notice">Every copy is out. Join the queue and we will email you when one comes back.' +
      '    You can still read the {{ data.book.sampleWord }} now.</p>' +
      '  <p v-else-if="data.book.stock <= 0" class="notice">We have no copies of this book yet. You can still read the {{ data.book.sampleWord }},' +
      '    and save it to your shelf for later.</p>' +
      '</div>'
  };

  window.BookNestVue && (window.BookNestVue.components.BorrowBox = BorrowBox);

  // Mounts <BorrowBox> in place of the Base availability box, action buttons and the notice under them.
  window.BookNestVue && window.BookNestVue.ready(function (Vue) {
    var box = document.querySelector('.book-info .avail-box');
    var actions = document.querySelector('.book-info .book-actions');
    var bookId = parseInt(new URLSearchParams(window.location.search).get('id') || '0', 10);
    if (!box || !actions || !bookId) { return; }
    var base = [box, actions];
    var next = actions.nextElementSibling;
    if (next && next.classList.contains('notice')) { base.push(next); }
    var read = actions.querySelector('a[href*="read.php"]');
    window.BookNestVue.enhance(Vue, BorrowBox, {
      bookId: bookId,
      readHref: read ? read.getAttribute('href') : '',
      readText: read ? read.textContent.trim() : ''
    }, base, box);
  });
})();
