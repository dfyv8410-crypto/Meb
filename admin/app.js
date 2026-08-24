let token=localStorage.getItem('token')||'';
const cols=[
  {id:'dashboard',label:'🏠 Главная'},
  {id:'pages',label:'📄 Страницы'},
  {id:'catalog',label:'🪑 Каталог'},
  {id:'projects',label:'🏗️ Проекты'},
  {id:'materials',label:'🧱 Материалы'},
  {id:'services',label:'🛠️ Услуги'},
  {id:'reviews',label:'⭐ Отзывы'},
  {id:'leads',label:'📩 Заявки'},
  {id:'media',label:'🖼️ Медиа'},
  {id:'users',label:'👥 Пользователи'},
  {id:'settings',label:'⚙️ Настройки'},
  {id:'backup',label:'💾 Бэкап'},
];
let cur='dashboard';
function hdr(){ return token? {Authorization:'Bearer '+token}:{} }
async function jget(p){ const r=await fetch(p,{headers:hdr()}); return r.json()}
async function jpost(p,b){ const r=await fetch(p,{method:'POST',headers:{'Content-Type':'application/json',...hdr()},body:JSON.stringify(b)}); return {ok:r.ok, data:await r.json()}}
async function jput(p,b){ const r=await fetch(p,{method:'PUT',headers:{'Content-Type':'application/json',...hdr()},body:JSON.stringify(b)}); return {ok:r.ok, data:await r.json()}}
async function jdel(p){ const r=await fetch(p,{method:'DELETE',headers:hdr()}); return {ok:r.ok, data:await r.json()}}
function renderMenu(){
  const m=document.getElementById('menu'); m.innerHTML=cols.map(c=>`<a href="#" onclick="nav('${c.id}');return false" class="${cur===c.id?'active':''}">${c.label}</a>`).join('');
}
async function checkMe(){
  const r=await fetch('/api/v1/me',{headers:hdr()}); if(r.ok){ const u=await r.json(); document.getElementById('who').textContent=u.name+' · '+u.role; document.getElementById('login').style.display='none'; document.getElementById('app').style.display=''; renderMenu(); nav(cur); } else { document.getElementById('login').style.display=''; document.getElementById('app').style.display='none';}
}
async function doLogin(){
  const email=document.getElementById('email').value, password=document.getElementById('pwd').value;
  const r=await fetch('/api/v1/auth/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email,password})});
  const j=await r.json(); if(!r.ok){ document.getElementById('loginMsg').textContent=j.error; return}
  token=j.token; localStorage.setItem('token',token); checkMe();
}
function logout(){ localStorage.removeItem('token'); token=''; checkMe();}

async function nav(id){
  cur=id; renderMenu();
  const dash=document.getElementById('dash'), list=document.getElementById('list'), form=document.getElementById('formBox');
  form.style.display='none'; dash.innerHTML=''; list.innerHTML='';
  if(id==='dashboard'){
    const s=await jget('/api/v1/analytics/summary');
    const h=await jget('/api/v1/health');
    dash.innerHTML=`<div class="grid2">
      <div class="card"><b>Аналитика</b><div>Заявок: ${s.leads||0} · Проектов: ${s.projects||0} · Каталог: ${s.catalog||0}</div></div>
      <div class="card"><b>Система</b> <span class="badge">● ${h.status||'ok'}</span><div style="font-size:13px;color:#666">API работает · Storage OK</div></div></div>
      <div class="card"><b>Быстрые действия</b><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
        <button class="btn" onclick="quickAdd('projects')">+ Проект</button>
        <button class="btn" onclick="quickAdd('catalog')">+ Каталог</button>
        <button class="btn" onclick="nav('leads')">Заявки</button>
        <button class="btn btn-ghost" onclick="doBackup()">Создать бэкап</button>
      </div></div>`;
    // recent leads
    const leads=await jget('/api/v1/leads');
    list.innerHTML=`<div class="card"><b>Последние заявки</b><table><tr><th>Имя</th><th>Телефон</th><th>Статус</th></tr>${leads.slice(0,5).map(l=>`<tr><td>${l.name}</td><td>${l.phone}</td><td>${l.status}</td></tr>`).join('')}</table></div>`;
    return;
  }
  if(id==='settings'){
    const s=await jget('/api/v1/settings');
    list.innerHTML=`<div class="card"><h3>Настройки</h3>
      <input id="s_siteName" class="input" placeholder="Название" value="${s.siteName||''}">
      <input id="s_phone" class="input" placeholder="Телефон" value="${s.phone||''}" style="margin-top:8px">
      <input id="s_email" class="input" placeholder="Email" value="${s.email||''}" style="margin-top:8px">
      <button class="btn" style="margin-top:10px" onclick="saveSettings()">Сохранить</button><span id="s_msg" style="margin-left:10px;color:green"></span></div>`;
    return;
  }
  if(id==='backup'){
    const items=await jget('/api/v1/backup/list');
    list.innerHTML=`<div class="card"><b>Бэкапы</b> <button class="btn" onclick="doBackup()">Создать</button>
      <table style="margin-top:10px"><tr><th>Файл</th><th>Размер</th><th>Дата</th><th></th></tr>${items.map(b=>`<tr><td>${b.filename}</td><td>${b.size}</td><td>${(b.createdAt||'').slice(0,19)}</td><td><button onclick="restore('${b.id}')">Restore</button></td></tr>`).join('')}</table></div>`;
    return;
  }
  if(id==='media'){
    list.innerHTML=`<div class="card"><b>Медиа</b><div style="margin-top:8px"><input type="file" id="fileIn"><input id="altIn" class="input" placeholder="ALT — опишите изображение" style="margin-top:8px"><button class="btn" onclick="uploadMedia()">Загрузить</button></div><div id="mediaGrid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px"></div></div>`;
    loadMedia(); return;
  }
  // generic CRUD
  const items=await jget('/api/v1/'+id);
  list.innerHTML=`<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><b>${id}</b><button class="btn" onclick="showForm('${id}')">+ Добавить</button></div>
    <table style="margin-top:10px"><tr><th>Title / Name</th><th>Slug/Email</th><th></th></tr>${items.map(it=>{
      const title=it.title||it.name||it.email||it.id;
      const slug=it.slug||it.email||'';
      return `<tr><td>${title}</td><td>${slug}</td><td><button onclick="editItem('${id}','${it.id}')">Edit</button> <button onclick="delItem('${id}','${it.id}')">Del</button></td></tr>`
    }).join('')}</table></div>`;
}

function showForm(col, data={}){
  const box=document.getElementById('formBox'); box.style.display='';
  const isUser=col==='users';
  box.innerHTML=`<h3>${data.id?'Редактировать':'Добавить'} ${col}</h3>
    ${isUser? `<input id="f_email" class="input" placeholder="email" value="${data.email||''}"><input id="f_name" class="input" placeholder="Имя" value="${data.name||''}" style="margin-top:8px"><input id="f_password" class="input" placeholder="пароль" type="password" style="margin-top:8px"><select id="f_role" class="input" style="margin-top:8px"><option value="manager" ${data.role==='manager'?'selected':''}>manager</option><option value="editor" ${data.role==='editor'?'selected':''}>editor</option><option value="admin" ${data.role==='admin'?'selected':''}>admin</option></select>`
    : `<input id="f_title" class="input" placeholder="title / name" value="${data.title||data.name||''}">
       <input id="f_slug" class="input" placeholder="slug" value="${data.slug||''}" style="margin-top:8px">
       <textarea id="f_desc" class="input" placeholder="описание" style="margin-top:8px">${data.desc||data.text||data.message||''}</textarea>
       <input id="f_extra" class="input" placeholder='extra JSON (опционально)' value='' style="margin-top:8px">`}
    <div style="margin-top:10px"><button class="btn" onclick="saveForm('${col}','${data.id||''}')">Сохранить</button> <button class="btn btn-ghost" onclick="document.getElementById('formBox').style.display='none'">Отмена</button></div>`;
  box.dataset.col=col; box.dataset.id=data.id||'';
}
async function editItem(col,id){
  const items=await jget('/api/v1/'+col); const it=items.find(x=>x.id===id); if(it) showForm(col,it);
}
async function delItem(col,id){
  if(!confirm('Удалить?')) return;
  const r=await jdel('/api/v1/'+col+'/'+id); if(r.ok) nav(cur);
}
async function saveForm(col, id){
  let body={};
  if(col==='users'){
    body={email:document.getElementById('f_email').value, name:document.getElementById('f_name').value, role:document.getElementById('f_role').value, password:document.getElementById('f_password').value};
    if(!body.password && id) delete body.password;
    // users -> register endpoint expects POST /auth/register? use /users
    if(id) { const r=await jput('/api/v1/users/'+id, body); if(r.ok) nav(cur); else alert(r.data.error) }
    else {
      // create via /api/v1/users needs auth
      // we hash on server? our users CRUD creates directly but needs salt/hash — call dedicated login? simplify: server handles password field
      const r=await fetch('/api/v1/users',{method:'POST',headers:{'Content-Type':'application/json',...hdr()},body:JSON.stringify(body)});
      // server stores raw? we need to hash if password present
      if(r.ok) nav(cur); else alert((await r.json()).error);
    }
    return;
  } else {
    body={title:document.getElementById('f_title').value, slug:document.getElementById('f_slug').value||undefined, desc:document.getElementById('f_desc').value};
    try{ const extra=JSON.parse(document.getElementById('f_extra').value||'null'); if(extra) Object.assign(body,extra)}catch(e){}
    if(!body.slug && body.title) body.slug=body.title.toLowerCase().replace(/[^a-z0-9]+/g,'-');
  }
  let res;
  if(id) res=await jput('/api/v1/'+col+'/'+id, body);
  else res=await jpost('/api/v1/'+col, body);
  if(res.ok) { document.getElementById('formBox').style.display='none'; nav(cur);} else alert(res.data.error||'Error');
}
async function saveSettings(){
  const body={siteName:document.getElementById('s_siteName').value, phone:document.getElementById('s_phone').value, email:document.getElementById('s_email').value};
  const r=await jput('/api/v1/settings', body); document.getElementById('s_msg').textContent=r.ok?'Сохранено':'Ошибка';
}
async function doBackup(){ const r=await jpost('/api/v1/backup/create',{}); if(r.ok) nav('backup');}
async function restore(id){ if(!confirm('Восстановить? Будет создан pre-restore бэкап.')) return; const r=await jpost('/api/v1/backup/restore/'+id,{}); alert(r.ok?'Готово':'Ошибка');}
async function loadMedia(){ const items=await jget('/api/v1/media'); const g=document.getElementById('mediaGrid'); if(!g) return; g.innerHTML=items.map(m=>`<div class="card" style="padding:8px"><img src="${m.url}" style="width:100%;aspect-ratio:1;object-fit:cover"><div style="font-size:12px">${m.originalName}</div></div>`).join('');}
async function uploadMedia(){
  const f=document.getElementById('fileIn').files[0]; if(!f) return alert('Выберите файл');
  const alt=document.getElementById('altIn').value;
  const b64=await new Promise(res=>{ const r=new FileReader(); r.onload=()=>res(r.result); r.readAsDataURL(f)});
  const r=await jpost('/api/v1/media/upload',{filename:f.name, data:b64, alt}); if(r.ok) loadMedia(); else alert(r.data.error);
}
function quickAdd(col){ nav(col); setTimeout(()=>showForm(col),300)}
checkMe();
