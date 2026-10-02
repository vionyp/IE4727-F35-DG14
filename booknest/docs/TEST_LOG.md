# BookNest: Test log

All results below come from real runs on the development machine (Windows 11, XAMPP with
PHP 8.2.12, MariaDB 10.4.32, Apache 2.4, Microsoft Edge) on **28 September 2026**. Nothing
here is predicted: where a test has not been run yet, it says so.

## How the tests were run

| Method | What it is | How to repeat it |
|---|---|---|
| **E2E script** | `tools/e2e-test.php` drives the real site over HTTP like a browser: it keeps cookies, reads CSRF tokens from forms, submits them, follows redirects, then checks the database directly. Concurrency tests fire two requests at the same instant with `curl_multi`. | `C:\xampp\php\php.exe tools\e2e-test.php`, then re-import `sql/schema.sql` and `sql/seed.sql` to reset the data |
| **Browser probe** | Headless Microsoft Edge loads a page, a small test script presses buttons and keys, and the resulting DOM is read back. | Temporary harness, removed after testing |
| **Device check** | Each page rendered in a 375px-wide frame in headless Edge, with a script comparing page width to screen width and listing any element that sticks out. | Temporary harness, removed after testing |
| **Lighthouse** | Google Lighthouse 12 run against Edge on every page, signed in where the page requires it. | `npx lighthouse http://localhost/booknest/<page> --only-categories=accessibility,best-practices,performance,seo` |
| **HTML validator** | `html-validate` 8 with the `html-validate:standard` preset on the rendered HTML of 13 page states. | Save each page with `curl`, then `npx html-validate *.html` |
| **Code search** | Search of all PHP and JS for forbidden technology. | `Select-String` / `grep` for the patterns in T44 |

Final automated result: **59 of 59 E2E checks passed, 0 PHP errors logged** (2 October 2026, after
the switch to borrowing). The first release recorded 44 of 44 on 28 September.

---

## Borrowing system (2 October 2026, XAMPP on Windows)

