const http=require('http'),fs=require('fs'),path=require('path'),url=require('url');
const {load,ROOT}=require('./core/config');
const db=require('./core/database');
const sec=require('./core/security');
const auth=require('./core/auth');
const Router=require('./core/router');
const audit=require('./core/audit');
const analytics=require('./core/analytics');
const notifier=require('./core/notifier');
const images=require('./core/images');

const cfg=load();
const router=new Router();
const migrations=require('./core/migrations');
const ALL_COLS=['pages','catalog','projects','materials','services','reviews','leads','users','categories','settings'];

function send(res,code,data,headers={}){
  const h={...sec.headers(),...headers};
  if(typeof data==='object' && !Buffer.isBuffer(data)){ h['Content-Type']='application/json; charset=utf-8'; data=JSON.stringify(data)}
  res.writeHead(code,h); res.end(data);
}
function mkdirp(p){ try{ fs.mkdirSync(p) }catch(e){ if(e.code!=='EEXIST') throw e } }
function parseBody(req){
  return new Promise(resolve=>{
    let b=''; req.on('data',c=>{b+=c; if(b.length>5e6) req.destroy()}); req.on('end',()=>{
      const ct=req.headers['content-type']||'';
      if(ct.includes('application/json')){ try{resolve(JSON.parse(b||'{}'))}catch(e){resolve({})} }
      else if(ct.includes('application/x-www-form-urlencoded')){ const o={}; new URLSearchParams(b).forEach((v,k)=>o[k]=v); resolve(o)}
      else resolve(b);
    })
  })
}
function mime(p){
  const m={'.html':'text/html','.css':'text/css','.js':'application/javascript','.json':'application/json','.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.webp':'image/webp','.woff2':'font/woff2'};
  return m[path.extname(p)]||'application/octet-stream';
}

// HEALTH
router.get('/api/v1/health', (req,res)=> send(res,200,{status:'ok', version:migrations.current(), node:process.version, time:new Date().toISOString()}))

// AUDIT LOG (admin read-only)
router.get('/api/v1/audit_log', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  send(res,200,db.all('audit_log').slice(-100))
})

// MEDIA delete
router.del('/api/v1/media/:id', async(req,res,params)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'editor')) return send(res,403,{error:'Forbidden'});
  const m=db.byId('media',params.id);
  if(m){ try{ fs.unlinkSync(path.join(ROOT,'storage/uploads',m.filename)) }catch(e){} }
  db.remove('media',params.id);
  audit.log({userId:u.id,action:'delete',entity:'media',entityId:params.id,ip:req.socket.remoteAddress});
  send(res,200,{ok:true})
})

// SEO AUDIT
const seoAudit=require('./modules/seo/audit');
router.get('/api/v1/seo/audit', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u) return send(res,401,{error:'Unauthorized'});
  send(res,200,seoAudit.audit())
})

// SYSTEM UPDATE
router.get('/api/v1/system/update/check', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'super_admin')) return send(res,403,{error:'Forbidden'});
  migrations.fetchLatest(latest=>{
    const current=migrations.current();
    send(res,200,{current, latest:latest||current, updateAvailable:!!(latest&&migrations.cmp(latest,current)>0)})
  });
})
router.post('/api/v1/system/update/run', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'super_admin')) return send(res,403,{error:'Forbidden'});
  // 1. auto backup before update
  const snapshot={}; ALL_COLS.forEach(c=> snapshot[c]=db.all(c));
  const stamp=Date.now();
  mkdirp(path.join(ROOT,'storage/backups'));
  fs.writeFileSync(path.join(ROOT,`storage/backups/pre-update-${stamp}.json`),JSON.stringify(snapshot,null,2));
  // 2. git pull (code updates deploy via git)
  let pullErr=null;
  try{
    require('child_process').execSync('git pull origin master',{cwd:ROOT,timeout:60000,stdio:'pipe'}).toString();
  }catch(e){ pullErr=e.message.slice(0,200) }
  // 3. migrations + health check; rollback data on failure
  try{
    const applied=migrations.runMigrations();
    const healthOK=fs.existsSync(ROOT)&&db.all('users').length>0;
    if(!healthOK) throw new Error('health check failed');
    audit.log({userId:u.id,action:'update',entity:'system',meta:{applied},ip:req.socket.remoteAddress});
    send(res,200,{ok:true, version:migrations.current(), applied})
  }catch(e){
    // rollback: restore pre-update snapshot
    Object.keys(snapshot).forEach(k=> db.setAll(k,snapshot[k]));
    audit.log({userId:u.id,action:'update-rollback',entity:'system',ip:req.socket.remoteAddress});
    send(res,500,{ok:false,error:(pullErr||e.message),restoredFrom:`pre-update-${stamp}`})
  }
})

