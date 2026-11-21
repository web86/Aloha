(() => {
    'use strict';
    const root = document.querySelector('[data-rental-catalog]');
    if (!root) return;
    const form = root.querySelector('[data-catalog-form]');
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
        if (guestPopover.hidden) return;
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
        guestPopover.hidden = true;
        guestToggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus) guestToggle.focus({preventScroll: true});
    }
    guestToggle.hidden = false;
    guestPopover.hidden = true;
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
        if (event.key === 'Escape' && !guestPopover.hidden) { event.preventDefault(); closeGuests(true); }
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
        const top = Math.max(0, header?.getBoundingClientRect().bottom || 0);
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
        form.elements.area_id.value = String(filters.area_id);
        selectBedrooms(filters.bedrooms);
        form.elements.adults.value = filters.adults;
        form.elements.children.value = filters.children;
        updateGuests();
        let count = 0;
        cards.forEach(card => {
            card.hidden = !matches(JSON.parse(card.dataset.catalogFields), filters);
            if (card.hidden) return;
            count++;
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
        if (push) history.pushState(null, '', urlFor(filters));
        document.dispatchEvent(new CustomEvent('aloha:catalog-filter'));
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        closeGuests();
        apply(normalize(Object.fromEntries(new FormData(form))), true);
    });
    root.querySelector('[data-catalog-chips]').addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button) return;
        event.preventDefault();
        const key = link.dataset.catalogRemove;
        apply(key ? {...current, [key]: key === 'bedrooms' ? '' : 0} : {area_id: 0, bedrooms: '', adults: 0, children: 0}, true);
        form.querySelector('button[type="submit"]').focus({preventScroll: true});
    });
    window.addEventListener('popstate', () => { closeGuests(); apply(fromURL()); });
    const toggle = root.querySelector('[data-catalog-toggle]');
    const map = root.querySelector('#catalog-map');
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const scroll = window.scrollY;
        map.hidden = !map.hidden;
        root.querySelector('[data-catalog-layout]').classList.toggle('is-map-hidden', map.hidden);
        toggle.textContent = map.hidden ? 'Show map' : 'Hide map';
        toggle.setAttribute('aria-expanded', String(!map.hidden));
        cards.forEach(card => card.querySelectorAll('img').forEach(image => {
            image.dataset.catalogSizes ||= image.sizes;
            image.sizes = map.hidden ? '(min-width: 1280px) 588px, (min-width: 600px) calc((100vw - 104px) / 2), calc(100vw - 40px)' : image.dataset.catalogSizes;
        }));
        document.dispatchEvent(new CustomEvent('aloha:catalog-map-toggle'));
        window.scrollTo({top: scroll, behavior: 'instant'});
    });
    apply(current);
})();
