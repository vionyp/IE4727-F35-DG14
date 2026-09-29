# BookNest: Site map

One home page and nine content pages (the course allows at most ten). Every page shares the
same header (logo, navigation, search, cart, account) and footer, so every page is one click
from Home, Browse, Study Rooms, Cart and Account. The hierarchy is wide and shallow: nothing
is more than three levels from the home page.

## Page hierarchy

```mermaid
flowchart TD
    H["Home<br/>index.php"]
    C["Browse<br/>catalogue.php"]
    B["Book detail<br/>book.php?id="]
    R["Sample reader<br/>read.php?id="]
    K["Cart and checkout<br/>checkout.php"]
    S["Study rooms<br/>rooms.php"]
    A["My Account<br/>account.php"]
    I["Sign in / Register<br/>sign-in.php"]
    N["Add a book<br/>add-book.php"]
    D["Admin<br/>admin.php"]

    H --> C
    H --> B
    H --> S
    H --> I
    C --> B
    B --> R
    B --> K
    R --> K
    R --> B
    S --> I
    I --> A
    A --> N
    A --> B
    A --> S
    A --> D

    classDef level1 fill:#E5A93D,color:#1A1204,stroke:#1A1204
    classDef level2 fill:#1F2530,color:#F2EEE6,stroke:#A9A398
    classDef level3 fill:#171B22,color:#F2EEE6,stroke:#2A3140
    class H level1
    class C,S,A,I,K level2
    class B,R,N,D level3
```

Levels: **Home** (level 1) → **Browse, Study Rooms, Cart, Account, Sign in** (level 2, all in the global header) → **Book, Reader, Add a book, Admin** (level 3).

## Access by role

| Page | Visitor | Member | Admin |
|---|---|---|---|
| Home, Browse, Book, Reader, Checkout | Yes | Yes | Yes |
| Study Rooms (view table) | Yes | Yes | Yes |
| Study Rooms (book) | Inline sign in | Yes | Yes |
| Sign in / Register | Yes | Redirects to My Account | Redirects to My Account |
| My Account | Redirects to Sign in | Yes | Yes |
| Add a book | Redirects to Sign in | Yes | Yes |
| Admin | Redirects to Sign in | Refused, back to Home | Yes |

## Form endpoints (not pages)

Forms post to scripts in `process/`. They never output HTML: they validate, write to the
database, set a message and redirect back (Post/Redirect/Get).

```mermaid
flowchart LR
    subgraph Pages
      I[sign-in.php]
      B[book.php]
      K[checkout.php]
      S[rooms.php]
      A[account.php]
      N[add-book.php]
      D[admin.php]
    end
    subgraph process/
      L[login.php]
      G[register.php]
      O[logout.php]
      CT[cart.php]
      CO[checkout.php]
      BR[book-room.php]
      CR[cancel-room.php]
      SH[shelf.php]
      AB[add-book.php]
      AA[admin-action.php]
    end
    I --> L & G
    B --> CT & SH
    K --> CT & CO
    S --> BR & L
    A --> CR & SH & O
    N --> AB
    D --> AA
    CO -->|paid, member| A
    CO -->|paid, guest| K
    CO -->|failed| K
    BR --> S
    CR --> A
```
