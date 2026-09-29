# BookNest: Storyboard and Three Click audit

## User scenarios

### Scenario 1: Find a book and read a sample (Nadia, 19, student, on her phone)

Nadia heard about *Frankenstein* in a lecture and wants to see if she would enjoy it.

```mermaid
flowchart LR
    A["Any page<br/>types 'franken' in header search"] -->|"Enter (1)"| B["catalogue.php?q=franken<br/>1 result"]
    B -->|"tap cover (2)"| C["book.php<br/>synopsis, price, stock"]
    C -->|"Read a sample (3)"| D["read.php<br/>swipes through 10 pages"]
    D -->|"Buy now on last page"| E["checkout.php"]
```

| Step | Screen | What she sees | Click |
|---|---|---|---|
| 1 | Header search | Types "franken", presses Enter | 1 |
| 2 | Catalogue | "1 book matches franken", one cover card | 2 |
| 3 | Book detail | Cover, synopsis, "Read a sample" as a clear secondary button | 3 |
| 4 | Reader | A single paper page; she swipes left to turn | swipe |
| 5 | End of sample | "Enjoying it?" with Buy now and Back to book | optional |

From the home page she can also tap the billboard or a row card directly, which cuts it to 2 clicks.

### Scenario 2: Buy a book as a guest (Mr Tan, 52, on a laptop)

```mermaid
flowchart LR
    A["Home<br/>New arrivals row"] -->|"click cover (1)"| B["book.php"]
    B -->|"Buy now (2)"| C["checkout.php<br/>book in cart, form"]
    C -->|"types details, Place order (3)"| D{"Payment"}
    D -->|success| E["checkout.php?done=<br/>confirmation banner, email"]
    D -->|failure| F["checkout.php<br/>cart kept, retry message"]
```

No account is needed. Buy now puts the book in the cart and opens checkout in one step.
The payment simulator defaults to Success, so the normal path has no extra click.

### Scenario 3: Book a study room (Aisha, member)

```mermaid
flowchart LR
    A["Any page"] -->|"Study Rooms (1)"| B["rooms.php<br/>today's table"]
    B -->|"click a Free cell (2)"| C["Booking form pre-filled<br/>room, date, start; 60 min default"]
    C -->|"Confirm booking (3)"| D{"Server checks<br/>overlap, 60 min/day, hours"}
    D -->|ok| E["rooms.php<br/>cell now 'Yours', success message, email"]
    D -->|rejected| F["rooms.php<br/>specific reason, form kept"]
```

Later she cancels: My Account (1), Cancel (2), Yes, cancel (3). Her daily hour is freed.

If a visitor clicks a Free cell, the booking panel shows an inline sign in form. After signing
in they return to the same pre-filled slot, so booking needs one extra submit only for the
first visit. This is the one documented exception to the rule.

---

## Three Click audit

A click is a link, button or form submit. Typing, swiping and scrolling do not count.
Counted for a signed in member starting on any page unless noted.

| # | Task | Path | Clicks | Test |
|---|---|---|---|---|
| 1 | Find a book by title | Search, Enter (1), result (2) | 2 | T39 |
| 2 | Browse a category | Category chip or row title (1), book (2) | 2 | T39 |
| 3 | Read a sample | Cover (1), Read a sample (2) | 2 | T39 |
| 4 | Buy one book (guest) | Cover (1), Buy now (2), Place order (3) | 3 | T39 |
| 5 | Book a study room | Study Rooms (1), Free cell (2), Confirm (3) | 3 | T39 |
| 6 | Cancel a booking | My Account (1), Cancel (2), Yes, cancel (3) | 3 | T39 |
| 7 | Save a book to My Shelf | Cover (1), Save to shelf (2) | 2 | T39 |
| 8 | Add a new book | Add a book (1), Submit (2) | 2 | T39 |
| 9 | Look up a serial number | Type serial in search, Enter (1) | 1 | T39 |
| 10 | Check opening hours | Visible in the footer of every page | 0 | T39 |
| 11 | Sign out | My Account (1), Sign out (2) | 2 | T39 |

Design decisions that keep these numbers low:

- **Search in the header of every page**, so finding starts anywhere.
- **Buy now** adds and opens checkout in one step; **guest checkout** means no sign in detour.
- **Free cells are links** that pre-fill the form; duration defaults to 60 minutes.
- **Account links are plain links**, not a dropdown menu that costs a click to open.
- **Opening hours live in the footer**, not on a separate page.
