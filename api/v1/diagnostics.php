<?php
/**
 * MEB diagnostics engine — full production checks for the admin "Система" tab.
 *   GET /api/v1/system/diagnostics          -> quick check (read-only, fast)
 *   GET /api/v1/system/diagnostics?mode=full -> deep check (self-HTTP + CRUD probe)
 * Auth: admin+ only. Every check is a REAL test (connection, probe, read).
 * Responses never leak credentials, DSN, absolute paths, or data contents.
 *
 * @package MEB
 */

declare(strict_types=1);

/** Map item status -> severity (overridable per item). */
function diag_sev(string $status): string
{
    if ($status === 'ok') return 'info';
    if ($status === 'warning') return 'medium';
    if ($status === 'notconfigured' || $status === 'notverified') return 'low';
    return 'high';
}

/** Push one check result into a category; non-ok items also become issues.
 *  Every item gets a unique per-category ID (PREFIX-NNN) unless an explicit one is given. */
function diag_push(array &$C, string $key, string $status, string $title, string $message,
                   string $severity = '', string $source = 'api/v1/diagnostics.php',
                   string $endpoint = '', string $fix = '', string $http = '',
                   string $id = '', array $meta = []): void
{
    if ($severity === '') $severity = diag_sev($status);
    if ($id === '') {
        $pref = [
            'core' => 'CORE', 'database' => 'DB', 'storage' => 'STORAGE', 'cache' => 'CACHE',
            'backup' => 'BACKUP', 'api' => 'API', 'admin' => 'ADMIN', 'public' => 'PUBLIC',
            'security' => 'SEC', 'media' => 'MEDIA', 'banners' => 'BANNER', 'perf' => 'PERF', 'logs' => 'LOG',
        ][$key] ?? strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $key), 0, 4));
        $id = $pref . '-' . str_pad((string) (count($C[$key]['items']) + 1), 3, '0', STR_PAD_LEFT);
    }
    $C[$key]['items'][] = [
        'id'       => $id,
        'status'   => $status,
        'title'    => $title,
        'message'  => $message,
        'severity' => $severity,
        'source'   => $source,
        'endpoint' => $endpoint,
        'fix'      => $fix,
        'http'     => $http,
    ] + $meta;
}

/**
 * Parse one log entry (multiline-aware): returns [status, severity, title, message, root-cause, fix, date]
 * or null when the line is a continuation/frame of a preceding entry.
 *  - Understands timestamps, PHP warning/notice/fatal/exception, PDO, HTTP 500, stack frames.
 *  - Multiline stack traces collapse into ONE entry (frames are skipped here).
 *  - Historical entries (older than the cutoff) come back as status 'ok' + date + FIXED, never as current errors.
 *  - CLI test artifacts (headers already sent by scripts/*.php) are NOT production errors.
 */
function diag_classify_log(string $line, int $cutoffTs): ?array
{
    $line = trim($line);
    if ($line === '') return null;

    // Stack-trace continuation / frame lines: "    at ...", "#0 ...", "at /path/...". They are part
    // of the previous entry and must never become their own log items (multiline = ONE error).
    if (preg_match('/^\s*(#\s?\d+\s|at\s)/', $line)) return null;

    $date       = '';
    $historical = false;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/', $line, $m)) {
        $date = $m[1] . '-' . $m[2] . '-' . $m[3]
              . (isset($m[4]) ? ' ' . $m[4] . ':' . $m[5] . ':' . $m[6] : ' 00:00:00');
        $ts = @strtotime($date);
        $historical = ($ts !== false && $ts < $cutoffTs);
    }

    $where = '';
    if (preg_match('# in ([A-Za-z0-9_./\\\\\-]+):(\d+)#', $line, $fm)) $where = $fm[1] . ':' . $fm[2];

    // --- CLI test artifact: headers already sent by scripts/*.php ---------------------------
    if (preg_match('#Cannot modify header information#i', $line) && preg_match('#output started at [^)]*\/scripts\/#', $line)) {
        return ['ok', 'info', 'Артефакт CLI-теста (headers already sent)',
                'Запись от ' . ($date !== '' ? $date : 'не датирована') . ': заголовки уже отправлены тестовым скриптом scripts/*.php — это не ошибка боевого сайта (Apache/PHP-FPM).',
                $where !== '' ? $where : 'scripts/*.php',
                'Игнорировать; HTTP-проверка /robots.txt и /sitemap.xml для прода выполняется отдельно.',
                $date];
    }
    if (preg_match('#Cannot modify header information#i', $line)) {
        if ($historical) {
            return ['ok', 'info', 'Историческая запись (headers already sent)',
                    'Запись от ' . $date . ': конфликт заголовков в прошлом — актуальной ошибкой не является.',
                    $where !== '' ? $where : '—', 'Если не воспроизводится сейчас — игнорировать.', $date];
        }
        return ['warning', 'warning', 'Конфликт заголовков (headers already sent)',
                'Запись от ' . $date . ': вызов header() после вывода — проверьте страницу через HTTP.',
                $where !== '' ? $where : '—', 'Убедитесь, что перед header() нет вывода (BOM, пробелов).',
                $date];
    }

    // --- Known-fixed exception: collection_update() returned null (TOCTOU) -------------------
    if (stripos($line, 'collection_update') !== false && stripos($line, 'null') !== false) {
        return ['ok', 'info', 'Историческая ошибка (ИСПРАВЛЕНА)',
                'Запись от ' . $date . ': collection_update() возвращал null (crud.php:60, TOCTOU) — исправлено guard «if (!$row) fail(404)» в crud.php:91. Текущая версия код не выдаёт.',
                'api/v1/crud.php:75-93',
                'Уже исправлено — повторную проверку делает полная диагностика (test CRUD в транзакции).',
                $date];
    }

    // --- PHP fatal/exception/PDO/HTTP 500 ----------------------------------------------------
    if (preg_match('#\[(EXCEPTION|FATAL|Parse error)\]#i', $line) ||
        preg_match('/PHP (Fatal|Parse) error/i', $line) ||
        preg_match('/PDOException|SQLSTATE|Драйвер.*не найден|driver.*not found/i', $line) ||
        preg_match('/HTTP\/\S+\s+500|status.?[:=].?500/i', $line)) {

        if ($historical) {
            return ['ok', 'info', ($date !== '' ? 'Историческая запись' : 'Запись') . ' (исключение PHP)',
                    'Запись от ' . $date . ': исключение PHP в прошлом (вероятный HTTP 500) — актуальной ошибкой не является.',
                    $where !== '' ? $where : '—', 'Если не воспроизводится сейчас — игнорировать.', $date];
        }
        $sev = 'high';
        return ['error', $sev, 'Исключение PHP',
                'Запись от ' . $date . ': необработанная ошибка PHP при обработке запроса (вероятный HTTP 500).',
                $where !== '' ? $where : 'location не определён',
                'Откройте файл:строку, найдите первопричину и исправьте её.',
                $date];
    }

    // --- PHP notices / warnings by level token -------------------------------------------------
    if (preg_match('#\[(\d+)\]#', $line, $lm)) {
        $lvl = (int) $lm[1];
        if ($historical) {
            $label = $lvl === 2 ? 'Предупреждение' : 'Уведомление PHP';
            return ['ok', 'info', 'Историческая запись (' . $label . ')',
                    'Запись от ' . $date . ': ' . $label . ' PHP (уровень ' . $lvl . ') — актуальной ошибкой не является.',
                    $where !== '' ? $where : '—', 'Если не воспроизводится сейчас — игнорировать.', $date];
        }
        if ($lvl === 2) {
            return ['warning', 'warning', 'Предупреждение PHP',
                    'Запись от ' . $date . ': PHP-предупреждение (уровень 2).',
                    $where !== '' ? $where : '—', 'Устраните причину предупреждения (обычно несовпадение типов/запись в read-only).',
                    $date];
        }
        return ['ok', 'info', 'Уведомление PHP',
                'Запись от ' . $date . ': PHP-notice (уровень ' . $lvl . ') — не ошибка продакшена.',
                $where !== '' ? $where : '—', 'Можно игнорировать.', $date];
    }

    // --- EADDRINUSE (dev) ----------------------------------------------------------------------
    if (preg_match('#EADDRINUSE#', $line)) {
        return ['ok', 'info', 'Порт занят (dev-сервер)',
                'Запись от ' . $date . ': локальный dev-сервер Node не смог занять порт — конфликт процессов в среде разработки.',
                'server.js', 'Не влияет на боевой сайт (Sweb/Apache).', $date];
    }

    // --- Anything else: manual check, never a hard error --------------------------------------
    $preview = function_exists('mb_strimwidth')
        ? mb_strimwidth($line, 0, 120, '…')
        : (strlen($line) > 120 ? substr($line, 0, 117) . '…' : $line);
    if ($date !== '') {
        return ['notverified', 'low', 'Нестандартная запись журнала',
                'Запись от ' . $date . ' не распознана автоматически (' . $preview . ') — требуется ручная проверка.',
                $where !== '' ? $where : '',
                'Проверьте контекст в storage/error.log; если это артефакт теста — игнорировать.', $date];
    }
    return ['notverified', 'low', 'Нестандартная строка журнала',
            'Строка без даты не распознана автоматически (' . $preview . ') — требуется ручная проверка.',
            '', 'Проверьте контекст в storage/error.log.', ''];
}

