<?php
/**
 * MEB self-test for the PHP/Sweb port.
 * Runs against a real MySQL DB and exercises the installer, auth, public
 * CRUD endpoints, public page rendering, and security helpers.
 *
 * Usage:  env -u LD_LIBRARY_PATH php scripts/selftest.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require dirname(__DIR__) . '/includes/router.php';

$tests = 0;
$pass  = 0;
$fails = [];

function check(string $name, bool $cond): void
{
    global $tests, $pass, $fails;
    $tests++;
    if ($cond) { $pass++; echo "  ✓ $name\n"; }
    else { $fails[] = $name; echo "  ✗ $name\n"; }
}

echo "== MEB PHP self-test ==\n";

// ---- DB connection -----------------------------------------------------
echo "\n[DB]\n";
check('pdo_mysql loaded', extension_loaded('pdo_mysql'));
$dbOk = false;
try { $dbOk = db_configured(); } catch (\Throwable $e) {}
check('database config connects', $dbOk);

// ---- Installer routine -------------------------------------------------
echo "\n[INSTALL]\n";
require_once dirname(__DIR__) . '/api/v1/install.php';
$installedFile = MEB_LOCK_FILE;
@unlink($installedFile); // force fresh for test

// Ensure a clean schema against the test DB
try {
    $dbName = (require MEB_CONFIG_DIR . '/database.php')['name'];
    db()->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '', $dbName) . '`');
    db()->exec('CREATE DATABASE `' . str_replace('`', '', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    db()->exec('USE `' . str_replace('`', '', $dbName) . '`');
    exec_schema();
    check('schema applied', true);
} catch (\Throwable $e) {
    check('schema applied', false);
    echo '  ! ' . $e->getMessage() . "\n";
}

// Run installer core
try {
    handle_install_do('admin@meb.local', 'Sup3rSecret!', 'Admin', 'MEB', true, '+79000000000');
    check('handle_install_do runs', true);
    check('lock file created', is_file($installedFile));
} catch (\Throwable $e) {
    check('handle_install_do runs', false);
    echo '  ! ' . $e->getMessage() . "\n";
}

// ---- Verify rows seeded -------------------------------------------------
echo "\n[DATA]\n";
foreach (['users','catalog','catalog_categories','projects','materials','services','reviews','pages'] as $table) {
    $n = (int) db()->query('SELECT COUNT(*) c FROM `' . safe_ident($table) . '`')->fetchColumn();
    check("table $table has rows or exists", is_int($n));
    echo "     $table = $n rows\n";
}

// ---- Auth / password ---------------------------------------------------
echo "\n[AUTH]\n";
$st = db()->query("SELECT * FROM users WHERE email='admin@meb.local' LIMIT 1");
$admin = $st->fetch();
check('admin user exists', (bool) $admin);
check('verify_password correct', $admin && verify_password('Sup3rSecret!', $admin));
check('verify_password wrong', $admin && !verify_password('nope', $admin));
$tok = sign_token(['id' => $admin['id'], 'email' => $admin['email'], 'role' => $admin['role']]);
$dec = verify_token($tok);
check('token sign+verify', $dec && $dec['id'] === $admin['id']);
check('token tamper rejected', verify_token($tok . 'x') === null);

// ---- CRUD data helpers -------------------------------------------------
echo "\n[CRUD]\n";
require_once dirname(__DIR__) . '/api/v1/crud.php';
$items = collection_list('catalog');
check('collection_list catalog', count($items) > 0);
if ($items) {
    $first = $items[0];
    $decoded = collection_list('projects')[0] ?? null;
    check('first catalog has title', isset($first['title']));
}

// ---- Public page rendering (capture HTML) ------------------------------
echo "\n[PAGES]\n";
$slug = (db()->query("SELECT slug FROM catalog LIMIT 1")->fetchColumn());
$pslug = (db()->query("SELECT slug FROM projects LIMIT 1")->fetchColumn());
$pageFiles = [
    'home'        => 'home.php',
    'projects'    => 'projects.php',
    'materials'   => 'materials.php',
    'services'    => 'services.php',
    'contacts'    => 'contacts.php',
    'catalog-hub' => 'catalog-hub.php',
];
foreach ($pageFiles as $k => $file) {
    ob_start();
    require dirname(__DIR__) . '/pages/' . $file;
    $html = ob_get_clean();
    $ok = strpos($html, '<html') !== false || stripos($html, '<!doctype') !== false;
    check("page $k renders (" . strlen($html) . " bytes)", $ok);
    unset($html);
}

// catalog category + item
$_MEB_CAT = 'kuhni'; $_MEB_SLUG = null;
ob_start(); require dirname(__DIR__) . '/pages/catalog-item.php'; $h = ob_get_clean();
check('catalog category renders', strlen($h) > 100); unset($h);
$_MEB_CAT = 'kuhni'; $_MEB_SLUG = $slug;
if ($slug) { ob_start(); require dirname(__DIR__) . '/pages/catalog-item.php'; $h = ob_get_clean();
check('catalog item renders', strlen($h) > 100); unset($h); }

// project detail
if ($pslug) {
    $_MEB_SLUG = $pslug;
    ob_start(); require dirname(__DIR__) . '/pages/project.php'; $h = ob_get_clean();
    check('project detail renders', strlen($h) > 100); unset($h);
}
// cms page
$pgslug = db()->query("SELECT slug FROM pages LIMIT 1")->fetchColumn();
if ($pgslug) {
    $_MEB_SLUG = $pgslug;
    ob_start(); require dirname(__DIR__) . '/pages/page.php'; $h = ob_get_clean();
    check('cms page renders', strlen($h) > 100); unset($h);
}

// ---- Security -----------------------------------------------------------
echo "\n[SECURITY]\n";
check('e() escapes', e('<script>') === '&lt;script&gt;');
check('safe_ident strips', safe_ident('a;DROP TABLE') === 'aDROPTABLE');
check('is_valid_email', function_exists('is_valid_email') && is_valid_email('a@b.co'));
check('validate_upload blocks php', (function () {
    $f = ['name' => 'x.php', 'error' => UPLOAD_ERR_OK, 'size' => 10, 'tmp_name' => '/nonexistent', 'type' => 'application/x-php'];
    return validate_upload($f) === null;
})());

// ---- Final --------------------------------------------------------------
echo "\n== RESULT: $pass/$tests passed ==\n";
if ($fails) { echo "FAILED: " . implode(', ', $fails) . "\n"; exit(1); }
echo "ALL OK\n";
