/* Native Gutenberg/Media Library APIs, no build dependency and no nested Image blocks. */
(() => {
 const {createElement:el, useState} = wp.element;
 const {Button, Modal, TextControl, TextareaControl, Notice, Placeholder, Tooltip} = wp.components;
 const {MediaUpload, MediaUploadCheck, useBlockProps} = wp.blockEditor;
 const plain = value => wp.htmlEntities.decodeEntities(String(value?.raw ?? value?.rendered ?? value ?? '').replace(/<[^>]*>/g, ''));
 const unique = ids => [...new Set(ids.filter(id => Number.isInteger(id) && id > 0))];
 wp.blocks.registerBlockType('aloha/media-gallery', {
  apiVersion:3, title:'Media Gallery', icon:'format-gallery', category:'media',
  attributes:{ids:{type:'array',items:{type:'integer'},default:[]}}, supports:{html:false,anchor:true},
  edit({attributes, setAttributes, isSelected}) {
   const ids = attributes.ids || [], [editing, setEditing] = useState(null), [busy, setBusy] = useState(false), [error, setError] = useState('');
   const media = wp.data.useSelect(select => ids.map(id => select('core').getMedia(id)), [ids.join(',')]);
   const update = values => setAttributes({ids:unique(values)});
   const move = (from, to) => { const values = [...ids]; values.splice(to, 0, ...values.splice(from, 1)); update(values); };
   const tool = (label, icon, onClick, extra = {}) => el(Tooltip,{text:label},el(Button,{icon,onClick,'aria-label':label,title:label,...extra}));
   const upload = (label, onSelect, multiple = true, icon = null) => el(MediaUploadCheck, {}, el(MediaUpload, {allowedTypes:['image'], multiple:multiple ? 'add' : false, onSelect, render:({open}) => icon ? tool(label,icon,open) : el(Button,{variant:'secondary',onClick:open},label)}));
   const append = () => upload(ids.length ? 'Add photos' : 'Select photos', items => update([...ids, ...(Array.isArray(items) ? items : [items]).map(m => m.id)]));
   const editMetadata = async id => {
    try { setError(''); const m = await wp.apiFetch({path:'/wp/v2/media/' + id + '?context=edit'}); setEditing({id, alt:m.alt_text || '', caption:m.caption?.raw ?? plain(m.caption)}); } catch(e) {setError(e.message);}
   };
   const saveMetadata = async () => {
    setBusy(true); setError('');
    try { await wp.data.dispatch('core').saveEntityRecord('postType', 'attachment', {id:editing.id, alt_text:editing.alt, caption:editing.caption}, {throwOnError:true}); setEditing(null); }
    catch(e) {setError(e.message);} finally {setBusy(false);}
   };
   return el('div', useBlockProps({className:'aloha-media-gallery'}),
    !!error && el(Notice,{status:'error',onRemove:()=>setError('')},error),
    ids.length ? el('div',{className:'aloha-gallery-mosaic','data-count':Math.min(ids.length,5)}, ...ids.slice(0,5).map((id,i) => {
     const m = media[i], src = m?.media_details?.sizes?.large?.source_url || m?.source_url;
     return el('figure',{className:'aloha-gallery-tile',key:id}, src ? el('span',{className:'aloha-gallery-photo'},el('img',{src,alt:m.alt_text || ''})) : el('span',{},m === null ? 'Image unavailable' : 'Loading photo…'), i === 4 && el('span',{className:'aloha-gallery-more'},ids.length > 5 ? '+' + (ids.length - 5) + ' photos' : 'View all photos (' + ids.length + ')'));
    })) : el(Placeholder,{icon:'format-gallery',label:'Media Gallery',instructions:'Select images from the Media Library. The first photo is the lead image.'},append()),
    !!ids.length && el('p',{},ids.length + ' photos · first photo is the lead image'),
    isSelected && !!ids.length && el('div',{className:'aloha-gallery-editor-tools'},append(), el('details', {open:true},el('summary',{},'Manage photos'),el('p',{},'Reorder, replace or remove photos here. Alt text and captions are shared Media Library data. Removing a photo here keeps its file in the Media Library.'),
     el('ol',{className:'aloha-gallery-editor-list'}, ...ids.map((id,i) => el('li',{key:id,className:i===0 ? 'is-lead' : undefined},
      media[i]?.source_url && el('img',{src:media[i].media_details?.sizes?.medium?.source_url || media[i].media_details?.sizes?.thumbnail?.source_url || media[i].source_url,alt:'',loading:'lazy'}),
      i===0 && el('span',{className:'aloha-gallery-lead-label'},'Lead image'),
      el('p',{},(i+1) + '. ' + (plain(media[i]?.title) || 'Photo ' + id)),
      el('div',{className:'aloha-gallery-editor-actions',role:'group','aria-label':'Photo '+(i+1)+' actions'},
       tool('Move earlier','arrow-left-alt2',()=>move(i,i-1),{disabled:i===0}),
       tool('Move later','arrow-right-alt2',()=>move(i,i+1),{disabled:i===ids.length-1}),
       tool('Make lead image','star-filled',()=>move(i,0),{disabled:i===0,isPressed:i===0}),
       upload('Replace photo',m=>{const values=[...ids];values[i]=m.id;update(values);},false,'update'),
       tool('Edit image details','edit',()=>editMetadata(id)),
       tool('Remove from gallery','trash',()=>update(ids.filter((_,n)=>n!==i)),{isDestructive:true})
      )
     )))
    )),
    editing && el(Modal,{title:'Edit Media Library details',onRequestClose:()=>!busy&&setEditing(null)},
     el('p',{},'These changes update the attachment in the Media Library everywhere it is used.'),
     el(TextControl,{label:'Alt text',value:editing.alt,onChange:alt=>setEditing({...editing,alt})}),
     el(TextareaControl,{label:'Caption',value:editing.caption,onChange:caption=>setEditing({...editing,caption})}),
     el(Button,{variant:'primary',isBusy:busy,disabled:busy,onClick:saveMetadata},'Save media details')
    )
   );
  },
  save:() => null
 });
})();
