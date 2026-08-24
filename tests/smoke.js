const http=require('http'),URL=require('url');
const BASE='http://localhost:3000';
let token='';
function req(method,p,body,useAuth=true){
  return new Promise((res,rej)=>{
    const o=URL.parse(BASE+p); o.method=method;
    const headers={'Content-Type':'application/json'};
    if(useAuth&&token) headers.Authorization='Bearer '+token;
    if(body) headers['Content-Length']=Buffer.byteLength(body);
    o.headers=headers;
    const r=http.request(o,res2=>{
      let d=''; res2.on('data',c=>d+=c);
      res2.on('end',()=>{ let j=null; try{j=JSON.parse(d)}catch(e){}; res({status:res2.statusCode,json:j,raw:d})});
    });
    r.on('error',rej);
    if(body) r.write(body);
    r.end();
  });
}
async function run(){
  // CLEAN SLATE: wipe all state so every run is identical
  const fs=require('fs');
  try{fs.unlinkSync('storage/installed.lock')}catch(e){}
  fs.readdirSync('storage/data').forEach(f=>{ if(f.endsWith('.json')) try{fs.unlinkSync('storage/data/'+f)}catch(e){} });
  const t=(n,ok,extra='')=>console.log((ok?'PASS':'FAIL')+' | '+n+(extra?' | '+extra:''));
  // 1 install check
  let r=await req('GET','/api/v1/install/check',null,false);
  t('install/check',r.status===200&&r.json.installed===false);
  // 2 install run with demo
  r=await req('POST','/api/v1/install/run',JSON.stringify({email:'admin@meb.local',password:'Admin123!',name:'Admin',siteName:'MEB',phone:'+7 900 000 00 00',demo:true}),false);
  t('install/run + demo',r.status===200,r.status+':'+(r.raw||'').slice(0,80));
  // 3 installer locked
  r=await req('POST','/api/v1/install/run',JSON.stringify({email:'x@x.x',password:'x'}),false);
  t('installer blocked after install',r.status===400);
  // 4 demo data seeded
  r=await req('GET','/api/v1/projects?published=true',null,false);
  const demoProjects=r.json||[];
  t('demo projects seeded',r.status===200&&demoProjects.length>=3,'count='+(demoProjects.length));
  // 5 login wrong
  r=await req('POST','/api/v1/auth/login',JSON.stringify({email:'admin@meb.local',password:'wrong'}),false);
  t('login wrong password →401',r.status===401);
  // 6 login ok
  r=await req('POST','/api/v1/auth/login',JSON.stringify({email:'admin@meb.local',password:'Admin123!'}),false);
  t('login ok',r.status===200&&r.json.token);
  token=r.json.token;
  // 7 me
  r=await req('GET','/api/v1/me');
  t('/me role super_admin',r.status===200&&r.json.role==='super_admin');
  // 8 unauthorized create
  const savedToken=token; token='';
  r=await req('POST','/api/v1/projects',JSON.stringify({title:'hack'}));
  t('unauthorized create →403',r.status===403);
  token=savedToken;
  // 9 CRUD project
  r=await req('POST','/api/v1/projects',JSON.stringify({title:'Test Project QA',slug:'test-project-qa',desc:'qa'}));
  t('create project',r.status===200&&r.json.id);
  const pid=r.json.id;
  r=await req('PUT','/api/v1/projects/'+pid,JSON.stringify({title:'QA Updated'}));
  t('update project',r.status===200&&r.json.title==='QA Updated');
  // 10 public lead
  r=await req('POST','/api/v1/leads-public',JSON.stringify({name:'QA Client',phone:'+79991112233'}),false);
  t('public lead create',r.status===200&&r.json.id);
  const lid=r.json.id;
  // 11 lead status update (manager can)
  r=await req('PUT','/api/v1/leads/'+lid,JSON.stringify({status:'in_progress'}));
  t('lead status update',r.status===200&&r.json.status==='in_progress');
  // 12 validation: lead without phone
  r=await req('POST','/api/v1/leads-public',JSON.stringify({name:'no-phone'}),false);
  t('lead missing phone →400',r.status===400);
  // 13 backup create + list
  r=await req('POST','/api/v1/backup/create',JSON.stringify({}));
  t('backup create',r.status===200&&r.json.filename);
  const bid=r.json.id;
  r=await req('GET','/api/v1/backup/list');
  t('backup list',r.status===200&&r.json.some(b=>b.id===bid));
  // 14 restore pre-restore safety
  r=await req('POST','/api/v1/backup/restore/'+bid,JSON.stringify({}));
  t('backup restore',r.status===200);
  const preRestore=fs.readdirSync('storage/backups').filter(f=>f.startsWith('pre-restore-'));
  t('pre-restore snapshot created',preRestore.length>=1);
  // 15 settings update
  r=await req('PUT','/api/v1/settings',JSON.stringify({siteName:'MEB Premium',phone:'+7 900 111 22 33'}));
  t('settings update',r.status===200&&r.json.siteName==='MEB Premium');
  // 16 sitemap xml
  r=await req('GET','/api/v1/seo/sitemap.xml',null,false);
  t('sitemap.xml',r.raw.includes('<urlset'),'len='+r.raw.length);
  // 17 analytics
  r=await req('GET','/api/v1/analytics/summary');
  t('analytics summary',r.status===200&&r.json.projects>=3);
  // 18 media upload
  const b64=Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>').toString('base64');
  r=await req('POST','/api/v1/media/upload',JSON.stringify({filename:'test.svg',data:b64,alt:'QA test image'}));
  t('media upload',r.status===200&&r.json.url,'url='+(r.json.url||''));
  const mediaUrl=r.json.url;
  // 19 media file served
  r=await req('GET',mediaUrl,null,false);
  t('media served',r.status===200&&r.raw.includes('<svg'));
  // 20 delete project
  r=await req('DELETE','/api/v1/projects/'+pid);
  t('delete project',r.status===200);
  // 21 static frontend
  r=await req('GET','/',null,false);
  t('frontend index.html',r.status===200&&r.raw.includes('hero'));
  r=await req('GET','/admin',null,false);
  t('admin panel html',r.status===200&&r.raw.includes('MEB Admin'));
  r=await req('GET','/install',null,false);
  t('installer html',r.status===200&&r.raw.includes('Installer'));
  // 22 security headers
  const h=require('http');
  await new Promise(res=>{
    h.get(BASE+'/',rs=>{ const hd=rs.headers;
      t('security headers CSP+nosniff',!!hd['content-security-policy']&&hd['x-content-type-options']==='nosniff');
      res();
    });
  });
  // 23 users: hash never exposed + create with hashed password
  r=await req('GET','/api/v1/users');
  t('users list no salt/hash leak',r.status===200&&r.json.every(x=>!x.salt&&!x.hash),'count='+r.json.length);
  r=await req('POST','/api/v1/users',JSON.stringify({email:'manager@meb.local',password:'Manager123!',name:'Manager QA',role:'manager'}));
  t('create user',r.status===200&&r.json.role==='manager');
  const muid=r.json.id;
  // 24 new user can login and is scoped
  r=await req('POST','/api/v1/auth/login',JSON.stringify({email:'manager@meb.local',password:'Manager123!'}),false);
  t('new user login',r.status===200);
  const mgrToken=r.json.token; token=mgrToken;
  r=await req('GET','/api/v1/users');
  t('manager cannot list users →403',r.status===403);
  r=await req('POST','/api/v1/backup/create',JSON.stringify({}));
  t('manager cannot backup →403',r.status===403);
  token=savedToken;
  r=await req('DELETE','/api/v1/users/'+muid);
  t('delete user',r.status===200);
  // 25 cannot delete last admin (self)
  const me=await req('GET','/api/v1/me');
  r=await req('DELETE','/api/v1/users/'+me.json.id);
  t('cannot delete last admin/self',r.status===400);
  // 26 PAGE BUILDER end-to-end
  const blocks=[
    {type:'hero',data:{title:'QA Hero'}},
    {type:'features',data:{items:[{title:'F1',desc:'d',kicker:'01'}]}},
    {type:'faq',data:{items:[{q:'Q?',a:'A'}]}},
    {type:'contact',data:{}}
  ];
  r=await req('POST','/api/v1/pages',JSON.stringify({title:'QA Builder',slug:'qa-builder',blocks,published:true}));
  const pageId=r.json.id;
  t('builder: create page with blocks',r.status===200&&pageId);
  r=await req('GET','/p/qa-builder',null,false);
  t('builder: all blocks render',r.raw.includes('QA Hero')&&r.raw.includes('F1')&&r.raw.includes('Q?')&&r.raw.includes('submitLead'));
  blocks[0].hidden=true;
  await req('PUT','/api/v1/pages/'+pageId,JSON.stringify({blocks}));
  r=await req('GET','/p/qa-builder',null,false);
  t('builder: hidden block not rendered',!r.raw.includes('QA Hero'));
  // 27 SEO audit endpoint
  r=await req('GET','/api/v1/seo/audit');
  const sa=r.json||{};
  t('seo audit score+issues shape',typeof sa.score==='number'&&Array.isArray(sa.issues));
  // 28 robots.txt + sitemap registered pages
  r=await req('GET','/robots.txt',null,false);
  t('robots.txt served',r.raw.includes('Disallow: /admin'));
  // cleanup builder page
  await req('DELETE','/api/v1/pages/'+pageId);
  // 29 ANALYTICS pageviews
  await req('GET','/',null,false); await req('GET','/p/about',null,false);
  r=await req('GET','/api/v1/analytics/summary');
  t('pageviews tracked',r.status===200&&r.json.views&&r.json.views.total>=2,'total='+(r.json.views||{}).total);
  // 30 NOTIFICATIONS on new lead
  r=await req('POST','/api/v1/leads-public',JSON.stringify({name:'Notify QA',phone:'+79990001122'}),false);
  r=await req('GET','/api/v1/notifications');
  const notes=r.json||[];
  t('notification created for lead',notes.some(n=>n.type==='lead'));
  r=await req('PUT','/api/v1/notifications/read-all',JSON.stringify({}));
  t('notifications read-all',r.status===200);
  // 31 MEDIA dimensions
  const png=Buffer.from('89504e470d0a1a0a0000000d4948445200000064000000320802000000','hex');
  const b64png=png.toString('base64');
  r=await req('POST','/api/v1/media/upload',JSON.stringify({filename:'dim.png',data:b64png,alt:'dims test'}));
  t('media width/height extracted',r.status===200&&r.json.width===100&&r.json.height===50,r.json.width+'x'+r.json.height);
  console.log('\nDONE');
}
run().catch(e=>{console.error('SUITE ERROR',e);process.exit(1)});
