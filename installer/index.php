<?php
/**
 * MEB — PHP/MySQL installer for shared hosting (Sweb).
 *
 * Pure-PHP, 7-step wizard. Runs directly under Apache + PHP + MySQL.
 * No Node.js, no npm, no separate API, no fetch to an unreachable endpoint,
 * no localhost/port dependency, no proxy required.
 *
 *   POST /installer/  (and  /installer/index.php)  — ordinary PHP POST.
 *
 * Step 1 performs the environment checks IN PHP and renders the real result
 * in HTML, so the page works even if /api/v1/* is unavailable.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/router.php';  // config, db, helpers, security
require_once dirname(__DIR__) . '/api/v1/install.php';   // handle_install_do, exec_schema, write_install_config, dir_rw

security_headers();
header('Content-Type: text/html; charset=utf-8');

// Form/links post back to the installer directory. On shared hosting this file
// is served directly by Apache, so no API routing is involved.
define('INST_ACTION', '/installer/');

/* ------------------------------------------------------------------ */
/* Reusable helpers (installer-local; no dependence on the API handler) */
/* ------------------------------------------------------------------ */

/** Safe MySQL identifier (host/db name for DSN). */
function inst_safe_ident(string $s): string
{
    return preg_replace('/[^A-Za-z0-9._-]/', '', $s);
}

/** Build a connect-only PDO (no dbname) for CREATE DATABASE attempts. */
function inst_server_pdo(string $host, int $port, string $user, string $pass): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
}

/** Connect to a specific database, or null on failure (error message returned by ref). */
function inst_db_pdo(string $host, int $port, string $name, string $user, string $pass, ?string &$err): ?PDO
{
    $err = null;
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, inst_safe_ident($name)),
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $pdo->query('SELECT 1');
        return $pdo;
    } catch (\Throwable $e) {
        $err = $e->getMessage();
        return null;
    }
}

/** Render a check row (green ✓ / red ✕) with an optional detail line. */
function inst_check_row(bool $ok, string $label, string $detail = ''): string
{
    $icon = $ok ? '&#10003;' : '&#10005;';
    $color = $ok ? '#0a7d34' : '#c62828';
    $d = $detail !== '' ? '<div class="inst-detail">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</div>' : '';
    return '<div class="inst-row"><span class="inst-icon" style="color:' . $color . '">' . $icon . '</span>'
         . '<span class="inst-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>' . $d . '</div>';
}

/* ------------------------------------------------------------------ */
/* Step 1 — real environment checks, computed & rendered here in PHP.   */
/* ------------------------------------------------------------------ */

/* PHP requirements: only what the PHP/MySQL app genuinely needs.
 * Min version 7.1 — verified: the app uses no features added in 7.2+,
 * only nullable types (?Type) and void returns (PHP 7.1 OK). */
$phpMin = '7.1';
$phpCompatible = version_compare(PHP_VERSION, $phpMin, '>=');
$phpChecks = [
    'PHP ' . PHP_VERSION . ' (7.1+)' => $phpCompatible,
    'PDO'                            => extension_loaded('pdo'),
    'PDO MySQL'                      => extension_loaded('pdo_mysql'),
    'mbstring'                       => extension_loaded('mbstring'),
    'JSON'                           => function_exists('json_encode') && function_exists('json_decode'),
    'password_hash / password_verify'=> function_exists('password_hash') && function_exists('password_verify'),
];
$phpFailNotes = [
    'PHP ' . PHP_VERSION . ' (7.1+)' => 'требуется PHP 7.1+, обнаружено ' . PHP_VERSION,
    'PDO'                            => 'расширение pdo не загружено',
    'PDO MySQL'                      => 'расширение pdo_mysql не загружено — включите его в панели хостинга',
    'mbstring'                       => 'расширение mbstring не загружено — включите его в панели хостинга',
    'JSON'                           => 'функции JSON недоступны',
    'password_hash / password_verify'=> 'функции password_hash/password_verify недоступны',
];
$phpOk = true;
$phpMissing = [];
foreach ($phpChecks as $label => $ok) {
    if (!$ok) { $phpOk = false; $phpMissing[] = $phpFailNotes[$label]; }
}
$phpDetail = $phpOk
    ? 'PHP ' . PHP_VERSION . ' — совместимо'
    : 'требуется: ' . implode('; ', $phpMissing);

