const db=require('../../core/database');
function audit(){
  const issues=[]; let checked=0;
  const pages=db.all('pages'), projects=db.all('projects'), catalog=db.all('catalog');
  function check(where,cond,problem,severity){
    if(!cond) issues.push({where,problem,severity});
  }
  [...pages,...projects,...catalog].forEach(p=>{
    checked++;
    const where=(p.title||p.id||'').slice(0,40);
    if(p.slug) check('/'+p.slug, true);
    check(where, !!p.seoTitle||!!p.title, 'нет SEO Title', 'medium');
    check(where, !!p.seoDesc||!!p.desc, 'нет описания (description)', 'medium');
    check(where, String(p.title||'').length<=70, 'Title длиннее 70 символов — обрежется в выдаче', 'low');
    check(where, String(p.seoDesc||p.desc||'').length>=50&&String(p.seoDesc||p.desc||'').length<=170, 'Description должен быть 50–160 символов', 'low');
    (p.images||[]).forEach(img=>{
      check(where+' · изображение', !!(img&&(img.alt||typeof img==='string'&&false)||p.imageAlt), 'у изображения не заполнен ALT', 'high');
    });
  });
  // score: start 100, -8 high, -4 medium, -2 low
  let score=100;
  issues.forEach(i=>{ score-= i.severity==='high'?8 : i.severity==='medium'?4 : 2 });
  return {score:Math.max(0,score), checked, issues};
}
module.exports={audit};
