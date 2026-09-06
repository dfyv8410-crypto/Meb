<?php
/**
 * Health subsystem tests — exercises the exact probes used by GET /api/v1/health.
 * Run: php scripts/health-test.php
 * Requires a configured DB (installed site) and PHP >= 7.1.
 */

declare(strict_types=1);

define("MEB_ROOT", dirname(__DIR__));
define("MEB_CONFIG_DIR", MEB_ROOT . "/config");
define("MEB_LOCK_FILE", MEB_ROOT . "/storage/installed.lock");
define("MEB_DATA_DIR", MEB_ROOT . "/storage");
define("MEB_UPLOADS_DIR", MEB_ROOT . "/uploads");
require_once MEB_ROOT . "/includes/router.php";
require_once MEB_ROOT . "/api/v1/system.php";

$pass = 0; $fail = 0;
function ck(string $d, bool $ok) { global $pass, $fail; if ($ok) { $pass++; echo "  PASS  $d\n"; } else { $fail++; echo "  FAIL  $d\n"; } }
function probe_residue(): array { return glob(MEB_UPLOADS_DIR . '/.health-probe-*') ?: []; }

echo "=== HEALTH SUBSYSTEM TESTS ===\n\n";

// ---- Database ----
echo "[Database]\n";
try {
    $one = db()->query('SELECT 1')->fetchColumn();
    ck("DB connection + SELECT 1 returns 1", (int)$one === 1);
} catch (\Throwable $e) {
    ck("DB connection + SELECT 1 returns 1", false);
}

// ---- Storage (critical: uploads) ----
echo "\n[Storage]\n";
ck("uploads dir exists", is_dir(MEB_UPLOADS_DIR));
ck("uploads real write/read/delete probe", health_dir_probe(MEB_UPLOADS_DIR));

// ---- Cache (optional: file cache dir) ----
echo "\n[Cache]\n";
if (is_dir(MEB_DATA_DIR . '/cache')) {
    ck("cache dir real write/read/delete probe", health_dir_probe(MEB_DATA_DIR . '/cache'));
} else {
    ck("cache dir present (optional)", false);
}

// ---- Backup ----
echo "\n[Backup]\n";
$bkTable = false;
try { db()->query('SELECT COUNT(*) FROM backups')->fetchColumn(); $bkTable = true; } catch (\Throwable $e) { $bkTable = false; }
ck("backups table accessible", $bkTable);
if (is_dir(MEB_UPLOADS_DIR . '/backups')) {
    ck("backups dir real write/read/delete probe", health_dir_probe(MEB_UPLOADS_DIR . '/backups'));
}

// ---- Health must not leave residue ----
echo "\n[No residue]\n";
ck("no .health-probe-* files left in uploads", count(probe_residue()) === 0);
ck("no .health-probe-* files left in cache", count(glob(MEB_DATA_DIR . '/cache/.health-probe-*') ?: []) === 0);

// ---- Security: probe refuses non-existent dirs ----
echo "\n[Security]\n";
ck("probe refuses non-existent dir", health_dir_probe(MEB_DATA_DIR . '/no-such-dir-' . bin2hex(random_bytes(4))) === false);

// ---- Schema: all expected tables present ----
echo "\n[Schema]\n";
$expected = ['users','sessions','settings','catalog_categories','catalog','projects','materials','services',
             'reviews','requests','media','pages','notifications','analytics','audit_log','backups',
             'migrations','rate_limits','menu_items','banners'];
$have = [];
try {
    $rows = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    foreach ($rows as $r) $have[] = $r[0];
} catch (\Throwable $e) {}
$missing = array_values(array_diff($expected, $have));
ck("all " . count($expected) . " tables present", count($missing) === 0);
if ($missing) echo "       missing: " . implode(', ', $missing) . "\n";

