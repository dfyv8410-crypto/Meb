<?php
/** Home page — quiet-luxury editorial studio layout. */

require_once __DIR__ . '/layout.php';

$s = site_settings();
$projects  = public_rows('projects');
$catalog   = public_rows('catalog');
$materials = collection_list('materials');
$reviews   = array_values(array_filter(collection_list('reviews'), function ($r) {
    // approved is TINYINT 0/1 — only 1 counts as published. The old
    // `($r['approved'] ?? true) !== false` check rendered NULL/dangling rows
    // (and int 0) as approved.
    return (int) ($r['approved'] ?? 0) === 1;
}));
$categories_list = collection_list('catalog_categories');
$categories_map = [];
$categories_title = [];
foreach ($categories_list as $cl) {
    $categories_map[$cl['id']] = $cl['slug'];
    $categories_title[$cl['id']] = $cl['title'];
}

usort($projects, function ($a, $b) {
    return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
});
$featured_projects = array_slice($projects, 0, 6);

$featured_catalog = array_slice($catalog, 0, 6);

function card(array $row, string $url, array $fields): void {
    $img = first_image($row);
    $meta = $fields[0];
    echo '<a href="' . e($url) . '" class="card card-clean reveal img-reveal">';
    echo '<div class="card-img-wrap"><img src="' . e($img) . '" alt="' . e($row['title'] ?? '') . '" loading="lazy"></div>';
    echo '<div class="card-body">';
    echo '<div class="eyebrow">' . e($meta) . '</div>';
    echo '<div class="card-title">' . e($row['title'] ?? 'Без названия') . '</div>';
    if (!empty($row['description'])) echo '<div class="card-desc">' . e(mb_strimwidth($row['description'], 0, 100, '...')) . '</div>';
    echo '</div></a>';
}

layout_head('', '');
layout_nav('home');
?>
<main id="main-content">

<!-- 01 HERO — editorial stage (slider preserved, no CTA buttons) -->
<section class="hero-slider" id="heroSlider" aria-label="Главная презентация">
  <h1 class="vh">ГОДНАЯ МЕБЕЛЬ — премиальная мебель на заказ</h1>
  <div class="hero-gl" aria-hidden="true"><i></i><i></i><i></i></div>
  <div class="hero-slider-track" id="heroTrack"></div>
  <span class="hero-index" id="heroIndex" aria-hidden="true">01</span>
  <button class="hero-arrow prev" aria-label="Предыдущий слайд" id="heroPrev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></button>
  <button class="hero-arrow next" aria-label="Следующий слайд" id="heroNext"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
  <div class="hero-pagination" id="heroPagination"></div>
</section>

<!-- 02 STUDIO — statement + values -->
<section class="container section">
  <div class="section-header reveal">
    <span class="kicker kicker-num">01 / Студия</span>
    <p class="statement">Мебель, которая<br>становится частью<br>вашего дома.</p>
  </div>
  <div class="value reveal">
    <span class="value-num">01</span>
    <h3 class="value-title">Производство</h3>
    <p class="value-desc">Современное оборудование и ручная доводка. Каждая деталь проходит строгий контроль.</p>
  </div>
  <div class="value reveal">
    <span class="value-num">02</span>
    <h3 class="value-title">Дизайн</h3>
    <p class="value-desc">От первого эскиза до установки — мы проектируем под ваше пространство.</p>
  </div>
  <div class="value reveal">
    <span class="value-num">03</span>
    <h3 class="value-title">Материалы</h3>
    <p class="value-desc">Массив, камень, латунь, стекло. Которые стареют красиво.</p>
  </div>
</section>

<!-- 03 COLLECTION — editorial grid -->
<?php if ($featured_catalog): ?>
<section id="catalog" class="container section">
  <div class="section-header row reveal">
    <div class="section-header-text">
      <span class="kicker kicker-num">02 / Коллекция</span>
      <h2 class="h2">Предметы, которые не требуют объяснений.</h2>
    </div>
    <div>
      <a class="link-arrow" href="/catalog">Весь каталог</a>
    </div>
  </div>
  <div class="grid-editorial">
    <?php foreach ($featured_catalog as $c) card($c, '/catalog/' . e(($categories_map[$c['category_id'] ?? ''] ?? 'catalog') . '/' . ($c['slug'] ?? $c['id'])), [$categories_title[$c['category_id'] ?? ''] ?? 'Изделие']); ?>
  </div>
</section>
<?php endif; ?>

