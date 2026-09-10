<?php
/**
 * Media endpoints: GET list (public) / POST upload / DELETE (editor+).
 * Files stored in /uploads; metadata in media table. Base64 upload (matches Node).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/image.php';

/** Map real MIME → canonical extension. */
function media_ext_for_mime(string $mime): string
{
    $map = [
        'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png',
        'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg',
        'image/avif' => 'avif', 'image/x-icon' => 'ico',
    ];
    return $map[$mime] ?? '';
}

function handle_media(string $M, array $seg): void
{
    if ($M === 'GET') {
        $folder = $_GET['folder'] ?? '';
        if ($folder !== '') {
            $st = db()->prepare('SELECT * FROM media WHERE folder = ? ORDER BY created_at DESC');
            $st->execute([$folder]);
            $rows = $st->fetchAll();
        } else {
            $rows = all_rows('media');
        }
        ok($rows);
    }

    if ($M === 'POST') {
        $u = current_user();
        if (!can($u, 'editor')) fail('Forbidden', 403);
        $b = read_body();
        $filename = (string) ($b['filename'] ?? '');
        $dataUrl  = (string) ($b['data'] ?? '');
        if ($filename === '' || $dataUrl === '') fail('filename и data обязательны', 400);
        // parse base64 data-url
        if (preg_match('#^data:([^;]+);base64,(.*)$#s', $dataUrl, $m)) {
            $mime = $m[1];
            $raw = base64_decode($m[2], true);
        } elseif (preg_match('#^(?:data:)?[^,]*;base64,(.*)$#s', $dataUrl, $m)) {
            $raw = base64_decode($m[1], true);
            $mime = '';
        } else {
            // assume raw base64
            $raw = base64_decode($dataUrl, true);
            $mime = '';
        }
        if ($raw === false || $raw === '') fail('Invalid data', 400);
        if (strlen($raw) > 15 * 1024 * 1024) fail('File too large', 400);

        // Real content sniff (never trust the client MIME or filename).
        $sniffed = @finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $raw);
        if (is_string($sniffed) && $sniffed !== '') $mime = $sniffed;
        $ext = media_ext_for_mime(strtolower($mime));
        if ($ext === '') {
            // fall back to extension-based allow-list (legacy webp/ico uploads)
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg','avif','ico'], true)) fail('Unsupported type', 400);
        }
        if ($ext === 'jpg') $ext = 'jpeg';
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg','avif','ico'], true)) fail('Unsupported type', 400);

        $safeFolder = clean_folder((string) ($b['folder'] ?? ''));
        $storeName = new_id() . '.' . $ext;
        $dir = MEB_ROOT . '/uploads' . ($safeFolder !== '' ? '/' . $safeFolder : '');
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $full = $dir . '/' . $storeName;
        if (@file_put_contents($full, $raw) === false) fail('Write failed', 500);
        @chmod($full, 0644);

        // Cross-check: raster extensions must carry valid raster payloads.
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','avif'], true) && @getimagesize($full) === false) {
            @unlink($full);
            fail('Not a valid image', 400);
        }

        // Hardening: reject decompression bombs and absurd dimensions.
        if (!in_array($ext, ['svg', 'ico'], true)) {
            $dims = @getimagesize($full);
            if ($dims === false || (int) $dims[0] <= 0 || (int) $dims[1] <= 0) {
                @unlink($full);
                fail('Not a valid image', 400);
            }
            if ((int) $dims[0] > 12000 || (int) $dims[1] > 12000 || (int) $dims[0] * (int) $dims[1] > 40000000) {
                @unlink($full);
                fail('Изображение слишком большое (макс. 12000×12000, 40 МП)', 400);
            }
        }

        // SVG hardening: only allow pure vector drawings — no scripts, no
        // embedded HTML/foreignObject, no remote fetchers.
        if ($ext === 'svg') {
            $svg = @file_get_contents($full);
            if ($svg === false) { @unlink($full); fail('Not a valid SVG', 400); }
            $lower = strtolower($svg);
            $bad = ['<script', 'javascript:', 'onload=', 'onerror=', 'onclick=',
                    'foreignobject', '<iframe', '<object', '<embed', 'data:text/html',
                    '&#x3c;', '%3cscript'];
            foreach ($bad as $needle) {
                if (strpos($lower, $needle) !== false) { @unlink($full); fail('SVG содержит недопустимое содержимое', 400); }
            }
            $trim = ltrim($svg, "\xEF\xBB\xBF\xFE\xFF \t\r\n");
            if (preg_match('#^<!--.*?-->#s', $trim, $_m)) $trim = ltrim(substr($trim, strlen($_m[0])));
            if (stripos($trim, '<svg') !== 0) { @unlink($full); fail('Файл не является корректным SVG', 400); }
        }

        [$w, $h] = image_dims($full);
        $url = '/uploads' . ($safeFolder !== '' ? '/' . $safeFolder : '') . '/' . $storeName;
        $id = new_id();
        $st = db()->prepare(
            'INSERT INTO media (id,filename,original_name,size,width,height,folder,alt,url,mime,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([$id, $storeName, $filename, strlen($raw), $w, $h, $safeFolder,
                      sanitize((string)($b['alt'] ?? '')), $url, $mime, now_db(), now_db()]);
        audit_log('create', 'media', $id);
        // Warm the responsive pipeline (4/3 cards + 16/9 hero) with the same
        // sizes the templates request. Uses the site-rooted URL so the cache
        // signature matches the public route, and skips sizes the source is
        // too small for. Watermark applies automatically once enabled.
        foreach ([[480,360],[640,480],[768,576],[960,720],[1280,960],[1280,720]] as $dim) {
            try {
                if (meb_img_need_resize($url, (int) $dim[0], (int) $dim[1], 'cover')) {
                    meb_var_generate($url, (int) $dim[0], (int) $dim[1], 'cover', 82);
                }
            } catch (Throwable $t) { /* non-fatal */ }
        }
        ok(find_row('media', $id));
    }

    if ($M === 'DELETE' && isset($seg[0])) {
        $u = current_user();
        if (!can($u, 'editor')) fail('Forbidden', 403);
        $row = find_row('media', $seg[0]);
        if (!$row) fail('Not found', 404);
        $path = MEB_ROOT . '/uploads' . ($row['folder'] !== '' ? '/' . $row['folder'] : '') . '/' . $row['filename'];
        @unlink($path);
        db()->prepare('DELETE FROM media WHERE id = ?')->execute([$seg[0]]);
        audit_log('delete', 'media', $seg[0]);
        ok(true);
    }

    fail('Not found', 404);
}

function clean_folder(string $f): string
{
    $f = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $f);
    $f = trim($f, '-');
    return mb_substr($f, 0, 40);
}

/** Return [width,height] for image files (PNG/JPEG/GIF/WebP best-effort). */
function image_dims(string $path): array
{
    $info = @getimagesize($path);
    if ($info !== false) return [(int) $info[0], (int) $info[1]];
    return [0, 0];
}