// ROBOTS.TXT
router.get('/robots.txt', async(req,res)=>{
  send(res,200,`User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /install\nSitemap: /api/v1/seo/sitemap.xml\n`,{'Content-Type':'text/plain'})
})

// AUTH
router.post('/api/v1/auth/login', async(req,res)=>{
  const body=await parseBody(req);
  if(!sec.rateLimit(req.socket.remoteAddress,10,60000,'login')) return send(res,429,{error:'Too many requests'});
  const user=db.all('users').find(u=>u.email===body.email);
  if(!user) return send(res,401,{error:'Invalid credentials'});
  if(!sec.verifyPassword(body.password,user.salt,user.hash)) return send(res,401,{error:'Invalid credentials'});
  const token=sec.sign({id:user.id,email:user.email,role:user.role},cfg.jwtSecret);
  audit.log({userId:user.id,action:'login',entity:'auth',ip:req.socket.remoteAddress});
  send(res,200,{token,user:{id:user.id,email:user.email,name:user.name,role:user.role}}, {'Set-Cookie':`token=${encodeURIComponent(token)}; HttpOnly; Path=/; SameSite=Lax; Max-Age=2592000`});
})
router.post('/api/v1/auth/logout', async(req,res)=> send(res,200,{ok:true},{'Set-Cookie':'token=; HttpOnly; Path=/; Max-Age=0'}))
router.get('/api/v1/me', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u) return send(res,401,{error:'Unauthorized'});
  send(res,200,{id:u.id,email:u.email,name:u.name,role:u.role})
})

// SETTINGS
router.get('/api/v1/settings', async(req,res)=>{ const s=db.all('settings')[0]||{}; send(res,200,s)})
router.put('/api/v1/settings', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  const body=await parseBody(req);
  const cur=db.all('settings')[0]||{id:'site'};
  let rec;
  if(cur.id) rec=db.update('settings',cur.id,body) || db.insert('settings',{id:'site',...body});
  else rec=db.insert('settings',{id:'site',...body});
  audit.log({userId:u.id,action:'update',entity:'settings',ip:req.socket.remoteAddress});
  send(res,200,rec)
})

// GENERIC CRUD FACTORY
function crud(col, need='editor'){
  router.get(`/api/v1/${col}`, async(req,res)=>{
    const q=url.parse(req.url,true).query;
    let items=db.all(col);
    if(q.slug) items=items.filter(x=>x.slug===q.slug);
    if(q.category) items=items.filter(x=>x.category===q.category||x.categoryId===q.category);
    if(q.published) items=items.filter(x=>String(x.published)===q.published);
    if(q.q){ const qq=q.q.toLowerCase(); items=items.filter(x=>JSON.stringify(x).toLowerCase().includes(qq))}
    send(res,200,items)
  })
  router.get(`/api/v1/${col}/:id`, async(req,res,params)=>{
    const item=db.byId(col,params.id)||db.bySlug(col,params.id);
    if(!item) return send(res,404,{error:'Not found'});
    send(res,200,item)
  })
  router.post(`/api/v1/${col}`, async(req,res)=>{
    const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,need)) return send(res,403,{error:'Forbidden'});
    const body=await parseBody(req);
    const rec=db.insert(col,body);
    audit.log({userId:u.id,action:'create',entity:col,entityId:rec.id,ip:req.socket.remoteAddress});
    send(res,200,rec)
  })
  router.put(`/api/v1/${col}/:id`, async(req,res,params)=>{
    const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,need)) return send(res,403,{error:'Forbidden'});
    const body=await parseBody(req);
    const rec=db.update(col,params.id,body);
    if(!rec) return send(res,404,{error:'Not found'});
    audit.log({userId:u.id,action:'update',entity:col,entityId:params.id,ip:req.socket.remoteAddress});
    send(res,200,rec)
  })
  router.del(`/api/v1/${col}/:id`, async(req,res,params)=>{
    const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,need)) return send(res,403,{error:'Forbidden'});
    const ok=db.remove(col,params.id);
    if(!ok) return send(res,404,{error:'Not found'});
    audit.log({userId:u.id,action:'delete',entity:col,entityId:params.id,ip:req.socket.remoteAddress});
    send(res,200,{ok:true})
  })
}
crud('pages','editor');
crud('categories','editor');
crud('catalog','editor');
crud('projects','editor');
crud('materials','editor');
crud('services','editor');
crud('reviews','editor');
crud('leads','manager');

