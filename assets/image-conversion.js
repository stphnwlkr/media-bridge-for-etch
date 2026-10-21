(()=>{
'use strict';
const config=window.uplinkMbeConversion;if(!config)return;
let current=null,job=null,results={},urls=[],worker=null,sequence=0,busy=false,reloadAfter=false,originalBlob=null,originalUrl=null,comparisonDialog=null,infoPopup=null,largeControls=null,previewFocus=null;
const root=document.getElementById('uplink-mbe-conversion-controls');if(!root)return;
root.classList.add('uplink-mbe-optimization-theme','wa-theme-default');
const text=config.strings;
let sizeScope='all',sizePanel=null,sizeChecks=[],sizeSummary=null,scopeGroup=null,saveProgress=null;
function button(content,cls=''){const node=el('wa-button',content,cls);node.setAttribute('size','s');node.setAttribute('variant',cls.includes('button-primary')?'brand':'neutral');node.setAttribute('appearance',cls.includes('button-primary')?'filled':'outlined');return node;}
const label=format=>({webp:'WebP',avif:'AVIF',jxl:'JPEG XL',png:'PNG',jpg:'JPG'}[format]||format);
const el=(tag,content,cls)=>{const node=document.createElement(tag);if(content)node.textContent=content;if(cls)node.className=cls;return node;};
const bytes=n=>new Intl.NumberFormat(undefined,{maximumFractionDigits:1}).format(n/1024)+' KB';
async function request(mode,data={},file=null){
 const form=new FormData();for(const [key,value]of Object.entries({action:'uplink_mbe_image_conversion',nonce:config.nonce,mode,attachment_id:current.id,...data})){if(Array.isArray(value))value.forEach(item=>form.append(key+'[]',item));else form.append(key,value);}
 if(file)form.append('file',file,'converted.'+data.format);
 const response=await fetch(config.ajaxUrl,{method:'POST',credentials:'same-origin',body:form});const body=await response.json();
 if(!response.ok||!body.success)throw new Error(body.data?.message||text.failed);return body.data;
}
function clean(){closeInfo();closeComparison();originalBlob=null;originalUrl=null;urls.forEach(URL.revokeObjectURL);urls=[];if(worker){worker.terminate();worker=null;}job=null;results={};}
function localUrl(blob){const url=URL.createObjectURL(blob);urls.push(url);return url;}
function encode(blob,format,quality,source,preview){
 if(!worker)worker=new Worker(config.worker,{type:'module'});
 const id=++sequence;
 return new Promise((resolve,reject)=>{
  const handler=event=>{if(event.data.id!==id)return;worker.removeEventListener('message',handler);worker.removeEventListener('error',error);event.data.error?reject(new Error(event.data.error)):resolve(event.data);};
  const error=()=>{worker.removeEventListener('message',handler);reject(new Error(text.encoderFailed));};
  worker.addEventListener('message',handler);worker.addEventListener('error',error,{once:true});worker.postMessage({id,blob,format,quality,preview,width:source.width,height:source.height});
 });
}
let notice,compare,restore,cards,intro,sourceSummary,overlay,overlayStatus,previousFocus;
const inertState=new Map();
const formatPanels=new Map();
function status(message){notice.hidden=!message;notice.textContent=message;if(overlayStatus)overlayStatus.textContent=message;}
function progressOverlay(value){
 const dialog=root.closest('.uplink-mbe-attachment-dialog');if(!dialog)return;
 if(!overlay){
  overlay=el('div','','uplink-mbe-conversion-overlay');overlay.hidden=true;overlay.tabIndex=-1;
  const panel=el('div','','uplink-mbe-conversion-overlay-panel');
  const spinner=el('span','','uplink-mbe-conversion-spinner');spinner.setAttribute('aria-hidden','true');
  overlayStatus=el('p','','uplink-mbe-conversion-overlay-status');overlayStatus.setAttribute('role','status');overlayStatus.setAttribute('aria-live','polite');overlayStatus.setAttribute('aria-atomic','true');
  panel.append(spinner,el('h3',text.processing),overlayStatus,el('p',text.keepOpen,'uplink-mbe-conversion-overlay-help'));overlay.append(panel);dialog.append(overlay);
  overlay.addEventListener('keydown',event=>{if(event.key==='Tab')event.preventDefault();});
 }
 overlay.hidden=!value;
 if(value){
  previousFocus=document.activeElement;overlayStatus.textContent=text.preparing;
  for(const child of dialog.children){if(child!==overlay){inertState.set(child,child.inert);child.inert=true;}}
  overlay.focus({preventScroll:true});
 }else{
  for(const [child,inert]of inertState)child.inert=inert;inertState.clear();
  const target=previousFocus?.isConnected&&!previousFocus.disabled?previousFocus:root.querySelector('button:not(:disabled),wa-button:not([disabled])');target?.focus({preventScroll:true});
 }
}
function syncControls(){
 root.querySelectorAll('button,input,wa-button,wa-radio-group,wa-checkbox,wa-slider').forEach(node=>{if(!node.classList.contains('uplink-mbe-comparison-range'))node.disabled=busy;});
 if(largeControls){largeControls.input.disabled=busy;largeControls.apply.disabled=busy||!results[largeControls.format]||!selectionValid();}
 for(const [format,panel]of formatPanels){
  panel.apply.disabled=busy||!results[format]||!selectionValid();
  panel.expand.disabled=!results[format];
 }
 for(const check of sizeChecks)check.disabled=busy||sizeScope!=='selected'||check.dataset.unavailable==='true';
}
function locked(value,preview=false){
 if(value&&preview)previewFocus=document.activeElement;
 busy=value;root.setAttribute('aria-busy',String(value));syncControls();if(!preview)progressOverlay(value);
 document.dispatchEvent(new CustomEvent('uplink-mbe-conversion-busy',{detail:value}));
 if(!value&&preview){const focus=previewFocus;previewFocus=null;if(document.activeElement===document.body&&focus?.isConnected&&!focus.disabled)focus.focus({preventScroll:true});}
}

async function action(fn,preview=false){if(busy)return;if(current?.saveBeforeConvert&&!await current.saveBeforeConvert()){status(text.failed);return;}locked(true,preview);try{await fn();}catch(error){status(error.message);}finally{locked(false,preview);if(reloadAfter){reloadAfter=false;window.location.reload();}}}

function render(media,force=false){
 if(busy&&!force)return;
 clean();sizeScope='all';sizePanel=null;sizeChecks=[];saveProgress=null;root.classList.remove('is-comparing');current=media;root.replaceChildren();root.hidden=!(media.canConvert||media.conversion);if(root.hidden)return;
 root.append(el('h4',text.title));
 intro=el('p',text.help,'uplink-mbe-conversion-intro');root.append(intro);
 applyContrastColors(root);
 notice=el('p','','uplink-mbe-conversion-status');notice.setAttribute('role','status');notice.setAttribute('aria-live','polite');notice.hidden=true;
 if(media.conversion){
  intro.hidden=true;
  root.append(el('p',text.saved.replace('%s',label(media.conversion.format))));
  const info=media.conversion;
  root.append(el('p',bytes(info.before)+' → '+bytes(info.after)+' · '+(100*(info.before-info.after)/info.before).toFixed(1)+'% '+text.savings.toLowerCase()+' · '+info.sizes+' '+text.files));
  root.append(el('p',text.quality+': '+(info.quality||text.automatic)+' · '+text.date+': '+info.date));
  if(info.rows?.length){
   const table=el('table'),head=el('thead'),tr=el('tr');[text.size,text.before,text.after,text.savings].forEach(t=>tr.append(el('th',t)));head.append(tr);table.append(head);const body=el('tbody');
   for(const row of info.rows){const line=el('tr');[row.size+' · '+row.width+'×'+row.height,bytes(row.before),bytes(row.after),(100*(row.before-row.after)/row.before).toFixed(1)+'%'].forEach(value=>line.append(el('td',value)));body.append(line);}table.append(body);root.append(table);
  }
  restore=button(text.restore,'button');restore.type='button';restore.addEventListener('click',()=>action(async()=>{
   status(text.restoring);await request('restore');status(text.restored);reloadAfter=true;
  }));if(media.canRestore)root.append(restore);root.append(notice);return;
 }
 const configured=Array.isArray(config.formats)?config.formats:['webp','avif'];
 const sourceFormat=media.mime==='image/jpeg'?'jpg':media.mime.split('/')[1];
 const formats=configured.filter(format=>format!==sourceFormat||media.canCompress);
 if(!formats.length){
  root.append(el('p',configured.length?text.currentOptimized:text.noFormats));
  const settings=el('a',text.compressionSettings);settings.href=config.settingsUrl;root.append(settings);return;
 }
 compare=button(text.compare,'button button-primary');compare.type='button';compare.addEventListener('click',()=>action(()=>compareFormats(formats),true));
 root.append(compare);
 sourceSummary=el('p','','uplink-mbe-conversion-source');sourceSummary.hidden=true;root.append(sourceSummary,notice);
 cards=el('div','','uplink-mbe-conversion-cards');cards.hidden=true;cards.style.setProperty('--conversion-columns',String(Math.min(3,formats.length)));root.append(cards);
 formatPanels.clear();
}

function luminance(hex){
 const channels=hex.replace('#','').match(/.{2}/g).map(value=>{const n=parseInt(value,16)/255;return n<=0.04045?n/12.92:((n+0.055)/1.055)**2.4;});
 return channels[0]*0.2126+channels[1]*0.7152+channels[2]*0.0722;
}
function contrast(a,b){const first=luminance(a),second=luminance(b);return(Math.max(first,second)+0.05)/(Math.min(first,second)+0.05);}
function applyContrastColors(target){
 const style=getComputedStyle(root);
 const token=(name,fallback)=>{const color=style.getPropertyValue('--mbe-'+name).trim();return /^#[a-f0-9]{6}$/i.test(color)?color:fallback;};
 const surface=token('card','#ffffff');
 const bestText=background=>{const candidate=contrast('#1d2327',background)>contrast('#ffffff',background)?'#1d2327':'#ffffff';return contrast(candidate,background)>=4.5?candidate:'#000000';};
 const readable=(color,minimum=4.5)=>contrast(color,surface)>=minimum?color:bestText(surface);
 const foreground=readable(token('text','#1d2327')),muted=readable(token('muted','#50575e'));
 const accent=readable(token('accent','#2271b1'),3),onAccent=bestText(accent);
 for(const [key,value]of Object.entries({surface,text:foreground,muted,accent,'on-accent':onAccent,border:muted}))target.style.setProperty('--conversion-'+key,value);
 target.classList.toggle('wa-dark',luminance(surface)<0.2);target.classList.toggle('wa-light',luminance(surface)>=0.2);
}
const paletteSource=root.closest('.uplink-mbe-native-wrap');
if(paletteSource)new MutationObserver(()=>{applyContrastColors(root);if(comparisonDialog)applyContrastColors(comparisonDialog);if(infoPopup)applyContrastColors(infoPopup.popup);}).observe(paletteSource,{attributes:true,attributeFilter:['style','class']});

function releaseUrl(url){if(!url)return;URL.revokeObjectURL(url);urls=urls.filter(item=>item!==url);}
function closeComparison(){largeControls=null;if(comparisonDialog){comparisonDialog.close();comparisonDialog.remove();comparisonDialog=null;}}
function comparison(original,compressed,format,source,split=50){
 const stage=el('div','','uplink-mbe-image-comparison');if(current.mime==='image/jpeg')stage.classList.add('is-opaque');stage.style.aspectRatio=source.width+'/'+source.height;stage.style.setProperty('--mbe-comparison-ratio',String(source.width/source.height));
 const after=el('img');after.src=compressed;after.alt=label(format)+' '+text.compressed;after.draggable=false;
 const before=el('img','','uplink-mbe-comparison-original');before.src=original;before.alt=text.original;before.draggable=false;
 const divider=el('span','↔','uplink-mbe-comparison-divider');divider.setAttribute('aria-hidden','true');
 const range=el('input');range.type='range';range.min='0';range.max='100';range.value=String(split);range.className='uplink-mbe-comparison-range';
 range.setAttribute('aria-label',text.compareImages.replace('%s',label(format)));
 function move(){const value=Number(range.value);before.style.clipPath='inset(0 '+(100-value)+'% 0 0)';divider.style.left=value+'%';range.setAttribute('aria-valuetext',value+'% '+text.original+', '+(100-value)+'% '+text.compressed);}
 range.addEventListener('input',move);move();
 stage.append(after,before,divider,el('span',text.original,'uplink-mbe-comparison-label is-original'),el('span',text.compressed,'uplink-mbe-comparison-label is-compressed'),range);
 return stage;
}
function showStatistics(target,result){
 const saving=100*(job.files.full.bytes-result.total)/job.files.full.bytes;
 target.replaceChildren(el('strong',bytes(result.total)),el('span',(saving>=0?text.smallerResult:text.largerResult).replace('%s',Math.abs(saving).toFixed(1)).replace('%%','%'),'uplink-mbe-savings'));
}
function updateLargeComparison(format){
 if(!largeControls||largeControls.format!==format||!results[format])return;
 const result=results[format],split=largeControls.preview.querySelector('input')?.value||50;
 largeControls.preview.replaceChildren(comparison(originalUrl,result.previewUrl,format,job.files.full,split));
 largeControls.input.value=Number(result.quality);largeControls.output.value=String(result.quality);showStatistics(largeControls.stats,result);
}
function openComparison(format,trigger){
 const result=results[format];if(!result)return;
 closeInfo();closeComparison();
 const dialog=el('dialog','','uplink-mbe-comparison-dialog uplink-mbe-optimization-theme wa-theme-default');comparisonDialog=dialog;
 const header=el('header'),title=el('h2',label(format)+' · '+text.compareTitle),close=el('button',text.close,'button');close.type='button';
 const titleId='uplink-mbe-comparison-title';title.id=titleId;dialog.setAttribute('aria-labelledby',titleId);
 close.addEventListener('click',()=>dialog.close());header.append(title,close);
 const preview=el('div','','uplink-mbe-large-preview'),controls=el('div','','uplink-mbe-large-controls'),stats=el('p','','uplink-mbe-format-stats');stats.setAttribute('role','status');
 const field=el('label','','uplink-mbe-format-quality'),caption=el('span',text.quality),output=el('output',String(result.quality)),input=el('wa-slider');
 input.min=1;input.max=100;input.step=1;input.value=Number(result.quality);input.label=label(format)+' '+text.quality;input.classList.add('wa-visually-hidden-label');
 field.append(caption,output,input);
 const apply=button(text.applyCompression,'button button-primary');apply.type='button';
 input.addEventListener('input',()=>{
  const panel=formatPanels.get(format);panel.input.value=input.value;panel.output.value=input.value;output.value=input.value;invalidate(format);apply.disabled=true;stats.textContent=text.releaseQuality;
 });
 input.addEventListener('change',()=>action(()=>compareFormats([format]),true));
 apply.addEventListener('click',()=>{if(busy||!results[format])return;closeComparison();action(()=>save(format));});
 controls.append(stats,field,apply);dialog.append(header,preview,controls,el('p',text.compareHelp));
 largeControls={format,preview,input,output,stats,apply};updateLargeComparison(format);syncControls();
 dialog.addEventListener('keydown',event=>event.stopPropagation());
 dialog.addEventListener('close',()=>{dialog.remove();if(comparisonDialog===dialog){comparisonDialog=null;largeControls=null;}if(trigger.isConnected)trigger.focus({preventScroll:true});});
 document.body.append(dialog);applyContrastColors(dialog);dialog.showModal();
}

function invalidate(format){
 const panel=formatPanels.get(format);
 delete results[format];panel.apply.disabled=true;panel.expand.disabled=true;panel.stats.textContent=text.releaseQuality;
}
function closeInfo(restoreFocus=false){
 if(!infoPopup)return;
 const {popup,button}=infoPopup;popup.remove();button.setAttribute('aria-expanded','false');button.removeAttribute('aria-describedby');infoPopup=null;
 if(restoreFocus&&button.isConnected)button.focus({preventScroll:true});
}
function createJxlInfo(){
 const button=el('button','i','uplink-mbe-format-info');button.type='button';button.setAttribute('aria-label',text.jxlInfo);button.setAttribute('aria-expanded','false');button.setAttribute('aria-controls','uplink-mbe-jxl-info-popup');
 button.addEventListener('click',()=>{
  if(infoPopup?.button===button){closeInfo();return;}
  closeInfo();
  const popup=el('div','','uplink-mbe-format-info-popup');popup.id='uplink-mbe-jxl-info-popup';popup.setAttribute('role','note');
  const header=el('div','','uplink-mbe-format-info-header'),close=el('button','×','uplink-mbe-info-close');close.type='button';close.setAttribute('aria-label',text.closeInfo);close.addEventListener('click',()=>closeInfo(true));
  header.append(el('h4',text.jxlSummary),close);popup.append(header,el('p',text.jxlHelp));(root.closest('.uplink-mbe-attachment-dialog')||document.body).append(popup);applyContrastColors(popup);
  const rect=button.getBoundingClientRect(),width=Math.min(360,window.innerWidth-32);popup.style.width=width+'px';
  popup.style.left=Math.max(16,Math.min(rect.right-width,window.innerWidth-width-16))+'px';
  const height=popup.getBoundingClientRect().height;popup.style.top=Math.max(16,Math.min(rect.bottom+8,window.innerHeight-height-16))+'px';
  button.setAttribute('aria-expanded','true');button.setAttribute('aria-describedby',popup.id);infoPopup={popup,button};
 });return button;
}
document.addEventListener('click',event=>{if(infoPopup&&!infoPopup.popup.contains(event.target)&&!infoPopup.button.contains(event.target))closeInfo();});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&infoPopup){event.preventDefault();event.stopPropagation();closeInfo(true);}},true);
window.addEventListener('resize',()=>closeInfo());
document.addEventListener('scroll',event=>{if(infoPopup&&!infoPopup.popup.contains(event.target))closeInfo();},true);
function createFormatCard(format,value){
 const card=el('wa-card','','uplink-mbe-conversion-card');card.dataset.format=format;
 const header=el('div','','uplink-mbe-format-heading'),heading=el('h4',label(format));header.slot='header';header.append(heading);const badge=el('wa-badge',text.previewBadge);badge.setAttribute('appearance','outlined');badge.setAttribute('variant','neutral');header.append(badge);
 if(format==='jxl')header.append(createJxlInfo());card.append(header);
 const preview=el('div','','uplink-mbe-format-preview');
 const original=el('img');original.src=current.url;original.alt=text.original;original.className='uplink-mbe-preview-placeholder';original.style.aspectRatio=job.files.full.width+'/'+job.files.full.height;preview.append(original);
 const stats=el('p',text.preparingPreview,'uplink-mbe-format-stats');stats.setAttribute('role','status');
 const field=el('label','','uplink-mbe-format-quality'),caption=el('span',text.quality),output=el('output',String(value)),input=el('wa-slider');
 field.hidden=true;input.min=1;input.max=100;input.step=1;input.value=Number(value);input.dataset.format=format;input.label=label(format)+' '+text.quality;input.classList.add('wa-visually-hidden-label');
 field.append(caption,output,input);
 const hints=el('span','','uplink-mbe-quality-hints');hints.append(el('span',text.smallerFile),el('span',text.moreDetail));field.append(hints);
 const refresh=button(text.retryPreview,'button'),expand=button(text.inspectShort.replace('%s',label(format)),'button uplink-mbe-inspect'),apply=button(text.useFormat.replace('%s',label(format)),'button button-primary');
 for(const button of [refresh,expand,apply])button.type='button';refresh.hidden=true;expand.hidden=true;apply.hidden=true;
 expand.setAttribute('aria-label',text.inspectFormat.replace('%s',label(format)));
 input.addEventListener('input',()=>{output.value=input.value;invalidate(format);});
 input.addEventListener('change',()=>action(()=>compareFormats([format]),true));
 refresh.addEventListener('click',()=>action(()=>compareFormats([format]),true));expand.addEventListener('click',()=>openComparison(format,expand));apply.addEventListener('click',()=>action(()=>save(format)));
 const actions=el('div','','uplink-mbe-format-actions');actions.slot='footer';actions.append(expand,apply,refresh);
 card.append(preview,stats,field,actions);

 cards.append(card);formatPanels.set(format,{card,input,output,field,preview,stats,refresh,expand,apply,previewUrl:null});
}

