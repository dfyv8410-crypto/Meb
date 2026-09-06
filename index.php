<?php
/**
 * MEB front controller.
 * Handles public pages (/, /catalog, /projects, /materials, /services,
 * /contacts, /p/:slug), serves static assets, and proxies /api/v1 to the API
 * router. Requests are rewritten here by .htaccess.
 *
 * @package MEB
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/router.php';

security_headers();

$uri = route_uri();

// ---- Installer bootstrap ------------------------------------------------
// If DB isn't configured/installed yet, route to the installer EXCEPT for
// /api/v1/install/* and /installer (so the wizard itself can run).
$isConfigApi = strpos($uri, '/api/v1/install') === 0;
$isInstaller = $uri === '/installer' || $uri === '/installer/' || strpos($uri, '/installer/') === 0;
if (!$isConfigApi && !$isInstaller && !is_installed()) {
    http_response_code(307);
    header('Location: /installer');
    exit;
}

// ---- API ---------------------------------------------------------------
if (strpos($uri, '/api/v1/') === 0 || $uri === '/api/v1') {
    require __DIR__ . '/api/v1/router.php';
    // router.php never returns (it exits)
}

// ---- SEO virtual files (handled before asset block so .xml/.txt don't 404) --
if ($uri === '/sitemap.xml') { require __DIR__ . '/pages/sitemap.php'; exit; }
if ($uri === '/robots.txt') { require __DIR__ . '/pages/robots.php'; exit; }

// ---- Static asset / storage / uploads ----------------------------------
$assetExtensions = ['html', 'css', 'js', 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'woff', 'woff2', 'ico', 'xml', 'txt'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
if (in_array($ext, $assetExtensions, true)) {
    serve_file(__DIR__ . '/public' . $uri);
    // if not found below, fall through to security check
}

// Files under /uploads are served via index.php (blocked from PHP execution).
if (strpos($uri, '/uploads') === 0) {
    serve_file(__DIR__ . $uri);
}

// ---- Page routing ------------------------------------------------------
function _meb_page(string $file): void
{
    global $_MEB_SLUG, $_MEB_CAT;
    try {
        require __DIR__ . '/pages/' . $file;
    } catch (\Throwable $e) {
        http_response_code(500);
        $msg = (string) $e->getMessage();
        error_log('MEB page error [' . $file . ']: ' . $msg);
echo '<!doctype html><html><head><meta charset="utf-8"><title>Ошибка</title>'
   . '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&family=Manrope:wght@400;500&display=swap" rel="stylesheet">'
   . '<style>body{font-family:"Manrope",sans-serif;padding:80px 24px;text-align:center;background:#F3EFE8;color:#25221E;margin:0}'
   . 'h1{font-family:"Cormorant Garamond",serif;font-weight:400;font-size:38px;line-height:1.1;margin-bottom:10px}'
   . '.sub{color:#706960;margin-bottom:32px;font-size:15px}'
   . '.btn{display:inline-block;background:#26231F;color:#F3EFE8;padding:12px 28px;border-radius:4px;text-decoration:none;font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;transition:all .3s ease}'
   . '.btn:hover{background:#3E3933;transform:translateY(-1px)}</style></head>'
   . '<body><h1>Произошла ошибка</h1>'
   . '<p class="sub">Мы уже работаем над исправлением. Попробуйте обновить страницу.</p>'
   . '<a class="btn" href="/">На главную</a></body></html>';
    }
    exit;
}
if ($uri === '/' || $uri === '/index.php' || $uri === '') {
    _meb_page('home.php');
}
if ($uri === '/catalog')            { _meb_page('catalog-hub.php'); }
if ($uri === '/projects')           { _meb_page('projects.php'); }
if ($uri === '/materials')          { _meb_page('materials.php'); }
if ($uri === '/services')           { _meb_page('services.php'); }
if ($uri === '/contacts') { _meb_page('contacts.php'); }

// /p/:slug
if (preg_match('#^/p/([^/]+)$#', $uri, $m)) {
    $_MEB_SLUG = urldecode($m[1]);
    _meb_page('page.php');
}
// /project/:slug
if (preg_match('#^/project/([^/]+)$#', $uri, $m)) {
    $_MEB_SLUG = urldecode($m[1]);
    _meb_page('project.php');
}
// /catalog/:cat or /catalog/:cat/:slug
if (preg_match('#^/catalog/([^/]+)(?:/([^/]+))?$#', $uri, $m)) {
    $_MEB_CAT = urldecode($m[1]);
    $_MEB_SLUG = isset($m[2]) ? urldecode($m[2]) : null;
    _meb_page('catalog-item.php');
}

// ---- SPA fallback: /admin -> admin SPA, /installer -> installer ---------
if ($uri === '/admin' || $uri === '/admin/') { serve_admin(); }
if (strpos($uri, '/admin/') === 0) { serve_admin(); }
if ($uri === '/installer' || $uri === '/installer/') { serve_installer(); }

// unknown
http_response_code(404);
echo '<!doctype html><html><head><meta charset="utf-8"><title>404</title>'
   . '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&family=Manrope:wght@400;500&display=swap" rel="stylesheet">'
   . '<style>body{font-family:"Manrope",sans-serif;padding:80px 24px;text-align:center;background:#F3EFE8;color:#25221E;margin:0}'
   . 'h1{font-family:"Cormorant Garamond",serif;font-size:72px;font-weight:400;margin-bottom:8px;color:#80654A}'
   . '.sub{color:#706960;margin-bottom:32px;font-size:15px}'
   . '.btn{display:inline-block;background:#26231F;color:#F3EFE8;padding:12px 28px;border-radius:4px;text-decoration:none;font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;transition:all .3s ease}'
   . '.btn:hover{background:#3E3933;transform:translateY(-1px)}</style></head>'
   . '<body><h1>404</h1><p class="sub">Страница не найдена</p><a class="btn" href="/">На главную</a></body></html>';
exit;

function serve_file(string $fullPath): void
{
    $fullPath = realpath($fullPath);
    $root = realpath(__DIR__);
    if ($fullPath === false || strpos($fullPath, $root) !== 0) {
        http_response_code(404); exit;
    }
    if (is_file($fullPath)) {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $map = [
            'html'=>'text/html; charset=utf-8','css'=>'text/css','js'=>'application/javascript',
            'json'=>'application/json','svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg',
            'jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','avif'=>'image/avif',
            'woff'=>'font/woff','woff2'=>'font/woff2','xml'=>'application/xml','txt'=>'text/plain',
            'ico'=>'image/x-icon',
        ];
        header('Content-Type: ' . ($map[$ext] ?? 'application/octet-stream'));
        // if URL is /uploads -> long cache; else short
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/uploads') === 0) {
            header('Cache-Control: public, max-age=31536000, immutable');
        }
        readfile($fullPath);
        exit;
    }
}

function serve_admin(): void
{
    $f = __DIR__ . '/admin/index.html';
    if (is_file($f)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($f);
        exit;
    }
    http_response_code(404); exit;
}

function serve_installer(): void
{
    $f = __DIR__ . '/installer/index.php';
    if (is_file($f)) {
        require $f;
    }
    http_response_code(404); exit;
}