The cart and payment checks were replaced by borrowing checks with the same IDs (T09 to T14, T28),
and T60 to T65 were added. T04, T37, T39b and T53 to T55 were redefined for loans. Every row below
is from the run of `tools/e2e-test.php` on 2 October 2026 against a freshly imported database,
except where the method says otherwise. Book titles come from that run; the script picks books
that nobody has on loan or in a queue, and sets their copies through the admin form.

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T09 | FR-20 | Visitor borrows | POST `process/loan.php` signed out | Sent to sign in, no loan | E2E | 303 to `sign-in.php`, loans still 68 | Pass |
| T10 | FR-20 | Collection date window | Yesterday; today + 8 days | Both refused | E2E | "Choose a collection date from today up to 7 days ahead." for both, no loan | Pass |
| T11 | FR-20 | Borrow an available book | Book with 3 copies, collect today | Loan `active`, due today + 14, one fewer on the shelf, email | E2E | Loan #69 active, due 16 Oct, on the shelf 3 → 2, "Loan confirmed" in `mail.log` | Pass |
| T11b | FR-20 | Borrow for a later day | Collect today + 3 | Loan `reserved`, due 14 days after collection | E2E | Reserved, collect 5 Oct, due 19 Oct | Pass |
| T12 | FR-22 | One loan per title | Same member borrows the same book again | Refused | E2E | "You already have The Subtle Art of Not Giving a F*ck on loan." | Pass |
| T13 | FR-22 | Queue at zero | Book with 1 copy: member 1 borrows it; members 2 and 3 try | Borrow refused; members 2 and 3 queue as #1 and #2; member 3 sees "You are #2 in line" | E2E | "Every copy of Deep Work is out..." then "#1 in line", "#2 in line" | Pass |
| T13b | FR-22 | Queue twice | Member 3 joins again | Refused | E2E | "You are already in the queue for Deep Work." | Pass |
| T13c | FR-21 | What others see | Visitor opens the book | Due date and queue length | E2E | "Borrowed until ..." and "2 people waiting" | Pass |
| T14 | FR-23 | Return offers the copy | Member 1 returns; member 3 then member 2 try to borrow | Member 2 offered and emailed; member 3 still refused; member 2 borrows the held copy | E2E | As expected; queue entry 2 `borrowed`, entry 3 `waiting` | Pass |
| T60 | FR-24 | Late fee at N days | Loan moved so it was due 5 days ago | 5 × S$0.50 = S$2.50, shown live | E2E | My Account: "5 days overdue, S$2.50 owed so far"; SQL fee 2.50 | Pass |
| T61 | FR-24 | Return stops the fee | Return it, then move all its dates 2 more days back | Fee stays S$2.50 | E2E | "It was 5 days late, so a late fee of S$2.50 was added"; two days on: S$2.50 | Pass |
| T62 | FR-23 | Race for the last copy | Two members borrow a 1 copy book at the same instant | Exactly one loan | E2E (`curl_multi`) | 1 open loan | Pass |
| T63 | FR-23 | Hold expires | Hold moved 3 days into the past, any page loaded | Hold `expired`, next person offered and emailed | E2E | Member 3 offered → expired; member 4 offered; "has ended" and offer emails logged | Pass |
| T64 | FR-39 | Staff add a copy | Admin raises copies from 1 to 2 while a member waits | Offered at once | E2E | "Saved changes to Deep Work. 1 new copy was offered to the queue." | Pass |
| T65 | FR-39 | Staff lower copies | Admin sets 1 copy while 2 are held | Refused, copies unchanged | E2E | "Deep Work has 2 copies out on loan or held for the queue, so the library must keep at least that many." | Pass |
| T28 | AR-S7 | External email | Member registered as `...@gmail.com` borrows | Loan works, email refused and logged | E2E | `mail.log`: "refused: external recipient blocked" | Pass |
| T04 | FR-11 | Serial lookup, member | Aisha searches `BNT-000123` | Panel with availability, copies, times borrowed, added by | E2E | Panel shown with all fields | Pass |
| T37 | FR-41 | Analytics accuracy | Dashboard "Late fees outstanding" vs a manual `SUM` over `loans` | Same value | E2E | S$12.50 on both (after the test loans) | Pass |
| T39b | FR-39 | Delete protection | Admin deletes a book that has been borrowed | Refused, book kept | E2E | "...has been borrowed before, so it is kept for the loan records." | Pass |
| T53 | FR-44 | Quick question | "Late fees" button | Fee answer, earlier messages kept | E2E | "Returning late costs S$0.50..." shown, conversation kept | Pass |
| T54 | FR-45 | Loan privacy | Visitor asks "what do I have on loan" | Asked to sign in, no loan data | E2E | "I can only show loans to the member they belong to." | Pass |
| T55 | FR-45 | Own loans | Aisha asks the same | Her loans with due date and fee | E2E | "Atomic Habits, due back ..." and "4 days overdue (S$2.00 so far)" | Pass |
| T59 | FR-44 | Understanding | 42 questions in `tools/assistant-test.php` (loans, fees, queue, return, collection, borrow added) | Each lands on the expected topic | Script | First run 41 of 42: "how long can I keep a book" not understood; pattern added; now 42 of 42 | Pass after fix |
| T39 | AR-U1 | Three Click Rule for borrowing | Borrow, join a queue, return | 3, 2 and 3 clicks | Walk-through of the built pages (screenshots) | Book page shows Borrow; `borrow.php` has today chosen and one Confirm button; Join the queue and Return (with confirm) as in `STORYBOARD.md` | Pass |
| T45 | AR-B2 | Page count | Top level `.php` pages | Still 10 | Code search | index, catalogue, book, read, borrow, rooms, account, sign-in, add-book, admin = 10 | Pass |
| T44 | AR-B1 | Forbidden technology | Same patterns as before, over all PHP and JS | No matches | Code search | 0 matches | Pass |
| T66 | all | Full regression | `tools/e2e-test.php` | All pass | E2E | **59 of 59**, 0 PHP errors | Pass |

Visual check: the book, catalogue, borrow, My Account and admin pages were rendered in headless
Chrome at 1366px and the home page at 390px. That found two problems: a card said "Borrowed until"
a date that had already passed (an overdue loan), and long availability text wrapped badly next
to the rating. Both were fixed and re-checked.

