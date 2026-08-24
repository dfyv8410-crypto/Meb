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
  {id:'seo',label:'🔍 SEO'},
  {id:'settings',label:'⚙️ Настройки'},
  {id:'system',label:'🖥️ Система'},
];
let cur='dashboard';
function hdr(){ return token? {Authorization:'Bearer '+token}:{} }
async function jget(p){ const r=await fetch(p,{headers:hdr()}); return r.json()}
async function jpost(p,b){ const r=await fetch(p,{method:'POST',headers:{'Content-Type':'application/json',...hdr()},body:JSON.stringify(b||{})}); return {ok:r.ok, data:await r.json()}}
async function jput(p,b){ const r=await fetch(p,{method:'PUT',headers:{'Content-Type':'application/json',...hdr()},body:JSON.stringify(b||{})}); return {ok:r.ok, data:await r.json()}}
async function jdel(p){ const r=await fetch(p,{method:'DELETE',headers:hdr()}); return {ok:r.ok, data:await r.json()}}
function renderMenu(){
  const m=document.getElementById('menu'); m.innerHTML=cols.map(c=>`<a href="#" onclick="nav('${c.id}');return false" class="${cur===c.id?'active':''}">${c.label}</a>`).join('');
}
async function checkMe(){
  const r=await fetch('/api/v1/me',{headers:hdr()});
  if(r.ok){ const u=await r.json(); el('who').textContent=u.name+' · '+u.role; el('login').style.display='none'; el('app').style.display=''; renderMenu(); nav(cur); refreshBell(); setInterval(refreshBell,30000); }
  else { el('login').style.display=''; el('app').style.display='none';}
}
async function doLogin(){
  const email=document.getElementById('email').value, password=document.getElementById('pwd').value;
  const r=await fetch('/api/v1/auth/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email,password})});
  const j=await r.json(); if(!r.ok){ document.getElementById('loginMsg').textContent=j.error; return}
  token=j.token; localStorage.setItem('token',token); checkMe();
}
function logout(){ localStorage.removeItem('token'); token=''; checkMe();}
const el=id=>document.getElementById(id);
const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

/* ============ DARK MODE + NOTIFICATIONS ============ */
function toggleDark(){
  document.body.classList.toggle('dark');
  localStorage.setItem('meb_dark',document.body.classList.contains('dark')?'1':'0');
}
if(localStorage.getItem('meb_dark')==='1') document.addEventListener('DOMContentLoaded',()=>document.body.classList.add('dark'));
async function refreshBell(){
  try{
    const notes=await jget('/api/v1/notifications')||[];
    const unread=notes.filter(n=>!n.read).length;
    const c=el('bellCnt'); if(c){ c.textContent=unread; c.style.display=unread?'block':'none' }
  }catch(e){}
}
async function openNotifications(){
  const box=el('notifBox');
  if(box.style.display==='none'){
    box.style.display='';
    const notes=await jget('/api/v1/notifications')||[];
    el('notifList').innerHTML=notes.slice(0,10).map(n=>`<div class="card" style="padding:8px;margin-top:6px;${n.read?'':'border-color:#C9A86A'}"><b style="font-size:13px">${esc(n.title)}</b><div style="font-size:12px;color:var(--muted)">${esc(n.body)}</div><div style="font-size:11px;color:var(--muted)">${esc((n.createdAt||'').slice(0,19))}</div></div>`).join('')||'<div style="color:var(--muted);font-size:13px">Пока нет уведомлений</div>';
  } else box.style.display='none';
}
async function markRead(){ await jput('/api/v1/notifications/read-all',{}); openNotifications(); openNotifications(); refreshBell(); }

/* ============ NAV ============ */
async function nav(id){
  cur=id; renderMenu();
  const dash=el('dash'), list=el('list'), form=el('formBox');
  form.style.display='none'; dash.innerHTML=''; list.innerHTML='';
  if(id==='dashboard') return renderDashboard(dash,list);
  if(id==='settings') return renderSettings(list);
  if(id==='backup') return renderBackup(list);
  if(id==='media') return renderMedia(list);
  if(id==='pages') return renderPages(list);
  if(id==='seo') return renderSEO(list);
  if(id==='system') return renderSystem(list);
  return renderCrud(id,list);
}

/* ============ DASHBOARD ============ */
async function renderDashboard(dash,list){
  const s=await jget('/api/v1/analytics/summary');
  const h=await jget('/api/v1/health');
  const views=(s.views||{});
  const max=Math.max(1,...(views.days||[]).map(d=>d.views));
  const bars=(views.days||[]).slice(-14).map(d=>`<div style="flex:1;display:flex;align-items:end;justify-content:center"><div title="${d.date}: ${d.views}" style="width:70%;height:${Math.round(d.views/max*46)+3}px;background:#C9A86A;border-radius:3px"></div></div>`).join('');
  dash.innerHTML=`<div class="grid2">
    <div class="card"><b>Аналитика</b><div>Заявок: ${s.leads||0} · Проектов: ${s.projects||0} · Каталог: ${s.catalog||0}</div></div>
    <div class="card"><b>Система</b> <span class="badge">● ${h.status||'ok'}</span><div style="font-size:13px;color:var(--muted)">API работает · Storage OK</div></div></div>
    <div class="card"><b>Посетители</b><span style="float:right;color:var(--muted);font-size:13px">всего ${views.total||0} · сегодня ${views.today||0}</span>
      <div style="display:flex;gap:4px;height:52px;margin-top:10px;align-items:end">${bars||'<span style="color:var(--muted);font-size:12px">Данных пока нет</span>'}</div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">Просмотры страниц за 14 дней</div></div>
    <div class="card"><b>Быстрые действия</b><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
      <button class="btn" onclick="quickAdd('projects')">+ Проект</button>
      <button class="btn" onclick="quickAdd('catalog')">+ Каталог</button>
      <button class="btn" onclick="quickAdd('materials')">+ Материал</button>
      <button class="btn" onclick="quickAdd('services')">+ Услуга</button>
      <button class="btn btn-ghost" onclick="nav('leads')">Заявки</button>
      <button class="btn btn-ghost" onclick="doBackup()">Создать бэкап</button>
    </div></div>`;
  const leads=await jget('/api/v1/leads');
  list.innerHTML=`<div class="card"><b>Последние заявки</b><table><tr><th>Имя</th><th>Телефон</th><th>Статус</th><th></th></tr>${(leads||[]).slice(0,5).map(l=>`<tr><td>${esc(l.name)}</td><td>${esc(l.phone)}</td><td><span class="badge">${esc(l.status)}</span></td><td><button onclick="editLead('${l.id}')">Открыть</button></td></tr>`).join('')}</table></div>`;
}
async function editLead(id){
  nav('leads'); setTimeout(async()=>{
    const items=await jget('/api/v1/leads'); const it=(items||[]).find(x=>x.id===id);
    if(it) showForm('leads',it);
  },300);
}

/* ============ SETTINGS ============ */
async function renderSettings(list){
  const s=await jget('/api/v1/settings')||{};
  const smtp=s.smtp||{}, fcm=s.fcm||{};
  list.innerHTML=`<div class="card"><h3>Настройки сайта</h3>
    <label>Название сайта</label><input id="s_siteName" class="input" value="${esc(s.siteName||'')}">
    <label style="display:block;margin-top:8px">Телефон</label><input id="s_phone" class="input" value="${esc(s.phone||'')}">
    <label style="display:block;margin-top:8px">Email</label><input id="s_email" class="input" value="${esc(s.email||'')}">
    <label style="display:block;margin-top:8px">Адрес</label><input id="s_address" class="input" value="${esc(s.address||'')}">
    <label style="display:block;margin-top:8px" title="Заголовок страницы в поисковой выдаче">SEO Title сайта ⓘ</label><input id="s_seoTitle" class="input" value="${esc((s.seo&&s.seo.title)||'')}">
    <label style="display:block;margin-top:8px" title="Описание для поисковиков (150-160 символов)">SEO Description ⓘ</label><textarea id="s_seoDesc" class="input">${esc((s.seo&&s.seo.desc)||'')}</textarea>
    <h3 style="margin-top:18px">Уведомления и бэкапы</h3>
    <label style="display:block;margin-top:6px" title="Куда слать уведомления о новых заявках">Email для уведомлений ⓘ</label><input id="s_notifyEmail" class="input" value="${esc(s.notifyEmail||'')}" placeholder="manager@meb.local">
    <label style="display:block;margin-top:8px" title="Автоматический бэкап базы и настроек">Расписание авто-бэкапа ⓘ</label>
    <select id="s_backupSchedule" class="input">${[['off','Выключено'],['daily','Раз в день'],['weekly','Раз в неделю']].map(([v,n])=>`<option value="${v}" ${s.backupSchedule===v?'selected':''}>${n}</option>`).join('')}</select>
    ${s.lastAutoBackup?`<div style="font-size:12px;color:var(--muted);margin-top:4px">Последний авто-бэкап: ${esc(String(s.lastAutoBackup).slice(0,19))}</div>`:''}
    <label style="display:block;margin-top:10px" title="SMTP-сервер для отправки писем. Без TLS на порту 25 или relay внутри сети.">SMTP хост / порт ⓘ</label>
    <div style="display:flex;gap:8px"><input id="s_smtpHost" class="input" placeholder="smtp.example.com" value="${esc(smtp.host||'')}"><input id="s_smtpPort" class="input" style="max-width:100px" placeholder="25" value="${esc(smtp.port||'')}"></div>
    <label style="display:block;margin-top:8px">SMTP пользователь / пароль (если требуется)</label>
    <div style="display:flex;gap:8px"><input id="s_smtpUser" class="input" value="${esc(smtp.user||'')}"><input id="s_smtpPass" class="input" type="password" value="${esc(smtp.pass||'')}"></div>
    <label style="display:block;margin-top:8px">SMTP From</label><input id="s_smtpFrom" class="input" value="${esc(smtp.from||'')}" placeholder="noreply@meb.local">
    <label style="display:block;margin-top:10px" title="Legacy FCM server key для push на Android. Получите в Firebase Console → Project Settings → Cloud Messaging.">FCM Server Key (Android push) ⓘ</label>
    <input id="s_fcmKey" class="input" value="${esc(fcm.key||'')}" placeholder="AAAAx…"><input id="s_fcmTopic" class="input" style="margin-top:8px" placeholder="topic, напр. meb-admin" value="${esc(fcm.topic||'')}">
    <button class="btn" style="margin-top:12px" onclick="saveSettings()">Сохранить всё</button><span id="s_msg" style="margin-left:10px;color:green"></span></div>`;
}
async function saveSettings(){
  const body={siteName:el('s_siteName').value, phone:el('s_phone').value, email:el('s_email').value, address:el('s_address').value,
    seo:{title:el('s_seoTitle').value, desc:el('s_seoDesc').value},
    notifyEmail:el('s_notifyEmail').value.trim(),
    backupSchedule:el('s_backupSchedule').value,
    smtp:{host:el('s_smtpHost').value.trim(), port:el('s_smtpPort').value.trim(), user:el('s_smtpUser').value.trim(), pass:el('s_smtpPass').value, from:el('s_smtpFrom').value.trim()},
    fcm:{key:el('s_fcmKey').value.trim(), topic:el('s_fcmTopic').value.trim()}};
  const cur0=await jget('/api/v1/settings')||{};
  const merged={...cur0,...body};
  const r=await jput('/api/v1/settings',merged); el('s_msg').textContent=r.ok?'✓ Сохранено':'Ошибка';
}

/* ============ BACKUP ============ */
async function renderBackup(list){
  const items=await jget('/api/v1/backup/list')||[];
  list.innerHTML=`<div class="card"><b>Бэкапы</b> <button class="btn" onclick="doBackup()">Создать</button>
    <table style="margin-top:10px"><tr><th>Файл</th><th>Размер</th><th>Дата</th><th></th></tr>${items.map(b=>`<tr><td>${esc(b.filename)}</td><td>${Math.round((b.size||0)/1024)} KB</td><td>${esc((b.createdAt||'').slice(0,19))}</td><td><button onclick="restore('${b.id}')">Restore</button></td></tr>`).join('')}</table>
    <div style="font-size:12px;color:#888;margin-top:8px">Перед восстановлением автоматически создаётся страховочная копия.</div></div>`;
}
async function doBackup(){ const r=await jpost('/api/v1/backup/create'); if(r.ok) nav(cur==='dashboard'?cur:'backup'); }
async function restore(id){ if(!confirm('Восстановить данные из бэкапа? Текущие данные будут сохранены в страховочную копию.')) return; const r=await jpost('/api/v1/backup/restore/'+id); alert(r.ok?'Восстановлено ✓':(r.data.error||'Ошибка'));}

/* ============ MEDIA ============ */
async function renderMedia(list){
  const items=await jget('/api/v1/media')||[];
  const folders=[...new Set((items||[]).map(m=>m.folder||''))];
  list.innerHTML=`<div class="card"><b>Медиа</b>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
      <button class="btn btn-ghost" onclick="filterFolder('')">Все</button>
      ${folders.filter(Boolean).map(f=>`<button class="btn btn-ghost" onclick="filterFolder('${esc(f)}')">${esc(f)}</button>`).join('')}
    </div>
    <div style="margin-top:10px"><input type="file" id="fileIn">
    <input id="folderIn" class="input" style="max-width:200px;display:inline-block;margin-left:8px" placeholder="Папка (опц.)">
    <input id="altIn" class="input" placeholder="ALT — кратко опишите изображение (важно для SEO)" style="margin-top:8px">
    <button class="btn" onclick="uploadMedia()">Загрузить</button></div>
    <input id="mediaSearch" class="input" placeholder="Поиск…" style="margin-top:10px" oninput="loadMedia(this.value)">
    <div id="mediaGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-top:12px"></div></div>`;
  window._curFolder='';
  loadMedia('');
}
function filterFolder(f){ window._curFolder=f; loadMedia(el('mediaSearch').value); }
async function loadMedia(q){
  const fp=window._curFolder!==undefined? '&folder='+encodeURIComponent(window._curFolder):'';
  const items=await jget('/api/v1/media?x=1'+fp)||[];
  const f=(items||[]).filter(m=>!q||(m.originalName||'').toLowerCase().includes(q.toLowerCase()));
  const g=el('mediaGrid'); if(g) g.innerHTML=f.map(m=>`<div class="card" style="padding:8px"><img src="${m.url}" loading="lazy" alt="${esc(m.alt)}" style="width:100%;aspect-ratio:1;object-fit:cover;background:#eee"><div style="font-size:11px;margin-top:4px">${esc(m.originalName)}</div><div style="font-size:10px;color:var(--muted)">${m.width?m.width+'×'+m.height+' · ':''}${Math.round((m.size||0)/1024)} KB</div><button style="width:100%;margin-top:4px;font-size:11px" onclick="copyUrl('${m.url}')">Копировать URL</button><button style="width:100%;margin-top:2px;font-size:11px;color:#c00" onclick="delMedia('${m.id}')">Удалить</button></div>`).join('');
}
function copyUrl(u){ navigator.clipboard.writeText(location.origin+u); alert('URL скопирован'); }
async function delMedia(id){ if(!confirm('Удалить файл?')) return; await jdel('/api/v1/media/'+id); loadMedia('');}
async function uploadMedia(){
  const f=el('fileIn').files[0]; if(!f) return alert('Выберите файл');
  const alt=el('altIn').value;
  const folder=el('folderIn')?el('folderIn').value.trim():'';
  const b64=await new Promise(res=>{ const r=new FileReader(); r.onload=()=>res(r.result); r.readAsDataURL(f)});
  const r=await jpost('/api/v1/media/upload',{filename:f.name, data:b64, alt, folder});
  if(r.ok) loadMedia(''); else alert(r.data.error);
}

/* ============ GENERIC CRUD ============ */
async function renderCrud(id,list){
  const items=await jget('/api/v1/'+id)||[];
  const isLeads=id==='leads', isReviews=id==='reviews';
  list.innerHTML=`<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><b>${id}</b>${isLeads?'':`<button class="btn" onclick="showForm('${id}')">+ Добавить</button>`}</div>
    ${isLeads?'<div style="font-size:12px;color:var(--muted)">Меняйте статус прямо в списке: new → in_progress → contacted → done / rejected</div>':''}
    <table style="margin-top:10px"><thead><tr><th>Название</th><th>Slug/Email</th><th>Статус</th><th></th></tr></thead><tbody>${items.map(it=>{
      const title=it.title||it.name||it.email||it.id;
      const slug=it.slug||it.email||'';
      let status;
      if(isLeads) status=`<select class="input" style="padding:4px 8px;font-size:12px" onchange="setLeadStatus('${it.id}',this.value)">${['new','in_progress','contacted','done','rejected'].map(s=>`<option ${it.status===s?'selected':''}>${s}</option>`).join('')}</select>`;
      else if(isReviews) status=`<button onclick="toggleReview('${it.id}',${it.approved===false})" title="${it.approved===false?'Опубликовать на сайте':'Скрыть с сайта'}">${it.approved===false?'🚫 скрыт':'✓ опубликован'}</button>`;
      else status=it.status?`<span class="badge">${esc(it.status)}</span>`:(it.published===false?'<span class="badge" style="background:#999">черновик</span>':'—');
      return `<tr><td>${esc(title)}</td><td>${esc(slug)}</td><td>${status}</td><td><button onclick="editItem('${id}','${it.id}')">Изм.</button> <button onclick="delItem('${id}','${it.id}')">Удл.</button></td></tr>`
    }).join('')}</tbody></table></div>`;
}
async function setLeadStatus(id,status){
  const r=await jput('/api/v1/leads/'+id,{status});
  if(!r.ok) alert(r.data.error||'Ошибка');
}
async function toggleReview(id,approve){
  const r=await jput('/api/v1/reviews/'+id,{approved:approve});
  if(r.ok) nav('reviews'); else alert((r.data||{}).error||'Ошибка');
}
function showForm(col, data={}){
  const box=el('formBox'); box.style.display='';
  const isUser=col==='users';
  const isLead=col==='leads';
  let inner;
  if(isUser){
    inner=`<h3>${data.id?'Редактировать пользователя':'Добавить пользователя'}</h3>
    <label>Email</label><input id="f_email" class="input" value="${esc(data.email||'')}">
    <label style="display:block;margin-top:6px">Имя</label><input id="f_name" class="input" value="${esc(data.name||'')}">
    <label style="display:block;margin-top:6px">${data.id?'Новый пароль (оставьте пустым чтобы не менять)':'Пароль'}</label><input id="f_password" class="input" type="password">
    <label style="display:block;margin-top:6px" title="manager не имеет доступа к backup, users и системным настройкам">Роль ⓘ</label>
    <select id="f_role" class="input">${['editor','manager','admin','super_admin'].map(r=>`<option ${data.role===r?'selected':''}>${r}</option>`).join('')}</select>`;
  } else if(isLead){
    inner=`<h3>Заявка</h3>
    <label>Имя</label><input id="f_title" class="input" value="${esc(data.name||'')}">
    <label style="display:block;margin-top:6px">Телефон</label><input id="f_phone" class="input" value="${esc(data.phone||'')}">
    <label style="display:block;margin-top:6px">Email</label><input id="f_email_l" class="input" value="${esc(data.email||'')}">
    <label style="display:block;margin-top:6px">Сообщение</label><textarea id="f_desc" class="input">${esc(data.message||'')}</textarea>
    <label style="display:block;margin-top:6px" title="Двигайте заявку по воронке продаж">Статус ⓘ</label>
    <select id="f_status" class="input">${['new','in_progress','contacted','done','rejected'].map(s=>`<option ${data.status===s?'selected':''}>${s}</option>`).join('')}</select>
    <label style="display:block;margin-top:6px">Комментарий менеджера</label><textarea id="f_comment" class="input">${esc(data.comment||'')}</textarea>`;
  } else {
    inner=`<h3>${data.id?'Редактировать':'Добавить'} · ${col}</h3>
    <label>Название</label><input id="f_title" class="input" value="${esc(data.title||data.name||'')}">
    <label style="display:block;margin-top:6px" title="Используется в адресе страницы: /p/slug или /project/slug">Slug (адрес) ⓘ</label><input id="f_slug" class="input" value="${esc(data.slug||'')}">
    <label style="display:block;margin-top:6px">Описание</label><textarea id="f_desc" class="input">${esc(data.desc||data.text||'')}</textarea>
    <label style="display:block;margin-top:6px" title='JSON с доп. полями: images, price, featured, published, categoryId…'>Доп. поля JSON (опционально) ⓘ</label>
    <textarea id="f_extra" class="input" rows="3" placeholder='{"price": 850000, "published": true}'></textarea>`;
  }
  box.innerHTML=inner+`<div style="margin-top:12px"><button class="btn" onclick="saveForm('${col}','${data.id||''}')">Сохранить</button>
   <button class="btn btn-ghost" onclick="el('formBox').style.display='none'">Отмена</button><span id="formMsg" style="margin-left:8px"></span></div>`;
  box.dataset.col=col; box.dataset.id=data.id||'';
}
async function editItem(col,id){
  const items=await jget('/api/v1/'+col); const it=(items||[]).find(x=>x.id===id); if(it) showForm(col,it);
}
async function delItem(col,id){
  if(!confirm('Удалить безвозвратно?')) return;
  const r=await jdel('/api/v1/'+col+'/'+id); if(r.ok) nav(cur); else alert((r.data||{}).error||'Ошибка');
}
async function saveForm(col, id){
  let body={};
  if(col==='users'){
    body={email:el('f_email').value, name:el('f_name').value, role:el('f_role').value};
    const pwd=el('f_password').value; if(pwd) body.password=pwd;
    let res;
    if(id) res=await jput('/api/v1/users/'+id,body); else res=await jpost('/api/v1/users',body);
    if(res.ok){ el('formBox').style.display='none'; nav(cur);} else alert(res.data.error||'Ошибка');
    return;
  } else if(col==='leads'){
    body={name:el('f_title').value, phone:el('f_phone').value, email:el('f_email_l').value, message:el('f_desc').value, status:el('f_status').value, comment:el('f_comment').value};
  } else {
    body={title:el('f_title').value, desc:el('f_desc').value};
    const slug=el('f_slug').value.trim(); if(slug) body.slug=slug;
    try{ const extra=JSON.parse(el('f_extra').value||'null'); if(extra) Object.assign(body,extra)}catch(e){ alert('JSON в доп. полях невалиден'); return}
  }
  let res;
  if(id) res=await jput('/api/v1/'+col+'/'+id, body);
  else res=await jpost('/api/v1/'+col, body);
  if(res.ok) { el('formBox').style.display='none'; nav(cur);} else alert(res.data.error||'Error');
}
function quickAdd(col){ nav(col); setTimeout(()=>showForm(col),300)}

/* ============ PAGE BUILDER ============ */
const BLOCK_TYPES=[
  {t:'hero',n:'Hero — заголовок'},{t:'text',n:'Текст'},{t:'features',n:'Преимущества'},
  {t:'gallery',n:'Галерея'},{t:'statistics',n:'Цифры'},{t:'faq',n:'FAQ'},
  {t:'cta',n:'Призыв к действию'},{t:'team',n:'Команда'},{t:'contact',n:'Форма заявки'}
];
let builderPage=null, dragIdx=-1;
async function renderPages(list){
  const pages=await jget('/api/v1/pages')||[];
  list.innerHTML=`<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><b>Страницы</b>
    <button class="btn" onclick="createPage()">+ Новая страница</button></div>
    <table style="margin-top:10px"><thead><tr><th>Заголовок</th><th>Адрес</th><th>Блоков</th><th></th></tr></thead><tbody>
    ${(pages||[]).map(p=>`<tr><td>${esc(p.title)}</td><td>/p/${esc(p.slug)}</td><td>${(p.blocks||[]).length}</td>
      <td><button onclick="openBuilder('${p.id}')">Конструктор</button>
      <a href="/p/${esc(p.slug)}" target="_blank"><button>Открыть ↗</button></a>
      <button onclick="delItem('pages','${p.id}')">Удл.</button></td></tr>`).join('')}
    </tbody></table>
    <div style="font-size:12px;color:#888;margin-top:8px">Совет: собирайте страницу из блоков — Hero сверху, CTA внизу.</div></div>`;
  if(builderPage) openBuilder(builderPage.id,true);
}
async function createPage(){
  const r=await jpost('/api/v1/pages',{title:'Новая страница',slug:'page-'+Date.now().toString(36),blocks:[{type:'hero',data:{title:'Заголовок страницы'}}],published:true});
  if(r.ok){ builderPage=r.data; nav('pages'); }
}
async function openBuilder(id,silent){
  const pages=await jget('/api/v1/pages');
  const p=(pages||[]).find(x=>x.id===id)||builderPage;
  if(!p) return;
  builderPage=JSON.parse(JSON.stringify(p));
  drawBuilder(!silent);
}
function drawBuilder(scroll){
  const p=builderPage; const box=el('list');
  box.innerHTML=`
  <div class="card"><div style="display:flex;justify-content:space-between;align-items:center">
    <b>Конструктор: ${esc(p.title)}</b>
    <div><a href="/p/${esc(p.slug)}" target="_blank"><button class="btn btn-ghost">Предпросмотр ↗</button></a>
    <button class="btn" onclick="savePage()">Сохранить страницу</button>
    <button class="btn btn-ghost" onclick="builderPage=null;nav('pages')">К списку</button></div></div>
    <span id="pbMsg" style="color:green;font-size:13px;margin-left:8px"></span>
    <div class="grid2" style="margin-top:12px">
      <div><label>Заголовок</label><input id="pb_title" class="input" value="${esc(p.title)}"></div>
      <div><label>Адрес (slug)</label><input id="pb_slug" class="input" value="${esc(p.slug)}"></div>
      <div><label>SEO Title ⓘ</label><input id="pb_seoTitle" class="input" title="Заголовок в поисковой выдаче Google/Yandex" value="${esc(p.seoTitle||'')}"></div>
      <div><label>H1 на странице</label><input id="pb_h1" class="input" value="${esc(p.h1||'')}"></div>
    </div>
    <label style="display:block;margin-top:8px" title="Описание для поисковиков, 150–160 символов">SEO Description ⓘ</label>
    <textarea id="pb_seoDesc" class="input" rows="2">${esc(p.seoDesc||'')}</textarea>
  </div>
  <div class="card"><b>Блоки</b> <span style="color:#888;font-size:12px">перетаскивайте ⠿ для сортировки</span>
    <div id="blockList">${p.blocks.map((b,i)=>blockRow(b,i)).join('')}</div>
    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
      <select id="addBlockType" class="input" style="max-width:260px">${BLOCK_TYPES.map(t=>`<option value="${t.t}">${t.n}</option>`).join('')}</select>
      <button class="btn" onclick="addBlock()">+ Добавить блок</button>
    </div></div>`;
  wireDrag();
  if(scroll) window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});
}
function blockRow(b,i){
  const meta=BLOCK_TYPES.find(t=>t.t===b.type)||{};
  return `<div class="block-row card" draggable="true" data-i="${i}" style="padding:12px;margin-top:8px;${b.hidden?'opacity:.5':''}">
    <div style="display:flex;align-items:center;gap:8px">
      <span class="drag-handle" style="cursor:grab">⠿</span>
      <b style="min-width:170px">${meta.n||b.type}</b>
      <span style="flex:1;font-size:12px;color:#888">${esc(shortPreview(b))}</span>
      <button title="Выше" onclick="moveBlock(${i},-1)">↑</button>
      <button title="Ниже" onclick="moveBlock(${i},1)">↓</button>
      <button title="${b.hidden?'Показать':'Скрыть'}" onclick="toggleHide(${i})">${b.hidden?'👁':'🙈'}</button>
      <button title="Дублировать" onclick="dupBlock(${i})">⧉</button>
      <button title="Редактировать" onclick="editBlock(${i})">✎</button>
      <button title="Удалить" style="color:#c00" onclick="delBlock(${i})">×</button>
    </div></div>`;
}
function shortPreview(b){
  const d=b.data||{};
  return d.title||d.text||d.q||((d.items||[])[0]&&(d.items[0].title||d.items[0].q))||'—';
}
function wireDrag(){
  document.querySelectorAll('#blockList .block-row').forEach(row=>{
    row.addEventListener('dragstart',e=>{dragIdx=+row.dataset.i; row.style.opacity=.4});
    row.addEventListener('dragend',()=>{row.style.opacity=1});
    row.addEventListener('dragover',e=>e.preventDefault());
    row.addEventListener('drop',e=>{
      e.preventDefault(); const to=+row.dataset.i;
      if(to===dragIdx) return;
      const [moved]=builderPage.blocks.splice(dragIdx,1);
      builderPage.blocks.splice(to,0,moved);
      drawBuilder(false); savePage(true);
    });
  });
}
function moveBlock(i,d){
  const j=i+d; const arr=builderPage.blocks;
  if(j<0||j>=arr.length) return;
  [arr[i],arr[j]]=[arr[j],arr[i]];
  drawBuilder(false); savePage(true);
}
function toggleHide(i){ builderPage.blocks[i].hidden=!builderPage.blocks[i].hidden; drawBuilder(false); savePage(true);}
function dupBlock(i){ const copy=JSON.parse(JSON.stringify(builderPage.blocks[i])); builderPage.blocks.splice(i+1,0,copy); drawBuilder(false); savePage(true);}
function delBlock(i){ if(!confirm('Удалить блок?')) return; builderPage.blocks.splice(i,1); drawBuilder(false); savePage(true);}
function addBlock(){
  const t=el('addBlockType').value;
  builderPage.blocks.push({type:t,data:defaultData(t)});
  drawBuilder(false); savePage(true);
}
function defaultData(t){
  if(t==='hero') return {title:'Заголовок',subtitle:'',ctaLabel:'',ctaUrl:''};
  if(t==='text') return {text:'Текст раздела'};
  if(['features','statistics','team'].includes(t)) return {items:[{title:'Пункт',desc:'',kicker:''}]};
  if(t==='faq') return {items:[{q:'Вопрос?',a:'Ответ'}]};
  if(t==='gallery') return {images:[]};
  if(t==='cta') return {title:'Готовы обсудить проект?',subtitle:'',ctaLabel:'Связаться',ctaUrl:'#contacts'};
  return {};
}
function editBlock(i){
  const b=builderPage.blocks[i]; const d=b.data||{};
  const itemFields=(key,qk,ak,kick)=>`
    <label>Элементы (${(d.items||[]).length})</label>
    <div id="itemsBox">${(d.items||[]).map((it,j)=>itemRow(it,j,key,qk,ak,kick)).join('')}</div>
    <button class="btn btn-ghost" style="margin-top:6px" onclick="addItem('${key}','${qk||''}','${ak||''}','${kick||''}')">+ элемент</button>`;
  const fields={
    hero:`<label>Заголовок</label><input id="eb_0" class="input" value="${esc(d.title||'')}">
      <label style="display:block;margin-top:6px">Подзаголовок</label><input id="eb_1" class="input" value="${esc(d.subtitle||'')}">
      <label style="display:block;margin-top:6px">Кнопка (текст)</label><input id="eb_2" class="input" value="${esc(d.ctaLabel||'')}">
      <label style="display:block;margin-top:6px">Кнопка (ссылка)</label><input id="eb_3" class="input" value="${esc(d.ctaUrl||'')}">`,
    text:`<label>Текст</label><textarea id="eb_0" class="input" rows="4">${esc(d.text||'')}</textarea>`,
    features:itemFields('items'),
    statistics:itemFields('items'),
    team:itemFields('items',null,null,'role'),
    faq:itemFields('items','q','a'),
    cta:`<label>Заголовок</label><input id="eb_0" class="input" value="${esc(d.title||'')}">
      <label style="display:block;margin-top:6px">Подзаголовок</label><input id="eb_1" class="input" value="${esc(d.subtitle||'')}">
      <label style="display:block;margin-top:6px">Кнопка (текст)</label><input id="eb_2" class="input" value="${esc(d.ctaLabel||'')}">
      <label style="display:block;margin-top:6px">Кнопка (ссылка)</label><input id="eb_3" class="input" value="${esc(d.ctaUrl||'')}">`,
    gallery:`<label>Ссылки на изображения (по одному в строке, можно из Медиа)</label>
      <textarea id="eb_0" class="input" rows="4">${esc((d.images||[]).map(u=>typeof u==='string'?u:u.url).join('\n'))}</textarea>
      <div style="font-size:12px;color:#888">Совет: загрузите картинки в «🖼 Медиа» и вставьте их URL сюда.</div>`,
    contact:`<div style="color:#888">Форма заявки подключается автоматически. Поля настраивать не нужно.</div>`
  };
  el('formBox').innerHTML=`<h3>Блок: ${(BLOCK_TYPES.find(t=>t.t===b.type)||{}).n||b.type}</h3>
    ${fields[b.type]||''}
    <div style="margin-top:10px"><button class="btn" onclick="saveBlock(${i})">Применить</button>
    <button class="btn btn-ghost" onclick="el('formBox').style.display='none'">Закрыть</button></div>`;
  el('formBox').style.display='';
  el('formBox').scrollIntoView({behavior:'smooth',block:'nearest'});
}
function itemRow(it,j,titleKey,qKey,aKey,kickKey){
  return `<div class="card" style="padding:8px;margin-top:6px">
    <input class="input eb-item" data-j="${j}" data-k="${titleKey||'title'}" placeholder="Заголовок" value="${esc(it[titleKey||'title']||'')}">
    ${qKey?`<input class="input eb-item" data-j="${j}" data-k="a" placeholder="Ответ" value="${esc(it.a||'')}" style="margin-top:4px">`:
    `<input class="input eb-item" data-j="${j}" data-k="desc" placeholder="Описание" value="${esc(it.desc||'')}" style="margin-top:4px">`}
    ${kickKey!==undefined?`<input class="input eb-item" data-j="${j}" data-k="${kickKey}" placeholder="${kickKey==='role'?'Роль':'Надзаголовок'}" value="${esc(it[kickKey]||'')}" style="margin-top:4px">`:''}
    <button style="margin-top:4px;font-size:12px;color:#c00" onclick="this.parentNode.remove()">удалить элемент</button></div>`;
}
function addItem(key,qKey,aKey,kickKey){
  const box=el('itemsBox');
  const div=document.createElement('div');
  div.innerHTML=itemRow({},box.children.length,'title',qKey,aKey,kickKey);
  box.appendChild(div.firstChild);
}
function collectItems(key,qKey,kickKey){
  return [...document.querySelectorAll('.eb-item')].reduce((acc,inp)=>{
    const j=+inp.dataset.j, k=inp.dataset.k;
    acc[j]=acc[j]||{}; acc[j][k]=inp.value; return acc;
  },[]).filter(Boolean);
}
function saveBlock(i){
  const b=builderPage.blocks[i]; const d=b.data||{};
  const v=n=>{ const e=el('eb_'+n); return e?e.value:undefined };
  switch(b.type){
    case 'hero': case 'cta':
      b.data={title:v(0),subtitle:v(1),ctaLabel:v(2),ctaUrl:v(3)}; break;
    case 'text': b.data={text:v(0)}; break;
    case 'features': b.data={items:collectItems('items')}; break;
    case 'statistics': b.data={items:collectItems('items').map(x=>({title:x.title,value:x.title,label:x.desc}))}; break;
    case 'team': b.data={items:collectItems('items',null,'role')}; break;
    case 'faq': b.data={items:collectItems('items','q').map(x=>({q:x.title,a:x.a}))}; break;
    case 'gallery': b.data={images:v(0).split('\n').map(s=>s.trim()).filter(Boolean)}; break;
  }
  el('formBox').style.display='none';
  drawBuilder(false); savePage(true);
}
async function savePage(silent){
  const p=builderPage;
  p.title=el('pb_title').value; p.slug=el('pb_slug').value.trim();
  p.seoTitle=el('pb_seoTitle').value; p.h1=el('pb_h1').value; p.seoDesc=el('pb_seoDesc').value;
  const r=await jput('/api/v1/pages/'+p.id,p);
  const msg=el('pbMsg');
  if(msg) msg.textContent=r.ok?(silent?'✓ автосохранение':'✓ Сохранено'):'Ошибка сохранения';
}