// ---- Real CRUD probe (transaction, no residue) ----
echo "\n[CRUD probe]\n";
$crudOk = false;
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
    $crudOk = true;
} catch (\Throwable $e) {
    try { if (db()->inTransaction()) db()->rollBack(); } catch (\Throwable $ig) {}
}
ck("transactional C/R/U/D on scratch row", $crudOk);
try {
    $left = db()->query('SELECT COUNT(*) FROM rate_limits WHERE bucket LIKE \'diag-%\'')->fetchColumn();
    ck("no diag-* rows left behind", (int)$left === 0);
} catch (\Throwable $e) { ck("no diag-* rows left behind", false); }

// ---- .htaccess security rules (must block sensitive dirs) ----
echo "\n[.htaccess]\n";
$ht = @file_get_contents(MEB_ROOT . '/.htaccess');
ck("root .htaccess present", $ht !== false);
ck("config/ blocked", is_string($ht) && preg_match('#RewriteRule \^\(config\)/#', $ht));
ck("includes/ blocked", is_string($ht) && preg_match('#RewriteRule \^\(includes\)/#', $ht));
ck("sql/ blocked", is_string($ht) && preg_match('#RewriteRule \^\(sql\)/#', $ht));
ck("storage blocked (data|backups|all of storage/)", is_string($ht) && preg_match('#RewriteRule \^storage/#', $ht));
ck("uploads/backups blocked (public leak fix)", is_string($ht) && preg_match('#uploads/backups#', $ht));
ck("uploads/backups/.htaccess present (defense in depth)", is_file(MEB_UPLOADS_DIR . '/backups/.htaccess'));

// ---- Project structure: normal root layout, NO sweb/ layer ----
// The app must live directly in the hosting document root (index.php, .htaccess,
// admin/, api/, config/, installer/, storage/, ... at the top level), not under
// any intermediate sweb/ folder. This invariant is enforced here so a sweb/
// layer can never silently come back.
echo "\n[Structure (root, no sweb layer)]\n";
$requiredTop = ['index.php', '.htaccess', 'router.php', 'admin', 'api', 'config', 'includes', 'installer', 'locales', 'pages', 'public', 'scripts', 'sql', 'storage', 'uploads'];
$missingTop = [];
foreach ($requiredTop as $e) {
    if (!file_exists(MEB_ROOT . '/' . $e)) $missingTop[] = $e;
}
ck("root layout: " . count($requiredTop) . " required entries directly in root", count($missingTop) === 0);
if ($missingTop) echo "       missing: " . implode(', ', $missingTop) . "\n";
ck("no sweb/ file or dir at root", !is_dir(MEB_ROOT . '/sweb') && !is_file(MEB_ROOT . '/sweb'));
$swebDirs = [];
$sit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MEB_ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($sit as $sf) {
    $rel = str_replace('\\', '/', $sf->getPathname());
    if (strpos($rel, '/.git/') !== false || substr($rel, -5) === '/.git') continue;
    if ($sf->isDir() && $sf->getFilename() === 'sweb') $swebDirs[] = $rel;
}
ck("no nested sweb/ directories", count($swebDirs) === 0);
if ($swebDirs) echo "       found: " . implode(', ', $swebDirs) . "\n";
// No sweb path references in any code/asset file (php/js/html/css/.htaccess).
// "Sweb.ru" in comments/hostnames is fine — only real path references count:
// sweb must be bounded by path delimiters (/ \ or start) on the LEFT and by
// a delimiter or end on the RIGHT ("PHP/Sweb port" is a word, not a path).
$scanExts = ['php', 'js', 'html', 'css', 'htaccess'];
$selfFile = str_replace('\\', '/', MEB_ROOT . '/scripts/health-test.php');
$swebRefs = [];
$rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MEB_ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rit as $rf) {
    $rel = str_replace('\\', '/', $rf->getPathname());
    if (strpos($rel, '/.git/') !== false || $rel === $selfFile) continue;
    if (strpos($rel, '/storage/') !== false || strpos($rel, '/uploads/') !== false || strpos($rel, '/locales/') !== false) continue;
    $dot = strrpos($rel, '.');
    $ext = $dot === false ? '' : strtolower(substr($rel, $dot + 1));
    if (!in_array($ext, $scanExts, true)) continue;
    $s = @file_get_contents($rel);
    if ($s === false) continue;
    if (preg_match('#(^|[\\\\/])sweb(?![a-z])(?![.]ru)([\\\\/.]|$)#i', $s)) $swebRefs[] = $rel;
}
ck("no sweb path references in code (" . count($swebRefs) . " hits)", count($swebRefs) === 0);
if ($swebRefs) echo "       in: " . implode(', ', array_slice($swebRefs, 0, 10)) . "\n";
// base_url must never carry a /sweb path segment (runtime config).
$appTxt = @file_get_contents(MEB_CONFIG_DIR . '/app.php');
$baseUrl = '';
if ($appTxt !== false && preg_match("#['\"]base_url['\"]\s*=>\s*['\"]([^'\"]*)['\"]#", $appTxt, $m)) $baseUrl = $m[1];
ck("configured base_url has no /sweb segment", strpos(strtolower($baseUrl), '/sweb') === false);

