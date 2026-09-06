let token = localStorage.getItem('token') || '';
let csrf = localStorage.getItem('csrf') || '';

const cols = [
  { id: 'dashboard', icon: '📊', label: 'Главная', g: 'main' },
  { id: 'pages',     icon: '📄', label: 'Страницы', g: 'content' },
  { id: 'catalog',   icon: '🪑', label: 'Каталог', g: 'content' },
  { id: 'projects',  icon: '🏗️', label: 'Проекты', g: 'content' },
  { id: 'materials', icon: '🧱', label: 'Материалы', g: 'content' },
  { id: 'services',  icon: '🛠️', label: 'Услуги', g: 'content' },
  { id: 'reviews',   icon: '⭐', label: 'Отзывы', g: 'content' },
  { id: 'leads',     icon: '📩', label: 'Заявки', g: 'aud' },
  { id: 'users',     icon: '👥', label: 'Пользователи', g: 'aud' },
  { id: 'menu_items', icon: '🧭', label: 'Меню', g: 'aud' },
  { id: 'banners',   icon: '🎯', label: 'Баннеры', g: 'aud' },
  { id: 'media',     icon: '🖼️', label: 'Медиа', g: 'aud' },
  { id: 'seo',       icon: '🔍', label: 'SEO', g: 'sys' },
  { id: 'settings',  icon: '⚙️', label: 'Настройки', g: 'sys' },
  { id: 'system',    icon: '🖥️', label: 'Система', g: 'sys' },
];

let cur = 'dashboard';
let _bellInterval = null;

function hdr() { return token ? { Authorization: 'Bearer ' + token } : {}; }
function csfHdr() { return csrf ? { 'X-CSRF-Token': csrf } : {}; }
function _unwrap(j) { return j && j.data !== undefined ? j.data : j; }
async function jget(p) { try { const r = await fetch(p, { headers: hdr() }); const j = await r.json(); return j.success !== false ? _unwrap(j) : null; } catch(e) { return null; } }
async function jpost(p, b) { try { console.debug('[MEB] POST', p, { auth_present: !!token, csrf_present: !!csrf }); const r = await fetch(p, { method: 'POST', headers: { 'Content-Type': 'application/json', ...hdr(), ...csfHdr() }, body: JSON.stringify(b || {}) }); const j = await r.json(); return { ok: r.ok && j.success !== false, data: _unwrap(j), status: r.status, error: j.error || '' }; } catch(e) { return { ok: false, data: null, status: 0, error: String((e && e.message) || e) }; } }
async function jput(p, b) { try { console.debug('[MEB] PUT', p, { auth_present: !!token, csrf_present: !!csrf }); const r = await fetch(p, { method: 'PUT', headers: { 'Content-Type': 'application/json', ...hdr(), ...csfHdr() }, body: JSON.stringify(b || {}) }); const j = await r.json(); return { ok: r.ok && j.success !== false, data: _unwrap(j), status: r.status, error: j.error || '' }; } catch(e) { return { ok: false, data: null, status: 0, error: String((e && e.message) || e) }; } }
async function jdel(p) { try { console.debug('[MEB] DELETE', p, { auth_present: !!token, csrf_present: !!csrf }); const r = await fetch(p, { method: 'DELETE', headers: { ...hdr(), ...csfHdr() } }); const j = await r.json(); return { ok: r.ok && j.success !== false, data: _unwrap(j), status: r.status, error: j.error || '' }; } catch(e) { return { ok: false, data: null, status: 0, error: String((e && e.message) || e) }; } }

const el = id => document.getElementById(id);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
// null/undefined default (replaces the ES2020 ?? operator — Node 8.10 + older
// browsers cannot parse ??)
const dv = (v, d) => (v === undefined || v === null ? d : v);

/* ============ TOAST ============ */
let toastTimer = null;
function toast(msg, type = 'info') {
  const t = el('adminToast');
  clearTimeout(toastTimer);
  t.className = 'toast ' + type;
  t.textContent = msg;
  requestAnimationFrame(() => t.classList.add('show'));
  toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

/* ============ MEDIA PICKER MODAL ============ */
let _mediaPickerCb = null;

function openMediaPicker(callback) {
  _mediaPickerCb = callback;
  const overlay = document.createElement('div');
  overlay.id = 'mediaPickerOverlay';
  overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px)';
  overlay.onclick = e => { if (e.target === overlay) closeMediaPicker(); };
  overlay.innerHTML = `<div style="background:var(--card);border-radius:12px;width:90vw;max-width:900px;max-height:85vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--line)">
      <b style="font-size:15px">Выберите изображение</b>
      <button class="btn btn-sm btn-ghost" onclick="closeMediaPicker()">✕</button>
    </div>
    <div style="padding:12px 20px;border-bottom:1px solid var(--line);display:flex;gap:8px;align-items:center">
      <input id="mpSearch" class="input" placeholder="Поиск файлов..." style="flex:1" oninput="loadPickerMedia(this.value)">
      <select id="mpFolder" class="input" style="max-width:160px" onchange="loadPickerMedia(el('mpSearch').value)"><option value="">Все папки</option></select>
    </div>
    <div id="mpGrid" style="flex:1;overflow-y:auto;padding:16px 20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px"></div>
    <div style="padding:12px 20px;border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
      <div style="display:flex;gap:8px;align-items:center">
        <input type="file" id="mpFileIn" accept="image/*" style="display:none">
        <button class="btn btn-sm btn-ghost" onclick="el('mpFileIn').click()">+ Загрузить новое</button>
        <span id="mpUploadMsg" style="font-size:12px;color:var(--muted)"></span>
      </div>
      <button class="btn btn-sm btn-ghost" onclick="closeMediaPicker()">Отмена</button>
    </div>
  </div>`;
  document.body.appendChild(overlay);
  el('mpFileIn').onchange = async () => {
    const f = el('mpFileIn').files[0];
    if (!f) return;
    el('mpUploadMsg').textContent = 'Загрузка...';
    const b64 = await new Promise(res => { const r = new FileReader(); r.onload = () => res(r.result); r.readAsDataURL(f); });
    const r = await jpost('/api/v1/media/upload', { filename: f.name, data: b64, alt: '', folder: '' });
    if (r.ok && r.data) {
      el('mpUploadMsg').textContent = '✓ Загружено';
      loadPickerMedia('');
      if (_mediaPickerCb) { _mediaPickerCb(r.data); closeMediaPicker(); }
    } else {
      el('mpUploadMsg').textContent = 'Ошибка';
    }
  };
  loadPickerMedia('');
  loadPickerFolders();
}

function closeMediaPicker() {
  const o = el('mediaPickerOverlay');
  if (o) o.remove();
  _mediaPickerCb = null;
}

async function loadPickerFolders() {
  const items = await jget('/api/v1/media') || [];
  const folders = [...new Set((items || []).map(m => m.folder || '').filter(Boolean))];
  const sel = el('mpFolder');
  if (sel) sel.innerHTML = '<option value="">Все папки</option>' + folders.map(f => `<option value="${esc(f)}">${esc(f)}</option>`).join('');
}

async function loadPickerMedia(q) {
  const folder = el('mpFolder') ? el('mpFolder').value : '';
  const fp = folder ? '&folder=' + encodeURIComponent(folder) : '';
  const items = await jget('/api/v1/media?x=1' + fp) || [];
  const f = (items || []).filter(m => !q || (m.original_name || '').toLowerCase().includes(q.toLowerCase()));
  const g = el('mpGrid');
  if (g) g.innerHTML = f.map(m =>
    `<div class="card" style="padding:0;overflow:hidden;cursor:pointer;transition:all .2s" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,.15)'" onmouseout="this.style.boxShadow=''" onclick="pickMediaItem('${esc(m.url)}','${esc(m.alt || '')}','${esc(m.id)}')">
      <div style="aspect-ratio:1;overflow:hidden;background:var(--linen)">
        <img src="${m.url}" loading="lazy" alt="${esc(m.alt)}" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div style="padding:6px 8px">
        <div style="font-size:10px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(m.original_name)}</div>
        <div style="font-size:9px;color:var(--muted);margin-top:1px">${m.width ? m.width + '×' + m.height + ' · ' : ''}${Math.round((m.size || 0) / 1024)} KB</div>
      </div>
    </div>`
  ).join('') || '<div class="empty-state" style="grid-column:1/-1;padding:30px"><div style="font-size:28px;opacity:.3;margin-bottom:6px">📁</div><p style="font-size:13px;color:var(--muted)">Нет файлов. Загрузите изображение.</p></div>';
}

function pickMediaItem(url, alt, id) {
  if (_mediaPickerCb) {
    _mediaPickerCb({ url, alt, id });
    closeMediaPicker();
  }
}

/* ============ IMAGE PICKER WIDGET ============ */
function imagePickerRow(fieldId, currentUrl, label) {
  const preview = currentUrl
    ? `<div style="margin-top:8px;display:flex;align-items:center;gap:10px;padding:8px;background:var(--linen);border-radius:8px">
        <img src="${esc(currentUrl)}" style="height:48px;width:48px;border-radius:6px;object-fit:cover">
        <div style="flex:1;font-size:12px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(currentUrl)}</div>
        <button class="btn btn-sm btn-ghost" style="font-size:11px" onclick="el('${fieldId}').value='';this.parentNode.remove()">Удалить</button>
       </div>`
    : '';
  return `<label>${label || 'Изображение'}</label>
    <div style="display:flex;gap:6px;align-items:center">
      <input id="${fieldId}" class="input" value="${esc(currentUrl || '')}" placeholder="URL изображения" style="flex:1">
      <button class="btn btn-sm btn-ghost" type="button" onclick="openMediaPicker(function(m){el('${fieldId}').value=m.url;el('${fieldId}').dispatchEvent(new Event('change'))})">Выбрать</button>
    </div>
    <div id="${fieldId}_preview">${preview}</div>`;
}

function imagePickerRowOnChange(fieldId) {
  const inp = el(fieldId);
  if (!inp) return;
  inp.addEventListener('change', function() {
    const prev = el(fieldId + '_preview');
    if (prev) {
      prev.innerHTML = this.value ? `<div style="margin-top:8px;display:flex;align-items:center;gap:10px;padding:8px;background:var(--linen);border-radius:8px"><img src="${esc(this.value)}" style="height:48px;width:48px;border-radius:6px;object-fit:cover"><div style="flex:1;font-size:12px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(this.value)}</div></div>` : '';
    }
  });
}

/* ============ MULTI-IMAGE PICKER ============ */
function multiImagePicker(fieldId, urls, label) {
  const arr = Array.isArray(urls) ? urls : [];
  const items = arr.map((u, i) =>
    `<div class="mip-item" data-i="${i}" draggable="true" style="position:relative;border-radius:8px;overflow:hidden;background:var(--linen);aspect-ratio:1;cursor:grab">
      <img src="${esc(typeof u === 'string' ? u : u.url || '')}" style="width:100%;height:100%;object-fit:cover">
      <button onclick="removeMultiImage('${fieldId}',${i})" style="position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.6);color:#fff;border:none;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center">✕</button>
    </div>`
  ).join('');
  return `<label>${label || 'Изображения'}</label>
    <input type="hidden" id="${fieldId}" value='${esc(JSON.stringify(arr))}'>
    <div id="${fieldId}_grid" class="mip-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:8px;margin-top:8px">${items}</div>
    <button class="btn btn-sm btn-ghost" style="margin-top:8px" type="button" onclick="openMultiImagePicker('${fieldId}')">+ Добавить изображения</button>`;
}

function openMultiImagePicker(fieldId) {
  _mediaPickerCb = function(m) {
    const inp = el(fieldId);
    if (!inp) return;
    let arr = [];
    try { arr = JSON.parse(inp.value || '[]'); } catch(e) { arr = []; }
    arr.push(m.url);
    inp.value = JSON.stringify(arr);
    refreshMultiImageGrid(fieldId);
  };
  openMediaPicker(_mediaPickerCb);
}

function removeMultiImage(fieldId, idx) {
  const inp = el(fieldId);
  if (!inp) return;
  let arr = [];
  try { arr = JSON.parse(inp.value || '[]'); } catch(e) { arr = []; }
  arr.splice(idx, 1);
  inp.value = JSON.stringify(arr);
  refreshMultiImageGrid(fieldId);
}

function refreshMultiImageGrid(fieldId) {
  const inp = el(fieldId);
  const grid = el(fieldId + '_grid');
  if (!inp || !grid) return;
  let arr = [];
  try { arr = JSON.parse(inp.value || '[]'); } catch(e) { arr = []; }
  grid.innerHTML = arr.map((u, i) =>
    `<div class="mip-item" data-i="${i}" draggable="true" style="position:relative;border-radius:8px;overflow:hidden;background:var(--linen);aspect-ratio:1;cursor:grab">
      <img src="${esc(typeof u === 'string' ? u : u.url || '')}" style="width:100%;height:100%;object-fit:cover">
      <button onclick="removeMultiImage('${fieldId}',${i})" style="position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.6);color:#fff;border:none;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center">✕</button>
    </div>`
  ).join('') || '<div style="font-size:12px;color:var(--muted);padding:12px;text-align:center">Нет изображений</div>';
  wireMultiImageDrag(fieldId);
}

function wireMultiImageDrag(fieldId) {
  const grid = el(fieldId + '_grid');
  if (!grid) return;
  let dragEl = null;
  grid.querySelectorAll('.mip-item').forEach(item => {
    item.addEventListener('dragstart', e => { dragEl = item; item.style.opacity = '.4'; e.dataTransfer.effectAllowed = 'move'; });
    item.addEventListener('dragend', () => { item.style.opacity = '1'; dragEl = null; });
    item.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
    item.addEventListener('drop', e => {
      e.preventDefault();
      if (!dragEl || dragEl === item) return;
      const inp = el(fieldId);
      if (!inp) return;
      let arr = [];
      try { arr = JSON.parse(inp.value || '[]'); } catch(ex) { arr = []; }
      const from = +dragEl.dataset.i, to = +item.dataset.i;
      if (from === to) return;
      const [moved] = arr.splice(from, 1);
      arr.splice(to, 0, moved);
      inp.value = JSON.stringify(arr);
      refreshMultiImageGrid(fieldId);
    });
  });
}

/* ============ SIDEBAR ============ */
const menuGroups = [
  ['main', 'Главная'],
  ['content', 'Контент'],
  ['aud', 'Клиенты и медиа'],
  ['sys', 'Система']
];

