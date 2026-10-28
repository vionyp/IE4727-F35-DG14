// Onboarding tour for new members: shows how to find, borrow and return a book, one highlighted step at a time.
// The rest of the page is frozen behind a dim layer; only the highlighted target and the tour card respond.
// Progress lives in a small cookie so the tour can carry on across pages. Skip or Escape ends it at once.
(function () {
  'use strict';

  var body = document.body;
  if (!body || body.getAttribute('data-tour') !== 'member') { return; }

  var COOKIE = body.getAttribute('data-tour-cookie') || 'bn_tour';
  var BASE = body.getAttribute('data-base') || '/';
  var PAD = 8;
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Each step: where it runs, what it highlights, and whether the member must click the target to move on.
  // type "modal" has no target, "info" moves on with Next, "click" moves on when the target is clicked.
  // restart: the step to go back to if the member lands on another page. fallback: where to go if the target is missing.
  var STEPS = [
    { page: 'any', type: 'modal', eyebrow: 'Getting started', title: 'Welcome to BookNest',
      text: 'Take a one minute tour and we will show you how to find a book, borrow it and give it back. You can skip at any time.',
      primary: 'Start the tour', secondary: 'Skip for now' },
    { page: 'any', type: 'click', target: ['#site-nav a[href$="catalogue.php"]', '.tabbar a[href$="catalogue.php"]'],
      title: 'Start with Browse',
      text: 'Every book in the library is listed here. You can also search by title, author or ISBN from the box at the top of any page.',
      hint: 'Select Browse to continue.' },
    { page: 'catalogue.php', type: 'click', target: availableCard, restart: 1, fallback: 7,
      title: 'Pick a book',
      text: 'Each card shows whether the book is on the shelf right now. This one is available to borrow.',
      hint: 'Open this book to continue.' },
    { page: 'book.php', type: 'info', target: ['.book-actions'], restart: 1,
      title: 'Everything you can do with a book',
      text: 'Borrow it, read a free sample first, or save it to your shelf for later. If every copy is out, join the queue and we will email you when one comes back.' },
    { page: 'book.php', type: 'click', target: ['.book-actions a[href*="borrow.php"]'], restart: 1, fallback: 7,
      title: 'Borrow it',
      text: 'Borrowing is free. Nothing is confirmed until the next page, so go ahead.',
      hint: 'Select Borrow to continue.' },
    { page: 'borrow.php', type: 'info', target: ['.borrow-form .field'], restart: 1,
      title: 'Choose a collection date',
      text: 'Pick the day you will collect the book at the front desk, up to a week ahead. Each option shows the date the book is due back.' },
    { page: 'borrow.php', type: 'info', target: ['.borrow-form button[type="submit"]'], restart: 1,
      title: 'Confirm when you are ready',
      text: 'Confirm loan reserves the book and emails you the due date. The tour never borrows anything for you, so this button is paused for now.' },
    { page: 'any', type: 'click', target: ['.loans-link', '.tabbar a[href$="account.php"]'],
      title: 'Your loans, one click away',
      text: 'This icon is on every page. The number shows how many books you have on loan.',
      hint: 'Open My loans to continue.' },
    { page: 'account.php', type: 'info', target: ['#loans'], restart: 7,
      title: 'Returning a book',
      text: 'Each loan shows its due date. To return a book early, press Return next to it. A late return adds a small fee for each day overdue, shown here and settled at the front desk.' },
    { page: 'any', type: 'info', target: ['#site-nav a[href$="rooms.php"]', '.tabbar a[href$="rooms.php"]'],
      title: 'Need a quiet place to study?',
      text: 'Study Rooms lets you book one of 50 rooms for 30 or 60 minutes, up to a week ahead.' },
    { page: 'any', type: 'modal', eyebrow: 'Tour complete', title: 'You are all set',
      text: 'You know how to find, borrow and return a book. You can replay this tour any time from My Account.',
      primary: 'Start exploring' }
  ];

  // Shown instead of a step when the member has left the page that step belongs to.
  var PAUSED = { page: 'any', type: 'modal', eyebrow: 'Tour paused', title: 'Pick up where you left off?',
    text: 'You moved to another page, so the tour paused. Continue from the last place we can show you, or end it here.',
    primary: 'Continue the tour', secondary: 'End the tour' };

  var TOTAL = STEPS.filter(function (s) { return s.type !== 'modal'; }).length;

  var ui = null;
  var current = -1;
  var step = null;
  var target = null;
  var frame = 0;

  // Returns the file name of the current page, treating the site root as index.php.
  function currentPage() {
    var file = window.location.pathname.split('/').pop();
    return file || 'index.php';
  }

  // Reads the saved step number, ignoring anything that is not a known step.
  function readStep() {
    var m = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
    if (!m || !/^\d{1,2}$/.test(m[1])) { return -1; }
    var n = parseInt(m[1], 10);
    return n < STEPS.length ? n : -1;
  }

  // Saves the step number for one day, so the tour can continue on the next page.
  function writeStep(n) {
    document.cookie = COOKIE + '=' + n + '; path=' + BASE + '; max-age=86400; samesite=lax';
  }

  // Deletes the progress cookie.
  function clearStep() {
    document.cookie = COOKIE + '=; path=' + BASE + '; max-age=0; samesite=lax';
  }

  // True when an element is on screen and has a size (hidden menus and tab bars are skipped).
  function isShown(el) {
    if (!el) { return false; }
    var r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && window.getComputedStyle(el).visibility !== 'hidden';
  }

  // Finds the first visible element for a step from its list of selectors, or by calling its finder.
  function findTarget(s) {
    if (!s.target) { return null; }
    if (typeof s.target === 'function') { return s.target(); }
    for (var i = 0; i < s.target.length; i++) {
      var el = document.querySelector(s.target[i]);
      if (isShown(el)) { return el; }
    }
    return null;
  }

  // Finds the first book in the catalogue that is on the shelf now, so the borrow steps always work.
  function availableCard() {
    var cards = document.querySelectorAll('.card');
    for (var i = 0; i < cards.length; i++) {
      var avail = cards[i].querySelector('.avail.is-in');
      var link = cards[i].querySelector('.card-link');
      if (avail && isShown(link)) { return link; }
    }
    return null;
  }

  // Creates an element with a class name and optional text.
  function make(tag, className, text) {
    var el = document.createElement(tag);
    if (className) { el.className = className; }
    if (text) { el.textContent = text; }
    return el;
  }

  // Builds the dim layer, highlight ring, click guard and tour card once.
  function build() {
    if (ui) { return; }
    ui = { root: make('div', 'tour') };
    ui.root.setAttribute('data-tour-layer', '');
    ['top', 'right', 'bottom', 'left'].forEach(function (side) {
      ui[side] = make('div', 'tour-shade');
      ui[side].setAttribute('aria-hidden', 'true');
      ui[side].addEventListener('click', nudge);
      ui.root.appendChild(ui[side]);
    });
    ui.ring = make('div', 'tour-ring');
    ui.ring.setAttribute('aria-hidden', 'true');
    ui.lid = make('div', 'tour-lid');
    ui.lid.setAttribute('aria-hidden', 'true');
    ui.lid.addEventListener('click', nudge);

    ui.pop = make('div', 'tour-pop');
    ui.pop.setAttribute('role', 'dialog');
    ui.pop.setAttribute('aria-modal', 'true');
    ui.pop.setAttribute('aria-labelledby', 'tour-title');
    ui.pop.setAttribute('aria-describedby', 'tour-text');
    ui.pop.tabIndex = -1;

    var top = make('div', 'tour-top');
    ui.count = make('p', 'tour-count');
    ui.skip = make('button', 'tour-skip', 'Skip tour');
    ui.skip.type = 'button';
    ui.skip.addEventListener('click', end);
    top.appendChild(ui.count);
    top.appendChild(ui.skip);

    ui.bar = make('div', 'tour-bar');
    ui.barFill = make('span');
    ui.bar.appendChild(ui.barFill);

    ui.title = make('h2', 'tour-title');
    ui.title.id = 'tour-title';
    ui.text = make('p', 'tour-text');
    ui.text.id = 'tour-text';
    ui.hint = make('p', 'tour-hint');
    ui.hint.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">'
      + '<path d="M9 3.5v11.2l-2.6-2.3a1.6 1.6 0 0 0-2.2 2.3l5.1 5.6c.6.6 1.4 1 2.3 1h5.2a3 3 0 0 0 3-2.6l.7-5.2a2 2 0 0 0-1.7-2.3L12 10.4V3.5a1.5 1.5 0 0 0-3 0z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>'
      + '<span></span>';

    ui.actions = make('div', 'tour-actions');
    ui.second = make('button', 'btn btn-ghost btn-sm');
    ui.second.type = 'button';
    ui.second.addEventListener('click', onSecondary);
    ui.primary = make('button', 'btn btn-primary btn-sm');
    ui.primary.type = 'button';
    ui.primary.addEventListener('click', onPrimary);
    ui.actions.appendChild(ui.second);
    ui.actions.appendChild(ui.primary);

    [top, ui.bar, ui.title, ui.text, ui.hint, ui.actions].forEach(function (el) { ui.pop.appendChild(el); });
    ui.root.appendChild(ui.ring);
    ui.root.appendChild(ui.lid);
    ui.root.appendChild(ui.pop);
    body.appendChild(ui.root);

    document.addEventListener('keydown', onKey, true);
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', schedule, true);
    window.addEventListener('load', schedule);
  }

  // Removes the layer and its listeners.
  function teardown() {
    if (!ui) { return; }
    unbindTarget();
    document.removeEventListener('keydown', onKey, true);
    window.removeEventListener('resize', schedule);
    window.removeEventListener('scroll', schedule, true);
    window.removeEventListener('load', schedule);
    ui.root.parentNode.removeChild(ui.root);
    ui = null;
    body.classList.remove('is-touring');
  }

  // Shows step n, checking it belongs on this page and that its target exists.
  function show(n) {
    current = n;
    var s = STEPS[n];
    if (s.page !== 'any' && s.page !== currentPage()) {
      render(PAUSED, null);
      return;
    }
    var el = findTarget(s);
    if (s.type !== 'modal' && !el) {
      var next = typeof s.fallback === 'number' ? s.fallback : n + 1;
      if (next >= STEPS.length) { end(); return; }
      writeStep(next);
      show(next);
      return;
    }
    render(s, el);
  }

  // Fills the tour card for a step and highlights its target.
  function render(s, el) {
    build();
    unbindTarget();
    step = s;
    target = el;
    body.classList.add('is-touring');
    ui.root.className = 'tour is-' + s.type;

    var isModal = s.type === 'modal';
    ui.pop.classList.toggle('is-center', isModal);
    if (isModal) {
      ui.count.textContent = s.eyebrow || '';
      ui.bar.hidden = true;
      ui.skip.hidden = true;
    } else {
      var number = STEPS.slice(0, current + 1).filter(function (x) { return x.type !== 'modal'; }).length;
      ui.count.textContent = 'Step ' + number + ' of ' + TOTAL;
      ui.bar.hidden = false;
      ui.barFill.style.width = Math.round(number / TOTAL * 100) + '%';
      ui.skip.hidden = false;
    }
    ui.title.textContent = s.title;
    ui.text.textContent = s.text;
    ui.hint.hidden = s.type !== 'click';
    ui.hint.querySelector('span').textContent = s.hint || '';

    ui.primary.hidden = s.type === 'click';
    ui.primary.textContent = s.primary || 'Next';
    ui.second.hidden = !s.secondary;
    ui.second.textContent = s.secondary || '';

    if (el) {
      if (s.type === 'click') { el.addEventListener('click', onTargetClick); }
      var tall = el.getBoundingClientRect().height > window.innerHeight * 0.6;
      if (window.getComputedStyle(el).position !== 'fixed' && !el.closest('.site-header, .tabbar')) {
        el.scrollIntoView({ block: tall ? 'start' : 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
      }
    }
    place();
    ui.pop.focus({ preventScroll: true });
  }

  // Positions the dim panels around the target, the ring on it, and the card beside it.
  function place() {
    frame = 0;
    if (!ui) { return; }
    var vw = document.documentElement.clientWidth;
    var vh = window.innerHeight;
    var hole = null;
    if (target) {
      var r = target.getBoundingClientRect();
      hole = { x: r.left - PAD, y: r.top - PAD, w: r.width + PAD * 2, h: r.height + PAD * 2 };
    }

    if (!hole) {
      box(ui.top, 0, 0, vw, vh);
      box(ui.bottom, 0, 0, 0, 0);
      box(ui.left, 0, 0, 0, 0);
      box(ui.right, 0, 0, 0, 0);
      ui.ring.hidden = true;
      ui.lid.hidden = true;
      ui.pop.style.top = '';
      ui.pop.style.left = '';
      return;
    }

    box(ui.top, 0, 0, vw, hole.y);
    box(ui.bottom, 0, hole.y + hole.h, vw, vh - hole.y - hole.h);
    box(ui.left, 0, hole.y, hole.x, hole.h);
    box(ui.right, hole.x + hole.w, hole.y, vw - hole.x - hole.w, hole.h);
    ui.ring.hidden = false;
    box(ui.ring, hole.x, hole.y, hole.w, hole.h);
    ui.lid.hidden = step.type !== 'info';
    box(ui.lid, hole.x, hole.y, hole.w, hole.h);

    var pw = ui.pop.offsetWidth;
    var ph = ui.pop.offsetHeight;
    var gap = 14;
    var top;
    var left;
    if (vw < 640) {
      // Phones: dock the card to the top or bottom edge, whichever is further from the target.
      left = 16;
      top = hole.y + hole.h / 2 > vh / 2 ? 16 : vh - ph - 16;
    } else if (hole.y + hole.h + gap + ph <= vh - 16) {
      top = hole.y + hole.h + gap;
    } else if (hole.y - gap - ph >= 16) {
      top = hole.y - gap - ph;
    } else if (hole.x + hole.w + gap + pw <= vw - 16) {
      left = hole.x + hole.w + gap;
    } else if (hole.x - gap - pw >= 16) {
      left = hole.x - gap - pw;
    } else {
      top = vh - ph - 16;
    }
    if (top === undefined) { top = Math.min(Math.max(hole.y, 16), vh - ph - 16); }
    if (left === undefined) { left = Math.min(Math.max(hole.x + hole.w / 2 - pw / 2, 16), vw - pw - 16); }
    ui.pop.style.top = Math.max(top, 16) + 'px';
    ui.pop.style.left = Math.max(left, 16) + 'px';
  }

  // Sets an element's fixed position and size, never below zero.
  function box(el, x, y, w, h) {
    el.style.left = x + 'px';
    el.style.top = y + 'px';
    el.style.width = Math.max(w, 0) + 'px';
    el.style.height = Math.max(h, 0) + 'px';
  }

  // Repositions on the next animation frame after a scroll or resize.
  function schedule() {
    if (!frame) { frame = window.requestAnimationFrame(place); }
  }

  // Shakes the card gently when the member clicks a frozen part of the page.
  function nudge(event) {
    event.preventDefault();
    if (!ui) { return; }
    ui.pop.classList.remove('is-nudge');
    void ui.pop.offsetWidth;
    ui.pop.classList.add('is-nudge');
    ui.pop.focus({ preventScroll: true });
  }

  // Moves on when the member clicks the highlighted target; the link then opens as normal.
  function onTargetClick() {
    var next = current + 1;
    var link = target;
    writeStep(next);
    unbindTarget();
    if (ui) { ui.root.classList.add('is-leaving'); }
    // A link to the same page (such as account.php#loans) does not reload, so show the next step here.
    if (link.tagName === 'A' && link.pathname === window.location.pathname && link.hash) {
      window.setTimeout(function () { if (ui) { show(next); } }, 300);
    }
  }

  // Stops listening to the previous step's target.
  function unbindTarget() {
    if (target) { target.removeEventListener('click', onTargetClick); }
  }

  // Next, Start or Continue.
  function onPrimary() {
    if (step === PAUSED) {
      var back = STEPS[current].restart;
      var to = typeof back === 'number' ? back : current;
      writeStep(to);
      show(to);
      return;
    }
    var next = current + 1;
    if (next >= STEPS.length) { end(); return; }
    writeStep(next);
    show(next);
  }

  // Skip for now, or End the tour.
  function onSecondary() {
    end();
  }

  // Ends the tour for good (until the member replays it) and gives focus back to the page.
  function end() {
    clearStep();
    teardown();
    var main = document.getElementById('main');
    if (main) { main.focus({ preventScroll: true }); }
  }

  // Escape skips the tour; Tab stays inside the target and the tour card while the page is frozen.
  function onKey(event) {
    if (!ui) { return; }
    if (event.key === 'Escape') {
      event.preventDefault();
      end();
      return;
    }
    if (event.key !== 'Tab') { return; }
    var stops = [];
    if (step && step.type === 'click' && target) { stops.push(target); }
    Array.prototype.forEach.call(ui.pop.querySelectorAll('button'), function (b) {
      if (!b.hidden) { stops.push(b); }
    });
    if (!stops.length) { return; }
    event.preventDefault();
    var i = stops.indexOf(document.activeElement);
    var nextIndex = event.shiftKey ? (i <= 0 ? stops.length - 1 : i - 1) : (i + 1) % stops.length;
    stops[nextIndex].focus();
  }

  // Lets a member replay the tour from My Account.
  function setupReplay() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-tour-replay]'), function (button) {
      button.hidden = false;
      button.addEventListener('click', function () {
        writeStep(0);
        show(0);
      });
    });
  }

  // Starts or resumes the tour from the saved step, or clears the layer if the tour has ended.
  function resume() {
    var saved = readStep();
    if (saved >= 0) { show(saved); } else { teardown(); }
  }

  setupReplay();
  if (body.hasAttribute('data-tour-new')) { writeStep(0); }
  resume();
  // The Back button can restore a page from memory with an old layer on it; bring it up to date.
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) { resume(); }
  });
})();
