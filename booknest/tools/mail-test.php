<?php
// Sends one test email to a local mailbox, to check the Mercury set up described in docs/SETUP.md.
// Usage: C:\xampp\php\php.exe tools\mail-test.php admin@localhost
require __DIR__ . '/../includes/bootstrap.php';

$to = $argv[1] ?? 'admin@localhost';
$ok = send_mail($to, SITE_NAME . ' mail test', mail_body('there', 'If you can read this in your mail client, local email works.'));
echo $ok ? "Handed to the mail server for $to. Check the mailbox in a minute.\n" : "Not sent. See storage/mail.log for the reason.\n";