function renderMenu() {
  const nav = el('sidebarNav');
  nav.innerHTML = menuGroups.map(([g, label]) => {
    const items = cols.filter(c => (c.g || 'main') === g);
    if (!items.length) return '';
    return (
      '<div class="sidebar-group">' + label + '</div>' +
      items.map(c =>
        `<a href="#" class="sidebar-link${cur === c.id ? ' active' : ''}" onclick="nav('${c.id}');return false">
          <span class="icon">${c.icon}</span>${c.label}
        </a>`
      ).join('')
    );
  }).join('');
}

function setPageTitle() {
  const item = cols.find(c => c.id === cur);
  if (el('pageTitle') && item) el('pageTitle').textContent = item.label;
}

/* ============ AUTH ============ */
async function checkMe() {
  const r = await fetch('/api/v1/me', { headers: hdr() });
  if (r.ok) {
    const j = await r.json();
    const u = _unwrap(j);
    el('who').textContent = (u.name || '—') + ' · ' + (u.role || '—');
    el('login').style.display = 'none';
    el('app').style.display = '';
    renderMenu();
    setPageTitle();
    nav(cur);
    refreshBell();
    if (_bellInterval) clearInterval(_bellInterval);
    _bellInterval = setInterval(refreshBell, 30000);
  } else {
    el('login').style.display = '';
    el('app').style.display = 'none';
  }
}

