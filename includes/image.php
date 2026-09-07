<?php
/**
 * MEB image pipeline — on-demand responsive variants.
 *
 * Never touches the originals. Generated files live in storage/cache/img
 * (content-addressed: the URL signature includes the source mtime, so a
 * changed source yields a fresh URL and old variants can be cached forever).
 *
 * Supported source formats: JPEG, PNG, GIF, WebP (GD). SVG sources are passed
 * through untouched (they are vector — resizing is pointless and would raster
 * the brand drawings).
 *
 * The serving route is /img/var/{w}x{h}/{fit}/{q}/{sig}/{src...} handled by
 * index.php → meb_serve_variant(). Every URL is signed with the site JWT
 * secret, so the endpoint cannot be abused to exhaustively re-render the
 * filesystem. Sources are additionally whitelisted to /uploads and /public.
 */

declare(strict_types=1);

if (!function_exists('meb_img_roots')) {
function meb_img_roots(): array
{
    $root = defined('MEB_ROOT') ? MEB_ROOT : dirname(__DIR__);
    return [$root . '/uploads', $root . '/public'];
}
}

/** Resolve a site-rooted URL (e.g. /uploads/x.png) to a real file, or null. */
if (!function_exists('meb_img_source_path')) {
function meb_img_source_path(string $src): ?string
{
    $root = defined('MEB_ROOT') ? MEB_ROOT : dirname(__DIR__);
    if ($src === '' || $src[0] !== '/') return null;
    $full = realpath($root . $src);
    if ($full === false || !is_file($full)) return null;
    $realRoot = realpath($root);
    if ($realRoot === false || strncmp($full, $realRoot, strlen($realRoot)) !== 0) return null;
    foreach (meb_img_roots() as $r) {
        $rr = realpath($r);
        if ($rr !== false && strncmp($full, $rr, strlen($rr)) === 0) return $full;
    }
    return null;
}
}

if (!function_exists('meb_img_ext_ok')) {
function meb_img_ext_ok(string $path): bool
{
    return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif', 'webp'], true);
}
}

if (!function_exists('meb_img_secret')) {
function meb_img_secret(): string
{
    $k = (string) (defined('MEB_JWT_SECRET') ? MEB_JWT_SECRET : '');
    if ($k === '') {
        $root = defined('MEB_ROOT') ? MEB_ROOT : dirname(__DIR__);
        $f = $root . '/config/app.php';
        $k = is_file($f) ? (hash('sha256', (string) file_get_contents($f))) : 'meb-dev-secret';
    }
    return $k;
}
}

if (!function_exists('meb_var_sig')) {
function meb_var_sig(string $src, int $w, int $h, string $fit, int $q): string
{
    $path = meb_img_source_path($src);
    $stamp = $path !== null ? (string) @filemtime($path) : '0';
    return substr(hash_hmac('sha256', $src . '|' . $w . '|' . $h . '|' . $fit . '|' . $q . '|' . $stamp, meb_img_secret()), 0, 24);
}
}

if (!function_exists('meb_img_need_resize')) {
/** True only when a smaller, real resize is actually useful. Never upscale. */
function meb_img_need_resize(string $src, int $w, int $h, string $fit): bool
{
    global $_MEB_SRC_DIMS;
    $path = meb_img_source_path($src);
    if ($path === null || !meb_img_ext_ok($path)) return false;
    if (isset($_MEB_SRC_DIMS[$path])) { $iw = $_MEB_SRC_DIMS[$path][0]; $ih = $_MEB_SRC_DIMS[$path][1]; }
    else {
        $info = @getimagesize($path);
        if ($info === false) return false;
        $iw = (int) $info[0]; $ih = (int) $info[1];
        $_MEB_SRC_DIMS[$path] = [$iw, $ih];
    }
    if ($iw <= 0) return false;
    if ($fit === 'width') return $w < $iw;
    return !($w >= $iw && $h >= $ih);
}
}

/**
 * URL of the resized variant (or the original when resizing is pointless).
 * $fit: cover | contain | width. $q: 0-100 webp quality.
 */
