# BookNest: Requirements

Project: *BookNest, an online library for browsing, sampling and borrowing books*
Course: IE4727 Web Application Design, Theme 6 (Online Library)
Status: Phase 0A baseline, written before implementation. Changes are logged in `CHANGELOG.md`.

---

## A. Application requirements (overall behaviour and quality)

Each requirement is measurable and has at least one test in `TEST_LOG.md`.

### Usability

| ID | Requirement | Measure |
|---|---|---|
| AR-U1 | Every primary task can be completed in three clicks or fewer (the Three Click Rule). | Audit in `STORYBOARD.md`, test T39 |
| AR-U2 | The header (logo, navigation, search, loans, account) is identical and in the same place on every page. | Visual check on all 10 pages |
| AR-U3 | Users always know where they are: active navigation item, breadcrumbs on inner pages, descriptive page titles. | T41, manual review |
| AR-U4 | Every action gives a clear response: a success, error or info message that says what happened and what to do next. | Flash message on every POST |
| AR-U5 | Forms keep the user's input after a server side error and point to the exact field. | T09, T15 |
| AR-U6 | Empty states offer a next step instead of a dead end (no results, nothing on loan, empty shelf). | Manual review |
| AR-U7 | Users stay in control: bookings can be cancelled, loans returned early, reservations cancelled, queues left, shelf items removed, reader closed at any time. | T14, T29, T38 |

### Responsiveness

| ID | Requirement | Measure |
|---|---|---|
| AR-R1 | Layout is mobile first and works from 360px to 1920px wide. | T41 at 360, 768, 1024, 1440 |
| AR-R2 | No horizontal page scroll at any width. Only the availability table scrolls inside its own wrapper. | T41 |
| AR-R3 | Touch interactions work: rows swipe, the reader turns pages by swipe. | T06 |
| AR-R4 | Pages render meaningful content quickly: no external fonts or libraries, images sized to avoid layout shift. | Lighthouse Performance 90+ |

### Security

| ID | Requirement | Measure |
|---|---|---|
| AR-S1 | All database access uses prepared statements. | Code review, T02 |
| AR-S2 | All output is escaped. | Code review |
| AR-S3 | Every POST form carries a CSRF token that the server verifies. | T33 |
| AR-S4 | Passwords are stored as `password_hash` values; login errors are generic. | T17 |
| AR-S5 | Protected pages and endpoints check the role on the server. | T31, T32 |
| AR-S6 | Sessions are regenerated on login, expire after 30 minutes idle, and use HttpOnly SameSite cookies. | T18 |
| AR-S7 | Email is only sent to accounts on the local mail server. | T28 |
| AR-S8 | Users never see raw PHP or SQL errors. | T02, T03 |

### Scalability

| ID | Requirement | Measure |
|---|---|---|
| AR-SC1 | Categories, books, rooms and opening hours come from the database or config, so adding one needs no code change. | Add a category in phpMyAdmin, it appears as a row |
| AR-SC2 | Layouts grow with data: rows scroll, grids wrap, the table adds rows for new rooms. | Manual review |
| AR-SC3 | Search and booking queries use indexes on searched and joined columns. | `schema.sql` review |

### Maintainability

| ID | Requirement | Measure |
|---|---|---|
| AR-M1 | One shared header, footer and bootstrap include; no duplicated layout code. | Code review |
| AR-M2 | Design tokens in one place (`base.css`); four external stylesheets. | Code review |
| AR-M3 | Every PHP and JS function has a one line purpose comment. | Code review |
| AR-M4 | Configuration (DB, hours, room and lending rules, mail domain) lives only in `config/config.php`. | Code review |

### Accessibility

