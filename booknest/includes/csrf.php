<?php
// Cross site request forgery protection for every POST form.

// Returns the session's CSRF token, creating it once.
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Prints the hidden CSRF input for a form.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// Stops the request unless the posted token matches the session token.
function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        app_log('CSRF check failed for ' . ($_SERVER['SCRIPT_NAME'] ?? ''));
        flash('error', 'Your session expired before the form was sent. Please try again.');
        redirect(back_url());
    }
}
