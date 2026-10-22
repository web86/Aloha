/* Assign identities before the editor serializes Primary. The server also validates API saves. */
(() => {
 const readKey = value => (value || '').split(/\s+/).find(c => /^aloha-mega-(rentals|island|homeowners|about|section-[a-f0-9-]{36})$/.test(c));
 const hasLinks = blocks => blocks.some(b => b.name === 'core/home-link' || (['core/navigation-link','core/navigation-submenu','core/home-link'].includes(b.name) && b.attributes.url) || hasLinks(b.innerBlocks || []));
 wp.hooks.addFilter('editor.BlockEdit', 'aloha/mega-identity', BlockEdit => function AlohaMegaIdentity(props) {
  const context = wp.data.useSelect(select => {
   if (props.name !== 'core/navigation-submenu') return null;
   const editor = select('core/block-editor'), root = editor.getBlockRootClientId(props.clientId);
   return {parent: editor.getBlock(root), siblings: editor.getBlocks(root), children: editor.getBlocks(props.clientId)};
  }, [props.name, props.clientId]);
  wp.element.useEffect(() => {
   if (context?.parent?.name !== 'core/navigation' || context.parent.attributes.ref !== Number(alohaMegaEditor.primaryId) || !hasLinks(context.children)) return;
   const key = readKey(props.attributes.className);
   const index = context.siblings.findIndex(b => b.clientId === props.clientId);
   if (key && !context.siblings.slice(0,index).some(b => readKey(b.attributes.className) === key)) return;
   const classes = (props.attributes.className || '').split(/\s+/).filter(c => c && !readKey(c));
   classes.push('aloha-mega-section-' + crypto.randomUUID());
   props.setAttributes({className: classes.join(' ')});
  }, [context, props.attributes.className, props.clientId]);
  return wp.element.createElement(BlockEdit, props);
 });
})();