async function doLogin() {
  const email = el('email').value;
  const password = el('pwd').value;
  if (!email || !password) { el('loginMsg').textContent = 'Заполните все поля'; return; }
  const r = await fetch('/api/v1/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password }) });
  const j = await r.json();
  if (!r.ok) { el('loginMsg').textContent = j.error || 'Ошибка входа'; return; }
  const d = _unwrap(j);
  token = d.token;
  csrf = d.csrf || '';
  localStorage.setItem('token', token);
  if (csrf) localStorage.setItem('csrf', csrf); else localStorage.removeItem('csrf');
  checkMe();
}

function logout() {
  localStorage.removeItem('token');
  localStorage.removeItem('csrf');
  csrf = '';
  token = '';
  fetch('/api/v1/auth/logout', { method: 'POST', headers: hdr() }).catch(() => {});
  checkMe();
}

/* ============ DARK MODE ============ */
function toggleDark() {
  document.body.classList.toggle('dark');
  localStorage.setItem('meb_dark', document.body.classList.contains('dark') ? '1' : '0');
}
if (localStorage.getItem('meb_dark') === '1') document.body.classList.add('dark');

/* ============ NOTIFICATIONS ============ */
async function refreshBell() {
  try {
    const notes = await jget('/api/v1/notifications') || [];
    const unread = notes.filter(n => !n.read).length;
    const c = el('bellCnt');
    if (c) { c.textContent = unread; c.style.display = unread ? 'block' : 'none'; }
  } catch (e) {}
}

function toggleNotifications() {
  const box = el('notifBox');
  if (box.style.display === 'none' || box.style.display === '') {
    box.style.display = 'block';
    loadNotifications();
  } else {
    box.style.display = 'none';
  }
}

async function loadNotifications() {
  const notes = await jget('/api/v1/notifications') || [];
  el('notifList').innerHTML = notes.slice(0, 10).map(n =>
    `<div class="card" style="padding:10px;margin-top:8px;${n.read ? '' : 'border-left:3px solid var(--brass)'}">
      <div style="font-size:13px;font-weight:600">${esc(n.title)}</div>
      <div style="font-size:12px;color:var(--muted);margin-top:2px">${esc(n.body)}</div>
      <div style="font-size:11px;color:var(--muted);margin-top:4px;display:flex;justify-content:space-between;align-items:center">
        <span>${esc((n.created_at || '').slice(0, 19))}</span>
        <span>
          ${n.read ? '' : '<button class="btn btn-sm btn-ghost" onclick="readOne(\'' + esc(n.id) + '\')" style="font-size:11px;padding:2px 6px">✓ Прочитано</button> '}
          <button class="btn btn-sm btn-ghost" onclick="delOne(\'' + esc(n.id) + '\')" style="font-size:11px;padding:2px 6px;color:var(--danger)">✕</button>
        </span>
      </div>
    </div>`
  ).join('') || '<div class="empty-state" style="padding:20px">Пока нет уведомлений</div>';
}

async function readOne(id) {
  await jput('/api/v1/notifications/' + id, {});
  loadNotifications();
  refreshBell();
}

async function delOne(id) {
  await jdel('/api/v1/notifications/' + id);
  loadNotifications();
  refreshBell();
  toast('Уведомление удалено', 'info');
}

async function markRead() {
  await jput('/api/v1/notifications/read-all', {});
  loadNotifications();
  refreshBell();
  toast('Уведомления прочитаны', 'success');
}

/* ============ NAV ============ */
async function nav(id) {
  cur = id;
  renderMenu();
  setPageTitle();
  const dash = el('dash'), list = el('list'), form = el('formBox');
  form.style.display = 'none';
  dash.innerHTML = '';
  list.innerHTML = '';
  if (id === 'dashboard') return renderDashboard(dash, list);
  if (id === 'settings') return renderSettings(list);
  if (id === 'backup') return renderBackup(list);
  if (id === 'media') return renderMedia(list);
  if (id === 'pages') return renderPages(list);
  if (id === 'seo') return renderSEO(list);
  if (id === 'system') return renderSystem(list);
  if (id === 'menu_items') return renderMenuItems(list);
  if (id === 'banners') return renderBanners(list);
  return renderCrud(id, list);
}

function showQuickAdd() {
  const quickCols = ['projects', 'catalog', 'materials', 'services'];
  if (quickCols.includes(cur)) showForm(cur);
  else nav('projects');
}

/* ============ DASHBOARD ============ */
async function renderDashboard(dash, list) {
  const s = await jget('/api/v1/analytics/summary') || {};
  const h = await jget('/api/v1/health') || {};
  const views = (s.views || {});
  const max = Math.max(1, ...(views.days || []).map(d => d.views));
  const bars = (views.days || []).slice(-14).map(d =>
    `<div style="flex:1;display:flex;align-items:end;justify-content:center">
      <div title="${d.date}: ${d.views}" style="width:70%;height:${Math.round(d.views / max * 46) + 3}px;background:var(--brass);border-radius:3px 3px 0 0;transition:height .4s ease"></div>
    </div>`
  ).join('');

  dash.innerHTML = `
    <div style="margin-bottom:20px">
      <div style="font-family:'Cormorant Garamond',serif;font-size:28px;font-weight:500;letter-spacing:-.01em">Добро пожаловать</div>
      <div style="font-size:13px;color:var(--muted);margin-top:2px">Обзор системы и контента</div>
    </div>
    <div class="grid3">
      <div class="card stat-card">
        <div class="stat-value">${s.leads || 0}</div>
        <div class="stat-label">Заявки</div>
      </div>
      <div class="card stat-card">
        <div class="stat-value">${s.projects || 0}</div>
        <div class="stat-label">Проекты</div>
      </div>
      <div class="card stat-card">
        <div class="stat-value">${s.catalog || 0}</div>
        <div class="stat-label">Каталог</div>
      </div>
    </div>
    <div class="grid2">
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <b style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)">Посетители</b>
          <span style="color:var(--muted);font-size:12px">всего ${views.total || 0} · сегодня ${views.today || 0}</span>
        </div>
        <div style="display:flex;gap:3px;height:52px;margin-top:14px;align-items:end">${bars || '<span style="color:var(--muted);font-size:12px">Данных пока нет</span>'}</div>
        <div style="font-size:11px;color:var(--muted);margin-top:8px;letter-spacing:.04em">Просмотры за 14 дней</div>
      </div>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <b style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)">Система</b>
          <span class="badge ${h.status === 'ok' ? 'badge-success' : 'badge-warning'}">${h.status === 'ok' ? 'Работает' : 'Требует внимания'}</span>
        </div>
        <div style="font-size:13px;color:var(--muted);margin-top:10px">БД: ${h.database && h.database.status === 'ok' ? 'работает' : 'ошибка'} · Хранилище: ${h.storage && h.storage.status === 'ok' ? 'работает' : 'ошибка'}</div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px">Версия: ${esc(h.version || '1.0.0')}</div>
      </div>
    </div>
    <div class="card">
      <b style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)">Быстрые действия</b>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <button class="btn btn-sm" onclick="quickAdd('projects')">+ Проект</button>
        <button class="btn btn-sm" onclick="quickAdd('catalog')">+ Каталог</button>
        <button class="btn btn-sm" onclick="quickAdd('materials')">+ Материал</button>
        <button class="btn btn-sm" onclick="quickAdd('services')">+ Услуга</button>
        <button class="btn btn-sm btn-ghost" onclick="nav('leads')">Заявки</button>
        <button class="btn btn-sm btn-ghost" onclick="doBackup()">Бэкап</button>
      </div>
    </div>`;

  const leads = await jget('/api/v1/leads');
  list.innerHTML = `<div class="card">
    <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Заявки</div>
    <b style="font-size:15px">Последние заявки</b>
    <table style="margin-top:12px">
      <thead><tr><th>Имя</th><th>Телефон</th><th>Статус</th><th></th></tr></thead>
      <tbody>${(leads || []).slice(0, 5).map(l =>
        `<tr>
          <td><b>${esc(l.name)}</b></td>
          <td>${esc(l.phone)}</td>
          <td><span class="badge badge-${l.status === 'done' ? 'success' : l.status === 'rejected' ? 'danger' : 'warning'}">${esc(l.status)}</span></td>
          <td><button class="btn btn-sm btn-ghost" onclick="editLead('${l.id}')">Открыть</button></td>
        </tr>`
      ).join('') || '<tr><td colspan="4" class="empty-state">Нет заявок</td></tr>'}</tbody>
    </table>
  </div>`;
}

async function editLead(id) {
  nav('leads');
  setTimeout(async () => {
    const items = await jget('/api/v1/leads');
    const it = (items || []).find(x => x.id === id);
    if (it) showForm('leads', it);
  }, 300);
}

/* ============ SETTINGS ============ */
async function renderSettings(list) {
  const s = await jget('/api/v1/settings') || {};
  const smtp = s.smtp || {}, fcm = s.fcm || {}, socials = s.socials || {}, og = s.og || {}, seo = s.seo || {}, analytics = s.analytics || {};

  const logoSection = s.logo
    ? `<div style="margin-top:12px;padding:16px;background:var(--linen);border-radius:8px;text-align:center">
        <img src="${esc(s.logo)}" style="max-height:48px;margin-bottom:8px">
        <div style="font-size:11px;color:var(--muted);margin-bottom:8px">Текущий логотип</div>
        <button class="btn btn-sm btn-danger" onclick="clearSetting('logo')">Удалить логотип</button>
       </div>`
    : `<div style="margin-top:12px;padding:24px;background:var(--linen);border-radius:8px;text-align:center;color:var(--muted);font-size:13px">Логотип не загружен</div>`;

  const faviconSection = s.favicon
    ? `<div style="margin-top:12px;display:flex;align-items:center;gap:12px;padding:12px;background:var(--linen);border-radius:8px">
        <img src="${esc(s.favicon)}" style="height:24px;width:24px;border-radius:4px;object-fit:cover">
        <span style="font-size:12px;color:var(--muted);flex:1">Текущий favicon</span>
        <button class="btn btn-sm btn-danger" onclick="clearSetting('favicon')">Удалить</button>
       </div>`
    : '';

  list.innerHTML = `
  <div class="card">
    <b style="font-size:15px">Логотип и брендинг</b>
    <div style="font-size:12px;color:var(--muted);margin-top:4px">Управление фирменным стилем сайта</div>
    <div class="grid2" style="margin-top:16px">
      <div>
        <label>Основной логотип</label>
        <div style="font-size:12px;color:var(--muted);margin-bottom:6px">SVG или PNG с прозрачным фоном. Рекомендуемая ширина: 200px</div>
        <input type="file" id="s_logoFile" accept="image/svg+xml,image/png,image/jpeg,image/webp" style="display:none" onchange="previewLogo(this)">
        <button class="btn btn-sm btn-ghost" onclick="el('s_logoFile').click()">Выбрать файл</button>
        <span id="s_logoName" style="font-size:12px;color:var(--muted);margin-left:8px"></span>
        ${logoSection}
        <div id="s_logoPreview" style="margin-top:8px"></div>
      </div>
      <div>
        <label>Favicon</label>
        <div style="font-size:12px;color:var(--muted);margin-bottom:6px">PNG или ICO, 32×32px</div>
        <input type="file" id="s_faviconFile" accept="image/png,image/x-icon,image/svg+xml" style="display:none" onchange="previewFavicon(this)">
        <button class="btn btn-sm btn-ghost" onclick="el('s_faviconFile').click()">Выбрать файл</button>
        <span id="s_faviconName" style="font-size:12px;color:var(--muted);margin-left:8px"></span>
        ${faviconSection}
        <div id="s_faviconPreview" style="margin-top:8px"></div>
      </div>
    </div>
  </div>

  <div class="card">
    <b style="font-size:15px">Основные</b>
    <div class="grid2" style="margin-top:16px">
      <div>
        <label>Название сайта</label><input id="s_siteName" class="input" value="${esc(s.siteName || '')}" placeholder="MEB">
        <label style="margin-top:12px">Теглайн</label><input id="s_tagline" class="input" value="${esc(s.tagline || '')}" placeholder="Премиальная мебель на заказ">
      </div>
      <div>
        <label style="margin-top:0">Копирайт</label><input id="s_copyright" class="input" value="${esc(s.copyright || '')}" placeholder="© 2026 MEB">
        <label style="margin-top:12px">Email для уведомлений</label><input id="s_notifyEmail" class="input" value="${esc(s.notifyEmail || '')}">
      </div>
    </div>
  </div>

  <div class="card">
    <b style="font-size:15px">Контакты</b>
    <div style="font-size:12px;color:var(--muted);margin-top:4px">Отображаются в шапке, футере и на странице контактов</div>
    <div class="grid2" style="margin-top:16px">
      <div>
        <label>Телефон</label><input id="s_phone" class="input" value="${esc(s.phone || '')}" placeholder="+7 (900) 000-00-00">
        <label style="margin-top:12px">Email</label><input id="s_email" class="input" value="${esc(s.email || '')}">
      </div>
      <div>
        <label>Адрес</label><input id="s_address" class="input" value="${esc(s.address || '')}">
        <label style="margin-top:12px">Часы работы</label><input id="s_hours" class="input" value="${esc(s.hours || '')}" placeholder="Пн-Пт 9:00-18:00">
      </div>
    </div>
  </div>

  <div class="card">
    <b style="font-size:15px">SEO</b>
    <div class="grid2" style="margin-top:12px">
      <div>
        <label>SEO Title</label><input id="s_seoTitle" class="input" value="${esc(seo.title || '')}">
        <label style="margin-top:12px">SEO Description</label><textarea id="s_seoDesc" class="input" rows="2">${esc(seo.desc || '')}</textarea>
        <label style="margin-top:12px">SEO Keywords</label><input id="s_seoKeywords" class="input" value="${esc(seo.keywords || '')}" placeholder="мебель, кухни, гардеробные">
      </div>
      <div></div>
    </div>
  </div>

  <div class="card">
    <b style="font-size:15px">Open Graph</b>
    <div style="font-size:12px;color:var(--muted);margin-top:4px">Превью при расшаривании в соцсетях и мессенджерах</div>
    <div class="grid2" style="margin-top:12px">
      <div>
        <label>OG Title</label><input id="s_ogTitle" class="input" value="${esc(og.title || '')}">
        <label style="margin-top:12px">OG Description</label><textarea id="s_ogDesc" class="input" rows="2">${esc(og.description || '')}</textarea>
      </div>
      <div>
        <div style="margin-top:0">${imagePickerRow('s_ogImage', og.image || '', 'OG Image')}</div>
      </div>
    </div>
  </div>

  <div class="card">
    <b style="font-size:15px">Социальные сети</b>
    <div class="grid2" style="margin-top:12px">
      <div>
        <label>Instagram</label><input id="s_instagram" class="input" value="${esc(socials.instagram || '')}" placeholder="https://instagram.com/...">
        <label style="margin-top:12px">Telegram</label><input id="s_telegram" class="input" value="${esc(socials.telegram || '')}" placeholder="https://t.me/...">
        <label style="margin-top:12px">WhatsApp</label><input id="s_whatsapp" class="input" value="${esc(socials.whatsapp || '')}" placeholder="+7 (900) 000-00-00">
      </div>
      <div>
        <label>VK</label><input id="s_vk" class="input" value="${esc(socials.vk || '')}" placeholder="https://vk.com/...">
        <label style="margin-top:12px">YouTube</label><input id="s_youtube" class="input" value="${esc(socials.youtube || '')}" placeholder="https://youtube.com/...">
        <label style="margin-top:12px">Facebook</label><input id="s_facebook" class="input" value="${esc(socials.facebook || '')}" placeholder="https://facebook.com/...">
      </div>
    </div>
  </div>

  <div class="card">
    <b style="font-size:15px">Аналитика</b>
    <div style="font-size:12px;color:var(--muted);margin-top:4px">Счётчики подключаются автоматически на все страницы сайта</div>
    <div class="grid2" style="margin-top:16px">
      <div>
        <label>Яндекс.Метрика ID</label><input id="s_ym" class="input" value="${esc(analytics.ym || '')}" placeholder="12345678">
        <label style="margin-top:12px">Google Analytics ID</label><input id="s_ga" class="input" value="${esc(analytics.ga || '')}" placeholder="G-XXXXXXXXXX">
      </div>
      <div>
        <label>Google Tag Manager ID</label><input id="s_gtm" class="input" value="${esc(analytics.gtm || '')}" placeholder="GTM-XXXXXXX">
        <div style="font-size:12px;color:var(--muted);margin-top:12px">Указывайте только идентификатор счётчика, без кода.</div>
      </div>
    </div>
  </div>

  <div class="card">
    <b style="font-size:15px">SMTP / Push</b>
    <div class="grid2" style="margin-top:12px">
      <div>
        <label>SMTP хост / порт</label>
        <div style="display:flex;gap:8px"><input id="s_smtpHost" class="input" placeholder="smtp.example.com" value="${esc(smtp.host || '')}"><input id="s_smtpPort" class="input" style="max-width:80px" placeholder="25" value="${esc(smtp.port || '')}"></div>
        <label style="margin-top:12px">SMTP пользователь / пароль</label>
        <div style="display:flex;gap:8px"><input id="s_smtpUser" class="input" value="${esc(smtp.user || '')}"><input id="s_smtpPass" class="input" type="password" value="${esc(smtp.pass || '')}"></div>
        <label style="margin-top:12px">SMTP From</label><input id="s_smtpFrom" class="input" value="${esc(smtp.from || '')}" placeholder="noreply@meb.local">
      </div>
      <div>
        <label>FCM Server Key (push)</label>
        <input id="s_fcmKey" class="input" value="${esc(fcm.key || '')}" placeholder="AAAAx…">
        <label style="margin-top:12px">FCM Topic</label>
        <input id="s_fcmTopic" class="input" placeholder="topic" value="${esc(fcm.topic || '')}">
        <label style="margin-top:12px">Авто-бэкап</label>
        <select id="s_backupSchedule" class="input">${[['off', 'Выключено'], ['daily', 'Раз в день'], ['weekly', 'Раз в неделю']].map(([v, n]) => `<option value="${v}" ${s.backupSchedule === v ? 'selected' : ''}>${n}</option>`).join('')}</select>
      </div>
    </div>
  </div>

  <div style="margin-top:16px;display:flex;gap:8px;align-items:center">
    <button class="btn" onclick="saveSettings()">Сохранить всё</button>
    <span id="s_msg" style="font-size:13px;color:var(--olive)"></span>
  </div>`;
  imagePickerRowOnChange('s_ogImage');
}

function previewLogo(input) {
  const file = input.files[0];
  if (!file) return;
  el('s_logoName').textContent = file.name;
  const reader = new FileReader();
  reader.onload = e => {
    el('s_logoPreview').innerHTML = `<div style="padding:12px;background:var(--linen);border-radius:8px;margin-top:8px;text-align:center"><img src="${e.target.result}" style="max-height:48px"></div>`;
  };
  reader.readAsDataURL(file);
}

function previewFavicon(input) {
  const file = input.files[0];
  if (!file) return;
  el('s_faviconName').textContent = file.name;
  const reader = new FileReader();
  reader.onload = e => {
    el('s_faviconPreview').innerHTML = `<div style="padding:8px;background:var(--linen);border-radius:8px;margin-top:8px;display:flex;align-items:center;gap:10px"><img src="${e.target.result}" style="height:24px;width:24px;border-radius:4px;object-fit:cover"><span style="font-size:12px;color:var(--muted)">${esc(file.name)}</span></div>`;
  };
  reader.readAsDataURL(file);
}

async function clearSetting(key) {
  const cur0 = await jget('/api/v1/settings') || {};
  delete cur0[key];
  const r = await jput('/api/v1/settings', cur0);
  if (r.ok) { toast(key + ' удалён', 'success'); nav('settings'); }
  else toast('Ошибка', 'error');
}

async function saveSettings() {
  const body = {
    siteName: el('s_siteName').value,
    tagline: el('s_tagline').value,
    phone: el('s_phone').value,
    email: el('s_email').value,
    address: el('s_address').value,
    hours: el('s_hours').value,
    copyright: el('s_copyright').value,
    seo: { title: el('s_seoTitle').value, desc: el('s_seoDesc').value, keywords: el('s_seoKeywords').value },
    og: { title: el('s_ogTitle').value, description: el('s_ogDesc').value, image: el('s_ogImage').value },
    socials: { instagram: el('s_instagram').value.trim(), telegram: el('s_telegram').value.trim(), whatsapp: el('s_whatsapp').value.trim(), vk: el('s_vk').value.trim(), youtube: el('s_youtube').value.trim(), facebook: el('s_facebook').value.trim() },
    analytics: { ym: el('s_ym').value.trim(), ga: el('s_ga').value.trim(), gtm: el('s_gtm').value.trim() },
    notifyEmail: el('s_notifyEmail').value.trim(),
    backupSchedule: el('s_backupSchedule').value,
    smtp: { host: el('s_smtpHost').value.trim(), port: el('s_smtpPort').value.trim(), user: el('s_smtpUser').value.trim(), pass: el('s_smtpPass').value, from: el('s_smtpFrom').value.trim() },
    fcm: { key: el('s_fcmKey').value.trim(), topic: el('s_fcmTopic').value.trim() }
  };
  // Handle logo upload
  const logoFile = el('s_logoFile').files[0];
  if (logoFile) {
    const b64 = await new Promise(res => { const r = new FileReader(); r.onload = () => res(r.result); r.readAsDataURL(logoFile); });
    const up = await jpost('/api/v1/media/upload', { filename: 'logo-' + logoFile.name, data: b64, alt: 'logo', folder: 'branding' });
    if (up.ok && up.data && up.data.url) body.logo = up.data.url;
  }
  // Handle favicon upload
  const favFile = el('s_faviconFile').files[0];
  if (favFile) {
    const b64 = await new Promise(res => { const r = new FileReader(); r.onload = () => res(r.result); r.readAsDataURL(favFile); });
    const up = await jpost('/api/v1/media/upload', { filename: 'favicon-' + favFile.name, data: b64, alt: 'favicon', folder: 'branding' });
    if (up.ok && up.data && up.data.url) body.favicon = up.data.url;
  }
  // PUT with the real CSRF token; surface the REAL server response.
  // Logs only booleans (token presence), never token/header values.
  let status = 0, err = '', rawJ = null, rawText = '';
  try {
    const r = await fetch('/api/v1/settings', { method: 'PUT', headers: { 'Content-Type': 'application/json', ...hdr(), ...csfHdr() }, body: JSON.stringify(body) });
    status = r.status;
    console.debug('[MEB] PUT /api/v1/settings', { auth_present: !!token, csrf_present: !!csrf });
    rawText = await r.text();
    try { rawJ = JSON.parse(rawText); } catch (e) { rawJ = null; }
    if (rawJ === null || rawJ.success === false) err = String((rawJ && (rawJ.error || rawJ.message)) || (rawText ? 'не-JSON ответ сервера' : 'пустой ответ'));
  } catch (e) {
    err = String((e && e.message) || e);
  }
  const ok = status >= 200 && status < 300 && rawJ !== null && rawJ.success !== false;
  if (!ok) {
    console.error('SETTINGS SAVE FAILED', { status: status, error: err, keys: Object.keys(body || {}), bytes: JSON.stringify(body || {}).length });
    el('s_msg').textContent = 'Ошибка сохранения (HTTP ' + (status || '?') + ')' + (err ? ': ' + err : '');
    toast('Ошибка сохранения (HTTP ' + (status || '?') + ')' + (err ? ': ' + err : ''), 'error');
  } else {
    el('s_msg').textContent = '✓ Сохранено';
    toast('Настройки сохранены', 'success');
    nav('settings');
  }
}

/* ============ BACKUP ============ */
async function renderBackup(list) {
  const items = await jget('/api/v1/backup/list') || [];
  list.innerHTML = `<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Бэкапы</div>
        <b style="font-size:15px">Резервные копии</b>
      </div>
      <button class="btn btn-sm" onclick="doBackup()">+ Создать</button>
    </div>
    <table style="margin-top:16px">
      <thead><tr><th>Файл</th><th>Размер</th><th>Дата</th><th></th></tr></thead>
      <tbody>${items.map(b =>
        `<tr>
          <td>${esc(b.filename)}</td>
          <td>${Math.round((b.size || 0) / 1024)} KB</td>
          <td>${esc((b.created_at || '').slice(0, 19))}</td>
          <td><button class="btn btn-sm btn-ghost" onclick="restore('${b.id}')">Восстановить</button></td>
        </tr>`
      ).join('') || '<tr><td colspan="4" class="empty-state">Нет бэкапов</td></tr>'}</tbody>
    </table>
  </div>`;
}

async function doBackup() {
  toast('Создаём бэкап...', 'info');
  const r = await jpost('/api/v1/backup/create');
  toast(r.ok ? 'Бэкап создан' : 'Ошибка создания', r.ok ? 'success' : 'error');
  if (r.ok) nav(cur === 'dashboard' ? cur : 'backup');
}

async function restore(id) {
  if (!confirm('Восстановить данные из бэкапа? Текущие данные будут сохранены в страховочную копию.')) return;
  toast('Восстанавливаем...', 'info');
  const r = await jpost('/api/v1/backup/restore/' + id);
  toast(r.ok ? 'Восстановлено ✓' : (r.data.error || 'Ошибка'), r.ok ? 'success' : 'error');
}

async function optimizeDB() {
  toast('Оптимизируем таблицы...', 'info');
  const r = await jpost('/api/v1/system/optimize-db');
  toast(r.ok ? 'Таблицы оптимизированы' : 'Ошибка', r.ok ? 'success' : 'error');
  if (r.ok) nav('system');
}

async function clearCache() {
  toast('Очищаем кэш...', 'info');
  const r = await jpost('/api/v1/system/clear-cache');
  toast(r.ok ? 'Кэш очищен' : 'Ошибка', r.ok ? 'success' : 'error');
  if (r.ok) nav('system');
}

/* ============ MEDIA ============ */
async function renderMedia(list) {
  const items = await jget('/api/v1/media') || [];
  const folders = [...new Set((items || []).map(m => m.folder || ''))].filter(Boolean);
  list.innerHTML = `<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Медиа</div>
        <b style="font-size:15px">Файлов: ${items.length}</b>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn btn-sm btn-ghost" onclick="filterFolder('')">Все</button>
        ${folders.map(f => `<button class="btn btn-sm btn-ghost" onclick="filterFolder('${esc(f)}')">${esc(f)}</button>`).join('')}
      </div>
    </div>
    <div id="dropZone" style="margin-top:16px;padding:18px 16px;background:var(--linen);border-radius:8px;border:1.5px dashed var(--line);cursor:pointer;transition:border-color .2s, background .2s">
      <input type="file" id="fileIn" accept="image/*,.svg,.ico" style="display:none" onchange="mediaFileChosen(this)">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span style="font-size:13px;color:var(--muted);flex:1;min-width:200px">Перетащите файл сюда или нажмите, чтобы выбрать · изображения до 15 МБ</span>
        <input id="folderIn" class="input" style="max-width:150px" placeholder="Папка">
        <input id="altIn" class="input" style="max-width:220px" placeholder="ALT — описание для SEO">
        <button class="btn btn-sm" onclick="uploadMedia()">Загрузить</button>
      </div>
      <div id="mediaFileName" style="margin-top:8px;font-size:12px;color:var(--brass)"></div>
    </div>
    <input id="mediaSearch" class="input" placeholder="Поиск файлов..." style="margin-top:12px" oninput="loadMedia(this.value)">
    <div id="mediaGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-top:14px"></div>
  </div>`;
  window._curFolder = '';
  wireDropZone();
  loadMedia('');
}

function mediaFileChosen(input) {
  const f = input.files[0];
  const nm = el('mediaFileName');
  if (nm) nm.textContent = f ? f.name + ' · ' + Math.round(f.size / 1024) + ' КБ' : '';
}

function wireDropZone() {
  const dz = el('dropZone');
  if (!dz) return;
  ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.style.borderColor = 'var(--brass)'; dz.style.background = 'var(--card)'; }));
  ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.style.borderColor = ''; dz.style.background = 'var(--linen)'; }));
  dz.addEventListener('drop', e => {
    const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (!f) return;
    const dt = new DataTransfer();
    dt.items.add(f);
    el('fileIn').files = dt.files;
    mediaFileChosen(el('fileIn'));
    uploadMedia();
  });
  dz.onclick = () => { el('fileIn').click(); };
}

