// Sample reader: turns the server rendered pages into a book with 3D page turns.
// Works by swipe or drag (Pointer Events), arrow keys, clicking page edges and the buttons.
(function () {
  'use strict';

  var root = document.querySelector('[data-reader]');
  if (!root) { return; }

  var stage = root.querySelector('[data-reader-stage]');
  var book = root.querySelector('[data-reader-book]');
  var pages = Array.prototype.slice.call(book.querySelectorAll('.page'));
  var countEl = root.querySelector('[data-reader-count]');
  var progressEl = root.querySelector('[data-reader-progress]');
  var prevBtn = root.querySelector('[data-reader-prev]');
  var nextBtn = root.querySelector('[data-reader-next]');
  var wide = window.matchMedia('(min-width: 1024px)');
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var storeKey = 'mg-read-' + root.getAttribute('data-book-id');
  var TURN_MS = 620;
  var SWIPE_PX = 40;

  var all = pages.slice();   // pages plus an optional blank page to complete the last spread
  var blank = null;
  var index = 0;             // the left page in a spread, or the only page on a phone
  var busy = false;

  // Returns 2 on wide screens (two page spread) and 1 on phones.
  function perView() {
    return wide.matches ? 2 : 1;
  }

  // Keeps spreads complete by adding a blank page when the page count is odd.
  function balancePages() {
    var needBlank = perView() === 2 && pages.length % 2 === 1;
    if (needBlank && !blank) {
      blank = document.createElement('section');
      blank.className = 'page page-blank';
      blank.setAttribute('aria-hidden', 'true');
      book.appendChild(blank);
    } else if (!needBlank && blank) {
      book.removeChild(blank);
      blank = null;
    }
    all = blank ? pages.concat([blank]) : pages.slice();
  }

  // Moves the index to the start of a spread when two pages are shown.
  function alignIndex(i) {
    i = Math.max(0, Math.min(i, all.length - 1));
    return perView() === 2 ? i - (i % 2) : i;
  }

  // Removes every position class from every page.
  function clearSlots() {
    all.forEach(function (p) { p.classList.remove('is-left', 'is-right', 'is-single'); });
  }

  // Shows a page in a slot, if the page exists.
  function place(page, slot) {
    if (page) { page.classList.add(slot); }
  }

  // Shows the current page or spread and updates the counter, progress and buttons.
  function render() {
    clearSlots();
    if (perView() === 2) {
      place(all[index], 'is-left');
      place(all[index + 1], 'is-right');
    } else {
      place(all[index], 'is-single');
    }
    updateStatus();
  }

  // Writes "Page 3 of 12" or "Pages 3 and 4 of 12", sets the progress bar and button states.
  function updateStatus() {
    var total = pages.length;
    var first = index + 1;
    var last = perView() === 2 ? Math.min(index + 2, total) : first;
    countEl.textContent = last > first ? 'Pages ' + first + ' and ' + last + ' of ' + total : 'Page ' + first + ' of ' + total;
    progressEl.style.width = Math.round((last / total) * 100) + '%';
    prevBtn.disabled = index === 0;
    nextBtn.disabled = index + perView() >= all.length;
    try { window.localStorage.setItem(storeKey, String(index)); } catch (err) { /* storage may be blocked */ }
  }

  // Copies a page for the turning leaf, hidden from assistive technology.
  function cloneFace(page) {
    var copy = page.cloneNode(true);
    copy.classList.remove('is-left', 'is-right', 'is-single');
    copy.removeAttribute('aria-label');
    Array.prototype.forEach.call(copy.querySelectorAll('[id]'), function (el) { el.removeAttribute('id'); });
    Array.prototype.forEach.call(copy.querySelectorAll('a, button, input'), function (el) { el.setAttribute('tabindex', '-1'); });
    return copy;
  }

  // Builds the turning leaf with a front and a back face.
  function makeLeaf(frontPage, backPage, side) {
    var leaf = document.createElement('div');
    leaf.className = 'leaf leaf-' + side;
    leaf.setAttribute('aria-hidden', 'true');
    var front = document.createElement('div');
    front.className = 'leaf-face leaf-front';
    front.appendChild(cloneFace(frontPage));
    var back = document.createElement('div');
    back.className = 'leaf-face leaf-back';
    if (backPage) { back.appendChild(cloneFace(backPage)); } else { back.classList.add('is-paper'); }
    leaf.appendChild(front);
    leaf.appendChild(back);
    return leaf;
  }

  // Animates a leaf between two rotations, then removes it and runs the callback.
  function turnLeaf(leaf, from, to, done) {
    leaf.style.transform = from;
    book.appendChild(leaf);
    void leaf.offsetWidth; // apply the start position before the transition begins
    leaf.style.transition = 'transform ' + TURN_MS + 'ms cubic-bezier(.45,.05,.25,1)';
    leaf.classList.add('is-turning');
    leaf.style.transform = to;
    var finished = false;
    function finish() {
      if (finished) { return; }
      finished = true;
      if (leaf.parentNode) { leaf.parentNode.removeChild(leaf); }
      done();
    }
    leaf.addEventListener('transitionend', finish);
    window.setTimeout(finish, TURN_MS + 150);
  }

  // Changes the page without animation (used for reduced motion and jumps).
  function jumpTo(newIndex) {
    index = alignIndex(newIndex);
    if (!reduceMotion.matches) {
      render();
      return;
    }
    book.classList.remove('is-fading');
    void book.offsetWidth;
    book.classList.add('is-fading');
    render();
  }

  // Turns forward by one page (phone) or one spread (desktop).
  function next() {
    if (busy || index + perView() >= all.length) { return; }
    if (reduceMotion.matches) { jumpTo(index + perView()); return; }
    busy = true;
    var leaf;
    clearSlots();
    if (perView() === 2) {
      place(all[index], 'is-left');
      place(all[index + 3], 'is-right');
      leaf = makeLeaf(all[index + 1], all[index + 2], 'right');
      turnLeaf(leaf, 'rotateY(0deg)', 'rotateY(-180deg)', function () { index += 2; busy = false; render(); });
    } else {
      place(all[index + 1], 'is-single');
      leaf = makeLeaf(all[index], null, 'full');
      turnLeaf(leaf, 'rotateY(0deg)', 'rotateY(-180deg)', function () { index += 1; busy = false; render(); });
    }
  }

  // Turns back by one page (phone) or one spread (desktop).
  function prev() {
    if (busy || index === 0) { return; }
    if (reduceMotion.matches) { jumpTo(index - perView()); return; }
    busy = true;
    var leaf;
    if (perView() === 2) {
      clearSlots();
      place(all[index - 2], 'is-left');
      place(all[index + 1], 'is-right');
      leaf = makeLeaf(all[index], all[index - 1], 'left');
      turnLeaf(leaf, 'rotateY(0deg)', 'rotateY(180deg)', function () { index -= 2; busy = false; render(); });
    } else {
      leaf = makeLeaf(all[index - 1], null, 'full');
      turnLeaf(leaf, 'rotateY(-180deg)', 'rotateY(0deg)', function () { index -= 1; busy = false; render(); });
    }
  }

  // Swipe and drag: a horizontal movement past the threshold turns the page.
  var startX = null;
  var startY = null;
  var dragged = false;
  stage.addEventListener('pointerdown', function (event) {
    if (event.button !== 0 || event.target.closest('a, button, input, select, textarea')) { return; }
    startX = event.clientX;
    startY = event.clientY;
    dragged = false;
  });
  stage.addEventListener('pointermove', function (event) {
    if (startX === null) { return; }
    var dx = event.clientX - startX;
    book.style.setProperty('--drag', Math.max(-1, Math.min(1, dx / 200)).toFixed(3));
    if (Math.abs(dx) > 6) { dragged = true; }
  });
  function endDrag(event) {
    if (startX === null) { return; }
    var dx = event.clientX - startX;
    var dy = event.clientY - startY;
    startX = null;
    book.style.setProperty('--drag', '0');
    if (Math.abs(dx) >= SWIPE_PX && Math.abs(dx) > Math.abs(dy)) {
      if (dx < 0) { next(); } else { prev(); }
    }
  }
  stage.addEventListener('pointerup', endDrag);
  stage.addEventListener('pointercancel', function () { startX = null; book.style.setProperty('--drag', '0'); });

  // Clicking the outer quarter of the book turns the page, like lifting a corner.
  stage.addEventListener('click', function (event) {
    if (dragged) { dragged = false; return; }
    if (event.target.closest('a, button, input, select, textarea, form')) { return; }
    var rect = book.getBoundingClientRect();
    var x = (event.clientX - rect.left) / rect.width;
    if (x < 0.25) { prev(); } else if (x > 0.75) { next(); }
  });

  // Keyboard: arrows and Page Up/Down turn pages, Home and End jump.
  document.addEventListener('keydown', function (event) {
    var target = event.target;
    var typing = target && target.closest && target.closest('input, textarea, select');
    if (typing || event.altKey || event.ctrlKey || event.metaKey) { return; }
    if (event.key === 'ArrowRight' || event.key === 'PageDown') { event.preventDefault(); next(); }
    else if (event.key === 'ArrowLeft' || event.key === 'PageUp') { event.preventDefault(); prev(); }
    else if (event.key === 'Home') { event.preventDefault(); jumpTo(0); }
    else if (event.key === 'End') { event.preventDefault(); jumpTo(all.length - 1); }
  });

  prevBtn.addEventListener('click', prev);
  nextBtn.addEventListener('click', next);

  // Switching between phone and desktop layouts keeps the reader on the same page.
  function onLayoutChange() {
    balancePages();
    index = alignIndex(index);
    render();
  }
  if (wide.addEventListener) { wide.addEventListener('change', onLayoutChange); } else { wide.addListener(onLayoutChange); }

  // Start: enhance the layout and resume where the reader left off.
  root.classList.add('is-enhanced');
  root.querySelector('[data-reader-controls]').hidden = false;
  root.querySelector('[data-reader-help]').hidden = false;
  balancePages();
  var saved = 0;
  try { saved = parseInt(window.localStorage.getItem(storeKey), 10) || 0; } catch (err) { saved = 0; }
  index = alignIndex(saved >= all.length - 1 ? 0 : saved);
  render();
})();
