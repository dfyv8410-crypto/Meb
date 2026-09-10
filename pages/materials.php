<?php
/** Materials listing. */

require_once __DIR__ . '/layout.php';

$materials = collection_list('materials');

layout_head('Материалы', 'Честные материалы, которые стареют красиво.');
layout_nav('materials');
?>
<main id="main-content">
<section class="container section" style="padding-top:clamp(32px,6vw,64px)">
  <div class="section-header reveal">
    <span class="kicker">Материалы</span>
    <h1 class="h2">Честные материалы</h1>
    <div class="section-divider"></div>
    <p class="sub">Которые стареют красиво.</p>
  </div>
  <div class="grid cols3">
    <?php
      if (!$materials) echo '<div class="empty-state"><div class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="40" height="40"><rect x="4" y="4" width="16" height="16"/><path d="M4 10h16M10 4v16"/></svg></div><p>Материалы скоро появятся</p></div>';
      foreach ($materials as $m) {
        echo '<a href="/materials#m-' . e($m['id']) . '" class="card card-clean reveal img-reveal">';
        echo '<div class="card-img-wrap">' . meb_pic(first_image($m), (string) ($m['title'] ?? ''), ['w' => 640, 'h' => 480, 'fit' => 'cover', 'q' => 80, 'sizes' => '(min-width:1081px) 300px, (min-width:721px) 33vw, calc(100vw - 40px)']) . '</div>';
        echo '<div class="card-body"><div class="eyebrow">' . e($m['type'] ?? 'Материал') . '</div>';
        echo '<div class="card-title">' . e($m['title'] ?? 'Без названия') . '</div></div></a>';
      }
    ?>
  </div>

  <?php foreach ($materials as $m): ?>
  <div class="card" style="padding:var(--sp-6);margin-top:var(--sp-5)" id="m-<?= e($m['id']) ?>">
    <div class="eyebrow"><?= e($m['type'] ?? 'Материал') ?></div>
    <div class="card-title" style="font-size:1.25rem;margin-top:var(--sp-2)"><?= e($m['title'] ?? '') ?></div>
    <?php if (!empty($m['description'])) echo '<p class="card-desc" style="margin-top:var(--sp-2)">' . e($m['description']) . '</p>'; ?>
    <?php if (!empty($m['props']) && is_array($m['props'])): ?>
      <ul class="material-props" style="margin-top:var(--sp-4)">
        <?php foreach ($m['props'] as $k => $v) echo '<li><b style="color:var(--ink);font-weight:600">' . e((string) $k) . ':</b> ' . e(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v) . '</li>'; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>
</main>
<?php layout_footer(); ?>
