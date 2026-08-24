const fs=require('fs'),path=require('path');
const cache={};
function load(lang){
  if(cache[lang]) return cache[lang];
  try{ return cache[lang]=JSON.parse(fs.readFileSync(path.join(__dirname,'../locales',lang+'.json'),'utf8')) }
  catch(e){ return cache[lang]={} }
}
function t(lang,key,fallback){
  const dict=load(lang||'ru');
  if(dict[key]) return dict[key];
  if(lang!=='ru'){ const r=load('ru'); if(r[key]) return r[key] }
  return fallback!==undefined?fallback:key;
}
module.exports={t};
