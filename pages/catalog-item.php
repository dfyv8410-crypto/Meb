<?php
/** Catalog detail: /catalog/:cat (category) or /catalog/:cat/:slug (item). */

require_once __DIR__ . '/layout.php';

$cat  = $_MEB_CAT;
$slug = $_MEB_SLUG ?? null;

$categories = public_categories();
$catalog    = public_rows('catalog');

$catRow = null;
foreach ($categories as $c) {
    if ($c['slug'] === $cat) { $catRow = $c; break; }
}
if (!$catRow) {
    http_response_code(404);
    layout_head('Категория не найдена', '');
    layout_nav('catalog');
    echo '<main id="main-content"><div class="container section" style="padding-top:clamp(32px,6vw,64px)"><h1 class="h2">Категория не найдена</h1><div class="section-divider"></div><a class="btn btn-ghost" href="/catalog">← В каталог</a></div></main>';
    layout_footer(); exit;
}

$items = array_values(array_filter($catalog, function ($c) use ($catRow) {
    return ($c['category_id'] ?? null) === ($catRow['id'] ?? '');
}));

$single = null;
if ($slug !== null) {
    foreach ($catalog as $c) {
        if (($c['slug'] ?? '') === $slug) { $single = $c; break; }
    }
}

if ($single) {
    layout_head($single['title'] ?? 'Изделие', $single['description'] ?? '');
    layout_nav('catalog');
    echo '<main id="main-content"><article class="container section" style="padding-top:clamp(32px,6vw,64px)">';
    echo '<div class="breadcrumb"><a href="/catalog">Каталог</a> / <a href="/catalog/' . e($cat) . '">' . e($catRow['title'] ?? $cat) . '</a> / ' . e($single['title'] ?? '') . '</div>';
    echo '<h1 class="h2" style="margin:var(--sp-3) 0 var(--sp-6)">' . e($single['title'] ?? '') . '</h1>';
    echo meb_pic(first_image($single), (string) ($single['title'] ?? ''), ['class' => 'detail-hero', 'w' => 1600, 'h' => 900, 'fit' => 'cover', 'q' => 82, 'sizes' => '100vw']);
    if (!empty($single['description'])) echo '<div class="detail-content" style="margin-top:var(--sp-8)">' . nl2br(e($single['description'])) . '</div>';
    if (!empty($single['specs']) && is_array($single['specs'])) {
        echo '<h2 class="h3" style="margin-top:var(--sp-8)">Характеристики</h2><ul class="spec-list">';
        foreach ($single['specs'] as $k => $v) echo '<li><b>' . e((string) $k) . '</b><span>' . e(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v) . '</span></li>';
        echo '</ul>';
    }
    echo '<div style="margin-top:var(--sp-8);display:flex;gap:var(--sp-3)"><a class="btn" href="/contacts">Обсудить заказ</a> <a class="btn btn-ghost" href="/catalog/' . e($cat) . '">← В категорию</a></div>';
    echo '</article></main>';
    layout_footer(); exit;
}

layout_head(($catRow['title'] ?? $cat) . ' — Каталог', $catRow['description'] ?? '');
layout_nav('catalog');
echo '<main id="main-content"><section class="container section" style="padding-top:clamp(32px,6vw,64px)">';
echo '<div class="breadcrumb"><a href="/catalog">Каталог</a> / ' . e($catRow['title'] ?? $cat) . '</div>';
echo '<div class="section-header reveal">';
echo '<span class="kicker">' . e($catRow['title'] ?? $cat) . '</span>';
echo '<h1 class="h2">' . e($catRow['title'] ?? $cat) . '</h1>';
echo '<div class="section-divider"></div>';
if (!empty($catRow['description'])) echo '<p class="sub">' . e($catRow['description']) . '</p>';
echo '</div>';
echo '<div class="grid cols3">';
if (!$items) echo '<div class="empty-state"><div class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="40" height="40"><rect x="4" y="4" width="16" height="16"/><path d="M4 10h16M10 4v16"/></svg></div><p>В категории пока пусто</p></div>';
foreach ($items as $c) {
    echo '<a href="/catalog/' . e($cat) . '/' . e($c['slug'] ?? $c['id']) . '" class="card card-clean reveal img-reveal">';
    echo '<div class="card-img-wrap">' . meb_pic(first_image($c), (string) ($c['title'] ?? ''), ['w' => 640, 'h' => 480, 'fit' => 'cover', 'q' => 80, 'sizes' => '(min-width:1081px) 300px, (min-width:721px) 33vw, 100vw']) . '</div>';
    echo '<div class="card-body"><div class="card-title">' . e($c['title'] ?? 'Без названия') . '</div>';
    if (!empty($c['description'])) echo '<div class="card-desc">' . e(mb_strimwidth($c['description'], 0, 100, '...')) . '</div>';
    echo '</div></a>';
}
echo '</div></section></main>';
layout_footer();