// USERS special handling (hash password, protect role escalation, never expose hash)
router.get('/api/v1/users', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  send(res,200,db.all('users').map(x=>({id:x.id,email:x.email,name:x.name,role:x.role,createdAt:x.createdAt})))
})
router.get('/api/v1/users/:id', async(req,res,params)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  const x=db.byId('users',params.id); if(!x) return send(res,404,{error:'Not found'});
  send(res,200,{id:x.id,email:x.email,name:x.name,role:x.role})
})
router.del('/api/v1/users/:id', async(req,res,params)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  if(u.id===params.id) return send(res,400,{error:'Нельзя удалить себя'});
  const admins=db.all('users').filter(x=>x.role==='super_admin'||x.role==='admin');
  const target=db.byId('users',params.id);
  if(target&&(target.role==='super_admin'||target.role==='admin')&&admins.length<=1) return send(res,400,{error:'Последний админ не может быть удалён'});
  const ok=db.remove('users',params.id);
  if(!ok) return send(res,404,{error:'Not found'});
  audit.log({userId:u.id,action:'delete',entity:'users',entityId:params.id,ip:req.socket.remoteAddress});
  send(res,200,{ok:true})
})
router.post('/api/v1/users', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  const body=await parseBody(req);
  if(!body.email||!body.password) return send(res,400,{error:'email и password обязательны'});
  const {salt,hash}=sec.hashPassword(body.password);
  const rec=db.insert('users',{email:sec.sanitize(body.email),name:sec.sanitize(body.name||''),role:['super_admin','admin','manager','editor'].includes(body.role)?body.role:'editor',salt,hash});
  audit.log({userId:u.id,action:'create',entity:'users',entityId:rec.id,ip:req.socket.remoteAddress});
  send(res,200,{id:rec.id,email:rec.email,name:rec.name,role:rec.role})
})
router.put('/api/v1/users/:id', async(req,res,params)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  const body=await parseBody(req);
  const patch={};
  if(body.email) patch.email=sec.sanitize(body.email);
  if(body.name) patch.name=sec.sanitize(body.name);
  if(body.role&&['super_admin','admin','manager','editor'].includes(body.role)) patch.role=body.role;
  if(body.password){ const {salt,hash}=sec.hashPassword(body.password); patch.salt=salt; patch.hash=hash }
  const rec=db.update('users',params.id,patch);
  if(!rec) return send(res,404,{error:'Not found'});
  audit.log({userId:u.id,action:'update',entity:'users',entityId:params.id,ip:req.socket.remoteAddress});
  send(res,200,{id:rec.id,email:rec.email,name:rec.name,role:rec.role})
})

// LEADS public create
router.post('/api/v1/leads-public', async(req,res)=>{
  const body=await parseBody(req);
  if(!body.name||!body.phone) return send(res,400,{error:'Name and phone required'});
  const rec=db.insert('leads',{...body,status:'new',createdAt:new Date().toISOString()});
  notifier.push('lead','Новая заявка: '+(body.name||''),`Телефон: ${body.phone}. ${body.message||''}`);
  send(res,200,rec)
})

// NOTIFICATIONS API
router.get('/api/v1/notifications', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u) return send(res,401,{error:'Unauthorized'});
  send(res,200,db.all('notifications').slice(-50).reverse())
})
router.put('/api/v1/notifications/read-all', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u) return send(res,401,{error:'Unauthorized'});
  db.all('notifications').forEach(n=>db.update('notifications',n.id,{read:true}));
  send(res,200,{ok:true})
})

