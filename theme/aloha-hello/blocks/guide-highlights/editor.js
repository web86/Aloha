/* Post IDs only. Published Guide eligibility and preview data come from the read-only endpoint. */
(() => {
 const {createElement:el,useEffect,useState}=wp.element;
 const {Button,Tooltip,SearchControl,Spinner,Notice}=wp.components;
 const keys=['featuredIds','additionalIds'],limits={featuredIds:2,additionalIds:6},labels={featuredIds:'Featured Articles',additionalIds:'Additional Articles'};
 const path=params=>'/aloha/v1/guide-highlights?'+params;
 function Search({selected,onAdd,label,full}) {
  const [term,setTerm]=useState(''),[result,setResult]=useState(null),[busy,setBusy]=useState(false),[error,setError]=useState(false);
  useEffect(()=>{
   if(!term.trim()){setResult(null);setBusy(false);return;}
   let active=true;setBusy(true);const timer=setTimeout(()=>wp.apiFetch({path:path('search='+encodeURIComponent(term))}).then(data=>{if(active){setResult(data);setError(false);setBusy(false);}}).catch(()=>{if(active){setError(true);setBusy(false);}}),200);
   return()=>{active=false;clearTimeout(timer);};
  },[term]);
  const items=(result?.items||[]).filter(item=>!selected.includes(item.id));
  if(full)return el('p',{},'Group is full. Remove or move an article to add another.');
  return el('div',{},el(SearchControl,{label:'Find article for '+label,value:term,onChange:setTerm,disabled:full,placeholder:full?'Group is full':'Search published Guide titles…'}),
   busy&&el(Spinner),error&&el(Notice,{status:'error',isDismissible:false},'Guide search is unavailable. Try again.'),
   result&&!result.configured&&el(Notice,{status:'warning',isDismissible:false},'Check the Big Island Guide category configuration in Aloha Content.'),
   result&&!busy&&el('div',{className:'aloha-guide-editor-results','aria-label':label+' search results'},items.length?items.map(item=>el(Button,{key:item.id,onClick:()=>{onAdd(item.id);setTerm('');},'aria-label':'Add '+item.title+' to '+label},item.thumbnail&&el('img',{src:item.thumbnail,alt:''}),el('span',{},item.title))):el('p',{},'No matching published Guide articles. Refine the title search.'))
  );
 }
 wp.blocks.registerBlockType('aloha/guide-highlights',{
  apiVersion:3,title:'Guide Highlights',category:'widgets',icon:'admin-post',attributes:{featuredIds:{type:'array',default:[]},additionalIds:{type:'array',default:[]}},supports:{html:false,anchor:true},
  edit({attributes,setAttributes}){
   const groups=Object.fromEntries(keys.map(k=>[k,attributes[k]||[]])),ids=[...groups.featuredIds,...groups.additionalIds];
   const [records,setRecords]=useState({}),[loading,setLoading]=useState(false),[error,setError]=useState(false),[refresh,setRefresh]=useState(0);
   useEffect(()=>{const update=()=>setRefresh(n=>n+1);window.addEventListener('focus',update);return()=>window.removeEventListener('focus',update);},[]);
   useEffect(()=>{
    if(!ids.length){setRecords({});setLoading(false);return;}
    let active=true;setLoading(true);
    wp.apiFetch({path:path(ids.map(id=>'include[]='+id).join('&'))}).then(data=>{if(active){setRecords(Object.fromEntries(data.items.map(item=>[item.id,item])));setLoading(false);setError(false);}}).catch(()=>{if(active){setError(true);setLoading(false);}});
    return()=>{active=false;};
   },[ids.join(','),refresh]);
   const tool=(label,icon,onClick,extra={})=>el(Tooltip,{text:label},el(Button,{icon,onClick,'aria-label':label,title:label,...extra}));
   const add=(key,id)=>{if(ids.includes(id)||groups[key].length>=limits[key])return;setAttributes({[key]:[...groups[key],id]});};
   const reorder=(key,from,to)=>{const values=[...groups[key]];values.splice(to,0,...values.splice(from,1));setAttributes({[key]:values});};
   const transfer=(key,id)=>{const other=keys.find(k=>k!==key);if(groups[other].length>=limits[other])return;setAttributes({[key]:groups[key].filter(n=>n!==id),[other]:[...groups[other],id]});};
   return el('div',wp.blockEditor.useBlockProps({className:'aloha-guide-editor'}),el('p',{},'Select published Big Island Guide articles. Titles, photos, excerpts and links stay connected to the original posts.'),
    loading&&el(Spinner),error&&el(Notice,{status:'error',isDismissible:false},'Article data could not be loaded. The selection is preserved.'),
    ...keys.map(key=>el('section',{key,className:'aloha-guide-editor-group','aria-label':labels[key]},el('h3',{},labels[key]+' ('+groups[key].length+' / '+limits[key]+')'),
     el('ol',{className:'aloha-guide-editor-selection '+(key==='featuredIds'?'aloha-guide-featured':'aloha-guide-additional')},...groups[key].map((id,index)=>{
      const item=records[id],other=keys.find(k=>k!==key),available=item?.available;
      return el('li',{key:id},available?el('div',{className:'aloha-guide-card'+(item.thumbnail?'':' has-no-image')},item.thumbnail&&el('img',{src:item.thumbnail,alt:item.alt||''}),el('div',{className:'aloha-guide-caption'},el('h3',{},item.title),item.excerpt&&el('p',{},item.excerpt))):el(Notice,{status:item?'warning':'info',isDismissible:false},item?'Selected article is unavailable: removed, unpublished or outside Big Island Guide. Remove or replace it.':'Loading selected article…'),
       el('div',{className:'aloha-guide-editor-actions',role:'group','aria-label':(item?.title||'Unavailable article')+' actions'},
        tool('Move earlier in '+labels[key],'arrow-up-alt2',()=>reorder(key,index,index-1),{disabled:index===0}),tool('Move later in '+labels[key],'arrow-down-alt2',()=>reorder(key,index,index+1),{disabled:index===groups[key].length-1}),
        tool('Move to '+labels[other],'randomize',()=>transfer(key,id),{disabled:groups[other].length>=limits[other]}),tool('Remove article from '+labels[key],'trash',()=>setAttributes({[key]:groups[key].filter(n=>n!==id)}),{isDestructive:true})
       )
      );
     })),el(Search,{selected:ids,onAdd:id=>add(key,id),label:labels[key],full:groups[key].length>=limits[key]})
    )),el(Button,{variant:'secondary',onClick:()=>setRefresh(n=>n+1)},'Refresh articles')
   );
  },save:()=>null
 });
})();
