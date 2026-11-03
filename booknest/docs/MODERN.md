# BookNest: Modern Enhancement (Vue 3)

The Modern Enhancement adds Vue 3 on top of the finished Base Version, on the branch `modern-vue`.
The Base Version (branch `main`) is unchanged: same 10 pages, same `process/` endpoints, same rules,
same 3-layer validation (HTML attributes, `forms.js`, PHP).

## 1. Rules this enhancement follows

| Rule | How it is kept |
|---|---|
| Vue 3 only | No React, no Bootstrap or Tailwind, no other libraries. |
| No CDN, no build step | `assets/vendor/vue.global.prod.js` (Vue 3.4.38, includes the template compiler) is a local file. Templates are strings compiled in the browser. |
| Progressive enhancement | PHP prints the full page first. Vue replaces a section only after its first data load succeeds. If Vue cannot load, the API fails or JavaScript is off, the Base page stays and works. |
| PHP is the source of truth | Every action goes to `api/`, which calls the **same** functions as `process/` (`borrow_book()`, `join_queue()`, `renew_loan()`, `return_loan()`...). Vue only shows what PHP answered, word for word. |
| JSON only in `api/` | `api/*.php` with `includes/api.php`: method check, sign in check, CSRF check (same token as the forms), prepared statements, JSON escaped with `JSON_HEX_*`, `nosniff`, `no-store`. |
| No new pages | Still 10 pages. Each enhanced page changed one line: its `$scripts` list. |

## 2. How a page is enhanced

```
PHP page (full HTML)  ->  boot.js loads vendor/vue.global.prod.js and css/vue.css
                      ->  the page script calls BookNestVue.enhance(Vue, Component, props, baseElements)
                      ->  the component loads its data from api/...
                            ok     -> emits 'ready'  -> Base elements hidden, Vue shown
                            failed -> emits 'failed' -> Vue removed, Base stays
                      ->  any later Vue error -> errorHandler puts the Base elements back
```

## 3. Features

| # | Page | Component(s) | What it adds | Endpoint |
|---|---|---|---|---|
| 1 | Browse (`catalogue.php`) | `<BookSearch>` | Live search (300 ms debounce), category chips and "On the shelf now only" filter without a request, sort, empty state | `api/books.php` (GET) |
| 2 | My Account (`account.php`) | `<LoansPanel>` | Renew and Return in place, computed totals, header badge and tiles follow, returned row slides out | `api/loans.php` (GET, POST) |
| 3 | Study Rooms (`rooms.php`) | `<RoomSlots>`, `<SlotCell>` | One room's half hour grid, date and room chosen with v-model, minutes left against the 60 minute cap, clicking a cell fills the Base booking form | `api/rooms.php` (GET) |
| 4 | all of the above | `useFetch()` composable | One shared request helper: loading and error state, cancels the older request, cancels on unmount | (none) |
| 5 | Book page (`book.php`) | `<BorrowBox>` | Borrow with a date picker and computed due date, join or leave the queue, save to shelf, all without reloading; pause reasons shown | `api/book.php` (GET, POST) |
| 6 | Add a book (`add-book.php`) | `<SerialLookup>`, `<SerialStatus>`, `useSerialCheck()` | Serial number looked up while typing; "Use this number" fills the form; the form field says free or taken at once | `api/serial.php` (GET) |
| 7 | Book page, Add a book, My Account | `<NoticeBox>`, `<ToastHost>` | Shared notice with slots; a toast in the corner after an action | (none) |

## 4. Vue concepts and where they are used

Every Vue file starts with a comment block that explains the concepts it uses.

| Concept | Files |
|---|---|
| Component (options object with `setup()` and a template) | every file in `assets/js/vue/` |
| `props` / `emits` | all components; `<SlotCell>` emits `pick`, `<SerialLookup>` emits `use` |
| `ref()` / `computed()` | all components (for example `mode` and `chosen` in `borrow-box.js`, `totalFee` in `loans-panel.js`) |
| `watch()` (debounced, `immediate`) | `book-search.js`, `room-slots.js`, `serial-lookup.js`, `borrow-box.js` |
| `v-model` (`.number`, `.trim`) | search box and sort, room and date selects, collection date, serial box |
| `v-for` with `:key`, `v-if` / `v-else-if` | all lists and states |
| Child component | `<SlotCell>` in `<RoomSlots>`; `<NoticeBox>` and `<ToastHost>` in `<BorrowBox>` |
| Slots (default and named) | `<NoticeBox>` (`notice-box.js`) |
| `<transition-group>` | returned loans (`loans-panel.js`), toasts (`toast.js`) |
| `<Teleport>` | `<ToastHost>` draws the toasts at the end of `<body>` (`toast.js`) |
| Shared store with `reactive()` | toast store (`toast.js`) |
| Composables | `useFetch()` (`use-fetch.js`), `useSerialCheck()` (`serial-lookup.js`) |
| Lifecycle hooks | `onMounted`, `onBeforeUnmount` (cancel requests, clear timers, remove listeners on Base inputs) |
| `nextTick()` | focus moves to messages, the date picker and the confirm button |