/* ============ SEO AUDIT ============ */
async function renderSEO(list){
  list.innerHTML='<div class="card"><b>SEO Audit</b><div style="margin-top:8px">Проверяем…</div></div>';
  const a=await jget('/api/v1/seo/audit');
  const color=a.score>=80?'green':a.score>=50?'#C9A86A':'#c00';
  list.innerHTML=`<div class="card"><div style="display:flex;align-items:center;gap:14px">
    <div style="font-family:serif;font-size:44px;color:${color}">${a.score}</div>
    <div><b>SEO-здоровье сайта</b><div style="font-size:13px;color:#666">Проверено страниц: ${a.checked}. Проблем: ${a.issues.length}</div></div>
    <button class="btn btn-ghost" style="margin-left:auto" onclick="renderSEO(document.getElementById('list'))">Перепроверить</button></div>
    ${a.issues.length?a.issues.map(i=>`<div class="card" style="padding:10px;margin-top:8px;border-color:#f0d0d0"><b>${esc(i.severity==='high'?'🔴':i.severity==='medium'?'🟡':'🔵')} ${esc(i.where)}</b><div style="font-size:13px;color:#666">${esc(i.problem)}</div></div>`).join(''):
    '<div style="color:green;margin-top:10px">✓ Критичных проблем не найдено</div>'}
  </div>
  <div class="card"><b>Технические файлы</b>
    <div style="margin-top:6px"><a href="/api/v1/seo/sitemap.xml" target="_blank">sitemap.xml ↗</a> · <a href="/robots.txt" target="_blank">robots.txt ↗</a></div>
  </div>`;
}

