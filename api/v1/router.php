<?php
/**
 * MEB REST API v1 — single front controller.
 * All routes: /api/v1/<path>, rewritten here via .htaccess.
 *
 * @package MEB
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/router.php';

security_headers();

$METHOD = $_SERVER['REQUEST_METHOD'];
$uri    = route_uri();
$path   = preg_replace('#^/api/v1/?#', '', $uri);
$seg    = array_values(array_filter(explode('/', (string) $path)));

// Auth
if (($seg[0] ?? '') === 'auth') {
    require __DIR__ . '/auth.php';
    handle_auth($METHOD, array_slice($seg, 1));
}

// /api/v1/me — current user (alias of auth/me, matches SPA)
if (($seg[0] ?? '') === 'me') {
    require __DIR__ . '/auth.php';
    handle_auth('GET', ['me']);
}

// Health (public)
if (($seg[0] ?? '') === 'health') {
    require __DIR__ . '/system.php';
    handle_health();
}

// Audit log (admin+)
if (($seg[0] ?? '') === 'audit_log') {
    require __DIR__ . '/system.php';
    handle_audit_log();
}

// Users
if (($seg[0] ?? '') === 'users') {
    require __DIR__ . '/users.php';
    handle_users($METHOD, $seg[1] ?? null);
}

// Media
if (($seg[0] ?? '') === 'media') {
    require __DIR__ . '/media.php';
    handle_media($METHOD, array_slice($seg, 1));
}

// Settings
if ($path === 'settings') {
    require __DIR__ . '/settings.php';
    handle_settings($METHOD);
}

// Leads (CRM + public create)
if (($seg[0] ?? '') === 'leads') {
    require __DIR__ . '/leads.php';
    handle_leads($METHOD, array_slice($seg, 1));
}
if (($seg[0] ?? '') === 'leads-public') {
    require __DIR__ . '/leads.php';
    handle_lead_public();
}

// Reviews public submission
if (($seg[0] ?? '') === 'reviews-public') {
    require __DIR__ . '/reviews-public.php';
    handle_review_public();
}

// Notifications
if (($seg[0] ?? '') === 'notifications') {
    require __DIR__ . '/notifications.php';
    handle_notifications($METHOD, array_slice($seg, 1));
}

// Analytics
if (($seg[0] ?? '') === 'analytics') {
    require __DIR__ . '/analytics.php';
    handle_analytics();
}

// SEO (sitemap / audit)
if (($seg[0] ?? '') === 'seo') {
    require __DIR__ . '/seo.php';
    handle_seo($seg[1] ?? '');
}

// Backup
if (($seg[0] ?? '') === 'backup') {
    require __DIR__ . '/backup.php';
    handle_backup($METHOD, array_slice($seg, 1));
}

// Diagnostics (admin+): full System Health with real checks
if (($seg[0] ?? '') === 'system' && ($seg[1] ?? '') === 'diagnostics') {
    require __DIR__ . '/system.php'; // health_dir_probe()
    require __DIR__ . '/diagnostics.php';
    handle_diagnostics();
}

// System (update check/run)
if (($seg[0] ?? '') === 'system') {
    require __DIR__ . '/system.php';
    handle_system($METHOD, array_slice($seg, 1));
}

// App download
if (($seg[0] ?? '') === 'app') {
    require __DIR__ . '/app.php';
    handle_app($seg[1] ?? '');
}

// Menu items (dynamic navigation)
if (($seg[0] ?? '') === 'menu') {
    require __DIR__ . '/menu.php';
    handle_menu($METHOD, array_slice($seg, 1));
}

// Banners — public endpoint (active banners for slider)
if (($seg[0] ?? '') === 'banners-public') {
    require __DIR__ . '/banners.php';
    handle_banners_public();
}

// Watermark — admin (live preview only, no writes)
if (($seg[0] ?? '') === 'watermark') {
    require __DIR__ . '/watermark.php';
    handle_watermark($METHOD, array_slice($seg, 1));
}

// Install
if (($seg[0] ?? '') === 'install') {
    require __DIR__ . '/install.php';
    handle_install($METHOD);
}

// Generic CRUD collections
$colTable = [
    'pages'      => ['table' => 'pages',             'role' => 'editor',  'entity' => 'pages'],
    'categories' => ['table' => 'catalog_categories', 'role' => 'editor', 'entity' => 'categories'],
    'catalog'    => ['table' => 'catalog',           'role' => 'editor',  'entity' => 'catalog'],
    'projects'   => ['table' => 'projects',          'role' => 'editor',  'entity' => 'projects'],
    'materials'  => ['table' => 'materials',         'role' => 'editor',  'entity' => 'materials'],
    'services'   => ['table' => 'services',          'role' => 'editor',  'entity' => 'services'],
    'reviews'    => ['table' => 'reviews',           'role' => 'editor',  'entity' => 'reviews'],
    'menu_items' => ['table' => 'menu_items',        'role' => 'editor',  'entity' => 'menu_items'],
    'banners'    => ['table' => 'banners',           'role' => 'editor',  'entity' => 'banners'],
];
if (isset($colTable[$seg[0] ?? ''])) {
    require __DIR__ . '/crud.php';
    handle_crud($METHOD, $colTable[$seg[0]], $seg);
}

fail('Not found', 404);
