/* One native modal for explicit film and photo triggers; no eager large image or iframe. */
(() => {
 'use strict';
 let dialog, stage, heading, opener, footer, counter, caption, previous, next, photos = [], index = 0, touch;
 const change = () => document.dispatchEvent(new Event('aloha-media-change'));
 const button = (label, action) => { const el = document.createElement('button'); el.type = 'button'; el.textContent = label; el.addEventListener('click', action); return el; };
 function create() {
  if (dialog) return;
  dialog = document.createElement('dialog'); dialog.className = 'aloha-media-dialog'; dialog.setAttribute('aria-labelledby', 'aloha-media-title');
  const inner = document.createElement('div'); inner.className = 'aloha-media-inner';
  heading = document.createElement('h2'); heading.id = 'aloha-media-title';
  const close = button('Close', () => dialog.close()); close.autofocus = true;
  stage = document.createElement('div'); stage.className = 'aloha-media-stage';
  footer = document.createElement('div'); footer.className = 'aloha-media-photo-footer';
  const controls = document.createElement('div'); controls.className = 'aloha-media-controls';
  previous = button('Previous', () => show(index - 1)); next = button('Next', () => show(index + 1));
  counter = document.createElement('span'); counter.setAttribute('role', 'status'); counter.setAttribute('aria-live', 'polite');
  caption = document.createElement('p'); caption.id = 'aloha-media-caption'; caption.tabIndex = 0; caption.className = 'aloha-media-caption';
  controls.append(previous, counter, next); footer.append(controls, caption); inner.append(heading, close, stage, footer); dialog.append(inner); document.body.append(dialog);
  dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
  dialog.addEventListener('keydown', e => {
   if (!photos.length || e.altKey || e.ctrlKey || e.metaKey) return;
   if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); show(index + (e.key === 'ArrowRight' ? 1 : -1)); }
  });
  stage.addEventListener('pointerdown', e => { touch = photos.length && e.pointerType === 'touch' ? {x:e.clientX, y:e.clientY, id:e.pointerId} : null; if (touch) stage.setPointerCapture(e.pointerId); });
  stage.addEventListener('pointerup', e => {
   if (!touch || touch.id !== e.pointerId) return;
   const x = e.clientX - touch.x, y = e.clientY - touch.y; touch = null;
   if (Math.abs(x) > 48 && Math.abs(x) > Math.abs(y) * 1.25) show(index + (x < 0 ? 1 : -1));
  });
  stage.addEventListener('pointercancel', () => { touch = null; });
  dialog.addEventListener('close', () => {
   // Native close is queued: Escape → Enter can reopen before it is delivered.
   if (dialog.open) return;
   stage.replaceChildren(); photos = []; touch = null; document.body.classList.remove('aloha-media-open');
   if (opener?.isConnected) opener.focus({preventScroll:true}); change();
  });
 }
 function resizePhoto() {
  const img = stage?.querySelector('img'), photo = photos[index];
  if (!img || !photo) return;
  const box = stage.getBoundingClientRect();
  img.sizes = Math.ceil(Math.min(box.width || innerWidth, (box.height || innerHeight) * photo.width / photo.height)) + 'px';
 }
 window.addEventListener('resize', resizePhoto);
 function show(value) {
  index = (value + photos.length) % photos.length;
  const photo = photos[index], img = document.createElement('img');
  img.alt = photo.alt || ''; img.decoding = 'async';
  counter.textContent = (index + 1) + ' / ' + photos.length; caption.textContent = photo.caption || '';
  if (photo.caption) img.setAttribute('aria-describedby', caption.id);
  // Only the active photograph receives a src. sizes describes the contained image.
  const box = stage.getBoundingClientRect();
  img.sizes = Math.ceil(Math.min(box.width || innerWidth, (box.height || innerHeight) * photo.width / photo.height)) + 'px';
  if (photo.srcset) img.srcset = photo.srcset;
  img.src = photo.url;
  img.addEventListener('error', () => { if (stage.contains(img)) stage.textContent = 'This photo is currently unavailable.'; });
  stage.replaceChildren(img);
  previous.disabled = next.disabled = photos.length < 2;
 }
 document.addEventListener('click', event => {
  if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) return;
  const film = event.target.closest('[data-aloha-youtube]');
  const photo = event.target.closest('[data-aloha-photo]');
  if (!film && !photo) return;
  if (!window.HTMLDialogElement?.prototype.showModal) return; // Photo href remains a working fallback.
  if (film && !/^[A-Za-z0-9_-]{11}$/.test(film.dataset.alohaYoutube)) return;
  let collection;
  if (photo) {
   try { collection = JSON.parse(photo.closest('[data-aloha-gallery]').querySelector('.aloha-gallery-data').textContent); } catch { return; }
   if (!Array.isArray(collection) || !collection.length || collection.some(p => !/^https?:\/\//.test(p.url) || !(p.width > 0 && p.height > 0))) return;
  }
  if (dialog?.open) return;
  event.preventDefault(); create(); opener = film || photo;
  photos = collection || []; dialog.classList.toggle('is-photo', !!photo); footer.hidden = !photo;
  heading.textContent = film ? film.dataset.alohaMediaTitle || 'Area film' : photo.closest('[data-aloha-gallery]').dataset.alohaMediaTitle || 'Photo gallery';
  if (film) {
   const frame = document.createElement('iframe'); frame.title = heading.textContent;
   frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture'; frame.allowFullscreen = true; frame.referrerPolicy = 'strict-origin-when-cross-origin';
   frame.src = 'https://www.youtube-nocookie.com/embed/' + film.dataset.alohaYoutube + '?autoplay=1&rel=0'; stage.replaceChildren(frame);
  }
  dialog.showModal(); document.body.classList.add('aloha-media-open');
  if (photo) show(Math.min(photos.length - 1, Number(photo.dataset.alohaPhoto) || 0));
  change();
 });
 // Broken remote files fail honestly without leaving a broken-image icon in the mosaic.
 document.addEventListener('error', e => { if (e.target.matches?.('.aloha-gallery-photo img')) e.target.parentElement.classList.add('is-unavailable'); }, true);
})();
