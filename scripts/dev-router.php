<?php
/**
 * DEV-ONLY router for `php -S` that mirrors the root `.htaccess` mod_rewrite
 * semantics so local testing behaves exactly like Apache shared hosting:
 *   - existing files/dirs are served directly (never routed to index.php)
 *   - everything else is rewritten to index.php (front controller)
 * Not used in production (Apache uses .htaccess directly).
 */

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$docRoot = __DIR__ . '/..';
$full = realpath($docRoot . $uriPath);

if ($full !== false && strpos($full, realpath($docRoot)) === 0) {
    if (is_file($full)) {
        if (strtolower(pathinfo($full, PATHINFO_EXTENSION)) === 'php') {
            $_SERVER['SCRIPT_NAME'] = $uriPath;
            require $full;
            exit;
        }
        return false; // let php -S serve the static file
    }
    if (is_dir($full)) {
        // mimic Apache DirectoryIndex: index.html then index.php
        if (is_file($full . '/index.html')) { readfile($full . '/index.html'); exit; }
        if (is_file($full . '/index.php'))  { $_SERVER['SCRIPT_NAME'] = rtrim($uriPath, '/') . '/index.php'; require $full . '/index.php'; exit; }
    }
}

// Rewrite everything else to the front controller (mirrors RewriteRule ^ index.php)
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $docRoot . '/index.php';