**Not re-run yet for the new pages:** Lighthouse, the HTML validator and the 375px device check
(T41 to T43). They are planned with the phone performance work. The keyboard-only walk-through
(T40) is still to do and now covers borrowing, queues and returns.

## Re-run after the real catalogue (1 October 2026, XAMPP on Windows)

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T46 | FR-12 | Book facts | 36 ISBNs looked up on Open Library | Title matches; page count, publisher and cover found | Script against openlibrary.org | 36 of 36 ISBNs resolve to the right title and have a cover. Open Library has no page count for *The Subtle Art* and *A Brief History of Time* (ours: 224 and 212) and files the *Educated* ISBN under a summary booklet; those three details are from our own knowledge, not verified | Pass with 3 notes |
| T47 | FR-16 | Preview labelling | Open the reader for Atomic Habits (in copyright) and Frankenstein (public domain) | First says "BookNest preview ... not an excerpt"; second says "Free sample" | Browser screenshot | As expected: 6 page preview and 12 page sample | Pass |
| T48 | FR-07 | ISBN search | `catalogue.php?q=9780735211292` | Atomic Habits | Browser | HTTP 200, one result | Pass |
| T49 | AR-B3 | Cover fallback | Site with and without `assets/covers/real/` | Real covers when present, generated covers when not | E2E ran before the download; screenshots after | Works both ways | Pass |
| T50 | all | Full regression | `tools/e2e-test.php` | 44 of 44 | E2E | 44 of 44 passed, 0 PHP errors, no test files left behind | Pass |
| T51 | AR-R4 | Performance after the visual redesign | Lighthouse 12, desktop and phone profiles | No regression in accessibility; performance stays high | Lighthouse | Accessibility 100 and Best Practices 100 on all pages tested. **Desktop profile:** Home 96, Browse 98, Book 100, Reader 98, Study Rooms 99. **Slow phone profile:** Book 93, Reader 93, Browse about 75, Home about 65. The phone figures for Home and Browse are lower than before the real covers (96 and 99) because a sharp phone screen downloads the large cover files over a simulated slow network | Pass on desktop; phone noted |

### Paige, the help assistant (1 October 2026)

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T52 | FR-44 | Typed question | "What time do you close?" | Hours answer in the open chat panel | E2E | "We are open every day, 10:00 to 21:00..." shown, panel open | Pass |
| T53 | FR-44 | Quick question button | "Delivery fees" | Delivery answer, earlier messages kept | E2E | Answer shown, conversation kept | Pass |
| T54 | FR-45 | Order privacy | Visitor asks about another person's order number | Refused, no details | E2E | "I can only show an order to the person who placed it" | Pass |
| T55 | FR-45 | Own order | Member asks "where is my order" | Their latest order summary | E2E | "Order #.. was placed on .." shown | Pass |
| T56 | AR-S2 | Script injection and unknown question | `<script>alert(1)</script> can I bring my dog` | Shown as text, logged as unanswered | E2E | Escaped; one row with `answered = 0` | Pass |
| T57 | AR-S3 | CSRF | Post without token | Rejected, nothing logged | E2E | Log row count unchanged | Pass |
| T58 | FR-46 | Logging | Five questions | Five new rows in `assistant_log` | E2E | 5 rows | Pass |
| T59 | FR-44 | Understanding | 40 realistic questions (`tools/assistant-test.php`) | Each lands on the expected topic | Script | First run 37 of 39: "any books about money?" not understood and "gift cards" mistaken for a payment card; both patterns fixed; now 40 of 40 | Pass after fix |

The full end to end suite is now **51 checks, all passing**.

The detailed results below were recorded on 28 September 2026 with the first catalogue; book
titles, order numbers and amounts in them refer to that data. T04, T09 to T14, T28, T37, T39b and
T53 to T55 have since been redefined for borrowing; their current definitions and results are in
the Borrowing section at the top. The rows below are the original results, kept for the record.

