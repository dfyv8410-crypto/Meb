const fs=require('fs'),path=require('path'),https=require('https');
const ROOT=path.join(__dirname,'..');
const VERSION_FILE=path.join(ROOT,'VERSION');
function current(){
  try{ return fs.readFileSync(VERSION_FILE,'utf8').trim() }catch(e){ return '1.0.0' }
}
const MIGRATIONS=[
  {v:'1.1.0', run(){ /* ensure settings has seo object */ }},
  {v:'1.2.0', run(){ /* reserved */ }}
];
function meta(){
  try{ return JSON.parse(fs.readFileSync(path.join(ROOT,'storage/data/_meta.json'),'utf8')) }catch(e){ return {version:null} }
}
function saveMeta(m){
  const tmp=path.join(ROOT,'storage/data/_meta.json.tmp');
  fs.writeFileSync(tmp,JSON.stringify(m,null,2));
  fs.renameSync(tmp,path.join(ROOT,'storage/data/_meta.json'));
}
function pending(fromVersion){
  if(!fromVersion||fromVersion==='0') return [];
  return MIGRATIONS.filter(m=>cmp(m.v,fromVersion)>0);
}
function cmp(a,b){
  const pa=String(a).split('.').map(Number), pb=String(b).split('.').map(Number);
  for(let i=0;i<3;i++){ const d=(pa[i]||0)-(pb[i]||0); if(d) return d }
  return 0;
}
function runMigrations(){
  const m=meta();
  let from=m.version||current();
  const list=pending(from);
  list.forEach(mig=>{ mig.run(); from=mig.v });
  saveMeta({version:current(), lastMigration:new Date().toISOString()});
  return list.map(x=>x.v);
}
function fetchLatest(cb){
  // reads VERSION file of the repo's master branch on GitHub
  https.get('https://raw.githubusercontent.com/dfyv8410-crypto/Meb/master/VERSION',res=>{
    if(res.statusCode!==200) return cb(null);
    let d=''; res.on('data',c=>d+=c); res.on('end',()=>cb(d.trim()));
  }).on('error',()=>cb(null)).setTimeout(6000,function(){this.destroy();cb(null)});
}
module.exports={current,runMigrations,pending,fetchLatest,cmp};
