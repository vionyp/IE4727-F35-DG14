// Modern Enhancement 2: <LoansPanel>, the reactive "Your loans" panel on My Account (account.php).
//
// Vue concepts used in this file
// - props: highlightId (the loan named in ?loan= after borrowing) is passed in once by createApp().
// - emits: 'ready' after the first load (boot.js then hides the Base list), 'failed' if it fails.
// - ref(): the loans, the server messages, which loan is busy and which return is being confirmed.
// - computed(): openCount ("4 of 6 loans"), totalFee (unpaid fees on loans still out plus books
//   already back) and dueSoon (collected, due within DUE_SOON_DAYS) are worked out from the list,
//   so they change by themselves after a renew or a return, without reloading the page.
// - watch(): when the totals change, the header badge and the two tiles above stay in step.
// - <transition-group>: when a book is returned its row fades out ('loan-leave-*' classes in vue.css)
//   instead of vanishing; v-for :key="l.id" lets Vue keep each row and animate only the one leaving.
// - nextTick(): waits for Vue to update the page before moving keyboard focus.
// Server rules: Renew and Return POST to api/loans.php with the page's CSRF token. That endpoint calls
// the same renew_loan()/return_loan() as process/loan.php, so PHP still decides everything. When PHP
// says no (already renewed, overdue, expired session, signed out) its message is shown as it was sent.
(function () {
  'use strict';

  // Formats an amount like PHP's money(): S$1.50.
  function money(n) { return 'S$' + Number(n || 0).toFixed(2); }
  // Returns "1 day" or "3 days", like days_text().
  function daysText(n) { return n + (n === 1 ? ' day' : ' days'); }
  // Returns "due today", "due tomorrow" or "due in 2 days", like due_soon_text().
  function dueSoonText(days) { return days === 0 ? 'due today' : (days === 1 ? 'due tomorrow' : 'due in ' + days + ' days'); }

  // The same lock and clock icons that includes/views.php prints next to these notices.
  var ICON_LOCK = '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
  var ICON_CLOCK = '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';

  var LoansPanel = {
    name: 'LoansPanel',
    props: { highlightId: { type: Number, default: 0 } },
    emits: ['ready', 'failed'],

    setup: function (props, ctx) {
      var Vue = window.Vue;
      var api = window.BookNestVue;

      var loans = Vue.ref([]);
      var feesFromReturns = Vue.ref(0);
      var blocks = Vue.ref([]);
      var rules = Vue.ref({ maxLoans: 6, maxRenewals: 1, dueSoonDays: 2, lateFeePerDay: 0.5, loanDays: 14 });
      var message = Vue.ref({ type: '', text: '', signin: '' });
      var busyId = Vue.ref(0);
      var confirmId = Vue.ref(0);
      var highlight = Vue.ref(props.highlightId);
      var messageEl = Vue.ref(null);
      var started = false;

      // Copies the server's answer into the reactive state.
      function apply(data) {
        if (!data || !Array.isArray(data.loans)) { return; }
        loans.value = data.loans;
        feesFromReturns.value = data.feesFromReturns || 0;
        blocks.value = data.blocks || [];
        if (data.rules) { rules.value = data.rules; }
      }

      // Shows a message and moves focus to it, so keyboard and screen reader users hear the result.
      function say(type, text, signin) {
        message.value = { type: type, text: text, signin: signin || '' };
        Vue.nextTick(function () { if (messageEl.value) { messageEl.value.focus(); } });
      }

      // Loads the loans the first time.
      function load() {
        api.requestJson('api/loans.php').then(function (res) {
          if (!res.ok) { throw new Error(res.data.error || 'Could not load loans.'); }
          apply(res.data);
          started = true;
          ctx.emit('ready');
        }).catch(function () { if (!started) { ctx.emit('failed'); } });
      }

      // Sends renew or return for one loan and shows PHP's answer, whatever it is.
      function send(action, loan) {
        busyId.value = loan.id;
        api.requestJson('api/loans.php', { method: 'POST', form: { action: action, loan_id: loan.id } })
          .then(function (res) {
            apply(res.data);
            if (res.ok) {
              highlight.value = action === 'renew' ? loan.id : 0;
              say('success', res.data.message);
            } else {
              say('error', res.data.error || 'That did not work. Please reload the page.', res.status === 401 ? res.data.signin : '');
            }
          })
          .catch(function () {
            say('error', 'Could not reach the library. Check your connection and try again, or reload the page.');
          })
          .then(function () { busyId.value = 0; confirmId.value = 0; });
      }

      // Renews a loan straight away (the server checks every rule).
      function renew(loan) { send('renew', loan); }
      // Opens the "are you sure" step for a return, like the Base confirm.
      function askReturn(loan) {
        confirmId.value = loan.id;
        Vue.nextTick(function () {
          var yes = document.getElementById('vl-yes-' + loan.id);
          if (yes) { yes.focus(); }
        });
      }
      // Returns a loan (or cancels a reservation) after it was confirmed.
      function giveBack(loan) { send('return', loan); }

      var openCount = Vue.computed(function () { return loans.value.length; });
      var totalFee = Vue.computed(function () {
        return loans.value.reduce(function (sum, l) { return sum + l.fee; }, feesFromReturns.value);
      });
      var dueSoon = Vue.computed(function () {
        return loans.value.filter(function (l) { return l.state === 'active' && l.daysLeft <= rules.value.dueSoonDays; });
      });
      var reminder = Vue.computed(function () {
        return dueSoon.value.map(function (l) { return l.title + ' is ' + dueSoonText(l.daysLeft); }).join('; ');
      });

      // Keeps the parts of the page outside Vue (header badge, tiles) in step with the totals.
      Vue.watch([openCount, totalFee], function () {
        var badge = document.querySelector('.loans-link .badge');
        if (badge) {
          badge.textContent = openCount.value;
          badge.classList.toggle('is-empty', openCount.value === 0);
          var spoken = badge.nextElementSibling;
          if (spoken && spoken.classList.contains('visually-hidden')) {
            spoken.textContent = ' ' + (openCount.value === 1 ? 'book' : 'books') + ' on loan';
          }
        }
        [].forEach.call(document.querySelectorAll('.tiles .tile'), function (tile) {
          var label = tile.querySelector('.tile-label');
          var value = tile.querySelector('.tile-value');
          if (!label || !value) { return; }
          if (label.textContent === 'Books on loan') { value.textContent = openCount.value; }
          if (label.textContent === 'Late fees owed') {
            value.textContent = money(totalFee.value);
            tile.classList.toggle('tile-alert', totalFee.value > 0);
          }
        });
      });

      Vue.onMounted(load);

      return {
        loans: loans, blocks: blocks, rules: rules, message: message, busyId: busyId, confirmId: confirmId,
        highlight: highlight, messageEl: messageEl, openCount: openCount, totalFee: totalFee, dueSoon: dueSoon,
        reminder: reminder, renew: renew, askReturn: askReturn, giveBack: giveBack, money: money, daysText: daysText,
        findUrl: api.url('catalogue.php?sort=available')
      };
    },

    template:
      '<div class="vl">' +
      '  <ul class="vl-summary" role="list" aria-label="Loan totals">' +
      '    <li><strong>{{ openCount }} of {{ rules.maxLoans }}</strong> loans</li>' +
      '    <li :class="{ \'is-alert\': totalFee > 0 }"><strong>{{ money(totalFee) }}</strong> late fees owed</li>' +
      '    <li :class="{ \'is-soon\': dueSoon.length > 0 }"><strong>{{ dueSoon.length }}</strong> due soon</li>' +
      '  </ul>' +
      '  <p v-if="message.text" ref="messageEl" tabindex="-1" class="notice vl-message" :class="message.type === \'error\' ? \'notice-alert\' : \'notice-ok\'"' +
      '     :role="message.type === \'error\' ? \'alert\' : \'status\'">{{ message.text }} <a v-if="message.signin" :href="message.signin">Sign in</a></p>' +
      '  <div v-if="blocks.length" class="notice notice-alert" role="status">' + ICON_LOCK + '<p><strong>New loans are paused.</strong> {{ blocks.join(\' \') }}</p></div>' +
      '  <p v-if="dueSoon.length" class="notice notice-soon" role="status">' + ICON_CLOCK + ' <span><strong>Reminder:</strong> {{ reminder }}.' +
      '     Return or renew in time to avoid the {{ money(rules.lateFeePerDay) }} a day late fee.</span></p>' +
      '  <transition-group v-if="loans.length" tag="ul" name="loan" class="loan-list" role="list">' +
      '    <li v-for="l in loans" :key="l.id" class="loan-item" :class="{ \'is-overdue\': l.state === \'overdue\', \'is-highlight\': l.id === highlight }"' +
      '        :aria-busy="busyId === l.id ? \'true\' : \'false\'" :data-loan="l.id">' +
      '      <a :href="l.url" tabindex="-1" aria-hidden="true"><img class="line-cover" :src="l.cover" :alt="l.coverAlt" width="64" height="96" loading="lazy" decoding="async"></a>' +
      '      <div class="loan-body">' +
      '        <h3><a :href="l.url">{{ l.title }}</a> <span class="muted small">· {{ l.author }}</span></h3>' +
      '        <p v-if="l.state === \'reserved\'"><span class="avail is-mine">Reserved</span> Collect at the front desk from <strong>{{ l.collectedLong }}</strong>. Due back {{ l.dueLong }}.</p>' +
      '        <p v-else-if="l.state === \'overdue\'"><span class="avail is-out">{{ daysText(l.daysLate) }} overdue, {{ money(l.fee) }} owed so far</span>' +
      '           It was due back on {{ l.dueLong }}. The fee grows by {{ money(rules.lateFeePerDay) }} a day until you return it.</p>' +
      '        <template v-else>' +
      '          <p><span class="avail" :class="l.daysLeft === 0 ? \'is-low\' : \'is-mine\'">{{ l.daysLeft === 0 ? \'Due today\' : \'Due back in \' + daysText(l.daysLeft) }}</span>' +
      '             Collected {{ l.collectedShort }}, due {{ l.dueLong }}.<template v-if="l.renewals"> Renewed {{ l.renewals === 1 ? \'once\' : l.renewals + \' times\' }}.</template></p>' +
      '          <p v-if="!l.canRenew && l.renewals < rules.maxRenewals" class="small muted">{{ l.renewWhy }}</p>' +
      '        </template>' +
      '      </div>' +
      '      <div class="loan-action cluster">' +
      '        <button v-if="l.canRenew" type="button" class="btn btn-secondary btn-sm" :disabled="busyId !== 0" @click="renew(l)"' +
      '                :aria-label="\'Renew \' + l.title + \' until \' + l.renewToLong">Renew to {{ l.renewToShort }}</button>' +
      '        <button v-if="confirmId !== l.id" type="button" class="btn btn-ghost btn-sm" :disabled="busyId !== 0" @click="askReturn(l)">' +
      '          {{ l.state === \'reserved\' ? \'Cancel reservation\' : \'Return\' }}</button>' +
      '        <div v-else class="confirm-panel" role="group" :aria-label="\'Confirm return of \' + l.title">' +
      '          <span class="small">{{ l.state === \'reserved\' ? \'Cancel this reservation?\' : \'Return \' + l.title + \' today?\' }}</span>' +
      '          <button :id="\'vl-yes-\' + l.id" type="button" class="btn btn-primary btn-sm" :disabled="busyId !== 0" @click="giveBack(l)">' +
      '            {{ l.state === \'reserved\' ? \'Yes, cancel\' : \'Yes, return it\' }}</button>' +
      '          <button type="button" class="btn btn-ghost btn-sm" @click="confirmId = 0">Not yet</button>' +
      '        </div>' +
      '      </div>' +
      '    </li>' +
      '  </transition-group>' +
      '  <p v-else class="notice">No books on loan. Borrow up to {{ rules.maxLoans }} books at a time, free for {{ rules.loanDays }} days, and renew each one' +
      '     {{ rules.maxRenewals === 1 ? \'once\' : rules.maxRenewals + \' times\' }}. <a :href="findUrl">See what is on the shelf</a></p>' +
      '</div>'
  };

  window.BookNestVue && (window.BookNestVue.components.LoansPanel = LoansPanel);

  // Mounts <LoansPanel> in the "Your loans" section, in place of the Base notices and loan list.
  // The queue ("Waiting for") and the recent returns table below stay as PHP printed them.
  window.BookNestVue && window.BookNestVue.ready(function (Vue) {
    var section = document.getElementById('loans');
    var head = section && section.querySelector('.section-head');
    if (!head) { return; }
    var base = [];
    for (var el = head.nextElementSibling; el && el.id !== 'queue' && !el.classList.contains('history'); el = el.nextElementSibling) {
      base.push(el);
    }
    if (!base.length) { return; }
    var loan = parseInt(new URLSearchParams(window.location.search).get('loan') || '0', 10) || 0;
    window.BookNestVue.enhance(Vue, LoansPanel, { highlightId: loan }, base, base[0]);
  });
})();
