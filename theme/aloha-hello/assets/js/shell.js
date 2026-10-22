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
 // Core owns only the responsive overlay. Aloha owns all submenu interactions.
 const nav = document.querySelector('.aloha-primary-nav');
 if (nav) {
  const groups = [...nav.querySelectorAll('li.has-child')].map((li, i) => {
   const link = li.querySelector(':scope > a');
   const button = li.querySelector(':scope > button');
   const panel = li.querySelector(':scope > ul');
   if (!link || !button || !panel) return null;
   const top = li.parentElement.classList.contains('wp-block-navigation__container');
   li.classList.add('aloha-submenu'); if (top) li.classList.add('aloha-mega-item');
   panel.id = `${overlay.id}-aloha-panel-${i}`;
   panel.classList.add(top ? 'aloha-mega-panel' : 'aloha-nested-panel');
   const items = [...panel.children].filter(el => !el.classList.contains('aloha-mega-editorial'));
   const count = items.length;
   if (top) {
    const wrapper = document.createElement('li'), links = document.createElement('ul');
    wrapper.className = 'aloha-mega-links'; links.className = 'aloha-mega-links-list';
    links.append(...items); wrapper.append(links); panel.append(wrapper);
   }
   panel.style.setProperty('--aloha-mega-rows', Math.ceil(count / (count > 8 ? 3 : count > 3 ? 2 : 1)));
   panel.style.setProperty('--aloha-mega-columns', count > 8 ? 3 : count > 3 ? 2 : 1);
   if (panel.querySelector(':scope > .aloha-mega-editorial')) panel.classList.add('has-editorial');
   [link, button].forEach(el => { el.setAttribute('aria-controls', panel.id); el.setAttribute('aria-expanded', 'false'); });
   link.setAttribute('aria-describedby', 'aloha-submenu-help');
   return {li, link, button, panel, top};
  }).filter(Boolean);
  const hint = document.createElement('span'); hint.id = 'aloha-submenu-help'; hint.className = 'screen-reader-text';
  hint.textContent = 'Use Arrow Down to explore links. On touch, tap once to expand and again to visit.'; nav.append(hint);
  let active = null, touchArmed = null, opening, closing, pointer = 'mouse', pointerAt = 0;
  const clearTimers = () => { clearTimeout(opening); clearTimeout(closing); };
  const inGroup = (g, target) => target instanceof Node && (g.li.contains(target) || g.panel.contains(target));
  const cancelClose = () => clearTimeout(closing);
  const setState = (g, open) => {
   g.li.classList.toggle('is-aloha-open', open);
   g.panel.inert = !open;
   [g.link, g.button].forEach(el => el.setAttribute('aria-expanded', String(open)));
  };
  const close = (restore = false) => {
   clearTimers();
   const previous = active;
   groups.forEach(g => setState(g, false)); active = touchArmed = null;
   if (restore) previous?.link.focus();
  };
  const open = (g, source) => {
   clearTimers();
   groups.forEach(other => { if (other !== g && !other.li.contains(g.li)) setState(other, false); });
   if (active !== g || source !== 'touch') touchArmed = null;
   active = g; setState(g, true);
   if (g.top) g.li.style.setProperty('--aloha-bridge-top', `${g.link.getBoundingClientRect().bottom - header.getBoundingClientRect().top}px`);
   if (g.top && !media.matches) g.panel.querySelectorAll('template.aloha-mega-media').forEach(t => t.replaceWith(t.content.cloneNode(true)));
  };
  const leaveGroup = (g, e) => {
   if (e.pointerType !== 'mouse' || media.matches || touchArmed || inGroup(g, e.relatedTarget)) return;
   clearTimeout(opening); cancelClose();
   closing = setTimeout(() => {
    if (active === g && !g.li.matches(':hover') && !g.panel.matches(':hover') && !inGroup(g, document.activeElement) && !dialog?.open) close();
   }, 200);
  };
  groups.forEach(g => {
   setState(g, false);
   g.link.addEventListener('pointerenter', e => {
    if (e.pointerType !== 'mouse' || media.matches) return;
    clearTimers(); touchArmed = null;
    if (active && active !== g && !active.panel.contains(document.activeElement)) close();
    opening = setTimeout(() => open(g, 'mouse'), 110);
   });
   [g.li, g.panel].forEach(region => {
    region.addEventListener('pointerenter', e => { if (e.pointerType === 'mouse') cancelClose(); });
    region.addEventListener('pointerleave', e => leaveGroup(g, e));
   });
   g.button.addEventListener('click', () => {
    if (g.li.classList.contains('is-aloha-open')) close(); else open(g, 'keyboard');
   });
   g.link.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') {
     e.preventDefault(); open(g, 'keyboard');
     [...g.panel.querySelectorAll('a, button')].find(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden')?.focus();
    }
   });
   g.link.addEventListener('click', e => {
    if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.detail === 0) return;
    const type = e.pointerType || (performance.now() - pointerAt < 1500 ? pointer : 'mouse');
    if (type !== 'touch' && type !== 'pen') return;
    if (touchArmed === g && g.li.classList.contains('is-aloha-open')) return;
    e.preventDefault(); e.stopPropagation(); open(g, 'touch'); touchArmed = g;
   });
  });
  document.addEventListener('pointerdown', e => {
   const next = e.pointerType || 'mouse';
   if (next !== pointer) { clearTimers(); touchArmed = null; }
   pointer = next; pointerAt = performance.now();
   const control = e.target.closest('a, button');
   // Collapsing the old mobile section on pointerdown moves the new target before click.
   if (active && !dialog?.open && !active.li.contains(e.target) && !(control && nav.contains(control))) close();
  }, true);
  nav.addEventListener('pointerover', e => {
   if (e.pointerType !== 'mouse' || media.matches || touchArmed || e.target.closest('.aloha-mega-panel')) return;
   const top = e.target.closest('.wp-block-navigation__container > li');
   if (top && !top.classList.contains('aloha-mega-item')) close();
  });
  nav.addEventListener('click', e => { if (active && !active.li.contains(e.target)) close(); });
  nav.addEventListener('keydown', e => {
   pointerAt = -Infinity; touchArmed = null;
   if (e.key === 'Tab' && media.matches && overlay?.classList.contains('is-menu-open')) {
    // Core caches the trap endpoints on opening; disclosures change the last visible link.
    const visible = [...overlay.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex^="-"])')].filter(el => !el.closest('[inert]') && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
    e.stopPropagation();
    if (e.shiftKey && document.activeElement === visible[0]) { e.preventDefault(); visible.at(-1)?.focus(); }
    else if (!e.shiftKey && document.activeElement === visible.at(-1)) { e.preventDefault(); visible[0]?.focus(); }
   }
   if (e.key === 'Escape' && active) { e.preventDefault(); e.stopPropagation(); close(true); }
  }, true);
  document.addEventListener('focusin', e => {
   if (media.matches && overlay?.classList.contains('is-menu-open') && e.target === closeMenu) return;
   if (performance.now() - pointerAt < 500 && nav.contains(e.target)) return;
   if (active && !dialog?.open && !active.li.contains(e.target)) close();
  });
  if (overlay) new MutationObserver(() => { if (!overlay.classList.contains('is-menu-open')) close(); }).observe(overlay, {attributes:true, attributeFilter:['class']});
  media.addEventListener('change', () => close());
  new ResizeObserver(() => {
   header.style.setProperty('--aloha-header-bottom', `${Math.max(0, header.getBoundingClientRect().bottom)}px`);
  }).observe(header);
  addEventListener('scroll', () => header.style.setProperty('--aloha-header-bottom', `${Math.max(0, header.getBoundingClientRect().bottom)}px`), {passive:true});
  // Resize clears touch arming even if the desktop breakpoint has not changed.
  addEventListener('resize', () => close());
  nav.classList.add('aloha-nav-ready');
 }

 function showInfo(title, message, trigger) {
  if (!dialog) return;
  returnFocus = trigger;
  dialog.querySelector('h2').textContent = title;
  dialog.querySelector('p').textContent = message;
  dialog.showModal();
  dialog.querySelector('button').focus();
 }
 document.addEventListener('click', event => {
  if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  const action = event.target.closest('[data-aloha-action]');
  const planned = event.target.closest('[data-aloha-destination]');
  if (action) {
   showInfo(action.dataset.alohaAction === 'call' ? 'Contact · demo' : 'Book Now · demo', action.dataset.alohaAction === 'call' ? 'A phone number has not been verified. No call has been initiated.' : 'This is a local demo. The catalog and booking flow are not available yet. No booking or payment has been made.', action);
  } else if (planned && (document.body.classList.contains('aloha-shell-preview') || planned.closest('.aloha-area-planned-link'))) {
   event.preventDefault();
   showInfo('Destination preview', `${planned.textContent.trim()} — ${planned.getAttribute('href')} · ${planned.dataset.alohaStage}. This destination is planned; no empty page has been created.`, planned);
  }
  if (event.target.closest('[data-aloha-close]')) dialog?.close();
 });
 dialog?.addEventListener('close', () => returnFocus?.focus());
 document.addEventListener('focusin', event => document.body.classList.toggle('aloha-field-focused', event.target.matches('input, select, textarea, [contenteditable="true"]')));
 document.addEventListener('focusout', () => requestAnimationFrame(() => document.body.classList.toggle('aloha-field-focused', document.activeElement.matches('input, select, textarea, [contenteditable="true"]'))));
})();
