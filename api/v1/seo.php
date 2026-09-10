<?php
/**
 * SEO endpoints: sitemap.xml (public) + audit (any authenticated).
 */

declare(strict_types=1);

function handle_seo(string $what): void
{
    require_once __DIR__ . '/crud.php';
    if ($what === 'sitemap.xml') {
        $base = MEB_BASE_URL !== '' ? rtrim(MEB_BASE_URL, '/') : site_base();
        $urls = ['/', '/projects', '/catalog', '/materials', '/services', '/contacts'];
        foreach (public_rows('pages') as $p) {
            $urls[] = '/p/' . rawurlencode($p['slug']);
        }
        foreach (public_rows('projects') as $p) {
            $urls[] = '/project/' . rawurlencode($p['slug']);
        }
        foreach (public_categories() as $c) {
            $urls[] = '/catalog/' . rawurlencode($c['slug']);
        }
        foreach (public_rows('catalog') as $i) {
            $cat = $i['category_id'] ?? '';
            if ($cat !== '') {
                $catRow = find_row('catalog_categories', $cat);
                if ($catRow) $urls[] = '/catalog/' . rawurlencode($catRow['slug']) . '/' . rawurlencode($i['slug']);
            }
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '  <url><loc>' . htmlspecialchars($base . $u, ENT_QUOTES) . '</loc></url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    // audit
    if ($what === 'audit') {
        $u = current_user();
        if (!$u) fail('Unauthorized', 401);
        $issues = [];
        $checked = 0;
        foreach (collection_list('pages') as $x) {
            if (empty($x['slug'])) $issues[] = ['where'=>'page:'.($x['title']??''),'problem'=>'нет slug','severity'=>'high'];
            $checked++;
        }
        foreach (collection_list('projects') as $x) {
            if (empty($x['slug'])) $issues[] = ['where'=>'project:'.($x['title']??''),'problem'=>'нет slug','severity'=>'high'];
            $checked++;
        }
        foreach (collection_list('catalog') as $x) {
            if (empty($x['slug'])) $issues[] = ['where'=>'catalog:'.($x['title']??''),'problem'=>'нет slug','severity'=>'high'];
            $checked++;
        }
        $score = 100;
        foreach ($issues as $i) {
            $score -= $i['severity'] === 'high' ? 8 : ($i['severity'] === 'medium' ? 4 : 2);
        }
        ok(['score' => max(0, $score), 'checked' => $checked, 'issues' => $issues]);
    }

    fail('Not found', 404);
}

/** Best-effort base URL for sitemap. */
function site_base(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}
