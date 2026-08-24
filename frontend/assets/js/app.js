const $=s=>document.querySelector(s);
async function jget(p){ const r=await fetch(p); return r.json() }
function cardHTML(x, kind){
  const img=(x.images&&x.images[0])||x.image||x.cover||'/frontend/assets/img/placeholder.svg';
  return `<article class="card reveal"><img loading="lazy" src="${img}" alt="${x.title}"><div class="card-body"><div class="eyebrow">${kind||''}</div><div class="card-title">${x.title}</div><div class="muted" style="font-size:13px">${(x.desc||'').slice(0,120)}</div></div></article>`
}
async function load(){
  try{
    const [projects,catalog,materials,reviews]=await Promise.all([
      jget('/api/v1/projects'), jget('/api/v1/catalog'), jget('/api/v1/materials'), jget('/api/v1/reviews')
    ]);
    const pg=$('#projectsGrid'); if(pg) pg.innerHTML=(projects.slice(0,6).map(p=>cardHTML(p,p.category||'проект')).join('')||'<div class="muted">Проекты скоро появятся</div>');
    const cg=$('#catalogGrid'); if(cg) cg.innerHTML=(catalog.slice(0,6).map(c=>cardHTML(c,'каталог')).join('')||'<div class="muted">Каталог наполняется</div>');
    const mg=$('#materialsGrid'); if(mg) mg.innerHTML=(materials.slice(0,6).map(m=>cardHTML(m,m.category)).join('')||'');
    const rg=$('#reviewsGrid'); if(rg) rg.innerHTML=(reviews.filter(r=>r.approved!==false).slice(0,4).map(r=>`<div class="card" style="padding:18px"><div style="color:#C9A86A">${'★'.repeat(r.rating||5)}</div><div style="margin:8px 0">${r.text}</div><div class="muted">${r.author} · ${r.role||''}</div></div>`).join('')||'');
    observe();
  }catch(e){}
}
function observe(){
  const io=new IntersectionObserver(es=> es.forEach(e=>{ if(e.isIntersecting) e.target.classList.add('in')}),{threshold:.12});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
  if(matchMedia('(prefers-reduced-motion: reduce)').matches) document.querySelectorAll('.reveal').forEach(el=>el.classList.add('in'));
}
let ticking=false;
const hero=$('#hero3d');
if(hero) window.addEventListener('mousemove',e=>{
  if(matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if(ticking) return; ticking=true; requestAnimationFrame(()=>{
    const r=hero.getBoundingClientRect(); const x=(e.clientX-r.left)/r.width-.5; const y=(e.clientY-r.top)/r.height-.5;
    hero.style.transform=`perspective(900px) rotateY(${x*4}deg) rotateX(${-y*4}deg)`; ticking=false;
  })
});
async function submitLead(e){
  e.preventDefault(); const fd=new FormData(e.target); const body=Object.fromEntries(fd.entries());
  const r=await fetch('/api/v1/leads-public',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const j=await r.json(); const m=$('#leadMsg');
  if(r.ok){ m.textContent='Спасибо! Свяжемся в ближайшее время.'; e.target.reset()} else m.textContent=j.error||'Ошибка';
}
window.submitLead=submitLead;
observe(); load();