function previewMedia(url, name, alt, w, h, size) {
  const o = document.createElement('div');
  o.id = 'mediaPreviewOverlay';
  o.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;padding:24px';
  o.onclick = e => { if (e.target === o) o.remove(); };
  o.innerHTML = `<div style="background:var(--card);border-radius:14px;max-width:92vw;max-height:90vh;overflow:auto;box-shadow:0 24px 70px rgba(0,0,0,.45)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--line);position:sticky;top:0;background:var(--card);z-index:1">
      <b style="font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(name)}</b>
      <button class="btn btn-sm btn-ghost" onclick="this.closest('#mediaPreviewOverlay').remove()">✕</button>
    </div>
    <div style="padding:16px;text-align:center">
      <img src="${esc(url)}" alt="${esc(alt)}" style="max-width:100%;max-height:60vh;border-radius:8px">
      <div style="margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
        <button class="btn btn-sm" onclick="copyUrl('${esc(url)}')">Копировать URL</button>
        <a class="btn btn-sm btn-ghost" href="${esc(url)}" download target="_blank">Скачать</a>
        <a class="btn btn-sm btn-ghost" href="${esc(url)}" target="_blank">Открыть</a>
      </div>
      <div style="margin-top:10px;font-size:12px;color:var(--muted)">${w && h ? esc(w) + '×' + esc(h) + ' px · ' : ''}${Math.round((size || 0) / 1024)} КБ · ${esc(alt || 'без ALT')}</div>
    </div>
  </div>`;
  document.body.appendChild(o);
}

function filterFolder(f) { window._curFolder = f; loadMedia(el('mediaSearch').value); }

async function loadMedia(q) {
  const fp = window._curFolder !== undefined ? '&folder=' + encodeURIComponent(window._curFolder) : '';
  const items = await jget('/api/v1/media?x=1' + fp) || [];
  const f = (items || []).filter(m => !q || (m.original_name || '').toLowerCase().includes(q.toLowerCase()));
  const g = el('mediaGrid');
  if (g) g.innerHTML = f.map(m =>
    `<div class="card" style="padding:0;overflow:hidden">
      <div style="aspect-ratio:1;overflow:hidden;background:var(--linen)">
        <img src="${m.url}" loading="lazy" alt="${esc(m.alt)}" style="width:100%;height:100%;object-fit:cover">
      </div>
      <div style="padding:8px 10px">
        <div style="font-size:11px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(m.original_name)}</div>
        <div style="font-size:10px;color:var(--muted);margin-top:2px">${m.width ? m.width + '×' + m.height + ' · ' : ''}${Math.round((m.size || 0) / 1024)} KB</div>
        <div style="display:flex;gap:4px;margin-top:6px">
          <button class="btn btn-sm btn-ghost" style="flex:1;font-size:10px;padding:4px 6px" onclick="previewMedia('${esc(m.url)}','${esc(m.original_name)}','${esc(m.alt || '')}',${m.width || 0},${m.height || 0},${m.size || 0})">Просмотр</button>
          <button class="btn btn-sm btn-ghost" style="flex:1;font-size:10px;padding:4px 6px" onclick="copyUrl('${esc(m.url)}')">Копировать</button>
          <button class="btn btn-sm btn-danger" style="flex:0;padding:4px 8px;font-size:10px" onclick="delMedia('${m.id}')">✕</button>
        </div>
      </div>
    </div>`
  ).join('') || '<div class="empty-state"><div style="font-size:32px;margin-bottom:8px;opacity:.3">📁</div><p style="font-size:13px;color:var(--muted)">Нет файлов</p></div>';
}

function copyUrl(u) {
  const url = location.origin + u;
  const done = () => toast('URL скопирован', 'success');
  const fail = () => toast('Не удалось скопировать URL', 'error');
  const fallback = () => {
    const t = document.createElement('textarea');
    t.value = url;
    t.style.cssText = 'position:fixed;left:-9999px;opacity:0';
    document.body.appendChild(t);
    t.select();
    try { document.execCommand('copy') ? done() : fail(); } catch (err) { fail(); }
    t.remove();
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(done).catch(fallback);
  } else fallback();
}

async function delMedia(id) {
  if (!confirm('Удалить файл?')) return;
  await jdel('/api/v1/media/' + id);
  loadMedia('');
  toast('Файл удалён', 'success');
}

async function uploadMedia() {
  const f = el('fileIn').files[0];
  if (!f) { toast('Выберите файл', 'error'); return; }
  const alt = el('altIn').value;
  const folder = el('folderIn') ? el('folderIn').value.trim() : '';
  toast('Загружаем...', 'info');
  const b64 = await new Promise(res => { const r = new FileReader(); r.onload = () => res(r.result); r.readAsDataURL(f); });
  const r = await jpost('/api/v1/media/upload', { filename: f.name, data: b64, alt, folder });
  if (r.ok) { loadMedia(''); toast('Файл загружен', 'success'); }
  else toast((r.data && r.data.error) || 'Ошибка загрузки', 'error');
}

/* ============ GENERIC CRUD ============ */
async function renderCrud(id, list) {
  const items = await jget('/api/v1/' + id) || [];
  const isLeads = id === 'leads', isReviews = id === 'reviews';
  const colDef = cols.find(c => c.id === id);
  list.innerHTML = `<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">${(colDef && colDef.label) || id}</div>
        <b style="font-size:15px">${(colDef && colDef.label) || id} · ${items.length} элементов</b>
      </div>
      ${isLeads ? '' : `<button class="btn btn-sm" onclick="showForm('${id}')">+ Добавить</button>`}
    </div>
    ${isLeads ? '<div style="font-size:12px;color:var(--muted);margin-top:6px">Изменяйте статус в списке: new → in_progress → contacted → done / rejected</div>' : ''}
    <table style="margin-top:14px">
      <thead><tr><th>Название</th><th>Slug/Email</th><th>Статус</th><th></th></tr></thead>
      <tbody>${items.map(it => {
        const title = it.title || it.name || it.email || it.id;
        const slug = it.slug || it.email || '';
        let status;
        if (isLeads) status = `<select class="input" style="padding:4px 8px;font-size:12px;width:auto" onchange="setLeadStatus('${it.id}',this.value)">${['new', 'in_progress', 'contacted', 'done', 'rejected'].map(s => `<option ${it.status === s ? 'selected' : ''}>${s}</option>`).join('')}</select>`;
        else if (isReviews) status = `<button class="btn btn-sm btn-ghost" onclick="toggleReview('${it.id}',${Number(it.approved) === 1 ? 'false' : 'true'})" title="${Number(it.approved) === 1 ? 'Скрыть' : 'Опубликовать'}">${Number(it.approved) === 1 ? '✓ опубл.' : '🚫 скрыт'}</button>`;
        else status = it.status ? `<span class="badge">${esc(it.status)}</span>` : (Number(dv(it.published, 1)) !== 1 ? '<span class="badge badge-warning">черновик</span>' : '—');
        return `<tr>
          <td><b>${esc(title)}</b></td>
          <td style="color:var(--muted)">${esc(slug)}</td>
          <td>${status}</td>
          <td>
            <button class="btn btn-sm btn-ghost" onclick="editItem('${id}','${it.id}')">Изм.</button>
            <button class="btn btn-sm btn-danger" style="padding:4px 8px" onclick="delItem('${id}','${it.id}')">✕</button>
          </td>
        </tr>`;
      }).join('') || '<tr><td colspan="4" class="empty-state">Нет элементов</td></tr>'}</tbody>
    </table>
  </div>`;
}

async function setLeadStatus(id, status) {
  const r = await jput('/api/v1/leads/' + id, { status });
  if (!r.ok) toast(r.data.error || 'Ошибка', 'error');
}

async function toggleReview(id, approve) {
  const r = await jput('/api/v1/reviews/' + id, { approved: approve });
  if (r.ok) nav('reviews'); else toast((r.data || {}).error || 'Ошибка', 'error');
}

function showForm(col, data = {}) {
  const box = el('formBox');
  box.style.display = '';
  const isUser = col === 'users';
  const isLead = col === 'leads';
  const isReview = col === 'reviews';
  let inner;

  if (isUser) {
    inner = `<div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">${data.id ? 'Редактирование' : 'Новый'} · Пользователь</div>
    <b style="font-size:15px">${data.id ? 'Редактировать пользователя' : 'Добавить пользователя'}</b>
    <div class="grid2" style="margin-top:12px">
      <div><label>Email</label><input id="f_email" class="input" value="${esc(data.email || '')}"></div>
      <div><label>Имя</label><input id="f_name" class="input" value="${esc(data.name || '')}"></div>
      <div><label>${data.id ? 'Новый пароль (пустым = без изменений)' : 'Пароль'}</label><input id="f_password" class="input" type="password"></div>
      <div><label>Роль</label><select id="f_role" class="input">${['editor', 'manager', 'admin', 'super_admin'].map(r => `<option ${data.role === r ? 'selected' : ''}>${r}</option>`).join('')}</select></div>
    </div>`;
  } else if (isLead) {
    inner = `<div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Заявка</div>
    <b style="font-size:15px">Заявка</b>
    <div class="grid2" style="margin-top:12px">
      <div><label>Имя</label><input id="f_title" class="input" value="${esc(data.name || '')}"></div>
      <div><label>Телефон</label><input id="f_phone" class="input" value="${esc(data.phone || '')}"></div>
      <div><label>Email</label><input id="f_email_l" class="input" value="${esc(data.email || '')}"></div>
      <div><label>Статус</label><select id="f_status" class="input">${['new', 'in_progress', 'contacted', 'done', 'rejected'].map(s => `<option ${data.status === s ? 'selected' : ''}>${s}</option>`).join('')}</select></div>
    </div>
    <label style="margin-top:12px">Сообщение</label><textarea id="f_desc" class="input">${esc(data.message || '')}</textarea>
    <label style="margin-top:12px">Комментарий менеджера</label><textarea id="f_comment" class="input">${esc(data.comment || '')}</textarea>`;
  } else if (isReview) {
    const approvedVal = Number(dv(data.approved, 1)) === 1 ? '1' : '0';
    inner = `<b style="font-size:15px">${data.id ? 'Редактировать отзыв' : 'Добавить отзыв'}</b>
    <div class="grid2" style="margin-top:12px">
      <div><label>Имя автора</label><input id="f_author" class="input" value="${esc(data.author || data.name || '')}"></div>
      <div><label>Роль / Должность</label><input id="f_role" class="input" value="${esc(data.role || '')}"></div>
      <div><label>Оценка (1-5)</label><input id="f_rating" class="input" type="number" min="1" max="5" value="${esc(data.rating || '5')}"></div>
      <div><label>Статус</label><select id="f_approved" class="input"><option value="1" ${approvedVal === '1' ? 'selected' : ''}>Одобрено</option><option value="0" ${approvedVal === '0' ? 'selected' : ''}>На модерации</option></select></div>
    </div>
    <label style="margin-top:12px">Текст отзыва</label><textarea id="f_desc" class="input" rows="4">${esc(data.text || '')}</textarea>`;
  } else if (col === 'catalog') {
    inner = `<b style="font-size:15px">${data.id ? 'Редактировать товар' : 'Добавить товар'}</b>
    <div class="grid2" style="margin-top:12px">
      <div><label>Название</label><input id="f_title" class="input" value="${esc(data.title || '')}"></div>
      <div><label>Slug (адрес)</label><input id="f_slug" class="input" value="${esc(data.slug || '')}"></div>
    </div>
    <div class="grid2" style="margin-top:12px">
      <div><label>Цена</label><input id="f_price" class="input" type="number" value="${esc(data.price || '')}"></div>
      <div><label>Категория (ID)</label><input id="f_category_id" class="input" value="${esc(data.category_id || '')}"></div>
    </div>
    <label style="margin-top:12px">Описание</label><textarea id="f_desc" class="input" rows="3">${esc(data.description || data.desc || '')}</textarea>
    <div class="grid2" style="margin-top:12px">
      <div>${imagePickerRow('f_cover', data.cover || '', 'Обложка')}</div>
      <div>${multiImagePicker('f_images', data.images || [], 'Изображения товара')}</div>
    </div>
    <label style="margin-top:12px">Характеристики (JSON)</label><textarea id="f_specs" class="input" rows="2">${esc(JSON.stringify(data.specs || {}))}</textarea>
    <div class="grid2" style="margin-top:12px">
      <div><label>SEO Title</label><input id="f_seo_title" class="input" value="${esc(data.seo_title || '')}"></div>
      <div><label>SEO Description</label><input id="f_seo_desc" class="input" value="${esc(data.seo_desc || '')}"></div>
    </div>
    <div class="grid2" style="margin-top:12px">
      <div><label>Рекомендуемый</label><select id="f_featured" class="input"><option value="0" ${!data.featured ? 'selected' : ''}>Нет</option><option value="1" ${data.featured ? 'selected' : ''}>Да</option></select></div>
      <div><label>Опубликован</label><select id="f_published" class="input"><option value="1" ${Number(dv(data.published, 1)) === 1 ? 'selected' : ''}>Да</option><option value="0" ${Number(dv(data.published, 1)) !== 1 ? 'selected' : ''}>Нет</option></select></div>
    </div>`;
  } else if (col === 'projects') {
    inner = `<b style="font-size:15px">${data.id ? 'Редактировать проект' : 'Добавить проект'}</b>
    <div class="grid2" style="margin-top:12px">
      <div><label>Название</label><input id="f_title" class="input" value="${esc(data.title || '')}"></div>
      <div><label>Slug (адрес)</label><input id="f_slug" class="input" value="${esc(data.slug || '')}"></div>
    </div>
    <div class="grid2" style="margin-top:12px">
      <div><label>Категория</label><input id="f_category" class="input" value="${esc(data.category || '')}"></div>
      <div><label>Год</label><input id="f_year" class="input" type="number" value="${esc(data.year || '')}"></div>
    </div>
    <label style="margin-top:12px">Описание</label><textarea id="f_desc" class="input" rows="3">${esc(data.description || data.desc || '')}</textarea>
    <div class="grid2" style="margin-top:12px">
      <div>${multiImagePicker('f_images', data.images || [], 'Изображения проекта')}</div>
      <div><label>Видео URL</label><input id="f_video" class="input" value="${esc(data.video || '')}"></div>
    </div>
    <div class="grid2" style="margin-top:12px">
      <div><label>Материалы (JSON массив)</label><textarea id="f_materials" class="input" rows="2">${esc(JSON.stringify(data.materials || []))}</textarea></div>
      <div><label>Особенности (JSON массив)</label><textarea id="f_features" class="input" rows="2">${esc(JSON.stringify(data.features || []))}</textarea></div>
    </div>
    <div class="grid2" style="margin-top:12px">
      <div><label>SEO Title</label><input id="f_seo_title" class="input" value="${esc(data.seo_title || '')}"></div>
      <div><label>SEO Description</label><input id="f_seo_desc" class="input" value="${esc(data.seo_desc || '')}"></div>
    </div>
    <div class="grid2" style="margin-top:12px">
      <div><label>Рекомендуемый</label><select id="f_featured" class="input"><option value="0" ${!data.featured ? 'selected' : ''}>Нет</option><option value="1" ${data.featured ? 'selected' : ''}>Да</option></select></div>
      <div><label>Опубликован</label><select id="f_published" class="input"><option value="1" ${Number(dv(data.published, 1)) === 1 ? 'selected' : ''}>Да</option><option value="0" ${Number(dv(data.published, 1)) !== 1 ? 'selected' : ''}>Нет</option></select></div>
    </div>`;
  } else if (col === 'materials') {
    inner = `<b style="font-size:15px">${data.id ? 'Редактировать материал' : 'Добавить материал'}</b>
    <div class="grid2" style="margin-top:12px">
      <div><label>Название</label><input id="f_title" class="input" value="${esc(data.title || '')}"></div>
      <div><label>Slug (адрес)</label><input id="f_slug" class="input" value="${esc(data.slug || '')}"></div>
    </div>
    <div class="grid2" style="margin-top:12px">
      <div><label>Тип / Категория</label><input id="f_category" class="input" value="${esc(data.category || '')}" placeholder="wood, stone, metal, glass"></div>
      <div>${imagePickerRow('f_image', data.image || '', 'Изображение')}</div>
    </div>
    <label style="margin-top:12px">Описание</label><textarea id="f_desc" class="input" rows="3">${esc(data.description || data.desc || '')}</textarea>
    <label style="margin-top:12px">Характеристики (JSON объект)</label><textarea id="f_props" class="input" rows="3">${esc(JSON.stringify(data.props || {}))}</textarea>`;
  } else if (col === 'services') {
    inner = `<b style="font-size:15px">${data.id ? 'Редактировать услугу' : 'Добавить услугу'}</b>
    <div class="grid2" style="margin-top:12px">
      <div><label>Название</label><input id="f_title" class="input" value="${esc(data.title || '')}"></div>
      <div><label>Slug (адрес)</label><input id="f_slug" class="input" value="${esc(data.slug || '')}"></div>
    </div>
    <div class="grid2" style="margin-top:12px">
      <div><label>Цена от</label><input id="f_price_from" class="input" type="number" value="${esc(data.price_from || '')}"></div>
      <div><label>Иконка</label><input id="f_icon" class="input" value="${esc(data.icon || '')}"></div>
    </div>
    <label style="margin-top:12px">Описание</label><textarea id="f_desc" class="input" rows="3">${esc(data.description || data.desc || '')}</textarea>`;
  } else {
    inner = `<b style="font-size:15px">${data.id ? 'Редактировать' : 'Добавить'} · ${(cols.find(c => c.id === col) || {}).label || col}</b>
    <div class="grid2" style="margin-top:12px">
      <div><label>Название</label><input id="f_title" class="input" value="${esc(data.title || data.name || '')}"></div>
      <div><label>Slug (адрес)</label><input id="f_slug" class="input" value="${esc(data.slug || '')}"></div>
    </div>
    <label style="margin-top:12px">Описание</label><textarea id="f_desc" class="input" rows="3">${esc(data.desc || data.text || '')}</textarea>
    <label style="margin-top:12px">Доп. поля JSON</label>
    <textarea id="f_extra" class="input" rows="3" placeholder='{"price": 850000, "published": true}'></textarea>`;
  }
  box.innerHTML = `<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
    <button class="btn btn-sm btn-ghost" onclick="el('formBox').style.display='none'">← Назад</button>
    <b style="font-size:15px">${data.id ? 'Редактирование' : 'Новый элемент'}</b>
  </div>` + inner + `
  <div style="margin-top:16px;display:flex;gap:8px;align-items:center">
    <button class="btn" onclick="saveForm('${col}','${data.id || ''}')">Сохранить</button>
    <button class="btn btn-ghost" onclick="el('formBox').style.display='none'">Отмена</button>
    <span id="formMsg" style="font-size:13px"></span>
  </div>`;
  box.dataset.col = col;
  box.dataset.id = data.id || '';
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  if (col === 'catalog') imagePickerRowOnChange('f_cover');
  if (col === 'materials') imagePickerRowOnChange('f_image');
}

