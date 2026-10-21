// Site wide behaviour: mobile menu toggle, dismissible messages, auto submitting selects.
(function () {
  'use strict';

  // Opens and closes the mobile navigation and keeps aria-expanded in sync.
  function setupMenu() {
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('site-nav');
    if (!toggle || !nav) { return; }
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      nav.classList.toggle('is-open', open);
      if (open) {
        var first = nav.querySelector('a');
        if (first) { first.focus(); }
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  // Shows a close button on each flash message and removes the message when pressed.
  function setupFlash() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-dismiss]'), function (button) {
      button.hidden = false;
      button.addEventListener('click', function () {
        var box = button.closest('.flash');
        var stack = box.parentNode;
        stack.removeChild(box);
        if (!stack.children.length) { stack.parentNode.removeChild(stack); }
        var main = document.getElementById('main');
        if (main) { main.focus(); }
      });
    });
  }

  // Submits a form as soon as a select changes, and hides the now unneeded submit button.
  function setupAutosubmit() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-autosubmit]'), function (select) {
      var form = select.form;
      var button = form.querySelector('[data-autosubmit-btn]');
      if (button) { button.hidden = true; }
      select.addEventListener('change', function () { form.submit(); });
    });
  }

  setupMenu();
  setupFlash();
  setupAutosubmit();
})();
