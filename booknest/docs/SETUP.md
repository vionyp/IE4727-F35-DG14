# BookNest: Setup (fresh XAMPP to running site in about 10 minutes)

## 1. Requirements

- Windows with **XAMPP** (tested with PHP 8.2.12, MariaDB 10.4.32, Apache 2.4, Mercury/32 4.62).
- No internet connection is needed to run the site.
- Optional, for live updates: **Node.js 18 or newer** (tested with Node 24). See section 6b.

## 2. Put the files where Apache can see them

Either copy the `booknest` folder to `C:\xampp\htdocs\booknest`, or keep it where it is and
link it (run Command Prompt as usual, no admin needed for a junction):

```
mklink /J C:\xampp\htdocs\booknest "C:\webdesign\FINAL PROJECT\booknest"
```

The site must be opened through Apache at `http://localhost/booknest/`, never as a file.

## 3. Start the servers

Open the **XAMPP Control Panel** and press **Start** for **Apache** and **MySQL**
(and **Mercury** if you want real emails, see step 6).

## 4. Create the database

1. Open `http://localhost/phpmyadmin`.
2. **Import** `sql/schema.sql` (creates the `booknest` database and its 9 tables).
3. **Import** `sql/seed.sql` (44 books, 9 users, 6 rooms, 68 loans, 5 queue places, about 100 bookings).

Or from a terminal in the project folder:

```
C:\xampp\mysql\bin\mysql.exe -uroot < sql\schema.sql
C:\xampp\mysql\bin\mysql.exe -uroot --default-character-set=utf8mb4 < sql\seed.sql
```

Import both again at any time to reset the demo. Dates in the seed are relative to the moment
of import, so re-import on the morning of the demo for the freshest dashboard.

## 4b. Download the book covers (once, needs internet)

```
C:\xampp\php\php.exe tools\fetch-covers.php
```

This saves the real cover of every book with an ISBN into `assets/covers/real/` (about 2 MB).
The folder is not in Git, so each teammate runs this once. If you skip it, or a cover cannot be
downloaded, the site shows its own generated cover for that book instead.

## 5. Open the site

`http://localhost/booknest/`

| Role | Email | Password |
|---|---|---|
| Admin | `admin@localhost` | `Admin123!` |
| Member | `budy@localhost` | `Member123!` |
| Member | `ben@localhost` | `Member123!` (already used his study hour today) |
| Member | `chloe@localhost` | `Member123!` |

Useful demo facts (dates move with the import day):

- **Budy** has *Atomic Habits* due back in 9 days, *The Silent Patient* 4 days overdue
  (S$2.00 owed), and is #2 in the queue for *Fourth Wing*. She also has a study room booked
  tomorrow at 14:00 and 4 books on her shelf.
- **Ben** is 12 days overdue with *The Psychology of Money* (S$6.00 owed).
- **Chloe** has *Dune* reserved, to collect in 2 days (shown as "Cancel reservation").
- *Fourth Wing*: both copies out, 3 people waiting. *Project Hail Mary*: its only copy is overdue,
  1 person waiting. *The Housemaid*: returned yesterday and held for Hana. *Dracula*: no copies.
- Two member submissions wait for approval in the admin dashboard, which shows S$10.00 of late
  fees outstanding.
- `BNT-000123` (The Da Vinci Code) is a good serial number to look up, and searching the ISBN
  `9780735211292` finds Atomic Habits.

## 6. Email (optional, for real delivery)

Every email is always written to `storage/mail.log`, so the demo works without this step.
For real delivery to local mailboxes:

1. `php.ini` already contains `SMTP=localhost` and `smtp_port=25` in a default XAMPP.
2. Start **Mercury** in the XAMPP Control Panel.
3. Mercury only accepts mail for mailboxes that exist. `admin` exists by default, so mail to
   `admin@localhost` (for example a loan by the admin account) is delivered straight away.
4. To receive mail for the other demo users, open Mercury, go to
   **Configuration > Manage local users**, and add `budy`, `ben` and `chloe`.
5. Check it works: `C:\xampp\php\php.exe tools\mail-test.php admin@localhost`, then look in
   `C:\xampp\MercuryMail\MAIL\Admin` (or read it in any mail client pointed at Mercury).

The site never sends to an address outside `@localhost`; those are refused and logged.

## 6b. Live updates (optional, Node.js + Express)

Without this step every page is correct when it loads, and changes made by someone else show after a
reload. With it, open pages update by themselves: Budy's fees change the moment the admin presses
**Mark as paid**, and a hand-in appears on the admin dashboard straight away.