async function editItem(col, id) {
  const items = await jget('/api/v1/' + col);
  const it = (items || []).find(x => x.id === id);
  if (it) showForm(col, it);
}

async function delItem(col, id) {
  if (!confirm('Удалить безвозвратно?')) return;
  const r = await jdel('/api/v1/' + col + '/' + id);
  if (r.ok) { nav(cur); toast('Удалено', 'success'); }
  else toast((r.data || {}).error || 'Ошибка', 'error');
}

async function saveForm(col, id) {
  let body = {};
  if (col === 'users') {
    body = { email: el('f_email').value, name: el('f_name').value, role: el('f_role').value };
    const pwd = el('f_password').value;
    if (pwd) body.password = pwd;
  } else if (col === 'leads') {
    body = { name: el('f_title').value, phone: el('f_phone').value, email: el('f_email_l').value, message: el('f_desc').value, status: el('f_status').value, comment: el('f_comment').value };
  } else if (col === 'reviews') {
    body = { author: el('f_author').value, role: el('f_role').value, text: el('f_desc').value, rating: parseInt(el('f_rating').value) || 5, approved: el('f_approved').value === '1' };
  } else if (col === 'catalog') {
    body = { title: el('f_title').value, description: el('f_desc').value, price: parseInt(el('f_price').value) || 0, category_id: el('f_category_id').value.trim(), cover: el('f_cover').value.trim(), seo_title: el('f_seo_title').value, seo_desc: el('f_seo_desc').value, featured: el('f_featured').value === '1', published: el('f_published').value === '1' };
    const slug = el('f_slug').value.trim(); if (slug) body.slug = slug;
    try { body.images = JSON.parse(el('f_images').value || '[]'); } catch (e) { body.images = []; }
    try { body.specs = JSON.parse(el('f_specs').value || '{}'); } catch (e) { body.specs = {}; }
  } else if (col === 'projects') {
    body = { title: el('f_title').value, description: el('f_desc').value, category: el('f_category').value, year: parseInt(el('f_year').value) || null, video: el('f_video').value.trim(), seo_title: el('f_seo_title').value, seo_desc: el('f_seo_desc').value, featured: el('f_featured').value === '1', published: el('f_published').value === '1' };
    const slug = el('f_slug').value.trim(); if (slug) body.slug = slug;
    try { body.images = JSON.parse(el('f_images').value || '[]'); } catch (e) { body.images = []; }
    try { body.materials = JSON.parse(el('f_materials').value || '[]'); } catch (e) { body.materials = []; }
    try { body.features = JSON.parse(el('f_features').value || '[]'); } catch (e) { body.features = []; }
  } else if (col === 'materials') {
    body = { title: el('f_title').value, description: el('f_desc').value, category: el('f_category').value, image: el('f_image').value.trim() };
    const slug = el('f_slug').value.trim(); if (slug) body.slug = slug;
    try { body.props = JSON.parse(el('f_props').value || '{}'); } catch (e) { body.props = {}; }
  } else if (col === 'services') {
    body = { title: el('f_title').value, description: el('f_desc').value, price_from: parseInt(el('f_price_from').value) || 0, icon: el('f_icon').value.trim() };
    const slug = el('f_slug').value.trim(); if (slug) body.slug = slug;
  } else {
    body = { title: el('f_title').value, desc: el('f_desc').value };
    const slug = el('f_slug').value.trim(); if (slug) body.slug = slug;
    try { const extra = JSON.parse((el('f_extra') ? el('f_extra').value : '') || 'null'); if (extra) Object.assign(body, extra); } catch (e) { toast('JSON невалиден', 'error'); return; }
  }
  let res;
  if (id) res = await jput('/api/v1/' + col + '/' + id, body);
  else res = await jpost('/api/v1/' + col, body);
  if (res.ok) { el('formBox').style.display = 'none'; nav(cur); toast('Сохранено', 'success'); }
  else toast((res.data && res.data.error) || 'Ошибка', 'error');
}

/* ============ MENU ITEMS ============ */
async function renderMenuItems(list) {
  const items = await jget('/api/v1/menu') || [];
  items.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
  list.innerHTML = `<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Навигация</div>
        <b style="font-size:15px">Меню сайта</b>
      </div>
      <button class="btn btn-sm" onclick="showMenuForm()">+ Добавить пункт</button>
    </div>
    <div style="font-size:12px;color:var(--muted);margin-top:6px">Пункты отображаются на публичном сайте в порядке сортировки.</div>
    <table style="margin-top:14px">
      <thead><tr><th>Название</th><th>URL</th><th>Порядок</th><th>Статус</th><th></th></tr></thead>
      <tbody>${items.map(m =>
        `<tr style="${m.is_active == 0 ? 'opacity:.5' : ''}">
          <td><b>${esc(m.title)}</b></td>
          <td style="color:var(--muted)">${esc(m.url)}</td>
          <td>${m.sort_order || 0}</td>
          <td><span class="badge ${m.is_active == 1 ? 'badge-success' : 'badge-warning'}">${m.is_active == 1 ? 'Активен' : 'Скрыт'}</span></td>
          <td>
            <button class="btn btn-sm btn-ghost" onclick="showMenuForm('${m.id}')">Изм.</button>
            <button class="btn btn-sm btn-danger" style="padding:4px 8px" onclick="delMenuItem('${m.id}')">✕</button>
          </td>
        </tr>`
      ).join('') || '<tr><td colspan="5" class="empty-state">Нет пунктов меню</td></tr>'}</tbody>
    </table>
  </div>`;
}

