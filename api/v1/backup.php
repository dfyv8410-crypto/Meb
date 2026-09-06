<?php
/**
 * Backup endpoints: list / create / restore.
 * Snapshots are stored as JSON in /uploads/backups. Direct web access to
 * /uploads/backups is blocked (root .htaccess + per-dir .htaccess); the files
 * are only reachable through this admin API.
 */

declare(strict_types=1);

require_once __DIR__ . '/crud.php';

const BACKUP_DIR = '/uploads/backups';
const BACKUP_COLS = ['pages','catalog_categories','catalog','projects','materials','services','reviews','requests','users'];

function backup_dir(): string
{
    $d = MEB_ROOT . BACKUP_DIR;
    if (!is_dir($d)) @mkdir($d, 0777, true);
    return $d;
}

function backup_snapshot(string $label): array
{
    $data = ['_cols' => BACKUP_COLS, '_stamp' => now_iso()];
    foreach (BACKUP_COLS as $t) {
        $data[$t] = all_rows($t);
    }
    return $data;
}

function backup_write(array $snap, string $type): array
{
    $json = json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $ts = gmdate('Ymd-His');
    $filename = $type . '-' . $ts . '.json';
    $full = backup_dir() . '/' . $filename;
    $written = @file_put_contents($full, $json);
    if ($written === false) {
        throw new RuntimeException('Не удалось записать файл бэкапа: ' . $filename);
    }
    @chmod($full, 0600);
    $size = (int) filesize($full);
    $id = new_id();
    $st = db()->prepare('INSERT INTO backups (id,filename,size,type,created_at,updated_at) VALUES (?,?,?,?,?,?)');
    $st->execute([$id, $filename, $size, $type, now_db(), now_db()]);
    audit_log('backup', 'system', $id);
    $row = find_row('backups', $id);
    if (!$row) throw new RuntimeException('Не удалось найти запись бэкапа в БД');
    return $row;
}

function handle_backup(string $M, array $seg): void
{
    $u = current_user();

    if ($M === 'GET' && (empty($seg) || ($seg[0] ?? '') === 'list')) {
        if (!can($u, 'admin')) fail('Forbidden', 403);
        ok(array_reverse(all_rows('backups')));
    }
    if ($M === 'POST' && ($seg[0] ?? '') === 'create') {
        if (!can($u, 'admin')) fail('Forbidden', 403);
        if (!csrf_check_ok()) fail('Invalid CSRF', 403);
        $row = backup_write(backup_snapshot('manual'), 'manual');
        ok($row, 200);
    }
    if ($M === 'POST' && ($seg[0] ?? '') === 'restore' && isset($seg[1])) {
        if (!can($u, 'super_admin')) fail('Forbidden', 403);
        if (!csrf_check_ok()) fail('Invalid CSRF', 403);
        $b = find_row('backups', $seg[1]);
        if (!$b) fail('Not found', 404);
        // safety pre-restore snapshot (creates the row; fail loudly if unwritable)
        backup_write(backup_snapshot('pre-restore'), 'pre-restore');
        $full = backup_dir() . '/' . $b['filename'];
        if (!is_file($full)) fail('Backup file missing', 500);
        $snap = json_decode((string) file_get_contents($full), true);
        if (!is_array($snap) || empty($snap['_cols'])) fail('Invalid backup file', 500);
        // Wipe + reinsert under a single transaction spanning ALL tables, so a
        // failure mid-restore rolls back every table instead of leaving a half
        // applied / partially wiped database.
        db()->beginTransaction();
        try {
            foreach ($snap['_cols'] as $t) {
                if (!isset($snap[$t]) || !is_array($snap[$t])) continue;
                db()->query('DELETE FROM `' . safe_ident($t) . '`');
                foreach ($snap[$t] as $row) {
                    if (empty($row['id'])) continue;
                    collection_insert_id($t, $row);
                }
            }
            db()->commit();
        } catch (\Throwable $e) {
            db()->rollBack();
            error_log('backup restore failed: ' . $e->getMessage());
            fail('Restore failed. Подробности в журнале ошибок.', 500);
        }
        audit_log('restore', 'system', $b['id']);
        ok(true);
    }
    fail('Not found', 404);
}
