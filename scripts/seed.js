const fs=require('fs'),path=require('path');
const ROOT=path.join(__dirname,'..');
const sec=require('../core/security');
const db=require('../core/database');

const args={};
process.argv.slice(2).forEach(a=>{
  const m=a.match(/^--([^=]+)(?:=(.*))?$/);
  if(m) args[m[1]]=m[2]!==undefined?m[2]:true;
});
const email=args.email||'admin@meb.local';
const password=args.password||'Admin123!';
const name=args.name||'Admin';
const siteName=args.siteName||'MEB';
const phone=args.phone||'';
if(db.all('users').length>0){ console.error('Users already exist — refusing to overwrite. Delete storage/data/*.json first.'); process.exit(1); }

const {salt,hash}=sec.hashPassword(password);
db.insert('users',{email,name,role:'super_admin',salt,hash});
db.insert('settings',{id:'site',siteName,tagline:'Индивидуальная мебель',phone,email});
if(args.demo){
  try{
    const seed=JSON.parse(fs.readFileSync(path.join(ROOT,'storage/demo-seed.json'),'utf8'));
    Object.keys(seed).forEach(k=>{ if(Array.isArray(seed[k])) db.setAll(k,seed[k]) });
    console.log('Demo data seeded.');
  }catch(e){ console.error('demo-seed.json not readable:',e.message); process.exit(1); }
}
console.log('Done. Login:',email);
