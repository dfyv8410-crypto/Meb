<?php
/**
 * Router script for PHP built-in server.
 * Rewrites URLs to match Apache .htaccess rules.
 */

$root = __DIR__;
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($uri !== '/' && file_exists($root . $uri)) {
    return false;
}

// API routing: /api/v1/xxx -> /api/v1/index.php (Apache-style)
if (preg_match('#^/api/v1/(.*)$#', $uri, $m)) {
    $target = $root . '/api/v1/index.php';
    if (file_exists($target)) {
        require $target;
        return;
    }
}

// Page routes: /p/slug -> index.php
if (preg_match('#^/p/(.+)$#', $uri, $m)) {
    $_GET['page'] = $m[1];
    require $root . '/index.php';
    return;
}

// Named pages: /about, /catalog, /projects, /materials, /services, /contacts
$named = ['catalog','projects','materials','services','contacts','about','reviews'];
foreach ($named as $n) {
    if ($uri === '/' . $n) {
        $_GET['page'] = $n;
        require $root . '/index.php';
        return;
    }
}

// Root
if ($uri === '/') {
    require $root . '/index.php';
    return;
}

// Fallback: try index.php
if (file_exists($root . '/index.php')) {
    require $root . '/index.php';
    return;
}

http_response_code(404);
echo '404 Not Found';
