(() => {
    'use strict';
    const root = document.querySelector('[data-rental-catalog]');
    if (!root) return;
    const form = root.querySelector('[data-catalog-form]');
    const mobile = matchMedia('(max-width: 767px)');
    const guests = root.querySelector('[data-guest-control]');
    const guestToggle = guests.querySelector('[data-guest-toggle]');
    const guestPopover = guests.querySelector('[data-guest-popover]');
    const guestDone = guests.querySelector('[data-guest-done]');
    const guestInputs = ['adults', 'children'].map(key => form.elements[key]);
    function updateGuests() {
        const [adults, children] = guestInputs.map(input => Number(input.value) || 0);
        guestToggle.textContent = adults + children ? `${adults} ${adults === 1 ? 'adult' : 'adults'}, ${children} ${children === 1 ? 'child' : 'children'}` : 'Add guests';
        guestToggle.title = guestToggle.textContent;
        guests.querySelectorAll('[data-guest-step]').forEach(button => {
            const value = Number(form.elements[button.dataset.guestField].value) || 0;
            button.disabled = Number(button.dataset.guestStep) < 0 ? value <= 0 : value >= 100;
        });
    }
    function positionGuests() {
        if (guestPopover.hidden || mobile.matches) return;
        const viewport = window.visualViewport;
        const height = viewport?.height || innerHeight;
        const top = viewport?.offsetTop || 0;
        const headerBottom = Math.max(top + 8, document.querySelector('.aloha-site-header')?.getBoundingClientRect().bottom || 0);
        guestPopover.style.translate = '';
        guestPopover.style.maxHeight = Math.max(100, top + height - headerBottom - 24) + 'px';
        const box = guestPopover.getBoundingClientRect();
        const x = box.left < 20 ? 20 - box.left : Math.min(0, innerWidth - 20 - box.right);
        const y = Math.max(headerBottom + 8 - box.top, Math.min(0, top + height - 16 - box.bottom));
        guestPopover.style.translate = `${x}px ${y}px`;
    }
    function closeGuests(restoreFocus = false) {
        guestPopover.hidden = !mobile.matches;
        guestToggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus) guestToggle.focus({preventScroll: true});
    }
    guestToggle.hidden = mobile.matches;
    guestPopover.hidden = !mobile.matches;
    guestPopover.setAttribute('role', 'dialog');
    guestPopover.setAttribute('aria-label', 'Guests');
    guestDone.hidden = false;
    guests.querySelectorAll('[data-guest-step]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', () => {
            const input = form.elements[button.dataset.guestField];
            input.value = Math.max(0, Math.min(100, (Number(input.value) || 0) + Number(button.dataset.guestStep)));
            updateGuests();
        });
    });
    guestInputs.forEach(input => input.addEventListener('input', () => {
        if (input.value !== '') input.value = Math.max(0, Math.min(100, Math.trunc(Number(input.value) || 0)));
        updateGuests();
    }));
    guestToggle.addEventListener('click', () => {
        const opening = guestPopover.hidden;
        guestPopover.hidden = !opening;
        guestToggle.setAttribute('aria-expanded', String(opening));
        if (opening) { positionGuests(); guestInputs[0].focus({preventScroll: true}); }
    });
    guestDone.addEventListener('click', () => closeGuests(true));
    guests.addEventListener('keydown', event => {
        if (!mobile.matches && event.key === 'Escape' && !guestPopover.hidden) { event.preventDefault(); closeGuests(true); }
    });
    guests.addEventListener('focusout', event => { if (!guests.contains(event.relatedTarget)) closeGuests(); });
    document.addEventListener('pointerdown', event => { if (!guests.contains(event.target)) closeGuests(); });
    const bedroomInputs = [...form.querySelectorAll('[name="bedrooms"]')];
    const selectBedrooms = value => bedroomInputs.forEach(input => { input.checked = input.value === value; });
    bedroomInputs.filter(input => input.value !== '').forEach(input => {
        let checkedBefore = false;
        input.addEventListener('pointerdown', () => { checkedBefore = input.checked; });
        input.addEventListener('click', event => {
            if (checkedBefore) {
                // Let the radio's native activation finish before selecting the reset value.
                setTimeout(() => { selectBedrooms(''); }, 0);
            }
            checkedBefore = false;
        });
        input.addEventListener('keydown', event => {
            if (event.key === ' ' && input.checked) { event.preventDefault(); selectBedrooms(''); }
        });
    });
    root.classList.add('is-enhanced');
    const sticky = root.querySelector('[data-catalog-sticky]');
    const header = document.querySelector('.aloha-site-header');
    function measureSticky() {
        const visible = Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--aloha-header-visible-bottom'));
        const top = Math.max(0, Number.isFinite(visible) ? visible : header?.getBoundingClientRect().bottom || 0);
        root.style.setProperty('--catalog-sticky-top', top + 'px');
        root.style.setProperty('--catalog-map-top', top + sticky.offsetHeight + 16 + 'px');
    }
    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(measureSticky);
        if (header) observer.observe(header);
        observer.observe(sticky);
    }
    addEventListener('resize', () => { measureSticky(); positionGuests(); });
    window.visualViewport?.addEventListener('resize', positionGuests);
    window.visualViewport?.addEventListener('scroll', positionGuests);
    document.addEventListener('aloha:header-change', measureSticky);
    addEventListener('scroll', measureSticky, {passive: true});
    measureSticky();
    const cards = [...root.querySelectorAll('[data-catalog-item]')];
    const areas = new Map([...form.elements.area_id.options].map(option => [Number(option.value), option.textContent]));
    const keys = ['area_id', 'bedrooms', 'adults', 'children'];
    const integer = (value, max) => typeof value === 'string' && /^[0-9]{1,9}$/.test(value) && Number(value) <= max ? Number(value) : 0;
    const normalize = input => ({area_id: areas.has(integer(input.area_id, 999999999)) ? integer(input.area_id, 999999999) : 0,
        bedrooms: ['2', '3', '4'].includes(input.bedrooms) ? input.bedrooms : '',
        adults: integer(input.adults, 100), children: integer(input.children, 100)});
    function fromURL() {
        const input = {};
        // Match PHP's last value, including array-shaped invalid parameters.
        for (const [name, value] of new URL(location.href).searchParams) {
            keys.forEach(key => {
                if (name === key) input[key] = value;
                else if (name.startsWith(key + '[')) input[key] = null;
            });
        }
        return normalize(input);
    }
    function urlFor(filters) {
        const url = new URL(form.action);
        keys.forEach(key => { if (filters[key]) url.searchParams.set(key, filters[key]); });
        return url;
    }
    function matches(fields, filters) {
        if (filters.area_id && fields.area !== filters.area_id) return false;
        if (filters.bedrooms && (fields.bedrooms === null || (filters.bedrooms === '4' ? fields.bedrooms < 4 : fields.bedrooms !== Number(filters.bedrooms)))) return false;
        const guests = filters.adults + filters.children;
        return (!guests || (fields.guests !== null && fields.guests >= guests)) && (!filters.children || fields.children !== false);
    }
    let current = fromURL();
    function apply(filters, push = false) {
        current = filters;
        setControls(filters);
        let count = 0;
        cards.forEach(card => {
            card.hidden = !matches(JSON.parse(card.dataset.catalogFields), filters);
            if (card.hidden) return;
            count++;
            sizeCard(card);
            const image = card.querySelector('img');
            if (image?.dataset.src) {
                if (image.dataset.srcset) { image.srcset = image.dataset.srcset; delete image.dataset.srcset; }
                image.src = image.dataset.src; delete image.dataset.src;
            }
        });
        root.querySelector('[data-catalog-count]').textContent = `${count} ${count === 1 ? 'home' : 'homes'} in current results`;
        root.querySelector('[data-catalog-empty]').hidden = count > 0;
        const chips = root.querySelector('[data-catalog-chips]');
        chips.replaceChildren();
        const labels = {area_id: areas.get(filters.area_id), bedrooms: `${filters.bedrooms === '4' ? '4+' : filters.bedrooms} bedrooms`, adults: `${filters.adults} adults`, children: `${filters.children} children`};
        keys.forEach(key => {
            if (!filters[key]) return;
            const without = {...filters, [key]: key === 'bedrooms' ? '' : 0};
            const chip = document.createElement('a');
            chip.href = urlFor(without); chip.dataset.catalogRemove = key;
            chip.append(document.createTextNode(labels[key]));
            const close = document.createElement('span'); close.className = 'aloha-catalog-chip-close'; close.textContent = '×'; close.setAttribute('aria-hidden', 'true'); chip.append(close);
            chip.setAttribute('aria-label', 'Remove ' + labels[key]);
            chips.append(chip);
        });
        if (Object.values(filters).some(Boolean)) {
            const clear = document.createElement('a'); clear.href = form.action;
            clear.dataset.catalogClear = ''; clear.textContent = 'Clear all'; chips.append(clear);
        }
        if (push) history[push === 'replace' ? 'replaceState' : 'pushState'](cleanHistory(), '', urlFor(filters));
        updateCompact();
        document.dispatchEvent(new CustomEvent('aloha:catalog-filter'));
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        closeGuests();
        const replacing = activeDialog?.kind === 'filters';
        if (replacing) finishModal();
        apply(normalize(Object.fromEntries(new FormData(form))), replacing ? 'replace' : true);
    });
    root.querySelector('[data-catalog-chips]').addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button) return;
        event.preventDefault();
        const key = link.dataset.catalogRemove;
        apply(key ? {...current, [key]: key === 'bedrooms' ? '' : 0} : {area_id: 0, bedrooms: '', adults: 0, children: 0}, true);
        form.querySelector('button[type="submit"]').focus({preventScroll: true});
    });
    // Native dialogs provide top-layer isolation, inert background and the focus trap.
    // The original fields and map are moved, never cloned or rebuilt.
    const fields = root.querySelector('[data-catalog-filter-fields]');
    const fieldsHome = document.createComment('catalog fields'); fields.before(fieldsHome);
    const toggle = root.querySelector('[data-catalog-toggle]');
    const map = root.querySelector('#catalog-map');
    const mapHome = document.createComment('catalog map'); map.before(mapHome);
    const layout = root.querySelector('[data-catalog-layout]');
    const compact = document.createElement('button'); compact.type = 'button';
    compact.className = 'aloha-catalog-mobile-filters'; compact.textContent = 'Filters'; compact.hidden = true;
    compact.setAttribute('aria-haspopup', 'dialog'); compact.setAttribute('aria-controls', 'catalog-filter-sheet');
    form.querySelector('.aloha-catalog-search').before(compact);
    const empty = {area_id: 0, bedrooms: '', adults: 0, children: 0};
    let activeDialog = null, desktopMapHidden = false;
    const cleanHistory = () => { const state = {...history.state}; delete state.alohaCatalogOverlay; return state; };
    function setControls(filters) {
        form.elements.area_id.value = String(filters.area_id);
        selectBedrooms(filters.bedrooms);
        form.elements.adults.value = filters.adults; form.elements.children.value = filters.children;
        updateGuests();
    }
    function updateCompact() {
        const count = Number(Boolean(current.area_id)) + Number(Boolean(current.bedrooms)) + Number(current.adults + current.children > 0);
        compact.textContent = count ? `Filters (${count})` : 'Filters';
    }
    function closeIcon() {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 20 20'); svg.setAttribute('width', '20'); svg.setAttribute('height', '20'); svg.setAttribute('aria-hidden', 'true');
        const path = document.createElementNS(svg.namespaceURI, 'path'); path.setAttribute('d', 'm5 5 10 10M15 5 5 15'); path.setAttribute('stroke', 'currentColor'); path.setAttribute('stroke-width', '1.6');
        svg.append(path); return svg;
    }
    function makeDialog(kind, title) {
        const dialog = document.createElement('dialog'); dialog.id = kind === 'filters' ? 'catalog-filter-sheet' : 'catalog-map-dialog';
        dialog.className = 'aloha-catalog-dialog is-' + kind; dialog.setAttribute('aria-labelledby', dialog.id + '-title');
        const heading = document.createElement('div'); heading.className = 'aloha-catalog-dialog-heading';
        const h2 = document.createElement('h2'); h2.id = dialog.id + '-title'; h2.textContent = title;
        const close = document.createElement('button'); close.type = 'button'; close.className = 'aloha-catalog-dialog-close';
        close.append(closeIcon(), document.createTextNode(kind === 'map' ? 'Close map' : 'Close filters'));
        close.addEventListener('click', () => dismissModal());
        heading.append(h2, close); dialog.append(heading); root.append(dialog);
        dialog.addEventListener('cancel', event => { event.preventDefault(); dismissModal(); });
        dialog.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const items = [...dialog.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),[tabindex="0"]')].filter(item => item.getClientRects().length && getComputedStyle(item).visibility !== 'hidden');
            if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items.at(-1)?.focus(); }
            else if (!event.shiftKey && document.activeElement === items.at(-1)) { event.preventDefault(); items[0]?.focus(); }
        });
        dialog.addEventListener('click', event => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dismissModal(); } });
        return dialog;
    }
    const sheet = makeDialog('filters', 'Filter homes');
    const sheetBody = document.createElement('div'); sheetBody.className = 'aloha-catalog-sheet-body'; sheet.append(sheetBody);
    const actions = document.createElement('div'); actions.className = 'aloha-catalog-sheet-actions';
    const clear = document.createElement('button'); clear.type = 'button'; clear.textContent = 'Clear all'; clear.className = 'aloha-catalog-sheet-clear';
    clear.addEventListener('click', () => setControls(empty));
    const applyButton = document.createElement('button'); applyButton.type = 'button'; applyButton.textContent = 'Show homes'; applyButton.className = 'aloha-catalog-sheet-apply';
    applyButton.addEventListener('click', () => form.requestSubmit()); actions.append(clear, applyButton); sheet.append(actions);
    const mapDialog = makeDialog('map', 'Rental locations');
    function modalEvent() { document.dispatchEvent(new CustomEvent('aloha:catalog-overlay')); }
    function mapEvent() { document.dispatchEvent(new CustomEvent('aloha:catalog-map-toggle')); }
    function openModal(kind, trigger, push = true) {
        if (!mobile.matches || activeDialog) return;
        closeGuests();
        const dialog = kind === 'filters' ? sheet : mapDialog;
        const y = scrollY;
        const saved = ['position', 'top', 'left', 'right', 'width', 'overflow', 'padding-right'].map(name => [name, document.body.style.getPropertyValue(name), document.body.style.getPropertyPriority(name)]);
        const gap = innerWidth - document.documentElement.clientWidth;
        if (kind === 'map') { map.hidden = false; toggle.setAttribute('aria-expanded', 'true'); }
        activeDialog = {kind, dialog, trigger, y, saved};
        if (push) history.pushState({...cleanHistory(), alohaCatalogOverlay: kind}, '', location.href);
        document.body.style.position = 'fixed'; document.body.style.top = -y + 'px'; document.body.style.left = '0'; document.body.style.right = '0'; document.body.style.width = '100%'; document.body.style.overflow = 'hidden';
        if (gap) document.body.style.paddingRight = (parseFloat(getComputedStyle(document.body).paddingRight) + gap) + 'px';
        dialog.showModal(); dialog.querySelector('.aloha-catalog-dialog-close').focus({preventScroll: true});
        modalEvent(); if (kind === 'map') mapEvent();
    }
    function finishModal(restoreFocus = true) {
        if (!activeDialog) return;
        const previous = activeDialog; activeDialog = null;
        previous.dialog.close();
        previous.saved.forEach(([name, value, priority]) => { if (value) document.body.style.setProperty(name, value, priority); else document.body.style.removeProperty(name); });
        if (previous.kind === 'map') { map.hidden = true; toggle.setAttribute('aria-expanded', 'false'); mapEvent(); }
        window.scrollTo({top: previous.y, behavior: 'instant'});
        if (restoreFocus && previous.trigger?.isConnected) previous.trigger.focus({preventScroll: true});
        modalEvent();
    }
    function dismissModal() {
        if (!activeDialog) return;
        const hasEntry = history.state?.alohaCatalogOverlay === activeDialog.kind;
        finishModal();
        if (hasEntry) history.back();
    }
    compact.addEventListener('click', () => openModal('filters', compact));
    toggle.hidden = false;
    toggle.setAttribute('aria-haspopup', mobile.matches ? 'dialog' : 'false');
    toggle.addEventListener('click', () => {
        if (mobile.matches) { openModal('map', toggle); return; }
        const y = scrollY;
        desktopMapHidden = !desktopMapHidden; map.hidden = desktopMapHidden;
        layout.classList.toggle('is-map-hidden', desktopMapHidden);
        toggle.textContent = desktopMapHidden ? 'Show map' : 'Hide map'; toggle.setAttribute('aria-expanded', String(!desktopMapHidden));
        updateSizes(); mapEvent(); window.scrollTo({top: y, behavior: 'instant'});
    });
    function sizeCard(card) {
        const width = Math.ceil(card.querySelector('.aloha-related-gallery').getBoundingClientRect().width);
        if (width) card.querySelectorAll('img').forEach(image => { image.sizes = width + 'px'; });
    }
    function updateSizes() { cards.filter(card => !card.hidden).forEach(sizeCard); }
    if ('ResizeObserver' in window) new ResizeObserver(updateSizes).observe(root.querySelector('.aloha-catalog-grid'));
    function changeMode() {
        if (activeDialog) { finishModal(false); history.replaceState(cleanHistory(), '', location.href); }
        if (mobile.matches) { sheetBody.append(fields); mapDialog.append(map); map.hidden = true; }
        else { fieldsHome.after(fields); mapHome.after(map); map.hidden = desktopMapHidden; }
        compact.hidden = !mobile.matches; guestToggle.hidden = mobile.matches; guestDone.hidden = mobile.matches;
        guestPopover.hidden = !mobile.matches; guestPopover.style.translate = ''; guestPopover.style.maxHeight = '';
        guestPopover.setAttribute('role', mobile.matches ? 'group' : 'dialog');
        layout.classList.toggle('is-map-hidden', mobile.matches || desktopMapHidden);
        toggle.textContent = mobile.matches || desktopMapHidden ? 'Show map' : 'Hide map';
        toggle.setAttribute('aria-expanded', String(!map.hidden)); toggle.setAttribute('aria-haspopup', mobile.matches ? 'dialog' : 'false');
        updateSizes(); measureSticky(); mapEvent();
    }
    mobile.addEventListener('change', changeMode);
    window.addEventListener('popstate', event => {
        const kind = event.state?.alohaCatalogOverlay;
        if (activeDialog && activeDialog.kind !== kind) finishModal();
        closeGuests(); apply(fromURL());
        if (mobile.matches && !activeDialog && ['filters', 'map'].includes(kind)) openModal(kind, kind === 'filters' ? compact : toggle, false);
    });
    changeMode();
    apply(current);
})();
