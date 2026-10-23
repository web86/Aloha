/* One visual collection, using Core media, fields, icons and FocalPointPicker. */
(() => {
 const {createElement:el, useState}=wp.element;
 const {Button, Tooltip, Modal, TextControl, FocalPointPicker, Placeholder}=wp.components;
 const {MediaUpload, MediaUploadCheck, RichText, useBlockProps}=wp.blockEditor;
 const empty=()=>({title:'',description:'',imageId:0,url:'',focalPoint:{x:.5,y:.5}});
 wp.blocks.registerBlockType('aloha/beaches',{
  apiVersion:3,title:'Beaches',category:'media',icon:'location-alt',attributes:{cards:{type:'array',default:[]}},supports:{html:false,anchor:true},
  edit({attributes,setAttributes,isSelected}){
   const cards=attributes.cards||[],[focal,setFocal]=useState(null);
   const media=wp.data.useSelect(select=>cards.map(c=>c.imageId?select('core').getMedia(c.imageId):null),[cards.map(c=>c.imageId).join(',')]);
   const update=values=>setAttributes({cards:values});
   const patch=(index,fields)=>update(cards.map((c,i)=>i===index?{...c,...fields}:c));
   const move=(from,to)=>{const values=[...cards];values.splice(to,0,...values.splice(from,1));update(values);};
   const tool=(label,icon,onClick,extra={})=>el(Tooltip,{text:label},el(Button,{icon,onClick,'aria-label':label,title:label,...extra}));
   const choose=(i,icon=true)=>el(MediaUploadCheck,{},el(MediaUpload,{allowedTypes:['image'],value:cards[i].imageId,onSelect:m=>patch(i,{imageId:m.id}),render:({open})=>icon?tool('Choose or replace photo','format-image',open):el(Button,{variant:'secondary',onClick:open},'Choose photo')}));
   const add=()=>el(Button,{variant:'primary',onClick:()=>update([...cards,empty()])},'Add Beach');
   return el('div',useBlockProps({className:'aloha-beaches-editor'}),
    !cards.length?el(Placeholder,{icon:'location-alt',label:'Beaches',instructions:'Add editorial cards for this Area. Check beach details and image locations before publishing.'},add()):el('div',{},isSelected&&el('p',{},'Beach cards are local to this block. Removing a card or photo keeps the original file in the Media Library.'),
     el('ol',{className:'aloha-beaches-editor-list'},...cards.map((card,i)=>{
      const m=media[i],src=m?.media_details?.sizes?.medium?.source_url||m?.source_url,point=card.focalPoint||{x:.5,y:.5};
      return el('li',{key:i},src?el('div',{className:'aloha-beaches-image'},el('img',{src,alt:m.alt_text||'',style:{objectPosition:(point.x*100)+'% '+(point.y*100)+'%'}})):el('div',{className:'aloha-beaches-editor-no-photo'},'No photo selected'),
       el(TextControl,{label:'Beach title',value:card.title||'',onChange:title=>patch(i,{title})}),
       el('label',{},'Short description',el(RichText,{tagName:'div',className:'aloha-beaches-editor-description',value:card.description||'',allowedFormats:['core/bold','core/italic','core/link'],placeholder:'Add a verified description…',onChange:description=>patch(i,{description}),'aria-label':'Short description'})),
       el(TextControl,{label:'Optional link',type:'url',value:card.url||'',placeholder:'/big-island/guide/…',onChange:url=>patch(i,{url}),help:'Leave empty until an editorial destination is confirmed.'}),
       el('div',{className:'aloha-beaches-editor-actions',role:'group','aria-label':'Beach '+(i+1)+' actions'},
        tool('Move earlier','arrow-left-alt2',()=>move(i,i-1),{disabled:i===0}),tool('Move later','arrow-right-alt2',()=>move(i,i+1),{disabled:i===cards.length-1}),choose(i),
        tool('Set focal point','image-crop',()=>setFocal(i),{disabled:!src}),
        tool('Remove photo','dismiss',()=>patch(i,{imageId:0}),{disabled:!card.imageId}),tool('Remove Beach','trash',()=>update(cards.filter((_,n)=>i!==n)),{isDestructive:true})
       )
      );
     })),isSelected&&add()
    ),
    focal!==null&&cards[focal]&&el(Modal,{title:'Photo focal point',onRequestClose:()=>setFocal(null)},
     el('p',{},'Choose the part of the photo to keep in the 4:3 frame. The original image is unchanged.'),
     el(FocalPointPicker,{url:media[focal]?.media_details?.sizes?.large?.source_url||media[focal]?.source_url,value:cards[focal].focalPoint||{x:.5,y:.5},onChange:focalPoint=>patch(focal,{focalPoint})}),
     el(Button,{variant:'secondary',onClick:()=>patch(focal,{focalPoint:{x:.5,y:.5}})},'Reset focal point'),el(Button,{variant:'primary',onClick:()=>setFocal(null)},'Done')
    )
   );
  },save:()=>null
 });
})();
