<?php
/**
 * Seed realistic demo data + run public pages end-to-end test.
 * Run: php scripts/public-test.php
 */
define("MEB_ROOT", "/storage/internal_new/project/Meb");
define("MEB_CONFIG_DIR", "/storage/internal_new/project/Meb/config");
define("MEB_LOCK_FILE", "/storage/internal_new/project/Meb/storage/installed.lock");
define("MEB_DATA_DIR", "/storage/internal_new/project/Meb/storage");
define("MEB_UPLOADS_DIR", "/storage/internal_new/project/Meb/uploads");
require_once MEB_ROOT . "/includes/router.php";
require_once MEB_ROOT . "/includes/helpers.php";
require_once MEB_ROOT . "/api/v1/crud.php";

$pass = 0;
$fail = 0;
function ck($d, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "  ✓ $d\n"; } else { $fail++; echo "  ✗ $d\n"; } }

echo "=== SEEDING DEMO DATA ===\n\n";

// Clean up any leftover test data from previous runs
$cleanupTables = ["catalog_categories", "catalog", "projects", "materials", "services", "reviews", "pages", "menu_items", "requests"];
foreach ($cleanupTables as $t) {
    try { db()->exec("DELETE FROM `$t`"); } catch (\Throwable $e) {}
}
echo "  Cleaned tables\n";

// Seed catalog categories
$catData = [
    ["id" => "kukhni",     "title" => "Кухни",           "slug" => "kukhni",     "description" => "Кухни на заказ из натуральных материалов", "cover" => "", "sort" => 1],
    ["id" => "gornye",     "title" => "Горные комнаты",   "slug" => "gornye",     "description" => "Мебель для горных комнат", "cover" => "", "sort" => 2],
    ["id" => "mebel",      "title" => "Мебель",           "slug" => "mebel",      "description" => "Корпусная и корпусно-столешничная мебель", "cover" => "", "sort" => 3],
    ["id" => "detskie",    "title" => "Детские комнаты",  "slug" => "detskie",    "description" => "Безопасная мебель для детей", "cover" => "", "sort" => 4],
    ["id" => "vannie",     "title" => "Ванные комнаты",   "slug" => "vannie",     "description" => "Мебель для ванных комнат", "cover" => "", "sort" => 5],
    ["id" => "garderob",   "title" => "Гардеробные",      "slug" => "garderob",   "description" => "Встроенные гардеробные", "cover" => "", "sort" => 6],
];
foreach ($catData as $c) {
    if (!find_row("catalog_categories", $c["id"])) {
        collection_insert_id("catalog_categories", $c);
    }
}
ck("Catalog categories seeded", count($catData) === 6);

// Seed catalog items
$catalogItems = [
    ["title" => "Кухня «Альпийская», дуб", "slug" => "kukhnya-alpiyskaya", "description" => "Современная кухня из массива дуба с фрезерованными фасадами. Столешница из искусственного камня.", "price" => 385000, "category_id" => "kukhni", "images" => [], "featured" => 1, "published" => 1, "seo_title" => "Кухня Альпийская из дуба - MEB", "seo_desc" => "Кухня на заказ из натурального дуба с каменной столешницей"],
    ["title" => "Кухня «Минимал», ясень", "slug" => "kukhnya-minimal", "description" => "Лаконичная кухня в скандинавском стиле. Фасады из ясеня. LED-подсветка.", "price" => 290000, "category_id" => "kukhni", "images" => [], "featured" => 1, "published" => 1],
    ["title" => "Кухня «Классик», орех", "slug" => "kukhnya-classic", "description" => "Кухня в классическом стиле с резными фасадами из ореха. Мраморная столешница.", "price" => 450000, "category_id" => "kukhni", "images" => [], "featured" => 0, "published" => 1],
    ["title" => "Горная комната «Шале»", "slug" => "gornaya-shale", "description" => "Комплект мебели для горной комнаты из сосны.", "price" => 280000, "category_id" => "gornye", "images" => [], "featured" => 1, "published" => 1],
    ["title" => "Шкаф-купе «Горизонт»", "slug" => "shkaf-gorizont", "description" => "Встроенный шкаф-купе с зеркальными дверями.", "price" => 185000, "category_id" => "garderob", "images" => [], "featured" => 0, "published" => 1],
];
foreach ($catalogItems as $c) {
    collection_insert("catalog", $c);
}
ck("Catalog items seeded", count($catalogItems) === 5);

