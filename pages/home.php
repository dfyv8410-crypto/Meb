<?php
/** Home page — premium furniture brand cover + rich showcase. */

require_once __DIR__ . '/layout.php';

$s = site_settings();
$projects  = public_rows('projects');
$catalog   = public_rows('catalog');
$materials = collection_list('materials');
$reviews   = array_values(array_filter(collection_list('reviews'), function ($r) {
    // approved is TINYINT 0/1 — only 1 counts as published.
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
$photoBandImg = '';
foreach ($featured_projects as $p) {
    $img = first_image($p);
    if ($img !== '/assets/img/placeholder.svg') { $photoBandImg = $img; break; }
}

function card(array $row, string $url, array $fields): void {
    $img = first_image($row);
    $meta = $fields[0];
    echo '<a href="' . e($url) . '" class="card card-clean tilt reveal img-reveal">';
    echo '<div class="card-img-wrap">' . meb_pic($img, (string) ($row['title'] ?? ''), [
        'w' => 960, 'h' => 720, 'fit' => 'cover', 'q' => 80,
        'sizes' => '(min-width:1081px) 33vw, (min-width:721px) 50vw, 100vw',
    ]) . '</div>';
    echo '<div class="card-body">';
    echo '<div class="eyebrow">' . e($meta) . '</div>';
    echo '<div class="card-title">' . e($row['title'] ?? 'Без названия') . '</div>';
    if (!empty($row['price'])) echo '<div class="card-price">от ' . e(number_format((float) $row['price'])) . ' ₽</div>';
    if (!empty($row['description'])) echo '<div class="card-desc">' . e(mb_strimwidth($row['description'], 0, 110, '...')) . '</div>';
    echo '<span class="card-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 17 17 7M9 7h8v8"/></svg></span>';
    echo '</div></a>';
}

layout_head('', '');
layout_nav('home', true);
?>
<main id="main-content">

<!-- 01 HERO — luxury cover slider -->
<section class="hero-slider" id="heroSlider" aria-label="Главная презентация">
  <h1 class="vh">ГОДНАЯ МЕБЕЛЬ — премиальная мебель на заказ. Кухни, гардеробные, гостиные из массива, камня и латуни.</h1>
  <div class="hero-slider-track" id="heroTrack"></div>
  <div class="hero-shade" aria-hidden="true"></div>
  <span class="hero-index" id="heroIndex" aria-hidden="true">01</span>
  <button class="hero-arrow prev" aria-label="Предыдущий слайд" id="heroPrev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></button>
  <button class="hero-arrow next" aria-label="Следующий слайд" id="heroNext"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
  <div class="hero-pagination" id="heroPagination"></div>
  <div class="hero-info"><b>Москва</b><span>·</span><span>Студия и производство</span></div>
  <div class="hero-scroll-hint" aria-hidden="true">Читать<i></i></div>
</section>

<!-- 02 STUDIO — statement band (full-width photo below) -->
<?php if ($photoBandImg): ?>
<section class="section-photo" aria-label="Интерьеры">
  <div class="parallax-media" data-speed="0.16">
    <?= meb_pic($photoBandImg, 'Интерьер из массива и камня', ['w' => 1920, 'h' => 1080, 'fit' => 'cover', 'q' => 82, 'sizes' => '100vw']) ?>
  </div>
  <div class="section-photo-veil" aria-hidden="true"></div>
  <div class="section-photo-content">
    <span class="kicker" style="color:var(--brass-soft)">Интерьеры, которые мы создали</span>
    <h2 class="h2" style="margin-top:var(--sp-4)">Мебель как архитектура интерьера</h2>
    <p class="sub" style="margin-top:var(--sp-4)">Кухни, гардеробные, гостиные, кабинеты — спроектировано под ваше пространство и собрано вручную.</p>
    <div class="form-row" style="justify-content:center">
      <a class="btn btn-light" href="/projects">Смотреть проекты</a>
      <a class="btn btn-ghost-light" href="/catalog">В каталог</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- 03 STUDIO — values -->
<section class="container section">
  <span class="sec-num" aria-hidden="true">01</span>
  <div class="section-header reveal">
    <span class="kicker kicker-num">01 / Студия</span>
    <p class="statement">Мебель, которая становится<br>частью вашего дома — <em>без компромиссов.</em></p>
    <p class="sub" style="margin-top:var(--sp-6)">Собственное производство, ручная доводка, честные материалы и точность до миллиметра. Мы проектируем долго и собираем навсегда.</p>
  </div>
  <div class="value reveal slow-in">
    <span class="value-num">01</span>
    <div>
      <h3 class="value-title">Производство</h3>
      <p class="value-desc">Современное оборудование и ручная доводка. Каждая деталь проходит строгий контроль качества.</p>
    </div>
  </div>
  <div class="value reveal slow-in">
    <span class="value-num">02</span>
    <div>
      <h3 class="value-title">Дизайн</h3>
      <p class="value-desc">От первого эскиза до установки — проектируем под ваше пространство: 3D-проект и смета в течение 48 часов.</p>
    </div>
  </div>
  <div class="value reveal slow-in">
    <span class="value-num">03</span>
    <div>
      <h3 class="value-title">Материалы</h3>
      <p class="value-desc">Массив, камень, латунь, стекло. Материалы, которые стареют красиво, а не выходят из моды.</p>
    </div>
  </div>
</section>

<!-- 04 COLLECTION — luxury product cards -->
<?php if ($featured_catalog): ?>
<section id="catalog" class="container section">
  <span class="sec-num" aria-hidden="true">02</span>
  <div class="section-header row reveal">
    <div class="section-header-text">
      <span class="kicker kicker-num">02 / Коллекция</span>
      <h2 class="h2" style="margin-top:var(--sp-3)">Предметы, которые<br>не требуют <em>объяснений.</em></h2>
    </div>
    <div>
      <a class="btn btn-ghost" href="/catalog"><span>Весь каталог</span><span class="btn-ar" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>
    </div>
  </div>
  <div class="grid-editorial" style="margin-top:var(--sp-12)">
    <?php foreach ($featured_catalog as $c) card($c, '/catalog/' . e(($categories_map[$c['category_id'] ?? ''] ?? 'catalog') . '/' . ($c['slug'] ?? $c['id'])), [$categories_title[$c['category_id'] ?? ''] ?? 'Изделие']); ?>
  </div>
</section>
<?php endif; ?>

<!-- 05 PROCESS — dark luxury band -->
<section class="section-dark" id="process">
  <div class="container pad">
    <span class="sec-num" aria-hidden="true">03</span>
    <div class="section-header reveal">
      <span class="kicker kicker-num">03 / Процесс</span>
      <h2 class="h2" style="margin-top:var(--sp-3)">От <em>идеи</em> до установки</h2>
      <p class="sub" style="margin-top:var(--sp-3)">Пять шагов, каждый под вашим контролем.</p>
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

<!-- 06 MATERIALS — dark material library -->
<?php if ($materials): ?>
<section class="section-dark" id="materials" style="border-top:1px solid rgba(243,239,232,.06)">
  <div class="container pad">
    <span class="sec-num" aria-hidden="true">04</span>
    <div class="section-header row reveal">
      <div class="section-header-text">
        <span class="kicker kicker-num">04 / Материалы</span>
        <h2 class="h2" style="margin-top:var(--sp-3)">Честные материалы</h2>
      </div>
      <div>
        <a class="btn btn-ghost-light" href="/materials">Все материалы</a>
      </div>
    </div>
    <div class="h-scroll" style="margin-top:var(--sp-12)">
      <?php foreach (array_slice($materials, 0, 8) as $m): ?>
        <a href="/materials#m-<?= e($m['id']) ?>" class="card card-clean tilt reveal">
          <div class="card-img-wrap"><?= meb_pic(first_image($m), (string) ($m['title'] ?? ''), ['w' => 480, 'h' => 640, 'fit' => 'cover', 'q' => 80, 'sizes' => '(min-width:1081px) 300px, 40vw, 55vw']) ?></div>
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

<!-- 07 PORTFOLIO -->
<?php if ($featured_projects): ?>
<section id="projects" class="container section">
  <span class="sec-num" aria-hidden="true">05</span>
  <div class="section-header row reveal">
    <div class="section-header-text">
      <span class="kicker kicker-num">05 / Портфолио</span>
      <h2 class="h2" style="margin-top:var(--sp-3)">Избранные <em>проекты</em></h2>
      <p class="sub" style="margin-top:var(--sp-3)">Каждый — под конкретный интерьер. Без типовых решений.</p>
    </div>
    <div>
      <a class="btn btn-ghost" href="/projects"><span>Все проекты</span><span class="btn-ar" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>
    </div>
  </div>
  <div class="masonry" style="margin-top:var(--sp-12)">
    <?php foreach ($featured_projects as $p) card($p, '/project/' . e($p['slug'] ?? $p['id']), [$p['category'] ?? 'Проект']); ?>
  </div>
</section>
<?php endif; ?>

<!-- 08 REVIEWS -->
<?php if ($reviews): ?>
<section id="reviews" class="container section">
  <span class="sec-num" aria-hidden="true">06</span>
  <div class="section-header reveal" style="text-align:center;margin-inline:auto">
    <span class="kicker kicker-num">06 / Отзывы</span>
    <h2 class="h2" style="margin-top:var(--sp-3)">Что говорят клиенты</h2>
  </div>
  <div class="grid cols2" style="margin-top:var(--sp-12)">
    <?php foreach (array_slice($reviews, 0, 6) as $r): ?>
      <div class="card review-card tilt reveal">
        <div class="review-stars" aria-label="Оценка <?= (int) ($r['rating'] ?? 5) ?> из 5"><?= str_repeat('★', (int) ($r['rating'] ?? 5)) ?></div>
        <div class="review-text">«<?= e($r['text'] ?? $r['review'] ?? '') ?>»</div>
        <div class="review-author"><?= e($r['author'] ?: 'Клиент') ?><?= !empty($r['role']) ? ' · ' . e($r['role']) : '' ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- 09 CTA -->
<section class="container section" style="padding-bottom:clamp(72px,9vw,128px)">
  <div class="cta-card reveal">
    <div>
      <span class="kicker">Обсудить проект</span>
      <h2 class="h2" style="margin-top:var(--sp-3)">Приедем, замерим,<br><em>спроектируем</em></h2>
      <p class="sub" style="margin-top:var(--sp-4)">Замер · 3D · смета за 48 часов · монтаж с защитой помещения</p>
      <div class="form-row">
        <a class="btn btn-light" href="/contacts">Получить консультацию</a>
        <a class="btn btn-ghost-light" href="/catalog">Смотреть коллекцию</a>
      </div>
    </div>
    <ul class="cta-features">
      <li>Авторский надзор на всех этапах</li>
      <li>Доставка и монтаж с защитой помещения</li>
      <li>Гарантия и сервисное обслуживание</li>
    </ul>
  </div>
</section>

</main>
<?php layout_footer(); ?>