<?php
/**
 * Generic CRUD + shared collection helpers.
 */

declare(strict_types=1);

function collection_list(string $table): array
{
    return all_rows($table);
}

/**
 * Public-facing rows: filters out drafts for collections that carry a
 * published/is_active column (catalog, projects, pages -> published;
 * banners, menu_items -> is_active). Collections without such a column
 * (materials, services, catalog_categories) return all rows unchanged.
 */
function public_rows(string $table): array
{
    static $has = [];
    if (!array_key_exists($table, $has)) {
        $cols = [];
        try {
            $st = db()->prepare('SHOW COLUMNS FROM `' . safe_ident($table) . '`');
            $st->execute();
            $cols = array_column($st->fetchAll(), 'Field');
        } catch (\Throwable $e) {
            $cols = [];
        }
        $has[$table] = [
            'published' => in_array('published', $cols, true),
            'is_active' => in_array('is_active', $cols, true),
        ];
    }
    return array_values(array_filter(all_rows($table), function ($r) use ($has, $table) {
        if ($has[$table]['published'] && (int) ($r['published'] ?? 1) !== 1) return false;
        if ($has[$table]['is_active'] && (int) ($r['is_active'] ?? 1) !== 1) return false;
        return true;
    }));
}

function find_row_or_slug(string $table, string $id): ?array
{
    $row = find_row($table, $id);
    if (!$row && preg_match('/^[A-Za-z0-9._-]{1,120}$/', $id)) {
        $st = db()->prepare('SELECT * FROM `' . safe_ident($table) . '` WHERE slug = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch() ?: null;
        if ($row) $row = decode_json_cols($table, $row);
    }
    return $row;
}

function collection_insert(string $table, array $b): array
{
    $jsonCols = json_cols($table);
    $b = normalize_seed_row($table, $b);
    $cols = []; $vals = []; $ph = [];
    foreach ($b as $k => $v) {
        $k = safe_ident((string) $k);
        if ($k === '' || $k === 'id') continue;
        $cols[] = "`$k`"; $ph[] = '?';
        $vals[] = in_array($k, $jsonCols, true) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
    }
    $id = new_id();
    $cols[] = '`id`'; $ph[] = '?'; $vals[] = $id;
    $cols[] = '`created_at`'; $ph[] = '?'; $vals[] = now_db();
    $cols[] = '`updated_at`'; $ph[] = '?'; $vals[] = now_db();
    $sql = 'INSERT INTO `' . safe_ident($table) . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
    db()->prepare($sql)->execute($vals);
    return find_row($table, $id);
}

function collection_update(string $table, string $id, array $b): array
{
    $jsonCols = json_cols($table);
    $b = normalize_seed_row($table, $b);
    $sets = []; $vals = [];
    foreach ($b as $k => $v) {
        $k = safe_ident((string) $k);
        if ($k === '' || $k === 'id') continue;
        $sets[] = "`$k` = ?";
        $vals[] = in_array($k, $jsonCols, true) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
    }
    $vals[] = now_db();
    $vals[] = $id;
    $sql = 'UPDATE `' . safe_ident($table) . '` SET ' . implode(',', $sets) . ', `updated_at` = ? WHERE id = ?';
    db()->prepare($sql)->execute($vals);
    $row = find_row($table, $id);
    if (!$row) fail('Not found', 404); // never return null under :array contract
    return $row;
}

function collection_delete(string $table, string $id): void
{
    db()->prepare('DELETE FROM `' . safe_ident($table) . '` WHERE id = ?')->execute([$id]);
}

function collection_insert_id(string $table, array $row): void
{
    $jsonCols = json_cols($table);
    $cols = []; $vals = []; $ph = [];
    foreach ($row as $k => $v) {
        $k = safe_ident((string) $k);
        if ($k === '') continue;
        $cols[] = $k; $ph[] = '?';
        $vals[] = in_array($k, $jsonCols, true) && is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
    }
    if (!in_array('id', $cols, true)) {
        $cols[] = 'id'; $ph[] = '?'; $vals[] = new_id();
    }
    if (!in_array('created_at', $cols, true)) { $cols[] = 'created_at'; $ph[] = '?'; $vals[] = now_db(); }
    if (!in_array('updated_at', $cols, true)) { $cols[] = 'updated_at'; $ph[] = '?'; $vals[] = now_db(); }
    $sql = 'INSERT INTO `' . safe_ident($table) . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
    db()->prepare($sql)->execute($vals);
}

/**
 * Normalize a Node/JSON seed row into MySQL column names per table.
 * Maps camelCase JSON keys to snake_case schema columns and known aliases.
 * Returns the column name rewrite map for automation.
 */
function seed_col_map(string $table): array
{
    static $map = [
        'catalog_categories' => ['desc' => 'description'],
        'catalog'  => ['desc' => 'description', 'categoryId' => 'category_id'],
        'projects' => ['desc' => 'description'],
        'materials'=> ['desc' => 'description'],
        'services' => ['desc' => 'description', 'priceFrom' => 'price_from'],
        'reviews'  => [],
        'pages'    => ['desc' => 'description', 'seoTitle' => 'seo_title', 'seoDesc' => 'seo_desc'],
        'requests' => ['desc' => 'message'],
    ];
    return $map[$table] ?? [];
}

/** Apply seed_col_map aliases to a row for insertion. */
function normalize_seed_row(string $table, array $row): array
{
    $map = seed_col_map($table);
    foreach ($map as $from => $to) {
        if (array_key_exists($from, $row) && !array_key_exists($to, $row)) {
            $row[$to] = $row[$from];
            unset($row[$from]);
        }
    }
    return $row;
}

function handle_crud(string $METHOD, array $col, array $seg): void
{
    $table = $col['table'];
    $need  = $col['role'];
    $ent   = $col['entity'];
    $id    = $seg[1] ?? null;

    if ($METHOD === 'GET' && $id === null) ok(collection_list($table));
    if ($METHOD === 'GET' && $id !== null) {
        $row = find_row_or_slug($table, $id);
        if (!$row) fail('Not found', 404);
        ok($row);
    }

    $u = current_user();
    if (!can($u, $need)) fail('Forbidden', 403);

    if ($METHOD === 'POST') {
        $b = read_body();
        $row = collection_insert($table, $b);
        audit_log('create', $ent, $row['id']);
        ok($row, 200);
    }
    if ($METHOD === 'PUT') {
        if (!$id) fail('Not found', 404);
        $row = find_row_or_slug($table, $id);
        if (!$row) fail('Not found', 404);
        $b = read_body();
        $row = collection_update($table, $row['id'], $b);
        audit_log('update', $ent, $row['id']);
        ok($row);
    }
    if ($METHOD === 'DELETE') {
        if (!$id) fail('Not found', 404);
        $row = find_row_or_slug($table, $id);
        if (!$row) fail('Not found', 404);
        collection_delete($table, $row['id']);
        audit_log('delete', $ent, $row['id']);
        ok(true);
    }
    fail('Not found', 404);
}
