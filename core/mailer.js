const net=require('net');
const db=require('./database');
// Minimal SMTP client (zero-dep). Работает с любым SMTP без TLS или STARTTLS-хостом на 25 порту.
function sendEmail({to,subject,text},cb){
  const s=db.all('settings')[0]||{};
  const smtp=s.smtp||{};
  if(!smtp.host) return cb&&cb(new Error('SMTP не настроен (Настройки → Уведомления)'));
  const socket=net.createConnection(+smtp.port||25,smtp.host);
  let step=0, buf='';
  const from=smtp.from||'meb@localhost';
  const cmds=[
    'HELO '+(smtp.helo||'localhost'),
    smtp.user?'AUTH LOGIN':null,
    smtp.user?Buffer.from(smtp.user).toString('base64'):null,
    smtp.user?Buffer.from(smtp.pass||'').toString('base64'):null,
    'MAIL FROM:<'+from+'>',
    'RCPT TO:<'+to+'>',
    'DATA',
    ['From: MEB <'+from+'>','To: <'+to+'>','Subject: =?UTF-8?B?'+Buffer.from(subject).toString('base64')+'?=','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','',''+text,'.'].join('\r\n'),
    'QUIT'
  ].filter(Boolean);
  socket.on('connect',()=>{});
  socket.on('data',d=>{
    buf+=d.toString();
    if(!/\r?\n$/.test(buf)) return;
    const code=parseInt(buf.slice(0,3),10);
    buf='';
    if(code>=400){ socket.destroy(); return cb&&cb(new Error('SMTP error '+code+' при шаге '+step)) }
    if(step<cmds.length){ socket.write(cmds[step++]+'\r\n') } else { socket.end(); cb&&cb(null); cb=null }
  });
  socket.on('error',e=>{ cb&&cb(e); cb=null });
}
module.exports={sendEmail};
