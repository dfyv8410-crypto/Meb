const db=require('./database');
const mailer=require('./mailer');
const https=require('https');
function push(type,title,body,meta){
  const rec=db.insert('notifications',{type,title,body,meta:meta||null,read:false});
  deliver(rec);
  return rec;
}
function deliver(rec){
  const s=db.all('settings')[0]||{};
  const email=s.notifyEmail;
  if(email){ mailer.sendEmail({to:email,subject:rec.title,text:rec.body},err=>{ if(err) db.update('notifications',rec.id,{emailError:String(err.message)}) }) }
  const fcm=(s.fcm&&s.fcm.key)||null;
  if(fcm&&s.fcm.topic){
    const data=JSON.stringify({to:'/topics/'+s.fcm.topic,priority:'high',notification:{title:rec.title,body:rec.body}});
    const req=https.request({hostname:'fcm.googleapis.com',path:'/fcm/send',method:'POST',
      headers:{'Content-Type':'application/json','Authorization':'key='+fcm,'Content-Length':Buffer.byteLength(data)}},res=>{
        let b=''; res.on('data',c=>b+=c); res.on('end',()=>{ if(res.statusCode>=400) db.update('notifications',rec.id,{pushError:b.slice(0,150)}) });
      });
    req.on('error',()=>{});
    req.write(data); req.end();
  }
}
module.exports={push};
