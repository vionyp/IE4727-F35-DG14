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
define('TEAM_NAMES', 'Mathew Prajogo');
define('TEAM_EMAIL', 'mathew@prajogo.com');

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

// Shop
define('DELIVERY_FEE', 3.50);
define('FREE_DELIVERY_FROM', 40.00);
define('MAX_QTY_PER_BOOK', 10);

// Payment is handled by a third party; the simulator lets the demo choose the outcome.
define('PAYMENT_SIMULATOR', true);

// Mail: only local accounts on the XAMPP mail server (Mercury) may receive email.
define('MAIL_LOCAL_DOMAIN', '@localhost');
define('MAIL_FROM', 'postmaster@localhost'); // must be an existing Mercury mailbox, or Mercury rejects the message

// Cookies
define('RECENT_COOKIE', 'bn_recent');
define('EMAIL_COOKIE', 'bn_email');
define('RECENT_LIMIT', 6);
