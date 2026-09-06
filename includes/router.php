<?php
/**
 * Front controller / router: parses REQUEST_URI, reads .htaccess rewrite,
 * dispatches to the matching handler. Entry point: index.php.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/validation.php';

// Production error logging
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', MEB_DATA_DIR . '/error.log');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Respect the error_reporting() mask: errors suppressed with '@' (such as
    // diagnostics' own best-effort probes and file writes) must NOT be logged —
    // otherwise diagnostics would read its own noise back as LOG-xxx warnings on
    // every run (feedback loop), and ordinary @-suppressed code would spam the log.
    if (!(error_reporting() & $errno)) return true;
    $msg = date('Y-m-d H:i:s') . " [$errno] $errstr in $errfile:$errline\n";
    @file_put_contents(MEB_DATA_DIR . '/error.log', $msg, FILE_APPEND | LOCK_EX);
    return true;
});

set_exception_handler(function (\Throwable $e) {
    $msg = date('Y-m-d H:i:s') . " [EXCEPTION] " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    @file_put_contents(MEB_DATA_DIR . '/error.log', $msg, FILE_APPEND | LOCK_EX);
    // API context: return a structured JSON 500 (no secrets/paths/stack).
    // Never leak the exception message upstream; details live in error.log.
    $reqUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($reqUri, '/api/v1') === 0 && function_exists('json_out')) {
        json_out(500, ['success' => false, 'error' => 'Internal server error']);
    }
    http_response_code(500);
    exit;
});

function route_uri(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    // Only strip a subdirectory prefix when a real rewrite routed the request
    // to this front controller (i.e. SCRIPT_NAME is a PHP script, not the
    // requested path itself). Under `php -S router.php` SCRIPT_NAME mirrors
    // the request URI, so we must NOT strip there.
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $scriptPath = parse_url($script, PHP_URL_PATH) ?: '/';
    if ($scriptPath !== '/' && $scriptPath !== $uri) {
        $dir = dirname($scriptPath);
        if ($dir !== '/' && strpos($uri . '/', rtrim($dir, '/') . '/') === 0) {
            $uri = substr($uri, strlen($dir));
        }
    }
    return '/' . ltrim($uri, '/');
}

/** Whether the site has been installed (DB configured + lock present). */
function is_installed(): bool
{
    return is_file(MEB_LOCK_FILE);
}
