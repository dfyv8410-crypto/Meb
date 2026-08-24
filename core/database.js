const fs=require('fs'),path=require('path');
const ROOT=path.join(__dirname,'..');
const DATA=path.join(ROOT,'storage/data');
function ensure(){ try{fs.mkdirSync(DATA)}catch(e){ if(e.code!=='EEXIST') throw e } }
function file(col){return path.join(DATA,col+'.json')}
function read(col){
  ensure();
  try{ return JSON.parse(fs.readFileSync(file(col),'utf8'))}catch(e){ return []}
}
function write(col,data){
  ensure();
  const tmp=file(col)+'.tmp';
  fs.writeFileSync(tmp,JSON.stringify(data,null,2));
  fs.renameSync(tmp,file(col));
}
function uid(){ return Date.now().toString(36)+Math.random().toString(36).slice(2,7)}
function now(){ return new Date().toISOString()}
const db={
  all(c){return read(c)},
  byId(c,id){return read(c).find(x=>x.id===id)||null},
  bySlug(c,slug){return read(c).find(x=>x.slug===slug)||null},
  find(c,fn){return read(c).filter(fn)},
  insert(c,doc){
    const arr=read(c); const rec={id:uid(),createdAt:now(),updatedAt:now(),...doc};
    arr.push(rec); write(c,arr); return rec;
  },
  update(c,id,patch){
    const arr=read(c); const i=arr.findIndex(x=>x.id===id); if(i<0) return null;
    arr[i]={...arr[i],...patch,updatedAt:now()}; write(c,arr); return arr[i];
  },
  upsert(c,doc){
    if(doc.id){ const ex=db.byId(c,doc.id); if(ex) return db.update(c,doc.id,doc) }
    return db.insert(c,doc);
  },
  remove(c,id){
    const arr=read(c); const n=arr.filter(x=>x.id!==id); if(n.length===arr.length) return false;
    write(c,n); return true;
  },
  setAll(c,arr){ write(c,arr) }
};
module.exports=db;