async function prepareSource(){
 if(job&&originalBlob)return;
 status(text.preparing);job=await request('begin');if(job.restore)throw new Error(text.restoreFirst);
 const response=await fetch(job.files.full.url,{credentials:'same-origin',cache:'no-store'});if(!response.ok)throw new Error(text.fetchFailed);
 originalBlob=await response.blob();originalUrl=localUrl(originalBlob);
}
async function compareFormats(formats){
 await prepareSource();closeInfo();root.classList.add('is-comparing');intro.hidden=true;compare.hidden=true;cards.hidden=false;
 sourceSummary.hidden=false;sourceSummary.textContent=text.mainPreview+' · '+job.files.full.width+' × '+job.files.full.height+' · '+bytes(job.files.full.bytes);
 let done=0;
 for(const format of formats){
  if(!formatPanels.has(format))createFormatCard(format,{webp:80,avif:50,jxl:75,jpg:80,png:80}[format]||80);
  const panel=formatPanels.get(format);
  invalidate(format);panel.card.classList.add('is-processing');panel.stats.textContent=text.preparingPreview;if(largeControls?.format===format)largeControls.stats.textContent=text.preparingPreview;panel.refresh.hidden=true;syncControls();
  const result={files:{},total:0,quality:format==='png'?0:Number(panel.input.value)};
  try{
   status(text.previewProgress.replace('%1$d',++done).replace('%2$d',formats.length));
   const encoded=await encode(originalBlob,format,result.quality,job.files.full,true);
   let blob=encoded.blob;if(blob.type===originalBlob.type&&blob.size>=originalBlob.size)blob=originalBlob;
   result.files.full=blob;result.total=blob.size;
   // Compare the exact pixels that will be saved, including the no-growth fallback.
   releaseUrl(panel.previewUrl);
   result.previewUrl=localUrl(blob===originalBlob?originalBlob:(encoded.preview||blob));results[format]=result;
   panel.previewUrl=result.previewUrl;panel.preview.replaceChildren(comparison(originalUrl,result.previewUrl,format,job.files.full));
   showStatistics(panel.stats,result);updateLargeComparison(format);
   panel.field.hidden=format==='png';panel.expand.hidden=false;panel.apply.hidden=false;

  }catch(error){delete results[format];panel.stats.textContent=error.message;panel.refresh.hidden=false;panel.field.hidden=true;if(largeControls?.format===format)largeControls.stats.textContent=error.message;}
  finally{panel.card.classList.remove('is-processing');syncControls();if(worker){worker.terminate();worker=null;}}
 }
 if(!sizePanel)createSizePanel();
 status(Object.keys(results).length?'':text.failed);
}


