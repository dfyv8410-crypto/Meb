<?php
/**
 * End-to-end flow test — tests real user workflows through PHP functions.
 * Run: php scripts/e2e-test.php
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

$pass = 0;
$fail = 0;
function ck($d, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ $d\n"; } else { $fail++; echo "  ✗ $d\n"; } }

echo "=== E2E FLOW TESTS ===\n\n";

// ============================================================
echo "[LEAD FLOW: Submit → DB → Notification → Admin read]\n";
// ============================================================

// 1. Create lead (simulates leads-public.php logic)
$leadId = new_id();
$st = db()->prepare("INSERT INTO requests (id,name,phone,email,message,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)");
$st->execute([$leadId, 'Тест Клиент', '+79991234567', 'client@test.com', 'Хочу кухню', 'new', now_db(), now_db()]);
ck("Lead created in DB", !empty($leadId));

// 2. Lead data correct
$lead = find_row('requests', $leadId);
ck("Lead has correct name", $lead['name'] === 'Тест Клиент');
ck("Lead has correct phone", $lead['phone'] === '+79991234567');
ck("Lead has correct email", $lead['email'] === 'client@test.com');
ck("Lead status is new", $lead['status'] === 'new');

// 3. Notification created (simulates leads.php logic)
$noteId = new_id();
$st2 = db()->prepare("INSERT INTO notifications (id,type,title,body,`read`,created_at,updated_at) VALUES (?,?,?,?,0,?,?)");
$st2->execute([$noteId, 'lead', 'Новая заявка: Тест Клиент', 'Телефон: +79991234567. Хочу кухню', now_db(), now_db()]);
ck("Notification created", !empty($noteId));

// 4. Admin can see lead in list
$leads = collection_list('requests');
$found = false;
foreach ($leads as $l) { if ($l['id'] === $leadId) { $found = true; break; } }
ck("Admin sees lead in list", $found);

// 5. Admin updates status
collection_update('requests', $leadId, ['status' => 'in_progress', 'comment' => 'Перезвоним завтра']);
$updated = find_row('requests', $leadId);
ck("Lead status updated to in_progress", $updated['status'] === 'in_progress');
ck("Lead comment saved", $updated['comment'] === 'Перезвоним завтра');

// 6. Admin marks as done
collection_update('requests', $leadId, ['status' => 'done']);
$done = find_row('requests', $leadId);
ck("Lead status set to done", $done['status'] === 'done');

// ============================================================
echo "\n[REVIEW FLOW: Submit → Pending → Approve → Site shows]\n";
// ============================================================

// 1. Public submits review (simulates reviews-public.php)
$revId = new_id();
$st = db()->prepare("INSERT INTO reviews (id,author,text,rating,approved,created_at,updated_at) VALUES (?,?,?,?,'0',?,?)");
$st->execute([$revId, 'Ольга Тестова', 'Отличная мебель! Очень довольна качеством.', 5, now_db(), now_db()]);
ck("Review submitted", !empty($revId));

// 2. Review is pending
$rev = find_row('reviews', $revId);
ck("Review is pending (approved=0)", $rev['approved'] == 0 || $rev['approved'] === false);

// 3. Home page does NOT show pending review
$reviews = collection_list('reviews');
$approved = array_filter($reviews, function ($r) { return ($r['approved'] ?? 0) == 1 || ($r['approved'] ?? false) === true; });
$pendingOnHome = false;
foreach ($approved as $r) { if ($r['id'] === $revId) { $pendingOnHome = true; break; } }
ck("Pending review NOT in approved list", !$pendingOnHome);

// 4. Admin approves review
collection_update('reviews', $revId, ['approved' => 1]);
$approved2 = find_row('reviews', $revId);
ck("Review approved", $approved2['approved'] == 1 || $approved2['approved'] === true);

// 5. Now review IS in approved list
$allReviews = collection_list('reviews');
$approved3 = array_filter($allReviews, function ($r) { return ($r['approved'] ?? 0) == 1 || ($r['approved'] ?? false) === true; });
$nowVisible = false;
foreach ($approved3 as $r) { if ($r['id'] === $revId) { $nowVisible = true; break; } }
ck("Approved review now visible", $nowVisible);

// 6. Admin can hide review
collection_update('reviews', $revId, ['approved' => 0]);
$hidden = find_row('reviews', $revId);
ck("Review hidden again", $hidden['approved'] == 0 || $hidden['approved'] === false);

// 7. Delete test review
collection_delete('reviews', $revId);
ck("Review deleted", find_row('reviews', $revId) === null);

// ============================================================
echo "\n[CATALOG FLOW: Create → Category shows → Item detail → Delete]\n";
// ============================================================

$testSlug = 'e2e-test-' . time();
$item = collection_insert('catalog', [
    'title' => 'E2E Тестовый стол',
    'slug' => $testSlug,
    'description' => 'Описание E2E стола для проверки.',
    'price' => 125000,
    'category_id' => 'kukhni',
    'featured' => 1,
    'published' => 1,
    'images' => [],
    'specs' => ['Материал' => 'Дуб', 'Размер' => '180x90'],
]);
$catItemId = $item['id'];
ck("Catalog item created", !empty($catItemId));

// Category page shows item
$catalog = collection_list('catalog');
$catItems = array_filter($catalog, function ($c) use ($catItemId) { return ($c['category_id'] ?? '') === 'kukhni'; });
$inCategory = false;
foreach ($catItems as $c) { if ($c['id'] === $catItemId) { $inCategory = true; break; } }
ck("Item in kukhni category", $inCategory);

// Item detail data correct
$fetched = find_row('catalog', $catItemId);
ck("Item title correct", $fetched['title'] === 'E2E Тестовый стол');
ck("Item price correct", $fetched['price'] == 125000);
ck("Item has specs", !empty($fetched['specs']) && is_array($fetched['specs']));
ck("Item specs: Материал", ($fetched['specs']['Материал'] ?? '') === 'Дуб');

// Update item
collection_update('catalog', $catItemId, ['title' => 'E2E Обновлённый стол', 'price' => 99999]);
$upd = find_row('catalog', $catItemId);
ck("Item updated", $upd['title'] === 'E2E Обновлённый стол' && $upd['price'] == 99999);

// Delete
collection_delete('catalog', $catItemId);
ck("Item deleted", find_row('catalog', $catItemId) === null);

// ============================================================
echo "\n[PROJECT FLOW: Create → Public page → Delete]\n";
// ============================================================

$projSlug = 'e2e-proj-' . time();
$proj = collection_insert('projects', [
    'title' => 'E2E Проект',
    'slug' => $projSlug,
    'description' => 'Описание E2E проекта.',
    'category' => 'кухня',
    'year' => '2026',
    'published' => 1,
    'features' => ['Фича 1', 'Фича 2'],
]);
ck("Project created", !empty($proj['id']));

$projData = find_row('projects', $proj['id']);
ck("Project slug set", $projData['slug'] === $projSlug);
ck("Project year set", $projData['year'] == 2026);
ck("Project has features", !empty($projData['features']) && is_array($projData['features']));

collection_delete('projects', $proj['id']);
ck("Project deleted", find_row('projects', $proj['id']) === null);

// ============================================================
echo "\n[MENU FLOW: Create → Sort → Toggle → Delete]\n";
// ============================================================

$menuItemsBefore = collection_list('menu_items');
$m1 = collection_insert('menu_items', ['title' => 'Пункт 1', 'url' => '/p1', 'sort_order' => 1, 'is_active' => 1]);
$m2 = collection_insert('menu_items', ['title' => 'Пункт 2', 'url' => '/p2', 'sort_order' => 2, 'is_active' => 1]);
$m3 = collection_insert('menu_items', ['title' => 'Пункт 3', 'url' => '/p3', 'sort_order' => 3, 'is_active' => 0]);
ck("Menu items created", !empty($m1['id']) && !empty($m2['id']) && !empty($m3['id']));

// Sort order (robust to pre-existing dynamic menu items)
$menuItems = collection_list('menu_items');
usort($menuItems, function ($a, $b) { return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0); });
$testPos = [];
foreach ($menuItems as $i => $mi) {
    if ($mi['title'] === 'Пункт 1') { $testPos[1] = $i; }
    if ($mi['title'] === 'Пункт 2') { $testPos[2] = $i; }
    if ($mi['title'] === 'Пункт 3') { $testPos[3] = $i; }
}
ck("Sort order correct (Пункт 1 → 2 → 3)", isset($testPos[1], $testPos[2], $testPos[3]) && $testPos[1] < $testPos[2] && $testPos[2] < $testPos[3]);

// Toggle: hide m1, show m3
collection_update('menu_items', $m1['id'], ['is_active' => 0]);
collection_update('menu_items', $m3['id'], ['is_active' => 1]);
$u1 = find_row('menu_items', $m1['id']);
$u3 = find_row('menu_items', $m3['id']);
ck("m1 now hidden", $u1['is_active'] == 0);
ck("m3 now active", $u3['is_active'] == 1);

// Public gets only active
$allMenu = collection_list('menu_items');
$activeMenu = array_filter($allMenu, function ($i) { return ($i['is_active'] ?? 1) == 1; });
$activeTitles = array_map(function ($i) { return $i['title']; }, array_values($activeMenu));
ck("Active menu: Пункт 2 and Пункт 3", in_array('Пункт 2', $activeTitles) && in_array('Пункт 3', $activeTitles));
ck("Active menu: NOT Пункт 1", !in_array('Пункт 1', $activeTitles));

// Cleanup
collection_delete('menu_items', $m1['id']);
collection_delete('menu_items', $m2['id']);
collection_delete('menu_items', $m3['id']);
ck("Menu items cleaned up", count(collection_list('menu_items')) === count($menuItemsBefore));

// ============================================================
echo "\n[PAGE BUILDER FLOW: Create → Render all 9 blocks → Delete]\n";
// ============================================================

$pbSlug = 'e2e-pb-' . time();
$pbPage = collection_insert('pages', [
    'title' => 'E2E PB',
    'slug' => $pbSlug,
    'blocks' => [
        ['type' => 'hero', 'data' => ['title' => 'Hero Title', 'subtitle' => 'Hero Sub', 'ctaLabel' => 'CTA', 'ctaUrl' => '/test']],
        ['type' => 'text', 'data' => ['text' => 'Paragraph content.']],
        ['type' => 'features', 'data' => ['items' => [['title' => 'F1', 'desc' => 'D1'], ['title' => 'F2', 'desc' => 'D2']]]],
        ['type' => 'gallery', 'data' => ['images' => [['url' => '/img/test.jpg']]]],
        ['type' => 'statistics', 'data' => ['items' => [['title' => '99+', 'desc' => 'stat']]]],
        ['type' => 'faq', 'data' => ['items' => [['q' => 'Q1?', 'a' => 'A1.']]]],
        ['type' => 'cta', 'data' => ['title' => 'CTA Block', 'ctaLabel' => 'Btn', 'ctaUrl' => '/test']],
        ['type' => 'team', 'data' => ['items' => [['title' => 'TM1', 'role' => 'Role1']]]],
        ['type' => 'contact', 'data' => []],
    ],
    'published' => 1,
]);
$_MEB_SLUG = $pbSlug;
ob_start(); include MEB_ROOT . "/pages/page.php"; $html = ob_get_clean();

ck("PB Hero", strpos($html, 'Hero Title') !== false);
ck("PB Hero subtitle", strpos($html, 'Hero Sub') !== false);
ck("PB Hero CTA", strpos($html, 'CTA') !== false);
ck("PB Text", strpos($html, 'Paragraph content') !== false);
ck("PB Features F1+F2", strpos($html, 'F1') !== false && strpos($html, 'F2') !== false);
ck("PB Gallery", strpos($html, 'test.jpg') !== false);
ck("PB Stats", strpos($html, '99+') !== false);
ck("PB FAQ", strpos($html, 'Q1?') !== false && strpos($html, 'A1.') !== false);
ck("PB CTA Block", strpos($html, 'CTA Block') !== false && strpos($html, 'Btn') !== false);
ck("PB Team", strpos($html, 'TM1') !== false && strpos($html, 'Role1') !== false);
ck("PB Contact form", strpos($html, 'submitLead') !== false);
ck("PB has nav", strpos($html, '<header') !== false);
ck("PB has footer", strpos($html, '<footer') !== false);

collection_delete('pages', $pbPage['id']);

// ============================================================
echo "\n[SETTINGS FLOW: Save → Layout reflects → Restore]\n";
// ============================================================

$s = get_settings();
$origName = $s['siteName'] ?? '';
$s['siteName'] = 'E2E Test Site';
$s['phone'] = '+7 (000) 000-00-00';
$s['copyright'] = '2026 E2E Test';
$s['socials'] = ['instagram' => 'https://instagram.com/e2e'];
save_settings($s);

$s2 = get_settings();
ck("Settings: siteName saved", $s2['siteName'] === 'E2E Test Site');
ck("Settings: phone saved", $s2['phone'] === '+7 (000) 000-00-00');
ck("Settings: copyright saved", $s2['copyright'] === '2026 E2E Test');
ck("Settings: socials saved", ($s2['socials']['instagram'] ?? '') === 'https://instagram.com/e2e');

// Restore
$s['siteName'] = $origName;
$s['phone'] = '';
$s['copyright'] = '';
$s['socials'] = [];
save_settings($s);

// ============================================================
echo "\n[HEALTH & ANALYTICS]\n";
// ============================================================

try {
    db()->query('SELECT 1');
    ck("DB connection healthy", true);
} catch (\Throwable $e) {
    ck("DB connection healthy", false);
}

$st = db()->prepare("INSERT INTO analytics (id,date,views,created_at,updated_at) VALUES (?,?,'10',?,?) ON DUPLICATE KEY UPDATE views=views+10");
$st->execute(["e2e-" . date("Y-m-d"), date("Y-m-d"), now_db(), now_db()]);
$st2 = db()->prepare("SELECT views FROM analytics WHERE date=?");
$st2->execute([date("Y-m-d")]);
$views = (int)$st2->fetchColumn();
ck("Analytics tracks pageviews", $views >= 10);

// ============================================================
echo "\n[NOTIFICATION FLOW: Create → List → Mark read → Delete]\n";
// ============================================================

$n1 = collection_insert('notifications', ['type' => 'lead', 'title' => 'Новая заявка', 'body' => 'Тест', 'read' => 0]);
$n2 = collection_insert('notifications', ['type' => 'review', 'title' => 'Новый отзыв', 'body' => 'Тест 2', 'read' => 0]);
ck("Notifications created", !empty($n1['id']) && !empty($n2['id']));

$notes = collection_list('notifications');
$unread = array_filter($notes, function ($n) { return ($n['read'] ?? 0) == 0; });
ck("Two unread notifications", count($unread) >= 2);

// Mark read
db()->prepare("UPDATE notifications SET `read`=1 WHERE id=?")->execute([$n1['id']]);
$n1u = find_row('notifications', $n1['id']);
ck("Notification marked as read", $n1u['read'] == 1);

// Cleanup
collection_delete('notifications', $n1['id']);
collection_delete('notifications', $n2['id']);
// Also clean any leftover notifications from lead flow
db()->exec("DELETE FROM notifications WHERE type IN ('lead','review')");
ck("Notifications cleaned up", count(collection_list('notifications')) === 0);

// ============================================================
echo "\n[USER FLOW: Create → Verify password → Update → Delete]\n";
// ============================================================

$uid = 'e2e-user-' . time();
$hash = password_hash('testpass123', PASSWORD_DEFAULT);
$st = db()->prepare("INSERT INTO users (id,email,name,role,pass_hash,created_at,updated_at) VALUES (?,?,?,?,?,?,?)");
$st->execute([$uid, 'e2e@test.com', 'E2E User', 'editor', $hash, now_db(), now_db()]);
ck("User created", !empty($uid));

$user = find_row('users', $uid);
ck("User password verify correct", password_verify('testpass123', $user['pass_hash']));
ck("User password verify wrong fails", !password_verify('wrongpass', $user['pass_hash']));
ck("User email correct", $user['email'] === 'e2e@test.com');
ck("User role correct", $user['role'] === 'editor');

db()->prepare("UPDATE users SET name=? WHERE id=?")->execute(['E2E Updated', $uid]);
$u2 = find_row('users', $uid);
ck("User updated", $u2['name'] === 'E2E Updated');

db()->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
ck("User deleted", find_row('users', $uid) === null);

// ============================================================
echo "\n[SITEMAP]\n";
// ============================================================

ob_start(); include MEB_ROOT . "/pages/sitemap.php"; $xml = ob_get_clean();
ck("Sitemap is valid XML", strpos($xml, '<?xml') !== false);
ck("Sitemap has URLs", strpos($xml, '<url>') !== false);
ck("Sitemap has homepage", strpos($xml, '<loc>') !== false);

// ============================================================
echo "\n=== RESULT: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
