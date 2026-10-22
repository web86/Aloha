/* Native installed Firefox, isolated temporary profile, built-in Node WebSocket; no driver install. */
const fs=require('node:fs'),{spawn}=require('node:child_process');
async function firefox(){
 const profile=fs.mkdtempSync('/tmp/aloha-firefox-');
 const child=spawn(process.env.FIREFOX_BINARY || '/Applications/Firefox.app/Contents/MacOS/firefox',['--new-instance','--headless','--profile',profile,'--remote-debugging-port','0'],{stdio:['ignore','pipe','pipe']});
 const url=await new Promise((resolve,reject)=>{let data='';const timer=setTimeout(()=>reject(Error('Firefox startup timeout: '+data)),15000);child.stderr.on('data',b=>{data+=b;const m=data.match(/WebDriver BiDi listening on (ws:\/\/[^\s]+)/);if(m){clearTimeout(timer);resolve(m[1]+'/session')}});child.on('exit',code=>{clearTimeout(timer);reject(Error('Firefox exit '+code+': '+data))})});
 const ws=new WebSocket(url),pending=new Map();let id=0;
 await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true})});
 ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);if(p){clearTimeout(p.timer);pending.delete(m.id);m.type==='error'?p.reject(Error(JSON.stringify(m))):p.resolve(m.result)}}});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;const timer=setTimeout(()=>{pending.delete(n);reject(Error(method+' timeout'))},20000);pending.set(n,{resolve,reject,timer});ws.send(JSON.stringify({id:n,method,params}))});
 const session=await send('session.new',{capabilities:{}});const {context}=await send('browsingContext.create',{type:'tab'});
 return {session,context,send,eval:async expr=>{const r=await send('script.evaluate',{expression:`(async()=>JSON.stringify(${expr}))()`,target:{context},awaitPromise:true});if(r.type==='exception')throw Error(JSON.stringify(r));return r.result.type === 'undefined' ? null : JSON.parse(r.result.value)},viewport:(width,height=1000)=>send('browsingContext.setViewport',{context,viewport:{width,height},devicePixelRatio:1}),goto:url=>send('browsingContext.navigate',{context,url,wait:'complete'}),move:(x,y,duration=0)=>send('input.performActions',{context,actions:[{type:'pointer',id:'mouse',parameters:{pointerType:'mouse'},actions:[{type:'pointerMove',x:Math.round(x),y:Math.round(y),duration,origin:'viewport'}]}]}),shot:async path=>{const r=await send('browsingContext.captureScreenshot',{context,origin:'viewport'});fs.writeFileSync(path,Buffer.from(r.data,'base64'))},close:async()=>{try{await send('session.end')}catch{}ws.close();if(child.exitCode===null){const exited=new Promise(resolve=>child.once('exit',resolve));child.kill();await exited;}fs.rmSync(profile,{recursive:true,force:true});}};
}
module.exports={firefox};
