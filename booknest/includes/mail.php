<?php
// Email: through the local XAMPP mail server (Mercury, only @localhost mailboxes), or through Gmail with
// PHPMailer when MAIL_TRANSPORT is 'gmail' (config/config.php). Every message is also written to storage/mail.log.

// Returns true if the address belongs to the local mail server.
function is_local_address(string $email): bool
{
    return str_ends_with(strtolower($email), strtolower(MAIL_LOCAL_DOMAIN));
}

// Sends a plain text email. With Mercury only local accounts receive it; anything else is refused and logged.
function send_mail(string $to, string $subject, string $body): bool
{
    $sent = false;
    if (preg_match('/[\r\n]/', $to . $subject)) {
        $status = 'refused: header injection attempt';
    } elseif (MAIL_TRANSPORT === 'gmail' && (!is_local_address($to) || MAIL_DEMO_INBOX !== '')) {
        // Real addresses go through Gmail. @localhost demo accounts still go to Mercury below, because
        // Gmail cannot deliver to them (unless MAIL_DEMO_INBOX collects them in one real inbox).
        [$sent, $status] = send_mail_gmail($to, $subject, $body);
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

// Sends one email through Gmail's SMTP server with PHPMailer. Returns [sent, status for the log].
function send_mail_gmail(string $to, string $subject, string $body): array
{
    $lib = APP_ROOT . '/lib/PHPMailer/';
    if (!is_file($lib . 'PHPMailer.php')) {
        return [false, 'failed: PHPMailer is not installed in lib/PHPMailer'];
    }
    if (SMTP_USER === '' || SMTP_PASS === '') {
        return [false, 'failed: SMTP_USER and SMTP_PASS are not set in config/config.php'];
    }
    // The demo accounts end in @localhost, which Gmail cannot reach: those go to the demo inbox.
    $deliverTo = $to;
    if (MAIL_DEMO_INBOX !== '') {
        $deliverTo = MAIL_DEMO_INBOX;
        $subject = '[for ' . $to . '] ' . $subject;
    } elseif (is_local_address($to)) {
        return [false, 'refused: ' . $to . ' is a local account; set MAIL_DEMO_INBOX to receive it in a real inbox'];
    } elseif (trim(MAIL_REAL_RECIPIENTS) !== '*'
        && !in_array(strtolower($to), array_map('strtolower', array_map('trim', explode(',', MAIL_REAL_RECIPIENTS))), true)) {
        // The seed has made-up addresses that may belong to real strangers: only listed people get real email.
        return [false, 'refused: ' . $to . ' is not in MAIL_REAL_RECIPIENTS, so no real email is sent to it'];
    }
    require_once $lib . 'Exception.php';
    require_once $lib . 'PHPMailer.php';
    require_once $lib . 'SMTP.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Timeout = 10;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_USER, SITE_NAME);
        $mail->addAddress($deliverTo);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return [true, 'sent through Gmail to ' . $deliverTo];
    } catch (Throwable $e) {
        return [false, 'failed: Gmail said ' . $mail->ErrorInfo];
    }
}
