function reqFields(body,fields){
  const miss=fields.filter(f=>!body[f]||String(body[f]).trim()==='');
  if(miss.length) return 'Missing: '+miss.join(', ');
  return null;
}
function isEmail(s){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s) }
function slugify(s){ return String(s).toLowerCase().trim().replace(/[^a-z0-9\u0400-\u04FF]+/g,'-').replace(/^-|-$/g,'') }
module.exports={reqFields,isEmail,slugify};
