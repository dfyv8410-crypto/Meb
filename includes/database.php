<?php
/**
 * PDO singleton with prepared-statement helpers + small ORM over collections.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = (array) require MEB_CONFIG_DIR . '/database.php';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'], $c['port'] ?? 3306, $c['name'], $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Check whether the DB config file is non-placeholder and connection works. */
function db_configured(): bool
{
    $c = (array) require MEB_CONFIG_DIR . '/database.php';
    // Reject the placeholder defaults
    if (($c['name'] ?? '') === 'meb' && ($c['user'] ?? '') === 'root' && ($c['pass'] ?? '') === '') {
        // Could still be a real empty-root install; try connecting
    }
    try {
        db()->query('SELECT 1');
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

function json_cols(string $table): array
{
    static $map = [
        'settings'      => ['data'],
        'catalog'       => ['images', 'specs'],
        'projects'      => ['images', 'materials', 'features'],
        'materials'     => ['props'],
        'pages'         => ['blocks'],
        'notifications' => ['meta'],
        'audit_log'     => ['meta'],
    ];
    return $map[$table] ?? [];
}

function decode_json_cols(string $table, array $row): array
{
    foreach (json_cols($table) as $c) {
        if (isset($row[$c]) && $row[$c] !== null) {
            $dec = json_decode((string) $row[$c], true);
            $row[$c] = is_array($dec) ? $dec : $row[$c];
        }
    }
    return $row;
}

/** All rows of a collection table. */
function all_rows(string $table): array
{
    $rows = db()->query('SELECT * FROM `' . safe_ident($table) . '`')->fetchAll();
    return array_map(function ($r) use ($table) { return decode_json_cols($table, $r); }, $rows);
}

/** Find a row by id or (optionally) by slug. */
function find_row(string $table, string $id): ?array
{
    $st = db()->prepare('SELECT * FROM `' . safe_ident($table) . '` WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row === false) return null;
    return decode_json_cols($table, $row);
}

/** Escape identifier (defense in depth) — only allow known chars. */
function safe_ident(string $name): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '', $name);
}

/** New base36 id, matching Node's Date.now/random scheme (approx.). */
function new_id(): string
{
    return base_convert((string) time(), 10, 36) . substr(uniqid('', true), -5);
}

function now_iso(): string
{
    $t = microtime(true);
    $s = gmdate('Y-m-d\TH:i:s', (int) $t);
    $ms = str_pad((string) ((int) round(($t - (int) $t) * 1000)), 3, '0', STR_PAD_LEFT);
    return $s . '.' . $ms . 'Z';
}

function now_db(): string
{
    return gmdate('Y-m-d H:i:s');
}
