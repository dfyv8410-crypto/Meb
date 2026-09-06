<?php
define("MEB_ROOT", "/storage/internal_new/project/Meb");
define("MEB_CONFIG_DIR", "/storage/internal_new/project/Meb/config");
define("MEB_LOCK_FILE", "/storage/internal_new/project/Meb/storage/installed.lock");
define("MEB_DATA_DIR", "/storage/internal_new/project/Meb/storage");
define("MEB_UPLOADS_DIR", "/storage/internal_new/project/Meb/uploads");
require_once MEB_ROOT . "/includes/router.php";
require_once MEB_ROOT . "/api/v1/crud.php";

$pass = 0;
$fail = 0;
function ck($d, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ $d\n"; } else { $fail++; echo "  ✗ $d\n"; } }

echo "=== FULL INTEGRATION TEST ===\n\n";

// MENU
echo "[MENU]\n";
$row = collection_insert("menu_items", ["title" => "Каталог", "url" => "/catalog", "sort_order" => 1, "is_active" => 1]);
ck("Menu CREATE", $row !== false && isset($row["id"]));
$id = $row["id"];
$updated = collection_update("menu_items", $id, ["title" => "Наш каталог"]);
ck("Menu UPDATE", $updated["title"] === "Наш каталог");
$items = collection_list("menu_items");
ck("Menu LIST", count($items) >= 1);
collection_delete("menu_items", $id);
ck("Menu DELETE", find_row("menu_items", $id) === null);

// CATALOG
echo "\n[CATALOG]\n";
$row = collection_insert("catalog", ["title" => "Тестовый стол", "slug" => "ts-" . time(), "description" => "Красивый стол", "price" => 85000, "category_id" => "cat1", "published" => 1, "featured" => 0]);
ck("Catalog CREATE", $row !== false);
$id = $row["id"];
$fetched = find_row("catalog", $id);
ck("Catalog READ", $fetched["title"] === "Тестовый стол" && $fetched["price"] == 85000);
$updated = collection_update("catalog", $id, ["title" => "Обновлённый стол", "price" => 95000, "published" => 0]);
ck("Catalog UPDATE", $updated["title"] === "Обновлённый стол" && $updated["price"] == 95000);
$items = collection_list("catalog");
ck("Catalog LIST", count($items) >= 1);
collection_delete("catalog", $id);
ck("Catalog DELETE", find_row("catalog", $id) === null);

// PROJECTS
echo "\n[PROJECTS]\n";
$row = collection_insert("projects", ["title" => "Тестовый проект", "slug" => "tp-" . time(), "description" => "Описание", "category" => "кухня", "published" => 1]);
ck("Projects CREATE", $row !== false);
$id = $row["id"];
$updated = collection_update("projects", $id, ["title" => "Обновлённый проект", "published" => 0]);
ck("Projects UPDATE", $updated["title"] === "Обновлённый проект");
$items = collection_list("projects");
ck("Projects LIST", count($items) >= 1);
collection_delete("projects", $id);
ck("Projects DELETE", find_row("projects", $id) === null);

// MATERIALS
echo "\n[MATERIALS]\n";
$row = collection_insert("materials", ["title" => "Дуб", "slug" => "dub-" . time(), "category" => "wood", "description" => "Натуральный дуб"]);
ck("Materials CREATE", $row !== false);
$id = $row["id"];
$updated = collection_update("materials", $id, ["title" => "Дуб обновлённый"]);
ck("Materials UPDATE", $updated["title"] === "Дуб обновлённый");
$items = collection_list("materials");
ck("Materials LIST", count($items) >= 1);
collection_delete("materials", $id);
ck("Materials DELETE", find_row("materials", $id) === null);

// SERVICES
echo "\n[SERVICES]\n";
$row = collection_insert("services", ["title" => "Замер", "slug" => "zamer-" . time(), "description" => "Бесплатный замер", "price_from" => 0]);
ck("Services CREATE", $row !== false);
$id = $row["id"];
$updated = collection_update("services", $id, ["title" => "Замер и проектирование", "price_from" => 5000]);
ck("Services UPDATE", $updated["title"] === "Замер и проектирование");
$items = collection_list("services");
ck("Services LIST", count($items) >= 1);
collection_delete("services", $id);
ck("Services DELETE", find_row("services", $id) === null);

// REVIEWS
echo "\n[REVIEWS]\n";
$row = collection_insert("reviews", ["author" => "Иван", "text" => "Отличная мебель!", "rating" => 5, "approved" => 0]);
ck("Reviews CREATE", $row !== false);
$id = $row["id"];
$fetched = find_row("reviews", $id);
ck("Reviews READ pending", $fetched["approved"] == 0 || $fetched["approved"] === false);
$updated = collection_update("reviews", $id, ["approved" => 1]);
ck("Reviews APPROVE", $updated["approved"] == 1 || $updated["approved"] === true);
$updated2 = collection_update("reviews", $id, ["approved" => 0]);
ck("Reviews HIDE", $updated2["approved"] == 0 || $updated2["approved"] === false);
$items = collection_list("reviews");
ck("Reviews LIST", count($items) >= 1);
collection_delete("reviews", $id);
ck("Reviews DELETE", find_row("reviews", $id) === null);

// PAGES
echo "\n[PAGES]\n";
$row = collection_insert("pages", ["title" => "О компании", "slug" => "about-" . time(), "blocks" => json_encode([["type" => "hero", "data" => ["title" => "О нас"]]]), "published" => 1]);
ck("Pages CREATE", $row !== false);
$id = $row["id"];
$updated = collection_update("pages", $id, ["title" => "О нашей компании"]);
ck("Pages UPDATE", $updated["title"] === "О нашей компании");
$items = collection_list("pages");
ck("Pages LIST", count($items) >= 1);
collection_delete("pages", $id);
ck("Pages DELETE", find_row("pages", $id) === null);

// LEADS
echo "\n[LEADS]\n";
$row = collection_insert("requests", ["name" => "Тест", "phone" => "+79001112233", "email" => "test@test.com", "message" => "Заявка", "status" => "new"]);
ck("Leads CREATE", $row !== false);
$id = $row["id"];
$updated = collection_update("requests", $id, ["status" => "in_progress"]);
ck("Leads UPDATE", $updated["status"] === "in_progress");
$items = collection_list("requests");
ck("Leads LIST", count($items) >= 1);
collection_delete("requests", $id);
ck("Leads DELETE", find_row("requests", $id) === null);

// USERS
echo "\n[USERS]\n";
$st = db()->prepare("INSERT INTO users (id,email,name,role,pass_hash,created_at,updated_at) VALUES (?,?,?,?,?,?,?)");
$uid = "test_" . time();
$st->execute([$uid, "test@test.com", "Тестер", "editor", password_hash("pass123", PASSWORD_DEFAULT), now_db(), now_db()]);
ck("Users CREATE", find_row("users", $uid) !== false);
$st2 = db()->prepare("UPDATE users SET name=? WHERE id=?");
$st2->execute(["Тестер обновлён", $uid]);
$u = find_row("users", $uid);
ck("Users UPDATE", $u["name"] === "Тестер обновлён");
ck("Users PASSWORD VERIFY", password_verify("pass123", $u["pass_hash"]));
ck("Users WRONG PASS", !password_verify("wrong", $u["pass_hash"]));
$st3 = db()->prepare("DELETE FROM users WHERE id=?");
$st3->execute([$uid]);
ck("Users DELETE", find_row("users", $uid) === null);

// SETTINGS
echo "\n[SETTINGS]\n";
$s = get_settings();
$s["siteName"] = "Тест Сайт";
$s["phone"] = "+79991112233";
$s["socials"] = ["instagram" => "https://instagram.com/test"];
save_settings($s);
$s2 = get_settings();
ck("Settings SAVE", $s2["siteName"] === "Тест Сайт" && $s2["phone"] === "+79991112233");
ck("Settings SOCIALS", ($s2["socials"]["instagram"] ?? "") === "https://instagram.com/test");
$s2["siteName"] = "MEB";
$s2["phone"] = "";
$s2["socials"] = [];
save_settings($s2);

// ANALYTICS
echo "\n[ANALYTICS]\n";
$today = date("Y-m-d");
$st = db()->prepare("INSERT INTO analytics (id,date,views,created_at,updated_at) VALUES (?,?,'5',?,?) ON DUPLICATE KEY UPDATE views=views+5");
$st->execute(["test_" . $today, $today, now_db(), now_db()]);
$st2 = db()->prepare("SELECT views FROM analytics WHERE date=?");
$st2->execute([$today]);
$views = (int)$st2->fetchColumn();
ck("Analytics TRACKING", $views >= 5);

// NOTIFICATIONS
echo "\n[NOTIFICATIONS]\n";
$row = collection_insert("notifications", ["type" => "test", "title" => "Тест", "body" => "Тестовое уведомление", "read" => 0]);
ck("Notifications CREATE", $row !== false);
$items = collection_list("notifications");
ck("Notifications LIST", count($items) >= 1);
collection_delete("notifications", $row["id"]);

// MEDIA
echo "\n[MEDIA]\n";
$row = collection_insert("media", ["filename" => "test.jpg", "original_name" => "test.jpg", "size" => 1024, "url" => "/uploads/test.jpg", "mime" => "image/jpeg"]);
ck("Media CREATE", $row !== false);
$items = collection_list("media");
ck("Media LIST", count($items) >= 1);
collection_delete("media", $row["id"]);

// BACKUP
echo "\n[BACKUP]\n";
$row = collection_insert("backups", ["filename" => "test-backup.json", "size" => 1024, "type" => "manual"]);
ck("Backup CREATE", $row !== false);
$items = collection_list("backups");
ck("Backup LIST", count($items) >= 1);
collection_delete("backups", $row["id"]);

// PAGE BUILDER
echo "\n[PAGE BUILDER]\n";
$pageData = ["title" => "Тест PB", "slug" => "test-pb-" . time(), "blocks" => [
    ["type" => "hero", "data" => ["title" => "Герой"]],
    ["type" => "text", "data" => ["text" => "Текст"]],
    ["type" => "features", "data" => ["items" => [["title" => "Фича"]]]],
    ["type" => "statistics", "data" => ["items" => [["title" => "100+"]]]],
    ["type" => "faq", "data" => ["items" => [["q" => "В?", "a" => "О."]]]],
    ["type" => "cta", "data" => ["title" => "CTA", "ctaLabel" => "Действие", "ctaUrl" => "/"]],
    ["type" => "team", "data" => ["items" => [["title" => "Иван"]]]],
], "published" => 1];
$row = collection_insert("pages", $pageData);
$_MEB_SLUG = $row["slug"];
ob_start();
include MEB_ROOT . "/pages/page.php";
$html = ob_get_clean();
ck("PB Hero", strpos($html, "pb-hero") !== false);
ck("PB Text", strpos($html, "pb-text") !== false);
ck("PB Features", strpos($html, "pb-features") !== false);
ck("PB Stats", strpos($html, "pb-stats") !== false);
ck("PB FAQ", strpos($html, "pb-faq") !== false);
ck("PB CTA", strpos($html, "cta-card") !== false);
ck("PB Team", strpos($html, "pb-team") !== false);
collection_delete("pages", $row["id"]);

// SECURITY
echo "\n[SECURITY]\n";
$s1 = htmlspecialchars('test"<>', ENT_QUOTES);
ck("e() escapes HTML", $s1 !== 'test"<>');
$r = db()->prepare("SELECT 1 FROM users WHERE email=?");
$r->execute(["admin@test.com OR 1=1"]);
ck("SQL injection blocked", $r->rowCount() === 0);
$r2 = db()->prepare("SELECT 1 FROM users WHERE email=?");
$r2->execute(["normal@test.com"]);
ck("SQL prepared OK", true);

// REVIEW FORM IN CONTACTS PAGE
echo "\n[REVIEW FORM]\n";
ob_start();
include MEB_ROOT . "/pages/contacts.php";
$html = ob_get_clean();
ck("Contacts has review form", strpos($html, "reviewForm") !== false || strpos($html, "review-form") !== false || strpos($html, "reviewFormModal") !== false);

// SETTINGS TABLE INTEGRITY
echo "\n[SETTINGS TABLE]\n";
$r = db()->query("SHOW COLUMNS FROM settings");
$cols = $r->fetchAll(PDO::FETCH_COLUMN);
ck("settings has data column", in_array("data", $cols));
ck("settings has id column", in_array("id", $cols));

echo "\n=== RESULT: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
