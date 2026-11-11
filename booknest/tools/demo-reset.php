<?php
// Puts the database back in the state the live demo expects, then checks it.
// Run from the project root, with MySQL started:  C:\xampp\php\php.exe tools\demo-reset.php
//
// What it does:
//   1. Saves the current database to a backup file (the path is printed), so nothing is lost.
//   2. Re-imports sql/schema.sql and sql/seed.sql.
//   3. Makes Budy's "The Silent Patient" an on-time loan (in the seed it is overdue, which would stop
//      Budy borrowing in Step 3 of the demo script), and makes "Atomic Habits" due in 2 days, so
//      "Send reminders now" on the admin dashboard emails Budy a due soon reminder before the renew.
//   4. Checks every demo item and prints a checklist.
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/loans.php';
require __DIR__ . '/../includes/desk.php';
require __DIR__ . '/../includes/reminders.php';

date_default_timezone_set(APP_TIMEZONE);
$bin = 'C:\\xampp\\mysql\\bin\\';
$auth = '-u' . DB_USER . (DB_PASS !== '' ? ' -p' . DB_PASS : '');

// 1. Backup.
$backup = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'booknest-before-demo-reset-' . date('Ymd-His') . '.sql';
exec("\"{$bin}mysqldump.exe\" $auth --default-character-set=utf8mb4 --routines --databases " . DB_NAME . ' > "' . $backup . '"', $out, $code);
if ($code !== 0 || !filesize($backup)) {
    exit("Could not back up the database. Is MySQL running in XAMPP?\n");
}
echo "Backup saved: $backup\n";

// 2. Fresh schema and seed.
foreach (['schema.sql', 'seed.sql'] as $file) {
    exec("\"{$bin}mysql.exe\" $auth --default-character-set=utf8mb4 < \"" . APP_ROOT . "/sql/$file\"", $out, $code);
    if ($code !== 0) {
        exit("Importing sql/$file failed. Restore the backup above if needed.\n");
    }
}
echo "Fresh schema and seed imported.\n";

// 3. Budy's Silent Patient: collected 3 days ago, due in 11 days, never renewed.
$budy = (int) db_value("SELECT id FROM users WHERE email = 'budy@localhost'");
db_exec("UPDATE loans l JOIN books b ON b.id = l.book_id
         SET l.collection_date = CURDATE() - INTERVAL 3 DAY, l.due_date = CURDATE() + INTERVAL 11 DAY, l.status = 'active', l.renewals = 0
         WHERE l.user_id = ? AND b.title = 'The Silent Patient' AND l.returned_date IS NULL", [$budy]);
db_exec("UPDATE loans l JOIN books b ON b.id = l.book_id
         SET l.collection_date = CURDATE() - INTERVAL 12 DAY, l.due_date = CURDATE() + INTERVAL 2 DAY, l.status = 'active', l.renewals = 0
         WHERE l.user_id = ? AND b.title = 'Atomic Habits' AND l.returned_date IS NULL", [$budy]);

// 4. Checklist.
$open = fn(string $title) => db_one("SELECT l.due_date, l.renewals, l.status FROM loans l JOIN books b ON b.id = l.book_id
                                     WHERE l.user_id = ? AND b.title = ? AND l.returned_date IS NULL", [$budy, $title]);
$avail = fn(string $title) => db_one("SELECT b.stock, b.stock - (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.status <> 'returned')
                                      - (SELECT COUNT(*) FROM book_queue q WHERE q.book_id = b.id AND q.status = 'offered') AS free,
                                      (SELECT COUNT(*) FROM book_queue q WHERE q.book_id = b.id AND q.user_id = ? AND q.status IN ('waiting','offered')) AS budy_queued
                                      FROM books b WHERE b.title = ?", [$budy, $title]);
$atomic = $open('Atomic Habits');
$silent = $open('The Silent Patient');
$hail = $avail('Project Hail Mary');
$pride = $avail('Pride and Prejudice');
$blocked = (int) db_value("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status <> 'returned' AND due_date < CURDATE()", [$budy]);
$todayMinutes = (int) db_value("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) FROM room_bookings
                                WHERE user_id = ? AND status = 'confirmed' AND booking_date = CURDATE()", [$budy]);
$fees = (int) db_value("SELECT COUNT(*) FROM loans WHERE status = 'returned' AND fee_cleared_at IS NULL AND returned_date > due_date");
$pending = (int) db_value("SELECT COUNT(*) FROM books WHERE status = 'pending'");
$budyReminder = (bool) array_filter(reminders_due(), fn($r) => (int) $r['user_id'] === $budy && $r['title'] === 'Atomic Habits' && $r['kind'] === 'due_soon');

$checks = [
    ['Browse + borrow: Pride and Prejudice is on the shelf', $pride && (int) $pride['free'] > 0 && !(int) $pride['budy_queued'] && !$open('Pride and Prejudice')],
    ['Budy can borrow (nothing overdue)', $blocked === 0],
    ['Renew: Atomic Habits, due in 2 days, not renewed yet', $atomic && (int) $atomic['renewals'] === 0 && $atomic['due_date'] === date('Y-m-d', strtotime('+2 day'))],
    ['Reminders: Budy gets a "due soon" email for Atomic Habits', $budyReminder],
    ['Return: The Silent Patient, on time', $silent && $silent['due_date'] >= date('Y-m-d')],
    ['Queue: Project Hail Mary has no free copy, Budy not queued', $hail && (int) $hail['free'] <= 0 && (int) $hail['stock'] > 0 && !(int) $hail['budy_queued']],
    ['Rooms: Budy has no booking today (book 60 min, then hit the cap)', $todayMinutes === 0],
    ["Admin: $fees late fees to settle, $pending books waiting for review", $fees > 0],
];
echo "\nDemo checklist\n";
$ok = true;
foreach ($checks as [$label, $pass]) {
    echo ($pass ? '  [ok]   ' : '  [FAIL] ') . $label . "\n";
    $ok = $ok && $pass;
}
echo "\nLogins: budy@localhost / Member123!   admin@localhost / Admin123!\n";
echo $ok ? "Ready for the demo.\n" : "Something is not as expected; see [FAIL] above.\n";
exit($ok ? 0 : 1);
