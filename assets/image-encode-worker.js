// Local single-thread jSquash codecs keep work off the UI and need no special server headers.
const modules = new Map();
async function codec(format) {
 if(!modules.has(format)) modules.set(format,(async()=>{
  const [{default:factory},{defaultOptions}] = await Promise.all([import(`./vendor/jsquash/${format}/codec/enc/${format}_enc.js`),import(`./vendor/jsquash/${format}/meta.js`)]);
  return {module:await factory({noInitialRun:true}),defaultOptions};
 })());
 return modules.get(format);
}
function animated(buffer,mime) {
 const view=new DataView(buffer);
 if(mime==='image/png') for(let p=8;p+12<=view.byteLength;){const type=view.getUint32(p+4);if(type===0x6163544c)return true;if(type===0x49444154)break;p+=12+view.getUint32(p);}
 if(mime==='image/webp') for(let p=12;p+8<=view.byteLength;){if(view.getUint32(p)===0x414e494d)return true;const n=view.getUint32(p+4,true);p+=8+n+(n%2);}
 return mime==='image/avif'&&new TextDecoder('latin1').decode(buffer.slice(0,64)).includes('avis');
}
self.onmessage=async({data})=>{
 const {id,blob,format,quality,preview,width,height}=data;
 try{
  if(animated(await blob.arrayBuffer(),blob.type))throw new Error('Animated images are left unchanged to preserve their frames.');
  const image=await createImageBitmap(blob,{imageOrientation:'none'});
  if(image.width!==width||image.height!==height){image.close();throw new Error('Decoded image dimensions differ from the original.');}
  const canvas=new OffscreenCanvas(width,height),ctx=canvas.getContext('2d');ctx.drawImage(image,0,0);image.close();
  const pixels=ctx.getImageData(0,0,width,height);
  let output;
  if(['webp','avif','jxl'].includes(format)){
   const {module,defaultOptions}=await codec(format);
   const encoded=module.encode(new Uint8Array(pixels.data.buffer),width,height,{...defaultOptions,quality});
   if(!encoded)throw new Error('The image encoder did not produce a file.');
   output=new Blob([encoded.slice()],{type:`image/${format}`});
  }else if(format==='png'){
   await import('./vendor/browser-image-compression/browser-image-compression.js');
   output=await self.imageCompression(new File([blob],'source.png',{type:'image/png'}),{useWebWorker:false,alwaysKeepResolution:true,maxIteration:5});
  }else output=await canvas.convertToBlob({type:'image/jpeg',quality:quality/100});
  let visual=output;
  if(preview&&format==='jxl'){
   const {default:factory}=await import('./vendor/jsquash/jxl/codec/dec/jxl_dec.js');
   const dec=await factory({noInitialRun:true});const decoded=dec.decode(new Uint8Array(await output.arrayBuffer()));
   if(!decoded||decoded.width!==width||decoded.height!==height)throw new Error('JPEG XL preview validation failed.');
   ctx.putImageData(new ImageData(new Uint8ClampedArray(decoded.data),width,height),0,0);
   visual=await canvas.convertToBlob({type:'image/png'});
  }
  canvas.width=0;canvas.height=0;
  self.postMessage({id,blob:output,preview:preview?visual:null});
 }catch(error){
  const raw=String(error.message||error);console.warn('Media Bridge '+format+' encoder:',raw);
  const friendly=/Aborted|WebAssembly|unreachable|memory access|out of memory/i.test(raw)
   ? 'The '+format.toUpperCase()+' encoder stopped unexpectedly. Try again or choose another format. Original files have not changed.' : raw;
  self.postMessage({id,error:friendly});
 }
};
