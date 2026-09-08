<?php
/**
 * Layout helpers: shared <head>, nav, footer + data-access wrappers for pages.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/router.php';
require_once __DIR__ . '/../includes/image.php';
require_once __DIR__ . '/../api/v1/crud.php';

if (!function_exists('site_settings')) {
function site_settings(): array
{
    return get_settings();
}
}

if (!function_exists('layout_head')) {
function layout_head(string $title, string $desc = ''): void
{
    $s = site_settings();
    $site = $s['siteName'] ?? 'MEB';
    $title = $title !== '' ? $title . ' — ' . $site : $site . ' — ' . ($s['tagline'] ?? 'Премиальная мебель');
    $seo = is_array($s['seo'] ?? null) ? $s['seo'] : [];
    $og  = is_array($s['og'] ?? null) ? $s['og'] : [];
    $desc = $desc !== '' ? $desc : ($seo['desc'] ?? 'Индивидуальная мебель на заказ. Кухни, гардеробные, гостиные.');
    $ogTitle = ($og['title'] ?? '') !== '' ? $og['title'] : $title;
    $ogDesc  = ($og['description'] ?? '') !== '' ? $og['description'] : $desc;
    $ogImage = $og['image'] ?? '/assets/img/placeholder.svg';
    $favicon = $s['favicon'] ?? '';
    $logo    = $s['logo'] ?? '';
    $keywords = ($seo['keywords'] ?? '');
    $an = is_array($s['analytics'] ?? null) ? $s['analytics'] : [];
    $yaId  = preg_replace('/[^0-9]/', '', (string) ($an['ym'] ?? ''));
    $gaId  = trim((string) ($an['ga'] ?? ''));
    $gtmId = trim((string) ($an['gtm'] ?? ''));
    $anHtml = '';
    if ($yaId !== '') {
        $anHtml .= '<script>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");ym(' . $yaId . ',"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true});</script>';
    }
    if ($gaId !== '') {
        $anHtml .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($gaId) . '"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . e($gaId) . '");</script>';
    }
    if ($gtmId !== '') {
        $anHtml .= '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","' . e($gtmId) . '");</script>';
    }
    echo '<!doctype html>
<html lang="' . tlang() . '">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . e($title) . '</title>
<meta name="description" content="' . e($desc) . '">
' . ($keywords !== '' ? '<meta name="keywords" content="' . e($keywords) . '">' : '') . '
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/premium.css?v=' . meb_asset_version() . '">
' . (($favicon !== '' && meb_asset_exists($favicon)) ? '<link rel="icon" href="' . e($favicon) . '">' : '') . '
<link rel="canonical" href="' . e(meb_origin()) . e($_SERVER['REQUEST_URI'] ?? '/') . '">
<meta property="og:title" content="' . e($ogTitle) . '">
<meta property="og:description" content="' . e($ogDesc) . '">
<meta property="og:image" content="' . e($ogImage) . '">
<meta property="og:type" content="website">
' . $anHtml . '
</head>
<body><a class="skip-link" href="#main-content">К содержанию</a>';
}
}

/**
 * Returns <img> or wordmark for the brand logo.
 * Renders the configured logo path when set; if the image fails to load
 * (missing file, broken CDN link), the hidden wordmark is shown instead —
 * no broken <img> icon ever appears. When no logo is configured, the
 * wordmark is rendered directly.
 */
if (!function_exists('logo_mark')) {
function logo_mark(string $logo, string $siteName): string
{
    if ($logo === '') {
        return '<span>' . e($siteName) . '</span>';
    }
    return '<img src="' . e($logo) . '" alt="' . e($siteName) . '" '
         . 'onerror="this.style.display=\'none\';var n=this.nextElementSibling;if(n)n.style.display=\'inline\';">'
         . '<span style="display:none">' . e($siteName) . '</span>';
}
}

/**
 * Categories for the «Категории» showroom panel. Fed live from the CMS ;
 * if the collection is unavailable, a static map of the seeded slugs is used.
 */
