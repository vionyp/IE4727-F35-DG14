# BookNest: Implementation

How the site is built, which techniques it uses, and why. Every section maps to the design
documents (`REQUIREMENTS.md`, `SITEMAP.md`, `STORYBOARD.md`, `WIREFRAMES.md`).

## 1. Technology

| Layer | Used | Not used (by course rule) |
|---|---|---|
| Structure | HTML5, semantic elements, ARIA only where HTML has no equivalent | Frames, iframes, templates |
| Presentation | CSS3: custom properties, Grid, Flexbox, `clamp()`, scroll snap, 3D transforms | Bootstrap, Tailwind, preprocessors |
| Behaviour | Vanilla JavaScript, six small files loaded with `defer` | jQuery, AJAX, `fetch`, JSON |
| Server | PHP 8.2 with `mysqli` prepared statements | Frameworks |
| Data | MariaDB 10.4 (MySQL compatible), InnoDB, 8 tables | |

No file is loaded from the internet: fonts are system fonts, and every image is an original
SVG made for this project.

## 2. Structure and page control

```
booknest/
  index.php ... admin.php        10 pages (1 home + 9)
  process/*.php                  10 form endpoints: validate, write, redirect
  includes/bootstrap.php         loaded first by every request
  includes/header.php, footer.php  one shared layout
  includes/{db,functions,auth,csrf,flash,loans,mail,rooms,covers,views,assistant}.php
  config/config.php              every setting in one place
  assets/css/{base,components,pages,print}.css
  assets/js/{nav,rows,search-filter,reader,rooms,forms}.js
  sql/schema.sql, sql/seed.sql
  tools/build-seed.php, e2e-test.php, mail-test.php
```

- **Page control.** Every page starts with `require 'includes/bootstrap.php'`, which loads the
  config, sets the timezone and error handling, starts a secure session and opens the database.
  Pages set `$page_title`, `$crumbs` and `$scripts`, then include the shared header and footer.
- **Post/Redirect/Get.** Forms never post to a page. They post to a `process/` script, which
  validates, writes, stores a flash message and redirects with `303 See Other`. Refreshing
  never resubmits a form.
- **Keeping input after an error.** `keep_form()` stores the submitted values and field
  errors in the session; after the redirect, `old()` and `field_error()` put them back into
  the form, with `aria-invalid` and `aria-describedby` set on the right fields. Passwords are
  never kept.
- **Guards.** `require_post()`, `verify_csrf()`, `require_login()` and `require_admin()` run
  at the top of every protected script. Hiding a button is never the only protection.

### GET, POST, COOKIE and server variables

| Kind | Where it is used |
|---|---|
| `$_GET` | Search `q`, `category`, `sort`; `book.php?id=`, `borrow.php?id=`; room `date`, `room`, `start`; serial lookup; `account.php?loan=` to highlight a new loan |
| `$_POST` | Every form; always read through `input()` / `input_int()` which trim, type check and cap length |
| `$_COOKIE` | `bn_recent` (recently viewed ids, validated as integers), `bn_email` (remember my email), `bn_session` |
| `$_SESSION` | Signed in user, CSRF token, flash messages, kept form values, Paige's conversation, idle timer |
| `$_SERVER` | `REQUEST_METHOD` (POST only endpoints), `SCRIPT_NAME` (active navigation), `REQUEST_URI` (return after sign in), `HTTP_REFERER` (safe return after an action, same host only), `HTTP_HOST`, `REMOTE_ADDR` (logs) |

## 3. Database

Nine InnoDB tables with foreign keys and indexes on every searched or joined column
(`sql/schema.sql`). The course guide suggests 3 to 4 tables; the features need 9
(users, categories, books, loans, book_queue, study_rooms, room_bookings, shelf, assistant_log).
`orders` and `order_items` were replaced by `loans` and `book_queue` on 2 October 2026.
`books.stock` now means copies the library owns; copies on the shelf are always worked out live
(owned, minus open loans, minus copies held for the queue) and never stored. `books.price` is kept
as a replacement value for staff and is never shown to members.

