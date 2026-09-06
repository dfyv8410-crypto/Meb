<?php
/**
 * Final comprehensive verification of all remaining items.
 * Run: php scripts/final-verify.php
 */
define("MEB_ROOT", "/storage/internal_new/project/Meb");
define("MEB_CONFIG_DIR", "/storage/internal_new/project/Meb/config");
define("MEB_LOCK_FILE", "/storage/internal_new/project/Meb/storage/installed.lock");
define("MEB_DATA_DIR", "/storage/internal_new/project/Meb/storage");
define("MEB_UPLOADS_DIR", "/storage/internal_new/project/Meb/uploads");
require_once MEB_ROOT . "/includes/router.php";
require_once MEB_ROOT . "/includes/helpers.php";
require_once MEB_ROOT . "/includes/auth.php";
require_once MEB_ROOT . "/api/v1/crud.php";
require_once MEB_ROOT . "/api/v1/system.php";
require_once MEB_ROOT . "/api/v1/seo.php";

$pass = 0;
$fail = 0;
function ck($d, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ $d\n"; } else { $fail++; echo "  ✗ $d\n"; } }

echo "=== FINAL VERIFICATION ===\n\n";

// ============================================================
echo "[HOME PAGE DATA LOADING]\n";
// ============================================================

$_MEB_SLUG = '';
ob_start(); include MEB_ROOT . "/pages/home.php"; $html = ob_get_clean();
ck("Home: has hero section", strpos($html, 'hero') !== false || strpos($html, 'Hero') !== false);
ck("Home: has nav", strpos($html, '<header') !== false);
ck("Home: has footer", strpos($html, '<footer') !== false);
ck("Home: has catalog section", strpos($html, 'catalog') !== false || strpos($html, 'Каталог') !== false);
ck("Home: has projects section", strpos($html, 'project') !== false || strpos($html, 'Проект') !== false);
ck("Home: has reviews section", strpos($html, 'review') !== false || strpos($html, 'Отзыв') !== false);
ck("Home: has contacts section", strpos($html, 'contact') !== false || strpos($html, 'Контакт') !== false);
ck("Home: has services section", strpos($html, 'services') !== false || strpos($html, 'Услуг') !== false);
ck("Home: has premium CSS", strpos($html, 'premium.css') !== false);
ck("Home: has app.js", strpos($html, 'app.js') !== false);

// ============================================================
echo "\n[SEO SITEMAP]\n";
// ============================================================

ob_start(); include MEB_ROOT . "/pages/sitemap.php"; $xml = ob_get_clean();
ck("Sitemap: starts with XML declaration", strpos($xml, '<?xml') === 0);
ck("Sitemap: has urlset", strpos($xml, '<urlset') !== false);
ck("Sitemap: has URLs", preg_match_all('#<url>#', $xml) > 0);
ck("Sitemap: has homepage", strpos($xml, '<loc>') !== false);
ck("Sitemap: has changefreq", strpos($xml, '<changefreq>') !== false);
ck("Sitemap: has priority", strpos($xml, '<priority>') !== false);
// Count URLs
$urlCount = substr_count($xml, '<url>');
ck("Sitemap: has multiple URLs ($urlCount total)", $urlCount >= 4);

// ============================================================
echo "\n[ROBOTS.TXT]\n";
// ============================================================

ob_start(); include MEB_ROOT . "/pages/robots.php"; $robots = ob_get_clean();
ck("Robots: has User-agent", strpos($robots, 'User-agent') !== false);
ck("Robots: has Disallow /admin", strpos($robots, 'Disallow: /admin') !== false);
ck("Robots: has Disallow /api", strpos($robots, 'Disallow: /api') !== false);
ck("Robots: has Sitemap", strpos($robots, 'Sitemap') !== false);

// ============================================================
echo "\n[AUDIT LOG]\n";
// ============================================================

// Create and delete an item to generate audit entries
$item = collection_insert('catalog', ['title' => 'Audit Test', 'slug' => 'audit-' . time(), 'published' => 1]);
collection_update('catalog', $item['id'], ['title' => 'Audit Test Updated']);
collection_delete('catalog', $item['id']);

$logs = collection_list('audit_log');
ck("Audit log has entries", count($logs) > 0);
// Check for our operations
$foundCreate = false; $foundUpdate = false; $foundDelete = false;
foreach ($logs as $l) {
    $act = $l['action'] ?? '';
    $ent = $l['entity'] ?? '';
    if ($act === 'create' && $ent === 'catalog') $foundCreate = true;
    if ($act === 'update' && $ent === 'catalog') $foundUpdate = true;
    if ($act === 'delete' && $ent === 'catalog') $foundDelete = true;
}
ck("Audit: create logged", $foundCreate);
ck("Audit: update logged", $foundUpdate);
ck("Audit: delete logged", $foundDelete);
// Check audit has timestamp
$latest = $logs[0] ?? [];
ck("Audit: has created_at", !empty($latest['created_at']));

// ============================================================
echo "\n[NOTIFICATIONS SYSTEM]\n";
// ============================================================

// Create notifications of different types
$n1 = collection_insert('notifications', ['type' => 'lead', 'title' => 'Final Test Lead', 'body' => 'Lead notification', 'read' => 0]);
$n2 = collection_insert('notifications', ['type' => 'review', 'title' => 'Final Test Review', 'body' => 'Review notification', 'read' => 0]);
$n3 = collection_insert('notifications', ['type' => 'system', 'title' => 'Final Test System', 'body' => 'System notification', 'read' => 1]);

$notes = collection_list('notifications');
$testNotes = array_filter($notes, function ($n) { return strpos($n['title'] ?? '', 'Final Test') === 0; });
$unreadTest = array_filter($testNotes, function ($n) { return ($n['read'] ?? 0) == 0; });
$readTest = array_filter($testNotes, function ($n) { return ($n['read'] ?? 1) == 1; });
ck("Notifications: total >= 3", count($testNotes) >= 3);
ck("Notifications: unread count >= 2", count($unreadTest) >= 2);
ck("Notifications: read count >= 1", count($readTest) >= 1);

// Mark all as read
db()->prepare("UPDATE notifications SET `read`=1 WHERE id=?")->execute([$n1['id']]);
db()->prepare("UPDATE notifications SET `read`=1 WHERE id=?")->execute([$n2['id']]);
$n1u = find_row('notifications', $n1['id']);
$n2u = find_row('notifications', $n2['id']);
ck("Notifications: mark read works", $n1u['read'] == 1 && $n2u['read'] == 1);

// Delete notifications
collection_delete('notifications', $n1['id']);
collection_delete('notifications', $n2['id']);
collection_delete('notifications', $n3['id']);
$remaining = collection_list('notifications');
$stillThere = array_filter($remaining, function ($n) { return strpos($n['title'] ?? '', 'Final Test') === 0; });
ck("Notifications: delete works", count($stillThere) === 0);

// ============================================================
echo "\n[PAGE BUILDER FULL WORKFLOW]\n";
// ============================================================

// Create page with all block types
$pbSlug = 'final-pb-' . time();
$page = collection_insert('pages', [
    'title' => 'Final PB Test',
    'slug' => $pbSlug,
    'published' => 1,
    'seo_title' => 'SEO Title',
    'seo_desc' => 'SEO Description',
    'canonical' => 'https://example.com/canonical',
    'h1' => 'Custom H1',
    'blocks' => [
        ['type' => 'hero', 'data' => ['title' => 'Hero', 'subtitle' => 'Sub', 'ctaLabel' => 'CTA', 'ctaUrl' => '/test']],
        ['type' => 'text', 'data' => ['text' => 'Text content.']],
        ['type' => 'features', 'data' => ['items' => [['title' => 'F1', 'desc' => 'D1'], ['title' => 'F2', 'desc' => 'D2']]]],
        ['type' => 'gallery', 'data' => ['images' => [['url' => '/img/g1.jpg'], ['url' => '/img/g2.jpg']]]],
        ['type' => 'statistics', 'data' => ['items' => [['title' => '100', 'desc' => 'items']]]],
        ['type' => 'faq', 'data' => ['items' => [['q' => 'Q1', 'a' => 'A1'], ['q' => 'Q2', 'a' => 'A2']]]],
        ['type' => 'cta', 'data' => ['title' => 'CTA Block', 'ctaLabel' => 'Button', 'ctaUrl' => '/contacts']],
        ['type' => 'team', 'data' => ['items' => [['title' => 'Person', 'role' => 'Role', 'desc' => 'Bio']]]],
        ['type' => 'contact', 'data' => []],
    ],
]);

// Verify in DB
$fetched = find_row('pages', $page['id']);
ck("PB: page saved in DB", !empty($fetched));
ck("PB: blocks stored as JSON", is_string($fetched['blocks']) || is_array($fetched['blocks']));
$blocks = is_array($fetched['blocks']) ? $fetched['blocks'] : json_decode($fetched['blocks'], true);
ck("PB: 9 blocks stored", count($blocks) === 9);
ck("PB: has seo_title", $fetched['seo_title'] === 'SEO Title');
ck("PB: has canonical", $fetched['canonical'] === 'https://example.com/canonical');
ck("PB: has h1", $fetched['h1'] === 'Custom H1');

// Render page
$_MEB_SLUG = $pbSlug;
ob_start(); include MEB_ROOT . "/pages/page.php"; $html = ob_get_clean();
ck("PB: hero renders", strpos($html, 'Hero') !== false);
ck("PB: text renders", strpos($html, 'Text content') !== false);
ck("PB: features render", strpos($html, 'F1') !== false && strpos($html, 'F2') !== false);
ck("PB: gallery renders", strpos($html, 'g1.jpg') !== false);
ck("PB: stats render", strpos($html, '100') !== false);
ck("PB: faq renders", strpos($html, 'Q1') !== false && strpos($html, 'A1') !== false);
ck("PB: cta renders", strpos($html, 'CTA Block') !== false);
ck("PB: team renders", strpos($html, 'Person') !== false);
ck("PB: contact form renders", strpos($html, 'submitLead') !== false);
ck("PB: seo_title in head", strpos($html, 'SEO Title') !== false);
ck("PB: canonical link", strpos($html, 'canonical') !== false);
ck("PB: custom h1", strpos($html, 'Custom H1') !== false);
ck("PB: has nav", strpos($html, '<header') !== false);
ck("PB: has footer", strpos($html, '<footer') !== false);

// Update page (change a block)
$blocks[0]['data']['title'] = 'Updated Hero';
collection_update('pages', $page['id'], ['blocks' => $blocks]);
$_MEB_SLUG = $pbSlug;
ob_start(); include MEB_ROOT . "/pages/page.php"; $html2 = ob_get_clean();
ck("PB: update hero renders", strpos($html2, 'Updated Hero') !== false);

// Delete page
collection_delete('pages', $page['id']);
ck("PB: page deleted", find_row('pages', $page['id']) === null);

// ============================================================
echo "\n[SETTINGS FULL WORKFLOW]\n";
// ============================================================

$s = get_settings();
$orig = $s;

// Save all settings
$s['siteName'] = 'Test Site Name';
$s['tagline'] = 'Test Tagline';
$s['phone'] = '+7 (999) 111-22-33';
$s['email'] = 'test@test.com';
$s['address'] = 'Test Address';
$s['siteDesc'] = 'Test Description';
$s['copyright'] = '2026 Test';
$s['logo'] = '/uploads/logo-test.png';
$s['favicon'] = '/uploads/favicon-test.png';
$s['ogTitle'] = 'OG Title';
$s['ogDesc'] = 'OG Description';
$s['ogImage'] = '/uploads/og-test.png';
$s['socials'] = [
    'instagram' => 'https://instagram.com/test',
    'telegram' => 'https://t.me/test',
    'whatsapp' => 'https://wa.me/test',
    'vk' => 'https://vk.com/test',
];
save_settings($s);

// Reload and verify
$s2 = get_settings();
ck("Settings: siteName", $s2['siteName'] === 'Test Site Name');
ck("Settings: tagline", $s2['tagline'] === 'Test Tagline');
ck("Settings: phone", $s2['phone'] === '+7 (999) 111-22-33');
ck("Settings: email", $s2['email'] === 'test@test.com');
ck("Settings: address", $s2['address'] === 'Test Address');
ck("Settings: siteDesc", $s2['siteDesc'] === 'Test Description');
ck("Settings: copyright", $s2['copyright'] === '2026 Test');
ck("Settings: logo", $s2['logo'] === '/uploads/logo-test.png');
ck("Settings: favicon", $s2['favicon'] === '/uploads/favicon-test.png');
ck("Settings: ogTitle", $s2['ogTitle'] === 'OG Title');
ck("Settings: ogDesc", $s2['ogDesc'] === 'OG Description');
ck("Settings: socials.instagram", $s2['socials']['instagram'] === 'https://instagram.com/test');
ck("Settings: socials.telegram", $s2['socials']['telegram'] === 'https://t.me/test');
ck("Settings: socials.whatsapp", $s2['socials']['whatsapp'] === 'https://wa.me/test');
ck("Settings: socials.vk", $s2['socials']['vk'] === 'https://vk.com/test');

// Verify layout uses settings
ob_start(); include MEB_ROOT . "/pages/layout.php"; layout_nav(); $navHtml = ob_get_clean();
ck("Settings: siteName in nav", strpos($navHtml, 'Test Site Name') !== false);
ck("Settings: logo in nav", strpos($navHtml, 'logo-test.png') !== false);

// Verify footer uses settings
ob_start(); include MEB_ROOT . "/pages/layout.php"; layout_footer(); $footerHtml = ob_get_clean();
ck("Settings: copyright in footer", strpos($footerHtml, '2026 Test') !== false);
ck("Settings: instagram in footer", strpos($footerHtml, 'instagram.com/test') !== false);
ck("Settings: phone in footer", strpos($footerHtml, '+7 (999) 111-22-33') !== false);

// Restore
save_settings($orig);

// ============================================================
echo "\n[CONTACTS PAGE]\n";
// ============================================================

ob_start(); include MEB_ROOT . "/pages/contacts.php"; $contactsHtml = ob_get_clean();
ck("Contacts: has form", strpos($contactsHtml, '<form') !== false || strpos($contactsHtml, 'form') !== false);
ck("Contacts: has name field", strpos($contactsHtml, 'name') !== false);
ck("Contacts: has phone field", strpos($contactsHtml, 'phone') !== false);
ck("Contacts: has email field", strpos($contactsHtml, 'email') !== false);
ck("Contacts: has message field", strpos($contactsHtml, 'message') !== false || strpos($contactsHtml, 'textarea') !== false);
ck("Contacts: has submit button", strpos($contactsHtml, 'submit') !== false || strpos($contactsHtml, 'Отправить') !== false);
ck("Contacts: has review form", strpos($contactsHtml, 'review') !== false || strpos($contactsHtml, 'Отзыв') !== false);
ck("Contacts: has nav", strpos($contactsHtml, '<header') !== false);
ck("Contacts: has footer", strpos($contactsHtml, '<footer') !== false);

// ============================================================
echo "\n[HEALTH ENDPOINT FULL]\n";
// ============================================================

$health = db()->query('SELECT 1')->fetchColumn();
ck("Health: DB query works", $health == 1);

$storage = is_writable(MEB_ROOT . '/storage');
ck("Health: storage writable", $storage);

$php = version_compare(PHP_VERSION, '7.1', '>=');
ck("Health: PHP >= 7.1", $php);

$pdoExt = extension_loaded('pdo_mysql');
ck("Health: PDO MySQL extension", $pdoExt);

// ============================================================
echo "\n[ERROR LOGGING]\n";
// ============================================================

// Verify error log path is configured
$errLog = ini_get('error_log');
ck("Error log configured", !empty($errLog));
ck("Error log path correct", strpos($errLog, 'error.log') !== false);
$displayErrors = ini_get('display_errors');
ck("Display errors off for production", $displayErrors === '0' || $displayErrors === false);

// ============================================================
echo "\n=== RESULT: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