async function showMenuForm(id) {
  let data = {};
  if (id) {
    const items = await jget('/api/v1/menu');
    data = (items || []).find(x => x.id === id) || {};
  }
  const box = el('formBox');
  box.style.display = '';
  box.innerHTML = `<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
    <button class="btn btn-sm btn-ghost" onclick="el('formBox').style.display='none'">← Назад</button>
    <b style="font-size:15px">${data.id ? 'Редактировать' : 'Добавить'} пункт меню</b>
  </div>
  <div class="grid2">
    <div><label>Название</label><input id="f_menu_title" class="input" value="${esc(data.title || '')}"></div>
    <div><label>URL</label><input id="f_menu_url" class="input" value="${esc(data.url || '/')}" placeholder="/catalog"></div>
  </div>
  <div class="grid2" style="margin-top:12px">
    <div><label>Порядок</label><input id="f_menu_sort" class="input" type="number" value="${esc(data.sort_order || '0')}"></div>
    <div><label>Статус</label><select id="f_menu_active" class="input"><option value="1" ${dv(data.is_active, 1) == 1 ? 'selected' : ''}>Активен</option><option value="0" ${dv(data.is_active, 1) == 0 ? 'selected' : ''}>Скрыт</option></select></div>
  </div>
  <div style="margin-top:16px;display:flex;gap:8px;align-items:center">
    <button class="btn" onclick="saveMenuItem('${data.id || ''}')">Сохранить</button>
    <button class="btn btn-ghost" onclick="el('formBox').style.display='none'">Отмена</button>
  </div>`;
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function saveMenuItem(id) {
  const body = {
    title: el('f_menu_title').value,
    url: el('f_menu_url').value,
    sort_order: parseInt(el('f_menu_sort').value) || 0,
    is_active: el('f_menu_active').value === '1' ? 1 : 0
  };
  if (!body.title) { toast('Введите название', 'error'); return; }
  let res;
  if (id) res = await jput('/api/v1/menu/' + id, body);
  else res = await jpost('/api/v1/menu', body);
  if (res.ok) { el('formBox').style.display = 'none'; nav('menu_items'); toast('Сохранено', 'success'); }
  else toast((res.data && res.data.error) || 'Ошибка', 'error');
}

async function delMenuItem(id) {
  if (!confirm('Удалить пункт меню?')) return;
  const r = await jdel('/api/v1/menu/' + id);
  if (r.ok) { nav('menu_items'); toast('Удалено', 'success'); }
  else toast((r.data && r.data.error) || 'Ошибка', 'error');
}

/* ============ BANNERS ============ */
async function renderBanners(list) {
  const items = await jget('/api/v1/banners') || [];
  window._bannerCache = items;
  items.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
  list.innerHTML = `<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Баннеры</div>
        <b style="font-size:15px">Слайдер · ${items.length} баннеров</b>
      </div>
      <button class="btn btn-sm" onclick="showBannerForm()">+ Создать баннер</button>
    </div>
    <div style="font-size:12px;color:var(--muted);margin-top:6px">Перетаскивайте для изменения порядка. Только активные баннеры отображаются на сайте.</div>
    <div id="bannerList" style="margin-top:14px">${items.map((b, i) =>
      `<div class="card" style="padding:12px;margin-top:8px;${b.is_active == 0 ? 'opacity:.5' : ''}" draggable="true" data-i="${i}" data-id="${b.id}">
        <div style="display:flex;gap:12px;align-items:center">
          <span class="drag-handle" style="cursor:grab;font-size:16px;color:var(--muted)">⠿</span>
          <div style="width:80px;height:50px;border-radius:6px;overflow:hidden;background:var(--linen);flex-shrink:0">
            ${b.image_url ? `<img src="${esc(b.image_url)}" style="width:100%;height:100%;object-fit:cover">` : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:10px">Нет фото</div>'}
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(b.title || 'Без названия')}</div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">${esc(b.subtitle || '')}</div>
          </div>
          <div style="display:flex;gap:4px;align-items:center">
            <span class="badge ${b.is_active == 1 ? 'badge-success' : 'badge-warning'}" style="font-size:10px">${b.is_active == 1 ? 'Активен' : 'Скрыт'}</span>
            <button class="btn btn-sm btn-ghost" style="padding:4px 8px" onclick="toggleBanner('${b.id}')">${b.is_active == 1 ? 'Скрыть' : 'Показать'}</button>
            <button class="btn btn-sm btn-ghost" onclick="previewBanner('${b.id}')">Предпросмотр</button>
            <button class="btn btn-sm btn-ghost" onclick="showBannerForm('${b.id}')">Изм.</button>
            <button class="btn btn-sm btn-ghost" onclick="dupBanner('${b.id}')">⧉</button>
            <button class="btn btn-sm btn-danger" style="padding:4px 8px" onclick="delBanner('${b.id}')">✕</button>
          </div>
        </div>
      </div>`
    ).join('') || '<div class="empty-state" style="padding:20px">Нет баннеров. Создайте первый!</div>'}</div>
  </div>`;
  wireBannerDrag();
}

function wireBannerDrag() {
  let dragEl = null;
  document.querySelectorAll('#bannerList [draggable]').forEach(row => {
    row.addEventListener('dragstart', e => { dragEl = row; row.style.opacity = '.4'; e.dataTransfer.effectAllowed = 'move'; });
    row.addEventListener('dragend', () => { row.style.opacity = '1'; dragEl = null; });
    row.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
    row.addEventListener('drop', async e => {
      e.preventDefault();
      if (!dragEl || dragEl === row) return;
      const fromId = dragEl.dataset.id, toId = row.dataset.id;
      const items = await jget('/api/v1/banners') || [];
      const fromIdx = items.findIndex(b => b.id === fromId);
      const toIdx = items.findIndex(b => b.id === toId);
      if (fromIdx < 0 || toIdx < 0) return;
      const [moved] = items.splice(fromIdx, 1);
      items.splice(toIdx, 0, moved);
      for (let i = 0; i < items.length; i++) {
        if (items[i].sort_order !== i) await jput('/api/v1/banners/' + items[i].id, { sort_order: i });
      }
      nav('banners');
    });
  });
}

async function showBannerForm(id) {
  let data = {};
  if (id) {
    const items = await jget('/api/v1/banners');
    data = (items || []).find(x => x.id === id) || {};
  }
  const box = el('formBox');
  box.style.display = '';
  box.innerHTML = `<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
    <button class="btn btn-sm btn-ghost" onclick="el('formBox').style.display='none'">← Назад</button>
    <b style="font-size:15px">${data.id ? 'Редактировать баннер' : 'Новый баннер'}</b>
  </div>
  <div class="grid2" style="margin-top:12px">
    <div><label>Статус</label><select id="f_banner_active" class="input"><option value="1" ${dv(data.is_active, 1) == 1 ? 'selected' : ''}>Активен</option><option value="0" ${dv(data.is_active, 1) == 0 ? 'selected' : ''}>Скрыт</option></select></div>
    <div></div>
  </div>
  <div style="margin-top:12px">
    ${imagePickerRow('f_banner_image', data.image_url || '', 'Изображение (Desktop)')}
  </div>
  <div style="margin-top:12px">
    ${imagePickerRow('f_banner_mobile_image', data.mobile_image_url || '', 'Изображение (Mobile)')}
  </div>
  <div class="grid2" style="margin-top:12px">
    <div><label>Заголовок</label><input id="f_banner_heading" class="input" value="${esc(data.title || '')}" placeholder="Искусство жить красиво"></div>
    <div><label>Подзаголовок</label><input id="f_banner_subheading" class="input" value="${esc(data.subtitle || '')}" placeholder="Мебель, созданная для вашего пространства"></div>
  </div>
  <div class="grid2" style="margin-top:12px">
    <div><label>Текст кнопки</label><input id="f_banner_btn_text" class="input" value="${esc(data.button_text || '')}" placeholder="Смотреть коллекцию"></div>
    <div><label>Ссылка кнопки</label><input id="f_banner_btn_url" class="input" value="${esc(data.button_url || '')}" placeholder="/catalog"></div>
  </div>
  <div class="grid2" style="margin-top:12px">
    <div><label>Позиция текста</label><select id="f_banner_pos" class="input"><option value="left" ${(data.text_position || 'left') === 'left' ? 'selected' : ''}>Слева</option><option value="center" ${data.text_position === 'center' ? 'selected' : ''}>По центру</option><option value="right" ${data.text_position === 'right' ? 'selected' : ''}>Справа</option></select></div>
    <div><label>Прозрачность оверлея (%)</label><input id="f_banner_overlay" class="input" type="number" min="0" max="100" value="${esc(dv(data.overlay_opacity, '40'))}"></div>
  </div>
  <div class="grid2" style="margin-top:12px">
    <div><label>Порядок</label><input id="f_banner_sort" class="input" type="number" value="${esc(dv(data.sort_order, '0'))}"></div>
    <div></div>
  </div>
  <div class="grid2" style="margin-top:12px">
    <div><label>Дата начала (пусто = всегда)</label><input id="f_banner_starts" class="input" type="datetime-local" value="${esc((data.starts_at || '').replace(' ', 'T').slice(0, 16))}"></div>
    <div><label>Дата окончания (пусто = всегда)</label><input id="f_banner_ends" class="input" type="datetime-local" value="${esc((data.ends_at || '').replace(' ', 'T').slice(0, 16))}"></div>
  </div>
  <div style="margin-top:16px;display:flex;gap:8px;align-items:center">
    <button class="btn" onclick="saveBanner('${data.id || ''}')">Сохранить</button>
    <button class="btn btn-ghost" onclick="el('formBox').style.display='none'">Отмена</button>
    <span id="formMsg" style="font-size:13px"></span>
  </div>`;
  box.dataset.col = 'banners';
  box.dataset.id = data.id || '';
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  imagePickerRowOnChange('f_banner_image');
  imagePickerRowOnChange('f_banner_mobile_image');
}

async function saveBanner(id) {
  const body = {
    title: el('f_banner_heading').value,
    subtitle: el('f_banner_subheading').value,
    image_url: el('f_banner_image').value.trim(),
    mobile_image_url: el('f_banner_mobile_image').value.trim(),
    button_text: el('f_banner_btn_text').value.trim(),
    button_url: el('f_banner_btn_url').value.trim(),
    text_position: el('f_banner_pos').value,
    overlay_opacity: parseInt(el('f_banner_overlay').value) || 40,
    sort_order: parseInt(el('f_banner_sort').value) || 0,
    is_active: el('f_banner_active').value === '1' ? 1 : 0,
    starts_at: el('f_banner_starts').value ? el('f_banner_starts').value.replace('T', ' ') + ':00' : null,
    ends_at: el('f_banner_ends').value ? el('f_banner_ends').value.replace('T', ' ') + ':00' : null
  };
  let res;
  if (id) res = await jput('/api/v1/banners/' + id, body);
  else res = await jpost('/api/v1/banners', body);
  if (res.ok) { el('formBox').style.display = 'none'; nav('banners'); toast('Сохранено', 'success'); }
  else toast((res.data && res.data.error) || 'Ошибка', 'error');
}

async function dupBanner(id) {
  const items = await jget('/api/v1/banners') || [];
  const orig = items.find(b => b.id === id);
  if (!orig) return;
  const body = { ...orig };
  delete body.id; delete body.created_at; delete body.updated_at;
  body.title = (body.title || '') + ' (копия)';
  body.is_active = 0;
  const r = await jpost('/api/v1/banners', body);
  if (r.ok) { nav('banners'); toast('Баннер дублирован', 'success'); }
}

async function delBanner(id) {
  if (!confirm('Удалить баннер?')) return;
  const r = await jdel('/api/v1/banners/' + id);
  if (r.ok) { nav('banners'); toast('Удалено', 'success'); }
}

async function toggleBanner(id) {
  const items = await jget('/api/v1/banners') || [];
  const b = items.find(x => x.id === id);
  if (!b) return;
  const r = await jput('/api/v1/banners/' + id, { is_active: b.is_active == 1 ? 0 : 1 });
  if (r.ok) { nav('banners'); toast('Статус обновлён', 'success'); }
  else toast((r.data && r.data.error) || 'Ошибка', 'error');
}

function closeBannerPreview() {
  const o = el('bannerPreviewOverlay');
  if (o) o.remove();
}

function previewBanner(id) {
  const items = window._bannerCache || [];
  const b = items.find(x => x.id === id);
  if (!b) { toast('Баннер не найден', 'error'); return; }
  const img = b.image_url || '/assets/img/placeholder.svg';
  const mobileImg = b.mobile_image_url || '';
  const pos = b.text_position || 'left';
  const align = pos === 'center' ? 'center' : (pos === 'right' ? 'flex-end' : 'flex-start');
  const textAlign = pos === 'center' ? 'center' : (pos === 'right' ? 'right' : 'left');
  const overlay = b.overlay_opacity == null ? 40 : b.overlay_opacity;
  const url = b.button_url || '';
  const btnText = b.button_text || 'Подробнее';
  const overlayEl = document.createElement('div');
  overlayEl.id = 'bannerPreviewOverlay';
  overlayEl.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.65);display:flex;align-items:center;justify-content:center;padding:24px';
  overlayEl.onclick = e => { if (e.target === overlayEl) closeBannerPreview(); };
  overlayEl.innerHTML = `<div style="background:var(--card);border-radius:14px;width:100%;max-width:960px;box-shadow:0 24px 70px rgba(0,0,0,.4);overflow:hidden">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid var(--line)">
      <b style="font-size:15px">Предпросмотр баннера</b>
      <button class="btn btn-sm btn-ghost" onclick="closeBannerPreview()">✕</button>
    </div>
    <div style="position:relative;aspect-ratio:16/6;background:var(--ink);overflow:hidden">
      <img src="${esc(img)}" alt="${esc(b.title || '')}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
      ${mobileImg ? `<img src="${esc(mobileImg)}" alt="${esc(b.title || '')}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none">` : ''}
      <div style="position:absolute;inset:0;background:linear-gradient(${pos === 'right' ? 'to left' : (pos === 'center' ? 'to top' : 'to right')}, rgba(10,8,6,${(overlay / 100).toFixed(2)}) 0%, rgba(10,8,6,0) 100%)"></div>
      <div style="position:absolute;inset:0;padding:40px 48px;display:flex;flex-direction:column;justify-content:center;align-items:${align};text-align:${textAlign};color:var(--paper)">
        ${b.title ? `<div style="font-family:'Cormorant Garamond',Georgia,serif;font-size:clamp(26px,4vw,52px);line-height:1.05;font-weight:400;max-width:560px">${esc(b.title).replace(/\n/g, '<br>')}</div>` : ''}
        ${b.subtitle ? `<div style="margin-top:12px;font-size:clamp(13px,1.4vw,17px);opacity:.85;max-width:460px">${esc(b.subtitle)}</div>` : ''}
        <div style="margin-top:22px;display:flex;gap:10px;flex-wrap:wrap;justify-content:${align}">
          <a href="${esc(url || '/contacts')}" target="_blank" style="background:var(--brass);color:#fff;padding:11px 26px;border-radius:8px;font-size:12px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;text-decoration:none">${esc(btnText)}</a>
          ${url ? `<a href="/contacts" target="_blank" style="padding:11px 20px;border:1px solid rgba(255,255,255,.5);color:var(--paper);border-radius:8px;font-size:12px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;text-decoration:none">Обсудить проект</a>` : ''}
        </div>
      </div>
    </div>
    <div style="padding:12px 20px;font-size:12px;color:var(--muted);display:flex;gap:16px;flex-wrap:wrap">
      <span>Позиция: <b>${pos}</b></span>
      <span>Оверлей: <b>${overlay}%</b></span>
      <span>Статус: <b>${b.is_active == 1 ? 'Активен' : 'Скрыт'}</b></span>
      ${url ? `<span>Ссылка: <b>${esc(url)}</b></span>` : ''}
    </div>
  </div>`;
  document.body.appendChild(overlayEl);
}

function quickAdd(col) { nav(col); setTimeout(() => showForm(col), 300); }

/* ============ PAGE BUILDER ============ */
const BLOCK_TYPES = [
  { t: 'hero', n: 'Hero' }, { t: 'text', n: 'Текст' }, { t: 'features', n: 'Преимущества' },
  { t: 'gallery', n: 'Галерея' }, { t: 'statistics', n: 'Цифры' }, { t: 'faq', n: 'FAQ' },
  { t: 'cta', n: 'CTA' }, { t: 'team', n: 'Команда' }, { t: 'contact', n: 'Форма заявки' }
];

let builderPage = null, dragIdx = -1;

async function renderPages(list) {
  const pages = await jget('/api/v1/pages') || [];
  list.innerHTML = `<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Страницы</div>
        <b style="font-size:15px">Конструктор страниц</b>
      </div>
      <button class="btn btn-sm" onclick="createPage()">+ Новая страница</button>
    </div>
    <table style="margin-top:16px">
      <thead><tr><th>Заголовок</th><th>Адрес</th><th>Блоков</th><th></th></tr></thead>
      <tbody>${(pages || []).map(p => `<tr>
        <td><b>${esc(p.title)}</b></td>
        <td style="color:var(--muted)">/p/${esc(p.slug)}</td>
        <td>${(p.blocks || []).length}</td>
        <td>
          <button class="btn btn-sm" onclick="openBuilder('${p.id}')">Конструктор</button>
          <a href="/p/${esc(p.slug)}" target="_blank"><button class="btn btn-sm btn-ghost">Открыть ↗</button></a>
          <button class="btn btn-sm btn-danger" style="padding:4px 8px" onclick="delItem('pages','${p.id}')">✕</button>
        </td>
      </tr>`).join('') || '<tr><td colspan="4" class="empty-state">Нет страниц</td></tr>'}</tbody>
    </table>
  </div>`;
  if (builderPage) openBuilder(builderPage.id, true);
}

