# BookNest: Requirements

Project: *BookNest, an online library for browsing, sampling and buying books*
Course: IE4727 Web Application Design, Theme 6 (Online Library)
Status: Phase 0A baseline, written before implementation. Changes are logged in `CHANGELOG.md`.

---

## A. Application requirements (overall behaviour and quality)

Each requirement is measurable and has at least one test in `TEST_LOG.md`.

### Usability

| ID | Requirement | Measure |
|---|---|---|
| AR-U1 | Every primary task can be completed in three clicks or fewer (the Three Click Rule). | Audit in `STORYBOARD.md`, test T39 |
| AR-U2 | The header (logo, navigation, search, cart, account) is identical and in the same place on every page. | Visual check on all 10 pages |
| AR-U3 | Users always know where they are: active navigation item, breadcrumbs on inner pages, descriptive page titles. | T41, manual review |
| AR-U4 | Every action gives a clear response: a success, error or info message that says what happened and what to do next. | Flash message on every POST |
| AR-U5 | Forms keep the user's input after a server side error and point to the exact field. | T09, T15 |
| AR-U6 | Empty states offer a next step instead of a dead end (no results, empty cart, empty shelf). | Manual review |
| AR-U7 | Users stay in control: bookings can be cancelled, shelf items removed, cart edited, reader closed at any time. | T29, T38 |

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
| AR-M4 | Configuration (DB, hours, limits, mail domain, payment simulator) lives only in `config/config.php`. | Code review |

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
| FR-01 | The home page shows a featured book billboard with cover, hook, "Read a sample" and "Buy now". |
| FR-02 | The home page shows horizontal rows for New arrivals, Staff picks, Recently viewed (if any) and each category, generated from the database. |
| FR-03 | Rows scroll by touch swipe and by previous/next buttons that also work with the keyboard. |
| FR-04 | Cover cards show title, author, rating and price, with a quick info overlay on hover and on focus. |
| FR-05 | Visitors see a sign in card on the home page; members see a personal welcome with shortcuts. |
| FR-06 | The home page shows opening hours with a live "Open now" or "Closed" status and a study room teaser. |

### F2 Search, filter and sort

| ID | Requirement |
|---|---|
| FR-07 | A search box on every page searches title, author and serial number. |
| FR-08 | The catalogue can be filtered by category and sorted by title, price (both directions), newest and rating. |
| FR-09 | Typing in the "Refine" box filters the visible results instantly without reloading. |
| FR-10 | An empty result shows the query and suggests categories to browse. |
| FR-11 | A signed in member searching an exact serial number (`BNT-000000`) sees a full product details panel (stock, status, added by, date added). |

### F3 Book detail

| ID | Requirement |
|---|---|
| FR-12 | The book page shows cover, title, author, category, year, pages, rating, price, stock state and synopsis. |
| FR-13 | The book page offers Buy now, Add to cart, Read a sample and, for members, Save to shelf. |
| FR-14 | The book page shows "You may also like" books from the same category. |
| FR-15 | Viewing a book records it in a "Recently viewed" cookie (last 6 books). |

### F4 Sample reader

| ID | Requirement |
|---|---|
| FR-16 | The reader shows 8 to 12 sample pages as a two page spread on desktop and a single page on phones. |
| FR-17 | Pages turn by swipe or drag, arrow keys, clicking page edges and Previous/Next buttons, with a 3D turn. |
| FR-18 | The reader shows a page counter and progress bar; the last page offers Buy now and Back to book. |
| FR-19 | Without JavaScript, all pages are readable as a vertical scroll. |

### F5 Cart and checkout

| ID | Requirement |
|---|---|
| FR-20 | Visitors and members can add books to a cart, change quantities and remove lines. |
| FR-21 | Checkout collects name, email, phone, delivery method, address (for delivery) and an optional note. |
| FR-22 | A payment simulator lets the demo choose Success or Failure. |
| FR-23 | On success the order is saved as paid, stock is reduced in one transaction, the cart is cleared and a confirmation email is sent. |
| FR-24 | On failure the order is saved as failed, stock is unchanged, the cart is kept and the user can retry. |

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
| FR-33 | My Account shows upcoming bookings, orders, My Shelf and book submissions. |
| FR-34 | Members can save books to and remove books from My Shelf. |

### F8 Add and search books

| ID | Requirement |
|---|---|
| FR-35 | Members can add a new book with serial, title, author, category, price, year, pages, synopsis, sample text and stock. |
| FR-36 | New books are stored as pending with a generated cover and appear in the catalogue only after approval. |
| FR-37 | Serial numbers must be unique and match `BNT-` plus six digits. |

### F9 Admin

| ID | Requirement |
|---|---|
| FR-38 | Admins can approve or reject pending books. |
| FR-39 | Admins can edit price, stock, featured and staff pick flags, and delete books that have never been ordered. |
| FR-40 | Admins can open or close study rooms. |
| FR-41 | Admins see analytics built with GROUP BY: revenue by category, top 5 books, orders per day (14 days), bookings per room, busiest hours, members, average order value, payment success rate. |

### F10 Cookies and request data

| ID | Requirement |
|---|---|
| FR-42 | A "Remember my email" cookie pre-fills the sign in form. |
| FR-43 | Cookie values are validated before use; tampered values are ignored. |
