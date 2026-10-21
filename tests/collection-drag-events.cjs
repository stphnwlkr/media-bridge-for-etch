/* Exercise the actual sidebar handlers without a browser's drag timing. */
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('assets/native-media.js','utf8');
const handler=source.slice(source.indexOf('\tfunction addDropTarget('),source.indexOf('\n\tfunction siblingCollections'));
async function scenario({drag={collection:4,ids:[11,12]},collection=7,mode,assign=true,reorder=false,saving=false,collectionDrag=0}={}){
 const events={},classes=new Set(),calls=[];
 const element={addEventListener:(type,fn)=>events[type]=fn,classList:{add:c=>classes.add(c),remove:c=>classes.delete(c)},contains:()=>false};
 const context={config:{canAssign:assign},draggedMedia:drag,draggedCollection:collectionDrag,state:{reorderMode:reorder},mediaOrderSaving:saving,assignMedia:async(...args)=>calls.push(args),finishMediaDrag:()=>{context.draggedMedia=null;}};
 vm.createContext(context);vm.runInContext(handler+'\nglobalThis.bind=addDropTarget;',context);context.bind(element,collection,mode);
 let prevented=false;const event={preventDefault:()=>prevented=true,stopPropagation(){},dataTransfer:{dropEffect:'none'}};
 events.dragover(event);const accepts=prevented;await events.drop(event);return {calls,accepts,classes};
}
(async()=>{
 let r=await scenario();assert.equal(r.accepts,true);assert.equal(JSON.stringify(r.calls),JSON.stringify([[[11,12],7,'move',4,true]]));assert.equal(r.classes.size,0);
 r=await scenario({drag:null});assert.equal(r.accepts,false);assert.equal(r.calls.length,0);
 for(const values of [{assign:false},{reorder:true},{saving:true},{collectionDrag:9}]){r=await scenario(values);assert.equal(r.accepts,false);assert.equal(r.calls.length,0);}
 r=await scenario({collection:4});assert.equal(r.calls.length,0);
 r=await scenario({collection:0,mode:'clear'});assert.equal(r.calls[0][2],'clear');
 r=await scenario({drag:{collection:0,ids:[11]}});assert.equal(r.calls[0][3],0);
 console.log('PASS: drag routing, move payload, selection capture, external drops, same target, permissions, reorder/saving guards, and Uncategorized');
})().catch(error=>{console.error(error);process.exit(1);});