<!-- 04 PROCESS — architectural line -->
<section class="section-alt" id="process">
  <div class="container" style="padding-block:clamp(72px,8vw,120px)">
    <div class="section-header reveal">
      <span class="kicker kicker-num">03 / Процесс</span>
      <h2 class="h2">От идеи до установки</h2>
    </div>
    <div class="process">
      <div class="process-step reveal">
        <span class="process-num">01</span>
        <div class="process-title">Замер</div>
        <p class="process-desc">Выезд инженера и точные размеры пространства.</p>
      </div>
      <div class="process-step reveal">
        <span class="process-num">02</span>
        <div class="process-title">Проектирование</div>
        <p class="process-desc">3D-проект и смета в течение 48 часов.</p>
      </div>
      <div class="process-step reveal">
        <span class="process-num">03</span>
        <div class="process-title">Производство</div>
        <p class="process-desc">Изготовление на собственном производстве.</p>
      </div>
      <div class="process-step reveal">
        <span class="process-num">04</span>
        <div class="process-title">Монтаж</div>
        <p class="process-desc">Доставка и установка с защитой помещения.</p>
      </div>
      <div class="process-step reveal">
        <span class="process-num">05</span>
        <div class="process-title">Сервис</div>
        <p class="process-desc">Гарантия и сервисное обслуживание.</p>
      </div>
    </div>
  </div>
</section>

<!-- 05 PORTFOLIO -->
<?php if ($featured_projects): ?>
<section id="projects" class="container section">
  <div class="section-header row reveal">
    <div class="section-header-text">
      <span class="kicker kicker-num">04 / Портфолио</span>
      <h2 class="h2">Избранные проекты</h2>
      <p class="sub">Каждый — под конкретный интерьер. Без типовых решений.</p>
    </div>
    <div>
      <a class="link-arrow" href="/projects">Все проекты</a>
    </div>
  </div>
  <div class="masonry">
    <?php foreach ($featured_projects as $p) card($p, '/project/' . e($p['slug'] ?? $p['id']), [$p['category'] ?? 'Проект']); ?>
  </div>
</section>
<?php endif; ?>

<!-- 06 MATERIALS -->
<?php if ($materials): ?>
<section class="section-alt" id="materials">
  <div class="container" style="padding-block:clamp(72px,8vw,120px)">
    <div class="section-header row reveal">
      <div class="section-header-text">
        <span class="kicker kicker-num">05 / Материалы</span>
        <h2 class="h2">Честные материалы</h2>
        <p class="sub">Которые стареют красиво.</p>
      </div>
      <div>
        <a class="link-arrow" href="/materials">Все материалы</a>
      </div>
    </div>
  <div class="h-scroll">
    <?php foreach (array_slice($materials, 0, 8) as $m): ?>
      <a href="/materials#m-<?= e($m['id']) ?>" class="card card-clean reveal">
        <div class="card-img-wrap"><img src="<?= e(first_image($m)) ?>" alt="<?= e($m['title'] ?? '') ?>" loading="lazy"></div>
        <div class="card-body">
          <div class="eyebrow"><?= e($m['type'] ?? 'Материал') ?></div>
          <div class="card-title"><?= e($m['title'] ?? 'Без названия') ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  </div>
</section>
<?php endif; ?>

<!-- 07 REVIEWS — editorial testimonial -->
<?php if ($reviews): ?>
<section id="reviews" class="container section">
  <div class="section-header reveal">
    <span class="kicker kicker-num">06 / Отзывы</span>
    <h2 class="h2">Что говорят клиенты</h2>
  </div>
  <div class="grid cols2">
    <?php foreach (array_slice($reviews, 0, 6) as $r): ?>
      <div class="card review-card reveal">
        <div class="review-stars" aria-label="Оценка <?= (int) ($r['rating'] ?? 5) ?> из 5"><?= str_repeat('★', (int) ($r['rating'] ?? 5)) ?></div>
        <div class="review-text"><?= e($r['text'] ?? $r['review'] ?? '') ?></div>
        <div class="review-author"><?= e($r['author'] ?: 'Клиент') ?><?= !empty($r['role']) ? ' · ' . e($r['role']) : '' ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- 08 CTA -->
<section class="container section">
  <div class="cta-card reveal">
    <div>
      <span class="kicker">Обсудить проект</span>
      <h2 class="h2">Приедем, замерим,<br>спроектируем</h2>
      <p class="sub">Замер · 3D · смета за 48 часов · монтаж с защитой помещения</p>
      <div class="form-row">
        <a class="btn btn-light" href="/contacts">Обсудить проект</a>
      </div>
    </div>
    <ul class="cta-features">
      <li>Авторский надзор</li>
      <li>Доставка и монтаж</li>
      <li>Гарантия и сервис</li>
    </ul>
  </div>
</section>

</main>
<?php layout_footer(); ?>