// SEO
router.get('/api/v1/seo/sitemap.xml', async(req,res)=>{
  const pages=db.all('pages'), projects=db.all('projects'), cats=db.all('categories'), items=db.all('catalog');
  let xml=`<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">`;
  xml+=`<url><loc>/</loc></url><url><loc>/materials</loc></url><url><loc>/services</loc></url>`;
  pages.filter(p=>p.published!==false).forEach(p=> xml+=`<url><loc>/p/${p.slug}</loc></url>`);
  projects.filter(p=>p.published!==false).forEach(p=> xml+=`<url><loc>/project/${p.slug}</loc></url>`);
  cats.forEach(c=> xml+=`<url><loc>/catalog/${c.slug}</loc></url>`);
  items.filter(i=>i.published!==false).forEach(i=>{
    const c=cats.find(c=>c.id===i.categoryId);
    if(c) xml+=`<url><loc>/catalog/${c.slug}/${i.slug}</loc></url>`;
  });
  xml+=`</urlset>`;
  send(res,200,xml,{'Content-Type':'application/xml'})
})

// ANALYTICS
router.get('/api/v1/analytics/summary', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u) return send(res,401,{error:'Unauthorized'});
  const views=analytics.summary(14);
  send(res,200,{leads:db.all('leads').length, projects:db.all('projects').length, catalog:db.all('catalog').length, reviews:db.all('reviews').length, users:db.all('users').length, views})
})

// BACKUP
router.get('/api/v1/backup/list', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  send(res,200,db.all('backups'))
})
router.post('/api/v1/backup/create', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'admin')) return send(res,403,{error:'Forbidden'});
  const id=Date.now().toString();
  const snapshot={}; ALL_COLS.forEach(c=> snapshot[c]=db.all(c));
  const file=path.join(ROOT,`storage/backups/backup-${id}.json`);
  mkdirp(path.join(ROOT,'storage/backups'));
  fs.writeFileSync(file,JSON.stringify(snapshot,null,2));
  const rec=db.insert('backups',{filename:`backup-${id}.json`,size:fs.statSync(file).size, type:'manual'});
  audit.log({userId:u.id,action:'backup',entity:'system',ip:req.socket.remoteAddress});
  send(res,200,rec)
})
router.post('/api/v1/backup/restore/:id', async(req,res,params)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'super_admin')) return send(res,403,{error:'Forbidden'});
  const b=db.byId('backups',params.id); if(!b) return send(res,404,{error:'Not found'});
  // pre-restore backup
  const snap={}; ALL_COLS.forEach(c=> snap[c]=db.all(c));
  fs.writeFileSync(path.join(ROOT,`storage/backups/pre-restore-${Date.now()}.json`),JSON.stringify(snap,null,2));
  const data=JSON.parse(fs.readFileSync(path.join(ROOT,`storage/backups/${b.filename}`),'utf8'));
  Object.keys(data).forEach(k=> db.setAll(k,data[k]));
  audit.log({userId:u.id,action:'restore',entity:'system',entityId:b.id,ip:req.socket.remoteAddress});
  send(res,200,{ok:true})
})

// MEDIA upload (base64 JSON fallback, no multipart dep)
// MEDIA list (with folder filter)
router.get('/api/v1/media', async(req,res)=>{
  const q=url.parse(req.url,true).query;
  let items=db.all('media');
  if(q.folder!==undefined) items=items.filter(m=>(m.folder||'')===q.folder);
  send(res,200,items)
})
router.post('/api/v1/media/upload', async(req,res)=>{
  const u=auth.getUserFromReq(req,cfg.jwtSecret); if(!u||!auth.can(u,'editor')) return send(res,403,{error:'Forbidden'});
  const body=await parseBody(req);
  // expects {filename, data: base64, alt, folder}
  if(!body.filename||!body.data) return send(res,400,{error:'filename and data required'});
  const buf=Buffer.from(body.data.split(',').pop(), 'base64');
  if(buf.length>cfg.uploadMax) return send(res,400,{error:'File too large'});
  const ext=path.extname(body.filename)||'.jpg';
  const name=Date.now().toString(36)+ext;
  // folder: safe subdir inside uploads
  let relDir='storage/uploads';
  if(body.folder){
    const safe=String(body.folder).replace(/[^a-zA-Z0-9_\-]/g,'-').replace(/^-+|-+$/g,'').slice(0,40);
    if(safe){ relDir='storage/uploads/'+safe }
  }
  mkdirp(path.join(ROOT,relDir));
  fs.writeFileSync(path.join(ROOT,relDir,name),buf);
  const dims=images.dimensions(buf)||{};
  const rec=db.insert('media',{filename:name, originalName:body.filename, size:buf.length, width:dims.width, height:dims.height, folder:body.folder?String(body.folder).replace(/[^a-zA-Z0-9_\-]/g,'-').slice(0,40):'', alt:body.alt||'', url:`/${relDir}/${name}`});
  send(res,200,rec)
})