if (!function_exists('meb_nav_categories')) {
function meb_nav_categories(): array
{
    $cats = [];
    try {
        $cats = collection_list('catalog_categories');
        if (is_array($cats)) {
            usort($cats, function ($a, $b) { return (int) ($a['sort'] ?? 0) <=> (int) ($b['sort'] ?? 0); });
        }
    } catch (\Throwable $e) {
        $cats = [];
    }
    if (!is_array($cats) || count($cats) === 0) {
        $cats = [
            ['slug' => 'kukhni',   'title' => 'Кухни'],
            ['slug' => 'garderob', 'title' => 'Гардеробные'],
            ['slug' => 'gornye',   'title' => 'Гостиные'],
            ['slug' => 'detskie',  'title' => 'Детские комнаты'],
            ['slug' => 'vannie',   'title' => 'Ванные комнаты'],
            ['slug' => 'mebel',    'title' => 'Мебель'],
        ];
    }
    return $cats;
}
}

/**
 * Renders the typographic category list (<ul class="mega-list">) used by both
 * the desktop showroom dropdown and the mobile accordion. Text only — no
 * fabricated imagery, per the brand brief.
 */
if (!function_exists('meb_render_cats')) {
function meb_render_cats(array $cats, string $ar, string $id = ''): string
{
    $out = '<ul class="mega-list"' . ($id !== '' ? ' id="' . e($id) . '"' : '') . '>';
    $ci = 0;
    foreach ($cats as $cat) {
        $slug = is_array($cat) ? (string) ($cat['slug'] ?? '') : '';
        $title = is_array($cat) ? (string) ($cat['title'] ?? '') : '';
        if ($slug === '' || $title === '') { continue; }
        $ci++;
        $out .= '<li><a href="' . e('/catalog/' . $slug) . '"><span class="mega-n">' . sprintf('%02d', $ci)
             . '</span><span class="mega-t">' . e($title) . '</span><span class="mega-ar">' . $ar . '</span></a></li>';
    }
    $out .= '<li class="mega-all"><a href="/catalog"><span class="mega-n">+</span><span class="mega-t">Весь каталог</span><span class="mega-ar">' . $ar . '</span></a></li>';
    $out .= '</ul>';
    return $out;
}
}

