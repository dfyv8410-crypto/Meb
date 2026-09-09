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
    // Site-rooted URLs are served both from the project root (uploads/) and
    // from /public (assets/… live in public/assets/…). Trying only
    // $root . $src meant /assets/img/*.svg never resolved and every product
    // card silently fell back to the placeholder drawing.
    $realRoot = realpath($root);
    $candidates = [$root . $src, $root . '/public' . $src];
    foreach ($candidates as $cand) {
        $full = realpath($cand);
        if ($full === false || !is_file($full)) continue;
        if ($realRoot === false || strncmp($full, $realRoot, strlen($realRoot)) !== 0) continue;
        foreach (meb_img_roots() as $r) {
            $rr = realpath($r);
            if ($rr !== false && strncmp($full, $rr, strlen($rr)) === 0) return $full;
        }
        break;
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

/**
 * Parse a "w/h" or "w:h" aspect hint into [W, H]; defaults to 4/3.
 */
if (!function_exists('meb_img_ratio')) {
function meb_img_ratio(?string $ratio): array
{
    if ($ratio !== null && preg_match('#^\s*(\d+)\s*(?::|/)\s*(\d+)\s*$#', $ratio, $m) && (int) $m[2] > 0) {
        return [(int) $m[1], (int) $m[2]];
    }
    return [4, 3];
}
}

/**
 * Intrinsic CSS-pixel size of an SVG source (its width/height attributes, or
 * the viewBox when they are absent). Null when unknown. Used to emit truthful
 * width/height ratio hints so the pre-load slot matches the real aspect — an
 * SVG must never be hinted with a differently-proportioned raster box.
 */
if (!function_exists('meb_img_svg_size')) {
function meb_img_svg_size(string $src): ?array
{
    $path = meb_img_source_path($src);
    if ($path === null) return null;
    $head = @file_get_contents($path, false, null, 0, 4096);
    if ($head === false) return null;
    $w = null; $h = null;
    if (preg_match('/<svg\b[^>]*\bwidth\s*=\s*"([0-9.]+)/i', $head, $m)) $w = (float) $m[1];
    if (preg_match('/<svg\b[^>]*\bheight\s*=\s*"([0-9.]+)/i', $head, $m)) $h = (float) $m[1];
    if (($w === null || $h === null) && preg_match('/<svg\b[^>]*\bviewBox\s*=\s*"([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)"/i', $head, $m)) {
        $w = $w ?? (float) $m[3];
        $h = $h ?? (float) $m[4];
    }
    if ($w === null || $h === null || $w <= 0 || $h <= 0) return null;
    return [(int) round($w), (int) round($h)];
}
}

/**
 * Build a truthful signed srcset string.
 *
 * - Candidates are generated at the display aspect ratio ($ratio), not a
 *   hardcoded 4/3 — so a 3/4 card gets 3/4 crops instead of an over-cropped
 *   upscale of a 4/3 frame.
 * - Candidate widths never exceed the source's natural width, and the largest
 *   useful candidate is the original itself labelled with its REAL width —
 *   the browser is never told "1920w" about a 1536px file.
 * - SVG sources pass through untouched ("1x").
 */
if (!function_exists('meb_pic_srcset')) {
function meb_pic_srcset(string $src, array $widths, string $fit = 'cover', int $q = 82, ?string $ratio = null): string
{
    if (strtolower(pathinfo($src, PATHINFO_EXTENSION)) === 'svg') return $src . ' 1x';

    $path = meb_img_source_path($src);
    $naturalW = 0;
    if ($path !== null && meb_img_ext_ok($path)) {
        $info = @getimagesize($path);
        if ($info !== false) $naturalW = (int) $info[0];
    }
    if ($naturalW <= 0) return $src . ' 1x';

    [$rw, $rh] = meb_img_ratio($ratio);
    $parts = [];
    foreach ($widths as $w) {
        $w = (int) $w;
        if ($w > $naturalW) break;
        $h = $fit === 'width' ? 0 : max(16, (int) round($w * $rh / $rw));
        $u = meb_var_url($src, $w, $h, $fit, $q);
        $parts[$w] = $u . ' ' . $w . 'w';
    }
    ksort($parts);
    // Honest ceiling: the source file at its real width.
    if (!isset($parts[$naturalW])) {
        $parts[$naturalW] = $src . ' ' . $naturalW . 'w';
    }
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
    $ratio = (string) ($o['ratio'] ?? ($h > 0 ? $w . '/' . $h : '4/3'));
    $cls = isset($o['class']) ? ' class="' . e($o['class']) . '"' : '';
    $loading = !empty($o['eager']) ? 'eager' : 'lazy';

    // Broken or empty sources degrade to the brand drawing — never a 404 icon
    // or an empty slot (a possible "stripe" on a narrow screen).
    if ($src === '' || meb_img_source_path($src) === null) {
        $src = '/assets/img/placeholder.svg';
    }

    $isSvg = strtolower(pathinfo($src, PATHINFO_EXTENSION)) === 'svg';
    if ($isSvg) {
        $srcAttr = $src;
        $srcset  = $src . ' 1x';
        // SVG: hint the REAL intrinsic aspect (width/height attrs), not the
        // requested raster box — the requested w/h is only a display target and
        // its ratio may clash with the artwork (e.g. 1600x900 for a 1200x920
        // SVG), which reserved a wrong pre-load slot and could squash the image.
        $size = meb_img_svg_size($src);
        $boxW = $size !== null ? $size[0] : $w;
        $boxH = $size !== null ? $size[1] : $h;
    } else {
        $variant = meb_var_url($src, $w, $h, $fit, $q);
        // The real fallback is a sized variant — never the multi-MB original,
        // so no browser downloads a 2MB PNG to fill a 300px slot.
        $srcAttr = $variant !== $src ? $variant : $src;
        $srcset  = meb_pic_srcset($src, [320, 480, 640, 960, 1280, 1920], $fit, $q, $ratio);
        $boxW = $w;
        $boxH = $h;
    }

    $out = '<img src="' . e($srcAttr) . '"' . $cls;
    $out .= ' srcset="' . e($srcset) . '"';
    $out .= ' sizes="' . e($sizes) . '"';
    $out .= ' alt="' . e($alt) . '"';
    $out .= ' width="' . $boxW . '" height="' . $boxH . '"';
    $out .= ' loading="' . $loading . '" decoding="async"';
    if (!empty($o['eager'])) $out .= ' fetchpriority="high"';
    $out .= '>';
    return $out;
}
}