// INSTALLER API
router.get('/api/v1/install/check', async(req,res)=>{
  const checks={node:process.version, storageWritable:true, version:'1.0.0'};
  try{fs.accessSync(path.join(ROOT,'storage'),fs.constants.W_OK)}catch(e){checks.storageWritable=false}
  const installed=fs.existsSync(path.join(ROOT,'storage/installed.lock'));
  send(res,200,{...checks,installed})
})
router.post('/api/v1/install/run', async(req,res)=>{
  if(fs.existsSync(path.join(ROOT,'storage/installed.lock'))) return send(res,400,{error:'Already installed'});
  const body=await parseBody(req);
  if(!body.email||!body.password) return send(res,400,{error:'email/password required'});
  // create admin
  const {salt,hash}=sec.hashPassword(body.password);
  db.insert('users',{email:body.email,name:body.name||'Admin',role:'super_admin',salt,hash});
  db.insert('settings',{id:'site',siteName:body.siteName||'MEB',tagline:'Индивидуальная мебель',phone:body.phone||'',email:body.email});
  if(body.demo){
    try{
      const seed=JSON.parse(fs.readFileSync(path.join(ROOT,'storage/demo-seed.json'),'utf8'));
      Object.keys(seed).forEach(k=>{ if(Array.isArray(seed[k])) db.setAll(k,seed[k])});
    }catch(e){}
  }
  fs.writeFileSync(path.join(ROOT,'storage/installed.lock'),new Date().toISOString());
  send(res,200,{ok:true})
})

// CMS PAGE RENDERING (/p/:slug)
const pageRender=require('./modules/pages/render');
router.get('/p/:slug', async(req,res,params)=>{
  const page=db.all('pages').find(x=>x.slug===params.slug && x.published!==false);
  if(!page) return send(res,404,'<!doctype html><html><body style="font-family:sans-serif;padding:60px;text-align:center"><h1>404</h1><a href="/">На главную</a></body></html>',{'Content-Type':'text/html; charset=utf-8'});
  const html=pageRender.renderPage(page);
  res.writeHead(200,{...sec.headers(),'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-cache'});
  res.end(html);
})

// PUBLIC SITE: project & catalog pages
const siteRender=require('./frontend/render');
function langOf(req){
  const q=url.parse(req.url,true).query;
  if(q.lang) return String(q.lang)==='en'?'en':'ru';
  const al=(req.headers['accept-language']||'').toLowerCase();
  return al.startsWith('en')?'en':'ru';
}
router.get('/project/:slug', async(req,res,params)=>{
  const p=db.bySlug('projects',params.slug);
  if(!p||p.published===false) return send(res,404,'404 · <a href="/">На главную</a>',{'Content-Type':'text/html; charset=utf-8'});
  res.writeHead(200,{...sec.headers(),'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-cache'});
  res.end(siteRender.renderProject(p,langOf(req)));
})
router.get('/catalog/:cat/:slug', async(req,res,params)=>{
  const cat=db.bySlug('categories',params.cat);
  const item=db.all('catalog').find(x=>x.slug===params.slug&&x.categoryId===(cat&&cat.id));
  if(!item) return send(res,404,'404 · <a href="/">На главную</a>',{'Content-Type':'text/html; charset=utf-8'});
  res.writeHead(200,{...sec.headers(),'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-cache'});
  res.end(siteRender.renderItem(item,cat,langOf(req)));
})
router.get('/catalog/:cat', async(req,res,params)=>{
  const cat=db.bySlug('categories',params.cat);
  if(!cat) return send(res,404,'404 · <a href="/">На главную</a>',{'Content-Type':'text/html; charset=utf-8'});
  const items=db.all('catalog').filter(x=>x.categoryId===cat.id&&x.published!==false);
  res.writeHead(200,{...sec.headers(),'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-cache'});
  res.end(siteRender.renderCategory(cat,items,langOf(req)));
})
router.get('/materials', async(req,res)=>{
  res.writeHead(200,{...sec.headers(),'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-cache'});
  res.end(siteRender.renderMaterialsPage(db.all('materials'),langOf(req)));
})
router.get('/services', async(req,res)=>{
  res.writeHead(200,{...sec.headers(),'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-cache'});
  res.end(siteRender.renderServicesPage(db.all('services'),langOf(req)));
})

