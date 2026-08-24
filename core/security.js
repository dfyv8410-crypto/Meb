const crypto=require('crypto');
function hashPassword(pwd,salt){
  salt=salt||crypto.randomBytes(16).toString('hex');
  const hash=crypto.pbkdf2Sync(pwd,salt,100000,32,'sha256').toString('hex');
  return {salt,hash}
}
function verifyPassword(pwd,salt,hash){
  const h=crypto.pbkdf2Sync(pwd,salt,100000,32,'sha256').toString('hex');
  return h===hash;
}
const b64u=s=>Buffer.from(s).toString('base64').replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
const b64uDec=s=>Buffer.from(s.replace(/-/g,'+').replace(/_/g,'/'),'base64').toString();
function sign(payload,secret){
  const b=b64u(JSON.stringify(payload));
  const sig=crypto.createHmac('sha256',secret).update(b).digest('base64').replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
  return b+'.'+sig;
}
function verify(token,secret){
  if(!token) return null;
  const [b,sig]=token.split('.');
  if(!b||!sig) return null;
  const exp=crypto.createHmac('sha256',secret).update(b).digest('base64').replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
  if(exp!==sig) return null;
  try{ return JSON.parse(b64uDec(b))}catch(e){return null}
}
function csrf(){ return crypto.randomBytes(24).toString('hex')}
function sanitize(s){
  if(typeof s!=='string') return s;
  return s.replace(/[<>"'`]/g,c=>({ '<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#96;' }[c]));
}
const rlMap=new Map();
function rateLimit(ip,limit=60,windowMs=60000,bucket='global'){
  const now=Date.now(); const key=bucket+':'+ip;
  let rec=rlMap.get(key); if(!rec||now-rec.start>windowMs) rec={count:0,start:now};
  rec.count++; rlMap.set(key,rec); return rec.count<=limit;
}
function headers(){
  return {
    'X-Frame-Options':'DENY',
    'X-Content-Type-Options':'nosniff',
    'Referrer-Policy':'strict-origin-when-cross-origin',
    'Permissions-Policy':'camera=(), microphone=()',
    'Content-Security-Policy':"default-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com https://unpkg.com https://cdn.jsdelivr.net; img-src 'self' data: https: blob:; connect-src 'self'"
  };
}
module.exports={hashPassword,verifyPassword,sign,verify,csrf,sanitize,rateLimit,headers};
