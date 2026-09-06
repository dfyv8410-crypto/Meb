<?php
/** /p/:slug — generic CMS page with full block rendering. */

require_once __DIR__ . '/layout.php';

$slug = $_MEB_SLUG;
$page = null;
// public_rows filters published=1 — drafts 404 instead of leaking publicly.
foreach (public_rows('pages') as $p) {
    if (($p['slug'] ?? '') === $slug) { $page = $p; break; }
}

if (!$page) {
    http_response_code(404);
    layout_head('Страница не найдена', '');
    echo '<main id="main-content"><div class="container section" style="padding-top:clamp(32px,6vw,64px)"><h1 class="h2">Страница не найдена</h1><div class="section-divider"></div><a class="btn btn-ghost" href="/">← Главная</a></div></main>';
    layout_footer(); exit;
}

$seoTitle = $page['seo_title'] ?? $page['title'] ?? '';
$seoDesc  = $page['seo_desc'] ?? $page['description'] ?? '';
layout_head($page['h1'] ?: ($page['title'] ?? $page['slug'] ?? 'Страница'), $seoDesc);
layout_nav();

if (!function_exists('render_block')) {
function render_block(array $b): void {
    $d = $b['data'] ?? [];
    $hidden = !empty($b['hidden']) ? ' style="display:none"' : '';

    switch ($b['type'] ?? '') {

    case 'hero':
        echo '<section class="pb-hero"' . $hidden . '>';
        if (!empty($d['title'])) echo '<h1>' . e($d['title']) . '</h1>';
        if (!empty($d['subtitle'])) echo '<p class="sub" style="max-width:600px;margin:12px auto 0">' . e($d['subtitle']) . '</p>';
        if (!empty($d['ctaLabel']) && !empty($d['ctaUrl'])) echo '<a class="btn btn-accent" href="' . e($d['ctaUrl']) . '" style="margin-top:24px">' . e($d['ctaLabel']) . '</a>';
        echo '</section>';
        break;

    case 'text':
        if (!empty($d['text'])) {
            echo '<section class="container section"' . $hidden . '><div class="pb-text">' . nl2br(e($d['text'])) . '</div></section>';
        }
        break;

    case 'features':
        if (!empty($d['items']) && is_array($d['items'])) {
            echo '<section class="container section"' . $hidden . '><div class="pb-features">';
            foreach ($d['items'] as $item) {
                echo '<div class="card pb-feature-card reveal">';
                if (!empty($item['kicker'])) echo '<span class="kicker">' . e($item['kicker']) . '</span>';
                if (!empty($item['title'])) echo '<h3>' . e($item['title']) . '</h3>';
                if (!empty($item['desc'])) echo '<p class="muted" style="font-size:var(--fs-sm)">' . e($item['desc']) . '</p>';
                echo '</div>';
            }
            echo '</div></section>';
        }
        break;

    case 'gallery':
        if (!empty($d['images']) && is_array($d['images'])) {
            echo '<section class="container section"' . $hidden . '><div class="grid cols3">';
            foreach ($d['images'] as $img) {
                $src = is_array($img) ? ($img['url'] ?? '') : (string) $img;
                if ($src) echo '<div class="img-reveal"><img src="' . e($src) . '" loading="lazy" style="width:100%;border-radius:var(--radius);aspect-ratio:4/3;object-fit:cover"></div>';
            }
            echo '</div></section>';
        }
        break;

    case 'statistics':
        if (!empty($d['items']) && is_array($d['items'])) {
            echo '<section class="container section"' . $hidden . '><div class="pb-stats">';
            foreach ($d['items'] as $item) {
                echo '<div class="card pb-stat reveal">';
                if (!empty($item['title'])) echo '<div class="pb-stat-value">' . e($item['title']) . '</div>';
                if (!empty($item['value']) || !empty($item['label'])) echo '<div class="pb-stat-label">' . e($item['value'] ?: $item['label'] ?? '') . '</div>';
                echo '</div>';
            }
            echo '</div></section>';
        }
        break;

    case 'faq':
        if (!empty($d['items']) && is_array($d['items'])) {
            echo '<section class="container section"' . $hidden . '><div class="pb-faq">';
            foreach ($d['items'] as $item) {
                echo '<div class="pb-faq-item">';
                if (!empty($item['q'])) echo '<div class="pb-faq-q">' . e($item['q']) . '</div>';
                if (!empty($item['a'])) echo '<div class="pb-faq-a">' . nl2br(e($item['a'])) . '</div>';
                echo '</div>';
            }
            echo '</div></section>';
        }
        break;

    case 'cta':
        echo '<section class="container section"' . $hidden . '>';
        echo '<div class="cta-card">';
        echo '<div>';
        if (!empty($d['title'])) echo '<span class="kicker" style="color:var(--brass)">Призыв</span><h2 class="h2" style="font-size:clamp(1.6rem,3vw,2.4rem)">' . e($d['title']) . '</h2>';
        if (!empty($d['subtitle'])) echo '<p class="sub" style="margin-top:12px">' . e($d['subtitle']) . '</p>';
        if (!empty($d['ctaLabel']) && !empty($d['ctaUrl'])) echo '<a class="btn" href="' . e($d['ctaUrl']) . '" style="margin-top:20px">' . e($d['ctaLabel']) . '</a>';
        echo '</div></div></section>';
        break;

    case 'team':
        if (!empty($d['items']) && is_array($d['items'])) {
            echo '<section class="container section"' . $hidden . '><div class="grid cols3">';
            foreach ($d['items'] as $item) {
                echo '<div class="card pb-team-card reveal">';
                if (!empty($item['title'])) echo '<b style="font-family:var(--ff-display);font-size:1.125rem">' . e($item['title']) . '</b>';
                if (!empty($item['role'])) echo '<div class="kicker" style="margin-top:var(--sp-2)">' . e($item['role']) . '</div>';
                if (!empty($item['desc'])) echo '<p class="muted" style="font-size:var(--fs-sm);margin-top:var(--sp-3)">' . e($item['desc']) . '</p>';
                echo '</div>';
            }
            echo '</div></section>';
        }
        break;

    case 'contact':
        echo '<section class="container section"' . $hidden . '>';
        echo '<div class="contact-grid">';
        echo '<form class="form card" style="padding:var(--sp-6)" onsubmit="submitLead(event)">';
        echo '<div><label class="form-label">Имя</label><input class="input" name="name" placeholder="Ваше имя" required></div>';
        echo '<div><label class="form-label">Телефон</label><input class="input" name="phone" placeholder="+7 (___) ___-__-__" required></div>';
        echo '<div><label class="form-label">Email</label><input class="input" name="email" placeholder="email@example.com" type="email"></div>';
        echo '<div><label class="form-label">Расскажите о проекте</label><textarea class="input" name="message" placeholder="Опишите ваш проект" rows="4"></textarea></div>';
        echo '<button class="btn btn-accent" type="submit">Отправить</button>';
        echo '<div id="leadMsg" class="muted" style="font-size:13px"></div>';
        echo '</form></div></section>';
        break;

    default:
        if (!empty($b['text'])) echo '<section class="container section"' . $hidden . '><div class="pb-text">' . nl2br(e($b['text'])) . '</div></section>';
        break;
    }
}
}
?>
<main id="main-content">
<?php if (!empty($page['seo_title'])): ?>
<meta property="og:title" content="<?= e($page['seo_title']) ?>">
<?php endif; ?>
<?php if (!empty($page['canonical'])): ?>
<link rel="canonical" href="<?= e($page['canonical']) ?>">
<?php endif; ?>

<?php if (!empty($page['blocks'])): ?>
  <?php $blocks = is_array($page['blocks']) ? $page['blocks'] : json_decode($page['blocks'], true); ?>
  <?php if (is_array($blocks)): foreach ($blocks as $b) render_block($b); endif; ?>
<?php elseif (!empty($page['content'])): ?>
  <section class="container section" style="padding-top:clamp(32px,6vw,64px)">
    <div class="detail-content"><?= nl2br(e($page['content'])) ?></div>
  </section>
<?php else: ?>
  <section class="container section" style="padding-top:clamp(32px,6vw,64px)">
    <h1 class="h2"><?= e($page['title'] ?? $page['slug'] ?? 'Страница') ?></h1>
  </section>
<?php endif; ?>
</main>
<?php layout_footer(); ?>