| Operation | Examples |
|---|---|
| SELECT | Catalogue search with `LIKE`, rows, book detail, availability, dashboards |
| INSERT | Register, loans, queue places, bookings, member books, shelf |
| UPDATE | Loan return and status, queue offer, expiry and leave, booking cancellation, approvals, copies and value edits, room open/closed |
| DELETE | Remove from shelf, admin delete of a never borrowed book |
| GROUP BY / aggregates | Most borrowed books, loans by category, longest queues, new loans per day, late fees outstanding (`SUM` of a live fee expression), overdue count, bookings per room, busiest hours, minutes used per day |

**Prepared statements everywhere.** `db_run()` prepares every query and binds values with
`$stmt->execute($params)`. User input never becomes SQL text: sort options are a whitelist
that maps to fixed `ORDER BY` strings, and `LIKE` wildcards in search terms are escaped.

**Catalogue and copyright.** `tools/catalogue.php` holds 36 real, current books (facts checked
against Open Library) and 6 public domain classics. Books in copyright never show their own
text: `books.sample_type` is `preview`, and the reader shows four pages written by us and
labelled "not an excerpt". Classics have `sample_type = excerpt` and show real opening pages.
Real cover images are downloaded by `tools/fetch-covers.php` into a folder Git ignores;
`cover_file()` uses a real cover when the file exists and the generated SVG cover otherwise.

**Seed data** is generated by `tools/build-seed.php`. It downloads the public domain texts
from Project Gutenberg once, cuts the licence header, finds each book's opening, and packs
about 125 words per page at sentence boundaries into 10 pages. All dates are written as
`NOW() - INTERVAL n DAY` or `CURDATE() + INTERVAL n DAY`, so the dashboard, the room table, the
overdue loans and the queues look current on any demo day. The catalogue lists a bookshop style
stock figure; the seed scales it to 1 to 5 library copies, and sets *Fourth Wing* (2 copies),
*Project Hail Mary* and *The Housemaid* (1 copy each) by hand so the queue states always appear.

## 4. Transactions and concurrency

**Borrowing.** Every action that changes how many copies are on the shelf (borrow, return,
leave a queue, an expired hold, staff changing the number of copies) runs in a transaction that
first locks the book row. The availability check and the insert then happen while no one else
can touch that book, so two members can never take the same last copy (test T62):

```php
$db->begin_transaction();
db_one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$uid]);              // one request per member at a time
$book = db_one("SELECT id, title FROM books WHERE id = ? AND status = 'approved' FOR UPDATE", [$bookId]);
// ...one open loan per title, queue position or held copy, copies on the shelf...
db_exec('INSERT INTO loans (user_id, book_id, collection_date, due_date, status) VALUES (?, ?, ?, ?, ?)', [...]);
$db->commit();
```

**The queue.** `queue_offer_next()` runs inside the same locked transaction after a return or a
change of copies: while a copy is free and someone is waiting, the first person (by `queued_at`)
becomes `offered` with `offered_at = NOW()`. The emails are sent after the commit, so a slow mail
server never holds a lock. XAMPP has no scheduler, so `loans_sync()` runs once per request from
`bootstrap.php`: it moves reserved loans to active on their collection date and active loans to
overdue after their due date, expires holds older than `QUEUE_HOLD_DAYS`, and offers the copy to
the next person (test T63).

**Late fees are never stored.** `loan_fee()` in PHP and `loan_fee_sql()` in SQL use the same rule:
days from the due date to the return date (or to today, while the book is out), times
`LATE_FEE_PER_DAY`. The total in My Account and on the dashboard is therefore always current,
and returning a book stops the fee growing because the return date stops the count (tests T60,
T61), the same way the room cap is recomputed from live bookings rather than cached.

