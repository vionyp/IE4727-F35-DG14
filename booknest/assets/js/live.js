// Live updates: listens to the BookNest live server (Node.js + Express, live/server.js) and updates the
// page in place when something it shows has changed, so nobody has to press Ctrl+R.
//
// How it works
// 1. PHP prints a signed pass and the topics this page cares about on <body>
//    (data-live-token, data-live-topics such as "me admin" or "me book:12").
// 2. This script opens one Server-Sent Events connection (EventSource) to the live server.
// 3. When a note arrives for one of the page's topics, it re-reads the same page from PHP in the
//    background and swaps in every part marked data-live="..." that changed. Parts that a Vue screen
//    has taken over are left alone: the 'booknest:live' event tells those Vue screens to reload their
//    own data from api/.
// 4. A note with a message (for example "Your late fee ... is marked as paid") is also shown in a notice.
// If the live server is not running, the connection simply fails and the page works as before.
(function () {
  'use strict';

  var body = document.body;
  var token = body.getAttribute('data-live-token');
  if (!token || !window.EventSource || !window.fetch || !window.DOMParser) { return; }

  var topics = (body.getAttribute('data-live-topics') || '').split(/\s+/).filter(Boolean);
  topics.push('hours');   // every page shows the opening hours in the footer
  var port = body.getAttribute('data-live-port') || '3000';
  var source = new EventSource(location.protocol + '//' + location.hostname + ':' + port + '/events?t=' + encodeURIComponent(token));
  var timer = 0;
  var busy = false;
  var again = false;

  source.addEventListener('update', function (event) {
    var note;
    try { note = JSON.parse(event.data); } catch (e) { return; }
    if (note.message) { notify(note.message); }
    if (topics.indexOf(note.topic) === -1) { return; }
    // Several notes often arrive together (a return tells the member, the book and the staff), so
    // wait a moment and refresh once.
    clearTimeout(timer);
    timer = setTimeout(function () {
      window.dispatchEvent(new CustomEvent('booknest:live', { detail: { topic: note.topic } }));
      refresh();
    }, 150);
  });

  // Connected once: if the line drops later (the server restarted), EventSource reconnects by itself.
  // Never connected: the live server is not running, so stop here instead of retrying every few seconds.
  var opened = false;
  source.addEventListener('open', function () { opened = true; });
  source.addEventListener('error', function () {
    if (!opened || source.readyState === EventSource.CLOSED) {
      source.close();
      if (window.console) { console.info('[BookNest] Live updates are off (the live server is not running). Pages update when you reload them.'); }
    }
  });

  // Re-reads this page from PHP and swaps the parts that changed.
  function refresh() {
    if (busy) { again = true; return; }
    busy = true;
    fetch(location.href, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-BookNest-Live': '1' } })
      .then(function (r) {
        // Signed out meanwhile, or the page is gone: leave it as it is.
        if (!r.ok || r.redirected) { return null; }
        return r.text();
      })
      .then(function (html) {
        if (html) { swap(new DOMParser().parseFromString(html, 'text/html')); }
      })
      .catch(function () { /* offline for a moment: the next note will try again */ })
      .then(function () {
        busy = false;
        if (again) { again = false; refresh(); }
      });
  }

  // Puts the fresh copy of each data-live part in place of the old one.
  function swap(fresh) {
    var parts = document.querySelectorAll('[data-live]');
    Array.prototype.forEach.call(parts, function (old) {
      if (!old.isConnected || old.closest('[data-vue-replaced]')) { return; }   // a Vue screen updates itself
      var active = document.activeElement;
      if (active && active !== body && old.contains(active) && /^(INPUT|SELECT|TEXTAREA)$/.test(active.tagName)) {
        return;   // never pull a field away from someone who is typing in it
      }
      var name = old.getAttribute('data-live');
      var next = fresh.querySelector('[data-live="' + (window.CSS && CSS.escape ? CSS.escape(name) : name) + '"]');
      if (!next || next.outerHTML === old.outerHTML) { return; }
      next = document.importNode(next, true);
      next.hidden = old.hidden;
      old.replaceWith(next);
      if (window.BookNestForms) { window.BookNestForms.enhance(next); }   // confirm steps and checks of forms.js
      next.classList.add('live-changed');
      setTimeout(function () { next.classList.remove('live-changed'); }, 1600);
    });
  }

  // A small notice in the corner, read out by screen readers.
  var box = null;
  function notify(text) {
    if (!box) {
      box = document.createElement('div');
      box.className = 'live-notes';
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      body.appendChild(box);
    }
    var note = document.createElement('p');
    note.className = 'live-note';
    note.textContent = text;
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'live-note-close';
    close.setAttribute('aria-label', 'Dismiss');
    close.textContent = '×';
    close.addEventListener('click', function () { note.remove(); });
    note.appendChild(close);
    box.appendChild(note);
    setTimeout(function () { note.remove(); }, 8000);
  }
})();
