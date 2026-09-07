<?php
/** /project/:slug — single project detail. */

require_once __DIR__ . '/layout.php';

$slug = $_MEB_SLUG;
$project = null;
foreach (public_rows('projects') as $p) {
    if (($p['slug'] ?? '') === $slug) { $project = $p; break; }
}

if (!$project) {
    http_response_code(404);
    layout_head('Проект не найден', '');
    layout_nav('projects');
    echo '<main id="main-content"><div class="container section" style="padding-top:clamp(32px,6vw,64px)"><h1 class="h2">Проект не найден</h1><a class="btn btn-ghost" href="/projects">← Все проекты</a></div></main>';
    layout_footer(); exit;
}

$imgs = first_image($project);
if (is_array($project['images'] ?? null) && count($project['images']) > 1) {
    $gallery = $project['images'];
} else {
    $gallery = [$imgs];
}

layout_head($project['title'] ?? 'Проект', $project['description'] ?? '');
layout_nav('projects');
?>
<main id="main-content">
<article class="container section" style="padding-top:clamp(32px,6vw,64px)">
  <div class="breadcrumb"><a href="/projects">Проекты</a> / <?= e($project['category'] ?? 'Проект') ?> · <?= e($project['title'] ?? '') ?></div>

  <div class="section-header reveal">
    <span class="kicker"><?= e($project['category'] ?? 'Проект') ?></span>
    <h1 class="h2"><?= e($project['title'] ?? '') ?></h1>
    <?php if (!empty($project['description'])): ?>
    <p class="sub"><?= e(mb_strimwidth($project['description'], 0, 160, '...')) ?></p>
    <?php endif; ?>
  </div>

  <div class="grid cols2" style="margin-top:var(--sp-6)">
    <?php foreach ($gallery as $g): $src = is_array($g) ? ($g['url'] ?? '') : $g;
      if ($src === '' || $src === '/assets/img/placeholder.svg') continue; ?>
      <div class="img-reveal">
        <?= meb_pic($src, (string) ($project['title'] ?? ''), [
            'w' => 960, 'h' => 540, 'fit' => 'cover', 'q' => 80,
            'class' => 'g-img', 'sizes' => '(min-width:721px) 50vw, 100vw',
        ]) ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($project['description'])) echo '<div class="detail-content" style="margin-top:var(--sp-8)">' . nl2br(e($project['description'])) . '</div>'; ?>

  <?php if (!empty($project['features']) && is_array($project['features'])): ?>
    <h2 class="h3" style="margin-top:var(--sp-8)">Особенности</h2>
    <ul style="line-height:2;padding-left:0;margin-top:var(--sp-4)"><?php foreach ($project['features'] as $f) echo '<li style="padding:6px 0;border-bottom:1px solid var(--line-faint);font-size:var(--fs-sm)">' . e(is_array($f) ? ($f['text'] ?? json_encode($f, JSON_UNESCAPED_UNICODE)) : (string) $f) . '</li>'; ?></ul>
  <?php endif; ?>

  <?php if (!empty($project['materials']) && is_array($project['materials'])): ?>
    <h2 class="h3" style="margin-top:var(--sp-8)">Материалы</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:var(--sp-4)"><?php foreach ($project['materials'] as $f) echo '<span class="badge">' . e(is_array($f) ? json_encode($f, JSON_UNESCAPED_UNICODE) : (string) $f) . '</span>'; ?></div>
  <?php endif; ?>

  <div style="margin-top:var(--sp-8)"><a class="btn" href="/contacts">Хочу похожий проект</a></div>
</article>
</main>
<?php layout_footer(); ?>