function selectionValid(){const keys=selectedSizeKeys();return !!keys.length&&keys.every(key=>job.files[key].available!==false);}
function selectedSizeKeys(){
 if(!job)return [];
 if(sizeScope==='all')return Object.keys(job.files).filter(key=>!job.files[key].excluded);
 if(sizeScope==='main')return ['full'];
 return sizeChecks.filter(control=>control.checked).map(control=>control.value);
}
function createSizePanel(){
 sizePanel=el('section','','uplink-mbe-size-panel wa-stack wa-gap-m');
 const heading=el('div','','wa-split');heading.append(el('h4',text.sizesTitle));
 sizeSummary=el('wa-badge','','uplink-mbe-size-summary');sizeSummary.setAttribute('appearance','outlined');sizeSummary.setAttribute('variant','neutral');heading.append(sizeSummary);sizePanel.append(heading);
 scopeGroup=el('wa-radio-group');scopeGroup.setAttribute('label',text.scopeLabel);scopeGroup.setAttribute('orientation','horizontal');scopeGroup.value='all';scopeGroup.setAttribute('size','s');
 for(const [value,label]of [['all',text.allSizes],['main',text.mainOnly],['selected',text.chooseSizes]]){const choice=el('wa-radio',label);choice.value=value;choice.setAttribute('appearance','button');scopeGroup.append(choice);}
 sizePanel.append(scopeGroup,el('p',text.sizeHint,'uplink-mbe-size-hint'));
 if(Object.values(job.files).some(file=>file.excluded))sizePanel.append(el('p',text.exclusionsHint,'uplink-mbe-exclusions-hint'));
 if(Object.values(job.files).some(file=>file.available===false))sizePanel.append(el('p',text.tooLarge,'uplink-mbe-size-hint'));
 const list=el('div','','uplink-mbe-size-list wa-grid wa-gap-m');list.setAttribute('role','group');list.setAttribute('aria-label',text.sizeInventory);
 for(const [key,file]of Object.entries(job.files)){
  const aliases=Object.entries(job.aliases||{}).filter(([,canonical])=>canonical===key).map(([name])=>name);
  const label=key==='full'?text.mainOnly:key==='original_image'?text.originalUpload:key;
  const check=el('wa-checkbox',label+(aliases.length?' / '+aliases.join(', '):''));check.value=key;check.checked=file.available!==false&&!file.excluded;check.dataset.unavailable=String(file.available===false);
  check.setAttribute('hint',file.width+' × '+file.height+' · '+bytes(file.bytes)+(file.available===false?' · '+text.tooLarge:'')+(file.excluded?' · '+text.excludedSize:''));
  check.addEventListener('change',updateSizeSelection);sizeChecks.push(check);list.append(check);
 }
 sizePanel.append(list);
 saveProgress=el('wa-progress-bar');saveProgress.setAttribute('label',text.processing);saveProgress.hidden=true;sizePanel.append(saveProgress);
 scopeGroup.addEventListener('change',()=>{sizeScope=scopeGroup.value;updateSizeSelection();});
 root.insertBefore(sizePanel,cards);updateSizeSelection();
}
function updateSizeSelection(){
 const keys=selectedSizeKeys(),total=keys.reduce((sum,key)=>sum+job.files[key].bytes,0);
 sizeSummary.textContent=keys.length+' / '+Object.keys(job.files).length+' '+text.selectedFiles+' · '+bytes(total);
 for(const check of sizeChecks){check.disabled=busy||sizeScope!=='selected'||check.dataset.unavailable==='true';check.hidden=sizeScope!=='selected';}
 sizePanel.querySelector('.uplink-mbe-size-list').hidden=sizeScope!=='selected';
 sizePanel.querySelectorAll('.uplink-mbe-size-hint').forEach(note=>note.hidden=sizeScope!=='selected'&&Object.values(job.files).every(file=>file.available!==false));
 syncControls();for(const check of sizeChecks)check.disabled=busy||sizeScope!=='selected'||check.dataset.unavailable==='true';
}

