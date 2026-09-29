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
2. **Import** `sql/schema.sql` (creates the `booknest` database and its 8 tables).
3. **Import** `sql/seed.sql` (38 books, 9 users, 6 rooms, 50 orders, about 100 bookings).

Or from a terminal in the project folder:

```
C:\xampp\mysql\bin\mysql.exe -uroot < sql\schema.sql
C:\xampp\mysql\bin\mysql.exe -uroot --default-character-set=utf8mb4 < sql\seed.sql
```

Import both again at any time to reset the demo. Dates in the seed are relative to the moment
of import, so re-import on the morning of the demo for the freshest dashboard.

## 5. Open the site

`http://localhost/booknest/`

| Role | Email | Password |
|---|---|---|
| Admin | `admin@localhost` | `Admin123!` |
| Member | `aisha@localhost` | `Member123!` |
| Member | `ben@localhost` | `Member123!` (already used his study hour today) |
| Member | `chloe@localhost` | `Member123!` |

Useful demo facts: Aisha has a booking tomorrow at 14:00 (cancellable) and 4 books on her
shelf; two member submissions wait for approval in the admin dashboard; *The Moonstone* is
out of stock; `BNT-000123` is a good serial number to look up.

## 6. Email (optional, for real delivery)

Every email is always written to `storage/mail.log`, so the demo works without this step.
For real delivery to local mailboxes:

1. `php.ini` already contains `SMTP=localhost` and `smtp_port=25` in a default XAMPP.
2. Start **Mercury** in the XAMPP Control Panel.
3. Mercury only accepts mail for mailboxes that exist. `admin` exists by default, so orders
   placed with the email `admin@localhost` are delivered straight away.
4. To receive mail for the other demo users, open Mercury, go to
   **Configuration > Manage local users**, and add `aisha`, `ben` and `chloe`.
5. Check it works: `C:\xampp\php\php.exe tools\mail-test.php admin@localhost`, then look in
   `C:\xampp\MercuryMail\MAIL\Admin` (or read it in any mail client pointed at Mercury).

The site never sends to an address outside `@localhost`; those are refused and logged.

## 7. Settings

Everything configurable is in `config/config.php`: database login, opening hours, room rules,
delivery fee, mail domain and sender, and `PAYMENT_SIMULATOR` (set to `false` to hide the
success/failure switch at checkout).

## 8. Run the automated tests (optional)

```
C:\xampp\php\php.exe tools\e2e-test.php
```

It runs 44 checks against the live site and prints PASS/FAIL for each. It creates test users,
orders and bookings, so re-import the two SQL files afterwards.

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
