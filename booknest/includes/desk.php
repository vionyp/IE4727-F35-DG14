<?php
// Desk codes and QR codes. Every loan and room booking has a short code such as L59-7F3A9C. The member
// shows it (or its QR code) at the front desk, and a librarian scans or types it on the admin dashboard
// to see that loan or booking and act on it. The part after the dash is a signature, so a code cannot
// be guessed by changing the number.

// Returns the desk code of a loan ('L') or a room booking ('R').
function desk_code(string $kind, int $id): string
{
    return $kind . $id . '-' . strtoupper(substr(hash_hmac('sha256', $kind . $id, DESK_SECRET), 0, 6));
}

// Reads a desk code typed or scanned at the desk. Returns ['kind' => 'L' or 'R', 'id' => 59], or null
// if it is not a genuine code. Accepts the full desk link as well, since that is what a QR code holds.
function desk_parse(string $input): ?array
{
    $input = strtoupper(trim($input));
    if (preg_match('/DESK=([LR]\d{1,9}-[0-9A-F]{6})/', $input, $m)) {
        $input = $m[1];
    }
    if (!preg_match('/^([LR])(\d{1,9})-([0-9A-F]{6})$/', $input, $m)) {
        return null;
    }
    $id = (int) $m[2];
    return hash_equals(desk_code($m[1], $id), $input) ? ['kind' => $m[1], 'id' => $id] : null;
}

// Returns a full link to a page of the site, on the host the current page was opened with (in a
// scheduled task there is no page, so it uses localhost). Emails and QR codes need full links.
function app_url(string $path = ''): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . BASE_URL . $path;
}

// The link a QR code opens: the desk check on the admin dashboard. A phone cannot open "localhost" (to
// the phone that is itself, and many cameras show no link at all), so when the page was opened as
// localhost the QR uses the laptop's network address instead, which a phone on the same Wi-Fi can reach.
function desk_url(string $code): string
{
    $url = app_url('admin.php?desk=' . rawurlencode($code));
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
        $ip = gethostbyname(gethostname());
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '127.')) {
            $url = preg_replace('#^(https?://)[^/:]+#', '${1}' . $ip, $url);
        }
    }
    return $url;
}

// Draws a QR code as an inline SVG (no image library needed), or returns '' if the QR encoder in
// lib/qrcode.php is not installed. The SVG is made only of rectangles built here, so it is safe to print.
function qr_svg(string $text, string $label): string
{
    $lib = APP_ROOT . '/lib/qrcode.php';
    if (!is_file($lib)) {
        return '';
    }
    // If the encoder fails for any reason, the page still works and shows the typed code only.
    try {
        require_once $lib;
        $qr = QRCode::getMinimumQRCode($text, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount();
    } catch (Throwable $e) {
        error_log('[' . date('c') . '] QR code could not be drawn: ' . $e->getMessage());
        return '';
    }
    $quiet = 4;   // the white border a scanner needs
    $size = $n + 2 * $quiet;
    $path = '';
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if ($qr->isDark($r, $c)) {
                $path .= 'M' . ($c + $quiet) . ' ' . ($r + $quiet) . 'h1v1h-1z';
            }
        }
    }
    return '<svg class="qr" viewBox="0 0 ' . $size . ' ' . $size . '" width="240" height="240" role="img" aria-label="' . e($label) . '"'
        . ' shape-rendering="crispEdges"><rect width="' . $size . '" height="' . $size . '" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
}

// The fold-out "Desk code" block shown with a loan or a booking on My Account.
function desk_block(string $code, string $what, string $hint): string
{
    $svg = qr_svg(desk_url($code), 'QR code for ' . $what);
    return '<details class="desk-code"><summary>' . icon('qr', 16) . ' Show QR code to collect</summary><div class="desk-code-body">'
        . $svg . '<p><strong class="desk-code-text">' . e($code) . '</strong><br><span class="small muted">' . e($hint) . '</span></p></div></details>';
}

// What the member should do with a loan's code: it is for collecting the book at the desk.
function desk_hint(array $loan): string
{
    return $loan['collection_date'] > date('Y-m-d')
        ? 'Show this at the front desk from ' . long_date($loan['collection_date']) . ' to collect the book.'
        : 'Your book is ready. Show this at the front desk to collect it.';
}