async function createPage() {
  const r = await jpost('/api/v1/pages', { title: 'Новая страница', slug: 'page-' + Date.now().toString(36), blocks: [{ type: 'hero', data: { title: 'Заголовок страницы' } }], published: true });
  if (r.ok) { builderPage = r.data; nav('pages'); }
}

async function openBuilder(id, silent) {
  const pages = await jget('/api/v1/pages');
  const p = (pages || []).find(x => x.id === id) || builderPage;
  if (!p) return;
  builderPage = JSON.parse(JSON.stringify(p));
  drawBuilder(!silent);
}

function drawBuilder(scroll) {
  const p = builderPage;
  const box = el('list');
  box.innerHTML = `
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Конструктор</div>
          <b style="font-size:15px">${esc(p.title)}</b>
        </div>
        <div style="display:flex;gap:6px">
          <a href="/p/${esc(p.slug)}" target="_blank"><button class="btn btn-sm btn-ghost">Предпросмотр ↗</button></a>
          <button class="btn btn-sm" onclick="savePage()">Сохранить</button>
          <button class="btn btn-sm btn-ghost" onclick="builderPage=null;nav('pages')">К списку</button>
        </div>
      </div>
      <span id="pbMsg" style="color:var(--olive);font-size:12px;margin-left:8px"></span>
      <div class="grid2" style="margin-top:12px">
        <div><label>Заголовок</label><input id="pb_title" class="input" value="${esc(p.title)}"></div>
        <div><label>Slug</label><input id="pb_slug" class="input" value="${esc(p.slug)}"></div>
        <div><label>SEO Title</label><input id="pb_seoTitle" class="input" value="${esc(p.seo_title || '')}"></div>
        <div><label>H1</label><input id="pb_h1" class="input" value="${esc(p.h1 || '')}"></div>
      </div>
      <label style="margin-top:8px">SEO Description</label>
      <textarea id="pb_seoDesc" class="input" rows="2">${esc(p.seo_desc || '')}</textarea>
    </div>
    <div class="card">
      <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">Конструктор</div>
      <b style="font-size:15px">Блоки страницы</b> <span style="color:var(--muted);font-size:12px;margin-left:8px">перетаскивайте для сортировки</span>
      <div id="blockList">${p.blocks.map((b, i) => blockRow(b, i)).join('')}</div>
      <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
        <select id="addBlockType" class="input" style="max-width:260px">${BLOCK_TYPES.map(t => `<option value="${t.t}">${t.n}</option>`).join('')}</select>
        <button class="btn btn-sm" onclick="addBlock()">+ Добавить блок</button>
      </div>
    </div>`;
  wireDrag();
  if (scroll) window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
}

function blockRow(b, i) {
  const meta = BLOCK_TYPES.find(t => t.t === b.type) || {};
  return `<div class="block-row card" draggable="true" data-i="${i}" style="padding:12px;margin-top:8px;${b.hidden ? 'opacity:.5' : ''}">
    <div style="display:flex;align-items:center;gap:8px">
      <span class="drag-handle">⠿</span>
      <b style="min-width:140px;font-size:13px">${meta.n || b.type}</b>
      <span style="flex:1;font-size:12px;color:var(--muted)">${esc(shortPreview(b))}</span>
      <button class="btn btn-sm btn-ghost" onclick="moveBlock(${i},-1)">↑</button>
      <button class="btn btn-sm btn-ghost" onclick="moveBlock(${i},1)">↓</button>
      <button class="btn btn-sm btn-ghost" onclick="toggleHide(${i})">${b.hidden ? '👁' : '🙈'}</button>
      <button class="btn btn-sm btn-ghost" onclick="dupBlock(${i})">⧉</button>
      <button class="btn btn-sm btn-ghost" onclick="editBlock(${i})">✎</button>
      <button class="btn btn-sm btn-danger" style="padding:4px 8px" onclick="delBlock(${i})">✕</button>
    </div>
  </div>`;
}

function shortPreview(b) {
  const d = b.data || {};
  return d.title || d.text || d.q || ((d.items || [])[0] && (d.items[0].title || d.items[0].q)) || '—';
}

function wireDrag() {
  document.querySelectorAll('#blockList .block-row').forEach(row => {
    row.addEventListener('dragstart', () => { dragIdx = +row.dataset.i; row.style.opacity = .4; });
    row.addEventListener('dragend', () => { row.style.opacity = 1; });
    row.addEventListener('dragover', e => e.preventDefault());
    row.addEventListener('drop', e => {
      e.preventDefault();
      const to = +row.dataset.i;
      if (to === dragIdx || dragIdx < 0) return;
      const [moved] = builderPage.blocks.splice(dragIdx, 1);
      builderPage.blocks.splice(to, 0, moved);
      drawBuilder(false);
      savePage(true);
    });
  });
}

function moveBlock(i, d) {
  const j = i + d;
  const arr = builderPage.blocks;
  if (j < 0 || j >= arr.length) return;
  [arr[i], arr[j]] = [arr[j], arr[i]];
  drawBuilder(false);
  savePage(true);
}

function toggleHide(i) { builderPage.blocks[i].hidden = !builderPage.blocks[i].hidden; drawBuilder(false); savePage(true); }
function dupBlock(i) { const copy = JSON.parse(JSON.stringify(builderPage.blocks[i])); builderPage.blocks.splice(i + 1, 0, copy); drawBuilder(false); savePage(true); }
function delBlock(i) { if (!confirm('Удалить блок?')) return; builderPage.blocks.splice(i, 1); drawBuilder(false); savePage(true); }
function addBlock() {
  const t = el('addBlockType').value;
  builderPage.blocks.push({ type: t, data: defaultData(t) });
  drawBuilder(false);
  savePage(true);
}

function defaultData(t) {
  if (t === 'hero') return { title: 'Заголовок', subtitle: '', ctaLabel: '', ctaUrl: '' };
  if (t === 'text') return { text: 'Текст раздела' };
  if (['features', 'statistics', 'team'].includes(t)) return { items: [{ title: 'Пункт', desc: '', kicker: '' }] };
  if (t === 'faq') return { items: [{ q: 'Вопрос?', a: 'Ответ' }] };
  if (t === 'gallery') return { images: [] };
  if (t === 'cta') return { title: 'Готовы обсудить проект?', subtitle: '', ctaLabel: 'Связаться', ctaUrl: '#contacts' };
  return {};
}

