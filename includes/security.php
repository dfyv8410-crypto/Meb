<?php
/**
 * Security: headers, sanitization, CSRF, upload validation, path guards.
 */

declare(strict_types=1);

/** Security headers sent on every response. */
function security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=()");
    header(
        "Content-Security-Policy: default-src 'self' 'unsafe-inline' " .
        "https://fonts.googleapis.com https://fonts.gstatic.com https://unpkg.com " .
        "https://cdn.jsdelivr.net; img-src 'self' data: https: blob:; connect-src 'self'"
    );
}

/** Output a JSON response with a status code. */
function json_out(int $code, $data): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Success wrapper: {success:true, data:...} */
function ok($data, int $code = 200): void
{
    json_out($code, ['success' => true, 'data' => $data]);
}

/** Error wrapper: {success:false, error:...} */
function fail(string $msg, int $code = 400): void
{
    json_out($code, ['success' => false, 'error' => $msg]);
}

/** HTML-escape (matches Node sec.sanitize). */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Sanitize a string for safe storage/echo (HTML-escape). */
function sanitize(?string $s): string
{
    return e($s);
}

/** Read JSON body (also handles form-encoded). Rejects oversized bodies. */
function read_body(): array
{
    if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 20971520) {
        fail('Request too large', 413);
    }
    $raw = file_get_contents('php://input');
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($ct, 'application/json') !== false) {
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
    if (strpos($ct, 'application/x-www-form-urlencoded') !== false) {
        parse_str($raw, $d);
        return is_array($d) ? $d : [];
    }
    if (!empty($_POST)) return $_POST;
    return [];
}

/** CSRF: generate+store a session token. */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

/** CSRF verify for state-changing admin requests. */
function csrf_check_ok(): bool
{
    // Stateless Bearer-header calls (admin SPA) are CSRF-immune; only enforce
    // when using cookie-based session auth without a header.
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return true;
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $token);
}

/**
 * Detect a file's MIME without FATALing when the fileinfo/mime_content_type
 * facility is missing on a minimal PHP 7.1 host. Returns '' when undetectable
 * so callers degrade to their existing "unknown MIME" handling.
 */
function file_mime(string $path): string
{
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if (is_string($m) && $m !== '') return $m;
    }
    if (class_exists('finfo')) {
        $finfo = @new \finfo(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $m = @$finfo->file($path);
            if (is_string($m) && $m !== false && $m !== '') return $m;
        }
    }
    return '';
}

/** Validate uploaded file MIME + extension + size; returns safe name or null. */
function validate_upload(array $file, int $maxBytes = 15 * 1024 * 1024): ?array
{
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > $maxBytes) return null;
    $name = basename($file['name']);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','gif','webp','svg','avif','mp4','webm','pdf'];
    if (!in_array($ext, $allowedExt, true)) return null;
    $mime = file_mime($file['tmp_name']) ?: $file['type'] ?? '';
    $allowedMime = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml','image/avif',
                    'video/mp4','video/webm','application/pdf','text/plain'];
    $base = explode(';', $mime)[0];
    if (!in_array($base, $allowedMime, true)) return null;
    // never allow anything executable
    if (in_array($ext, ['php','phtml','phar','php5','php7','pht','cgi','pl','py','sh'], true)) return null;
    return ['name' => $name, 'ext' => $ext, 'mime' => $mime, 'size' => (int) $file['size']];
}

/** Ensure no PHP execution in uploads via .htaccess (also created by installer). */
function uploads_htaccess(): void
{
    $dir = MEB_ROOT . '/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $f = $dir . '/.htaccess';
    if (!is_file($f)) {
        @file_put_contents($f,
            "php_flag engine off\n" .
            "RemoveHandler .php .phtml .phar\n" .
            "RemoveType .php .phtml\n" .
            "Options -ExecCGI\n");
    }
}

/** Prevent config/ but let installer write it during setup. */
function protect_config_dir(): void
{
    // placeholder — installer handles its own .htaccess
}