1. Install Node.js 18 or newer from nodejs.org.
2. Once, in a terminal: `cd booknest\live` then `npm install` (downloads Express into `live\node_modules`).
3. Every time, next to Apache and MySQL: `cd booknest\live` then `npm start`. Leave the window open.
   It prints `BookNest live server on http://localhost:3000`, and one line for each change it passes on.
4. Check it: open http://localhost:3000/ and you see `pagesListening`, the number of open BookNest pages.

The port and the shared secret are `LIVE_PORT` and `LIVE_SECRET` in `config/config.php`; the Node
server reads them from that file too.

## 6c. Loan reminders and real email (optional)

BookNest emails members when a reserved book is ready to collect, when a loan is due in 2 days, on
the due day, and when it is overdue (then every 7 days). Each reminder is sent once.

- **By hand:** the admin dashboard shows "Loan reminders" with a **Send reminders now** button, or run
  `C:\xampp\php\php.exe tools\send-reminders.php`.
- **Every morning:** let Windows run it at 09:00 (one command, in a terminal):
  `schtasks /Create /SC DAILY /ST 09:00 /TN "BookNest reminders" /TR "C:\xampp\php\php.exe \"C:\xampp\htdocs\booknest\tools\send-reminders.php\""`
  (change the path to where your booknest folder is). Remove it with `schtasks /Delete /TN "BookNest reminders"`.
- A reminder only counts as sent when the mail server takes it. With Mercury stopped and Gmail not set
  up, the emails are still written to `storage/mail.log`, but they wait and are tried again next time.

**Real email through Gmail (PHPMailer):**

1. PHPMailer 6.9.3 must be in `lib/PHPMailer/` (`PHPMailer.php`, `SMTP.php`, `Exception.php`, from
   github.com/PHPMailer/PHPMailer, folder `src`).
2. In your Google Account turn on 2-Step Verification, then create an **App password** (16 letters).
3. Create `config/mail.local.php` (git ignores it, so the password never reaches GitHub):
   ```php
   <?php
   define('MAIL_TRANSPORT', 'gmail');
   define('SMTP_USER', 'your.address@gmail.com');
   define('SMTP_PASS', 'abcdabcdabcdabcd');          // the app password, without spaces
   define('MAIL_DEMO_INBOX', 'your.address@gmail.com'); // the demo accounts are @localhost: their mail comes here
   ```
   Or, to email real people, leave `MAIL_DEMO_INBOX` as `''` and list them:
   `define('MAIL_REAL_RECIPIENTS', 'viony001@e.ntu.edu.sg');`. Nobody else gets real email, because the
   test data has made-up addresses (such as budy@gmail.com) that real people may own.
4. Press **Send reminders now** and check the inbox. Each subject starts with `[for budy@localhost]`.

## 6d. Collecting a book with the QR code

A borrowed book waits at the front desk until the member collects it. My Account shows **Show QR code
to collect** under it, with a code such as `L59-7F3A9C`. The librarian scans the QR code with a phone
signed in as admin, or types the code into **Collection desk** on the admin dashboard, checks the
member, and presses **Hand over the book**. The QR codes need the encoder in `lib/qrcode.php`
(qrcode-generator by Kazuhiko Arase, MIT); without it only the typed code is shown.

When the site is opened as `localhost`, the QR link uses the laptop's network address (for example
`http://10.91.202.59/booknest/`), so a phone on the same Wi-Fi can open it. Campus Wi-Fi or Windows
Firewall may block that; typing the code always works.

## 7. Settings

Everything configurable is in `config/config.php`: database login, opening hours, room rules,
lending rules (`LOAN_DAYS`, `COLLECT_AHEAD_DAYS`, `QUEUE_HOLD_DAYS`, `LATE_FEE_PER_DAY`), and the
mail domain and sender.

## 8. Run the automated tests (optional)

```
C:\xampp\php\php.exe tools\e2e-test.php
```

It runs 59 checks against the live site and prints PASS/FAIL for each. It creates test users,
loans, queue places and bookings and changes some books' copies, so re-import the two SQL files
afterwards.

## 9. Rebuild the seed and covers (optional)

```
C:\xampp\php\php.exe tools\build-seed.php
```

Needs internet the first time (downloads the public domain texts into `tools/cache`).
Regenerates `sql/seed.sql`, every cover in `assets/covers/` and every room plan.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Something went wrong on our side" | MySQL is not running, or the database was not imported. Details are in `storage/error.log`. |
| Pages show PHP code | You opened the file directly. Use `http://localhost/booknest/`. |
| "Your session expired before the form was sent" | The page was open for a long time; reload and try again. |
| Emails not arriving | Check Mercury is running and the mailbox exists (step 6). `storage/mail.log` shows the status of every message. |
