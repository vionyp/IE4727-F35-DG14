<?php
// Sends the loan reminder emails that are due (ready to collect, due soon, due today, overdue).
// Run by hand:        C:\xampp\php\php.exe tools\send-reminders.php
// Or every morning:   see docs/SETUP.md, "Loan reminders" (Windows Task Scheduler).
// Each reminder is sent once; running it twice in a day sends nothing new.
require __DIR__ . '/../includes/bootstrap.php';

$counts = send_loan_reminders();
echo date('Y-m-d H:i') . '  Sent ' . reminder_summary($counts) . ".\n";
if ($counts['failed']) {
    echo $counts['failed'] . " could not be sent (is the mail server running?). They will be tried again next time.\n";
}
exit($counts['failed'] ? 1 : 0);
