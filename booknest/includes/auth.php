<?php
// Sessions, sign in state and role guards.

// Starts the session with safe cookie settings and applies the idle timeout.
function start_secure_session(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bn_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => BASE_URL, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    if (!empty($_SESSION['uid']) && isset($_SESSION['last_active'])
        && $now - $_SESSION['last_active'] > SESSION_IDLE_SECONDS) {
        unset($_SESSION['uid']);
        session_regenerate_id(true);
        flash('info', 'You were signed out after 30 minutes without activity. Please sign in again.');
    }
    $_SESSION['last_active'] = $now;
}

// Returns the signed in user row, or null for visitors.
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = db_one('SELECT id, full_name, email, role, created_at FROM users WHERE id = ?', [$_SESSION['uid']]);
            if (!$user) {
                unset($_SESSION['uid']);
            }
        }
    }
    return $user;
}

// Returns the signed in user's id, or 0.
function user_id(): int
{
    return (int) (current_user()['id'] ?? 0);
}

// Returns true for admins.
function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

// Returns the user's first name for friendly copy.
function first_name(?array $user = null): string
{
    $user ??= current_user();
    return $user ? explode(' ', trim($user['full_name']))[0] : '';
}

// Marks the user as signed in, with a fresh session id.
function sign_in_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    $_SESSION['last_active'] = time();
}

// Sends visitors to the sign in page, then back here after signing in.
function require_login(string $message = 'Please sign in to continue.'): array
{
    $user = current_user();
    if (!$user) {
        flash('info', $message);
        redirect('sign-in.php?return=' . rawurlencode(is_post() ? back_url() : current_path()));
    }
    return $user;
}

// Allows only admins; others are sent away with a clear message.
function require_admin(): array
{
    $user = require_login('Please sign in with a staff account.');
    if ($user['role'] !== 'admin') {
        app_log('Refused admin access for user ' . $user['id']);
        flash('error', 'That page is for library staff only.');
        redirect('');
    }
    return $user;
}