if (!function_exists('meb_var_url')) {
function meb_var_url(string $src, int $w, int $h, string $fit = 'cover', int $q = 82): string
{
    $w = max(16, (int) $w); $h = max(16, (int) $h);
    if (strtolower(pathinfo($src, PATHINFO_EXTENSION)) === 'svg') return $src;
    if (!meb_img_need_resize($src, $w, $h, $fit)) return $src;
    $fit = in_array($fit, ['cover', 'contain', 'width'], true) ? $fit : 'cover';
    $sig = meb_var_sig($src, $w, $h, $fit, $q);
    return '/img/var/' . $w . 'x' . $h . '/' . $fit . '/' . $q . '/' . $sig . '/' . rawurlencode(ltrim($src, '/'));
}
}

if (!function_exists('meb_img_fit_dims')) {
/** target w/h applying fit; never upscales. Returns [w,h] after fit math. */
function meb_img_fit_dims(int $iw, int $ih, int $w, int $h, string $fit): array
{
    if ($iw <= 0 || $ih <= 0) return [$w, $h];
    if ($w >= $iw && $h >= $ih) return [$iw, $ih];
    if ($fit === 'cover') {
        $r = max($w / $iw, $h / $ih);
        $tw = (int) round($iw * $r); $th = (int) round($ih * $r);
        return [$tw, $th];
    }
    if ($fit === 'width') {
        $r = $w / $iw;
        return [$w, (int) round($ih * $r)];
    }
    // contain
    $r = min($w / $iw, $h / $ih);
    return [max(1, (int) round($iw * $r)), max(1, (int) round($ih * $r))];
}
}

if (!function_exists('meb_img_load')) {
function meb_img_load(string $path)
{
    $e = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($e === 'png') return @imagecreatefrompng($path);
    if ($e === 'gif') return @imagecreatefromgif($path);
    if ($e === 'webp') return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    return @imagecreatefromjpeg($path);
}
}

/**
 * Generate the variant into the cache and return its file path (or null on
 * failure). $h is ignored for fit=width (kept for signature parity).
 */
if (!function_exists('meb_var_generate')) {
function meb_var_generate(string $src, int $w, int $h, string $fit, int $q): ?string
{
    $path = meb_img_source_path($src);
    if ($path === null || !meb_img_ext_ok($path)) return null;
    $info = @getimagesize($path);
    if ($info === false) return null;
    $iw = (int) $info[0]; $ih = (int) $info[1];
    $img = meb_img_load($path);
    if (!$img) return null;

    [$tw, $th] = meb_img_fit_dims($iw, $ih, $w, $h, $fit);
    $crop = ($fit === 'cover');
    $scaleW = $crop ? $tw : $w;
    $scaleH = $crop ? $th : $h;
    if ($fit === 'width') { $scaleW = $tw; $scaleH = $th; }
    $scaleW = max(1, $scaleW); $scaleH = max(1, $scaleH);

    $srcW = ($crop && !($scaleW >= $iw && $scaleH >= $ih)) ? $scaleW : $iw;
    $srcH = ($crop && !($scaleW >= $iw && $scaleH >= $ih)) ? $scaleH : $ih;

    $canvas = imagecreatetruecolor($w, $fit === 'contain' ? $h : ($fit === 'width' ? $scaleH : $h));
    if (!$canvas) { imagedestroy($img); return null; }

    $hasAlpha = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'gif', 'webp'], true) || ($info[2] === IMAGETYPE_PNG || $info[2] === IMAGETYPE_GIF || $info[2] === IMAGETYPE_WEBP);
    if ($hasAlpha) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $trans = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $trans);
    } else {
        imagealphablending($canvas, false);
        $bg = imagecolorallocate($canvas, 20, 18, 16); // premium dark seam for contain
        imagefill($canvas, 0, 0, $bg);
    }

    $sx = 0; $sy = 0;
    if ($crop && $scaleW > $iw) $sx = (int) round(($iw - $srcW) / 2);
    if ($crop && $scaleH > $ih) $sy = (int) round(($ih - $srcH) / 2);

    imagecopyresampled($canvas, $img, 0, 0, $sx, $sy, $scaleW, $scaleH, $srcW, $srcH);
    imagedestroy($img);

    $outW = imagesx($canvas); $outH = imagesy($canvas);
    if ($crop && ($outW !== $w || $outH !== $h)) {
        // exact crop to the requested box
        $cropX = (int) max(0, round(($outW - $w) / 2));
        $cropY = (int) max(0, round(($outH - $h) / 2));
        $final = imagecreatetruecolor($w, $h);
        if ($final) {
            if ($hasAlpha) { imagealphablending($final, false); imagesavealpha($final, true); $t2 = imagecolorallocatealpha($final,0,0,0,127); imagefill($final,0,0,$t2); }
            else { $bg2 = imagecolorallocate($final, 20, 18, 16); imagefill($final, 0, 0, $bg2); }
            imagecopy($final, $canvas, 0, 0, $cropX, $cropY, $w, $h);
            imagedestroy($canvas);
            $canvas = $final;
        }
    }

    $sig = meb_var_sig($src, $w, $h, $fit, $q);
    $dir = MEB_DATA_DIR . '/cache/img/' . substr($sig, 0, 2);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $out = $dir . '/' . $sig . '.webp';
    $ok = function_exists('imagewebp') && imagewebp($canvas, $out, max(1, min(100, $q)));
    imagedestroy($canvas);
    if (!$ok) return null;
    @chmod($out, 0644);
    return $out;
}
}

