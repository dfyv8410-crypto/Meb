<?php
/**
 * Layout helpers: shared <head>, nav, footer + data-access wrappers for pages.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/router.php';
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
<link rel="stylesheet" href="/assets/css/premium.css">
' . ($favicon !== '' ? '<link rel="icon" href="' . e($favicon) . '">' : '') . '
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

if (!function_exists('layout_nav')) {
function layout_nav(string $active = '', bool $darkTop = false): void
{
    $s = site_settings();
    $logo = $s['logo'] ?? '';
    $siteName = $s['siteName'] ?? 'MEB';
    $navCls = 'nav' . ($darkTop ? ' dark-top' : '');
    echo '<header class="' . $navCls . '" id="siteNav"><div class="container nav-inner">
  <a class="logo" href="/"><span class="logo-mark" aria-hidden="true"></span>';
    echo logo_mark($logo, $siteName);
    echo '</a>
  <nav class="nav-links" id="navLinks" aria-label="Основная навигация">';

    // Try dynamic menu first, fall back to static
    $menuItems = [];
    try {
        $menuItems = collection_list('menu_items');
        usort($menuItems, function ($a, $b) { return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0); });
        $menuItems = array_filter($menuItems, function ($i) { return ($i['is_active'] ?? 1) == 1; });
    } catch (\Throwable $e) {}

    if (!empty($menuItems)) {
        foreach ($menuItems as $item) {
            $href = $item['url'] ?? '/';
            $label = $item['title'] ?? '';
            $cls = ($active !== '' && strpos($href, '/' . $active) === 0) ? ' active' : '';
            echo '<a href="' . e($href) . '" class="' . $cls . '">' . e($label) . '</a>';
        }
    } else {
        $items = [
            ['/catalog', 'Каталог'],
            ['/catalog', 'Коллекции'],
            ['/projects', 'Проекты'],
            ['/services', 'О бренде'],
            ['/services', 'Услуги'],
            ['/contacts', 'Контакты'],
        ];
        foreach ($items as [$href, $label]) {
            $cls = ($active !== '' && strpos($href, '/' . $active) === 0) ? ' active' : '';
            echo '<a href="' . $href . '" class="' . $cls . '">' . e($label) . '</a>';
        }
    }

    echo '<a class="btn btn-sm btn-ghost nav-overlay-cta" href="/contacts">Обсудить проект</a>';
    echo '</nav>
  <div class="nav-cta">
    <a class="btn btn-sm btn-ghost desktop-only" href="/contacts">Обсудить проект</a>
    <button class="burger" id="burger" aria-label="Меню" aria-expanded="false" aria-controls="navLinks"><span></span><span></span></button>
  </div>
</div></header>';
}
}

if (!function_exists('layout_footer')) {
function layout_footer(): void
{
    $s = site_settings();
    $siteName = $s['siteName'] ?? 'MEB';
    $logo = $s['logo'] ?? '';
    $copyright = $s['copyright'] ?? '© 2026 — Премиальная мебель';
    $socials = $s['socials'] ?? [];
    $phone = $s['phone'] ?? '';
    $email = $s['email'] ?? '';
    $address = $s['address'] ?? '';
    $hours = $s['hours'] ?? '';

    $socialLinks = '';
    if (!empty($socials['instagram'])) $socialLinks .= '<a href="' . e($socials['instagram']) . '" target="_blank" rel="noopener">Instagram</a>';
    if (!empty($socials['telegram'])) $socialLinks .= '<a href="' . e($socials['telegram']) . '" target="_blank" rel="noopener">Telegram</a>';
    if (!empty($socials['whatsapp'])) $socialLinks .= '<a href="https://wa.me/' . e(preg_replace('/[^0-9]/', '', $socials['whatsapp'])) . '" target="_blank" rel="noopener">WhatsApp</a>';
    if (!empty($socials['vk'])) $socialLinks .= '<a href="' . e($socials['vk']) . '" target="_blank" rel="noopener">VK</a>';
    if (!empty($socials['youtube'])) $socialLinks .= '<a href="' . e($socials['youtube']) . '" target="_blank" rel="noopener">YouTube</a>';

    echo '<footer class="footer"><div class="container">
  <div class="footer-grid">
    <div class="footer-brand">
      <a class="logo" href="/"><span class="logo-mark" aria-hidden="true"></span>';
    echo str_replace('<img ', '<img style="height:40px" ', logo_mark($logo, $siteName));
    echo '</a>
      <p class="footer-tagline">Мебель, созданная<br>с вниманием к деталям.</p>
      <p class="footer-copy">Кухни, гардеробные, гостиные. Ручная доводка и точность до миллиметра.</p>';
    if ($socialLinks !== '') {
        echo '<div class="footer-social">' . $socialLinks . '</div>';
    }
    echo '</div>
    <div class="footer-col">
      <h4>Навигация</h4>
      <a href="/catalog">Каталог</a>
      <a href="/catalog">Коллекции</a>
      <a href="/projects">Проекты</a>
      <a href="/services">О бренде</a>
      <a href="/services">Услуги</a>
      <a href="/contacts">Контакты</a>
    </div>';
    echo '<div class="footer-col">
      <h4>Контакты</h4>';
    if ($phone) echo '<a href="tel:' . e(preg_replace('/[^+0-9]/', '', $phone)) . '">' . e($phone) . '</a>';
    if ($email) echo '<a href="mailto:' . e($email) . '">' . e($email) . '</a>';
    if ($address) echo '<span class="footer-contact-line">' . e($address) . '</span>';
    if ($hours) echo '<span class="footer-contact-line">' . e($hours) . '</span>';
    echo '</div>
  </div>
  <div class="footer-bottom">
    <span>' . e($copyright) . '</span>
    <div class="footer-bottom-meta">
      <a href="/admin">Админ-панель</a>
      <a href="/sitemap.xml">Карта сайта</a>
    </div>
  </div>
</div></footer>
<script src="/assets/js/app.js"></script>
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
