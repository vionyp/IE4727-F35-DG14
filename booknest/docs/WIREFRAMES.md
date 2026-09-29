# BookNest: Wireframes

Grayscale structural wireframes made before styling. They fix *what goes where*; colour,
type and imagery come later from the design system. Desktop is 1280px, mobile is 360px.
`[ ]` are buttons or links, `( )` are inputs, `▒` are images.

## Shared frame (every page)

```
DESKTOP
+--------------------------------------------------------------------------------+
| [skip to content]                                                              |
| LOGO BookNest   Home  Browse  Study Rooms   ( search books, authors... )  [Cart 2] [My Account] |
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
| LOGO BookNest   [Cart 2][≡]|
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
|  [Buy now S$14.90]  [Read a sample]                        +----------+        |
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
| MEMBER + serial query:  SERIAL MATCH panel  ▒ | serial, stock, status, added by |
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
| +-----------+   S$14.90   In stock                                           |
|                 [Buy now] [Add to cart] [Read a sample] [Save to shelf]        |
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
LAST PAGE: "End of sample" ▒ cover  [Buy now]  [Back to book]
```

## 5. Cart and checkout (`checkout.php`)

```
+--------------------------------------------------------------------------------+
| Home > Checkout                                                                |
| h1 Checkout                                                                    |
| +----------------------------------------+  +--------------------------------+ |
| | Your details                           |  | ORDER SUMMARY (sticky)         | |
| | (Full name)          (Email)           |  | ▒ Title  qty( 1 )[Update][x]   | |
| | (Phone)                                |  | ▒ Title  qty( 2 )[Update][x]   | |
| | Delivery ( ) Delivery  ( ) Pickup      |  | Subtotal / Delivery / Total    | |
| | (Address, shown for delivery)          |  +--------------------------------+ |
| | (Note)                                 |                                     |
| | PAYMENT SIMULATOR (o) Success ( ) Fail |                                     |
| | [Place order S$29.80]                  |                                     |
| +----------------------------------------+                                     |
| DONE STATE: banner "Order #42 is confirmed" + summary. EMPTY: ▒ + [Browse]     |
+--------------------------------------------------------------------------------+
MOBILE: summary first (collapsed list), then form.
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
| tiles: Books on shelf 4 | Orders 3 | Minutes left today 60                     |
+--------------------------------------------------------------------------------+
| Upcoming bookings: Folio · Tue 29 Sep · 14:00-15:00   [Cancel] -> [Yes, cancel]|
| Orders: #41 · 2 books · S$29.80 · Paid                                         |
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
| | (Price) (Year) (Pages) (Stock)           |  |  ▒▒▒ title/author  |           |
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
| tiles: Revenue | Paid orders | Avg order | Payment success | Members           |
+--------------------------------------------------------------------------------+
| Revenue by category  ████████ S$120  | Top 5 books ██████                       |
| Orders per day (14)  ▂▃▅▇▅▃ columns  | Bookings per room ████ | Busiest hours  |
+--------------------------------------------------------------------------------+
| Pending books: ▒ title · by member · [Approve] [Reject]                        |
| Books table: serial | title | price( ) | stock( ) | flags | [Save] [Delete]     |
| Rooms: Folio · open [Close room]                                               |
+--------------------------------------------------------------------------------+
```
