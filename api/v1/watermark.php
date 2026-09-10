<?php
/**
 * Watermark endpoints (admin+).
 *
 * GET /api/v1/watermark/preview?opacity=25&size=22&position=br&mode=single[&logo=/uploads/...png]
 *   Renders a real site photo with the currently configured watermark logo and the
 *   draft settings passed via query params — a live preview for the admin panel.
 *   The preview never writes files and never touches settings or the image cache.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/image.php';

/** Pick a real furniture photo to demonstrate the watermark on. */
function wm_sample_photo(): string
{
    static $pick = null;
    if ($pick !== null) return $pick;
    $candidates = [
        '/uploads/tl3tcp06710.png', // AIR "Коллекция" photo used on the home page
    ];
    foreach ($candidates as $u) {
        if (meb_img_source_path($u) !== null) return $pick = $u;
    }
    $root = defined('MEB_ROOT') ? MEB_ROOT : dirname(__DIR__, 2);
    foreach ((array) glob($root . '/uploads/*.{png,jpg,jpeg,webp}', GLOB_BRACE) as $f) {
        $u = '/uploads/' . basename($f);
        if (strpos($u, 'placeholder') !== false) continue;
        if (meb_img_source_path($u) !== null) return $pick = $u;
    }
    return $pick = '';
}

function handle_watermark(string $M, array $seg): void
{
    $u = current_user();
    if (!can($u, 'admin')) fail('Forbidden', 403);

    if ($M === 'GET' && ($seg[0] ?? '') === 'preview') {
        $cfg = meb_wm_config();
        // Draft overrides from the admin live-preview (never persisted here).
        foreach (['opacity' => 'int', 'size' => 'int', 'position' => 'str', 'mode' => 'str'] as $k => $t) {
            if (!isset($_GET[$k]) || $_GET[$k] === '') continue;
            $cfg[$k] = $t === 'int' ? (int) $_GET[$k] : (string) $_GET[$k];
        }
        if (isset($_GET['logo']) && $_GET['logo'] !== '') $cfg['logo'] = (string) $_GET['logo'];
        $cfg['opacity'] = max(0, min(100, (int) $cfg['opacity']));
        $cfg['size'] = max(4, min(45, (int) $cfg['size']));
        if (!preg_match('#^(tl|tc|tr|cl|cc|cr|bl|bc|br)$#', (string) $cfg['position'])) $cfg['position'] = 'br';
        if (!in_array($cfg['mode'], ['single', 'tile'], true)) $cfg['mode'] = 'single';
        $cfg['enabled'] = $cfg['logo'] !== '' && meb_img_source_path((string) $cfg['logo']) !== null;

        $sample = wm_sample_photo();
        if ($sample === '') fail('Нет фото для примера', 404);
        $path = meb_img_source_path($sample);
        $img = $path !== null ? meb_img_load($path) : false;
        if (!$img) fail('Не удалось открыть фото', 404);

        $iw = imagesx($img); $ih = imagesy($img);
        $w = 900; $h = 675; // 4/3 demo frame large enough to judge opacity/size
        [$dw, $dh] = meb_img_fit_dims($iw, $ih, $w, $h, 'cover');
        $canvas = imagecreatetruecolor($w, $h);
        if (!$canvas) { imagedestroy($img); fail('Out of memory', 500); }
        imagealphablending($canvas, false);
        $bg = imagecolorallocate($canvas, 20, 18, 16);
        imagefill($canvas, 0, 0, $bg);
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $dw, $dh, $iw, $ih);
        imagedestroy($img);
        if ($dw !== $w || $dh !== $h) { // center-crop
            $cx = (int) max(0, round(($dw - $w) / 2));
            $cy = (int) max(0, round(($dh - $h) / 2));
            $final = imagecreatetruecolor($w, $h);
            if ($final) {
                imagecopy($final, $canvas, 0, 0, $cx, $cy, $w, $h);
                imagedestroy($canvas);
                $canvas = $final;
            }
        }

        meb_wm_apply($canvas, $cfg);

        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        imagepng($canvas);
        imagedestroy($canvas);
        exit;
    }

    fail('Not found', 404);
}