const fs=require('fs'),path=require('path');
const ROOT=path.join(__dirname,'..');
function load(){
  let cfg={port:3000, siteName:'MEB — Премиальная мебель', tagline:'Индивидуальная мебель под ваше пространство', jwtSecret:'meb-premium-secret-change-me-2026', uploadMax:15*1024*1024};
  try{ const j=JSON.parse(fs.readFileSync(path.join(ROOT,'storage/data/_config.json'),'utf8')); cfg={...cfg,...j}; }catch(e){}
  if(process.env.PORT) cfg.port=+process.env.PORT;
  return cfg;
}
module.exports={load,ROOT};
