<?php
// General helpers: escaping, URLs, redirects, request input, form state, formatting.

// Escapes a value for safe output in HTML text and attributes.
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Builds an absolute site path from a relative one.
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

// Builds an asset URL with a version stamp so browsers pick up changes.
function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . $path) . '?v=' . $v;
}

// Sends a redirect to a site path and stops the script.
function redirect(string $path): never
{
    $target = str_starts_with($path, BASE_URL) ? $path : url($path);
    header('Location: ' . $target, true, 303);
    exit;
}

// Returns true when the current request is a POST.
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// Rejects anything but POST for form endpoints.
function require_post(): void
{
    if (!is_post()) {
        flash('error', 'That action needs a form submission.');
        redirect('');
    }
}

// Reads and trims a string from an input array, capped at a maximum length.
function input(array $source, string $key, int $max = 255): string
{
    $v = $source[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $max);
}

// Reads a positive integer from an input array, or 0.
function input_int(array $source, string $key): int
{
    $v = $source[$key] ?? '';
    return (is_string($v) && ctype_digit($v)) ? (int) $v : 0;
}

// Accepts only a local path inside the site, for safe "return to" redirects.
function safe_return(?string $path, string $fallback = ''): string
{
    if (is_string($path) && str_starts_with($path, BASE_URL) && !str_contains($path, '//')
        && !preg_match('/[\r\n]/', $path)) {
        return $path;
    }
    return url($fallback);
}

// Returns the page the user came from if it is ours, for redirects after an action.
function back_url(string $fallback = ''): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $path = parse_url($ref, PHP_URL_PATH);
    $query = parse_url($ref, PHP_URL_QUERY);
    $host = parse_url($ref, PHP_URL_HOST);
    $ownHost = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    if ($host && strcasecmp($host, $ownHost) !== 0) {
        return url($fallback);
    }
    return safe_return($path ? $path . ($query ? '?' . $query : '') : null, $fallback);
}

// Stores submitted values and field errors so the form can be shown again after a redirect.
function keep_form(string $form, array $old, array $errors): void
{
    unset($old['password'], $old['confirm'], $old['csrf']);
    $_SESSION['form'] = ['name' => $form, 'old' => $old, 'errors' => $errors];
}

// Returns the kept form state once, then clears it.
function form_state(): array
{
    static $state = null;
    if ($state === null) {
        $state = $_SESSION['form'] ?? ['name' => '', 'old' => [], 'errors' => []];
        unset($_SESSION['form']);
    }
    return $state;
}

// Returns a previously submitted value for a field of the given form.
function old(string $form, string $key, string $default = ''): string
{
    $s = form_state();
    if ($s['name'] !== $form) {
        return $default;
    }
    $v = $s['old'][$key] ?? $default;
    return is_string($v) ? $v : $default;
}

// Returns the error message for a field of the given form, or an empty string.
function field_error(string $form, string $key): string
{
    $s = form_state();
    return $s['name'] === $form ? ($s['errors'][$key] ?? '') : '';
}

// Prints aria-invalid and aria-describedby for a field, joining an optional hint id with its error id.
function field_attrs(string $form, string $key, string $hintId = ''): string
{
    $err = field_error($form, $key);
    $ids = trim($hintId . ($err ? ' ' . $form . '-' . $key . '-error' : ''));
    return ($err ? ' aria-invalid="true"' : '') . ($ids !== '' ? ' aria-describedby="' . e($ids) . '"' : '');
}

// Prints the inline error message element for a field (empty but present for JS).
function field_error_html(string $form, string $key): string
{
    $err = field_error($form, $key);
    return '<p class="field-error" id="' . e($form . '-' . $key . '-error') . '"'
        . ($err ? '' : ' hidden') . '>' . e($err) . '</p>';
}

// Formats an amount in Singapore dollars (used for late fees and, for staff, replacement values).
function money(float|string|null $amount): string
{
    return 'S$' . number_format((float) $amount, 2);
}

// Formats a date as "Mon 28 Sep".
function short_date(string $date): string
{
    return date('D j M', strtotime($date));
}

// Formats a date as "Monday 28 September".
function long_date(string $date): string
{
    return date('l j F', strtotime($date));
}

// Formats a TIME column as "14:00".
function hm(string $time): string
{
    return substr($time, 0, 5);
}

// Returns a rating as accessible star text, for example "Rated 4.5 out of 5".
function rating_html(float|string $rating): string
{
    $r = number_format((float) $rating, 1);
    return '<span class="rating"><span aria-hidden="true">&#9733; ' . $r . '</span><span class="visually-hidden">Rated ' . $r . ' out of 5</span></span>';
}

// Reports whether the library is open now and the next change of state.
function library_status(?int $now = null): array
{
    $now ??= time();
    $h = (int) date('G', $now);
    $open = $h >= OPEN_HOUR && $h < CLOSE_HOUR;
    $fmt = fn(int $x) => sprintf('%02d:00', $x);
    return $open
        ? ['open' => true, 'label' => 'Open now', 'detail' => 'Closes at ' . $fmt(CLOSE_HOUR)]
        : ['open' => false, 'label' => 'Closed', 'detail' => 'Opens at ' . $fmt(OPEN_HOUR) . ($h >= CLOSE_HOUR ? ' tomorrow' : '')];
}

// Returns a book URL.
function book_url(int $id): string
{
    return url('book.php?id=' . $id);
}

// Returns the book ids stored in the recently viewed cookie, ignoring anything invalid.
function recent_ids(): array
{
    $raw = $_COOKIE[RECENT_COOKIE] ?? '';
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $ids = [];
    foreach (explode(',', $raw) as $part) {
        if (ctype_digit($part) && (int) $part > 0) {
            $ids[] = (int) $part;
        }
    }
    return array_slice(array_values(array_unique($ids)), 0, RECENT_LIMIT);
}

// Adds a book to the front of the recently viewed cookie.
function remember_recent(int $id): void
{
    $ids = array_slice(array_values(array_unique(array_merge([$id], recent_ids()))), 0, RECENT_LIMIT);
    setcookie(RECENT_COOKIE, implode(',', $ids), [
        'expires' => time() + 30 * 86400, 'path' => BASE_URL, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    $_COOKIE[RECENT_COOKIE] = implode(',', $ids);
}

// Returns true when the current script matches the given page file, for active navigation.
function is_page(string $file): bool
{
    return basename($_SERVER['SCRIPT_NAME'] ?? '') === $file;
}

// Returns the current request path with query string, for "return to this page" links.
function current_path(): string
{
    return $_SERVER['REQUEST_URI'] ?? url();
}

// Writes a line to the application log with the visitor's address.
function app_log(string $message): void
{
    error_log('[' . date('c') . '] ' . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . ' ' . $message);
}