async function save(format){
 const result=results[format];if(!job||!result)throw new Error(text.compareFirst);
 const keys=selectedSizeKeys();if(!selectionValid())throw new Error(text.selectionEmpty);
 const selection=await request('select',{token:job.token,scope:sizeScope,sizes:keys});
 await request('validate',{token:job.token});
 const entries=Object.entries(job.files).filter(([key])=>selection.sizes.includes(key));
 if(saveProgress){saveProgress.hidden=false;saveProgress.value=0;}
 try{
  for(let i=0;i<entries.length;i++){
   const [size,source]=entries[i];let file=result.files[size];
   if(!file){
    status(text.progress.replace('%1$d',i+1).replace('%2$d',entries.length));
    const response=await fetch(source.url,{credentials:'same-origin',cache:'no-store'});if(!response.ok)throw new Error(text.fetchFailed);
    const original=await response.blob(),encoded=await encode(original,format,result.quality,source,false);file=encoded.blob;
    if(file.type===original.type&&file.size>=original.size)file=original;
   }
   status(text.saving.replace('%1$d',i+1).replace('%2$d',entries.length));
   await request('stage',{token:job.token,size,format,quality:result.quality},file);
   if(saveProgress)saveProgress.value=Math.round(100*(i+1)/entries.length);
  }
  status(text.updating);const saved=await request('commit',{token:job.token});
  render({...current,conversion:saved.summary},true);document.dispatchEvent(new CustomEvent('uplink-mbe-conversion-complete',{detail:{id:current.id}}));
  status(text.complete.replace('%1$d',saved.sizes).replace('%2$d',saved.references));
 }catch(error){try{await request('cancel',{token:job.token});}catch(_){}render(current,true);throw error;}finally{if(worker){worker.terminate();worker=null;}}
}
document.addEventListener('uplink-mbe-attachment-shown',event=>render(event.detail));
window.addEventListener('beforeunload',event=>{if(busy){event.preventDefault();event.returnValue='';}});
})();
