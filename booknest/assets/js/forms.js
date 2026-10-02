// Forms: inline validation on top of HTML5 rules, tabs, counters,
// confirm steps and the live cover preview. PHP re-checks everything on the server.
(function () {
  'use strict';

  var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };

  // Returns the visible label text of a field, for friendly messages.
  function labelOf(el) {
    var label = el.id ? document.querySelector('label[for="' + el.id + '"]') : null;
    var text = label ? label.textContent : (el.getAttribute('aria-label') || el.name);
    return text.replace(/\(optional\)/i, '').trim().toLowerCase();
  }

  // Returns the error paragraph for a field, creating one if the server did not print it.
  function errorBox(el) {
    var field = el.closest('.field') || el.parentNode;
    var box = field.querySelector('.field-error');
    if (!box) {
      box = document.createElement('p');
      box.className = 'field-error';
      box.id = (el.form.id || 'form') + '-' + el.name + '-error';
      field.appendChild(box);
    }
    if (!box.id) { box.id = (el.form.id || 'form') + '-' + el.name + '-error'; }
    return box;
  }

  // Shows or clears the error for one field.
  function setError(el, message) {
    var box = errorBox(el);
    box.textContent = message;
    box.hidden = !message;
    var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (id) { return id && id !== box.id; });
    if (message) {
      ids.push(box.id);
      el.setAttribute('aria-invalid', 'true');
    } else {
      el.removeAttribute('aria-invalid');
    }
    if (ids.length) { el.setAttribute('aria-describedby', ids.join(' ')); } else { el.removeAttribute('aria-describedby'); }
  }

  // Returns the first problem with a field, checking HTML5 constraints then custom rules.
  function problemWith(el) {
    var v = el.validity;
    var label = labelOf(el);
    if (el.disabled || el.type === 'hidden') { return ''; }
    if (v.valueMissing) {
      return el.type === 'checkbox' ? 'Please tick this box to continue.' : 'Please enter your ' + label + '.';
    }
    if (v.typeMismatch && el.type === 'email') { return 'Enter an email address like name@example.com.'; }
    if (v.patternMismatch) { return el.getAttribute('data-pattern-msg') || 'Please check the format of your ' + label + '.'; }
    if (v.tooShort) { return 'Use at least ' + el.minLength + ' characters.'; }
    if (v.tooLong) { return 'Use no more than ' + el.maxLength + ' characters.'; }
    if (v.rangeUnderflow || v.rangeOverflow) { return 'Enter a number from ' + el.min + ' to ' + el.max + '.'; }
    if (v.stepMismatch) { return el.step === '0.01' ? 'Use at most two decimal places.' : 'Enter a whole number.'; }
    if (v.badInput) { return 'Enter a number.'; }

    // Custom rules that HTML5 cannot express.
    var match = el.getAttribute('data-match');
    if (match && el.value !== el.form.elements[match].value) { return 'The passwords do not match.'; }
    if (el.hasAttribute('data-strength') && el.value && !(/[A-Za-z]/.test(el.value) && /\d/.test(el.value))) {
      return 'Use at least one letter and one number.';
    }
    if (el.hasAttribute('data-name-rule') && el.value && !/^[\p{L} .'-]+$/u.test(el.value.trim())) {
      return 'Use letters, spaces, apostrophes and hyphens only.';
    }
    var custom = el.getAttribute('data-custom-error');
    return custom || '';
  }

  // Validates a whole form on submit and focuses the first problem.
  function setupValidation(form) {
    var tried = false;
    form.setAttribute('novalidate', '');
    form.addEventListener('submit', function (event) {
      tried = true;
      var first = null;
      each(form.elements, function (el) {
        if (!el.name || !el.willValidate && !el.hasAttribute('data-custom-error')) { return; }
        var msg = problemWith(el);
        setError(el, msg);
        if (msg && !first) { first = el; }
      });
      if (first) {
        event.preventDefault();
        first.focus();
        return;
      }
      // Prevent double submission while the server works.
      each(form.querySelectorAll('button[type="submit"]'), function (b) {
        window.setTimeout(function () { b.disabled = true; }, 0);
      });
    });
    // After the first attempt, re-check fields as the user fixes them.
    form.addEventListener('input', function (event) {
      if (tried && event.target.name) { setError(event.target, problemWith(event.target)); }
      if (event.target.name === 'password' && form.elements.confirm && form.elements.confirm.value) {
        setError(form.elements.confirm, problemWith(form.elements.confirm));
      }
    });
    form.addEventListener('change', function (event) {
      if (tried && event.target.name) { setError(event.target, problemWith(event.target)); }
    });
  }

  // Accessible tabs; the tab named in the URL hash (for example #register) opens first.
  function setupTabs(container) {
    var tabs = Array.prototype.slice.call(container.querySelectorAll('[role="tab"]'));
    var panels = tabs.map(function (t) { return document.getElementById(t.getAttribute('aria-controls')); });

    function select(i, focus) {
      tabs.forEach(function (t, j) {
        var on = i === j;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        panels[j].hidden = !on;
      });
      if (focus) { tabs[i].focus(); }
    }

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () {
        select(i, false);
        if (window.history.replaceState) { window.history.replaceState(null, '', '#' + panels[i].id); }
      });
      tab.addEventListener('keydown', function (event) {
        var k = event.key;
        if (k === 'ArrowRight' || k === 'ArrowLeft') {
          event.preventDefault();
          select((i + (k === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length, true);
        }
      });
    });
    var start = panels.findIndex(function (p) { return '#' + p.id === window.location.hash; });
    if (start < 0) {
      start = tabs.findIndex(function (t) { return t.getAttribute('aria-selected') === 'true'; });
    }
    select(Math.max(0, start), false);
  }

  // Replaces a direct destructive button with a two step "are you sure" inline confirm.
  function setupConfirm(form) {
    var trigger = form.querySelector('[data-confirm-trigger]');
    var panel = form.querySelector('[data-confirm-panel]');
    var dismiss = form.querySelector('[data-confirm-dismiss]');
    if (!trigger || !panel) { return; }
    trigger.hidden = false;
    panel.hidden = true;
    if (dismiss) { dismiss.hidden = false; }
    trigger.addEventListener('click', function () {
      trigger.hidden = true;
      panel.hidden = false;
      var yes = panel.querySelector('button[type="submit"]');
      if (yes) { yes.focus(); }
    });
    if (dismiss) {
      dismiss.addEventListener('click', function () {
        panel.hidden = true;
        trigger.hidden = false;
        trigger.focus();
      });
    }
  }

  // Shows "12 / 2000" under text areas with a maximum length.
  function setupCounter(el) {
    var counter = document.createElement('span');
    counter.className = 'counter';
    counter.setAttribute('aria-hidden', 'true');
    el.parentNode.insertBefore(counter, el.nextSibling);
    function update() {
      counter.textContent = el.value.length + ' / ' + el.maxLength;
      var pagesOut = document.querySelector('[data-pages-for="' + el.id + '"]');
      if (pagesOut) {
        var text = el.value.trim();
        var pages = text ? text.split(/\n?---PAGE---\n?/).filter(function (p) { return p.trim(); }).length : 0;
        pagesOut.textContent = pages ? pages + (pages === 1 ? ' page' : ' pages') + ' in this sample.' : 'No sample yet.';
      }
    }
    el.addEventListener('input', update);
    update();
  }

  // Keeps serial numbers in capitals while typing.
  function setupUppercase(el) {
    el.addEventListener('input', function () {
      var pos = el.selectionStart;
      el.value = el.value.toUpperCase();
      el.setSelectionRange(pos, pos);
    });
  }

  // Shows a live password strength hint.
  function setupStrength(el) {
    var hint = document.getElementById(el.getAttribute('data-strength'));
    if (!hint) { return; }
    el.addEventListener('input', function () {
      var v = el.value;
      var score = (v.length >= 8) + (/[A-Za-z]/.test(v) && /\d/.test(v)) + (v.length >= 12 || /[^A-Za-z0-9]/.test(v));
      hint.textContent = !v ? 'At least 8 characters, with a letter and a number.'
        : ['Too short so far.', 'Getting there. Add a number or a letter.', 'Good password.', 'Strong password.'][score];
      hint.setAttribute('data-score', String(score));
    });
  }

  // Updates the add book cover preview as the title, author and category change.
  function setupCoverPreview(preview) {
    var form = preview.closest('form') || document.querySelector(preview.getAttribute('data-form'));
    if (!form) { return; }
    var title = preview.querySelector('[data-preview-title]');
    var author = preview.querySelector('[data-preview-author]');
    var label = preview.querySelector('[data-preview-label]');
    function update() {
      title.textContent = form.elements.title.value.trim() || 'Your book title';
      author.textContent = form.elements.author.value.trim() || 'Author name';
      var sel = form.elements.category_id;
      label.textContent = sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : 'Category';
      var len = title.textContent.length;
      preview.style.setProperty('--title-size', len <= 10 ? '2.1rem' : len <= 22 ? '1.7rem' : '1.35rem');
    }
    ['title', 'author', 'category_id'].forEach(function (name) {
      form.elements[name].addEventListener('input', update);
      form.elements[name].addEventListener('change', update);
    });
    update();
  }

  each(document.querySelectorAll('form[data-validate]'), setupValidation);
  each(document.querySelectorAll('[data-tabs]'), setupTabs);
  each(document.querySelectorAll('form[data-confirm]'), setupConfirm);
  each(document.querySelectorAll('textarea[maxlength][data-count]'), setupCounter);
  each(document.querySelectorAll('[data-uppercase]'), setupUppercase);
  each(document.querySelectorAll('[data-strength]'), setupStrength);
  each(document.querySelectorAll('[data-cover-preview]'), setupCoverPreview);
})();
