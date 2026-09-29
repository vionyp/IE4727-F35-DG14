<?php
// Email through the local XAMPP mail server (Mercury), with a guard for external addresses.

// Returns true if the address belongs to the local mail server.
function is_local_address(string $email): bool
{
    return str_ends_with(strtolower($email), strtolower(MAIL_LOCAL_DOMAIN));
}

// Sends a plain text email to a local account; anything else is refused and logged.
function send_mail(string $to, string $subject, string $body): bool
{
    $sent = false;
    if (preg_match('/[\r\n]/', $to . $subject)) {
        $status = 'refused: header injection attempt';
    } elseif (!is_local_address($to)) {
        $status = 'refused: external recipient blocked (only ' . MAIL_LOCAL_DOMAIN . ' accounts receive mail)';
    } else {
        ini_set('sendmail_from', MAIL_FROM);
        ini_set('default_socket_timeout', '5');
        $headers = 'From: ' . SITE_NAME . ' <' . MAIL_FROM . ">\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";
        $sent = @mail($to, $subject, $body, $headers);
        $status = $sent ? 'sent to local mail server' : 'failed: local mail server (Mercury) not reachable';
    }
    mail_log($to, $subject, $body, $status);
    return $sent;
}

// Appends every message to storage/mail.log so the demo can always show what was sent.
function mail_log(string $to, string $subject, string $body, string $status): void
{
    $entry = str_repeat('=', 60) . "\n"
        . 'Date:    ' . date('Y-m-d H:i:s') . "\n"
        . 'To:      ' . $to . "\n"
        . 'Subject: ' . $subject . "\n"
        . 'Status:  ' . $status . "\n\n"
        . $body . "\n";
    file_put_contents(STORAGE_PATH . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
}

// Wraps a message body with the standard greeting and sign off.
function mail_body(string $name, string $content): string
{
    return "Hello " . $name . ",\n\n" . $content . "\n\nSee you soon,\nThe " . SITE_NAME . " team\n"
        . BRANCH_NAME . ', ' . BRANCH_ADDRESS . "\nOpen daily " . sprintf('%02d:00 to %02d:00', OPEN_HOUR, CLOSE_HOUR) . "\n";
}
