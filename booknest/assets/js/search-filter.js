// Instant refine on the catalogue: filters the cards already on the page as the user types.
// The server search stays the source of truth; this only hides and shows rendered results.
(function () {
  'use strict';

  var wrap = document.querySelector('[data-refine]');
  var grid = document.querySelector('[data-filter-grid]');
  var countEl = document.getElementById('result-count');
  if (!wrap || !grid || !countEl) { return; }

  var input = wrap.querySelector('input');
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.card'));
  var empty = document.querySelector('[data-filter-empty]');
  var originalCount = countEl.textContent;
  var timer = null;

  // Normalises text for matching: lower case, no accents, single spaces.
  function normalise(text) {
    var t = text.toLowerCase();
    if (t.normalize) { t = t.normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    return t.replace(/\s+/g, ' ').trim();
  }

  // Shows only the cards whose title or author contains every typed word.
  function applyFilter() {
    var words = normalise(input.value).split(' ').filter(Boolean);
    var shown = 0;
    cards.forEach(function (card) {
      var haystack = normalise(card.getAttribute('data-title') + ' ' + card.getAttribute('data-author'));
      var match = words.every(function (w) { return haystack.indexOf(w) !== -1; });
      card.hidden = !match;
      if (match) { shown++; }
    });
    empty.hidden = shown > 0;
    grid.hidden = shown === 0;
    countEl.textContent = words.length
      ? shown + ' of ' + cards.length + (cards.length === 1 ? ' book' : ' books') + ' match "' + input.value.trim() + '".'
      : originalCount;
  }

  wrap.hidden = false;
  input.addEventListener('input', function () {
    window.clearTimeout(timer);
    timer = window.setTimeout(applyFilter, 120);
  });
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { input.value = ''; applyFilter(); }
  });
})();