/* ============ SYSTEM (health/update/logs) ============ */
async function renderSystem(list){
  const h=await jget('/api/v1/health');
  const up=await jget('/api/v1/system/update/check');
  const logs=await jget('/api/v1/audit_log')||[];
  list.innerHTML=`<div class="grid2">
  <div class="card"><b>System Health</b>
    <table style="margin-top:8px">${['Database','Storage','API','Cache'].map(k=>`<tr><td>${k}</td><td>🟢 Работает</td></tr>`).join('')}
    <tr><td>Backup</td><td>${(await jget('/api/v1/backup/list')).length>0?'🟢 Есть бэкапы':'🟡 Нет ни одного бэкапа'}</td></tr></table>
    <div style="font-size:12px;color:#888;margin-top:6px">Версия API: ${esc(h.version||'')} · Node: ${esc(h.node||process.version||'')}</div>
  </div>
  <div class="card"><b>Обновления</b>
    <div style="margin-top:6px">Установлена: <b>v${esc(up.current||'1.0.0')}</b> · Последняя: <b>v${esc(up.latest||'?')}</b></div>
    ${up.updateAvailable
      ? `<button class="btn" style="margin-top:10px" onclick="runUpdate()">Обновить (авто-бэкап + откат при ошибке)</button>`
      : `<div style="color:green;margin-top:8px;font-size:13px">✓ У вас последняя версия</div>`}
    <div id="updMsg" style="font-size:13px;margin-top:8px;color:#555"></div>
    <div style="font-size:12px;color:#888;margin-top:8px">Перед обновлением автоматически создаётся бэкап. При ошибке — автоматический откат.</div>
  </div></div>
  <div class="card"><b>Audit Log</b><table style="margin-top:8px"><tr><th>Когда</th><th>Кто</th><th>Действие</th><th>Объект</th></tr>
    ${logs.slice(-12).reverse().map(l=>`<tr><td>${esc((l.createdAt||'').slice(0,19))}</td><td>${esc(l.userId||'')}</td><td>${esc(l.action)}</td><td>${esc(l.entity)} ${esc(l.entityId||'')}</td></tr>`).join('')}</table>
  </div>`;
}
async function runUpdate(){
  el('updMsg').textContent='Обновляем: бэкап → pull → миграции → проверка…';
  const r=await jpost('/api/v1/system/update/run');
  el('updMsg').innerHTML=r.ok
    ? `✓ Обновлено до v${r.data.version}. Перезапустите сервер (pm2 restart meb), затем обновите страницу.`
    : `<span style="color:#c00">Ошибка: ${esc(r.data.error)}. Выполнен откат к бэкапу ${esc(r.data.restoredFrom||'')}</span>`;
}
checkMe();
