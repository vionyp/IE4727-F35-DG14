# Changelog

## 2026-10-28: Borrowing limits, renewals, reminders, settling fees, submission emails (Viony)

Extends F5 Borrowing, F8 Add books and F9 Admin. No new feature number and no new page: the site
still has 10 features and 10 pages.

- **Borrowing limits (FR-45):** new loans pause while a member has an overdue book, already holds
  6 open loans (`MAX_LOANS`), or owes S$5.00 or more in unpaid fees (`FEE_BLOCK_LIMIT`). Checked by
  `borrow_blocks()` inside the borrowing transaction, after the member row is locked, so two quick
  requests cannot pass the limit together. Borrow, the book page and My Account explain which rule
  applies. Joining a queue still works.
- **Renewals (FR-46):** "Renew to [date]" on My Account adds another 14 days, once per loan
  (`MAX_RENEWALS`), unless the loan is overdue or someone is waiting. Uses the `renewals` column that
  was already in the schema. `renew_problem()` is the single rule used by both the page and the
  server; `renew_loan()` locks the book row so nobody can join the queue mid-renewal. Emailed.
- **Reminders (FR-47):** loans due today or within 2 days (`DUE_SOON_DAYS`) are named on Home and
  My Account; overdue loans are named on Home. Shown on the page, no scheduler needed.
- **Settling fees (FR-49):** new "Late fees to settle" table on the admin dashboard lists unpaid
  fees on returned books, with "Mark as paid". This sets `fee_cleared_at` (the column existed but
  nothing wrote it) and emails a receipt. Loans still out are refused, because their fee is still
  growing.
- **Submission emails (FR-48):** approving or rejecting a member's book now emails that member.
- Files: `includes/loans.php`, `process/loan.php`, `process/admin-action.php`, `account.php`,
  `admin.php`, `borrow.php`, `book.php`, `index.php`, `config/config.php` (four new lending
  constants), `assets/css/pages.css` (two notice styles). No schema change, no AJAX, JSON or libraries.
- Tests T95 to T105 added to `tools/e2e-test.php`; e2e **69 of 69**, 0 PHP errors.

## 2026-10-28: Onboarding tour for new members, part of F7 Accounts (Mathew)

Added to F7 (registration), not as an eleventh feature, so the site still has 10 features.

- **When:** straight after registering (`process/register.php` sets a one time session flag; the
  header passes it to the page). Members can replay it from My Account ("Take the tour").
- **Steps (9, plus a welcome and a closing card):** Browse, pick a book that is on the shelf, the
  book's actions, Borrow, the collection date, Confirm loan, My loans, returning a book, Study Rooms.
- **Freeze:** a dim layer covers the page with a cut out around the highlighted element. On click
  steps only that element works; on information steps nothing on the page works and the card has
  Next. Clicking a frozen area gently shakes the card. Tab stays inside the target and the card.
- **Skip:** "Skip tour" on every step, "Skip for now" on the welcome card, and Escape. The tour
  never confirms a loan for the member: the Confirm loan button is paused during its step.
- **Across pages:** progress is a small cookie (`bn_tour`, `TOUR_COOKIE` in `config/config.php`)
  holding only a step number; anything else is ignored. Signing out deletes it. If the member
  leaves the tour's path, a "Tour paused" card offers to continue or end it.
- Phones: the card docks to the top or bottom edge and the tab bar items are used as targets.
- Respects `prefers-reduced-motion`. Without JavaScript nothing changes (progressive enhancement).
- Files: new `assets/js/tour.js`; tour styles at the end of `components.css` (still four
  stylesheets); small changes to `includes/header.php`, `process/register.php`,
  `process/logout.php`, `account.php`, `config/config.php`. No AJAX, JSON or libraries.
- Tests T91 to T94 added to `TEST_LOG.md` as manual browser checks, still to be run.

## 2026-10-21: Paige removed; back to 10 features (Mathew)

The brief allows 10 features, so the help assistant is gone. (A guided tour was built on
20 October to replace her and removed again the next day for the same reason.)

- Removed `includes/assistant.php`, `process/assistant.php`, `tools/assistant-test.php`, the
  `assistant_log` table and its seed rows, the admin dashboard panel, her CSS and JavaScript,
  and her footer link. The phone tab bar now has four tabs: Home, Browse, Rooms, Account.
