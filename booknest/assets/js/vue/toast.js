// Modern Enhancement 7b: toasts, a short "done" note in the corner after an action ("Borrowed The Hobbit").
//
// Vue concepts used in this file
// - reactive(): one small store object, { items: [] }, shared by every component on the page. Any
//   component calls BookNestVue.toast.show('...') and every <ToastHost> that reads the store updates.
//   This is the "shared store" pattern without a library such as Pinia.
// - <Teleport to="body">: <ToastHost> is written inside the component that uses it (the borrow box,
//   the loans panel), but Vue draws its markup at the end of <body>. So the toast is never clipped by
//   the panel's layout and is always fixed to the corner of the screen.
// - <transition-group>: toasts slide in and fade out ('toast-*' classes in vue.css); off when the
//   visitor asks for reduced motion.
// - Lifecycle: each toast removes itself after 5 seconds; onBeforeUnmount() clears the timers.
// Accessibility: the panel's own message (role="status", focused) is what screen readers announce.
// The toast repeats it for sighted users only, so it is aria-hidden to avoid saying the same thing twice.
(function () {
  'use strict';

  var store = null;
  var timers = {};
  var nextId = 1;
  var LIFETIME = 5000;

  // Creates the store the first time it is needed (Vue may still be loading when this file runs).
  function getStore() {
    if (!store) { store = window.Vue.reactive({ items: [] }); }
    return store;
  }

  // Removes one toast.
  function dismiss(id) {
    var s = getStore();
    window.clearTimeout(timers[id]);
    delete timers[id];
    var i = s.items.findIndex(function (t) { return t.id === id; });
    if (i >= 0) { s.items.splice(i, 1); }
  }

  // Shows a toast; kind is 'ok' or 'error'. At most three are shown, the oldest goes first.
  function show(text, kind) {
    if (!window.Vue || !text) { return; }
    var s = getStore();
    var id = nextId++;
    s.items.push({ id: id, text: text, kind: kind || 'ok' });
    while (s.items.length > 3) { dismiss(s.items[0].id); }
    timers[id] = window.setTimeout(function () { dismiss(id); }, LIFETIME);
  }

  var ToastHost = {
    name: 'ToastHost',
    setup: function () {
      var Vue = window.Vue;
      Vue.onBeforeUnmount(function () { Object.keys(timers).forEach(function (id) { dismiss(Number(id)); }); });
      return { store: getStore(), dismiss: dismiss };
    },
    template:
      '<teleport to="body">' +
      '  <transition-group tag="ul" name="toast" class="vt" aria-hidden="true">' +
      '    <li v-for="t in store.items" :key="t.id" class="vt-item" :class="\'is-\' + t.kind" @click="dismiss(t.id)">{{ t.text }}</li>' +
      '  </transition-group>' +
      '</teleport>'
  };

  if (window.BookNestVue) {
    window.BookNestVue.toast = { show: show, dismiss: dismiss };
    window.BookNestVue.components.ToastHost = ToastHost;
  }
})();