## 1. Search, browse and serial lookup

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T01 | FR-07 | Search | `catalogue.php?q=alice` | Alice's Adventures in Wonderland listed | E2E | HTTP 200, title found | Pass |
| T02 | AR-S1 | Search injection | `q=' OR 1=1 --` | No error, no data leak, "No results" | E2E | HTTP 200, 0 cards, "No results" shown | Pass |
| T03 | FR-08 | Sort whitelist | `sort=DROP` | Falls back to Title A to Z, no error | E2E | HTTP 200, "Title A to Z" selected | Pass |
| T04 | FR-11 | Serial lookup, member | Aisha searches `BNT-000123` | Exact match panel with stock, status, sold, added by | E2E | Panel shown with all fields | Pass |
| T05 | FR-11 | Serial lookup, visitor | Visitor searches `BNT-000123` | No details; "member feature" note | E2E | Note shown, no panel | Pass |
| T45 | AR-B2 | Page count | Count top level `.php` pages | 1 home + 9 content pages (limit 10) | Code search | index, catalogue, book, read, checkout, rooms, account, sign-in, add-book, admin = 10 | Pass |

## 2. Sample reader

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T06 | FR-17 | Page turn, phone | 400px wide, press Next | Single page view turns with a 3D leaf | Browser probe | Leaf present during the turn; "Page 1 of 12" → "Page 2 of 12"; leaf removed after | Pass |
| T06b | FR-17 | Page turn, desktop | 1440px wide, press Next, then Right arrow, then Previous | Spread turns forward twice and back once | Browser probe | "Pages 1 and 2" → "3 and 4" → "5 and 6" → "3 and 4" | Pass |
| T07 | FR-18 | Last page | Press End | Stops on the last spread, Next disabled, Buy now shown | Browser probe | "Pages 11 and 12 of 12", visible: Page 11 and End of sample, Next disabled | Pass |
| T07b | FR-18 | Resume | Reopen the reader at phone width | Resumes at the saved page | Browser probe | Opened at "Page 11 of 12" (saved in `localStorage`) | Pass |
| T07c | AR-A6 | JavaScript errors | Whole probe run | No uncaught errors | Browser probe | First run found `event.target.closest is not a function` when a key event targeted `document`; fixed in `reader.js`; re-run: none | Pass after fix |
| T08 | FR-19 | No JavaScript | Load `read.php` without scripts | All pages readable as a vertical scroll | Code review + HTML | Pages are rendered in plain HTML by PHP; the stacked layout is the default CSS until JS adds `.is-enhanced` | Pass (by inspection) |

Swipe gestures were implemented with Pointer Events (40px threshold) and the same `next()`/`prev()` functions the probe exercised; **a touch device test by a person is still recommended** before the demo.

## 3. Accounts, sessions and cookies

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T17a | FR-32 | Sign in | `aisha@localhost` / `Member123!` | Signed in, welcome message | E2E | "Welcome back, Aisha." | Pass |
| T17 | AR-S4 | Wrong password | `aisha@localhost` / `wrong-password1` | Generic error | E2E | "Email or password is incorrect." | Pass |
| T17b | AR-S4 | Unknown email | `nobody@localhost` | Same generic error (no account enumeration) | E2E | Same message | Pass |
| T15 | FR-31 | Duplicate email | Register with `aisha@localhost` | Friendly error, no new row | E2E | Field error, users still 9 | Pass |
| T15b | FR-31 | Register | New name, email, matching passwords, agree | Account created and signed in | E2E | "Welcome to BookNest, Test..." | Pass |
| T16 | FR-31 | Password mismatch (HTML5/JS bypassed) | `Abcdefg123` / `Abcdefg999` | PHP rejects | E2E | "The passwords do not match." | Pass |
| T18 | AR-S6 | Idle timeout | Session idle for 31 minutes | Signed out with a message | E2E (session file clock moved back 31 min) | "You were signed out after 30 minutes without activity." | Pass |
| T19 | FR-15 | Recently viewed cookie | View books 7, 13, 25, open home | "Recently viewed" row | E2E | Row rendered | Pass |
| T19b | FR-42 | Remember my email | Sign in with the box ticked | `bn_email` cookie, HttpOnly | E2E | Cookie stored with HttpOnly flag | Pass |
| T20 | FR-43 | Tampered cookie | `bn_recent=1,abc,DROP TABLE,13` | Junk ignored, page works | E2E | HTTP 200, ids 1 and 13 shown | Pass |

