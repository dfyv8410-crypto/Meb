<?php
/** Dynamic sitemap.xml built from MySQL collections (absolute URLs only). */

require_once __DIR__ . '/layout.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
// Absolute origin: configured base_url, else scheme-prefixed HTTP_HOST.
// Empty MEB_BASE_URL used to produce relative <loc> entries (spec-invalid).
$base = meb_origin();
$categories = collection_list('catalog_categories');

$add = function (string $loc, string $changefreq = 'weekly', string $priority = '0.8') use ($base) {
    echo '<url><loc>' . e($base . $loc) . '</loc><changefreq>' . $changefreq . '</changefreq><priority>' . $priority . '</priority></url>' . "\n";
};
$add('/', 'daily', '1.0');
$add('/catalog', 'weekly', '0.9');
$add('/projects', 'weekly', '0.9');
$add('/materials', 'monthly', '0.7');
$add('/services', 'monthly', '0.7');
$add('/contacts', 'monthly', '0.6');
foreach ($categories as $c) $add('/catalog/' . e($c['slug'] ?? ''), 'weekly', '0.8');
foreach (public_rows('catalog') as $c) {
    $catSlug = 'catalog';
    foreach ($categories as $cat) {
        if (($cat['id'] ?? '') === ($c['category_id'] ?? '')) { $catSlug = $cat['slug']; break; }
    }
    $add('/catalog/' . $catSlug . '/' . e($c['slug'] ?? $c['id']), 'weekly', '0.8');
}
foreach (public_rows('projects') as $p) $add('/project/' . e($p['slug'] ?? $p['id']), 'monthly', '0.7');
foreach (public_rows('pages') as $p) $add('/p/' . e($p['slug'] ?? ''), 'monthly', '0.6');
echo '</urlset>' . "\n";
