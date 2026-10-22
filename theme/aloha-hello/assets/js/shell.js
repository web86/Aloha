(() => {
 'use strict';
 const header = document.querySelector('.aloha-site-header');
 const dialog = document.querySelector('.aloha-demo-dialog');
 const overlay = document.querySelector('.aloha-primary-nav .wp-block-navigation__responsive-container');
 const closeMenu = document.querySelector('.aloha-primary-nav .wp-block-navigation__responsive-container-close');
 const openMenu = document.querySelector('.aloha-primary-nav .wp-block-navigation__responsive-container-open');
 if (openMenu && overlay) {
  openMenu.setAttribute('aria-controls', overlay.id);
  openMenu.setAttribute('aria-expanded', 'false');
 }
 const bottom = document.querySelector('.aloha-bottom-bar');
 if (bottom) new ResizeObserver(() => {
  const height = bottom.getBoundingClientRect().height;
  if (height) document.documentElement.style.setProperty('--aloha-bottom-height', `${height}px`);
 }).observe(bottom);
 const media = matchMedia('(max-width: 1279px)');
 const footerMedia = matchMedia('(min-width: 768px)');
 let menuWasOpen = false, historyEntry = false, suppressBack = false, returnFocus;
 const scroll = () => header?.classList.toggle('is-scrolled', scrollY > 8);
 addEventListener('scroll', scroll, {passive: true}); scroll();
 const footer = () => document.querySelectorAll('.aloha-footer-group').forEach(group => {
  group.open = footerMedia.matches;
  group.querySelector('summary').tabIndex = footerMedia.matches ? -1 : 0;
 });
 footerMedia.addEventListener('change', footer); footer();
 if (overlay) new MutationObserver(() => {
  const opened = overlay.classList.contains('is-menu-open');
  openMenu?.setAttribute('aria-expanded', String(opened));
  if (opened && !menuWasOpen) {
   history.pushState({...history.state, alohaMenu: true}, ''); historyEntry = true;
  } else if (!opened && menuWasOpen && historyEntry) {
   historyEntry = false; suppressBack = true; history.back();
  }
  menuWasOpen = opened;
 }).observe(overlay, {attributes: true, attributeFilter: ['class']});
 addEventListener('popstate', () => {
  if (suppressBack) { suppressBack = false; return; }
  if (menuWasOpen) { historyEntry = false; closeMenu?.click(); }
 });
 media.addEventListener('change', () => { if (!media.matches && menuWasOpen) closeMenu?.click(); });
 function showInfo(title, message, trigger) {
  if (!dialog) return;
  returnFocus = trigger;
  dialog.querySelector('h2').textContent = title;
  dialog.querySelector('p').textContent = message;
  dialog.showModal();
  dialog.querySelector('button').focus();
 }
 document.addEventListener('click', event => {
  const action = event.target.closest('[data-aloha-action]');
  const planned = event.target.closest('[data-aloha-destination]');
  if (action) {
   showInfo(action.dataset.alohaAction === 'call' ? 'Contact · demo' : 'Book Now · demo', action.dataset.alohaAction === 'call' ? 'A phone number has not been verified. No call has been initiated.' : 'This is a local demo. The catalog and booking flow are not available yet. No booking or payment has been made.', action);
  } else if (planned && document.body.classList.contains('aloha-shell-preview')) {
   event.preventDefault();
   showInfo('Destination preview', `${planned.textContent.trim()} — ${planned.getAttribute('href')} · ${planned.dataset.alohaStage}. This destination is planned; no empty page has been created.`, planned);
  }
  if (event.target.closest('[data-aloha-close]')) dialog?.close();
 });
 dialog?.addEventListener('close', () => returnFocus?.focus());
 document.addEventListener('focusin', event => document.body.classList.toggle('aloha-field-focused', event.target.matches('input, select, textarea, [contenteditable="true"]')));
 document.addEventListener('focusout', () => requestAnimationFrame(() => document.body.classList.toggle('aloha-field-focused', document.activeElement.matches('input, select, textarea, [contenteditable="true"]'))));
})();