/* Storage: only the directories the app really writes to. dir_rw auto-creates
 * missing dirs and verifies real write access with a probe file (shared-hosting
 * friendly: does not demand 777 for the whole tree). */
$dirs = ['config' => MEB_CONFIG_DIR, 'storage' => MEB_ROOT . '/storage', 'uploads' => MEB_ROOT . '/uploads'];
$dirWritable = true;
$dirDetail = '';
foreach ($dirs as $label => $d) {
    if (!dir_rw($d)) {
        $dirWritable = false;
        $dirDetail .= '[ ' . $label . ': ' . ($d) . ' ] ';
    }
}
$storageDetail = $dirWritable
    ? 'config/, storage/, uploads/ доступны для записи'
    : 'Нет записи в: ' . trim($dirDetail);
if (!$dirWritable) $storageDetail .= ' — проверьте права (755/775/777) именно на эти каталоги';

$pdoOk   = extension_loaded('pdo_mysql');
$pdoDetail = $pdoOk
    ? 'расширение pdo_mysql загружено'
    : 'расширение pdo_mysql не загружено — включите его в панели хостинга';

$requirementsOk = $phpOk && $dirWritable && $pdoOk;

/* ------------------------------------------------------------------ */
/* Step 2 — MySQL connection test (real PDO, no API).                  */
/* ------------------------------------------------------------------ */

$mysqlErr = null;
$mysqlOk  = false;
$mysqlTested = false;

