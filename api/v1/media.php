<?php
/**
 * Media endpoints: GET list (public) / POST upload / DELETE (editor+).
 * Files stored in /uploads; metadata in media table. Base64 upload (matches Node).
 */

declare(strict_types=1);

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

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg','avif','ico'], true)) fail('Unsupported type', 400);

        $safeFolder = clean_folder((string) ($b['folder'] ?? ''));
        $storeName = new_id() . '.' . $ext;
        $dir = MEB_ROOT . '/uploads' . ($safeFolder !== '' ? '/' . $safeFolder : '');
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $full = $dir . '/' . $storeName;
        if (@file_put_contents($full, $raw) === false) fail('Write failed', 500);
        @chmod($full, 0644);

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