**Study rooms.** Two people could otherwise pass the checks at the same moment. The booking
transaction locks the member's row (so the daily 60 minute cap cannot be beaten by two tabs)
and the room's row (so two members cannot take the same slot) with `SELECT ... FOR UPDATE`,
then runs the overlap and cap checks, then inserts. Tests T25 and T26 fire two requests at the
same instant and confirm exactly one wins.

## 5. Security

| Threat | Defence |
|---|---|
| SQL injection | Prepared statements, whitelisted sort, integer casting |
| Cross site scripting | Every output passes through `e()` (`htmlspecialchars` with `ENT_QUOTES`, UTF-8) |
| Cross site request forgery | Random 32 byte token per session in every POST form, checked with `hash_equals` |
| Session theft or fixation | `HttpOnly`, `SameSite=Lax`, strict mode, `session_regenerate_id(true)` on sign in, 30 minute idle timeout, full destroy on sign out |
| Password leaks | `password_hash` / `password_verify`; the same message for wrong email or wrong password |
| Open redirects | `safe_return()` accepts only paths inside `/booknest/`; referer used only if same host |
| Email misuse | `send_mail()` refuses any recipient outside `@localhost` and header injection attempts, and logs every message to `storage/mail.log` |
| Information leaks | `display_errors` off; a custom handler logs the error and shows a calm message; `config/`, `includes/`, `storage/`, `sql/` and `tools/` are blocked with `.htaccess` |

## 6. Validation in three layers

1. **HTML5** first: `required`, `type="email|tel|number|date"`, `min`, `max`, `step`,
   `minlength`, `maxlength`, `pattern`.
2. **JavaScript** (`forms.js`, `rooms.js`) only for what HTML5 cannot do: password match,
   letter and number rule, start plus duration before
   closing, not in the past, daily quota preview, live counters. Errors appear under the
   field and are linked with `aria-describedby`.
3. **PHP** re-checks everything, plus the rules that need the database: unique email and
   serial, overlaps, the daily cap, copies on the shelf, one loan per title, the queue, and the
   collection date window. The full field matrix is in the project brief.

## 7. Front end techniques

- **Design tokens.** All colours, type sizes, spacing, radii and motion live as custom
  properties in `base.css`. Components never use raw values for these.
- **Mobile first.** The base CSS is the phone layout; `min-width` queries at 640, 768, 900,
  1024 and 1280px add columns. Fluid type uses `clamp()`.
- **Book rows** are CSS Grid tracks with `scroll-snap-type: x mandatory`, so they swipe
  natively on touch. `rows.js` adds previous/next buttons that disable at the ends and hide
  when everything fits.
- **Cover cards** show a quick info overlay on hover **and** keyboard focus; touch devices
  (`hover: none`) skip it and go straight to the book.
- **Instant refine** (`search-filter.js`) hides already rendered cards by matching `data-`
  attributes on every keystroke. No request is sent; the PHP search stays the source of truth.
- **Sample reader** (`reader.js`). PHP renders every page as a `<section>`, so without
  JavaScript the sample is a plain scroll. With JavaScript the pages become a book:
  a two page spread with a spine on desktop, one page on phones. A turn clones the page
  being turned into a two sided "leaf" (`transform-style: preserve-3d`,
  `backface-visibility: hidden`) and rotates it 180 degrees around the spine with a shading
  gradient, while the next pages are already in place underneath. It responds to Pointer
  Events swipes and drags (40px threshold), arrow keys, Page Up/Down, Home/End, clicks on the
  outer quarter of the book, and the buttons. `prefers-reduced-motion` swaps the turn for a
  short fade. The page counter is an `aria-live` region.
- **Availability table.** A real `<table>` with `<caption>`, `scope="col"` time headers and
  `scope="row"` room headers, a sticky first column, and text plus a symbol in every cell
  (never colour alone). Free cells are real links that work without JavaScript; with
  JavaScript they fill the form in place.
- **Progressive enhancement everywhere.** Tabs, the mobile menu, confirm steps and dismiss
  buttons are hidden until JavaScript is ready, and every form works without it.
