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

  // Paige, the help assistant: sending a message reloads the page (no AJAX), so remember where
  // the visitor was, show the newest message, and put the cursor back in the box.
  function setupAssistant() {
    var box = document.querySelector('[data-assistant]');
    if (!box) { return; }
    var form = box.querySelector('[data-assistant-form]');
    var log = box.querySelector('[data-assistant-log]');
    var input = box.querySelector('input[name="message"]');
    var saved = null;
    try { saved = window.sessionStorage.getItem('bn-scroll'); window.sessionStorage.removeItem('bn-scroll'); } catch (err) { saved = null; }
    if (box.open) {
      if (saved !== null) { window.scrollTo(0, parseInt(saved, 10) || 0); }
      log.scrollTop = log.scrollHeight;
      input.focus({ preventScroll: true });
    }
    // A link to #assistant anywhere on the site (for example in the footer) opens the panel.
    function openFromHash() {
      if (window.location.hash === '#assistant' && !box.open) { box.open = true; }
    }
    window.addEventListener('hashchange', openFromHash);
    openFromHash();
    form.addEventListener('submit', function () {
      try { window.sessionStorage.setItem('bn-scroll', String(window.scrollY)); } catch (err) { /* storage may be blocked */ }
    });
    box.addEventListener('toggle', function () {
      if (box.open) { log.scrollTop = log.scrollHeight; input.focus({ preventScroll: true }); }
    });
    document.addEventListener('click', function (event) {
      if (box.open && !box.contains(event.target) && !event.target.closest('a[href="#assistant"]')) { box.open = false; }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && box.open && box.contains(document.activeElement)) {
        box.open = false;
        box.querySelector('summary').focus();
      }
    });
  }

  setupMenu();
  setupFlash();
  setupAutosubmit();
  setupAssistant();
})();
