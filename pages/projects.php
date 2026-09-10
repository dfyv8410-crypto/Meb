<?php
/** Projects listing — portfolio grid. */

require_once __DIR__ . '/layout.php';

$projects = public_rows('projects');
usort($projects, function ($a, $b) { return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''); });

layout_head('Проекты', 'Каждый — под конкретный интерьер. Без типовых решений.');
layout_nav('projects');
?>
<main id="main-content">
<section class="container section" style="padding-top:clamp(32px,6vw,64px)">
  <div class="section-header reveal">
    <span class="kicker">Портфолио</span>
    <h1 class="h2">Проекты</h1>
    <div class="section-divider"></div>
    <p class="sub">Каждый — под конкретный интерьер. Без типовых решений.</p>
  </div>
  <div class="masonry">
    <?php
      if (!$projects) echo '<div class="empty-state"><div class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="40" height="40"><rect x="4" y="4" width="16" height="16"/><path d="M4 10h16M10 4v16"/></svg></div><p>Проекты скоро появятся</p></div>';
      foreach ($projects as $p) {
        $img = first_image($p);
        $href = '/project/' . e($p['slug'] ?? $p['id']);
        echo '<a href="' . $href . '" class="card card-clean reveal img-reveal">';
        echo '<div class="card-img-wrap">' . meb_pic($img, (string) ($p['title'] ?? ''), [
          'w' => 960, 'h' => 720, 'fit' => 'cover', 'q' => 80,
          'sizes' => '(min-width:1081px) 33vw, (min-width:721px) 50vw, calc(100vw - 40px)',
      ]) . '</div>';
        echo '<div class="card-body"><div class="eyebrow">' . e($p['category'] ?? 'Проект') . '</div>';
        echo '<div class="card-title">' . e($p['title'] ?? 'Без названия') . '</div>';
        if (!empty($p['description'])) echo '<div class="card-desc">' . e(mb_strimwidth($p['description'], 0, 100, '...')) . '</div>';
        echo '</div></a>';
      }
    ?>
  </div>
</section>
</main>
<?php layout_footer(); ?>