## 4. Security and roles

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T31 | AR-S5 | Member opens admin | Aisha requests `admin.php` | Refused, redirected with message | E2E | 303 to home, "That page is for library staff only." | Pass |
| T32 | AR-S5 | Visitor posts to add book | POST `process/add-book.php` signed out | Sent to sign in, nothing saved | E2E | 303 to `sign-in.php`, no new row | Pass |
| T33 | AR-S3 | CSRF | POST `process/shelf.php` without token | Rejected, nothing saved | E2E | 303 with "session expired" message, shelf unchanged | Pass |
| T33b | AR-S2 | Private folders | GET `config/config.php`, `storage/mail.log` | Blocked | E2E | 403 and 403 | Pass |
| T44 | AR-B1 | Forbidden technology | Search for `fetch(`, `XMLHttpRequest`, `JSON.`, `json_encode`, `json_decode`, `jquery`, `<iframe`, `<frame`, `action="mailto` | No matches in the base version | Code search | 0 matches | Pass |

## 5. Cart, checkout and payment (retired on 2 October 2026, replaced by borrowing)

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T09 | FR-21 | Empty name (HTML5 bypassed) | `full_name=""` | PHP rejects, no order row | E2E | Field error, order count unchanged | Pass |
| T10 | FR-21 | Delivery without address | `delivery_method=delivery`, `address=""` | Rejected | E2E | "Enter the full delivery address..." | Pass |
| T10b | FR-21 | Address without postal code | `12 Nanyang Drive` | Rejected | E2E | "Add the 6 digit Singapore postal code..." | Pass |
| T11 | FR-21 | Bad phone | `12345` | Rejected | E2E | "Enter a Singapore number with 8 digits..." | Pass |
| T12 | FR-24 | Payment fails | Simulator = failure | Order `failed`, stock unchanged, cart kept | E2E | Order #51 failed, stock 16 unchanged, cart still shown | Pass |
| T13 | FR-23 | Payment succeeds | Simulator = success | Order `paid`, stock reduced, email, cart cleared | E2E | Order #52 paid S$18.40, stock 16 → 15, mail logged, confirmation page | Pass |
| T14 | FR-20 | Quantity above stock | `qty=999` (tampered) | Refused | E2E | "Choose a quantity from 1 to 10 for Frankenstein." | Pass |
| T28 | AR-S7 | External email | Checkout with `someone@gmail.com` | Order works, email refused and logged | E2E | mail.log: "refused: external recipient blocked" | Pass |
| T28b | AR-S7 | Real local delivery | `tools/mail-test.php admin@localhost` with Mercury running | Message arrives in Mercury's Admin mailbox | Manual | Arrived. First attempt bounced because the sender `library@localhost` was not a Mercury mailbox; sender changed to `postmaster@localhost`; second attempt delivered | Pass after fix |

## 6. Study rooms

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T21 | FR-27 | 60 minute booking | Free slot, 60 min | Confirmed, email | E2E | "Booked. Folio, Wednesday 30 September, 10:00 to 11:00." + mail logged | Pass |
| T22 | FR-28 | 90 minutes (tampered) | `duration=90` | Rejected | E2E | "A booking can be 30 or 60 minutes long." | Pass |
| T23 | FR-28 | Daily cap | 30 more minutes after a 60 minute booking, same day, other room and time | Rejected | E2E | "You already have 60 minutes booked on Wed 30 Sep. The daily limit is 60 minutes." | Pass |
| T24 | FR-28 | Overlap | Second member books the same room and time | Rejected | E2E | "Someone has just booked Folio at that time." | Pass |
| T25 | FR-28 | Race, two members | Two members submit the same slot at the same instant | Exactly one booking | E2E (`curl_multi`) | 1 confirmed booking | Pass |
| T26 | FR-28 | Race, one member, two rooms | One member books two rooms at the same instant | Daily total stays at 60 minutes | E2E (`curl_multi`) | 60 minutes booked | Pass |
| T27 | FR-28 | Past and too far ahead | Today 10:00; today + 8 days | Both rejected | E2E | "That time has already passed." / "Choose a date from today up to 7 days ahead." | Pass |
| T29 | FR-29 | Cancel then rebook | Cancel, then book again that day | Quota freed, rebook succeeds | E2E | "...Your hour for that day is free again." then "Booked. Quill..." | Pass |
| T29b | AR-S5 | Cancel someone else's booking | Another member posts the booking id | Refused | E2E | "We could not find that booking." | Pass |
| T30 | FR-28 | After closing | 20:30 for 60 minutes | Rejected | E2E | "The library closes at 21:00..." | Pass |

