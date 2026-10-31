<?php
// Modern Enhancement: shared helpers for the JSON endpoints in api/.
// The endpoints load the normal bootstrap (session, idle timeout, loans_sync) and call the same rule
// functions as the PHP pages, so the Vue screens can never follow different rules from the Base Version.
// Differences from process/: they answer with JSON and a status code instead of a flash and a redirect.
require_once __DIR__ . '/bootstrap.php';

// Sends a JSON response and stops. The HEX flags turn < > & ' " into \u escapes, so the body can
// never be read as HTML, and nosniff stops a browser from guessing another type.
function api_send(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

// Sends an error message in the same words the PHP pages would flash.
function api_error(int $status, string $message, array $extra = []): never
{
    api_send($status, ['error' => $message] + $extra);
}

// Allows only the given HTTP methods; anything else gets 405 with an Allow header.
function api_method(string ...$allowed): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        api_error(405, 'This address only accepts ' . implode(' or ', $allowed) . ' requests.');
    }
    return $method;
}

// Returns the signed in user, or answers 401 (the pages would redirect to the sign in form instead).
function api_user(string $message = 'Please sign in to continue.'): array
{
    $user = current_user();
    if (!$user) {
        api_error(401, $message, ['signin' => url('sign-in.php')]);
    }
    return $user;
}

// Checks the posted CSRF token exactly like verify_csrf(), but answers 403 instead of redirecting.
function api_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        app_log('CSRF check failed for ' . ($_SERVER['SCRIPT_NAME'] ?? ''));
        api_error(403, 'Your session expired before the form was sent. Please reload the page and try again.');
    }
}

// Replaces the HTML error page from bootstrap.php: log the error, answer with a short JSON message.
set_exception_handler(function (Throwable $e): void {
    error_log('[' . date('c') . '] ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ' '
        . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    api_error(500, 'Something went wrong on our side. Please reload the page.');
});
