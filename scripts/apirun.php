<?php
/**
 * Single API request runner (testing). Invoked once per request from the shell:
 *   METHOD=POST PATH_=/auth/login AUTH='' BODY='{"email":"a","password":"b"}' \
 *     env -u LD_LIBRARY_PATH php scripts/apirun.php
 *
 * Edits server env, bootstraps exactly like api/v1/router.php, and emits the
 * JSON the client would receive. register_shutdown_function reliably runs on
 * json_out()'s exit(), so we can capture status + body.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD']   = getenv('METHOD') ?: 'GET';
$_SERVER['REQUEST_URI']      = '/api/v1' . (getenv('PATH_') ?: '/');
$_SERVER['SCRIPT_NAME']      = '/index.php';
$_SERVER['PHP_SELF']         = '/index.php';
$_SERVER['HTTP_AUTHORIZATION'] = getenv('AUTH') ?: '';
$_SERVER['HTTP_X_CSRF_TOKEN']  = getenv('CSRF') ?: '';
$_SERVER['CONTENT_TYPE']     = 'text/plain';          // triggers $_POST fallback in read_body()
$_SERVER['REMOTE_ADDR']      = '127.0.0.1';
$_SERVER['HTTP_HOST']        = 'localhost';

// Prod simulation extras:
//   COOKIE="token=<auth-token>; PHPSESSID=<sid>" — cookie-based auth (Sweb drops
//   the Authorization header, so auth falls back to the HttpOnly login cookie).
//   SESS="<sid>" + SERVER_CSRF="<server-side csrf>" — seeds the PHP session so
//   csrf_check_ok() sees the SAME $_SESSION['csrf'] the login issued.
if (getenv('COOKIE')) {
    foreach (array_filter(array_map('trim', explode(';', getenv('COOKIE')))) as $pair) {
        $eq = strpos($pair, '=');
        if ($eq !== false) $_COOKIE[trim(substr($pair, 0, $eq))] = substr($pair, $eq + 1);
    }
}
if (getenv('SESS') && getenv('SERVER_CSRF')) {
    @session_id(getenv('SESS'));
    @session_start();
    $_SESSION['csrf'] = getenv('SERVER_CSRF');
    @session_write_close();
}

$body = $GLOBALS['BODY'] = getenv('BODY') ?: '';
if ($body !== '') {
    $parsed = json_decode($body, true);
    $_POST = is_array($parsed) ? $parsed : [];
} else {
    $_POST = [];
}

ob_start();
register_shutdown_function(function () {
    $out = ob_get_clean();
    fwrite(STDOUT, "STATUS:" . http_response_code() . "\n" . (is_string($out) ? $out : '') . "\n");
});

require dirname(__DIR__) . '/api/v1/router.php';
