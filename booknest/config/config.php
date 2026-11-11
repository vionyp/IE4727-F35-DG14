<?php
// Central configuration. Every environment specific value lives here and nowhere else.

// Database (default XAMPP credentials)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'booknest');

// Site
define('SITE_NAME', 'BookNest');
define('BASE_URL', '/booknest/');
define('APP_TIMEZONE', 'Asia/Singapore');
define('APP_ROOT', dirname(__DIR__));
define('STORAGE_PATH', APP_ROOT . '/storage');
define('DEBUG', false);

// Team credits shown in the footer
define('TEAM_NAMES', 'Mathew Prajogo and Viony Prajogo');
define('TEAM_EMAIL', 'mathew@prajogo.com');
define('TEAM_EMAIL_2', 'vionyprajogo123@gmail.com');

// Sessions
define('SESSION_IDLE_SECONDS', 30 * 60);

// Library opening hours (24 hour clock, also the study room hours)
define('OPEN_HOUR', 10);
define('CLOSE_HOUR', 21);
define('BRANCH_NAME', 'BookNest Reading Room');
define('BRANCH_ADDRESS', '50 Nanyang Avenue, Singapore 639798');

// Study room rules
define('SLOT_MINUTES', 30);
define('MAX_BOOKING_MINUTES', 60);
define('DAILY_CAP_MINUTES', 60);
define('ADVANCE_DAYS', 7);

// Lending rules (PHP enforces every one of them)
define('LOAN_DAYS', 14);               // a loan runs 14 days from the collection date
define('COLLECT_AHEAD_DAYS', 7);       // the collection date can be today up to 7 days ahead
define('QUEUE_HOLD_DAYS', 2);          // the next reader in a queue has 2 days to borrow a returned copy
define('LATE_FEE_PER_DAY', 0.50);      // in SGD, for every day after the due date
define('MAX_LOANS', 6);                // open loans (reserved, out or overdue) a member may hold at once
define('FEE_BLOCK_LIMIT', 5.00);       // unpaid late fees from this amount stop new loans until settled at the desk
define('MAX_RENEWALS', 1);             // times a loan can be extended by another LOAN_DAYS
define('DUE_SOON_DAYS', 2);            // loans due within this many days get a reminder on Home and My Account

// Mail: only local accounts on the XAMPP mail server (Mercury) may receive email.
define('MAIL_LOCAL_DOMAIN', '@localhost');
define('MAIL_FROM', 'postmaster@localhost'); // must be an existing Mercury mailbox, or Mercury rejects the message

// Cookies
define('RECENT_COOKIE', 'bn_recent');
define('EMAIL_COOKIE', 'bn_email');
define('TOUR_COOKIE', 'bn_tour');  // onboarding tour progress, written by assets/js/tour.js
define('RECENT_LIMIT', 6);

// Live updates (Modern Enhancement 2): a small Node.js + Express server in live/ pushes "something
// changed" to open pages. The site works the same without it; pages then update on reload as before.
define('LIVE_PORT', 3000);                                 // the browser connects to this port on the same host
define('LIVE_NOTIFY_URL', 'http://127.0.0.1:3000/notify'); // PHP tells the live server about changes here
define('LIVE_SECRET', 'change-me-booknest-live-2026');     // shared with live/server.js (it reads this file)
define('LIVE_TOKEN_SECONDS', 2 * 60 * 60);                 // how long a page's live pass is valid

// Desk codes and QR codes (includes/desk.php): signs each loan's and booking's code so it cannot be guessed.
define('DESK_SECRET', 'change-me-booknest-desk-2026');

// Loan reminder emails (includes/reminders.php). DUE_SOON_DAYS above sets how early "due soon" goes out.
define('REMIND_OVERDUE_DAYS', 7);   // an overdue book is reminded on its first late day, then every 7 days

// How email is delivered (includes/mail.php). Every email is also written to storage/mail.log.
//   'mercury'  XAMPP's own mail server, only to @localhost mailboxes (the default; start Mercury in XAMPP)
//   'gmail'    real email through Gmail with PHPMailer (lib/PHPMailer). Needs a Gmail address and an
//              app password (Google Account > Security > 2-Step Verification > App passwords).
// Put your own Gmail settings in config/mail.local.php (git ignores it), for example:
//   <?php define('MAIL_TRANSPORT', 'gmail'); define('SMTP_USER', 'you@gmail.com');
//         define('SMTP_PASS', 'abcdabcdabcdabcd'); define('MAIL_DEMO_INBOX', 'you@gmail.com');
if (is_file(__DIR__ . '/mail.local.php')) {
    require __DIR__ . '/mail.local.php';
}
defined('MAIL_TRANSPORT') || define('MAIL_TRANSPORT', 'mercury');
defined('SMTP_HOST') || define('SMTP_HOST', 'smtp.gmail.com');
defined('SMTP_PORT') || define('SMTP_PORT', 587);
defined('SMTP_USER') || define('SMTP_USER', '');   // the Gmail address that sends
defined('SMTP_PASS') || define('SMTP_PASS', '');   // its 16 letter app password, never the normal password
// The demo accounts are @localhost, which Gmail cannot deliver to. With 'gmail', every email goes to
// this one real inbox instead, with the intended member in the subject. Leave '' to send to real addresses only.
defined('MAIL_DEMO_INBOX') || define('MAIL_DEMO_INBOX', '');
// Real addresses that may receive email through Gmail when MAIL_DEMO_INBOX is '' (comma separated),
// or '*' for everyone (only once every account has its owner's real address, not the test data).
// Everyone else is refused, because the test data contains made-up addresses that real people may own.
defined('MAIL_REAL_RECIPIENTS') || define('MAIL_REAL_RECIPIENTS', '');
