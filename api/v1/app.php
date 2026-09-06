<?php
/**
 * App endpoints: latest APK info + download.
 * APK files live in /uploads/releases (not web-accessible directly).
 */

declare(strict_types=1);

function releases_dir(): string
{
    $d = MEB_ROOT . '/uploads/releases';
    if (!is_dir($d)) @mkdir($d, 0777, true);
    return $d;
}

function latest_apk(): ?string
{
    $files = glob(releases_dir() . '/*.apk');
    if (!$files) return null;
    usort($files, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
    return $files[0];
}

function handle_app(string $what): void
{
    if ($what === 'latest') {
        $f = latest_apk();
        if (!$f) fail('No APK available', 404);
        $baseName = basename($f);
        $ver = str_replace(['meb-admin-', '.apk'], '', $baseName);
        ok(['version' => $ver, 'filename' => $baseName, 'size' => (int) filesize($f), 'url' => '/api/v1/app/download']);
    }
    if ($what === 'download') {
        $f = latest_apk();
        if (!$f) { http_response_code(404); exit('No APK'); }
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . basename($f) . '"');
        header('Content-Length: ' . filesize($f));
        readfile($f);
        exit;
    }
    fail('Not found', 404);
}
