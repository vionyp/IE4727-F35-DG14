// Study rooms: clicking a free slot fills the booking form in place, and the form checks
// closing time, past times and the daily quota before sending. PHP checks again on the server.
(function () {
  'use strict';

  var form = document.querySelector('form[data-booking]');
  var table = document.querySelector('[data-availability]');
  if (!form || !table) { return; }

  var room = form.elements.room_id;
  var date = form.elements.date;
  var start = form.elements.start;
  var duration = form.elements.duration;
  var summary = form.querySelector('[data-booking-summary]');
  var cap = parseInt(form.getAttribute('data-cap'), 10);
  var closeMin = parseInt(form.getAttribute('data-close'), 10);
  var today = form.getAttribute('data-today');
  var nowMin = parseInt(form.getAttribute('data-now'), 10);
  var tableDate = date.value;

  // Reads "2026-09-28:30,2026-09-29:0" into an object of minutes already used per date.
  var used = {};
  (form.getAttribute('data-quota') || '').split(',').forEach(function (pair) {
    var parts = pair.split(':');
    if (parts.length === 2) { used[parts[0]] = parseInt(parts[1], 10) || 0; }
  });

  // Converts "14:30" to minutes after midnight.
  function toMinutes(hm) {
    var p = hm.split(':');
    return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
  }

  // Converts minutes after midnight to "14:30".
  function toTime(m) {
    var h = Math.floor(m / 60);
    var mm = m % 60;
    return (h < 10 ? '0' : '') + h + ':' + (mm < 10 ? '0' : '') + mm;
  }

  // Formats "2026-09-28" as "Mon 28 Sep" without relying on the visitor's timezone.
  function niceDate(iso) {
    var p = iso.split('-');
    var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
    return d.toLocaleDateString('en-SG', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' });
  }

  // Marks a field with a custom rule message (read by forms.js) or clears it.
  function rule(el, message) {
    if (message) { el.setAttribute('data-custom-error', message); } else { el.removeAttribute('data-custom-error'); }
  }

  // Checks the rules HTML5 cannot express and writes a plain language summary.
  function check() {
    rule(start, '');
    rule(duration, '');
    if (!start.value || !date.value) {
      summary.textContent = '';
      return;
    }
    var s = toMinutes(start.value);
    var d = parseInt(duration.value, 10);
    var e = s + d;
    var already = used[date.value] || 0;
    if (date.value === today && s <= nowMin) {
      rule(start, 'That time has already passed. Choose a later slot.');
    } else if (e > closeMin) {
      rule(duration, 'The library closes at ' + toTime(closeMin) + '. Choose an earlier start or 30 minutes.');
    } else if (already + d > cap) {
      rule(duration, 'You already have ' + already + ' minutes booked on ' + niceDate(date.value) + '. The daily limit is ' + cap + ' minutes'
        + (cap - already > 0 ? ', so choose ' + (cap - already) + ' minutes.' : '.'));
    }
    var roomName = room.selectedIndex > 0 ? room.options[room.selectedIndex].text.split(' · ')[0] : 'a room';
    var left = Math.max(0, cap - already - d);
    summary.textContent = roomName + ', ' + niceDate(date.value) + ', ' + start.value + ' to ' + toTime(e)
      + '. You will have ' + left + ' minutes left that day.';
  }

  // Highlights the chosen cell in the table.
  function markSelected(link) {
    Array.prototype.forEach.call(table.querySelectorAll('.is-selected'), function (el) { el.classList.remove('is-selected'); });
    if (link) { link.classList.add('is-selected'); }
  }

  table.addEventListener('click', function (event) {
    var link = event.target.closest('a.slot-free');
    if (!link) { return; }
    event.preventDefault();
    room.value = link.getAttribute('data-room');
    start.value = link.getAttribute('data-start');
    date.value = tableDate;
    markSelected(link);
    check();
    var panel = document.getElementById('book');
    panel.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    duration.focus({ preventScroll: true });
  });

  [room, date, start, duration].forEach(function (el) {
    el.addEventListener('change', check);
  });
  check();
})();