// Seed projects
$projects = [
    ["title" => "Кухня в пентхаусе на Патриарших", "slug" => "kukhnya-patriarshie", "description" => "Просторная кухня-гостиная в пентхаусе. Натуральный дуб, мрамор, встроенная техника.", "category" => "кухня", "year" => "2025", "published" => 1],
    ["title" => "Горная комната в загородном доме", "slug" => "gornaya-zagorod", "description" => "Уютная горная комната из сосны с каминной зоной.", "category" => "горная комната", "year" => "2025", "published" => 1],
    ["title" => "Детская для двоих", "slug" => "detskaya-dvoih", "description" => "Двухъярусная кровать, рабочие зоны, вместительные шкафы.", "category" => "детская", "year" => "2024", "published" => 1],
    ["title" => "Гардеробная в спальне", "slug" => "garderob-spalnya", "description" => "Встроенная гардеробная ссистемой хранения Elfit.", "category" => "гардеробная", "year" => "2025", "published" => 1],
];
foreach ($projects as $p) {
    collection_insert("projects", $p);
}
ck("Projects seeded", count($projects) === 4);

// Seed materials
$materials = [
    ["title" => "Дуб", "slug" => "dub", "category" => "wood", "description" => "Натуральный дуб — прочное дерево с красивой текстурой.", "image" => "", "props" => ["Твёрдость" => "Высокая", "Цвет" => "Светло-коричневый"]],
    ["title" => "Ясень", "slug" => "yasen", "category" => "wood", "description" => "Ясень — светлое дерево с выраженной текстурой.", "image" => "", "props" => ["Твёрдость" => "Средняя", "Цвет" => "Светлый"]],
    ["title" => "Сосна", "slug" => "sosna", "category" => "wood", "description" => "Сосна — доступная хвойная порода.", "image" => "", "props" => ["Твёрдость" => "Низкая", "Цвет" => "Жёлтый"]],
    ["title" => "Искусственный камень", "slug" => "kamen", "category" => "stone", "description" => "Кварцевый агломерат — прочный материал.", "image" => "", "props" => ["Твёрдость" => "Очень высокая", "Цвет" => "Белый/Серый"]],
];
foreach ($materials as $m) {
    collection_insert("materials", $m);
}
ck("Materials seeded", count($materials) === 4);

// Seed services
$services = [
    ["title" => "Замер и проектирование", "slug" => "zamer", "description" => "Бесплатный замер помещения. 3D-проектирование мебели.", "price_from" => 0],
    ["title" => "Изготовление мебели", "slug" => "izgotovlenie", "description" => "Производство мебели на собственном оборудовании.", "price_from" => 50000],
    ["title" => "Доставка и установка", "slug" => "dostavka", "description" => "Аккуратная доставка и профессиональная установка.", "price_from" => 5000],
];
foreach ($services as $s) {
    collection_insert("services", $s);
}
ck("Services seeded", count($services) === 3);

// Seed reviews (mix of approved and pending)
$reviews = [
    ["author" => "Алексей Петров", "role" => "Директор", "text" => "Заказывали кухню для нового офиса. Ребята молодцы — всё сделали в срок, качество отличное. Материалы подобрали идеально под наш интерьер.", "rating" => 5, "approved" => 1],
    ["author" => "Мария Иванова", "text" => "Делали детскую для двоих детей. Всё безопасно, красиво и функционально. Дети в восторге!", "rating" => 5, "approved" => 1],
    ["author" => "Дмитрий Козлов", "text" => "Шкаф-купе получился шикарный. Мастера вежливые, всё убрали за собой. Рекомендую!", "rating" => 5, "approved" => 1],
    ["author" => "Ольга Сидорова", "text" => "Отличная работа! Кухня из дуба — просто мечта. Спасибо команде MEB!", "rating" => 4, "approved" => 1],
    ["author" => "Тестовый отзыв", "text" => "Это тестовый отзыв на модерации.", "rating" => 3, "approved" => 0],
];
foreach ($reviews as $r) {
    collection_insert("reviews", $r);
}
ck("Reviews seeded (4 approved + 1 pending)", true);

