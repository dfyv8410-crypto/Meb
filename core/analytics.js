const db=require('./database');
function today(){ return new Date().toISOString().slice(0,10) }
function track(path){
  // only public pages
  if(!path.startsWith('/api/') && !path.startsWith('/admin') && !path.startsWith('/install') && !/\.(js|css|svg|png|jpg|webp|woff2|ico)$/.test(path)){
    const arr=db.all('analytics');
    const t=today();
    let rec=arr.find(x=>x.date===t);
    if(rec){ db.update('analytics',rec.id,{views:rec.views+1}) } else { db.insert('analytics',{date:t,views:1}) }
  }
}
function summary(days){
  const arr=db.all('analytics').sort((a,b)=>a.date<b.date?-1:1);
  const total=arr.reduce((s,x)=>s+x.views,0);
  const last=days?arr.slice(-days):arr;
  return {total,today:(arr.find(x=>x.date===today())||{views:0}).views, days:last.map(x=>({date:x.date,views:x.views}))};
}
module.exports={track,summary};