## 7. Members and admin

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T34 | FR-37 | Duplicate serial | Submit the same serial twice | Second rejected | E2E | "...is already in the library." | Pass |
| T34b | FR-37 | Bad serial format | `ABC-12` | Rejected | E2E | "Use the format BNT- followed by six digits..." | Pass |
| T35 | FR-36 | Member submission | New book by Aisha | Saved `pending`, not in catalogue | E2E | Status pending, not visible in search | Pass |
| T36 | FR-38 | Approve | Admin approves it | Appears in catalogue | E2E | "...is approved and now in the catalogue." and visible in search | Pass |
| T37 | FR-41 | Analytics accuracy | Compare dashboard revenue with `SELECT SUM(total) ... 'paid'` | Same value | E2E | S$1,330.40 on both | Pass |
| T38 | FR-34 | Shelf CRUD | Save, then remove | 1 row, then 0 rows | E2E | 1 → 0 | Pass |
| T39b | FR-39 | Delete protection | Admin deletes a book that has orders | Refused, book kept | E2E | "...kept for the sales records. Set its stock to 0 instead." | Pass |

## 8. Usability, accessibility and quality

| ID | Req | Feature | Input data | Expected output | Method | Actual result | Result |
|---|---|---|---|---|---|---|---|
| T39 | AR-U1 | Three Click Rule | The 11 tasks in `STORYBOARD.md` | 3 clicks or fewer each | Design walk-through of the built pages | Paths match the storyboard: Buy now adds and opens checkout in one step; free cells pre-fill the booking form; account links are direct | Pass (walk-through) |
| T41 | AR-R2 | Phone width | All 10 pages in a 375px frame | No horizontal page scroll | Device check | First run: book page 413px (blurred glow), admin 440px (bar charts, then hidden table labels); all fixed; re-run: every page exactly 375px | Pass after fix |
| T42 | AR-A3 | Lighthouse | All 10 pages | Accessibility 95+ | Lighthouse | First run 93 to 97 (account link had no name on phones, reader caption contrast 4.22:1, slot link label mismatch); fixed; re-run: **Accessibility 100, Best Practices 100, SEO 100 on all 10 pages**; Performance 87 to 100 | Pass after fix |
| T43 | AR-M1 | Valid HTML | 13 rendered page states | No errors | HTML validator | First run: 68 errors, all `aria-label` on a plain `<span>` in the star rating; fixed with visually hidden text; re-run: 0 errors | Pass after fix |
| T43b | AR-A1 | Contrast | Every text and UI colour pair | WCAG AA | Contrast calculation (`IMPLEMENTATION.md`) | Input borders were 1.9:1 and small grey text 4.2:1; raised to 3.3:1+ and 5.3:1+; all pairs now pass | Pass after fix |
| T40 | AR-A2 | Keyboard only | Booking and checkout with Tab, Enter, arrows | Fully usable, focus visible | Partly automated | Reader arrow keys verified (T06b); every control has a name and a focus style (Lighthouse). **A full manual keyboard walk-through has not been done yet** | To do |

## Lighthouse scores (final run)

Measured on 28 September 2026, before the switch to borrowing ("Checkout" is now the Borrow page,
which has not been measured yet).

| Page | Accessibility | Best practices | SEO | Performance |
|---|---|---|---|---|
| Home | 100 | 100 | 100 | 96 |
| Browse | 100 | 100 | 100 | 99 |
| Book detail | 100 | 100 | 100 | 99 |
| Reader | 100 | 100 | 100 | 99 |
| Checkout | 100 | 100 | 100 | 99 |
| Study rooms | 100 | 100 | 100 | 87 |
| My Account | 100 | 100 | 100 | 100 |
| Sign in | 100 | 100 | 100 | 100 |
| Add a book | 100 | 100 | 100 | 94 |
| Admin | 100 | 100 | 100 | 98 |

Study Rooms scores lowest on performance because its 132 cell table is large for Lighthouse's
simulated mid range phone. It is still fast on a real laptop.
