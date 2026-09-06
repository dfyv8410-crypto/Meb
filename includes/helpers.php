<?php
/**
 * Small conventional helpers: audit logging, settings access, i18n.
 */

declare(strict_types=1);

/** Append an audit log entry. */
function audit_log(string $action, string $entity, string $entityId = '', $meta = null): void
{
    $u = current_user();
    $uid = $u ? $u['id'] : 'system';
    $st = db()->prepare(
        'INSERT INTO audit_log (id, user_id, action, entity, entity_id, meta, ip, created_at)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        new_id(), $uid, $action, $entity, $entityId,
        $meta === null ? null : json_encode($meta),
        $_SERVER['REMOTE_ADDR'] ?? '', now_db(),
    ]);
}

/** Single-row settings doc (id='site') as assoc array, with flattened JSON. */
function get_settings(): array
{
    $row = find_row('settings', 'site');
    if (!$row) return [];
    $data = $row['data'];
    if (is_string($data)) $data = json_decode($data, true);
    return is_array($data) ? $data : [];
}

/** Update the single settings row. */
function save_settings(array $data): array
{
    $existing = get_settings();
    $merged = array_merge($existing, $data);
    $st = db()->prepare(
        'INSERT INTO settings (id, data, updated_at) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = VALUES(updated_at)'
    );
    $st->execute(['site', json_encode($merged, JSON_UNESCAPED_UNICODE), now_db()]);
    return $merged;
}

/** Simple JSON-col decoded helper (values already come decoded from db()). */

/** Absolute site origin (scheme://host) for canonical/sitemap/robots URLs. */
function meb_origin(): string
{
    if (MEB_BASE_URL !== '') return rtrim(MEB_BASE_URL, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** i18n strings (mirror locales/ru.json,en.json, minimal set). */
function tlang(): string
{
    if (isset($_GET['lang']) && $_GET['lang'] === 'en') return 'en';
    $al = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    return strpos($al, 'en') !== false ? 'en' : 'ru';
}

function t(string $key, string $lang = ''): string
{
    $lang = $lang ?: tlang();
    static $ru = [];
    static $en = [];
    if (!$ru) $ru = (array) json_decode((string) @file_get_contents(MEB_ROOT . '/locales/ru.json'), true);
    if (!$en) $en = (array) json_decode((string) @file_get_contents(MEB_ROOT . '/locales/en.json'), true);
    $dict = $lang === 'en' ? $en : $ru;
    return isset($dict[$key]) ? (string) $dict[$key] : $key;
}
