const db=require('./database');
function log({userId,action,entity,entityId,meta,ip}){
  try{ db.insert('audit_log',{userId:userId||'system',action,entity,entityId,meta:meta||null,ip:ip||''})}catch(e){}
}
module.exports={log};
