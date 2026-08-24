const db=require('./database'), sec=require('./security');
function getUserFromReq(req,secret){
  let token=null;
  const h=req.headers['authorization'];
  if(h&&h.startsWith('Bearer ')) token=h.slice(7);
  if(!token && req.headers.cookie){
    const m=req.headers.cookie.match(/token=([^;]+)/);
    if(m) token=decodeURIComponent(m[1]);
  }
  if(!token) return null;
  const p=sec.verify(token,secret); if(!p) return null;
  return db.byId('users',p.id)||null;
}
function requireRole(user,roles){
  if(!user) return false;
  return roles.includes(user.role);
}
const ROLE_HIERARCHY={super_admin:4,admin:3,manager:2,editor:1};
function can(user,need){
  if(!user) return false;
  return (ROLE_HIERARCHY[user.role]||0) >= (ROLE_HIERARCHY[need]||99);
}
module.exports={getUserFromReq,requireRole,can};