/** Build a signed srcset string. */
if (!function_exists('meb_pic_srcset')) {
function meb_pic_srcset(string $src, array $widths, string $fit = 'cover', int $q = 82): string
{
    if (strtolower(pathinfo($src, PATHINFO_EXTENSION)) === 'svg') return $src . ' 1x';
    $parts = [];
    foreach ($widths as $w) {
        $h = $fit === 'width' ? 0 : (int) round($w * 0.75);
        $u = meb_var_url($src, (int) $w, (int) $h, $fit, $q);
        $parts[(int) $w] = $u . ' ' . (int) $w . 'w';
    }
    ksort($parts);
    $urls = array_values(array_unique(array_map(function ($p) { return explode(' ', $p)[0]; }, $parts)));
    if ($urls === [ $src ]) return $src . ' 1x';
    return implode(', ', $parts);
}
}

/**
 * Responsive <img>: variant srcset + sizes + width/height (ratio) hints to
 * reserve the slot before load (no CLS) + lazy/async.
 *
 * Options:
 *   w, h     display box (used for srcset candidates + aspect hint)
 *   fit      cover|contain|width
 *   q        webp quality
 *   ratio    "4/3" style aspect hint override
 *   eager    do not lazy-load (LCP image)
 *   class    extra classes
 */
/**
 * Serve a /img/var/... variant: parse, validate HMAC signature, generate
 * (idempotent, content-addressed cache) and stream with immutable cache
 * headers. On any failure → 404.
 */
if (!function_exists('meb_serve_variant_uri')) {
function meb_serve_variant_uri(string $uri): void
{
    if (!preg_match('#^/img/var/([0-9]+)x([0-9]+)/(cover|contain|width)/([0-9]{1,3})/([a-f0-9]{24})/(.+)$#', $uri, $m)) {
        http_response_code(404); exit;
    }
    $w = (int) $m[1]; $h = (int) $m[2]; $fit = $m[3]; $q = (int) $m[4]; $sig = $m[5];
    $src = '/' . rawurldecode($m[6]);
    $w = max(16, min(2400, $w));
    $h = $fit === 'width' ? 0 : max(16, min(2400, $h));
    $q = max(30, min(95, $q));
    if (meb_var_sig($src, $w, $h, $fit, $q) !== $sig) { http_response_code(404); exit; }
    $file = meb_var_generate($src, $w, $h, $fit, $q);
    if ($file === null) { http_response_code(404); exit; }
    header('Content-Type: image/webp');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}
}

if (!function_exists('meb_pic')) {
function meb_pic(string $src, string $alt, array $o = []): string
{
    $w = (int) ($o['w'] ?? 768);
    $h = (int) ($o['h'] ?? (int) round($w * 0.75));
    $fit = $o['fit'] ?? 'cover';
    $q = (int) ($o['q'] ?? 82);
    $sizes = (string) ($o['sizes'] ?? '100vw');
    $cls = isset($o['class']) ? ' class="' . e($o['class']) . '"' : '';
    $loading = !empty($o['eager']) ? 'eager' : 'lazy';
    $srcset = meb_pic_srcset($src, [320, 480, 640, 960, 1280, 1920], $fit, $q);
    $out = '<img src="' . e($src) . '"' . $cls;
    $out .= ' srcset="' . e($srcset) . '"';
    $out .= ' sizes="' . e($sizes) . '"';
    $out .= ' alt="' . e($alt) . '"';
    $out .= ' width="' . $w . '" height="' . $h . '"';
    $out .= ' loading="' . $loading . '" decoding="async"';
    if (!empty($o['eager'])) $out .= ' fetchpriority="high"';
    $out .= '>';
    return $out;
}
}