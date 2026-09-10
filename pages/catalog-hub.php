<?php
/** Catalog hub — lists categories and all catalog items. */

require_once __DIR__ . '/layout.php';

$categories = public_categories();
$catById = [];
foreach ($categories as $cat) $catById[(string) ($cat['id'] ?? '')] = $cat;
// Only items attached to an existing (active) category are listed publicly —
// otherwise the hub would emit links that 404.
$catalog = array_values(array_filter(public_rows('catalog'), function ($row) use ($catById) {
    return isset($catById[(string) ($row['category_id'] ?? '')]);
}));

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
  <div class="grid cols3" style="margin-bottom:var(--sp-12)">
    <?php foreach ($categories as $cat): ?>
      <?php
        $cover = first_image($cat);
        $slug  = (string) ($cat['slug'] ?? '');
        $title = (string) ($cat['title'] ?? $slug);
      ?>
      <a href="/catalog/<?= e($slug) ?>" class="card card-clean reveal img-reveal">
        <div class="card-img-wrap"><?= meb_pic($cover, $title, [
            'w' => 960, 'h' => 720, 'fit' => 'cover', 'q' => 80,
            'sizes' => '(min-width:1081px) 33vw, (min-width:721px) 50vw, 100vw',
        ]) ?></div>
        <div class="card-body">
          <div class="eyebrow">Коллекция</div>
          <div class="card-title"><?= e($title) ?></div>
          <?php if (!empty($cat['description'])): ?>
            <div class="card-desc"><?= e(mb_strimwidth($cat['description'], 0, 100, '...')) ?></div>
          <?php endif; ?>
        </div>
      </a>
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
          echo '<div class="card-img-wrap">' . meb_pic(first_image($c), (string) ($c['title'] ?? ''), [
              'w' => 960, 'h' => 720, 'fit' => 'cover', 'q' => 80,
              'sizes' => '(min-width:1081px) 33vw, (min-width:721px) 50vw, 100vw',
          ]) . '</div>';
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