- REQUIREMENTS lists features F1 to F10 (FR-01 to FR-43); FR-04 (no rating on cards) and FR-25
  (the Crescent map) updated to match the site.
- Tests: Paige's T52 to T58 retired, T90 checks she is gone; e2e **58 of 58**.

## 2026-10-19: "Nocturne Bibliophilia" visual redesign from the Google Stitch mock up (Mathew)

Design only. No page, feature, form, query or rule changed; the backend is untouched.

- **Type:** Noto Serif for headings and Inter for the interface, self-hosted in `assets/fonts/`
  (SIL Open Font License, licence files included; no font CDN). The two fonts the first screen
  needs are preloaded in `includes/header.php`.
- **Colour:** page titles and the wordmark in headline gold `#F3CF8E` with a soft glow (was a
  gradient fill); calmer background light; panels with a subtler edge.
- **Components:** eyebrow labels with a glowing dot; primary button gradient with an amber
  glow; secondary buttons turn gold on hover; new teal outline button; inputs on the raised
  surface with an amber focus ring; availability shown as tinted status chips; loans count as a
  bubble on the books icon; Paige's launcher is a quiet pill with gold lettering.
- **Cards and rows:** 8px covers with a hairline border, lift and scale with an amber halo on
  hover; cards show title, author and availability (rating stays on the book page and in the
  featured panel). Row titles have a small amber icon and a "View all" link on the right.
  Phones show 140px cards.
- **Home:** the featured book is now a rounded panel (italic quoted hook, cover on the right on
  wide screens); the three steps sit in one bento strip; the sign in card has a header with an
  icon; the study room teaser has a stat box and a teal button instead of the room image; the
  visit panel shows a live status, SGT chip, address and three hour tiles instead of a 7 row
  table (hours are the same every day).
- **Footer:** brand column with logo and a one line description.
- **Phones:** a bottom tab bar (Home, Browse, Rooms, Paige, Account / Sign in) under 1024px.
- Checks: e2e 64/64, assistant 42/42, html-validate clean, no forbidden technology,
  Lighthouse home accessibility 100, best practices 100, performance about the same as before
  the redesign (the infinite "breathing" animation was rebuilt with transform and opacity
  after it cost 3.5 s of blocking time).

## 2026-10-13: The Crescent, a library map with 50 study rooms (Mathew)

Replaces the first map (three squares with two rooms each, 7 October), which looked like a
spreadsheet rather than a building.

- **50 study rooms** instead of 6, in four arcs: A Focus pods (20 rooms, 2 seats), B Study rooms
  (15, 4 seats), C Media rooms (10, 4 seats) and D Project rooms (5, 6 seats). Rooms are named by
  arc and number, for example `A07`. Two are closed for maintenance in the seed data.
- **The map** is one inline SVG of a crescent moon shaped building around a "Moon Garden". Each
  room is a small rounded tile on its arc. `map_arcs()` and `map_position()` in
  `includes/rooms.php` place them: an arc has a radius and an opening angle, and `cos` / `sin`
  turn each room's angle into x and y. Inner arcs are shorter, which gives the moon its taper.
- **Colour by time.** With 50 rooms the useful question is "which rooms are free at 3 pm?", so
  the map shows every room at one chosen time (`?at=15:00`, a select box beside the map). The
  states are the same five as the timetable: free, taken, yours, past, closed. Each also differs
  by outline or symbol, not only by colour, and has a tooltip and screen reader label.
- **A free room is a link** to `rooms.php?date=..&at=..&room=..&start=..#book`, which opens the
  booking form filled in. Booking is still three clicks: Study Rooms, a room, Confirm.
