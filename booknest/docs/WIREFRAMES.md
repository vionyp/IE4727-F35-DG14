# BookNest: Wireframes

Grayscale structural wireframes made before styling. They fix *what goes where*; colour,
type and imagery come later from the design system. Desktop is 1280px, mobile is 360px.
`[ ]` are buttons or links, `( )` are inputs, `▒` are images.

## Shared frame (every page)

```
DESKTOP
+--------------------------------------------------------------------------------+
| [skip to content]                                                              |
| LOGO BookNest   Home  Browse  Study Rooms   ( search books, authors... )  [Loans 2] [My Account] |
+--------------------------------------------------------------------------------+
| flash message (success / error / info), dismissible                            |
+--------------------------------------------------------------------------------+
|                                   <main>                                       |
+--------------------------------------------------------------------------------+
| FOOTER: Visit us (hours, Open now)  | Explore (links) | About (credits, team, email) |
| Cookie note · Public domain credit · Not affiliated with NLB                   |
+--------------------------------------------------------------------------------+

MOBILE
+------------------------------+
| LOGO BookNest  [Loans 2][≡]|
| ( search books...        )[>]|
+------------------------------+
| [≡] opens: Home / Browse /   |
|     Study Rooms / Account    |
+------------------------------+
```

## 1. Home (`index.php`)

```
DESKTOP
+--------------------------------------------------------------------------------+
| BILLBOARD                                                                      |
|  FEATURED THIS WEEK                                        +----------+        |
|  Frankenstein                    (h1, display serif)       |  ▒▒▒▒▒▒  |        |
|  Mary Shelley · 1818 · ★ 4.6                               |  cover   |        |
|  One line hook, two lines max.                             |  ▒▒▒▒▒▒  |        |
|  ● Available to borrow                                     |          |        |
|  [Borrow it free]  [Read a sample]                         +----------+        |
+--------------------------------------------------------------------------------+
| VISITOR: Three clicks to anything (3 points)     | SIGN IN CARD (email)(pass) [Sign in] |
| MEMBER:  Welcome back, Aisha · next booking · [Add a book] [My Shelf]           |
+--------------------------------------------------------------------------------+
| Recently viewed                                         [<] [>]  (cookie only) |
| [▒] [▒] [▒] [▒] [▒] [▒]  -> scroll snap row                                   |
| New arrivals                                      See all  [<] [>]             |
| Staff picks ...                                                                |
| Fiction / Mystery / Science Fiction / Non Fiction / Young Readers / Classics   |
+--------------------------------------------------------------------------------+
| ▒ room image  | Study rooms. Quiet space, one hour a day. 14 free slots today [See rooms] |
+--------------------------------------------------------------------------------+
| Visit us: hours table + Open now badge     | address, how to get there         |
+--------------------------------------------------------------------------------+

MOBILE: billboard stacks (cover on top, text below, buttons full width); sign in card
below value points; rows show 2.3 cards so the cut off card signals "swipe".
```

## 2. Browse (`catalogue.php`)

```
+--------------------------------------------------------------------------------+
| h1 Browse the collection            "12 books match 'holmes'"                  |
| chips: [All] [Fiction] [Mystery] [Sci-Fi] [Non Fiction] [Young] [Classics]     |
| ( Refine these results... )                         Sort [ Title A-Z  v ] [Go] |
+--------------------------------------------------------------------------------+
| MEMBER + serial query:  SERIAL MATCH panel ▒ | serial, copies, loans, status, by |
+--------------------------------------------------------------------------------+
| [▒ card] [▒ card] [▒ card] [▒ card] [▒ card] [▒ card]   grid, 6 cols desktop   |
| [▒ card] [▒ card] ...                                   2 cols mobile          |
+--------------------------------------------------------------------------------+
| EMPTY STATE: ▒ illustration · No results for "x" · Try [Classics] [Mystery]    |
+--------------------------------------------------------------------------------+
```

## 3. Book detail (`book.php`)

```
+--------------------------------------------------------------------------------+
| Home > Science Fiction > Frankenstein                                          |
| +-----------+   SCIENCE FICTION                                                |
| |  ▒▒▒▒▒▒▒  |   Frankenstein (h1)                                            |
| |  cover    |   Mary Shelley                                                 |
| |  ▒▒▒▒▒▒▒  |   ★ 4.6 · 1818 · 280 pages                                     |
| +-----------+   ● Available to borrow   (or Borrowed until 6 Oct · 3 waiting) |
|                 3 of 4 copies on the shelf · 14 day loans, free for members    |
|                 [Borrow] or [Join the queue]  [Read a sample] [Save to shelf]  |
|                 Synopsis paragraph...                                          |
|                 Details: serial · category · year · pages                      |
+--------------------------------------------------------------------------------+
| You may also like   [▒][▒][▒][▒][▒]                                            |
+--------------------------------------------------------------------------------+
MOBILE: cover centred on top at 60% width, then text, buttons full width, stacked.
```

## 4. Sample reader (`read.php`)

```
DESKTOP (two page spread)
+--------------------------------------------------------------------------------+
| [x Close]            Frankenstein · Sample           Pages 4 and 5 of 12       |
|                                                                                |
|      +-----------------------+|+-----------------------+                       |
|      |  left page text       |||  right page text      |                       |
|      |                       |||                       |   click edges = turn  |
|      |                  4    |||   5                   |   drag / swipe = turn |
|      +-----------------------+|+-----------------------+                       |
|                                                                                |
|  [< Previous]   ============------------ progress   [Next >]                   |
+--------------------------------------------------------------------------------+
MOBILE: one page fills the screen, same bars. NO JS: pages stacked vertically.
LAST PAGE: "End of sample" ▒ cover  [Borrow this book]  [Back to book]
```