// STATIC + PERFORMANCE (gzip, ETag)
const zlib=require('zlib');
function acceptsGzip(req){ return (req.headers['accept-encoding']||'').includes('gzip') }
function respondWithCache(req,res,data,type,cacheControl){
  const etag='"'+require('crypto').createHash('md5').update(data).digest('hex').slice(0,16)+'"';
  if(req.headers['if-none-match']===etag){
    res.writeHead(304,{...sec.headers(),'ETag':etag,'Cache-Control':cacheControl});
    return res.end();
  }
  const headers={...sec.headers(),'Content-Type':type,'Cache-Control':cacheControl,'ETag':etag,'Vary':'Accept-Encoding'};
  if(acceptsGzip(req)&&data.length>1024&&/text|json|javascript|svg/.test(type)){
    zlib.gzip(data,(err,gz)=>{
      if(err) { res.writeHead(200,headers); return res.end(data) }
      res.writeHead(200,{...headers,'Content-Encoding':'gzip','Content-Length':gz.length});
      res.end(gz);
    });
  } else {
    res.writeHead(200,{...headers,'Content-Length':data.length});
    res.end(data);
  }
}
function serveStatic(req,res){
  const parsed=url.parse(req.url);
  let p=parsed.pathname;
  if(p==='/' ) p='/frontend/index.html';
  else if(p==='/admin' || p==='/admin/') p='/admin/index.html';
  else if(p==='/install' || p==='/install/') p='/installer/index.html';
  const full=path.join(ROOT,p);
  if(!full.startsWith(ROOT)) return send(res,403,'Forbidden');
  if(fs.existsSync(full) && fs.statSync(full).isFile()){
    const data=fs.readFileSync(full);
    return respondWithCache(req,res,data,mime(full),p.includes('/storage/')?'public, max-age=31536000, immutable':'public, max-age=300');
  }
  // SPA fallback for frontend pretty urls
  if(!p.startsWith('/api/') && !p.startsWith('/storage/')){
    const idx=fs.readFileSync(path.join(ROOT,'frontend/index.html'));
    return respondWithCache(req,res,idx,'text/html; charset=utf-8','public, max-age=60');
  }
  send(res,404,{error:'Not found'})
}

const server=http.createServer(async(req,res)=>{
  if(!sec.rateLimit(req.socket.remoteAddress,120,60000,'api')) return send(res,429,{error:'Rate limit'});
  analytics.track(url.parse(req.url).pathname);
  const m=router.match(req);
  if(m){
    try{ await m.handler(req,res,m.params)}catch(e){ console.error(e); send(res,500,{error:'Internal error'})}
  } else {
    serveStatic(req,res);
  }
});

// SCHEDULED AUTO-BACKUP
setInterval(()=>{
  try{
    const s=db.all('settings')[0]||{};
    const sched=s.backupSchedule;
    if(!sched||sched==='off') return;
    const last=s.lastAutoBackup?new Date(s.lastAutoBackup).getTime():0;
    const intervalMs=sched==='daily'?86400000:sched==='weekly'?604800000:0;
    if(!intervalMs) return;
    if(Date.now()-last<intervalMs) return;
    const snapshot={}; ALL_COLS.forEach(c=> snapshot[c]=db.all(c));
    mkdirp(path.join(ROOT,'storage/backups'));
    const id=Date.now().toString();
    const file=path.join(ROOT,`storage/backups/backup-${id}.json`);
    fs.writeFileSync(file,JSON.stringify(snapshot,null,2));
    db.insert('backups',{filename:`backup-${id}.json`,size:fs.statSync(file).size,type:'auto'});
    const cur=db.all('settings')[0];
    if(cur&&cur.id) db.update('settings',cur.id,{lastAutoBackup:new Date().toISOString()});
    notifier.push('backup','Авто-бэкап создан',`Расписание: ${sched}. Файл: backup-${id}.json`);
  }catch(e){}
},60000);

const PORT=cfg.port;
server.listen(PORT,()=> console.log(`MEB running http://localhost:${PORT}`));
