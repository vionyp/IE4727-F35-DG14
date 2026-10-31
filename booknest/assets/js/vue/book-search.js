// Modern Enhancement 1: <BookSearch>, live search on the Browse page (catalogue.php).
//
// Vue concepts used in this file
// - Component: BookSearch is an options object with props, emits, setup() and a template string.
//   Vue compiles the template in the browser (vue.global.prod.js includes the compiler), so there
//   is no build step.
// - props: the starting search, category and sort come from the page address and are passed in
//   once by createApp(); the component copies them into its own state.
// - emits: 'ready' after the first successful load (boot.js then hides the Base grid), 'failed'
//   if the first load fails (the Base grid stays), and 'searched' each time a search finishes.
// - ref(): reactive state (query, category, books...). Changing a ref re-renders what uses it.
// - watch() with a 300 ms debounce: typing does not call the server on every key; the request is
//   sent 300 ms after the last change, and an older request still on its way is cancelled.
// - computed(): visibleBooks is derived from the books plus the category, availability and sort
//   filters; it recalculates by itself when any of them change, with no extra request.
// - v-model: two-way binding of the search box, sort select and "on the shelf" checkbox.
// - v-for with :key="b.id": one card per book; the key lets Vue reuse each card when the list changes.
// - v-if / v-else-if: loading, empty and error states.
// Escaping: every value is shown with {{ }} or a bound attribute, which Vue escapes. No v-html.
// Server rules: the search itself runs in PHP (api/books.php); this file only filters and sorts
// the results it was given. With JavaScript off the Base page works as before.
(function () {
  'use strict';

  var SORTS = [
    { key: 'title', label: 'Title A to Z' },
    { key: 'newest', label: 'Newest first' },
    { key: 'rating', label: 'Highest rated' },
    { key: 'available', label: 'On the shelf now first' }
  ];

  // Orders a copy of the list the same way catalogue.php does for each sort key.
  function sortBooks(list, key) {
    var byTitle = function (a, b) { return a.title.localeCompare(b.title); };
    var copy = list.slice();
    if (key === 'newest') {
      copy.sort(function (a, b) { return a.added < b.added ? 1 : (a.added > b.added ? -1 : 0); });
    } else if (key === 'rating') {
      copy.sort(function (a, b) { return (b.rating - a.rating) || byTitle(a, b); });
    } else if (key === 'available') {
      copy.sort(function (a, b) { return ((b.available > 0) - (a.available > 0)) || byTitle(a, b); });
    } else {
      copy.sort(byTitle);
    }
    return copy;
  }

  var BookSearch = {
    name: 'BookSearch',
    props: {
      initialQuery: { type: String, default: '' },
      initialCategory: { type: String, default: '' },
      initialSort: { type: String, default: '' }
    },
    emits: ['ready', 'failed', 'searched'],

    setup: function (props, ctx) {
      var Vue = window.Vue;
      var api = window.BookNestVue;

      var query = Vue.ref(props.initialQuery);
      var category = Vue.ref(props.initialCategory);
      var sortKnown = SORTS.some(function (s) { return s.key === props.initialSort; });
      var sort = Vue.ref(sortKnown ? props.initialSort : (props.initialQuery ? 'rating' : 'title'));
      var sortChosen = sortKnown;   // like catalogue.php: until a sort is chosen, a search sorts by rating
      var onlyAvailable = Vue.ref(false);
      var books = Vue.ref([]);
      var categories = Vue.ref([]);
      var loading = Vue.ref(true);
      var error = Vue.ref('');
      var searchedFor = Vue.ref(props.initialQuery);
      var started = false;
      var timer = 0;
      var controller = null;

      // Asks api/books.php for the books matching q; cancels the previous request first.
      function load(q) {
        if (controller) { controller.abort(); }
        controller = window.AbortController ? new AbortController() : null;
        var mine = controller;
        loading.value = true;
        api.requestJson('api/books.php', { query: { q: q }, signal: mine ? mine.signal : undefined })
          .then(function (res) {
            if (!res.ok) { throw new Error(res.data.error || 'Search failed.'); }
            books.value = res.data.books;
            categories.value = res.data.categories;
            if (category.value && !categories.value.some(function (c) { return c.slug === category.value; })) {
              category.value = '';   // unknown category in the address, as catalogue.php ignores it
            }
            searchedFor.value = q;
            error.value = '';
            loading.value = false;
            ctx.emit('searched', q);
            if (!started) { started = true; ctx.emit('ready'); }
          })
          .catch(function (err) {
            if (err && err.name === 'AbortError') { return; }
            loading.value = false;
            if (!started) { ctx.emit('failed'); return; }
            error.value = 'Live search is not available right now. Use the search box at the top of the page instead.';
          });
      }

      // Searches at once (Enter key), skipping the debounce.
      function searchNow() {
        clearTimeout(timer);
        load(query.value.trim());
      }

      // Clears the search and both filters.
      function clearAll() {
        query.value = '';
        category.value = '';
        onlyAvailable.value = false;
      }

      // Debounced watch: wait 300 ms after the last key press, then search.
      Vue.watch(query, function (value) {
        clearTimeout(timer);
        loading.value = true;
        timer = setTimeout(function () { load(value.trim()); }, 300);
      });

      // The filtered and sorted list that the template shows.
      var visibleBooks = Vue.computed(function () {
        var list = books.value.filter(function (b) {
          return (!category.value || b.category === category.value) && (!onlyAvailable.value || b.available > 0);
        });
        return sortBooks(list, sort.value);
      });

      var categoryName = Vue.computed(function () {
        var c = categories.value.filter(function (x) { return x.slug === category.value; })[0];
        return c ? c.name : '';
      });

      // One sentence for the count line, read out by screen readers when it changes.
      var summary = Vue.computed(function () {
        if (loading.value) { return 'Searching...'; }
        var n = visibleBooks.value.length;
        return n + (n === 1 ? ' book' : ' books')
          + (searchedFor.value ? ' match "' + searchedFor.value + '"' : '')
          + (categoryName.value ? ' in ' + categoryName.value : '')
          + (onlyAvailable.value ? ', on the shelf now' : '') + '.';
      });

      // Follows the PHP default sort (rating for a search, title otherwise) until the visitor picks one.
      Vue.watch(searchedFor, function (q) {
        if (!sortChosen) { sort.value = q ? 'rating' : 'title'; }
      });

      // Remembers that the visitor chose a sort, so searching no longer changes it.
      function chooseSort() { sortChosen = true; }

      // Keeps the address, the page heading and the tab title in step, so a reload (even with
      // JavaScript off) shows the same search on the server.
      Vue.watch([searchedFor, category, sort], function () {
        var params = new URLSearchParams();
        if (searchedFor.value) { params.set('q', searchedFor.value); }
        if (category.value) { params.set('category', category.value); }
        if (sort.value !== (searchedFor.value ? 'rating' : 'title')) { params.set('sort', sort.value); }
        var qs = params.toString();
        window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
        var h1 = document.querySelector('.page-head h1');
        var heading = searchedFor.value ? 'Results for "' + searchedFor.value + '"' : (categoryName.value || 'Browse the collection');
        if (h1) { h1.textContent = heading; }
        document.title = (searchedFor.value ? 'Search: ' + searchedFor.value : (categoryName.value || 'Browse')) + ' | BookNest';
      });

      Vue.onMounted(function () { load(query.value.trim()); });

      return {
        query: query, category: category, sort: sort, onlyAvailable: onlyAvailable, sorts: SORTS,
        categories: categories, loading: loading, error: error, visibleBooks: visibleBooks, summary: summary,
        searchNow: searchNow, clearAll: clearAll, chooseSort: chooseSort, emptyImage: api.url('assets/img/empty-shelf.svg')
      };
    },

    template:
      '<section class="vs" aria-labelledby="vs-title">' +
      '  <h2 id="vs-title" class="visually-hidden">Search and filter the catalogue</h2>' +
      '  <div class="vs-controls">' +
      '    <div class="field vs-query">' +
      '      <label for="vs-q">Search as you type</label>' +
      '      <input id="vs-q" type="search" v-model="query" @keydown.enter.prevent="searchNow" maxlength="100"' +
      '             placeholder="Title, author, ISBN or serial number" autocomplete="off">' +
      '    </div>' +
      '    <div class="field vs-sort">' +
      '      <label for="vs-sort">Sort by</label>' +
      '      <select id="vs-sort" v-model="sort" @change="chooseSort"><option v-for="s in sorts" :key="s.key" :value="s.key">{{ s.label }}</option></select>' +
      '    </div>' +
      '    <label class="check vs-avail"><input type="checkbox" v-model="onlyAvailable"> On the shelf now only</label>' +
      '  </div>' +
      '  <div class="chips" role="group" aria-label="Categories">' +
      '    <button type="button" class="chip" :class="{ \'is-active\': !category }" :aria-pressed="!category ? \'true\' : \'false\'" @click="category = \'\'">All</button>' +
      '    <button v-for="c in categories" :key="c.slug" type="button" class="chip" :class="{ \'is-active\': category === c.slug }"' +
      '            :aria-pressed="category === c.slug ? \'true\' : \'false\'" @click="category = c.slug">{{ c.name }}</button>' +
      '  </div>' +
      '  <p class="lede vs-count" aria-live="polite">{{ summary }}</p>' +
      '  <p v-if="error" class="notice notice-alert" role="alert">{{ error }}</p>' +
      '  <div v-if="visibleBooks.length" class="card-grid" :class="{ \'is-loading\': loading }" :aria-busy="loading ? \'true\' : \'false\'">' +
      '    <article v-for="b in visibleBooks" :key="b.id" class="card">' +
      '      <a class="card-link" :href="b.url">' +
      '        <span class="card-cover">' +
      '          <img :src="b.cover" :srcset="b.cover !== b.coverLarge ? b.cover + \' 1x, \' + b.coverLarge + \' 2x\' : null" :alt="b.coverAlt"' +
      '               width="400" height="600" loading="lazy" decoding="async">' +
      '          <span class="card-overlay" aria-hidden="true"><span class="card-hook">{{ b.hook }}</span><span class="card-cta">View details</span></span>' +
      '        </span>' +
      '        <span class="card-title">{{ b.title }}</span>' +
      '      </a>' +
      '      <p class="card-author">{{ b.author }}</p>' +
      '      <p class="card-meta"><span class="avail card-avail" :class="b.availability.class">{{ b.availability.text }}</span>' +
      '        <span v-if="b.availability.note" class="avail-note">{{ b.availability.note }}</span></p>' +
      '    </article>' +
      '  </div>' +
      '  <div v-else-if="!loading" class="empty-state">' +
      '    <img :src="emptyImage" alt="" width="220" height="140">' +
      '    <h2>Nothing matches</h2>' +
      '    <p>Check the spelling, try an author\'s surname, or clear the filters to see every book.</p>' +
      '    <div class="empty-actions"><button type="button" class="btn btn-secondary btn-sm" @click="clearAll">Clear search and filters</button></div>' +
      '  </div>' +
      '</section>'
  };

  window.BookNestVue && (window.BookNestVue.components.BookSearch = BookSearch);

  // Mounts <BookSearch> on the Browse page in place of the Base chips, toolbar and grid.
  window.BookNestVue && window.BookNestVue.ready(function (Vue) {
    var pick = function (sel) { return document.querySelector(sel); };
    var base = [pick('#result-count'), pick('main nav.chips[aria-label="Categories"]'), pick('main .toolbar'),
      pick('[data-filter-grid]'), pick('[data-filter-empty]'), pick('main .container > .empty-state')].filter(Boolean);
    var anchor = pick('main nav.chips[aria-label="Categories"]') || pick('main .toolbar');
    if (!anchor) { return; }
    var params = new URLSearchParams(window.location.search);
    var pageQuery = (params.get('q') || '').trim();
    // The member serial panel answers the search PHP ran; hide it while a different search is shown.
    var serialBits = [].slice.call(document.querySelectorAll('main .serial-panel, main .container > p.notice'));
    window.BookNestVue.enhance(Vue, BookSearch, {
      initialQuery: pageQuery,
      initialCategory: params.get('category') || '',
      initialSort: params.get('sort') || '',
      onSearched: function (q) { serialBits.forEach(function (el) { el.hidden = q !== pageQuery; }); }
    }, base, anchor);
  });
})();
