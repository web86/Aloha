(() => {
    'use strict';
    const root = document.querySelector('[data-rental-catalog]');
    if (!root) return;
    const config = JSON.parse(root.querySelector('[data-catalog-map-config]').textContent);
    const surface = root.querySelector('[data-catalog-map-surface]');
    const message = root.querySelector('[data-catalog-map-message]');
    const aside = root.querySelector('#catalog-map');
    const preview = root.querySelector('[data-catalog-map-preview]');
    const zoomButtons = [...root.querySelectorAll('[data-catalog-zoom]')];
    const cards = new Map([...root.querySelectorAll('[data-catalog-item]')].map(card => [Number(card.dataset.catalogItem), card]));
    if (navigator.globalPrivacyControl === true) { message.textContent = config.privacy; return; }
    if (!config.key || !config.mapId || !config.points.length) return;
    let started = false;
    let failed = false;
    let script;
    let timeout;
    const fail = () => {
        failed = true; clearTimeout(timeout); script?.remove();
        surface.hidden = true; preview.hidden = true; message.hidden = false; message.textContent = config.error;
        zoomButtons.forEach(button => { button.disabled = true; });
        delete window.alohaCatalogMapsReady;
    };
    async function ready() {
        clearTimeout(timeout); delete window.alohaCatalogMapsReady;
        try {
            const api = window.google.maps;
            const [{Map, InfoWindow}, {AdvancedMarkerElement}, {LatLngBounds}] = await Promise.all([
                api.importLibrary('maps'), api.importLibrary('marker'), api.importLibrary('core'),
            ]);
            if (failed) return;
            surface.hidden = false; message.hidden = true;
            const map = new Map(surface, {mapId: config.mapId, center: config.points[0].position, zoom: 13,
                disableDefaultUI: true, gestureHandling: 'cooperative', clickableIcons: false});
            const bounds = new LatLngBounds();
            const info = new InfoWindow({headerDisabled: true, disableAutoPan: false, ariaLabel: 'Home preview', maxWidth: 280});
            let selected = null;
            const markers = config.points.map(point => {
                bounds.extend(point.position);
                const button = document.createElement('button');
                button.type = 'button'; button.setAttribute('aria-expanded', 'false');
                button.setAttribute('aria-controls', 'catalog-map-preview');
                const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                icon.setAttribute('viewBox', '0 0 16 16'); icon.setAttribute('aria-hidden', 'true');
                const path = document.createElementNS(icon.namespaceURI, 'path');
                path.setAttribute('d', 'm2 7 6-5 6 5v7H9v-4H7v4H2Z'); path.setAttribute('fill', 'none'); path.setAttribute('stroke', 'currentColor'); path.setAttribute('stroke-width', '1.4');
                icon.append(path);
                const text = document.createElement('span');
                button.append(icon, text);
                const marker = new AdvancedMarkerElement({map, position: point.position, title: point.title, collisionBehavior: 'OPTIONAL_AND_HIDES_LOWER_PRIORITY'});
                marker.append(button);
                const entry = {point, button, text, marker};
                button.addEventListener('click', () => select(entry));
                return entry;
            });
            preview.id = 'catalog-map-preview';
            function close(restoreFocus = false) {
                const previous = selected;
                selected = null; info.close(); preview.hidden = true; preview.replaceChildren();
                update();
                if (restoreFocus) previous?.button.focus({preventScroll: true});
            }
            function select(entry) {
                if (selected === entry) { close(); return; }
                close(); selected = entry; update();
                const {point} = entry;
                preview.replaceChildren();
                const link = document.createElement('a'); link.href = point.url;
                if (point.image) {
                    const image = document.createElement('img');
                    image.alt = point.image.alt; image.width = point.image.width; image.height = point.image.height;
                    image.sizes = '250px'; if (point.image.srcset) image.srcset = point.image.srcset;
                    image.src = point.image.src; image.decoding = 'async'; link.append(image);
                }
                const copy = document.createElement('div'); copy.className = 'aloha-catalog-preview-text';
                const title = document.createElement('strong'); title.textContent = point.title;
                const facts = [];
                if (point.facts?.bedrooms !== null && point.facts?.bedrooms !== undefined) facts.push(point.facts.bedrooms + ' bd');
                if (point.facts?.guests !== null && point.facts?.guests !== undefined) facts.push(point.facts.guests + ' guests');
                facts.push(point.price ? `from ${point.price}/night` : 'Check dates for pricing');
                const details = document.createElement('p'); details.textContent = facts.join(' · ');
                copy.append(title, details); link.append(copy);
                const dismiss = document.createElement('button'); dismiss.type = 'button'; dismiss.className = 'aloha-catalog-preview-close'; dismiss.textContent = '×'; dismiss.setAttribute('aria-label', 'Close home preview');
                dismiss.addEventListener('click', () => close(true));
                preview.append(link, dismiss); preview.hidden = false;
                preview.style.maxHeight = Math.max(100, surface.clientHeight - 90) + 'px';
                info.setOptions({maxWidth: Math.min(280, surface.clientWidth - 32)});
                info.setContent(preview);
                info.open({map, anchor: entry.marker, shouldFocus: true});
            }
            function update() {
                markers.forEach(entry => {
                    const {point, button, text, marker} = entry;
                    const match = cards.has(point.id) && !cards.get(point.id).hidden;
                    button.className = 'aloha-catalog-marker' + (match ? ' is-match' : ' is-neutral') + (selected === entry ? ' is-selected' : '');
                    text.textContent = match ? (point.price || 'View home') : '';
                    button.setAttribute('aria-label', `${point.title} — ${match ? 'in current results' : "doesn't match your filters"}${match && point.price ? ', from ' + point.price + ' per night' : ''}`);
                    button.setAttribute('aria-expanded', String(selected === entry));
                    marker.zIndex = selected === entry ? 1000 : match ? 10 : 1;
                    marker.collisionBehavior = selected === entry ? 'REQUIRED_AND_HIDES_OPTIONAL' : 'OPTIONAL_AND_HIDES_LOWER_PRIORITY';
                });
            }
            preview.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(true); } });
            surface.addEventListener('keydown', event => { if (event.key === 'Escape' && selected) { event.preventDefault(); event.stopPropagation(); close(true); } }, true);
            const fit = () => {
                if (aside.hidden || !surface.clientWidth) return;
                if (selected) { preview.style.maxHeight = Math.max(100, surface.clientHeight - 90) + 'px'; info.open({map, anchor: selected.marker, shouldFocus: false}); return; }
                if (markers.length > 1) map.fitBounds(bounds, {top: 100, right: 65, bottom: 90, left: 45});
                else map.setCenter(config.points[0].position);
            };
            zoomButtons.forEach(button => {
                button.disabled = false;
                button.addEventListener('click', () => map.setZoom(Math.max(2, Math.min(21, (map.getZoom() || 13) + Number(button.dataset.catalogZoom)))));
            });
            update(); fit();
            document.addEventListener('aloha:catalog-filter', () => { close(); update(); });
            const resize = () => { if (!aside.hidden) { api.event?.trigger(map, 'resize'); requestAnimationFrame(fit); } };
            document.addEventListener('aloha:catalog-map-toggle', () => { if (aside.hidden) close(); else resize(); });
            if ('ResizeObserver' in window) {
                let resizing;
                new ResizeObserver(() => { clearTimeout(resizing); resizing = setTimeout(resize, 100); }).observe(surface);
            }
        } catch { fail(); }
    }
    function show() {
        if (started || aside.hidden) return;
        started = true; message.textContent = config.loading;
        const oldFailure = window.gm_authFailure;
        window.gm_authFailure = () => { if (typeof oldFailure === 'function') oldFailure(); fail(); };
        timeout = setTimeout(fail, 12000);
        if (window.google?.maps?.importLibrary) { void ready(); return; }
        window.alohaCatalogMapsReady = ready;
        script = document.createElement('script');
        const url = new URL('https://maps.googleapis.com/maps/api/js');
        url.search = new URLSearchParams({key: config.key, callback: 'alohaCatalogMapsReady', loading: 'async', auth_referrer_policy: 'origin'});
        script.async = true; script.referrerPolicy = 'origin'; script.src = url; script.onerror = fail;
        document.head.append(script);
    }
    document.addEventListener('aloha:catalog-map-toggle', () => { if (!aside.hidden) show(); });
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (!entries.some(entry => entry.isIntersecting)) return;
            observer.disconnect(); show();
        }, {rootMargin: '200px'});
        observer.observe(aside);
    } else show();
})();
