<?php
/** Catalog hub — lists categories and all catalog items. */

require_once __DIR__ . '/layout.php';

$categories = collection_list('catalog_categories');
$catalog    = public_rows('catalog');

layout_head('Каталог', 'Кухни, гардеробные, гостиные, спальни, кабинеты и решения для бизнеса.');
layout_nav('catalog');
?>
<main id="main-content">
<section class="container section" style="padding-top:clamp(32px,6vw,64px)">
  <div class="section-header reveal">
    <span class="kicker">Каталог</span>
    <h1 class="h2">Коллекция</h1>
    <div class="section-divider"></div>
    <p class="sub">Кухни, гардеробные, гостиные, спальни, кабинеты и решения для бизнеса.</p>
  </div>

  <?php if ($categories): ?>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:var(--sp-8)" class="reveal">
    <?php foreach ($categories as $cat): ?>
      <a class="badge" href="/catalog/<?= e($cat['slug']) ?>"><?= e($cat['title'] ?? $cat['slug']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="grid-editorial">
    <?php
      if (!$catalog) { echo '<div class="empty-state"><div class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="40" height="40"><rect x="4" y="4" width="16" height="16"/><path d="M4 10h16M10 4v16"/></svg></div><p>Каталог наполняется</p></div>'; }
      foreach ($catalog as $c) {
          $catSlug = 'catalog';
          $catTitle = 'Изделие';
          foreach ($categories as $cat) {
              if (($cat['id'] ?? '') === ($c['category_id'] ?? '')) {
                  $catSlug = $cat['slug'];
                  $catTitle = $cat['title'] ?? $cat['slug'];
                  break;
              }
          }
          $href = '/catalog/' . e($catSlug) . '/' . e($c['slug'] ?? $c['id']);
          echo '<a href="' . $href . '" class="card card-clean reveal img-reveal">';
          echo '<div class="card-img-wrap"><img src="' . e(first_image($c)) . '" alt="' . e($c['title'] ?? '') . '" loading="lazy"></div>';
          echo '<div class="card-body"><div class="eyebrow">' . e($catTitle) . '</div>';
          echo '<div class="card-title">' . e($c['title'] ?? 'Без названия') . '</div>';
          if (!empty($c['description'])) echo '<div class="card-desc">' . e(mb_strimwidth($c['description'], 0, 100, '...')) . '</div>';
          echo '</div></a>';
      }
    ?>
  </div>
</section>
</main>
<?php layout_footer(); ?>