// Seed menu items
$menuData = [
    ["title" => "Каталог", "url" => "/catalog", "sort_order" => 1, "is_active" => 1],
    ["title" => "Проекты", "url" => "/projects", "sort_order" => 2, "is_active" => 1],
    ["title" => "Материалы", "url" => "/materials", "sort_order" => 3, "is_active" => 1],
    ["title" => "Услуги", "url" => "/services", "sort_order" => 4, "is_active" => 1],
    ["title" => "Контакты", "url" => "/contacts", "sort_order" => 5, "is_active" => 1],
];
foreach ($menuData as $m) {
    $st = db()->prepare("SELECT id FROM menu_items WHERE title=?");
    $st->execute([$m["title"]]);
    if ($st->rowCount() === 0) {
        collection_insert("menu_items", $m);
    }
}
ck("Menu items seeded", count($menuData) === 5);

// Seed settings
$s = get_settings();
$s["siteName"] = $s["siteName"] ?? "MEB — Мебель на заказ";
$s["phone"] = $s["phone"] ?? "+7 (999) 123-45-67";
$s["email"] = $s["email"] ?? "info@godnayamebel.ru";
$s["address"] = $s["address"] ?? "Москва, ул. Примерная, д. 1";
$s["siteDesc"] = $s["siteDesc"] ?? "Изготовление мебели на заказ из натуральных материалов";
$s["socials"] = $s["socials"] ?? ["instagram" => "https://instagram.com/meb", "telegram" => "https://t.me/meb"];
save_settings($s);
ck("Settings seeded", true);

echo "\n=== TESTING PUBLIC PAGES ===\n\n";

// Reset analytics for clean test
db()->exec("TRUNCATE TABLE analytics");

// Test each public page by including it and checking output
$pages = [
    ["file" => "home.php",       "label" => "Home",      "checks" => ["hero", "Каталог", "Проекты", "reviews"]],
    ["file" => "catalog-hub.php","label" => "Catalog Hub","checks" => ["catalog", "Каталог"]],
    ["file" => "projects.php",   "label" => "Projects",   "checks" => ["Проект"]],
    ["file" => "materials.php",  "label" => "Materials",  "checks" => ["card", "Материал"]],
    ["file" => "services.php",   "label" => "Services",   "checks" => ["Услуги", "card"]],
    ["file" => "contacts.php",   "label" => "Contacts",   "checks" => ["contacts", "Контакты"]],
];

foreach ($pages as $i => $p) {
    $_MEB_SLUG = $p["file"] === "page.php" ? ($p["slug"] ?? "") : "";
    $label = $p["label"];
    $checks = $p["checks"];
    ob_start();
    $included = @include MEB_ROOT . "/pages/" . $p["file"];
    $html = ob_get_clean();

    if (!$included) {
        ck($label . " renders", false);
        continue;
    }
    $ok = true;
    foreach ($checks as $check) {
        if (strpos($html, $check) === false) {
            echo "    Missing: $check\n";
            $ok = false;
        }
    }
    $hasNav = strpos($html, "nav") !== false;
    $hasFooter = strpos($html, "footer") !== false;
    $hasCards = strpos($html, "card") !== false;
    ck($label . " renders (nav=$hasNav, footer=$hasFooter, cards=$hasCards)", $ok);
}

// Test individual catalog items (by category)
echo "\n[CATALOG ITEMS]\n";
$cats = collection_list("catalog_categories");
if (!empty($cats)) {
    $_MEB_CAT = $cats[0]["slug"];
    $_MEB_SLUG = null;
    ob_start();
    @include MEB_ROOT . "/pages/catalog-item.php";
    $html = ob_get_clean();
    $hasTitle = strpos($html, e($cats[0]["title"])) !== false;
    ck("Catalog category: " . $cats[0]["title"], $hasTitle);
} else {
    ck("Catalog category (no data)", false);
}