- **Timetable** (the page's required table) now shows one arc at a time, chosen with chips, so
  it is at most 20 rows instead of 50. The room list in the booking form is grouped by arc.
- **Data:** 72 generated members join the 8 named demo members so the rooms look used (about 75
  bookings a day). Loans and queues still use only the named members, so nothing else changed.
- Admin: the room chart shows the 8 busiest rooms; the open/close list is more compact.
- No JavaScript was added (the time select reuses the existing auto submit), and no image files.
- Tests T80 to T84: all 50 rooms drawn, free room links to the form, **rooms drawn as taken
  match the bookings in the database**, closed room is not a link, timetable per arc, bad
  address values fall back safely. The suite is now 64 checks, all passing.
- Lighthouse on the page: accessibility 100, best practices 100; performance 99 desktop, 75 on
  the slow phone profile (the page now draws 50 rooms and a timetable).

## 2026-10-02: Borrowing instead of buying (Phase 1 of the borrowing brief)

Why: our course theme is *Online Library*. Selling books did not fit it, so the core transaction is
now a loan. This changes the data model and several pages, not only the wording.

- **Rules** (all enforced in PHP, constants in `config/config.php`): loans last `LOAN_DAYS` = 14 days
  from a collection date the member picks (today up to `COLLECT_AHEAD_DAYS` = 7 days ahead); one open
  loan per title; when no copy is on the shelf, members join a queue; a returned copy is offered to
  the first person in line and held for `QUEUE_HOLD_DAYS` = 2 days; late returns cost
  `LATE_FEE_PER_DAY` = S$0.50 a day after the due date, worked out live from the dates.
- **Database:** `orders` and `order_items` replaced by `loans` and `book_queue`. `books.stock` now
  means copies owned; copies on the shelf are calculated (owned minus open loans minus held copies).
  `books.price` stays as a staff-only replacement value and defaults to 0. `loans.renewals` and
  `loans.fee_cleared_at` are created now for the next two phases and are not used yet.
- **No price anywhere for members.** Cards, the book page, the billboard, the reader, the serial
  lookup and Paige show availability instead: "Available to borrow", "Due back in X days",
  "X days overdue" (red), "Borrowed until [date]", "N people waiting", "You are #2 in line".
- **Pages:** `checkout.php` became `borrow.php` (pick a collection date, confirm), so the site still
  has ten pages. `book.php` offers Borrow, Join the queue, Leave the queue or Borrow your held copy.
  `account.php` lists loans with due dates, fees so far and a Return button, the queue places,
  and recent returns; its tiles show books on loan and fees owed. `admin.php` replaces revenue with
  books on loan, overdue loans, late fees outstanding, people in queues, most borrowed books, loans
  by category, longest queues and the overdue list. The header cart became a "My loans" count.
- **Code:** new `includes/loans.php` (rules, availability, fees, queue) and `process/loan.php`
  (borrow, queue, leave, return). Removed `includes/cart.php`, `process/cart.php`,
  `process/checkout.php`, the delivery toggle in `forms.js` and the checkout styles. Every action
  that changes copies on the shelf locks the book row (`SELECT ... FOR UPDATE`). A small
  `loans_sync()` runs once per request, because XAMPP has no scheduler, to move loans from reserved
  to active to overdue and to expire holds.
- **Admin:** copies can no longer be set below the copies out or held; new copies go straight to
  the queue; a book that has ever been borrowed cannot be deleted. The member Add a book form no
  longer asks for a price.
- **Emails:** loan confirmed (with due date), your reserved book is ready, hold ended, returned
  (with any fee), reservation cancelled. Still only to `@localhost`.
- **Paige:** order, payment, refund, delivery and checkout answers became loans, late fees, queue,
  return, collection and how to borrow. Quick buttons updated. She still never shows one member's
  loans to anyone else.
- **Seed:** `tools/build-seed.php` now writes 68 loans (57 returned over the last 90 days for the
  charts) and 5 queue places, with every state on show: due soon, 1, 4 and 12 days overdue, a
  reservation, a 3 person queue, and a copy held for the next reader. Library copies are 1 to 5 per
  title.
- **Tests:** the cart and payment checks (T09 to T14, T28) were replaced, not just deleted, by
  borrowing checks, and T60 to T65 were added: late fee at 5 days, return stops the fee, two members
  racing for the last copy, a hold expiring, staff adding a copy, staff lowering copies. T04, T37,
  T39b, T53 to T55 now check loans instead of sales. Result: **59 of 59 E2E checks pass**,
  Paige 42 of 42.
- **Found on the way:** the serial lookup field on Add a book still had the old `MRG-` pattern, so
  the browser refused valid `BNT-` serials; fixed. Cards showed "Borrowed until" a date already past
  when the loan was overdue; it now says "Borrowed, return overdue".
- **Docs:** `REQUIREMENTS.md` (F5 rewritten as FR-20 to FR-24, FR-01, 04, 08, 11 to 13, 18, 33, 35,
  39, 41, 44, 45), `SITEMAP.md`, `STORYBOARD.md` (Three Click audit: borrow is 3 clicks, join a queue
  2, return 3), `WIREFRAMES.md`, `IMPLEMENTATION.md` (new section 11 with the judgment calls),
  `SETUP.md`, `TEST_LOG.md`, `README.md`.

## 2026-10-01: Paige, the help assistant (Mathew)

- New on every page: **Paige**, a virtual assistant in the header, to the right of the account link, whose chat panel drops down from the bar
  (`includes/assistant.php`, `process/assistant.php`). She is a component, not an eleventh page.
- **What she is:** a scripted assistant written in PHP. She matches the visitor's words to a
  topic and answers from the site's rules and live data. She is **not** a language model and the
  site says so ("automated, not a person"). A real AI model would need JSON, AJAX and an outside
  service, which the Base Version does not allow; that upgrade is a candidate for the Modern
  Enhancement.
- **What she can do:** order status (only for the person who placed the order), the member's next
  study room, free slots today, booking and cancelling rules, opening hours and address, delivery
  fees, payment problems, returns, account help, serial number lookup for members, book search
  with live price and stock, recommendations by genre or best sellers, and handing over to staff.
- **No AJAX:** a message is an ordinary form post followed by a redirect. The conversation is kept
  in the session; a few lines of JavaScript restore the scroll position after the reload.
  Without JavaScript the panel still opens and works (`<details>` element).
- New table `assistant_log` records every question, its topic and whether it was answered. The
  admin dashboard shows questions by topic (GROUP BY) and the latest unanswered ones.
- Tests: `tools/assistant-test.php` (40 questions, all on the expected topic) and seven new
  end to end checks (T52 to T58). The suite is now 51 checks, all passing.

## 2026-10-01: Atmosphere and finish (Mathew)

- Page background is no longer flat: four soft pools of light (amber, teal, violet, rose) over a
  near black gradient, with a fine paper grain. All CSS, no image files.
- Panels, tiles, room cards and booking cards have a gradient fill and a gradient hairline edge.
  Header, footer, billboard and shelf rows are separated by gradient hairlines.
- Headlines fade from paper white to warm amber; the primary button is a lit amber gradient; a
  band of light crosses a cover on hover; the featured cover and the open book sit on a warm halo.
- Kept accessible: worst case text contrast on the lightest part of the background is 5:1;
  Lighthouse accessibility is still 100. Hover motion respects `prefers-reduced-motion`.
- Performance findings while doing this (see `TEST_LOG.md`, T51): a `mix-blend-mode` grain
  layer and an endlessly drifting background cost up to 11 s of blocking time on Lighthouse's
  slow phone profile, so both were removed and the background is static. Cover images now come
  in two sizes (small for cards and thumbnails, large for the book page) and only the first row
  of the catalogue loads eagerly.
- Reader: the book layout is applied before `reader.js` runs, which removed a layout jump
  (CLS 0.20 to 0.00 on desktop).

## 2026-10-01: Real catalogue (Mathew)

- The catalogue now sells books that are actually in bookshops: 36 current titles in six
  categories (Self Improvement, Business and Money, Fiction, Mystery and Thriller, Sci-Fi and
  Fantasy, Non Fiction) plus 6 public domain classics. Data lives in `tools/catalogue.php`.
- Titles, authors, publishers, years, page counts and ISBNs are real and were checked against
  Open Library on 1 October 2026 (three items Open Library could not confirm are listed in
  `TEST_LOG.md`, T46). Prices are BookNest's own shelf prices in SGD, set close to Singapore
  bookshop prices; they are not copied from any one shop.
- New `books` columns: `isbn`, `publisher`, `format`, `sample_type`. Shown on the book page and
  in the member serial lookup. Search now also matches ISBN.
- **Copyright:** modern books are in copyright, so the reader does not show their text. Each
  has a four page *BookNest preview* written by us (what it is about, three ideas, who it is
  for), clearly labelled "not an excerpt". Only the classics show real opening pages
  (Project Gutenberg). Synopses are our own words, not publisher blurbs.
- **Covers:** `tools/fetch-covers.php` downloads real cover images from Open Library into
  `assets/covers/real/`. That folder is ignored by Git because the images belong to their
  publishers and the repository is public. If an image is missing, the generated BookNest cover
  is shown instead, so the site works either way. The footer credits Open Library.
- Removed eight leftover test covers (`bnt-9*.svg`); `tools/e2e-test.php` now deletes the
  cover it creates. Re-run on XAMPP after the change: 44 of 44 pass.
- Design documents: the wireframes are unchanged. `REQUIREMENTS.md` FR-16 and FR-18 were
  reworded to cover previews as well as samples.

## 2026-09-28: Renamed from Marginalia to BookNest

- New name everywhere: site name, page titles and descriptions, branch name, docs,
  `PROJECT_BRIEF.md`, folder (`booknest/`), URL (`/booknest/`) and database (`booknest`).
- Serial numbers now start with `BNT-` (was `MRG-`), and cover files are `bnt-*.svg`.
- Cookie and session names now start with `bn_` (was `mg_`).
- New logo mark and favicon: an open book resting in a woven nest.
- No functional change. The course PDFs are no longer kept in the repo (`*.pdf` is in `.gitignore`).
- Re-checked after the rename: every PHP and JS file passes a syntax check, and
  `tools/e2e-test.php` passes 44 of 44 on a Linux test setup (PHP 8.3, MariaDB 10.11, Apache 2.4).
  Test T18 hard codes the Windows path `C:/xampp/tmp`, so on Linux its session path had to be
  swapped for the run. **Re-run the test on XAMPP before you submit** and update `TEST_LOG.md`.
  Because the database is now named `booknest`, import `sql/schema.sql` and `sql/seed.sql` again.

## 2026-09-28: Base Version complete (Phases 0A to 6)

### Phase 0A: design documents
- Wrote `REQUIREMENTS.md` (22 application requirements, 43 functional requirements),
  `SITEMAP.md`, `STORYBOARD.md` (3 scenarios, Three Click audit) and `WIREFRAMES.md` (10 pages,
  desktop and mobile) before any code.

### Phase 0B: foundation
- Schema with 8 InnoDB tables; seed builder that pulls 38 public domain samples from Project
  Gutenberg and writes relative dates, original SVG covers and room plans.
- Shared bootstrap, secure sessions, CSRF, flash messages, prepared statement helpers, mail guard.
- Design tokens and four external stylesheets.

### Phases 1 to 5: features
- Home, catalogue (search, filter, sort, instant refine, member serial lookup), book detail.
- Sample reader with two page spread, 3D page turn, swipe, keyboard and reduced motion.
- Cart, guest checkout, payment simulator, transaction safe stock.
- Study rooms: availability table, booking with row locks, cancellation, daily cap.
- Accounts, My Account, My Shelf, add a book, admin approvals and GROUP BY analytics.

### Phase 6: polish and proof (issues found by testing, then fixed)
- Reader: keyboard handler crashed when a key event targeted the document (found by the
  browser probe).
- Mobile: menu button showed on desktop (CSS order); book page glow and admin charts caused
  sideways scrolling at 375px; visually hidden table labels widened the page (fixed by
  positioning the table wrapper).
- Accessibility: account link had no name on phones; reader caption contrast was 4.22:1;
  room slot labels did not include the visible word "Free"; star ratings used `aria-label` on
  a `<span>` (68 validator errors); input borders were 1.9:1 and small grey text 4.2:1.
  All fixed; Lighthouse accessibility now 100 on every page and the HTML validator reports 0 errors.
- Email: Mercury rejected the sender `library@localhost`; switched to `postmaster@localhost`
  and confirmed real delivery.
- Covers: slightly softer accent colours.

### Deviations from the design documents
- None in structure. The wireframes place the member "Admin" link in My Account; it also
  appears in the main navigation for admins, which keeps admin tasks within three clicks.