// ---- Media chain (upload whitelist incl. ico + files on disk) ----
echo "\n[Media chain]\n";
$mediaCode = @file_get_contents(MEB_ROOT . '/api/v1/media.php');
$mediaOk = is_string($mediaCode);
foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'ico'] as $ext) {
    if ($mediaOk && strpos($mediaCode, "'" . $ext . "'") === false) $mediaOk = false;
}
ck("media upload whitelist covers all ext + ico (favicon fix)", $mediaOk);
$mediaDir = MEB_UPLOADS_DIR . '/media';
ck("uploads/media dir exists", is_dir($mediaDir));
$brokenMedia = [];
try {
    $rows = db()->query('SELECT folder, filename FROM media LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $m) {
        $mfolder = (string) ($m['folder'] ?? '');
        $full = MEB_UPLOADS_DIR . ($mfolder !== '' ? '/' . $mfolder : '') . '/' . $m['filename'];
        if (!is_file($full)) $brokenMedia[] = ($mfolder !== '' ? $mfolder . '/' : '') . $m['filename'];
    }
    ck("media DB rows exist on disk (" . count($rows) . " checked)", count($brokenMedia) === 0);
} catch (\Throwable $e) {
    ck("media DB rows exist on disk", false);
}
if ($brokenMedia) echo "       missing on disk: " . implode(', ', array_slice($brokenMedia, 0, 5)) . "\n";

// ---- Branding chain (logo / favicon) ----
echo "\n[Branding (logo/favicon)]\n";
$layout = @file_get_contents(MEB_ROOT . '/pages/layout.php');
ck("layout.php renders logo from settings", is_string($layout) && strpos($layout, "\$s['logo']") !== false);
ck("layout.php renders favicon from settings", is_string($layout) && strpos($layout, "\$s['favicon']") !== false);
$idx = @file_get_contents(MEB_ROOT . '/index.php');
ck("root index serves .ico static (favicon whitelist)", is_string($idx) && strpos($idx, "'ico'") !== false);
$cfg = [];
try { $cfg = get_settings(); } catch (\Throwable $ig) { $cfg = []; }
foreach (['logo', 'favicon'] as $k) {
    $v = trim((string) ($cfg[$k] ?? ''));
    if ($v === '') { ck("settings[" . $k . "] unset (default brand used)", true); continue; }
    $rel = preg_replace('#^https?://[^/]+#', '', $v);
    ck("settings[" . $k . "] file exists on disk", is_file(MEB_ROOT . '/' . ltrim($rel, '/')));
}

