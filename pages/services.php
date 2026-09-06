<?php
/** Services listing. */

require_once __DIR__ . '/layout.php';

$services = collection_list('services');

layout_head('Услуги', 'Замер, 3D-проект, производство, доставка и монтаж.');
layout_nav('services');
?>
<main id="main-content">
<section class="container section" style="padding-top:clamp(32px,6vw,64px)">
  <div class="section-header reveal">
    <span class="kicker">Услуги</span>
    <h1 class="h2">Полный цикл</h1>
    <div class="section-divider"></div>
    <p class="sub">Замер · 3D · смета за 48 часов · монтаж с защитой помещения.</p>
  </div>
  <div class="grid cols2">
    <?php
      if (!$services) echo '<div class="empty-state"><div class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="40" height="40"><rect x="4" y="4" width="16" height="16"/><path d="M4 10h16M10 4v16"/></svg></div><p>Услуги скоро появятся</p></div>';
      foreach ($services as $s) {
        echo '<div class="card reveal" style="padding:var(--sp-6)">';
        echo '<span class="kicker">' . e($s['category'] ?? 'Услуга') . '</span>';
        echo '<div class="card-title" style="font-size:1.25rem;margin-top:var(--sp-2)">' . e($s['title'] ?? 'Без названия') . '</div>';
        if (!empty($s['description'])) echo '<p class="card-desc" style="margin-top:var(--sp-3)">' . e($s['description']) . '</p>';
        if (!empty($s['price_from'])) echo '<div class="card-price">от ' . e((string) $s['price_from']) . ' ₽</div>';
        echo '</div>';
      }
    ?>
  </div>
  <div style="margin-top:var(--sp-8)"><a class="btn" href="/contacts">Оставить заявку</a></div>
</section>
</main>
<?php layout_footer(); ?>