| ID | Requirement | Measure |
|---|---|---|
| AR-A1 | WCAG 2.1 AA contrast for all text and UI. | Contrast table in `IMPLEMENTATION.md` |
| AR-A2 | Every feature works with a keyboard only, with a visible focus ring. | T40 |
| AR-A3 | Semantic landmarks, one `h1` per page, labelled inputs, announced errors. | Lighthouse Accessibility 95+ (T42) |
| AR-A4 | Information is never conveyed by colour or hover alone. | Table legend, overlays also on focus |
| AR-A5 | Motion respects `prefers-reduced-motion`. | Manual review |
| AR-A6 | Core content works without JavaScript (progressive enhancement). | T08 |

### Overall behaviour

| ID | Requirement |
|---|---|
| AR-B1 | The Base Version uses only HTML5, CSS3, vanilla JavaScript, PHP and MySQL, with no AJAX, JSON, iframes or frameworks (T44). |
| AR-B2 | The site has one home page and nine content pages, each with text, images and a unique title (T45). |
| AR-B3 | The site runs on a fresh XAMPP install with no internet connection. |

---

## B. Functional requirements

Mapped to features F1 to F10 of the project brief.

### F1 Browsing (home)

| ID | Requirement |
|---|---|
| FR-01 | The home page shows a featured book billboard with cover, hook, live availability, "Borrow it free" and "Read a sample". |
| FR-02 | The home page shows horizontal rows for New arrivals, Staff picks, Recently viewed (if any) and each category, generated from the database. |
| FR-03 | Rows scroll by touch swipe and by previous/next buttons that also work with the keyboard. |
| FR-04 | Cover cards show title, author, rating and live availability (never a price), with a quick info overlay on hover and on focus. |
| FR-05 | Visitors see a sign in card on the home page; members see a personal welcome with shortcuts. |
| FR-06 | The home page shows opening hours with a live "Open now" or "Closed" status and a study room teaser. |

### F2 Search, filter and sort

| ID | Requirement |
|---|---|
| FR-07 | A search box on every page searches title, author, ISBN and serial number. |
| FR-08 | The catalogue can be filtered by category and sorted by title, newest, rating and "on the shelf now first". |
| FR-09 | Typing in the "Refine" box filters the visible results instantly without reloading. |
| FR-10 | An empty result shows the query and suggests categories to browse. |
| FR-11 | A signed in member searching an exact serial number (`BNT-000000`) sees a full details panel (availability, copies owned, on loan, waiting, times borrowed, status, added by, date added). |

### F3 Book detail