if (!function_exists('layout_nav')) {
function layout_nav(string $active = '', bool $darkTop = false): void
{
    $s = site_settings();
    $logo = $s['logo'] ?? '';
    $siteName = $s['siteName'] ?? 'MEB';
    $navCls = 'nav' . ($darkTop ? ' dark-top' : '');
    $activeKey = in_array($active, ['catalog', 'projects', 'services', 'contacts'], true) ? $active : '';

    $ar = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

    // Curated premium navigation — the five primary items from the brand brief.
    // «О бренде» and «Услуги» share the existing /services route; only «Услуги»
    // lights up as active there (a single indicator per destination).
    $keys   = ['catalog', 'projects', 'brand', 'services', 'contacts'];
    $labels = ['Категории', 'Проекты', 'О бренде', 'Услуги', 'Контакты'];
    $drops  = [true, false, false, false, false];
    $routes = ['/catalog', '/projects', '/services', '/services', '/contacts'];

    $cats = meb_nav_categories();

    echo '<header class="' . $navCls . '" id="siteNav"><div class="container nav-inner">
  <a class="logo" href="/" aria-label="' . e($siteName) . ' — на главную"><span class="logo-mark" aria-hidden="true"></span>';
    echo logo_mark($logo, $siteName);
    echo '</a>
  <nav class="nav-links" id="navDesktop" aria-label="Основная навигация"><ul class="nav-menu">';

    foreach ($keys as $i => $key) {
        $label = $labels[$i];
        $drop = $drops[$i];
        $route = $routes[$i];
        $idx = sprintf('%02d', $i + 1);
        $cls = 'nav-item' . ($drop ? ' drop' : '') . ($key === $activeKey ? ' active' : '');
        if ($drop) {
            echo '<li class="' . $cls . '">
        <button type="button" class="nav-link nav-trigger" data-drop-trigger aria-haspopup="true" aria-expanded="false" aria-controls="megaCatalog">
          <span class="nav-idx" aria-hidden="true">' . $idx . '</span><span class="nav-label">' . e($label) . '</span><span class="nav-arr" aria-hidden="true"></span>
        </button>
        <div class="mega" role="region" aria-label="Каталог — категории">
          <div class="mega-inner">
            <div class="mega-main">
              <span class="kicker">Каталог студии</span>
              <span class="mega-title">Showroom</span>
              <p class="mega-lead">Проектирование и производство на заказ. Каждое направление решается под ваше пространство.</p>
            </div>';
            echo meb_render_cats($cats, $ar, 'megaCatalog');
            echo '</div>
        </div>
      </li>';
        } else {
            echo '<li class="' . $cls . '"><a class="nav-link" href="' . e($route) . '"><span class="nav-idx" aria-hidden="true">' . $idx . '</span><span class="nav-label">' . e($label) . '</span></a></li>';
        }
    }

    echo '</ul></nav>
  <div class="nav-cta">
    <a class="nav-cta-link desktop-only" href="/contacts"><span class="nav-label">Обсудить проект</span><span class="nav-ar">' . $ar . '</span></a>
    <button class="burger" id="burger" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobileMenu"><span></span><span></span></button>
  </div>
</div></header>';

    // Fullscreen mobile panel — body-level so it can be fixed-positioned
    // independently of the glass capsule (a filtered ancestor would otherwise
    // trap it to the header strip).
    echo '<div class="mobile-panel" id="mobileMenu" role="dialog" aria-modal="true" aria-label="Меню">
  <div class="mobile-panel-inner">
    <ul class="mobile-menu">';

    foreach ($keys as $i => $key) {
        $label = $labels[$i];
        $drop = $drops[$i];
        $route = $routes[$i];
        $idx = sprintf('%02d', $i + 1);
        $cls = 'nav-item' . ($drop ? ' drop' : '') . ($key === $activeKey ? ' active' : '');
        if ($drop) {
            echo '<li class="' . $cls . '">
        <button type="button" class="nav-link nav-trigger" data-drop-trigger aria-haspopup="true" aria-expanded="false" aria-controls="mobileCatalog">
          <span class="nav-idx" aria-hidden="true">' . $idx . '</span><span class="nav-label">' . e($label) . '</span><span class="nav-arr" aria-hidden="true"></span>
        </button>
        <div class="mega" id="mobileCatalog">' . meb_render_cats($cats, $ar) . '</div>
      </li>';
        } else {
            echo '<li class="' . $cls . '"><a class="nav-link" href="' . e($route) . '"><span class="nav-idx" aria-hidden="true">' . $idx . '</span><span class="nav-label">' . e($label) . '</span></a></li>';
        }
    }

    echo '</ul>
    <div class="mobile-panel__cta">
      <a class="nav-cta-link" href="/contacts"><span class="nav-label">Обсудить проект</span><span class="nav-ar">' . $ar . '</span></a>
      <span class="mobile-panel__code">MEB &middot; Showroom</span>
    </div>
  </div>
</div>';
}
}

