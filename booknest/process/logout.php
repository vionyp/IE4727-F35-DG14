<?php
// Sign out: destroys the session and its cookie, then starts a clean one for the goodbye message.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
verify_csrf();

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();

session_start();
session_regenerate_id(true);
flash('success', 'You are signed out. See you soon.');
redirect('');