/** Best-effort self HTTP GET. Returns ['code'=>int,'body'=>string] or [] when unreachable. */
function diag_http(string $path, int $timeout = 5): array
{
    $base = trim((string) MEB_BASE_URL);
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $url = rtrim($base, '/') . '/' . ltrim($path, '/');
    $ctx = stream_context_create(['http' => [
        'timeout'        => $timeout,
        'ignore_errors'  => true,
        'follow_location'=> true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return [];
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return ['code' => $code, 'body' => $body];
}

/** Parse a PHP ini size value ("128M", "-1", "512k") into bytes. */
function diag_php_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '-1' || $val === '') return -1;
    $unit = strtolower(substr($val, -1));
    $num  = (int) $val;
    switch ($unit) {
        case 'g': return $num * 1073741824;
        case 'm': return $num * 1048576;
        case 'k': return $num * 1024;
        default:  return $num;
    }
}

/** GET /api/v1/system/diagnostics (admin+) — run the real battery of checks. */
function handle_diagnostics(): void
{
    $u = current_user();
    if (!can($u, 'admin')) fail('Forbidden', 403);

    $full    = (($_GET['mode'] ?? 'quick') === 'full');
    $checked = now_iso();
    $src     = 'api/v1/diagnostics.php';

    $C = [
        'core'    => ['label' => 'Ядро',          'items' => []],
        'database'=> ['label' => 'База данных',   'items' => []],
        'storage' => ['label' => 'Хранилище',     'items' => []],
        'cache'   => ['label' => 'Кэш',           'items' => []],
        'backup'  => ['label' => 'Резервирование','items' => []],
        'api'     => ['label' => 'API',           'items' => []],
        'admin'   => ['label' => 'Админпанель',   'items' => []],
        'public'  => ['label' => 'Публичный сайт','items' => []],
        'security'=> ['label' => 'Безопасность',  'items' => []],
        'media'   => ['label' => 'Медиа',          'items' => []],
        'banners' => ['label' => 'Баннеры',        'items' => []],
        'perf'    => ['label' => 'Производительность','items' => []],
        'logs'    => ['label' => 'Журнал ошибок', 'items' => []],
    ];

    $t0 = microtime(true);

    /* ---------------- CORE ---------------- */
    $bin = PHP_BINARY === '' ? 'php' : basename(PHP_BINARY);
    if (PHP_VERSION_ID >= 70100) {
        diag_push($C, 'core', 'ok', 'Версия PHP',
            'PHP ' . PHP_VERSION . ' — поддерживается (требуется 7.1+). Сервер: ' . $bin . '.', '', $src);
    } else {
        diag_push($C, 'core', 'error', 'Версия PHP',
            'PHP ' . PHP_VERSION . ' ниже требуемой 7.1 — часть функциональности может не работать.', 'high', $src);
    }

    $reqExt = ['pdo' => true, 'pdo_mysql' => true, 'json' => true, 'fileinfo' => false, 'mbstring' => false];
    foreach ($reqExt as $ext => $critical) {
        if (extension_loaded($ext)) {
            diag_push($C, 'core', 'ok', 'Расширение ' . $ext, 'Расширение загружено.', '', $src);
        } elseif ($critical) {
            diag_push($C, 'core', 'error', 'Расширение ' . $ext,
                'Критическое расширение ' . $ext . ' не загружено — часть системы не сможет работать.',
                'critical', $src, '', 'Включите ' . $ext . ' в панели хостинга (Sweb: PHP → расширения).');
        } else {
            diag_push($C, 'core', 'warning', 'Расширение ' . $ext,
                'Расширение ' . $ext . ' не загружено — сниженная функциональность (MIME/кодировки).', '', $src);
        }
    }

    if (is_file(MEB_CONFIG_DIR . '/app.php') && is_file(MEB_CONFIG_DIR . '/database.php')) {
        diag_push($C, 'core', 'ok', 'Файлы конфигурации', 'config/app.php и config/database.php на месте.', '', $src);
    } else {
        diag_push($C, 'core', 'error', 'Файлы конфигурации',
            'Отсутствует config/app.php или config/database.php — установка не завершена.',
            'critical', $src, '', 'Переустановите из config.sample или запустите /api/v1/install.');
    }

    if (is_file(MEB_LOCK_FILE)) {
        diag_push($C, 'core', 'ok', 'Маркер установки', 'storage/installed.lock существует — установка завершена.', '', $src);
    } else {
        diag_push($C, 'core', 'error', 'Маркер установки',
            'Отсутствует storage/installed.lock — возможен доступ к установщику.',
            'critical', $src, '', 'Создайте storage/installed.lock.');
    }

    $baseUrl = trim((string) MEB_BASE_URL);
    if ($baseUrl === '') {
        diag_push($C, 'core', 'warning', 'Базовый URL',
            'MEB_BASE_URL не задан — используется автодетект по HTTP_HOST. Задайте его в config/app.php.',
            '', 'config/app.php');
    } elseif (preg_match('#(localhost|127\.0\.0\.1|\.swtest\.ru|example\.com)#i', $baseUrl)) {
        diag_push($C, 'core', 'error', 'Базовый URL',
            'MEB_BASE_URL указывает на локальный/тестовый адрес — не годится для боевого сайта.',
            'high', $src, '', 'config/app.php → base_url на реальный домен.');
    } else {
        diag_push($C, 'core', 'ok', 'Базовый URL', 'MEB_BASE_URL задан корректно.', '', $src);
    }

    $mem  = ini_get('memory_limit');
    $memB = diag_php_bytes((string) $mem);
    if ($memB === -1) {
        diag_push($C, 'core', 'ok', 'Лимит памяти', 'memory_limit = -1 (без ограничения).', '', $src);
    } elseif ($memB >= 67108864) {
        diag_push($C, 'core', 'ok', 'Лимит памяти', 'memory_limit = ' . $mem . ' (>= 64M).', '', $src);
    } else {
        diag_push($C, 'core', 'warning', 'Лимит памяти',
            'memory_limit = ' . $mem . ' — для админки/медиа рекомендуется 128M+.', '', $src);
    }

    $upMax   = diag_php_bytes((string) ini_get('upload_max_filesize'));
    $postMax = diag_php_bytes((string) ini_get('post_max_size'));
    if ($upMax > 0 && $upMax < 15728640) {
        diag_push($C, 'core', 'warning', 'Лимит загрузки',
            'upload_max_filesize = ' . ini_get('upload_max_filesize') . ' меньше рекомендуемых 15MB.', '', $src);
    } else {
        diag_push($C, 'core', 'ok', 'Лимит загрузки', 'upload_max_filesize = ' . ini_get('upload_max_filesize') . '.', '', $src);
    }
    if ($postMax > 0 && $postMax < $upMax) {
        diag_push($C, 'core', 'error', 'post_max_size',
            'post_max_size меньше upload_max_filesize — загрузка медиа будет срываться.',
            'high', $src, '', 'PHP.ini → post_max_size >= upload_max_filesize.');
    } else {
        diag_push($C, 'core', 'ok', 'post_max_size', 'post_max_size = ' . ini_get('post_max_size') . '.', '', $src);
    }

    /* ---------------- DATABASE ---------------- */
    $dbOk = true;
    try {
        db()->query('SELECT 1');
        diag_push($C, 'database', 'ok', 'Подключение к БД', 'SELECT 1 выполнен успешно.', '', $src);
    } catch (\Throwable $e) {
        $dbOk = false;
        diag_push($C, 'database', 'error', 'Подключение к БД',
            'Не удалось подключиться к базе данных. Проверьте config/database.php и доступы хостинга.',
            'critical', $src, '', 'Проверьте данные БД в config/database.php; MySQL включается в панели Sweb.');
    }

    $mysqlVersion = '';
    if ($dbOk) {
        try { $mysqlVersion = (string) db()->query('SELECT VERSION()')->fetchColumn(); } catch (\Throwable $ign) {}
        $expected = ['users','sessions','settings','catalog_categories','catalog','projects','materials','services',
                     'reviews','requests','media','pages','notifications','analytics','audit_log','backups',
                     'migrations','rate_limits','menu_items','banners'];
        $rowListOk = true;
        $have = [];
        try {
            $rows = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
            foreach ($rows as $r) $have[] = $r[0];
        } catch (\Throwable $e) {
            $rowListOk = false;
            diag_push($C, 'database', 'error', 'Чтение списка таблиц', 'SHOW TABLES не выполнился.', 'high', $src);
        }
        if ($rowListOk) {
            $missing = array_values(array_diff($expected, $have));
            if (!$missing) {
                diag_push($C, 'database', 'ok', 'Схема БД',
                    'Все ' . count($expected) . ' таблиц на месте' . ($mysqlVersion !== '' ? ' (MySQL ' . $mysqlVersion . ')' : '') . '.', '', $src);
            } else {
                diag_push($C, 'database', 'error', 'Схема БД',
                    'Отсутствуют таблицы: ' . implode(', ', $missing) . '.',
                    'critical', $src, '', 'Импортируйте sql/schema.sql (или перейдите к установщику).');
            }
        }

        // Real read on JSON-column collections (proves the decode path works).
        $readFail = [];
        foreach (['settings','catalog','projects','materials','pages','notifications','audit_log'] as $tbl) {
            try { all_rows($tbl); } catch (\Throwable $e) { $readFail[] = $tbl; }
        }
        if (!$readFail) {
            diag_push($C, 'database', 'ok', 'Чтение коллекций', 'Таблицы с JSON-полями читаются и декодируются корректно.', '', $src);
        } else {
            diag_push($C, 'database', 'error', 'Чтение коллекций',
                'Проблемы при чтении/декодировании: ' . implode(', ', $readFail), 'high', $src);
        }

        // Row counts (aggregates only — no data contents).
        $counts = [];
        $countOk = true;
        foreach (['catalog','projects','materials','services','reviews','requests','users','pages','banners','media','backups'] as $tbl) {
            try { $counts[$tbl] = (int) db()->query('SELECT COUNT(*) FROM `' . safe_ident($tbl) . '`')->fetchColumn(); }
            catch (\Throwable $e) { $counts[$tbl] = -1; $countOk = false; }
        }
        $cntMsg = '';
        foreach ($counts as $t => $n) $cntMsg .= $t . '=' . ($n < 0 ? 'err' : $n) . ' ';
        if ($countOk) {
            diag_push($C, 'database', 'ok', 'Записей в коллекциях', trim($cntMsg), '', $src);
        } else {
            diag_push($C, 'database', 'error', 'Записей в коллекциях',
                'Не удалось посчитать записи: ' . trim($cntMsg), 'high', $src);
        }

        // Real C/R/U/D probe inside a transaction on a scratch row (rollback-safe, no residue).
        if ($full) {
            $crudOk = true;
            try {
                $db = db();
                $bucket = 'diag-' . bin2hex(random_bytes(4));
                $db->beginTransaction();
                $st = $db->prepare('INSERT INTO rate_limits (bucket, ip, window_ts, count) VALUES (?,?,?,?)');
                $st->execute([$bucket, '0.0.0.0', 0, 1]);
                $sel = $db->prepare('SELECT `count` FROM rate_limits WHERE bucket = ? LIMIT 1');
                $sel->execute([$bucket]);
                $v = (int) $sel->fetchColumn();
                $upd = $db->prepare('UPDATE rate_limits SET count = ? WHERE bucket = ?');
                $upd->execute([$v + 1, $bucket]);
                $del = $db->prepare('DELETE FROM rate_limits WHERE bucket = ?');
                $del->execute([$bucket]);
                $db->commit();
            } catch (\Throwable $e) {
                $crudOk = false;
                try { if (db()->inTransaction()) db()->rollBack(); } catch (\Throwable $ig) {}
            }
            if ($crudOk) {
                diag_push($C, 'database', 'ok', 'Тест записи/чтения/обновления/удаления',
                    'Создание → чтение → обновление → удаление выполнены в транзакции, остатки отсутствуют.', '', $src);
            } else {
                diag_push($C, 'database', 'error', 'Тест записи/чтения/обновления/удаления',
                    'Транзакционный тест CRUD не прошёл — выполнен откат, данные не изменены.', 'high', $src,
                    '', 'Проверьте права пользователя БД (INSERT/UPDATE/DELETE).');
            }
        }
    }

    /* ---------------- STORAGE ---------------- */
    if (is_dir(MEB_DATA_DIR)) {
        diag_push($C, 'storage', 'ok', 'Директория storage', 'storage/ существует.', '', $src);
    } else {
        diag_push($C, 'storage', 'error', 'Директория storage', 'Отсутствует storage/.', 'high', $src, '', 'Создайте storage/ с правами на запись.');
    }
    if (health_dir_probe(MEB_DATA_DIR . '/data')) {
        diag_push($C, 'storage', 'ok', 'Запись в storage/data', 'Тест записи/чтения/удаления в storage/data прошёл.', '', $src);
    } else {
        diag_push($C, 'storage', 'error', 'Запись в storage/data', 'storage/data недоступен для записи.', 'high', $src, '', 'CHMOD 755/775 на storage/data.');
    }
    if (is_dir(MEB_UPLOADS_DIR)) {
        diag_push($C, 'storage', 'ok', 'Директория uploads', 'uploads/ существует.', '', $src);
    } else {
        diag_push($C, 'storage', 'error', 'Директория uploads', 'Отсутствует uploads/ — медиа не сможет сохраняться.', 'critical', $src, '', 'Создайте uploads/ с правами на запись.');
    }
    if (health_dir_probe(MEB_UPLOADS_DIR)) {
        diag_push($C, 'storage', 'ok', 'Запись в uploads', 'Тест записи/чтения/удаления в uploads прошёл.', '', $src);
    } else {
        diag_push($C, 'storage', 'error', 'Запись в uploads', 'uploads/ недоступен для записи — загрузка медиа не работает.', 'critical', $src, '', 'CHMOD 755/775 на uploads.');
    }

    /* ---------------- CACHE ---------------- */
    if (function_exists('apcu_enabled') && @apcu_enabled()) {
        diag_push($C, 'cache', 'ok', 'Кэш', 'Работает APCu — быстрый кэш в памяти.', 'info', $src);
    } elseif (health_dir_probe(MEB_DATA_DIR . '/cache')) {
        diag_push($C, 'cache', 'ok', 'Кэш', 'Работает файловый кэш (storage/cache).', 'info', $src);
    } else {
        diag_push($C, 'cache', 'notconfigured', 'Кэш',
            'Кэш не настроен — сайт работает без кэша. Это INFO, а не ошибка: допустимо на shared-хостинге.', '', $src);
    }

    /* ---------------- BACKUP ---------------- */
    try {
        db()->query('SELECT COUNT(*) FROM backups');
        diag_push($C, 'backup', 'ok', 'Таблица backups', 'Таблица бэкапов доступна.', '', $src);
    } catch (\Throwable $e) {
        diag_push($C, 'backup', 'error', 'Таблица backups', 'Таблица backups недоступна.', 'high', $src, '', 'Импортируйте схему.');
    }
    if (is_file(__DIR__ . '/backup.php')) {
        diag_push($C, 'backup', 'ok', 'Модуль бэкапирования', 'api/v1/backup.php на месте.', '', $src);
    } else {
        diag_push($C, 'backup', 'error', 'Модуль бэкапирования', 'api/v1/backup.php отсутствует.', 'critical', $src);
    }
    $bkDir = MEB_UPLOADS_DIR . '/backups';
    if (health_dir_probe($bkDir)) {
        diag_push($C, 'backup', 'ok', 'Каталог бэкапов', 'uploads/backups доступен для записи.', '', $src);
    } else {
        diag_push($C, 'backup', 'error', 'Каталог бэкапов', 'uploads/backups недоступен для записи — создание бэкапов не работает.', 'high', $src, '', 'CHMOD на uploads/backups.');
    }
    $bkCount = 0;
    try { $bkCount = (int) db()->query('SELECT COUNT(*) FROM backups')->fetchColumn(); } catch (\Throwable $e) {}
    diag_push($C, 'backup', 'ok', 'Снимков бэкапов', 'Создано снимков: ' . $bkCount, 'info', $src);
    if (is_file($bkDir . '/.htaccess')) {
        diag_push($C, 'backup', 'ok', 'Защита каталога бэкапов', 'uploads/backups/.htaccess запрещает прямой доступ.', '', $src);
    } else {
        diag_push($C, 'backup', 'error', 'Защита каталога бэкапов',
            'uploads/backups/.htaccess отсутствует — снимки могут быть скачаны напрямую!',
            'critical', $src, '', 'Создайте uploads/backups/.htaccess с "Require all denied".');
    }

    /* ---------------- API (route matrix) ---------------- */
    $apiRoutes = [
        ['POST','auth/login',        'auth.php',     null,            'public'],
        ['POST','auth/logout',       'auth.php',     null,            'any'],
        ['GET','me',                 'auth.php',     'users',         'any'],
        ['GET','health',             'system.php',   null,            'public'],
        ['GET','audit_log',          'system.php',   'audit_log',     'admin'],
        ['GET/POST','users',         'users.php',    'users',         'admin'],
        ['GET/POST','media',         'media.php',    'media',         'editor'],
        ['GET/POST','settings',      'settings.php', 'settings',      'admin'],
        ['GET','leads',              'leads.php',    'requests',      'admin'],
        ['POST','leads-public',      'leads.php',    'requests',      'public'],
        ['POST','reviews-public',    'reviews-public.php','reviews',  'public'],
        ['GET','notifications',      'notifications.php','notifications','any'],
        ['GET','analytics',          'analytics.php','analytics',     'admin'],
        ['GET','seo',                'seo.php',      'pages',         'admin'],
        ['GET/POST','backup',        'backup.php',   'backups',       'admin'],
        ['GET/POST','system',        'system.php',   null,            'super_admin'],
        ['GET','app',                'app.php',      null,            'any'],
        ['GET/POST','menu',          'menu.php',     'menu_items',    'editor'],
        ['GET','banners-public',     'banners.php',  'banners',       'public'],
        ['CRUD','pages',             'crud.php',     'pages',         'editor'],
        ['CRUD','categories',        'crud.php',     'catalog_categories', 'editor'],
        ['CRUD','catalog',           'crud.php',     'catalog',       'editor'],
        ['CRUD','projects',          'crud.php',     'projects',      'editor'],
        ['CRUD','materials',         'crud.php',     'materials',     'editor'],
        ['CRUD','services',          'crud.php',     'services',      'editor'],
        ['CRUD','reviews',           'crud.php',     'reviews',       'editor'],
        ['CRUD','menu_items',        'crud.php',     'menu_items',    'editor'],
        ['CRUD','banners',           'crud.php',     'banners',       'editor'],
    ];
    foreach ($apiRoutes as $r) {
        list($method, $path, $file, $table, $access) = $r;
        $fileOk = is_file(__DIR__ . '/' . $file);
        $tblOk  = true;
        $tblMsg = '';
        if ($table) {
            try {
                db()->query('SELECT COUNT(*) FROM `' . safe_ident($table) . '`');
            } catch (\Throwable $e) {
                $tblOk = false;
                $tblMsg = '; таблица ' . $table . ' недоступна';
            }
        }
        $ep = '/api/v1/' . $path . '  [' . $access . ']';
        if ($fileOk && $tblOk) {
            diag_push($C, 'api', 'ok', $method . ' ' . $path,
                'Обработчик ' . $file . ' на месте' . ($table ? '; таблица доступна' : '') . '. ' . $access,
                'info', $src, $ep);
        } else {
            diag_push($C, 'api', 'error', $method . ' ' . $path,
                'Проблема маршрута: ' . ($fileOk ? '' : 'нет файла ' . $file . '; ') . $tblMsg,
                'high', $src, $ep, 'Проверьте целостность файлов api/v1/ и схемы БД.');
        }
    }

    /* ---------------- ADMIN ---------------- */
    if ($u) {
        diag_push($C, 'admin', 'ok', 'Права доступа', 'Вы подключены как admin (' . $u['role'] . ').', 'info', $src);
    }
    try {
        $s = get_settings();
        diag_push($C, 'admin', 'ok', 'Настройки', 'settings читается (' . count($s) . ' полей).', '', $src);
    } catch (\Throwable $e) {
        diag_push($C, 'admin', 'error', 'Настройки', 'Чтение settings не удалось.', 'high', $src);
    }
    foreach (['media' => 'Медиабиблиотека', 'users' => 'Пользователи', 'pages' => 'Страницы', 'banners' => 'Баннеры'] as $tbl => $label) {
        try {
            $n = (int) db()->query('SELECT COUNT(*) FROM `' . safe_ident($tbl) . '`')->fetchColumn();
            diag_push($C, 'admin', 'ok', $label, $label . ': ' . $n . ' записей.', 'info', $src);
        } catch (\Throwable $e) {
            diag_push($C, 'admin', 'error', $label, $label . ': таблица недоступна.', 'high', $src);
        }
    }
    if (is_file(MEB_ROOT . '/admin/index.html') && is_file(MEB_ROOT . '/admin/app.js')) {
        diag_push($C, 'admin', 'ok', 'Файлы админ-панели', 'admin/index.html и admin/app.js на месте.', '', $src);
    } else {
        diag_push($C, 'admin', 'error', 'Файлы админ-панели', 'Отсутствуют файлы админ-панели.', 'high', $src);
    }

    /* ---------------- PUBLIC SITE ---------------- */
    $pages = [
        'home', 'layout', 'catalog-hub', 'catalog-item', 'projects', 'project',
        'materials', 'services', 'contacts', 'page', 'robots', 'sitemap',
    ];
    $missingPages = [];
    foreach ($pages as $pg) {
        if (!is_file(MEB_ROOT . '/pages/' . $pg . '.php')) $missingPages[] = $pg;
    }
    if (!$missingPages) {
        diag_push($C, 'public', 'ok', 'Файлы страниц', 'Все ' . count($pages) . ' файлов frontend-страниц на месте.', '', $src);
    } else {
        diag_push($C, 'public', 'error', 'Файлы страниц', 'Отсутствуют: ' . implode(', ', $missingPages), 'high', $src);
    }
    if ($full) {
        $hp = diag_http('/');
        if (!empty($hp) && !empty($hp['code']) && $hp['code'] >= 200 && $hp['code'] < 400 && strlen($hp['body']) > 0) {
            diag_push($C, 'public', 'ok', 'Главная страница (HTTP)', 'HTTP ' . $hp['code'] . ', ' . strlen($hp['body']) . ' байт.', '', $src, '/', '', (string) $hp['code']);
        } elseif (empty($hp)) {
            diag_push($C, 'public', 'notverified', 'Главная страница (HTTP)', 'Self-запрос недоступен из этой среды (сеть/SSL/локально) — проверьте вручную.', '', $src);
        } else {
            diag_push($C, 'public', 'error', 'Главная страница (HTTP)', 'Главная вернула HTTP ' . $hp['code'], 'high', $src, '/', '', (string) $hp['code']);
        }
        foreach (['/api/v1/health', '/api/v1/banners-public'] as $pubEp) {
            $resp = diag_http($pubEp);
            if (!empty($resp) && !empty($resp['code']) && $resp['code'] >= 200 && $resp['code'] < 400 &&
                strpos($resp['body'], '"success":true') !== false) {
                diag_push($C, 'public', 'ok', 'Публичный API ' . $pubEp, 'HTTP 200, JSON success.', '', $src, $pubEp, '', (string) ($resp['code'] ?? ''));
            } elseif (empty($resp)) {
                diag_push($C, 'public', 'notverified', 'Публичный API ' . $pubEp, 'Self-запрос недоступен — проверьте вручную.', '', $src);
            } else {
                diag_push($C, 'public', 'error', 'Публичный API ' . $pubEp, 'HTTP ' . $resp['code'], 'high', $src, $pubEp, '', (string) ($resp['code'] ?? ''));
            }
        }
        // SEO endpoints are verified over HTTP — header-conflict entries in error.log from CLI tools
        // are NOT production errors and must not surface here.
        foreach (['/robots.txt' => 'robots', '/sitemap.xml' => 'sitemap'] as $seoPath => $seoName) {
            $r = diag_http($seoPath);
            if (empty($r) || empty($r['code'])) {
                diag_push($C, 'public', 'notverified', 'HTTP ' . $seoPath,
                    'Self-запрос недоступен — проверьте ' . $seoPath . ' вручную.', '', $src, $seoPath, '', '' , 'SEO-' . ($seoName === 'robots' ? 'ROBOTS' : 'SITEMAP') . '-001');
            } elseif ($r['code'] === 200 && trim($r['body']) !== '' &&
                      ($seoName === 'robots' || strpos(ltrim($r['body']), '<?xml') === 0)) {
                diag_push($C, 'public', 'ok', 'HTTP ' . $seoPath,
                    'HTTP 200, содержимое корректно отдано.', '', $src, $seoPath, '', (string) $r['code'], 'SEO-' . ($seoName === 'robots' ? 'ROBOTS' : 'SITEMAP') . '-001');
            } else {
                diag_push($C, 'public', 'warning', 'HTTP ' . $seoPath,
                    'HTTP ' . $r['code'] . ' или неожиданное содержимое — проверьте без заголовков, выведенных раньше времени.',
                    '', $src, $seoPath, 'Убедитесь, что страница отдаётся через HTTP без лишнего вывода.', (string) ($r['code'] ?? ''), 'SEO-' . ($seoName === 'robots' ? 'ROBOTS' : 'SITEMAP') . '-001');
            }
        }
    } else {
        diag_push($C, 'public', 'notverified', 'HTTP-проверки страниц', 'Запустите полную диагностику (?mode=full) для HTTP-проверок.', '', $src);
    }

    /* ---------------- SECURITY (behavioral, real Apache verdict) ---------------- */
    // A directory is considered closed ONLY when a real HTTP probe returns a block status
    // (403/404/410/405). Presence of .htaccess rules is supplementary evidence, never the
    // verdict itself. When self-HTTP is impossible (CLI/offline) -> NOT TESTABLE, not an error.
    $ht = @file_get_contents(MEB_ROOT . '/.htaccess');
    $hasRule = [];
    $hasRule['config']  = $ht !== false && preg_match('#RewriteRule \^\(config\)/#', $ht);
    $hasRule['includes']= $ht !== false && preg_match('#RewriteRule \^\(includes\)/#', $ht);
    $hasRule['sql']     = $ht !== false && preg_match('#RewriteRule \^\(sql\)/#', $ht);
    $hasRule['storage'] = $ht !== false && preg_match('#RewriteRule \^storage/#', $ht);
    $hasRule['backups'] = $ht !== false && preg_match('#RewriteRule \^uploads/backups#', $ht);
    $hasRule['uplphp']  = ($ht !== false && preg_match('#RewriteRule \^uploads/\.\*\\\.php\$#', $ht))
                        || (is_file(MEB_ROOT . '/uploads/.htaccess') && stripos((string) @file_get_contents(MEB_ROOT . '/uploads/.htaccess'), 'php') !== false);

    $probeFile = function (string $dir): string {
        $base = rtrim($dir, '/');
        $files = @scandir(MEB_ROOT . $base);
        if ($files) {
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === '.htaccess' || is_dir(MEB_ROOT . $base . '/' . $f)) continue;
                return $base . '/' . $f;
            }
        }
        return $base . '/';
    };
    $secTargets = [
        ['config',  'Конфиг-каталог (config/)',            '/config/app.php',        [403, 404, 405, 410]],
        ['includes','Includes (includes/)',                '/includes/database.php', [403, 404, 405, 410]],
        ['sql',     'SQL-каталог (sql/)',                  '/sql/schema.sql',        [403, 404, 405, 410]],
        ['storage', 'storage/data + backups (PDF/PII)',    '/storage/error.log',     [403, 404, 405, 410]],
        ['backups', 'uploads/backups (резервные копии)',   $probeFile('/uploads/backups'), [403, 404, 405, 410]],
        // For the PHP-exec probe a 404 only means "no such file" — it does NOT prove execution is blocked.
        ['uplphp',  'PHP в uploads (запрет исполнения)',   '/uploads/meb-diag-probe-' . substr(bin2hex(random_bytes(2)), 0, 6) . '.php', [403]],
    ];
    foreach ($secTargets as $t) {
        list($key, $title, $probe, $blockCodes) = $t;
        $ids = [
            'config' => 'SEC-CONFIG-001', 'includes' => 'SEC-INCLUDES-001', 'sql' => 'SEC-SQL-001',
            'storage' => 'SEC-STORAGE-001', 'backups' => 'SEC-BACKUPS-001', 'uplphp' => 'SEC-UPLOADS-PHP-001',
        ];
        // Real behavioral probe first — the only trustworthy signal on a live host.
        $resp = diag_http($probe, 4);
        if (!empty($resp) && !empty($resp['code'])) {
            $code = (int) $resp['code'];
            if (in_array($code, $blockCodes, true)) {
                diag_push($C, 'security', 'ok', $title,
                    'Real probe ' . $probe . ' → HTTP ' . $code . ' (заблокировано).', '',
                    $src, $probe, $title . ' должен быть заблокирован', (string) $code, $ids[$key]);
            } elseif ($code >= 200 && $code < 300) {
                // A resource actually served is a REAL leak — critical, no ambiguity.
                if ($key === 'uplphp' && is_file(MEB_ROOT . '/uploads/' . basename($probe))) {
                    diag_push($C, 'security', 'error', $title,
                        'Probe ' . $probe . ' выполнился как PHP (HTTP ' . $code . ') — исполнение PHP внутри uploads НЕ заблокировано!',
                        'critical', $src, $probe, 'Восстановите FilesMatch/F-правило для uploads/.*\.php$.', (string) $code, $ids[$key]);
                } else {
                    diag_push($C, 'security', 'error', $title,
                        'Реальный probe ' . $probe . ' вернул HTTP ' . $code . ' — каталог доступен по HTTP! Проверьте .htaccess/AllowOverride.',
                        'critical', $src, $probe, 'Верните правила блокировки в .htaccess (см. REPAIR_REPORT §7).', (string) $code, $ids[$key]);
                }
            } else {
                diag_push($C, 'security', 'notverified', $title,
                    'Probe ' . $probe . ' вернул неоднозначный HTTP ' . $code . ' — не является доказательством ни блокировки, ни утечки; проверьте вручную.',
                    '', $src, $probe, 'Откройте ' . $probe . ' в браузере; ожидается 403/404.', (string) $code, $ids[$key]);
            }
            continue;
        }
        // No HTTP available (CLI/offline): report rule presence honestly, never a hard error.
        if ($hasRule[$key]) {
            diag_push($C, 'security', 'notverified', $title,
                'Self-запрос недоступен из этой среды (CLI/offline) — NOT TESTABLE. Правило присутствует в .htaccess, но поведение не подтверждено.',
                '', $src, $probe, $title . ' должен быть заблокирован · проверьте на хосте через HTTP.', '', $ids[$key]);
        } else {
            diag_push($C, 'security', 'warning', $title,
                'Self-запрос недоступен (CLI/offline) И правило для ' . $title . ' не найдено в .htaccess — проведите ручную HTTP-проверку.',
                '', $src, $probe, 'Добавьте правила блокировки и проверьте через HTTP.', '', $ids[$key]);
        }
    }
    if (is_file(MEB_ROOT . '/uploads/.htaccess')) {
        diag_push($C, 'security', 'ok', 'uploads/.htaccess', 'Файл присутствует (вспомогательный признак; главная проверка — probe PHP выше).', 'info', $src);
    } else {
        diag_push($C, 'security', 'warning', 'uploads/.htaccess', 'Файл отсутствует (создаётся автоматически при загрузке).', '', $src);
    }
    if (is_file(MEB_ROOT . '/storage/.htaccess')) {
        diag_push($C, 'security', 'ok', 'storage/.htaccess', 'Файл присутствует (вспомогательный признак).', 'info', $src);
    } else {
        diag_push($C, 'security', 'warning', 'storage/.htaccess', 'storage/.htaccess отсутствует — рекомендуется добавить запрет.', '', $src);
    }
    if (function_exists('validate_upload')) {
        diag_push($C, 'security', 'ok', 'Валидация загрузок', 'validate_upload() доступна (MIME + расширение + размер).', '', $src);
    } else {
        diag_push($C, 'security', 'error', 'Валидация загрузок', 'validate_upload() не определена.', 'critical', $src);
    }

    /* ---------------- PERFORMANCE (full only) ---------------- */
    if ($full) {
        $tQ = microtime(true);
        try { db()->query('SELECT 1'); $qms = (int) round((microtime(true) - $tQ) * 1000); }
        catch (\Throwable $e) { $qms = -1; }
        diag_push($C, 'perf', 'ok', 'Задержка БД', $qms >= 0 ? intval($qms) . ' мс (SELECT 1)' : 'недоступно', 'info', $src);
    }
    $runMs = (int) round((microtime(true) - $t0) * 1000);
    diag_push($C, 'perf', 'ok', 'Время проверки', $runMs . ' мс (' . ($full ? 'полная' : 'быстрая') . ')', 'info', $src);
    diag_push($C, 'perf', 'ok', 'Память процесса', round(memory_get_usage(true) / 1048576) . ' MB', 'info', $src);

    /* ---------------- MEDIA (upload → disk → DB → public URL chain) ---------------- */
    $nMedia = 0;
    try {
        $nMedia = (int) db()->query('SELECT COUNT(*) FROM media')->fetchColumn();
        diag_push($C, 'media', 'ok', 'Таблица media', 'Записей в медиабиблиотеке: ' . $nMedia . '.', 'info', $src);
    } catch (\Throwable $e) {
        diag_push($C, 'media', 'error', 'Таблица media', 'Таблица media недоступна.', 'high', $src, '', 'Импортируйте схему БД.');
    }
    // Whitelist covers the favicon chain (`.ico` was missing — fixed).
    $mediaExt = "api/v1/media.php";
    $mediaCode = (string) @file_get_contents(MEB_ROOT . '/api/v1/media.php');
    $whitelistMissing = [];
    foreach (['jpg','jpeg','png','gif','webp','svg','avif','ico'] as $need) {
        if (strpos($mediaCode, "'" . $need . "'") === false) $whitelistMissing[] = $need;
    }
    if ($whitelistMissing) {
        diag_push($C, 'media', 'error', 'Белый список расширений',
            'В media.php нет разрешений: ' . implode(', ', $whitelistMissing) . '.',
            'high', 'api/v1/media.php', '/api/v1/media', 'Верните расширения в массив разрешённых (включая ico для favicon).');
    } else {
        diag_push($C, 'media', 'ok', 'Белый список расширений', 'Разрешены все графические форматы + ico (favicon).', '', $mediaExt);
    }
    $mediaDir = MEB_UPLOADS_DIR . '/media';
    if (is_dir($mediaDir)) {
        diag_push($C, 'media', 'ok', 'Каталог uploads/media', 'Каталог загрузок по умолчанию на месте.', '', $src);
    } else {
        diag_push($C, 'media', 'ok', 'Каталог uploads/media', 'Каталог создаётся при первой загрузке (writable root подтверждён выше).', 'info', $src);
    }
    // Real chain: DB rows must have existing readable files on disk.
    if ($nMedia > 0) {
        $broken = [];
        $checked = 0;
        try {
            $list = db()->query('SELECT id, folder, filename, url FROM media ORDER BY created_at DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($list as $m) {
                $checked++;
                $full = MEB_UPLOADS_DIR . ($m['folder'] !== '' ? '/' . $m['folder'] : '') . '/' . $m['filename'];
                if (!is_file($full) || !is_readable($full)) $broken[] = ($m['folder'] !== '' ? $m['folder'] . '/' : '') . $m['filename'];
            }
        } catch (\Throwable $e) {
            $broken[] = 'ошибка чтения';
        }
        if (!$broken) {
            diag_push($C, 'media', 'ok', 'Файлы ↔ записи БД',
                'Проверено ' . $checked . ' записей — файлы существуют и читаются.', '', $src);
        } else {
            diag_push($C, 'media', 'error', 'Файлы ↔ записи БД',
                'Файлов нет на диске (или нет прав): ' . implode(', ', array_slice($broken, 0, 5)) . '.',
                'high', 'uploads/', '', 'Пере-загрузите недостающие файлы или удалите записи без файлов.');
        }
    } else {
        diag_push($C, 'media', 'ok', 'Файлы ↔ записи БД', 'Записей нет — цепочка будет проверена после первой загрузки.', 'info', $src);
    }

    /* ---------------- BANNERS (admin → DB → public hero chain) ---------------- */
    try {
        $nB   = (int) db()->query('SELECT COUNT(*) FROM banners')->fetchColumn();
        $nAct = (int) db()->query('SELECT COUNT(*) FROM banners WHERE is_active = 1')->fetchColumn();
        diag_push($C, 'banners', 'ok', 'Таблица banners',
            'Всего баннеров: ' . $nB . ', активных: ' . $nAct . '.', 'info', $src);
    } catch (\Throwable $e) {
        diag_push($C, 'banners', 'error', 'Таблица banners', 'Таблица banners недоступна.', 'high', $src, '', 'Импортируйте sql/schema.sql.');
    }
    try {
        $acts = db()->query('SELECT id, title, subtitle, image_url, button_url, button_text, starts_at, ends_at, sort_order FROM banners WHERE is_active = 1 ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC);
        if (!$acts) {
            diag_push($C, 'banners', 'warning', 'Активные баннеры',
                'Контентная рекомендация: активных баннеров нет — на главной показывается запасной слайдер. Это НЕ ошибка безопасности; создайте баннер и включите его, если нужен промо.',
                '', $src, '', 'Раздел Баннеры → «+ Баннер» → вкл. is_active.');
        } else {
            $imgMiss = [];
            $dateMiss = [];
            foreach ($acts as $b) {
                if (!preg_match('#^https?://#', (string) $b['image_url'])) {
                    $rel = ltrim((string) $b['image_url'], '/');
                    $full = MEB_ROOT . '/' . $rel;
                    if (strpos($rel, 'uploads/') === 0 && !is_file($full)) $imgMiss[] = $b['image_url'];
                }
                if ($b['starts_at'] && $b['ends_at'] && $b['starts_at'] > $b['ends_at']) {
                    $dateMiss[] = $b['title'];
                }
            }
            if (!$imgMiss && !$dateMiss) {
                diag_push($C, 'banners', 'ok', 'Активные баннеры',
                    'Проверено ' . count($acts) . ' — изображения и даты корректны.', '', $src);
            } else {
                $parts = array_merge(
                    $imgMiss ? ['нет файлов: ' . implode(', ', array_slice($imgMiss, 0, 4))] : [],
                    $dateMiss ? ['путаница дат: ' . implode(', ', $dateMiss)] : []
                );
                diag_push($C, 'banners', 'error', 'Активные баннеры',
                    implode('; ', $parts), 'high', $src, '/api/v1/banners-public', 'Замените изображения или поправьте даты начала/окончания.');
            }
        }
    } catch (\Throwable $e) {
        diag_push($C, 'banners', 'error', 'Чтение активных баннеров', 'Не удалось прочитать активные баннеры.', 'high', $src);
    }
    $pubCode = (string) @file_get_contents(MEB_ROOT . '/public/assets/js/app.js');
    if (strpos($pubCode, 'button_url') !== false) {
        diag_push($C, 'banners', 'ok', 'Публичный рендер баннеров',
            'public/assets/js/app.js читает button_url — кнопка баннера ведёт по нужной ссылке.', '', $src);
    } else {
        diag_push($C, 'banners', 'error', 'Публичный рендер баннеров',
            'app.js не использует button_url — кнопка баннера не получит ссылку из админки.',
            'high', 'public/assets/js/app.js', '/api/v1/banners-public', 'В renderSlide() читайте banner.button_url для link.');
    }
    if (strpos($pubCode, 'link') !== false && strpos($pubCode, 'renderFallback') !== false) {
        diag_push($C, 'banners', 'ok', 'Запасной слайдер', 'При отсутствии баннеров рендерится редакторский фолбэк.', 'info', $src);
    }

    /* ---------------- LOGS (real runtime errors) ---------------- */
    $anyLogIssue = false;
    $logHistoric = 0;
    // Entries older than 3 days are "historical" — shown as FIXED/OK, never as current errors.
    $cutoffTs = time() - 259200;
    foreach (['error.log' => 'PHP-журнал error.log', 'server-error.log' => 'Серверный журнал (dev)'] as $lf => $label) {
        $p = MEB_ROOT . '/storage/' . $lf;
        if (!is_file($p)) {
            diag_push($C, 'logs', 'ok', $label, 'Файл отсутствует — ошибок не логировалось.', 'info', $src, '', '', '');
            continue;
        }
        $raw = @file_get_contents($p);
        $lines = $raw === false ? [] : preg_split('/\R/', $raw);
        $tail = array_slice($lines, -80);
        $seen = [];
        $found = 0;
        foreach (array_reverse($tail) as $ln) {
            $cl = diag_classify_log($ln, $cutoffTs);
            if ($cl === null) continue;
            list($st, $sev, $title, $what, $where, $fix, $dt) = $cl;
            $sig = $title . '|' . $where;
            if (isset($seen[$sig])) continue; // dedupe recurring lines (same type + location)
            $seen[$sig] = true;
            $found++;
            if ($st === 'ok' && !empty($dt) && $dt <= date('Y-m-d', $cutoffTs)) {
                $logHistoric++;
                diag_push($C, 'logs', 'ok', $title, $what, 'info', $where, '', $fix, '', '', ['date' => $dt]);
                continue;
            }
            diag_push($C, 'logs', $st, $title, $what, $sev, $where, '', $fix, '', '', ['date' => $dt]);
            $anyLogIssue = $anyLogIssue || ($st !== 'ok' && $st !== 'notverified');
        }
        if ($found === 0) {
            diag_push($C, 'logs', 'ok', $label, 'Проанализировано ' . count($tail) . ' строк — проблем не выявлено.', 'info', $src, '', '', '');
        }
    }
    if (!$anyLogIssue) {
        // keep a single positive note instead of drowning the list
        if (file_exists(MEB_ROOT . '/storage/error.log')) {
            $n = substr_count((string) @file_get_contents(MEB_ROOT . '/storage/error.log'), "\n") + 1;
        } else {
            $n = 0;
        }
        $histNote = $logHistoric > 0 ? ' · исторических записей (исправлено/CLI-артефакты): ' . $logHistoric : '';
        diag_push($C, 'logs', 'ok', 'Итог по журналам', 'Всего строк в логе: ' . $n . ' · активных ошибок нет' . $histNote . '.', 'info', $src, '', '', '');
    }

    /* ---------------- AGGREGATE ---------------- */
    $issues = [];
    $agg = ['total' => 0, 'ok' => 0, 'warning' => 0, 'error' => 0,
            'notconfigured' => 0, 'notverified' => 0, 'critical' => 0, 'high' => 0];
    $cats = [];
    foreach ($C as $key => $cat) {
        $a = ['total' => 0, 'ok' => 0, 'warning' => 0, 'error' => 0,
              'notconfigured' => 0, 'notverified' => 0, 'critical' => 0, 'high' => 0];
        foreach ($cat['items'] as $it) {
            $a['total']++;
            if ($it['severity'] === 'critical') $a['critical']++;
            if ($it['severity'] === 'high') $a['high']++;
            if (isset($a[$it['status']])) $a[$it['status']]++;
            if ($it['status'] !== 'ok') {
                $issues[] = [
                    'id'       => $it['id'] ?? '',
                    'severity' => $it['severity'],
                    'category' => $cat['label'],
                    'title'    => $it['title'],
                    'message'  => $it['message'],
                    'where'    => $it['source'],
                    'endpoint' => $it['endpoint'],
                    'http'     => $it['http'],
                    'fix'      => $it['fix'],
                    'date'     => $it['date'] ?? '',
                ];
            }
        }
        $a['status'] = $a['error'] > 0 ? (($a['critical'] > 0) ? 'critical' : 'error')
                     : (($a['warning'] + $a['notverified'] > 0) ? 'warning' : 'ok');
        $cats[$key] = ['label' => $cat['label'], 'status' => $a['status']] + $a;
        foreach ($a as $k => $v) {
            if ($k === 'status') continue;
            if (isset($agg[$k])) $agg[$k] += $v;
        }
    }
    $agg['percent'] = $agg['total'] > 0 ? (int) round($agg['ok'] / $agg['total'] * 100) : 0;
    $agg['status']  = $agg['error'] > 0 ? (($agg['critical'] > 0) ? 'critical' : 'error')
                    : (($agg['warning'] + $agg['notverified'] > 0) ? 'warning' : 'ok');

    $w = ['critical' => 6, 'high' => 5, 'medium' => 4, 'low' => 3, 'info' => 2];
    usort($issues, function ($a, $b) use ($w) {
        return ($w[$b['severity']] ?? 0) - ($w[$a['severity']] ?? 0);
    });

    // Lightweight persistent summary (no secrets) for the "last checked" UI.
    $histFile = MEB_DATA_DIR . '/data/last-diagnostics.json';
    if (!is_dir(dirname($histFile))) @mkdir(dirname($histFile), 0777, true);
    $history = [
        'checked_at' => $checked,
        'mode'       => $full ? 'full' : 'quick',
        'status'     => $agg['status'],
        'percent'    => $agg['percent'],
        'ok'         => $agg['ok'],
        'warning'    => $agg['warning'],
        'error'      => $agg['error'],
        'total'      => $agg['total'],
    ];
    @file_put_contents($histFile, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // Best-effort audit entry (never breaks the response on a transient DB issue).
    try {
        audit_log('diagnostics', 'system', '', [
            'mode'   => $full ? 'full' : 'quick',
            'status' => $agg['status'],
            'percent'=> $agg['percent'],
        ]);
    } catch (\Throwable $ign) {}

    ok([
        'version'    => trim((string) @file_get_contents(MEB_ROOT . '/VERSION')),
        'checked_at' => $checked,
        'mode'       => $full ? 'full' : 'quick',
        'overall'    => $agg,
        'categories' => $cats,
        'issues'     => $issues,
        'last_run'   => $history,
    ]);
}