## 5. Borrow (`borrow.php`, replaced the cart and checkout on 2 October 2026)

```
+--------------------------------------------------------------------------------+
| Home > Fiction > The Midnight Library > Borrow                                 |
| h1 Borrow this book                                                            |
| +-----------------------------+  +-------------------------------------------+ |
| | ▒ cover  The Midnight Library|  | When will you collect it?                 | |
| |          Matt Haig            |  | Collection date [ Today, Fri 2 Oct ·      | |
| |          ● Available to borrow|  |                   due back Fri 16 Oct  v ] | |
| |          3 of 4 on the shelf  |  | · 14 days from the day you collect        | |
| +-----------------------------+  | · Return early any time                   | |
|                                  | · Late returns S$0.50 a day               | |
|                                  | [Confirm loan]                            | |
|                                  +-------------------------------------------+ |
| OTHER STATES: "You already have this book" · "You are #2 in line" [Leave]      |
|               "Every copy is out" [Join the queue] · held copy: "until Sun 4 Oct"|
+--------------------------------------------------------------------------------+

```

## 6. Study rooms (`rooms.php`)

```
+--------------------------------------------------------------------------------+
| h1 Study rooms       Rules: up to 60 min a day · 7 days ahead · You have 60 min left |
| Dates: [Today] [Tue] [Wed] [Thu] [Fri] [Sat] [Sun] [Mon]   (date)[Show]        |
| Legend: ○ Free  ● Taken  ★ Yours  – Past                                        |
| +------------+------+------+------+------+----                                 |
| | Room       |10:00 |10:30 |11:00 |11:30 | ...  (caption: availability table)  |
| | Folio (2)  | Free | Taken| Taken| Free |                                     |
| | Quill (2)  | Past | Free | Yours| Free |                                     |
| +------------+------+------+------+------+----   scrolls inside wrapper        |
|                                                                                |
| #book  BOOK A ROOM                                                             |
| [Room v] (date) [Start v] [Duration 60 v] (Purpose) [Confirm booking]          |
| visitor: inline SIGN IN form instead of Confirm                                |
+--------------------------------------------------------------------------------+
| Room cards: ▒ plan  Folio · 2 seats · Level 2 · whiteboard, power              |
+--------------------------------------------------------------------------------+
MOBILE: date chips scroll; table wrapper scrolls with sticky room column.
```

## 7. My Account (`account.php`)

```
+--------------------------------------------------------------------------------+
| h1 Hello, Aisha                         [Add a book] [Admin] [Sign out]        |
| tiles: On loan 2 | Fees owed S$2.00 | Waiting for 1 | Minutes left 60 | Shelf 4 |
+--------------------------------------------------------------------------------+
| Upcoming bookings: Folio · Tue 29 Sep · 14:00-15:00   [Cancel] -> [Yes, cancel]|
| Your loans: ▒ The Silent Patient · 4 days overdue, S$2.00 so far   [Return]   |
|             ▒ Atomic Habits · Due back in 9 days                    [Return]   |
| Waiting for: ▒ Fourth Wing · You are #2 in line                [Leave queue]   |
| Recent returns: table of book, collected, due, returned, late fee             |
| My Shelf: [▒][▒][▒][▒]  each with [Remove]                                      |
| My submissions: The Jungle Book · Pending                                      |
+--------------------------------------------------------------------------------+
```

## 8. Sign in / Register (`sign-in.php`)

```
+--------------------------------------------------------------------------------+
| +----------------------------+  +------------------------------------------+   |
| |  ▒▒ illustration: lamp     |  | [Sign in] [Create account]  (tabs)       |   |
| |  over an open book         |  | (Email) (Password) [x] Remember my email |   |
| |  "Members can book rooms,  |  | [Sign in]                                |   |
| |   keep a shelf, add books" |  | -- or register: name, email, pw, confirm |   |
| +----------------------------+  +------------------------------------------+   |
+--------------------------------------------------------------------------------+
MOBILE: illustration becomes a short banner above the tabs.
```

## 9. Add a book (`add-book.php`)

```
+--------------------------------------------------------------------------------+
| Home > My Account > Add a book                                                 |
| h1 Add a book to the library                                                   |
| Serial lookup: (BNT-000123) [Look up]  -> result panel                         |
| +------------------------------------------+  +--------------------+           |
| | (Serial) (Title) (Author) [Category v]   |  | LIVE COVER PREVIEW |           |
| | (Year) (Pages) (Copies)                  |  |  ▒▒▒ title/author  |           |
| | (Synopsis ................. 0/2000)      |  |                    |           |
| | (Sample text, ---PAGE--- between pages)  |  +--------------------+           |
| | [Submit for review]                      |                                   |
| +------------------------------------------+                                   |
+--------------------------------------------------------------------------------+
```

## 10. Admin (`admin.php`)

```
+--------------------------------------------------------------------------------+
| h1 Library dashboard                                                           |
| tiles: On loan | Overdue | Fees outstanding | People in queues | Members       |
+--------------------------------------------------------------------------------+
| Most borrowed ██████ | Loans by category ████ | Longest queues ███ | Overdue   |
| New loans per day (14)  ▂▃▅▇▅▃ columns | Bookings per room ████ | Busiest hours|
+--------------------------------------------------------------------------------+
| Pending books: ▒ title · by member · [Approve] [Reject]                        |
| Books table: serial | title | value( ) | copies( ) | on loan | flags | [Save]  |
| Rooms: Folio · open [Close room]                                               |
+--------------------------------------------------------------------------------+
```