- **Print stylesheet** hides navigation and buttons so confirmations print cleanly.

## 7b. Paige, the help assistant

Paige is a rule based assistant, deliberately built without AI services so the Base Version
stays inside the course rules. `assistant_reply()` lower-cases the message and tests it against
ordered regular expressions, most specific first (for example "how do I borrow" is checked before
"my loans", so a how-to question is not mistaken for a question about the member's own loans). Each topic has a
handler: static topics return help text built from `config.php` constants (fees, hours, limits),
and data topics query the database with prepared statements.

- **Privacy:** loans, fees and queue places are only ever read for the signed in member. A visitor
  who asks is told to sign in, and no loan data is shown (test T54).
- **State without AJAX:** the form posts to `process/assistant.php`, which appends the question
  and answer to `$_SESSION['assistant']` (last 24 messages), logs the question, and redirects
  back with Post/Redirect/Get. The widget is a `<details>` element, so it opens without
  JavaScript; `nav.js` only restores scroll position, scrolls the log and handles Escape.
- **Honest fallback:** if nothing matches, she says so, offers staff contact, and the question
  is stored with `answered = 0` for the admin dashboard.
- **Accessibility:** the conversation is a `role="log"` live region, each bubble has hidden
  "You said" / "Paige said" text, and the input has a label.

## 8. Accessibility

Skip link; `header`, `nav`, `main`, `footer` landmarks; one `h1` per page; visible labels on
every input; errors announced; `aria-current="page"` on the active navigation item and
breadcrumb; a visible focus ring on everything (`:focus-visible`, 2px, offset 2px); all
interactive targets at least 36px (44px for the main controls). Lighthouse scores 100 for
accessibility on all ten pages (see `TEST_LOG.md`).

### Contrast (WCAG 2.1 AA)

| Pair | Colours | Ratio | Needed |
|---|---|---|---|
| Body text on page | `#F2EEE6` on `#0E1116` | 16.34:1 | 4.5:1 |
| Body text on panels | `#F2EEE6` on `#171B22` | 14.92:1 | 4.5:1 |
| Muted text on page | `#A9A398` on `#0E1116` | 7.55:1 | 4.5:1 |
| Muted text on raised surfaces | `#A9A398` on `#1F2530` | 6.14:1 | 4.5:1 |
| Amber links on panels | `#E5A93D` on `#171B22` | 8.28:1 | 4.5:1 |
| Text on amber buttons | `#1A1204` on `#E5A93D` | 8.90:1 | 4.5:1 |
| Text on teal buttons | `#06201D` on `#3FB6A8` | 6.87:1 | 4.5:1 |
| Teal "Free" on panels | `#3FB6A8` on `#171B22` | 6.96:1 | 4.5:1 |
| Danger text on panels | `#E5615A` on `#171B22` | 5.09:1 | 4.5:1 |
| Error messages | `#F08A84` on `#0E1116` | 7.81:1 | 4.5:1 |
| "Taken" cells | `#B7B1A6` on `#1F2530` | 7.22:1 | 4.5:1 |
| "Past" cells and placeholders | `#948E84` on `#171B22` | 5.31:1 | 4.5:1 |
| Reader text on paper | `#2B2620` on `#F4EDE0` | 12.88:1 | 4.5:1 |
| Reader captions on paper | `#635847` on `#F4EDE0` | 5.98:1 | 4.5:1 |
| Input borders (UI) | `#636C82` on `#171B22` | 3.29:1 | 3:1 |
| Focus ring (UI) | `#7DB7FF` on `#0E1116` | 9.09:1 | 3:1 |

## 9. Email

`send_mail()` sends through PHP `mail()` to Mercury on `localhost:25`, with sender
`postmaster@localhost` (Mercury rejects senders that are not local mailboxes). Every message,
sent or refused, is also appended to `storage/mail.log`, so the demo can show emails even if
Mercury is not running. Emails: loan confirmed (with the due date), your reserved book is ready
(queue offer), hold ended, book returned (with any late fee), reservation cancelled, booking
confirmation, booking cancellation.

## 10. Traceability

| Requirement | Feature | Main files | Tests |
|---|---|---|---|
| FR-01 to FR-06 | F1 Home | `index.php`, `rows.js`, `views.php` | T19, T39, T41, T42 |
| FR-07 to FR-11 | F2 Search | `catalogue.php`, `search-filter.js` | T01 to T05 |
| FR-12 to FR-15 | F3 Book | `book.php`, `functions.php` (cookie) | T19, T20 |
| FR-16 to FR-19 | F4 Reader | `read.php`, `reader.js`, `pages.css` | T06 to T08 |
| FR-20 to FR-24 | F5 Borrowing | `borrow.php`, `book.php`, `account.php`, `includes/loans.php`, `process/loan.php` | T09 to T14, T28, T60 to T65 |
| FR-25 to FR-30 | F6 Rooms | `rooms.php`, `rooms.js`, `includes/rooms.php`, `process/book-room.php`, `process/cancel-room.php` | T21 to T30 |
| FR-31 to FR-34 | F7 Accounts | `sign-in.php`, `account.php`, `process/login.php`, `register.php`, `logout.php`, `shelf.php` | T15 to T18, T38 |
| FR-35 to FR-37 | F8 Add book | `add-book.php`, `process/add-book.php`, `covers.php` | T34, T35 |
| FR-38 to FR-41 | F9 Admin | `admin.php`, `process/admin-action.php` | T36, T37, T39b |
| FR-44 to FR-46 | F11 Help assistant | `includes/assistant.php`, `process/assistant.php`, `admin.php`, `tools/assistant-test.php` | T52 to T59 |
| FR-42, FR-43 | F10 Cookies | `functions.php`, `process/login.php` | T19b, T20 |
| AR-S1 to AR-S8 | Security | `csrf.php`, `auth.php`, `db.php`, `.htaccess` | T02, T17, T18, T28, T31 to T33b |
| AR-A1 to AR-A6 | Accessibility | all CSS, `forms.js`, `reader.js` | T40, T42, T43, T43b |
| AR-R1 to AR-R4 | Responsiveness | all CSS | T41, T42 |
| AR-B1, AR-B2 | Course rules | whole project | T44, T45 |

## 11. Lending decisions (2 October 2026)

The switch from buying to borrowing left a few choices that the brief did not settle. Each is
recorded here so it can be confirmed or changed.

| Decision | What we chose | Why |
|---|---|---|
| Where the borrow form lives | `checkout.php` became `borrow.php`, a page with one field (collection date) | Keeps the ten page structure the wireframes and storyboard describe, and leaves room for the other states (already borrowed, in the queue, held for you). |
| When a loan counts as collected | Automatically on the chosen collection date; no staff step | The brief describes no front desk system. Until then the loan shows as "Reserved" and can be cancelled. |
| Who can borrow | Signed in members only | A loan has to belong to an account, for due dates, fees and the one copy per title rule. Visitors still browse and read samples. |
| Hold window for the queue | `QUEUE_HOLD_DAYS` = 2 | Long enough to see the email and come in, short enough that a popular book does not sit on the hold shelf. An offered member may only pick a collection date inside the hold. |
| Leaving a queue | Allowed at any time; a held copy then goes to the next person | Users stay in control (AR-U7). |
| Copies held for the queue | Counted as not on the shelf | Otherwise a walk-in borrower could take the copy promised to the person at the front of the queue. |
| Staff lowering copies | Refused below copies on loan plus copies held | Prevents a negative number of copies on the shelf. |
| Price | Kept in the database as a replacement value for staff; removed from the member form | Members never see it; staff may still want an asset value. |
| Paying late fees | Out of scope in this phase; shown only, settled at the desk | As the brief asks. A desk "fees paid" action is planned for the next phase. |
