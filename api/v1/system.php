<?php
/**
 * System endpoints: update check/run (super_admin).
 * On shared hosting there is no git/node; update = checked patches only,
 * replaced by backup/restore workflow documented in SWEB_DEPLOY.md.
 */

declare(strict_types=1);

function handle_system(string $M, array $seg): void
{
    $u = current_user();
    if (!can($u, 'super_admin')) fail('Forbidden', 403);

    if ($M === 'GET' && ($seg[0] ?? '') === 'update' && ($seg[1] ?? '') === 'check') {
        // On shared hosting we can't git pull; report current version + availability
        $current = trim((string) @file_get_contents(MEB_ROOT . '/VERSION'));
        ok(['current' => $current, 'latest' => $current, 'updateAvailable' => false,
            'note' => 'Auto-update не доступен на shared-хостинге. Обновите вручную через FTP и сделайте бэкап.']);
    }
    if ($M === 'POST' && ($seg[0] ?? '') === 'update' && ($seg[1] ?? '') === 'run') {
        fail('Auto-update не поддерживается на shared-хостинге. Обновите файлы по FTP, затем проверьте миграции.', 400);
    }

    // Optimize all tables
    if ($M === 'POST' && ($seg[0] ?? '') === 'optimize-db') {
        if (!csrf_check_ok()) fail('Invalid CSRF', 403);
        $tables = [];
        try {
            $rows = db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
            foreach ($rows as $r) {
                $tbl = $r[0];
                db()->query("OPTIMIZE TABLE `$tbl`")->fetchAll();
                $tables[] = $tbl;
            }
            audit_log('optimize', 'system', count($tables) . ' tables');
            ok(['tables' => count($tables)]);
        } catch (\Throwable $e) {
            error_log('optimize-db failed: ' . $e->getMessage());
            fail('Optimize failed. Подробности в журнале ошибок.', 500);
        }
    }

    // Clear OPcache if available
    if ($M === 'POST' && ($seg[0] ?? '') === 'clear-cache') {
        if (!csrf_check_ok()) fail('Invalid CSRF', 403);
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        // Clear any file-based caches in storage
        $cleared = 0;
        $dirs = [MEB_DATA_DIR . '/cache', MEB_ROOT . '/storage/cache'];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                foreach ($files as $f) {
                    if (is_file($f)) { @unlink($f); $cleared++; }
                }
            }
        }
        audit_log('clear-cache', 'system', $cleared . ' files');
        ok(['cleared' => $cleared, 'opcache' => function_exists('opcache_reset')]);
    }
    fail('Not found', 404);
}

/**
 * Real write/read/delete probe on a directory. Returns true only if a
 * temporary file can be created, written, read back with identical content,
 * and removed (leaving no residue). Used by health to avoid trusting a bare
 * is_writable()/is_dir() check.
 */
function health_dir_probe(string $dir): bool
{
    if (!is_dir($dir)) return false;
    $probe = rtrim($dir, '/') . '/.health-probe-' . bin2hex(random_bytes(6)) . '.tmp';
    $token = 'MEB_HEALTH_' . bin2hex(random_bytes(4));
    if (@file_put_contents($probe, $token) === false) return false;
    $ok = false;
    $read = @file_get_contents($probe);
    if ($read === $token) {
        $ok = @unlink($probe) || !is_file($probe);
    } else {
        @unlink($probe);
    }
    return $ok && !is_file($probe);
}

/** GET /api/v1/health — public service status (real tests, no secrets leaked). */
function handle_health(): void
{
    $php = PHP_VERSION;

    // --- Database: real connection + SELECT 1 (do not expose credentials) ---
    $dbStatus = 'error';
    $dbMsg = 'Нет соединения с базой данных';
    try {
        db()->query('SELECT 1');
        $dbStatus = 'ok';
        $dbMsg = 'Соединение с БД и запрос работают';
    } catch (\Throwable $e) {
        $dbStatus = 'error';
        $dbMsg = 'Нет соединения с базой данных';
    }

    // --- Storage: real write/read/delete probe on critical media dir ---
    // Critical = the uploads dir (media is stored here and served publicly).
    $storageStatus = 'error';
    $storageMsg = 'Не удалось проверить хранилище';
    try {
        if (health_dir_probe(MEB_UPLOADS_DIR)) {
            $storageStatus = 'ok';
            $storageMsg = 'Хранилище: чтение/запись/удаление работают';
        } else {
            $storageStatus = 'error';
            $storageMsg = 'Не удалось выполнить тест записи в хранилище. Проверьте права каталога';
        }
    } catch (\Throwable $e) {
        $storageStatus = 'error';
        $storageMsg = 'Не удалось выполнить тест хранилища';
    }

    // --- Optional cache: detect working backend (APCu -> file -> none) ---
    // A missing/absent cache backend is NOT an error on shared hosting — the
    // site degrades gracefully. Report the backend actually available.
    $cacheStatus = 'notconfigured';
    $cacheBackend = 'none';
    $cacheMsg = 'Кэш не настроен, сайт работает без него';
    if (function_exists('apcu_enabled') && @apcu_enabled()) {
        $cacheStatus = 'ok';
        $cacheBackend = 'apcu';
        $cacheMsg = 'APCu доступен';
    } elseif (health_dir_probe(MEB_DATA_DIR . '/cache')) {
        $cacheStatus = 'ok';
        $cacheBackend = 'file';
        $cacheMsg = 'Файловый кэш доступен';
    }

    // --- Backup: is the subsystem actually configurable? ---
    // Separate "available/configured" from "how many backups exist".
    $backupStatus = 'notconfigured';
    $backupMsg = 'Резервное копирование не настроено';
    $backupsCount = 0;
    try {
        $backupsCount = (int) db()->query('SELECT COUNT(*) FROM backups')->fetchColumn();
    } catch (\Throwable $e) {
        $backupsCount = 0;
    }
    $backupDirProbe = @is_dir(MEB_UPLOADS_DIR . '/backups') ? health_dir_probe(MEB_UPLOADS_DIR . '/backups') : false;
    if ($backupDirProbe) {
        $backupStatus = 'ok';
        $backupMsg = 'Резервное копирование доступно (' . (int)$backupsCount . ' записей)';
    }

    // --- Overall (backward-compatible summary) ---
    $status = ($dbStatus === 'ok' && $storageStatus === 'ok') ? 'ok' : 'degraded';

    ok([
        'status'    => $status,
        'db'        => $dbStatus === 'ok',
        'storage'   => $storageStatus === 'ok',
        'backups'   => $backupsCount,
        'version'   => trim((string) @file_get_contents(MEB_ROOT . '/VERSION')),
        'php'       => $php,
        'runtime'   => 'php-' . $php,
        // structured, human-readable state (consumed by the admin SPA)
        'database'  => ['status' => $dbStatus, 'message' => $dbMsg],
        'storage'   => ['status' => $storageStatus, 'message' => $storageMsg],
        'api'       => ['status' => 'ok', 'message' => 'API отвечает'],
        'cache'     => ['status' => $cacheStatus, 'backend' => $cacheBackend, 'message' => $cacheMsg],
        'backup'    => ['status' => $backupStatus, 'message' => $backupMsg],
    ]);
}

/** GET /api/v1/audit_log — recent audit entries (admin+). */
function handle_audit_log(): void
{
    $u = current_user();
    if (!can($u, 'admin')) fail('Forbidden', 403);
    $rows = all_rows('audit_log');
    usort($rows, function ($a, $b) { return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''); });
    ok($rows);
}