## 5. Files

| File | Lines | Purpose |
|---|---|---|
| `assets/vendor/vue.global.prod.js` | (147 KB) | Vue 3.4.38, local copy |
| `assets/js/vue/boot.js` | 139 | Loads Vue, `requestJson()`, `enhance()` |
| `assets/js/vue/use-fetch.js` | 63 | `useFetch()` composable |
| `assets/js/vue/book-search.js` | 243 | Feature 1 |
| `assets/js/vue/loans-panel.js` | 228 | Feature 2 |
| `assets/js/vue/slot-cell.js`, `room-slots.js` | 52, 245 | Feature 3 |
| `assets/js/vue/borrow-box.js` | 231 | Feature 5 |
| `assets/js/vue/serial-lookup.js` | 227 | Feature 6 |
| `assets/js/vue/notice-box.js`, `toast.js` | 59, 67 | Feature 7 |
| `assets/css/vue.css` | 94 | Styles, loaded only when Vue runs; reuses the Base tokens |
| `includes/api.php` | 63 | JSON helpers: `api_send`, `api_error`, `api_method`, `api_user`, `api_csrf` |
| `api/books.php`, `loans.php`, `rooms.php`, `book.php`, `serial.php` | 47, 87, 53, 113, 49 | The endpoints |
| `api/.htaccess` | 2 | No directory listing |
| `tools/api-test.php` | 405 | Endpoint tests over HTTP |
| `tools/vue-browser-test.mjs` | 819 | Browser tests in headless Edge |

Changed Base files: `catalogue.php`, `account.php`, `rooms.php`, `book.php`, `add-book.php`, one
`$scripts` line each. Not changed: `process/`, `includes/` (except the new `api.php`), `sql/`, `config/`.

## 6. Tests (3 November 2026, each suite on a fresh seed)

| Suite | Command | Result |
|---|---|---|
| Base end to end | `C:\xampp\php\php.exe tools\e2e-test.php` | **69 of 69**, 0 PHP errors |
| API | `C:\xampp\php\php.exe tools\api-test.php` | **44 of 44** |
| Browser | `node tools\vue-browser-test.mjs` | **68 of 68** |

The tests change data (they borrow, renew, return), so re-import `sql/schema.sql` and
`sql/seed.sql` afterwards for a clean demo.

What the browser tests cover for every enhanced page:

- the Vue screen replaces the Base section, and the action works without a page reload;
- **JavaScript off**: the PHP page and forms work;
- **Vue file blocked**: the Base page and its vanilla JavaScript work;
- **server refusals** (a stale second tab, an expired CSRF token, signed out meanwhile): PHP's
  message is shown as sent, nothing changes;
- **no console errors**, and only the expected 4xx answers reach the network log.

API tests cover, for every endpoint: wrong method (405), not signed in (401), wrong or missing
CSRF token (403), unknown action (400), rule refusals (422 with PHP's message), unknown or injected
ids (404 or 422), and that the PHP page still prints its Base markup.

Not run for the Modern Enhancement: Lighthouse, the W3C validator, a screen reader, a real phone.

## 7. Differences with and without Vue

| Page | Base Version | With Vue |
|---|---|---|
| Browse | Search and sort reload the page; the refine box filters the cards on screen | Results update while typing; extra "On the shelf now only" filter. Breadcrumbs are not updated while searching. |
| My Account | Renew and Return reload, message in the flash | Done in place, message inside the panel and a toast. The queue and history tables update after a reload. |
| Study Rooms | Map and timetable of every room, date chips reload | One room at a time with dropdowns; the map and date chips still reload. |
| Book page | Borrow opens `borrow.php`; queue and shelf reload | Borrow, queue and shelf happen in place. `borrow.php` still exists for the Base flow. |
| Add a book | Look up button reloads the page | Looked up while typing; the form field shows free or taken. |
| All | Nothing to wait for | The PHP version shows for a moment, then Vue takes over. Refused actions appear as red 4xx lines in DevTools (they are expected answers, not errors). |
