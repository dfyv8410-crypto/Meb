const db=require('../core/database');
const i18n=require('../core/i18n');
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let LANG='ru';
const L=k=>i18n.t(LANG,k);
function money(v){ return 'от '+Number(v).toLocaleString(LANG==='en'?'en-US':'ru-RU')+' ₽' }
function shell(title,desc,canonical,body,lang){
  lang=lang||'ru';
  const L=k=>i18n.t(lang,k);
  return `<!doctype html><html lang="${lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${esc(title)} — MEB</title><meta name="description" content="${esc(desc)}"><link rel="canonical" href="${esc(canonical)}">
<meta property="og:title" content="${esc(title)}"><meta property="og:image" content="/frontend/assets/img/placeholder.svg">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/frontend/assets/css/premium.css"></head>
<body>
<header class="nav"><div class="container nav-inner"><a class="logo" href="/">MEB</a><nav class="nav-links"><a href="/#catalog">${L('nav.catalog')}</a><a href="/#projects">${L('nav.projects')}</a><a href="/#materials">${L('nav.materials')}</a><a href="/#contacts">${L('nav.contacts')}</a></nav><a class="btn" href="/#contacts">${L('cta.discuss')}</a></div></header>
<main>${body}</main>
<footer class="footer"><div class="container" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap"><span>© 2026 MEB — ${L('footer.copyright')}</span><span><a href="/">${L('footer.home')}</a> · <a href="/#projects">${L('nav.projects')}</a></span></div></footer>
<script src="/frontend/assets/js/app.js"></script></body></html>`;
}
function renderProject(p,lang){
  LANG=lang||'ru';
  const imgs=(p.images&&p.images.length)?p.images:['/frontend/assets/img/placeholder.svg'];
  const body=`
  <section class="container hero" style="grid-template-columns:1.2fr .8fr">
    <div>
      <div class="badge">${esc(p.category||L('nav.projects'))} · ${esc(p.year||'')}</div>
      <h1 class="reveal">${esc(p.title)}</h1>
      <p class="reveal">${esc(p.desc)}</p>
      ${p.size?`<p style="margin-top:10px"><b>${esc(p.sizeLabel||(LANG==='en'?'Area':'Площадь'))}:</b> ${esc(p.size)}${p.materials?` · <b>${esc(LANG==='en'?'Materials':'Материалы')}:</b> ${esc(Array.isArray(p.materials)?p.materials.join(', '):p.materials)}`:''}</p>`:''}
      ${(p.features||[]).length?`<ul style="color:#555;line-height:2;margin-top:12px">${p.features.map(f=>`<li>✓ ${esc(f)}</li>`).join('')}</ul>`:''}
    </div>
    <div class="hero-card reveal"><img src="${esc(imgs[0])}" alt="${esc(p.title)}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover"></div>
  </section>
  <section class="container section"><h2 class="h2 reveal">${L('project.gallery')}</h2>
    <div class="masonry">${imgs.map(u=>`<article class="card reveal"><img loading="lazy" src="${esc(u)}" alt="${esc(p.title)} — фото"></article>`).join('')}
    </div></section>
  <section id="contacts" class="container section"><div class="card" style="padding:28px;text-align:center">
    <h3 class="h2">${L('project.want_same')}</h3><p class="sub">${L('project.calc_48h')}</p>
    <a class="btn" href="/#contacts">${L('cta.discuss')}</a></div></section>`;
  return shell(p.title,p.desc||'','/project/'+p.slug,body,LANG);
}
function renderCategory(cat,items,lang){
  LANG=lang||'ru';
  const body=`
  <section class="container section"><div class="badge">${(items||[]).length} ${L('category.positions')}</div>
    <h1 class="h2" style="font-size:48px">${esc(cat.title)}</h1>
    <p class="sub">${esc(cat.desc||'')}</p>
    <div class="masonry">${(items||[]).map(x=>{
      const img=(x.images&&x.images[0])||x.cover||'/frontend/assets/img/placeholder.svg';
      const price=x.price?`<div style="margin-top:6px;font-weight:600">${money(x.price)}</div>`:'';
      return `<article class="card reveal"><img loading="lazy" src="${esc(img)}" alt="${esc(x.title)}">
        <div class="card-body"><div class="card-title">${esc(x.title)}</div><div class="muted" style="font-size:13px">${esc((x.desc||'').slice(0,110))}</div>${price}
        ${x.slug?`<div style="margin-top:10px"><a class="btn btn-ghost" style="padding:9px 16px;font-size:11px" href="/catalog/${esc(cat.slug)}/${esc(x.slug)}">${L('item.more')}</a></div>`:''}</div></article>`
    }).join('')||`<div class="muted">${L('category.soon')}</div>`}</div></section>`;
  return shell(cat.title,cat.desc||'',`/catalog/${cat.slug}`,body,LANG);
}
function renderItem(item,cat,lang){
  LANG=lang||'ru';
  const body=`
  <section class="container hero">
    <div><div class="badge">${esc(cat?cat.title:L('nav.catalog'))}</div>
    <h1 class="reveal">${esc(item.title)}</h1><p class="reveal">${esc(item.desc)}</p>
    ${item.price?`<div style="font-family:'Cormorant Garamond',serif;font-size:34px;margin-top:8px">${money(item.price)}</div>`:''}
    <div style="margin-top:14px"><a class="btn" href="/#contacts">${L('item.request_calc')}</a></div></div>
    <div class="hero-card reveal"><img src="${esc((item.images&&item.images[0])||'/frontend/assets/img/placeholder.svg')}" alt="${esc(item.title)}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover"></div>
  </section>
  <section class="container section">${specsHTML(item)}</section>`;
  return shell(item.title,item.desc||'',`/catalog/${cat?cat.slug:'x'}/${item.slug}`,body,LANG);
}
function specsHTML(item){
  const s=item.specs||{};
  const rows=[[L('spec.size'),item.size||s.size],[L('spec.material'),Array.isArray(item.materials)?item.materials.join(', '):(item.materials||s.material)],[L('spec.facade'),s.facade],[L('spec.hardware'),s.hardware],[L('spec.finish'),s.finish]].filter(r=>r[1]);
  if(!rows.length) return '';
  return `<div class="grid cols2"><div class="card" style="padding:18px"><h3 class="h2" style="font-size:24px">${L('item.specs')}</h3>
  <table style="width:100%;border-collapse:collapse;font-size:14px">${rows.map(r=>`<tr><td style="padding:8px 0;color:#777;width:40%">${r[0]}</td><td>${esc(r[1])}</td></tr>`).join('')}</table></div></div>`;
}
module.exports={renderProject,renderCategory,renderItem};
