const url=require('url');
class Router{
  constructor(){this.routes=[]}
  add(method,path,handler){ this.routes.push({method,path,handler}) }
  get(p,h){this.add('GET',p,h)}
  post(p,h){this.add('POST',p,h)}
  put(p,h){this.add('PUT',p,h)}
  del(p,h){this.add('DELETE',p,h)}
  match(req){
    const parsed=url.parse(req.url,true);
    const pathname=parsed.pathname;
    const method=req.method;
    for(const r of this.routes){
      const params=matchPath(r.path,pathname);
      if(params!==null && (r.method===method || r.method==='*')){
        return {handler:r.handler, params, query:parsed.query, pathname}
      }
    }
    return null;
  }
}
function matchPath(pattern,actual){
  if(pattern===actual) return {};
  const pp=pattern.split('/'), ap=actual.split('/');
  if(pp.length!==ap.length) return null;
  const p={};
  for(let i=0;i<pp.length;i++){
    if(pp[i].startsWith(':')) p[pp[i].slice(1)]=decodeURIComponent(ap[i]);
    else if(pp[i]!==ap[i]) return null;
  }
  return p;
}
module.exports=Router;
