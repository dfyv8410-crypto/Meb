// Извлекает размеры PNG/JPEG/GIF/WebP без внешних зависимостей
function dimensions(buf){
  if(!buf||buf.length<24) return null;
  // PNG
  if(buf[0]===0x89&&buf[1]===0x50){
    return {width:buf.readUInt32BE(16),height:buf.readUInt32BE(20)};
  }
  // GIF
  if(buf.slice(0,3).toString()==='GIF'){
    return {width:buf.readUInt16LE(6),height:buf.readUInt16LE(8)};
  }
  // JPEG
  if(buf[0]===0xFF&&buf[1]===0xD8){
    let i=2;
    while(i<buf.length-9){
      if(buf[i]!==0xFF){ i++; continue }
      const marker=buf[i+1];
      if(marker>=0xC0&&marker<=0xCF&&![0xC4,0xC8,0xCC].includes(marker)){
        return {height:buf.readUInt16BE(i+5),width:buf.readUInt16BE(i+7)};
      }
      i+=2+buf.readUInt16BE(i+2);
    }
  }
  // WebP VP8X
  if(buf.slice(0,4).toString()==='RIFF'&&buf.slice(8,12).toString()==='WEBP'&&buf.slice(12,16).toString()==='VP8X'){
    return {width:1+(buf[24]|(buf[25]<<8)|(buf[26]<<16)),height:1+(buf[27]|(buf[28]<<8)|(buf[29]<<16))};
  }
  return null;
}
module.exports={dimensions};