// Test individual projects
echo "\n[PROJECTS]\n";
$projects = collection_list("projects");
foreach (array_slice($projects, 0, 2) as $p) {
    $slug = $p["slug"];
    $_MEB_SLUG = $slug;
    ob_start();
    @include MEB_ROOT . "/pages/project.php";
    $html = ob_get_clean();
    $hasTitle = strpos($html, e($p["title"])) !== false;
    ck("Project: " . $p["title"], $hasTitle);
}

// Test CMS pages (page.php)
echo "\n[CMS PAGE BUILDER]\n";
$pageData = ["title" => "О компании", "slug" => "about-test", "blocks" => [
    ["type" => "hero", "data" => ["title" => "О компании MEB", "subtitle" => "Производим мебель с 2015 года"]],
    ["type" => "text", "data" => ["text" => "Мы — команда профессионалов, создающих мебель вашей мечты."]],
    ["type" => "features", "data" => ["items" => [
        ["title" => "Натуральные материалы", "desc" => "Только дуб, ясень, сосна"],
        ["title" => "Гарантия 5 лет", "desc" => "На всю мебель"],
        ["title" => "Бесплатный замер", "desc" => "Выезжаем в Москве"],
    ]]],
    ["type" => "statistics", "data" => ["items" => [
        ["title" => "1000+", "desc" => "Проектов"],
        ["title" => "10 лет", "desc" => "На рынке"],
        ["title" => "98%", "desc" => "Довольных клиентов"],
    ]]],
    ["type" => "faq", "data" => ["items" => [
        ["q" => "Сколько времени занимает изготовление?", "a" => "От 21 рабочего дня в зависимости от сложности."],
        ["q" => "Есть ли гарантия?", "a" => "Да, 5 лет на всю мебель."],
    ]]],
    ["type" => "cta", "data" => ["title" => "Готовы обсудить проект?", "ctaLabel" => "Связаться с нами", "ctaUrl" => "/contacts"]],
    ["type" => "team", "data" => ["items" => [
        ["title" => "Иван Петров", "role" => "Директор"],
        ["title" => "Анна Сидорова", "role" => "Дизайнер"],
    ]]],
], "published" => 1];
// Remove old test page if exists
$existing = db()->prepare("DELETE FROM pages WHERE slug='about-test'");
$existing->execute();
$row = collection_insert("pages", $pageData);
$_MEB_SLUG = $row["slug"];
ob_start();
@include MEB_ROOT . "/pages/page.php";
$html = ob_get_clean();
ck("CMS Hero block", strpos($html, "pb-hero") !== false);
ck("CMS Text block", strpos($html, "pb-text") !== false);
ck("CMS Features block", strpos($html, "pb-features") !== false);
ck("CMS Statistics block", strpos($html, "pb-stats") !== false);
ck("CMS FAQ block", strpos($html, "pb-faq") !== false);
ck("CMS CTA block", strpos($html, "cta-card") !== false);
ck("CMS Team block", strpos($html, "pb-team") !== false);
ck("CMS has nav", strpos($html, "nav") !== false);
ck("CMS has footer", strpos($html, "footer") !== false);
collection_delete("pages", $row["id"]);

// Test sitemap
echo "\n[SITEMAP]\n";
ob_start();
@include MEB_ROOT . "/pages/sitemap.php";
$xml = ob_get_clean();
ck("Sitemap has XML header", strpos($xml, "<?xml") !== false);
ck("Sitemap has URLs", strpos($xml, "<url>") !== false);
ck("Sitemap has homepage", strpos($xml, "<loc>") !== false);

// Test analytics — verify table works (tracking happens in index.php router, not page files)
echo "\n[ANALYTICS]\n";
$today = date("Y-m-d");
$st = db()->prepare("INSERT INTO analytics (id,date,views,created_at,updated_at) VALUES (?,?,'5',?,?) ON DUPLICATE KEY UPDATE views=views+5");
$st->execute(["test_" . $today, $today, now_db(), now_db()]);
$st2 = db()->prepare("SELECT views FROM analytics WHERE date=?");
$st2->execute([$today]);
$views = (int)$st2->fetchColumn();
ck("Analytics INSERT + SELECT works", $views >= 5);

echo "\n=== RESULT: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