if (!function_exists('layout_footer')) {
function layout_footer(): void
{
    $s = site_settings();
    $siteName = $s['siteName'] ?? 'MEB';
    $logo = $s['logo'] ?? '';
    $tagline = $s['tagline'] ?? 'Индивидуальная мебель';
    $socials = $s['socials'] ?? [];
    $phone = $s['phone'] ?? '';
    $email = $s['email'] ?? '';
    $address = $s['address'] ?? '';
    $hours = $s['hours'] ?? '';
    $year = date('Y');

    $socialLinks = '';
    if (!empty($socials['instagram'])) $socialLinks .= '<a href="' . e($socials['instagram']) . '" target="_blank" rel="noopener">Instagram</a>';
    if (!empty($socials['telegram'])) $socialLinks .= '<a href="' . e($socials['telegram']) . '" target="_blank" rel="noopener">Telegram</a>';
    if (!empty($socials['whatsapp'])) $socialLinks .= '<a href="https://wa.me/' . e(preg_replace('/[^0-9]/', '', $socials['whatsapp'])) . '" target="_blank" rel="noopener">WhatsApp</a>';
    if (!empty($socials['vk'])) $socialLinks .= '<a href="' . e($socials['vk']) . '" target="_blank" rel="noopener">VK</a>';
    if (!empty($socials['youtube'])) $socialLinks .= '<a href="' . e($socials['youtube']) . '" target="_blank" rel="noopener">YouTube</a>';

    $ar = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

    // Navigation — real existing routes only (no invented links).
    $navRows = '<a href="/catalog">Каталог</a>'
             . '<a href="/projects">Проекты</a>'
             . '<a href="/services">О бренде</a>'
             . '<a href="/services">Услуги</a>'
             . '<a href="/contacts">Контакты</a>';

    $catRows = '';
    $fi = 0;
    foreach (meb_nav_categories() as $fc) {
        $fslug = is_array($fc) ? (string) ($fc['slug'] ?? '') : '';
        $ftitle = is_array($fc) ? (string) ($fc['title'] ?? '') : '';
        if ($fslug === '' || $ftitle === '' || $fi >= 6) continue;
        $fi++;
        $catRows .= '<a href="' . e('/catalog/' . $fslug) . '">' . e($ftitle) . '</a>';
    }

    echo '<footer class="footer"><div class="container">
  <div class="footer-top">
    <div>
      <span class="footer-kicker">ГОДНАЯ МЕБЕЛЬ &middot; Студия и производство</span>
      <p class="footer-statement">Предметы, которые<br>не требуют <em>объяснений.</em></p>
      <p class="footer-statement-sub">Кухни, гардеробные, гостиные, кабинеты. Проектирование, производство и монтаж под ключ.</p>
    </div>
    <a class="footer-cta" href="/contacts"><span>Обсудить проект</span><span class="footer-cta-ar">' . $ar . '</span></a>
  </div>
  <div class="footer-grid">
    <div class="footer-brand">
      <a class="logo" href="/" aria-label="Годная мебель — на главную"><span class="logo-mark" aria-hidden="true"></span>';
    echo str_replace('<img ', '<img style="height:40px" ', logo_mark($logo, 'ГОДНАЯ МЕБЕЛЬ'));
    echo '</a>
      <p class="footer-tagline">' . e($tagline) . '</p>';
    if ($siteName !== '') {
        echo '<p class="footer-copy">' . e($siteName) . ' — мебель на заказ с собственным производством.</p>';
    }
    if ($socialLinks !== '') {
        echo '<div class="footer-social">' . $socialLinks . '</div>';
    }
    echo '</div>
    <div class="footer-col">
      <h4>Навигация</h4>' . $navRows . '
    </div>
    <div class="footer-col">
      <h4>Категории</h4>' . ($catRows !== '' ? $catRows : '<a href="/catalog">Весь каталог</a>') . '
    </div>
    <div class="footer-col footer-col-contact">
      <h4>Контакты</h4>';
    if ($phone) echo '<a class="footer-phone" href="tel:' . e(preg_replace('/[^+0-9]/', '', $phone)) . '">' . e($phone) . '</a>';
    if ($email) echo '<a href="mailto:' . e($email) . '">' . e($email) . '</a>';
    if ($address) echo '<span class="footer-contact-line">' . e($address) . '</span>';
    if ($hours) echo '<span class="footer-contact-line">' . e($hours) . '</span>';
    echo '</div>
  </div>
  <div class="footer-wordmark" aria-hidden="true">ГОДНАЯ&nbsp;МЕБЕЛЬ</div>
  <div class="footer-bottom">
    <span class="footer-bottom-brand">&copy; ' . e(!empty($s['copyright']) ? $s['copyright'] : ('ГОДНАЯ МЕБЕЛЬ ' . "\u{00B7}" . ' ' . $year)) . '</span>
    <div class="footer-bottom-meta">
      <a href="/catalog">Каталог</a>
      <a href="/sitemap.xml">Карта сайта</a>
    </div>
  </div>
</div></footer>
<script src="/assets/js/app.js?v=' . meb_asset_version() . '"></script>
</body>
</html>';
}
}

if (!function_exists('first_image')) {
function first_image(array $row): string
{
    foreach (['images', 'image', 'cover'] as $k) {
        if (!empty($row[$k])) {
            if (is_array($row[$k]) && count($row[$k]) > 0) {
                return is_array($row[$k][0]) ? ($row[$k][0]['url'] ?? '') : (string) $row[$k][0];
            }
            if (is_string($row[$k]) && $row[$k] !== '') return $row[$k];
        }
    }
    return '/assets/img/placeholder.svg';
}
}