$step   = (string) ($_POST['step'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$inHost  = trim((string) ($_POST['db_host'] ?? 'localhost'));
$inPort  = (int) ($_POST['db_port'] ?? 3306);
$inName  = trim((string) ($_POST['db_name'] ?? ''));
$inUser  = trim((string) ($_POST['db_user'] ?? ''));
$inPass  = (string) ($_POST['db_pass'] ?? '');

if ($isPost && $step === '2') {
    $mysqlTested = true;
    $pdo = inst_db_pdo($inHost, $inPort, $inName, $inUser, $inPass, $mysqlErr);
    if ($pdo !== null) {
        $mysqlOk = true;
        $mysqlErr = null;
    } else {
        // Connection failed. Try to create the database (tolerant of shared
        // hosting where the user may NOT have CREATE DATABASE privilege).
        try {
            $server = inst_server_pdo($inHost, $inPort, $inUser, $inPass);
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . inst_safe_ident($inName) .
                          '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo2 = inst_db_pdo($inHost, $inPort, $inName, $inUser, $inPass, $mysqlErr);
            if ($pdo2 !== null) {
                $mysqlOk = true;
                $mysqlErr = null;
            }
        } catch (\Throwable $e) {
            // keep the original connection message
        }
    }
}

// Carried-forward values from earlier steps (hidden round-trip via plain POST).
$inEmail    = strtolower(trim((string) ($_POST['email'] ?? '')));
$inAdmin    = trim((string) ($_POST['name'] ?? 'Admin'));
$inPassA    = (string) ($_POST['password'] ?? '');
$inSiteName = trim((string) ($_POST['siteName'] ?? ''));
$inSiteUrl  = trim((string) ($_POST['siteUrl'] ?? ''));
$inPhone    = trim((string) ($_POST['phone'] ?? ''));
$inDemo     = !empty($_POST['demo']) || !empty($_POST['demo_toggle']);

/* ------------------------------------------------------------------ */
/* Step 6 — perform the actual installation.                           */
/* ------------------------------------------------------------------ */

$installErrors = [];
$installOk     = false;
$installRun    = false;
$installLog    = [];

if ($isPost && $step === '6') {
    $installRun = true;

    if ($inName === '' || $inUser === '') {
        $installErrors[] = 'Не заполнены параметры базы данных. Вернитесь на шаг 2.';
    }
    if ($inEmail === '' || strlen($inPassA) < 6) {
        $installErrors[] = 'Укажите email и пароль администратора (мин. 6 символов).';
    }
    if ($inSiteName === '') {
        $inSiteName = 'MEB';
    }

    if (!$installErrors) {
        try {
            // 1) Real DB connection (with CREATE DATABASE fallback for hosting
            //    where the DB is created in the panel and the grant lacks
            //    CREATE DATABASE).
            $pdo = inst_db_pdo($inHost, $inPort, $inName, $inUser, $inPass, $mysqlErr);
            if ($pdo === null) {
                try {
                    $server = inst_server_pdo($inHost, $inPort, $inUser, $inPass);
                    $server->exec('CREATE DATABASE IF NOT EXISTS `' . inst_safe_ident($inName) .
                                  '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                } catch (\Throwable $e) { /* may lack privilege; DB must exist */ }
                $pdo = inst_db_pdo($inHost, $inPort, $inName, $inUser, $inPass, $mysqlErr);
            }
            if ($pdo === null) {
                $installErrors[] = 'Не удалось подключиться к MySQL: ' . $mysqlErr
                    . '. Убедитесь, что база "' . htmlspecialchars($inName) . '" создана в панели хостинга.';
                throw new RuntimeException('db');
            }

            // 2) Write config (base_url from user input or auto-detected from
            //    current request host — never hardcoded).
            write_install_config($inHost, $inPort, $inName, $inUser, $inPass, $inSiteUrl);
            $installLog[] = 'config/database.php и config/app.php записаны';

            // 3) Lock must not already exist.
            if (is_file(MEB_LOCK_FILE)) {
                $installErrors[] = 'Установка уже была выполнена ранее (storage/installed.lock). Для повторной установки удалите этот файл.';
                throw new RuntimeException('locked');
            }

            // 4) Create tables + admin + settings + optional demo seed + lock.
            handle_install_do($inEmail, $inPassA, $inAdmin, $inSiteName, $inDemo, $inPhone);
            $installLog[] = 'Схема таблиц создана, администратор создан' . ($inDemo ? ', демо-контент загружен' : '');
            $installLog[] = 'storage/installed.lock создан';

            $installOk = true;
        } catch (\Throwable $e) {
            if ($e->getMessage() !== 'db' && $e->getMessage() !== 'locked') {
                $installErrors[] = 'Ошибка установки: ' . $e->getMessage();
            }
        }
    }

    // If success -> show result step (Step 7).
    // If failure -> show errors back on Step 6 so the user can retry/edit.
}

/* ================================================================== */
/* HTML rendering                                                      */
/* ================================================================== */

function inst_hidden(array $fields): string
{
    $out = '';
    foreach ($fields as $k => $v) {
        $out .= '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '" value="'
              . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '">';
    }
    return $out;
}

$dbFields = ['db_host' => $inHost, 'db_port' => $inPort, 'db_name' => $inName,
             'db_user' => $inUser, 'db_pass' => $inPass];
$adminFields = ['email' => $inEmail, 'name' => $inAdmin, 'password' => $inPassA];
$siteFields  = ['siteName' => $inSiteName, 'siteUrl' => $inSiteUrl, 'phone' => $inPhone];
$demoFields  = ['demo' => $inDemo];

$heads = '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>MEB — Мастер установки</title>'
       . '<style>'
       . 'body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f3f4f6;margin:0;padding:40px 16px}'
       . '.card{max-width:680px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:32px 36px}'
       . '.muted{color:#6b7280}.small{font-size:13px}'
       . 'h1{font-size:24px;margin:0 0 4px}.step{color:#2563eb;font-weight:600;margin:0 0 16px}'
       . '.inst-row{display:flex;align-items:baseline;gap:10px;padding:9px 0;border-bottom:1px solid #eee}'
       . '.inst-icon{font-weight:700}.inst-label{font-weight:600}.inst-detail{color:#6b7280;font-size:13px;margin-left:24px}'
       . 'label{display:block;font-weight:600;margin:14px 0 4px}'
       . 'input[type=text],input[type=password],input[type=email],input[type=number]{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px}'
       . 'input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}'
       . '.btn{display:inline-block;background:#2563eb;color:#fff;border:none;padding:12px 26px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;margin-top:20px}'
       . '.btn:hover{background:#1d4ed8}.btn:disabled{background:#9ca3af;cursor:not-allowed}'
       . '.btn-green{background:#0a7d34}.btn-green:hover{background:#086b2c}'
       . '.alert{border-radius:8px;padding:12px 14px;margin:16px 0;font-size:14px}'
       . '.alert-ok{background:#e7f6ec;color:#0a7d34}.alert-err{background:#fdecea;color:#c62828}'
       . '.log{background:#0f172a;color:#a5f3fc;border-radius:8px;padding:12px 14px;font-family:monospace;font-size:13px;white-space:pre-line}'
       . '.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}'
       . '</style></head><body><div class="card">';

$foot = '</div></body></html>';

/* ---- Step 7 (result) ---- */
if ($installRun && $installOk) {
    $base = MEB_BASE_URL !== '' ? MEB_BASE_URL : 'этот сайт';
    echo $heads;
    echo '<h1>Мастер установки</h1><p class="step">Шаг 7 из 7 — Результат</p>';
    echo '<div class="alert alert-ok"><strong>&#10003; Установка завершена успешно.</strong></div>';
    echo '<div class="log">' . htmlspecialchars(implode("\n", $installLog), ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<p class="muted small">Адрес сайта: ' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a class="btn btn-green" href="' . htmlspecialchars($base . '/admin', ENT_QUOTES, 'UTF-8') . '">Войти в панель управления</a>';
    echo $foot;
    exit;
}

/* ---- Step 6 (install / errors) ---- */
if ($installRun && !$installOk) {
    echo $heads;
    echo '<h1>Мастер установки</h1><p class="step">Шаг 6 из 7 — Установка</p>';
    if ($installErrors) {
        echo '<div class="alert alert-err"><strong>Не удалось завершить установку:</strong></div>';
        foreach ($installErrors as $e) {
            echo '<div class="alert alert-err">' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
    echo '<form method="post" action="' . htmlspecialchars((defined('INST_ACTION') ? INST_ACTION : '/installer/'), ENT_QUOTES, 'UTF-8') . '">';
    echo inst_hidden($dbFields) . inst_hidden($adminFields) . inst_hidden($siteFields) . inst_hidden($demoFields);
    echo inst_hidden(['step' => '6']);
    echo '<button class="btn" type="submit">Повторить установку</button>';
    echo '<a class="btn" style="background:#6b7280;margin-left:10px" href="' . htmlspecialchars((defined('INST_ACTION') ? INST_ACTION : '/installer/'), ENT_QUOTES, 'UTF-8') . '">К началу</a>';
    echo '</form>';
    echo $foot;
    exit;
}

/* ---- Step 2 (MySQL form + test result) ---- */
if ($isPost && $step === '2' && $mysqlOk) {
    echo $heads;
    echo '<h1>Мастер установки</h1><p class="step">Шаг 2 из 7 — База данных MySQL</p>';
    echo '<div class="alert alert-ok"><strong>&#10003; Подключение к MySQL успешно.</strong> База «'
       . htmlspecialchars($inName, ENT_QUOTES, 'UTF-8') . '» доступна.</div>';
    echo '<form method="post" action="' . htmlspecialchars((defined('INST_ACTION') ? INST_ACTION : '/installer/'), ENT_QUOTES, 'UTF-8') . '">';
    echo inst_hidden($dbFields);
    echo inst_hidden(['step' => '3']);
    echo '<label>Email администратора</label><input type="email" name="email" value="' . htmlspecialchars($inEmail, ENT_QUOTES, 'UTF-8') . '" required>';
    echo '<label>Имя администратора</label><input type="text" name="name" value="' . htmlspecialchars($inAdmin, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label>Пароль администратора (мин. 6 символов)</label><input type="password" name="password" autocomplete="new-password" required>';
    echo '<button class="btn" type="submit">Далее — шаг 3</button>';
    echo '</form>';
    echo $foot;
    exit;
}

if (($isPost && $step === '2' && !$mysqlOk) || ($isPost && $step === '2' && !$mysqlTested)) {
    // Only reached when step=2 but the test failed (form re-shown with errors).
}

/* ---- Step 3 (admin) — reached from MySQL summary form ---- */
if ($isPost && $step === '3') {
    echo $heads;
    echo '<h1>Мастер установки</h1><p class="step">Шаг 3 из 7 — Администратор</p>';
    echo '<div class="alert alert-ok"><strong>&#10003; Администратор.</strong> Учётная запись суперпользователя.</div>';
    echo '<form method="post" action="' . htmlspecialchars((defined('INST_ACTION') ? INST_ACTION : '/installer/'), ENT_QUOTES, 'UTF-8') . '">';
    echo inst_hidden($dbFields) . inst_hidden($adminFields);
    echo inst_hidden(['step' => '4']);
    echo '<label>Название сайта</label><input type="text" name="siteName" value="' . htmlspecialchars($inSiteName, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label>Адрес сайта (URL)</label><input type="text" name="siteUrl" value="' . htmlspecialchars($inSiteUrl !== '' ? $inSiteUrl : (('http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? ''))), ENT_QUOTES, 'UTF-8') . '" placeholder="https://example.com">';
    echo '<label>Телефон (необязательно)</label><input type="text" name="phone" value="' . htmlspecialchars($inPhone, ENT_QUOTES, 'UTF-8') . '">';
    echo '<button class="btn" type="submit">Далее — шаг 4</button>';
    echo '</form>';
    echo $foot;
    exit;
}

/* ---- Step 4 (site) ---- */
if ($isPost && $step === '4') {
    echo $heads;
    echo '<h1>Мастер установки</h1><p class="step">Шаг 4 из 7 — Настройка сайта</p>';
    echo '<div class="alert alert-ok"><strong>&#10003; Настройки сайта.</strong> Можно изменить ниже.</div>';
    echo '<form method="post" action="' . htmlspecialchars((defined('INST_ACTION') ? INST_ACTION : '/installer/'), ENT_QUOTES, 'UTF-8') . '">';
    echo inst_hidden($dbFields) . inst_hidden($adminFields) . inst_hidden($siteFields);
    echo inst_hidden(['step' => '5']);
    echo '<label>Название сайта</label><input type="text" name="siteName" value="' . htmlspecialchars($inSiteName, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label>Адрес сайта (URL)</label><input type="text" name="siteUrl" value="' . htmlspecialchars($inSiteUrl, ENT_QUOTES, 'UTF-8') . '" placeholder="https://example.com">';
    echo '<label>Телефон (необязательно)</label><input type="text" name="phone" value="' . htmlspecialchars($inPhone, ENT_QUOTES, 'UTF-8') . '">';
    echo '<button class="btn" type="submit">Далее — шаг 5</button>';
    echo '</form>';
    echo $foot;
    exit;
}

/* ---- Step 5 (demo) ---- */
if ($isPost && $step === '5') {
    echo $heads;
    echo '<h1>Мастер установки</h1><p class="step">Шаг 5 из 7 — Демо-контент</p>';
    echo '<div class="alert alert-ok"><strong>&#10003; Демо-контент.</strong> Заполнить сайт демо-данными при установке.</div>';
    echo '<form method="post" action="' . htmlspecialchars((defined('INST_ACTION') ? INST_ACTION : '/installer/'), ENT_QUOTES, 'UTF-8') . '">';
    echo inst_hidden($dbFields) . inst_hidden($adminFields) . inst_hidden($siteFields);
    echo '<input type="hidden" name="demo" value="' . ($inDemo ? '1' : '') . '">';
    echo '<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="demo_toggle" value="1" ' . ($inDemo ? 'checked' : '') . '> Загрузить демо-данные (рекомендуется для первого запуска)</label>';
    echo inst_hidden(['step' => '6']);
    echo '<button class="btn" type="submit">Установить</button>';
    echo '</form>';
    echo $foot;
    exit;
}

/* ---- Step 6 confirmation not reached here (handled above) ---- */

/* ---- Step 1 (default) + Step 2 failed re-display ---- */
$isStep2Redisplay = ($isPost && $step === '2');

echo $heads;
echo '<h1>Мастер установки</h1>';
echo '<p class="step">' . ($isStep2Redisplay ? 'Шаг 2 из 7 — База данных MySQL' : 'Шаг 1 из 7 — Проверка системы') . '</p>';

if (!$isStep2Redisplay) {
    echo '<p class="muted">Убедимся, что сервер готов к установке.</p>';
    echo inst_check_row($phpOk, 'PHP — ' . ($phpOk ? 'требования выполнены' : 'ошибка'), $phpDetail);
    echo inst_check_row($dirWritable, 'Хранилище — ' . ($dirWritable ? 'доступно для записи' : 'ошибка'), $storageDetail);
    echo inst_check_row($pdoOk, 'MySQL / PDO — ' . ($pdoOk ? 'доступно' : 'ошибка'), $pdoDetail);

    echo '<form method="post" action="' . htmlspecialchars((defined('INST_ACTION') ? INST_ACTION : '/installer/'), ENT_QUOTES, 'UTF-8') . '">';
    if ($requirementsOk) {
        echo inst_hidden(['step' => '2']);
        echo '<button class="btn" type="submit">Далее</button>';
    } else {
        echo inst_hidden(['step' => '1']);
        echo '<div class="alert alert-err" style="margin-top:16px">Некоторые требования не выполнены. Устраните их на хостинге и нажмите «Проверить снова».</div>';
        echo '<button class="btn" type="submit">Проверить снова</button>';
    }
    echo '</form>';
} else {
    // Step 2 re-display after a failed MySQL test.
    echo '<p class="muted">Укажите данные базы данных из панели хостинга.</p>';
    if ($mysqlTested && !$mysqlOk) {
        $reason = (string) $mysqlErr;
        // Never echo the password. Strip any password-like value defensively.
        $reason = str_replace($inPass, '***', $reason);
        echo '<div class="alert alert-err"><strong>&#10005; Не удалось подключиться к MySQL.</strong><br><span class="small">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</span></div>';
    }
    echo '<form method="post" action="' . htmlspecialchars((defined('INST_ACTION') ? INST_ACTION : '/installer/'), ENT_QUOTES, 'UTF-8') . '">';
    echo inst_hidden(['step' => '2']);
    echo '<label>Хост</label><input type="text" name="db_host" value="' . htmlspecialchars($inHost, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label>Порт</label><input type="number" name="db_port" value="' . (int)$inPort . '">';
    echo '<label>Имя базы</label><input type="text" name="db_name" value="' . htmlspecialchars($inName, ENT_QUOTES, 'UTF-8') . '" required>';
    echo '<label>Пользователь</label><input type="text" name="db_user" value="' . htmlspecialchars($inUser, ENT_QUOTES, 'UTF-8') . '" required>';
    echo '<label>Пароль</label><input type="password" name="db_pass" autocomplete="off">';
    echo '<div class="grid2">';
    echo '<button class="btn" type="submit" name="act" value="test">Проверить подключение</button>';
    echo '<button class="btn btn-green" type="submit" name="act" value="next">Далее</button>';
    echo '</div>';
    echo '</form>';
}

echo $foot;
