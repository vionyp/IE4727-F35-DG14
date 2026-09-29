// Book rows: previous and next buttons for the horizontal, scroll snapping rows.
(function () {
  'use strict';

  var smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Enables the buttons for one row and keeps their disabled state in step with the scroll.
  function setupRow(row) {
    var track = row.querySelector('[data-row-track]');
    var controls = row.querySelector('.row-controls');
    var prev = row.querySelector('[data-row-prev]');
    var next = row.querySelector('[data-row-next]');
    if (!track || !controls) { return; }

    // Updates which buttons can be used, and hides both when everything already fits.
    function update() {
      var max = track.scrollWidth - track.clientWidth - 2;
      controls.hidden = max <= 0;
      prev.disabled = track.scrollLeft <= 2;
      next.disabled = track.scrollLeft >= max;
    }

    // Scrolls the row by almost one full view in the given direction.
    function scrollByPage(direction) {
      track.scrollBy({ left: direction * track.clientWidth * 0.9, behavior: smooth ? 'smooth' : 'auto' });
    }

    prev.addEventListener('click', function () { scrollByPage(-1); });
    next.addEventListener('click', function () { scrollByPage(1); });
    track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
    window.addEventListener('resize', update);
    update();
  }

  Array.prototype.forEach.call(document.querySelectorAll('.row'), setupRow);
})();
