// Modern Enhancement 4: useFetch(), a composable shared by <BookSearch>, <LoansPanel> and <RoomSlots>.
//
// Vue concepts used in this file
// - Composable: a plain function, called inside a component's setup(), that creates reactive state
//   and logic and hands it back. Each call gets its OWN state, so two components (or two uses in one
//   component) never share a loading flag by accident. It replaces the same fetch, cancel and loading
//   code that steps 1 to 3 each wrote by hand.
// - ref(): loading, error, status and data are refs, so a template that shows them updates by itself.
// - onBeforeUnmount(): a lifecycle hook registered from inside the composable; if the component goes
//   away (for example boot.js restores the Base page), a request still on its way is cancelled.
//
// run(path, options) calls BookNestVue.requestJson() (boot.js) and resolves to:
//   { ok, status, data }  when the server answered with JSON (also for 401, 403, 422: the caller shows data.error)
//   null                  when this request was cancelled because a newer run() started
// and rejects when the network failed or the reply was not JSON.
(function () {
  'use strict';

  // Creates one independent "request slot" with its own reactive state.
  function useFetch() {
    var Vue = window.Vue;
    var loading = Vue.ref(false);
    var error = Vue.ref('');
    var status = Vue.ref(0);
    var data = Vue.ref(null);
    var controller = null;

    // Starts a request, cancelling the previous one from this same slot.
    function run(path, options) {
      if (controller) { controller.abort(); }
      controller = window.AbortController ? new AbortController() : null;
      var mine = controller;
      loading.value = true;
      error.value = '';
      var opts = Object.assign({}, options || {}, { signal: mine ? mine.signal : undefined });
      return window.BookNestVue.requestJson(path, opts).then(function (res) {
        if (mine !== controller) { return null; }   // a newer request has taken over
        status.value = res.status;
        data.value = res.data;
        error.value = res.ok ? '' : (res.data.error || 'The request was refused.');
        loading.value = false;
        return res;
      }, function (err) {
        if (err && err.name === 'AbortError') { return null; }
        if (mine === controller) {
          loading.value = false;
          error.value = 'Could not reach the library. Check your connection and try again.';
        }
        throw err;
      });
    }

    // Cancels any request still on its way.
    function cancel() {
      if (controller) { controller.abort(); controller = null; }
    }

    Vue.onBeforeUnmount(cancel);
    return { loading: loading, error: error, status: status, data: data, run: run, cancel: cancel };
  }

  if (window.BookNestVue) { window.BookNestVue.useFetch = useFetch; }
})();
