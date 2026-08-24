const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function renderBlock(b){
  const d=b.data||{};
  switch(b.type){
    case 'hero': return `<section class="container hero"><div><h1 class="reveal">${esc(d.title)}</h1>${d.subtitle?`<p class="reveal">${esc(d.subtitle)}</p>`:''}${d.ctaLabel?`<a class="btn" href="${esc(d.ctaUrl||'#contacts')}">${esc(d.ctaLabel)}</a>`:''}</div></section>`;
    case 'text': return `<section class="container section"><div class="prose reveal">${esc(d.text)}</div></section>`;
    case 'features': return `<section class="container section"><div class="grid cols3">${(d.items||[]).map(i=>`<div class="card" style="padding:18px"><div class="eyebrow">${esc(i.kicker||'')}</div><div class="card-title">${esc(i.title)}</div><div class="muted" style="font-size:14px">${esc(i.desc)}</div></div>`).join('')}</div></section>`;
    case 'gallery': return `<section class="container section"><div class="masonry">${(d.images||[]).map(u=>`<article class="card reveal"><img loading="lazy" src="${esc(u.url||u)}" alt="${esc(u.alt||'')}"></article>`).join('')}</div></section>`;
    case 'statistics': return `<section class="container section"><div class="grid cols3">${(d.items||[]).map(i=>`<div style="text-align:center"><div style="font-family:'Cormorant Garamond',serif;font-size:44px">${esc(i.value)}</div><div class="eyebrow">${esc(i.label)}</div></div>`).join('')}</div></section>`;
    case 'faq': return `<section class="container section"><div class="grid cols2">${(d.items||[]).map(i=>`<div class="card" style="padding:18px"><div class="card-title">${esc(i.q)}</div><div class="muted" style="font-size:14px">${esc(i.a)}</div></div>`).join('')}</div></section>`;
    case 'cta': return `<section class="container section"><div class="card" style="padding:28px;text-align:center"><h3 class="h2">${esc(d.title)}</h3><p class="sub">${esc(d.subtitle||'')}</p><a class="btn" href="${esc(d.ctaUrl||'#contacts')}">${esc(d.ctaLabel||'Обсудить проект')}</a></div></section>`;
    case 'team': return `<section class="container section"><div class="grid cols3">${(d.items||[]).map(i=>`<div class="card" style="padding:18px;text-align:center"><div class="card-title">${esc(i.name)}</div><div class="muted" style="font-size:13px">${esc(i.role)}</div></div>`).join('')}</div></section>`;
    case 'contact': return `<section id="contacts" class="container section"><form class="form card" style="padding:18px" onsubmit="submitLead(event)"><input class="input" name="name" placeholder="Имя" required><input class="input" name="phone" placeholder="Телефон" required><textarea class="input" name="message" placeholder="Сообщение" rows="3"></textarea><button class="btn">Отправить</button><div id="leadMsg" class="muted"></div></form></section>`;
    default: return '';
  }
}
function renderPage(page, extraHead=''){
  const blocks=(page.blocks||[]).filter(x=>!x.hidden).map(renderBlock).join('\n');
  return `<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${esc(page.seoTitle||page.title)} — MEB</title>
<meta name="description" content="${esc(page.seoDesc||'')}">
<link rel="canonical" href="/p/${esc(page.slug)}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/frontend/assets/css/premium.css">${extraHead}</head>
<body>
<header class="nav"><div class="container nav-inner"><a class="logo" href="/">MEB</a><nav class="nav-links"><a href="/#catalog">Каталог</a><a href="/#projects">Проекты</a><a href="/#materials">Материалы</a><a href="/#contacts">Контакты</a></nav><a class="btn" href="/#contacts">Обсудить проект</a></div></header>
<main>${blocks}</main>
<footer class="footer"><div class="container" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap"><span>© 2026 MEB — Премиальная мебель</span><span><a href="/">Главная</a></span></div></footer>
<script src="/frontend/assets/js/app.js"></script></body></html>`;
}
module.exports={renderPage,renderBlock};