| ID | Requirement |
|---|---|
| FR-12 | The book page shows cover, title, author, category, year, format, pages, ISBN, publisher, rating, live availability, copies owned, loan period, late fee and synopsis. |
| FR-13 | The book page offers Borrow (or Join the queue, Leave the queue, Borrow your held copy, depending on the member's situation), Read a sample and, for members, Save to shelf. Visitors see "Sign in to borrow". |
| FR-14 | The book page shows "You may also like" books from the same category. |
| FR-15 | Viewing a book records it in a "Recently viewed" cookie (last 6 books). |

### F4 Sample reader

| ID | Requirement |
|---|---|
| FR-16 | The reader shows a book's reading pages as a two page spread on desktop and a single page on phones: a real 10 page sample for public domain books, or a short preview written by BookNest (labelled as not an excerpt) for books in copyright. |
| FR-17 | Pages turn by swipe or drag, arrow keys, clicking page edges and Previous/Next buttons, with a 3D turn. |
| FR-18 | The reader shows a page counter and progress bar; the last page offers Borrow this book and Back to book. |
| FR-19 | Without JavaScript, all pages are readable as a vertical scroll. |

### F5 Borrowing, queues and late fees

Replaced the cart and checkout on 2 October 2026 so the core transaction matches the course theme,
an online library (see `CHANGELOG.md`). Constants live in `config/config.php`.

| ID | Requirement |
|---|---|
| FR-20 | A signed in member borrows a book by choosing a collection date (today up to `COLLECT_AHEAD_DAYS` = 7 days ahead) and confirming. The loan lasts `LOAN_DAYS` = 14 days from the collection date, and a confirmation email gives the due date. Visitors are sent to sign in first. |
| FR-21 | No price is shown to members anywhere. Every book shows live availability instead: "Available to borrow"; for the member's own loan "Due back in X days", "Due today" or "X days overdue" (in red); for a book someone else has, "Borrowed until [date]"; and "N people waiting" when a queue exists. |
| FR-22 | A member can hold one open loan per title. When no copy is on the shelf the member can join a queue: they see their own position ("You are #2 in line"), everyone else sees the queue length, and they can leave the queue at any time. |
| FR-23 | When a copy comes back (a return, or staff adding copies) the first person in the queue is emailed and the copy is held for them for `QUEUE_HOLD_DAYS` = 2 days. A hold that is not taken up passes to the next person. Two members can never take the same last copy. |
| FR-24 | Members return a loan, or cancel a reservation not yet collected, from My Account. A late return costs `LATE_FEE_PER_DAY` = S$0.50 for every day after the due date, worked out live from the dates; returning stops the fee growing. My Account shows each overdue loan with the fee so far and the total owed. Paying fees is out of scope: they are settled at the front desk. |

### F6 Study rooms

| ID | Requirement |
|---|---|
| FR-25 | The rooms page shows an availability table (rooms by 30 minute slots) for a chosen date up to 7 days ahead. |
| FR-26 | Each cell shows Free, Taken, Yours, Past or Closed with text and icon. |
| FR-27 | Clicking a free cell pre-fills the booking form; members confirm with a duration of 30 or 60 minutes. |
| FR-28 | The server rejects bookings over 60 minutes, over 60 minutes per user per day, overlapping, in the past, after closing or more than 7 days ahead. |
| FR-29 | Members can cancel a future booking from My Account, which frees their daily quota. |
| FR-30 | Booking and cancellation send a confirmation email. |

### F7 Accounts

| ID | Requirement |
|---|---|
| FR-31 | Visitors can register with name, email and password (confirmed). |
| FR-32 | Users can sign in, optionally remember their email, and sign out. |
| FR-33 | My Account shows loans (due dates, fees, return), queue places, recent returns, upcoming bookings, My Shelf and book submissions. |
| FR-34 | Members can save books to and remove books from My Shelf. |

### F8 Add and search books

| ID | Requirement |
|---|---|
| FR-35 | Members can add a new book with serial, title, author, category, year, pages, synopsis, sample text and the number of copies they can give. |
| FR-36 | New books are stored as pending with a generated cover and appear in the catalogue only after approval. |
| FR-37 | Serial numbers must be unique and match `BNT-` plus six digits. |

### F9 Admin

| ID | Requirement |
|---|---|
| FR-38 | Admins can approve or reject pending books. |
| FR-39 | Admins can edit copies owned (never below the copies on loan or held for the queue), replacement value, featured and staff pick flags, and delete books that have never been borrowed. Added copies are offered to the queue at once. |
| FR-40 | Admins can open or close study rooms. |
| FR-41 | Admins see analytics built with GROUP BY and aggregates: books on loan, overdue loans, late fees outstanding, people in queues, most borrowed books, loans by category, longest queues, overdue loans with fees, new loans per day (14 days), bookings per room, busiest hours, members. |

### F11 Help assistant

| ID | Requirement |
|---|---|
| FR-44 | Every page offers a help assistant (Paige) that answers typed questions and quick question buttons about borrowing, loans, late fees, queues, collection, study rooms, opening hours, accounts and books. |
| FR-45 | The assistant states that it is automated, never shows a loan or booking to anyone but its owner, and offers staff contact when it cannot answer. |
| FR-46 | Every question is logged with its topic so that staff can see, in the admin dashboard, what people ask and what was not answered. |

### F10 Cookies and request data

| ID | Requirement |
|---|---|
| FR-42 | A "Remember my email" cookie pre-fills the sign in form. |
| FR-43 | Cookie values are validated before use; tampered values are ignored. |
