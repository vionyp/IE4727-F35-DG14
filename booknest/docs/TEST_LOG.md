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

Final automated result: **44 of 44 E2E checks passed, 0 PHP errors logged**.

---

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

## 5. Cart, checkout and payment

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
