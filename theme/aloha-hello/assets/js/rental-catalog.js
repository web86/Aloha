(() => {
    'use strict';
    const root = document.querySelector('[data-rental-catalog]');
    if (!root) return;
    const form = root.querySelector('[data-catalog-form]');
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
        form.elements.bedrooms.value = filters.bedrooms;
        form.elements.adults.value = filters.adults;
        form.elements.children.value = filters.children;
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
            chip.textContent = labels[key] + ' ×'; chip.setAttribute('aria-label', 'Remove ' + labels[key]);
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
    window.addEventListener('popstate', () => apply(fromURL()));
    const toggle = root.querySelector('[data-catalog-toggle]');
    const map = root.querySelector('#catalog-map');
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        map.hidden = !map.hidden;
        root.querySelector('[data-catalog-layout]').classList.toggle('is-map-hidden', map.hidden);
        toggle.textContent = map.hidden ? 'Show map' : 'Hide map';
        toggle.setAttribute('aria-expanded', String(!map.hidden));
        cards.forEach(card => card.querySelectorAll('img').forEach(image => {
            image.dataset.catalogSizes ||= image.sizes;
            image.sizes = map.hidden ? '(min-width: 1280px) 588px, (min-width: 600px) calc((100vw - 104px) / 2), calc(100vw - 40px)' : image.dataset.catalogSizes;
        }));
        document.dispatchEvent(new CustomEvent('aloha:catalog-map-toggle'));
    });
    apply(current);
})();