function editBlock(i) {
  const b = builderPage.blocks[i];
  const d = b.data || {};
  const itemFields = (key, qk, ak, kick) => `
    <label>Элементы (${(d.items || []).length})</label>
    <div id="itemsBox">${(d.items || []).map((it, j) => itemRow(it, j, key, qk, ak, kick)).join('')}</div>
    <button class="btn btn-sm btn-ghost" style="margin-top:6px" onclick="addItem('${key}','${qk || ''}','${ak || ''}','${kick || ''}')">+ элемент</button>`;
  const fields = {
    hero: `<label>Заголовок</label><input id="eb_0" class="input" value="${esc(d.title || '')}"><label style="margin-top:6px">Подзаголовок</label><input id="eb_1" class="input" value="${esc(d.subtitle || '')}"><label style="margin-top:6px">Кнопка (текст)</label><input id="eb_2" class="input" value="${esc(d.ctaLabel || '')}"><label style="margin-top:6px">Кнопка (ссылка)</label><input id="eb_3" class="input" value="${esc(d.ctaUrl || '')}">`,
    text: `<label>Текст</label><textarea id="eb_0" class="input" rows="4">${esc(d.text || '')}</textarea>`,
    features: itemFields('items'),
    statistics: itemFields('items'),
    team: itemFields('items', null, null, 'role'),
    faq: itemFields('items', 'q', 'a'),
    cta: `<label>Заголовок</label><input id="eb_0" class="input" value="${esc(d.title || '')}"><label style="margin-top:6px">Подзаголовок</label><input id="eb_1" class="input" value="${esc(d.subtitle || '')}"><label style="margin-top:6px">Кнопка (текст)</label><input id="eb_2" class="input" value="${esc(d.ctaLabel || '')}"><label style="margin-top:6px">Кнопка (ссылка)</label><input id="eb_3" class="input" value="${esc(d.ctaUrl || '')}">`,
    gallery: `${multiImagePicker('eb_gallery_images', d.images || [], 'Изображения галереи')}`,
    contact: '<div style="color:var(--muted);font-size:13px">Форма заявки подключается автоматически.</div>'
  };
  el('formBox').innerHTML = `<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
    <button class="btn btn-sm btn-ghost" onclick="el('formBox').style.display='none'">← Назад</button>
    <b style="font-size:15px">Блок: ${(BLOCK_TYPES.find(t => t.t === b.type) || {}).n || b.type}</b>
  </div>
  ${fields[b.type] || ''}
  <div style="margin-top:12px;display:flex;gap:8px">
    <button class="btn btn-sm" onclick="saveBlock(${i})">Применить</button>
    <button class="btn btn-sm btn-ghost" onclick="el('formBox').style.display='none'">Закрыть</button>
  </div>`;
  el('formBox').style.display = '';
  el('formBox').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function itemRow(it, j, titleKey, qKey, aKey, kickKey) {
  return `<div class="card" style="padding:8px;margin-top:6px">
    <input class="input eb-item" data-j="${j}" data-k="${titleKey || 'title'}" placeholder="Заголовок" value="${esc(it[titleKey || 'title'] || '')}">
    ${qKey ? `<input class="input eb-item" data-j="${j}" data-k="a" placeholder="Ответ" value="${esc(it.a || '')}" style="margin-top:4px">` :
      `<input class="input eb-item" data-j="${j}" data-k="desc" placeholder="Описание" value="${esc(it.desc || '')}" style="margin-top:4px">`}
    ${kickKey !== undefined ? `<input class="input eb-item" data-j="${j}" data-k="${kickKey}" placeholder="${kickKey === 'role' ? 'Роль' : 'Надзаголовок'}" value="${esc(it[kickKey] || '')}" style="margin-top:4px">` : ''}
    <button style="margin-top:4px;font-size:11px;color:var(--danger);background:none;border:none;cursor:pointer" onclick="this.parentNode.remove()">удалить</button>
  </div>`;
}

function addItem(key, qKey, aKey, kickKey) {
  const box = el('itemsBox');
  const div = document.createElement('div');
  div.innerHTML = itemRow({}, box.children.length, 'title', qKey, aKey, kickKey);
  box.appendChild(div.firstChild);
}

function collectItems(key, qKey, kickKey) {
  return [...document.querySelectorAll('.eb-item')].reduce((acc, inp) => {
    const j = +inp.dataset.j, k = inp.dataset.k;
    acc[j] = acc[j] || {};
    acc[j][k] = inp.value;
    return acc;
  }, []).filter(Boolean);
}

function saveBlock(i) {
  const b = builderPage.blocks[i];
  const v = n => { const e = el('eb_' + n); return e ? e.value : undefined; };
  switch (b.type) {
    case 'hero': case 'cta':
      b.data = { title: v(0), subtitle: v(1), ctaLabel: v(2), ctaUrl: v(3) }; break;
    case 'text': b.data = { text: v(0) }; break;
    case 'features': b.data = { items: collectItems('items').map(x => ({ title: x.items || x.title, desc: x.desc || '' })) }; break;
    case 'statistics': b.data = { items: collectItems('items').map(x => ({ title: x.items || x.title, value: x.value || '', label: x.desc || '' })) }; break;
    case 'team': b.data = { items: collectItems('items', null, 'role').map(x => ({ title: x.items || x.title, role: x.role || '', desc: x.desc || '' })) }; break;
    case 'faq': b.data = { items: collectItems('items', 'q').map(x => ({ q: x.items || x.title || '', a: x.a || '' })) }; break;
    case 'gallery':
      const ginp = el('eb_gallery_images');
      let gimgs = [];
      try { gimgs = JSON.parse(ginp ? ginp.value || '[]' : '[]'); } catch(e) { gimgs = []; }
      b.data = { images: gimgs };
      break;
  }
  el('formBox').style.display = 'none';
  drawBuilder(false);
  savePage(true);
}

async function savePage(silent) {
  const p = builderPage;
  // Explicit snake_case payload matching the pages table. Never send the raw
  // builder object (it carries camelCase seoTitle/seoDesc which are not schema
  // columns and would fail the UPDATE with "unknown column" MySQL 1054).
  const body = {
    title: el('pb_title').value,
    slug: el('pb_slug').value.trim(),
    h1: el('pb_h1').value,
    seo_title: el('pb_seoTitle').value,
    seo_desc: el('pb_seoDesc').value,
    blocks: p.blocks,
    published: p.published
  };
  const r = await jput('/api/v1/pages/' + p.id, body);
  const msg = el('pbMsg');
  if (msg) msg.textContent = r.ok ? (silent ? '✓ автосохранение' : '✓ Сохранено') : 'Ошибка сохранения';
}

/* ============ SEO AUDIT ============ */
async function renderSEO(list) {
  list.innerHTML = '<div class="card"><div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">SEO</div><div style="margin-top:8px;color:var(--muted)">Проверяем...</div></div>';
  const a = await jget('/api/v1/seo/audit') || { score: 0, checked: 0, issues: [] };
  const color = a.score >= 80 ? 'var(--olive)' : a.score >= 50 ? 'var(--brass)' : 'var(--danger)';
  list.innerHTML = `<div class="card">
    <div style="display:flex;align-items:center;gap:16px">
      <div style="font-family:'Cormorant Garamond',serif;font-size:48px;font-weight:500;color:${color}">${a.score}</div>
      <div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:2px">SEO Audit</div>
        <b style="font-size:15px">SEO-здоровье</b>
        <div style="font-size:12px;color:var(--muted);margin-top:2px">Страниц: ${a.checked} · Проблем: ${a.issues.length}</div>
      </div>
      <button class="btn btn-sm btn-ghost" style="margin-left:auto" onclick="renderSEO(document.getElementById('list'))">Перепроверить</button>
    </div>
    ${a.issues.length ? a.issues.map(i => `<div class="card" style="padding:10px;margin-top:8px;border-left:3px solid ${i.severity === 'high' ? 'var(--danger)' : i.severity === 'medium' ? 'var(--brass)' : 'var(--olive)'}">
      <b style="font-size:13px">${esc(i.where)}</b>
      <div style="font-size:12px;color:var(--muted);margin-top:2px">${esc(i.problem)}</div>
    </div>`).join('') : '<div style="color:var(--olive);margin-top:12px;font-size:13px">✓ Критичных проблем не найдено</div>'}
  </div>
  <div class="card">
    <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:8px">Технические файлы</div>
    <div style="display:flex;gap:16px">
      <a href="/api/v1/seo/sitemap.xml" target="_blank" style="color:var(--brass);font-size:13px">sitemap.xml ↗</a>
      <a href="/robots.txt" target="_blank" style="color:var(--brass);font-size:13px">robots.txt ↗</a>
    </div>
  </div>`;
}

/* ============ SYSTEM ============ */
async function renderSystem(list) {
  const up = await jget('/api/v1/system/update/check') || {};
  const logs = await jget('/api/v1/audit_log') || [];

  list.innerHTML = `
  <div class="grid2">
    <div class="card">
      <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:8px">Обновления</div>
      <div style="margin-top:8px;font-size:13px">Установлена: <b>v${esc(up.current || '1.0.0')}</b> · Последняя: <b>v${esc(up.latest || '?')}</b></div>
      ${up.updateAvailable ? `<button class="btn" style="margin-top:12px" onclick="runUpdate()">Обновить</button>` : '<div style="color:var(--olive);margin-top:10px;font-size:13px">✓ Последняя версия</div>'}
      <div id="updMsg" style="font-size:13px;margin-top:8px"></div>
    </div>
    <div class="card">
      <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:8px">Android App</div>
      <div id="apkInfo" style="margin-top:8px;font-size:13px;color:var(--muted)">Загрузка...</div>
      <button id="apkDlBtn" class="btn btn-sm" style="margin-top:10px;display:none" onclick="downloadApk()">⬇ Скачать APK</button>
    </div>
  </div>
  <div class="card" style="margin-top:16px">
    <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:8px">Audit Log</div>
    <table style="margin-top:8px">
      <thead><tr><th>Когда</th><th>Кто</th><th>Действие</th><th>Объект</th></tr></thead>
      <tbody>${logs.slice(-12).reverse().map(l => `<tr>
        <td style="font-size:12px">${esc((l.created_at || '').slice(0, 16))}</td>
        <td>${esc(l.user_id || '')}</td>
        <td>${esc(l.action)}</td>
        <td>${esc(l.entity)} ${esc(l.entity_id || '')}</td>
      </tr>`).join('')}</tbody>
    </table>
  </div>
  <div id="sysDiag" style="margin-top:16px"><div style="color:var(--muted);font-size:13px;padding:6px 0">Запуск диагностики…</div></div>`;
  loadApkInfo();
  await renderDiagUI();
}

let _diag = null;

function fmtDt(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return isNaN(d.getTime()) ? String(iso).slice(0, 16) : d.toLocaleString('ru-RU');
}

function sevBadge(s) {
  const m = {
    critical: ['badge-danger', 'Критично'],
    high:     ['badge-danger', 'Высокая'],
    medium:   ['badge-warning', 'Средняя'],
    low:      ['', 'Низкая'],
    info:     ['', 'Инфо'],
  };
  const r = m[s] || m.low;
  return `<span class="badge ${r[0]}"${r[0] === '' ? ' style="border:1px solid var(--line);color:var(--muted)"' : ''}>${r[1]}</span>`;
}

function diagHtml(d) {
  const o = d.overall || {};
  const pct = o.percent || 0;
  const status = o.status || 'warning';
  const stBadge  = status === 'ok' ? 'badge-success' : (status === 'error' || status === 'critical') ? 'badge-danger' : 'badge-warning';
  const stLabel  = status === 'ok' ? 'Работает' : status === 'critical' ? 'Критические ошибки' : status === 'error' ? 'Есть ошибки' : 'Требует внимания';
  const stColor  = status === 'ok' ? 'var(--olive)' : (status === 'error' || status === 'critical') ? 'var(--error)' : 'var(--brass)';
  const catOrder = ['core', 'database', 'storage', 'cache', 'backup', 'media', 'api', 'admin', 'banners', 'public', 'security', 'perf', 'logs'];
  const cats = d.categories || {};
  const issues = d.issues || [];

  const catHTML = catOrder.filter(k => cats[k]).map(k => {
    const c = cats[k];
    const cb = c.status === 'ok' ? 'badge-success' : (c.status === 'error' || c.status === 'critical') ? 'badge-danger' : 'badge-warning';
    const cl = c.status === 'ok' ? 'OK' : c.status === 'critical' ? 'Критично' : c.status === 'error' ? 'Ошибки' : 'Внимание';
    const rows = (c.items || []).map(it => {
      const ic = it.status === 'ok' ? '<span style="color:var(--olive)">✓</span>'
        : it.status === 'warning' ? '<span style="color:var(--brass)">⚠</span>'
        : (it.status === 'notconfigured' || it.status === 'notverified') ? '<span style="color:var(--muted)">○</span>'
        : '<span style="color:var(--error)">✕</span>';
      const meta = [it.source, it.endpoint].filter(Boolean).join(' · ');
      return `<tr>
        <td style="vertical-align:top;padding:8px 10px 0 0">${ic}</td>
        <td style="padding:6px 0">
          <b style="font-size:13px">${esc(it.title)}</b>
          ${it.id ? `<span class="badge" style="border:1px solid var(--line);font-size:10px;margin-left:6px">${esc(it.id)}</span>` : ''}
          <div style="font-size:12px;color:var(--muted);margin-top:2px">${esc(it.message)}</div>
          ${it.date ? `<div style="font-size:11px;color:var(--muted);margin-top:2px">Дата: ${esc(it.date)}</div>` : ''}
          ${meta ? `<div style="font-size:10px;color:var(--muted);margin-top:3px;opacity:.75">${esc(meta)}</div>` : ''}
        </td>
      </tr>`;
    }).join('');
    return `<div class="card" style="padding:0;overflow:hidden">
      <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer" onclick="diagToggle('${k}')">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <b style="font-size:13px">${esc(c.label)}</b>
          <span style="font-size:11px;color:var(--muted)">${c.ok}/${c.total} ok${c.error ? ' · ошибок ' + c.error : ''}${c.warning ? ' · предупреждений ' + c.warning : ''}</span>
        </div>
        <span class="badge ${cb}">${cl}</span>
      </div>
      <div id="diagCat-${k}" style="display:none">
        ${rows ? '<table style="width:100%"><tbody>' + rows + '</tbody></table>' : '<div style="padding:10px 16px;font-size:12px;color:var(--muted)">Нет проверок</div>'}
      </div>
    </div>`;
  }).join('');

  const issueHTML = issues.length ? issues.map(it => `
    <div style="padding:12px 0;border-bottom:1px solid var(--line)">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        ${sevBadge(it.severity)}
        ${it.id ? `<span class="badge" style="border:1px solid var(--line);font-size:10px">${esc(it.id)}</span>` : ''}
        <b style="font-size:13px">${esc(it.title)}</b>
        <span style="font-size:11px;color:var(--muted)">${esc(it.category)}</span>
        ${it.http ? `<span class="badge" style="border:1px solid var(--line);font-size:10px">HTTP ${esc(it.http)}</span>` : ''}
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:5px">${esc(it.message)}</div>
      ${it.date ? `<div style="font-size:11px;color:var(--muted);margin-top:3px;opacity:.8">Дата: ${esc(it.date)}</div>` : ''}
      ${(it.where || it.endpoint) ? `<div style="font-size:11px;color:var(--muted);margin-top:3px;opacity:.8">Где: ${esc(it.where || '—')}${it.endpoint ? ' · ' + esc(it.endpoint) : ''}</div>` : ''}
      ${it.fix ? `<div style="font-size:12px;color:var(--olive);margin-top:4px">→ Исправление: ${esc(it.fix)}</div>` : ''}
    </div>`).join('') : '<div style="color:var(--olive);font-size:13px;margin-top:8px">✓ Проблем не обнаружено</div>';

  return `
  <div class="card" style="margin-top:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:4px">System Health · Диагностика</div>
        <div style="font-size:12px;color:var(--muted)">Версия: ${esc(d.version || '')} · Режим: ${d.mode === 'full' ? 'полная' : 'быстрая'} · Проверено: ${fmtDt(d.checked_at)}</div>
      </div>
      <span class="badge ${stBadge}">${stLabel}</span>
    </div>
    <div style="margin-top:14px;height:8px;border-radius:4px;background:var(--linen);overflow:hidden">
      <div style="width:${pct}%;height:100%;background:${stColor};transition:width .5s ease"></div>
    </div>
    <div style="display:flex;gap:16px;margin-top:10px;flex-wrap:wrap;font-size:12px;color:var(--muted)">
      <span>Прогресс: <b>${o.ok}/${o.total}</b> · ${pct}%</span>
      <span style="color:var(--olive)">OK: ${o.ok}</span>
      <span>Предупреждения: ${o.warning}</span>
      <span style="color:var(--error)">Ошибки: ${o.error}</span>
      <span style="color:var(--error)">Критично: ${o.critical}</span>
      <span>Не настроено: ${o.notconfigured}</span>
    </div>
    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
      <button class="btn btn-sm" onclick="runDiag('quick')">Быстрая проверка</button>
      <button class="btn btn-sm btn-ghost" onclick="runDiag('full')">Полная диагностика</button>
      <button class="btn btn-sm btn-ghost" onclick="exportDiagReport()">Экспорт отчёта</button>
      <button class="btn btn-sm btn-ghost" onclick="optimizeDB()">Оптимизировать БД</button>
      <button class="btn btn-sm btn-ghost" onclick="clearCache()">Очистить кэш</button>
    </div>
  </div>

  <div class="card">
    <div style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--brass);margin-bottom:6px">Error Center</div>
    <b style="font-size:15px">Проблемы и рекомендации</b>
    <div style="margin-top:6px">${issueHTML}</div>
  </div>

  <div style="display:grid;gap:12px">${catHTML}</div>`;
}

async function renderDiagUI() {
  const box = el('sysDiag');
  if (!box) return;
  box.innerHTML = '<div style="color:var(--muted);font-size:13px;padding:6px 0">Проверка системы…</div>';
  const d = await jget('/api/v1/system/diagnostics?mode=quick');
  if (!d) {
    box.innerHTML = '<div class="card">Диагностика недоступна (требуются права администратора).</div>';
    return;
  }
  _diag = d;
  if (!box.isConnected) return;
  box.innerHTML = diagHtml(d);
}

async function runDiag(mode) {
  toast(mode === 'full' ? 'Запущена полная диагностика…' : 'Быстрая проверка…', 'info');
  const d = await jget('/api/v1/system/diagnostics?mode=' + (mode || 'quick'));
  const box = el('sysDiag');
  if (!d) {
    if (box) box.innerHTML = '<div class="card">Диагностика недоступна (требуются права администратора).</div>';
    return null;
  }
  _diag = d;
  if (box && box.isConnected) box.innerHTML = diagHtml(d);
  toast('Проверка завершена: ' + (d.overall && d.overall.status ? d.overall.status : 'ok'),
    d.overall && d.overall.status === 'ok' ? 'success' : 'error');
  return d;
}

function diagToggle(key) {
  const b = el('diagCat-' + key);
  if (b) b.style.display = b.style.display === 'none' ? '' : 'none';
}

function exportDiagReport() {
  const d = _diag;
  if (!d) { toast('Сначала запустите диагностику', 'info'); return; }
  let txt = 'MEB — ОТЧЁТ О ДИАГНОСТИКЕ\n' + '='.repeat(44) + '\n';
  txt += 'Дата: ' + fmtDt(d.checked_at) + '\n';
  txt += 'Режим: ' + (d.mode === 'full' ? 'Полная' : 'Быстрая') + '\n';
  txt += 'Статус: ' + (d.overall && d.overall.status) + ' (' + (d.overall && d.overall.percent) + '%)\n';
  txt += 'Пройдено: ' + (d.overall && d.overall.ok) + '/' + (d.overall && d.overall.total) + '\n';
  txt += 'Ошибок: ' + (d.overall && d.overall.error) + ' · Предупреждений: ' + (d.overall && d.overall.warning) + '\n';
  txt += 'Версия: ' + (d.version || '') + '\n\n';
  txt += 'ПРОБЛЕМЫ\n' + '-'.repeat(44) + '\n';
  if (d.issues && d.issues.length) {
    d.issues.forEach(it => {
      txt += '[' + it.severity.toUpperCase() + '] ' + (it.id ? it.id + ' ' : '') + it.category + ': ' + it.title + '\n';
      txt += '   ' + it.message + '\n';
      if (it.date) txt += '   дата: ' + it.date + '\n';
      if (it.endpoint) txt += '   endpoint: ' + it.endpoint + '\n';
      if (it.fix) txt += '   решение: ' + it.fix + '\n';
    });
  } else {
    txt += 'Проблем не обнаружено\n';
  }
  txt += '\nКАТЕГОРИИ\n' + '-'.repeat(44) + '\n';
  Object.keys(d.categories || {}).forEach(k => {
    const c = d.categories[k];
    txt += '[' + c.status.toUpperCase() + '] ' + c.label + ' (' + c.ok + '/' + c.total + ')\n';
    (c.items || []).forEach(it => {
      txt += '   - [' + it.status.toUpperCase() + (it.id ? ' ' + it.id : '') + '] ' + it.title + ' — ' + it.message + '\n';
      if (it.date) txt += '      (дата: ' + it.date + ')\n';
    });
  });
  const blob = new Blob([txt], { type: 'text/plain;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'meb-diagnostics-' + (d.checked_at || '').slice(0, 10) + '.txt';
  document.body.appendChild(a);
  a.click();
  setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 100);
}

async function runUpdate() {
  el('updMsg').textContent = 'Обновляем...';
  const r = await jpost('/api/v1/system/update/run');
  el('updMsg').innerHTML = r.ok
    ? `✓ Обновлено до v${r.data.version}. Обновите страницу.`
    : `<span style="color:var(--danger)">Ошибка: ${esc(r.data.error)}. Откат к ${esc(r.data.restoredFrom || '')}</span>`;
}

async function loadApkInfo() {
  try {
    const d = await jget('/api/v1/app/latest');
    if (d.version) {
      el('apkInfo').textContent = 'Версия: v' + d.version + ' · ' + Math.round(d.size / 1024) + ' КБ';
      el('apkDlBtn').style.display = '';
    } else {
      el('apkInfo').textContent = 'APK пока не доступен';
    }
  } catch (e) { el('apkInfo').textContent = 'Нет данных'; }
}

function downloadApk() { window.open('/api/v1/app/download', '_blank'); }

/* ============ INIT ============ */
checkMe();