// ---- Banner chain ----
echo "\n[Banner chain]\n";
try { db()->query('SELECT COUNT(*) FROM banners')->fetchColumn(); ck("banners table accessible", true); }
catch (\Throwable $e) { ck("banners table accessible", false); }
$pubJs = @file_get_contents(MEB_ROOT . '/public/assets/js/app.js');
ck("public hero reads button_url (link fix)", is_string($pubJs) && strpos($pubJs, 'button_url') !== false);
$admJs = @file_get_contents(MEB_ROOT . '/admin/app.js');
ck("admin form heading -> title (binding fix)", is_string($admJs) && strpos($admJs, "title: el('f_banner_heading')") !== false);
ck("admin form subheading -> subtitle (binding fix)", is_string($admJs) && strpos($admJs, "subtitle: el('f_banner_subheading')") !== false);
ck("admin datetime save T->space (input[type=datetime-local] fix)", is_string($admJs) && strpos($admJs, ".replace('T', ' ')") !== false);
$activeBroken = [];
try {
    $acts = db()->query('SELECT title, image_url, starts_at, ends_at FROM banners WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($acts as $b) {
        if ($b['image_url'] && !preg_match('#^https?://#', $b['image_url'])) {
            $path = parse_url($b['image_url'], PHP_URL_PATH) ?: '';
            $rel = preg_replace('#^/uploads/#', '', $path);
            if (strpos($path, '/uploads') === 0) {
                $exists = is_file(MEB_UPLOADS_DIR . '/' . $rel);
            } else {
                $exists = is_file(MEB_ROOT . '/public' . $path);
            }
            if (!$exists) $activeBroken[] = $b['title'];
        }
        if ($b['starts_at'] && $b['ends_at'] && $b['starts_at'] > $b['ends_at']) $activeBroken[] = $b['title'] . ' (даты)';
    }
    ck("active banners images exist + dates sane (" . count($acts) . " active)", count($activeBroken) === 0);
} catch (\Throwable $e) {
    ck("active banners images exist + dates sane", false);
}
if ($activeBroken) echo "       problem banners: " . implode(', ', array_slice($activeBroken, 0, 5)) . "\n";

// ---- PHP 7.1 compat + no-prod-secrets regression (deliverable sources) ----
echo "\n[PHP 7.1 compat & secrets]\n";
$scanRoots = [MEB_ROOT . '/api', MEB_ROOT . '/includes', MEB_ROOT . '/pages', MEB_ROOT . '/scripts', MEB_ROOT . '/admin', MEB_ROOT . '/public'];
$phpFiles = [MEB_ROOT . '/index.php'];
foreach ($scanRoots as $root) {
    if (!is_dir($root)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isDir()) continue;
        $p = str_replace('\\', '/', $f->getPathname());
        if (substr($p, -4) === '.php') $phpFiles[] = $p;
    }
}
$compatBad = [];
foreach ($phpFiles as $p) {
    if ($p === $selfFile) continue;
    $s = @file_get_contents($p);
    if ($s === false) continue;
    foreach (['fn (', '??=', 'str_contains', 'array_is_list'] as $bp) {
        if (strpos($s, $bp) !== false) $compatBad[] = str_replace(MEB_ROOT . '/', '', $p) . ' uses ' . $bp . ' (PHP7.2+)';
    }
}
ck("no PHP7.2+ only syntax in PHP sources", count($compatBad) === 0);
if ($compatBad) echo "       " . implode("\n       ", array_slice($compatBad, 0, 10)) . "\n";
$secretHits = [];
foreach (array_merge($phpFiles, [MEB_ROOT . '/.htaccess', MEB_ROOT . '/admin/index.html']) as $p) {
    if ($p === $selfFile) continue;
    $s = @file_get_contents($p);
    if ($s === false) continue;
    foreach (['Crfkfcgfcb1', 'dfyv8410', 'dfyz'] as $sec) {
        if (stripos($s, $sec) !== false) $secretHits[] = str_replace(MEB_ROOT . '/', '', $p);
    }
}
ck("no prod credentials in web-facing sources", count($secretHits) === 0);
if ($secretHits) echo "       found in: " . implode(', ', array_slice($secretHits, 0, 10)) . "\n";

echo "\n=== RESULT: " . $pass . " passed, " . $fail . " failed ===\n";
exit($fail > 0 ? 1 : 0);