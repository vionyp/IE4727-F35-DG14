<?php
// Shared bootstrap: loaded first by every page and endpoint.
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(APP_TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/error.log');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/rooms.php';
require_once __DIR__ . '/covers.php';
require_once __DIR__ . '/views.php';
require_once __DIR__ . '/assistant.php';

set_exception_handler('handle_fatal');
start_secure_session();

// Logs an uncaught error and shows a calm message instead of a stack trace.
function handle_fatal(Throwable $e): void
{
    error_log('[' . date('c') . '] ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' '
        . ($_SERVER['REQUEST_URI'] ?? '') . ' ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><html lang="en-SG"><head><meta charset="utf-8"><title>Something went wrong | '
        . SITE_NAME . '</title><link rel="stylesheet" href="' . BASE_URL . 'assets/css/base.css"></head>'
        . '<body><main class="container fatal"><h1>Something went wrong on our side</h1>'
        . '<p>The page could not be loaded. Please try again in a moment, or go back to the '
        . '<a href="' . BASE_URL . '">home page</a>.</p></main></body></html>';
    exit;
}
