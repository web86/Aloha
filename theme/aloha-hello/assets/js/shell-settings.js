/* Settings API owns persistence; the standard Media Library owns attachments. */
(() => {
 'use strict';
 document.querySelectorAll('.aloha-media-field').forEach(field => {
  const input = field.querySelector('input[type=hidden]'), preview = field.querySelector('.aloha-media-preview');
  const status = field.querySelector('.aloha-media-status');
  let frame;
  field.querySelector('.aloha-media-select').addEventListener('click', () => {
   if (!frame) {
    frame = wp.media({title: 'Mega Menu image', button: {text: 'Use image'}, library: {type: 'image'}, multiple: false});
    frame.on('select', () => {
     const item = frame.state().get('selection').first().toJSON();
     if (item.type !== 'image' || !['image/jpeg','image/png','image/webp','image/avif','image/gif'].includes(item.mime)) {
      status.textContent = 'Choose a JPEG, PNG, WebP, AVIF or GIF image.'; return;
     }
     input.value = item.id;
     const img = document.createElement('img');
     img.src = item.sizes?.thumbnail?.url || item.url; img.alt = item.alt || '';
     img.style.cssText = 'max-width:150px;height:auto'; preview.replaceChildren(img);
     status.textContent = 'Image selected. Save Changes to apply.';
    });
    frame.on('open', () => { const selection = frame.state().get('selection'); selection.reset(); if (+input.value) selection.add(wp.media.attachment(+input.value)); });
   }
   frame.open();
  });
  field.querySelector('.aloha-media-remove').addEventListener('click', () => {
   input.value = '0'; preview.replaceChildren(); status.textContent = 'Image removed. Save Changes to apply.';
  });
 });
})();
