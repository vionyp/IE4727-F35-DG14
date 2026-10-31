// Modern Enhancement 3b: <RoomSlots>, a live slot grid for one study room (rooms.php).
//
// Vue concepts used in this file
// - props: the starting date, room, start time and duration come from the Base page and booking form;
//   canBook says whether a member booking form is on the page.
// - emits: 'ready' after the first load (boot.js then hides the Base timetable), 'failed' if it fails.
// - v-model (with .number): the date, room and duration selects are bound two ways to refs, so the
//   select shows the ref and changing the select changes the ref.
// - watch() on [date, room] with { immediate: true }: whenever the date or the room changes, the
//   slots are fetched again from api/rooms.php (an older request still on its way is cancelled).
// - computed(): minutes left on that day against the 60 minute daily cap (DAILY_CAP_MINUTES), the
//   minutes left after the chosen booking, whether the choice is over the cap, and the rooms grouped
//   by arc for the <optgroup>s. They update by themselves when the date, duration or choice changes.
// - Child component with props and emit: each cell is a <slot-cell> (slot-cell.js). The grid passes
//   data down as props and listens for @pick, then fills the Base booking form.
// - v-for with :key: one <li> per slot (key = start time) and one <option> per room (key = id).
// Server rules: the grid only helps to choose. Booking still posts the Base form to
// process/book-room.php, which checks the daily cap, overlaps and closing time again in PHP.
(function () {
  'use strict';

  var MEMORY = 'bn_room_view';   // sessionStorage key: the room last shown, so it comes back after booking

  // Remembers the room and date on view for this tab (a convenience only; never required).
  function remember(date, room) {
    try { window.sessionStorage.setItem(MEMORY, JSON.stringify({ date: date, room: room })); } catch (e) { /* storage blocked */ }
  }

  // Returns the room remembered for this date, or 0.
  function recall(date) {
    try {
      var v = JSON.parse(window.sessionStorage.getItem(MEMORY) || 'null');
      return v && v.date === date ? (parseInt(v.room, 10) || 0) : 0;
    } catch (e) { return 0; }
  }

  // Converts "14:30" to minutes after midnight.
  function toMinutes(hm) { var p = hm.split(':'); return parseInt(p[0], 10) * 60 + parseInt(p[1], 10); }
  // Converts minutes after midnight to "14:30".
  function toTime(m) { var h = Math.floor(m / 60), mm = m % 60; return (h < 10 ? '0' : '') + h + ':' + (mm < 10 ? '0' : '') + mm; }

  var RoomSlots = {
    name: 'RoomSlots',
    props: {
      initialDate: { type: String, default: '' },
      initialRoom: { type: Number, default: 0 },
      initialStart: { type: String, default: '' },
      initialDuration: { type: Number, default: 60 },
      canBook: { type: Boolean, default: false }
    },
    emits: ['ready', 'failed'],

    setup: function (props, ctx) {
      var Vue = window.Vue;
      var api = window.BookNestVue;

      var date = Vue.ref(props.initialDate);
      var room = Vue.ref(props.initialRoom);
      var duration = Vue.ref(props.initialDuration === 30 ? 30 : 60);
      var selected = Vue.ref(props.initialStart);
      var slots = Vue.ref([]);
      var rooms = Vue.ref([]);
      var dates = Vue.ref([]);
      var roomInfo = Vue.ref({ name: '', capacity: 0, arc: '' });
      var dateLong = Vue.ref('');
      var minutesUsed = Vue.ref(0);
      var signedIn = Vue.ref(false);
      var rules = Vue.ref({ dailyCap: 60, maxBooking: 60, slotMinutes: 30, closeMinutes: 21 * 60 });
      var loading = Vue.ref(true);
      var error = Vue.ref('');
      var started = false;
      var controller = null;
      var lastKey = '';

      // Fetches the slots for the chosen date and room; cancels the previous request first.
      function load() {
        if (controller) { controller.abort(); }
        controller = window.AbortController ? new AbortController() : null;
        loading.value = true;
        api.requestJson('api/rooms.php', { query: { date: date.value, room: room.value || '' }, signal: controller ? controller.signal : undefined })
          .then(function (res) {
            if (!res.ok) { throw new Error(res.data.error || 'Could not load the slots.'); }
            var d = res.data;
            var key = d.date + '|' + d.room.id;
            if (lastKey && lastKey !== key) { selected.value = ''; }   // a new room or day clears the old choice
            lastKey = key;   // set before date/room below, so the watch does not fetch the same slots again
            remember(d.date, d.room.id);
            slots.value = d.slots;
            rooms.value = d.rooms;
            dates.value = d.dates;
            roomInfo.value = d.room;
            dateLong.value = d.dateLong;
            minutesUsed.value = d.minutesUsed;
            signedIn.value = d.signedIn;
            rules.value = d.rules;
            date.value = d.date;
            room.value = d.room.id;
            error.value = '';
            loading.value = false;
            if (!started) { started = true; ctx.emit('ready'); }
          })
          .catch(function (err) {
            if (err && err.name === 'AbortError') { return; }
            loading.value = false;
            if (!started) { ctx.emit('failed'); return; }
            error.value = err.message || 'Could not load the slots. Reload the page to try again.';
          });
      }

      // Refetch whenever the date or the room changes (and once at the start).
      Vue.watch([date, room], function () {
        if (date.value + '|' + room.value === lastKey) { return; }   // already showing these slots
        load();
      }, { immediate: true });

      var leftThatDay = Vue.computed(function () { return Math.max(0, rules.value.dailyCap - minutesUsed.value); });
      var leftAfter = Vue.computed(function () { return Math.max(0, leftThatDay.value - (selected.value ? duration.value : 0)); });
      var overCap = Vue.computed(function () { return !!selected.value && minutesUsed.value + duration.value > rules.value.dailyCap; });
      var pastClosing = Vue.computed(function () { return !!selected.value && toMinutes(selected.value) + duration.value > rules.value.closeMinutes; });
      var endTime = Vue.computed(function () { return selected.value ? toTime(toMinutes(selected.value) + duration.value) : ''; });
      var freeCount = Vue.computed(function () { return slots.value.filter(function (s) { return s.status === 'free'; }).length; });
      var roomsByArc = Vue.computed(function () {
        var groups = [];
        rooms.value.forEach(function (r) {
          var g = groups[groups.length - 1];
          if (!g || g.arc !== r.arc) { g = { arc: r.arc, rooms: [] }; groups.push(g); }
          g.rooms.push(r);
        });
        return groups;
      });

      // Sets a field of the Base booking form and tells rooms.js and forms.js that it changed.
      function setField(id, value) {
        var el = document.getElementById(id);
        if (el && el.value !== String(value)) {
          el.value = String(value);
          el.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }

      // Copies the choice into the Base booking form (the form PHP checks), then moves to it.
      function fillForm(scroll) {
        if (!props.canBook || !selected.value) { return; }
        setField('bk-room', room.value);
        setField('bk-date', date.value);
        setField('bk-start', selected.value);
        setField('bk-duration', duration.value);
        if (scroll) {
          var panel = document.getElementById('book');
          var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
          if (panel) { panel.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' }); }
          var field = document.getElementById('bk-duration');
          if (field) { field.focus({ preventScroll: true }); }
        }
      }

      // Handles @pick from a <slot-cell>: remember the start time and fill the form.
      function pick(start) {
        selected.value = start;
        fillForm(true);
      }

      // A new duration in the grid goes into the form too (the form is what gets sent).
      Vue.watch(duration, function () { fillForm(false); });

      // And a duration changed in the form comes back into the grid.
      Vue.onMounted(function () {
        var field = document.getElementById('bk-duration');
        if (field) {
          field.addEventListener('change', function () {
            var v = parseInt(field.value, 10);
            if ((v === 30 || v === 60) && v !== duration.value) { duration.value = v; }
          });
        }
      });

      var dateLabel = Vue.computed(function () {
        var d = dates.value.filter(function (x) { return x.value === date.value; })[0];
        return d ? d.label.replace(/^(Today|Tomorrow), /, '') : date.value;
      });

      return {
        date: date, room: room, duration: duration, selected: selected, slots: slots, dates: dates, roomInfo: roomInfo,
        dateLong: dateLong, dateLabel: dateLabel, signedIn: signedIn, rules: rules, loading: loading, error: error,
        leftThatDay: leftThatDay, leftAfter: leftAfter, overCap: overCap, pastClosing: pastClosing, endTime: endTime,
        freeCount: freeCount, roomsByArc: roomsByArc, pick: pick, canBook: props.canBook,
        signInUrl: api.url('sign-in.php')
      };
    },

    template:
      '<section class="vr" aria-labelledby="vr-title">' +
      '  <div class="section-head"><h2 id="vr-title">Timetable: {{ roomInfo.name }}<span class="muted small"> · {{ roomInfo.capacity }} seats, {{ roomInfo.arc }}</span></h2></div>' +
      '  <div class="vr-controls">' +
      '    <div class="field"><label for="vr-date">Date</label>' +
      '      <select id="vr-date" v-model="date"><option v-for="d in dates" :key="d.value" :value="d.value">{{ d.label }}</option></select></div>' +
      '    <div class="field"><label for="vr-room">Room</label>' +
      '      <select id="vr-room" v-model.number="room"><optgroup v-for="g in roomsByArc" :key="g.arc" :label="g.arc">' +
      '        <option v-for="r in g.rooms" :key="r.id" :value="r.id" :disabled="!r.isActive">{{ r.name }} · {{ r.capacity }} seats{{ r.isActive ? \'\' : \' (closed)\' }}</option>' +
      '      </optgroup></select></div>' +
      '    <div class="field"><label for="vr-duration">How long</label>' +
      '      <select id="vr-duration" v-model.number="duration"><option :value="60">1 hour</option><option :value="30">30 minutes</option></select></div>' +
      '  </div>' +
      '  <p v-if="signedIn" class="vr-quota" :class="{ \'is-over\': overCap }" aria-live="polite">' +
      '    <strong>{{ leftThatDay }} of {{ rules.dailyCap }} minutes</strong> left for you on {{ dateLabel }}.' +
      '    <template v-if="selected"> Chosen: {{ roomInfo.name }}, {{ selected }} to {{ endTime }}, which leaves <strong>{{ leftAfter }} minutes</strong>.</template>' +
      '    <template v-if="overCap"> That is more than you have left that day, so the booking would be refused. Choose 30 minutes or another day.</template>' +
      '    <template v-else-if="pastClosing"> The library closes at 21:00, so choose 30 minutes or an earlier start.</template>' +
      '  </p>' +
      '  <p v-else class="vr-quota">Viewing is open to everyone. <a :href="signInUrl">Sign in</a> to book up to {{ rules.dailyCap }} minutes a day.</p>' +
      '  <p v-if="error" class="notice notice-alert" role="alert">{{ error }}</p>' +
      '  <ol class="vr-grid" :class="{ \'is-loading\': loading }" :aria-busy="loading ? \'true\' : \'false\'" :aria-label="\'Half hour slots for \' + roomInfo.name + \' on \' + dateLong">' +
      '    <li v-for="s in slots" :key="s.start"><span class="vr-time">{{ s.start }}</span>' +
      '      <slot-cell :cell="s" :room-name="roomInfo.name" :selected="s.start === selected" :interactive="canBook" @pick="pick"></slot-cell></li>' +
      '  </ol>' +
      '  <p class="small muted vr-note">{{ freeCount }} free half hours in {{ roomInfo.name }} on {{ dateLong }}. Choose another room or day above; the map shows every room at one time.</p>' +
      '</section>'
  };

  if (window.BookNestVue) { window.BookNestVue.components.RoomSlots = RoomSlots; }

  // Mounts <RoomSlots> in place of the Base arc timetable. The map and the booking form stay as PHP printed them.
  if (window.BookNestVue) {
    window.BookNestVue.ready(function (Vue) {
      var head = document.getElementById('timetable');
      if (!head || !window.BookNestVue.components.SlotCell) { return; }
      var base = [head, document.querySelector('main .legend'), document.querySelector('main .availability-wrap'), document.querySelector('main .table-note')].filter(Boolean);
      var params = new URLSearchParams(window.location.search);
      var form = document.getElementById('booking');
      var pickVal = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };
      var firstRoom = document.querySelector('[data-availability] [data-room]');
      var initialDate = pickVal('bk-date') || pickVal('date-jump');
      // After booking, PHP sends the member back to rooms.php?date=... without a room: reopen the one they were using.
      var initialRoom = parseInt(params.get('room') || pickVal('bk-room') || '0', 10) || recall(initialDate)
        || (firstRoom ? parseInt(firstRoom.getAttribute('data-room'), 10) : 0);
      RoomSlots.components = { SlotCell: window.BookNestVue.components.SlotCell };   // the child used as <slot-cell>
      return window.BookNestVue.enhance(Vue, RoomSlots, {
        initialDate: initialDate,
        initialRoom: initialRoom,
        initialStart: params.get('room') ? (params.get('start') || '') : '',
        initialDuration: parseInt(pickVal('bk-duration') || '60', 10),
        canBook: !!form
      }, base, head);
    });
  }
})();
