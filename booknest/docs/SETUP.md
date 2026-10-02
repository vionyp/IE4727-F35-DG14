# BookNest: Setup (fresh XAMPP to running site in about 10 minutes)

## 1. Requirements

- Windows with **XAMPP** (tested with PHP 8.2.12, MariaDB 10.4.32, Apache 2.4, Mercury/32 4.62).
- No internet connection is needed to run the site.

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
| Member | `aisha@localhost` | `Member123!` |
| Member | `ben@localhost` | `Member123!` (already used his study hour today) |
| Member | `chloe@localhost` | `Member123!` |

Useful demo facts (dates move with the import day):

- **Aisha** has *Atomic Habits* due back in 9 days, *The Silent Patient* 4 days overdue
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
   **Configuration > Manage local users**, and add `aisha`, `ben` and `chloe`.
5. Check it works: `C:\xampp\php\php.exe tools\mail-test.php admin@localhost`, then look in
   `C:\xampp\MercuryMail\MAIL\Admin` (or read it in any mail client pointed at Mercury).

The site never sends to an address outside `@localhost`; those are refused and logged.

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
